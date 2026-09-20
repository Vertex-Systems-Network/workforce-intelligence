<?php

namespace App\Services\Enterprise;

use App\Models\EnterpriseIdentityProvider;
use App\Models\EnterpriseSsoState;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\Security\OutboundUrlGuard;
use App\Services\Security\SecurityEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Provides standards-oriented OIDC authorization-code authentication with PKCE and signed ID-token verification. */
class OidcService
{
    public function __construct(private readonly OutboundUrlGuard $guard) {}

    /** Return the decrypted provider configuration. */
    public function providerConfig(EnterpriseIdentityProvider $provider): array
    {
        return json_decode(Crypt::decryptString($provider->config_encrypted), true) ?: [];
    }

    /** Create an OIDC authorization request with state, nonce, and PKCE. */
    public function authorizationUrl(EnterpriseIdentityProvider $provider): string
    {
        abort_unless($provider->type === 'oidc' && $provider->status === 'active', 404);
        $config = $this->providerConfig($provider);
        $this->assertRequiredConfig($config);

        foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $key) {
            $this->assertOidcEndpoint((string) $config[$key]);
        }

        $state = Str::random(64);
        $verifier = Str::random(96);
        $nonce = Str::random(40);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $redirect = route('enterprise.oidc.callback', ['provider' => $provider->id]);

        EnterpriseSsoState::create([
            'state_hash' => hash('sha256', $state),
            'enterprise_identity_provider_id' => $provider->id,
            'code_verifier_encrypted' => Crypt::encryptString($verifier),
            'nonce' => $nonce,
            'redirect_uri' => $redirect,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        $query = http_build_query([
            'client_id' => $config['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $redirect,
            'scope' => $config['scopes'] ?? 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        return rtrim($config['authorization_endpoint'], '?').'?'.$query;
    }

    /** Complete OIDC login after validating discovery metadata, the signed ID token, nonce, and UserInfo subject. */
    public function callback(EnterpriseIdentityProvider $provider, Request $request): User
    {
        abort_unless($provider->type === 'oidc' && $provider->status === 'active', 404);

        $state = (string) $request->query('state');
        $code = (string) $request->query('code');
        abort_unless($state !== '' && $code !== '', 422, 'OIDC callback is missing state or authorization code.');

        $stateRow = EnterpriseSsoState::query()
            ->where('state_hash', hash('sha256', $state))
            ->where('enterprise_identity_provider_id', $provider->id)
            ->first();

        abort_unless($stateRow && $stateRow->expires_at->isFuture(), 422, 'OIDC state is invalid or expired.');

        $config = $this->providerConfig($provider);
        $this->assertRequiredConfig($config);
        $discovery = $this->discovery($config);

        $verifier = Crypt::decryptString($stateRow->code_verifier_encrypted);
        $redirectUri = $stateRow->redirect_uri;
        $expectedNonce = (string) $stateRow->nonce;

        // Consume state before the first token exchange so the authorization response cannot be replayed.
        $stateRow->delete();

        $tokenEndpoint = (string) $discovery['token_endpoint'];
        $tokenRequest = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $config['client_id'],
            'code_verifier' => $verifier,
        ];
        if (filled($config['client_secret'] ?? null)) {
            $tokenRequest['client_secret'] = $config['client_secret'];
        }

        $token = Http::withOptions($this->guard->httpOptions($tokenEndpoint))
            ->asForm()
            ->timeout(12)
            ->post($tokenEndpoint, $tokenRequest);

        abort_unless(
            $token->successful()
                && filled($token->json('access_token'))
                && filled($token->json('id_token')),
            422,
            'OIDC token exchange did not return the required access and ID tokens.'
        );

        $idClaims = $this->verifyIdToken(
            (string) $token->json('id_token'),
            $config,
            $discovery,
            $expectedNonce
        );

        $userinfoEndpoint = (string) $discovery['userinfo_endpoint'];
        $info = Http::withOptions($this->guard->httpOptions($userinfoEndpoint))
            ->withToken((string) $token->json('access_token'))
            ->acceptJson()
            ->timeout(12)
            ->get($userinfoEndpoint);

        abort_unless($info->successful(), 422, 'OIDC user info request failed.');

        $profile = $info->json();
        abort_unless(is_array($profile), 422, 'OIDC provider returned an invalid UserInfo response.');

        $subject = (string) ($profile['sub'] ?? '');
        abort_unless($subject !== '' && hash_equals((string) $idClaims['sub'], $subject), 422, 'OIDC UserInfo subject does not match the signed ID token.');

        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 422, 'OIDC provider did not return a valid email address.');
        abort_unless(($profile['email_verified'] ?? null) === true, 422, 'OIDC provider must explicitly confirm that the email address is verified.');

        $domains = $provider->domains ?? [];
        if ($domains) {
            $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
            abort_unless(in_array($domain, array_map('strtolower', $domains), true), 403, 'Your email domain is not allowed for this identity provider.');
        }

        $workspaceId = (int) $provider->workspace_id;
        $user = User::query()->where('email', $email)->first();
        $member = $user
            ? WorkspaceMember::query()
                ->where('workspace_id', $workspaceId)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first()
            : null;

        // Never let a workspace-controlled IdP claim an existing global account that is not already a member.
        if ($user && ! $member) {
            abort(403, 'This existing WorkIntel account is not linked to this workspace. Ask an administrator to invite or link the account before using SSO.');
        }

        if (! $user) {
            abort_unless($provider->jit_provisioning, 403, 'No WorkIntel user exists for this SSO identity.');
            $names = $this->names($profile);
            $user = User::create([
                'first_name' => $names[0],
                'last_name' => $names[1],
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(64)),
                'timezone' => 'UTC',
                'status' => 'active',
            ]);

            $member = WorkspaceMember::create([
                'workspace_id' => $workspaceId,
                'user_id' => $user->id,
                'job_title' => 'SSO User',
                'employment_type' => 'full_time',
                'employment_stage' => 'active',
                'joining_date' => today(),
                'status' => 'active',
                'timezone' => $user->timezone,
            ]);

            $role = Role::query()
                ->where('workspace_id', $workspaceId)
                ->where('status', 'active')
                ->where('slug', $provider->default_role_slug)
                ->first()
                ?: Role::query()->where('workspace_id', $workspaceId)->where('status', 'active')->where('slug', 'employee')->first();

            if ($role) {
                $member->roles()->sync([$role->id]);
            }
        } else {
            abort_unless($user->status === 'active', 403, 'This WorkIntel account is not active.');
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
            $request->session()->put('enterprise_sso_workspace_id', $workspaceId);

            if (($config['trust_idp_mfa'] ?? false) && $this->idTokenProvesMfa($idClaims)) {
                $request->session()->put('mfa_verified_at', now()->toIso8601String());
            }
        }

        $user->forceFill(['last_login_at' => now()])->save();
        app(SecurityEventService::class)->record(
            $member->workspace,
            $user,
            'auth.sso_login_succeeded',
            'info',
            $request,
            ['provider_id' => $provider->id, 'provider_type' => 'oidc', 'subject_hash' => hash('sha256', $subject)]
        );

        return $user;
    }

    /** Validate OIDC discovery and endpoint consistency without authenticating a user. */
    public function test(EnterpriseIdentityProvider $provider): array
    {
        $config = $this->providerConfig($provider);
        if ($provider->type === 'saml') {
            return [
                'ok' => true,
                'runtime' => 'configuration_only',
                'message' => 'SAML metadata/configuration is valid at the WorkIntel layer. Signed assertion runtime requires a standards-compliant SAML adapter.',
            ];
        }

        $this->assertRequiredConfig($config);
        $discovery = $this->discovery($config);

        return [
            'ok' => true,
            'status' => 200,
            'issuer' => $discovery['issuer'],
            'authorization_endpoint' => $discovery['authorization_endpoint'],
            'token_endpoint' => $discovery['token_endpoint'],
            'userinfo_endpoint' => $discovery['userinfo_endpoint'],
            'jwks_uri' => $discovery['jwks_uri'],
        ];
    }

    /** @return array<string,mixed> */
    private function discovery(array $config): array
    {
        $issuer = (string) $config['issuer'];
        $this->assertOidcEndpoint($issuer);

        $url = rtrim($issuer, '/').'/.well-known/openid-configuration';
        $response = Http::withOptions($this->guard->httpOptions($url))
            ->acceptJson()
            ->timeout(8)
            ->get($url);

        abort_unless($response->successful(), 422, 'OIDC discovery request failed.');
        $discovery = $response->json();
        abort_unless(is_array($discovery), 422, 'OIDC discovery returned invalid JSON.');
        abort_unless(($discovery['issuer'] ?? null) === $issuer, 422, 'OIDC discovery issuer does not exactly match the configured issuer.');

        foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri'] as $key) {
            abort_unless(filled($discovery[$key] ?? null), 422, "OIDC discovery is missing {$key}.");
            $this->assertOidcEndpoint((string) $discovery[$key]);
        }

        foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $key) {
            abort_unless(
                hash_equals((string) $config[$key], (string) $discovery[$key]),
                422,
                "Configured OIDC {$key} does not match provider discovery metadata."
            );
        }

        return $discovery;
    }

    /** @return array<string,mixed> */
    private function verifyIdToken(string $jwt, array $config, array $discovery, string $expectedNonce): array
    {
        $segments = explode('.', $jwt);
        abort_unless(count($segments) === 3, 422, 'OIDC ID token is malformed.');

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;
        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        $claims = json_decode($this->base64UrlDecode($encodedPayload), true);
        $signature = $this->base64UrlDecode($encodedSignature);

        abort_unless(is_array($header) && is_array($claims), 422, 'OIDC ID token contains invalid JSON.');
        abort_unless(($header['alg'] ?? null) === 'RS256', 422, 'OIDC ID token must use RS256.');
        abort_unless(filled($header['kid'] ?? null), 422, 'OIDC ID token is missing a key identifier.');

        abort_unless(($claims['iss'] ?? null) === $config['issuer'], 422, 'OIDC ID token issuer is invalid.');

        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        abort_unless(in_array($config['client_id'], $audiences, true), 422, 'OIDC ID token audience is invalid.');
        if (count($audiences) > 1) {
            abort_unless(($claims['azp'] ?? null) === $config['client_id'], 422, 'OIDC ID token authorized party is invalid.');
        }

        $now = time();
        abort_unless(is_numeric($claims['exp'] ?? null) && (int) $claims['exp'] >= $now - 60, 422, 'OIDC ID token has expired.');
        abort_unless(is_numeric($claims['iat'] ?? null) && (int) $claims['iat'] <= $now + 60, 422, 'OIDC ID token issued-at time is invalid.');
        abort_unless(
            filled($claims['nonce'] ?? null) && hash_equals($expectedNonce, (string) $claims['nonce']),
            422,
            'OIDC ID token nonce is invalid.'
        );
        abort_unless(filled($claims['sub'] ?? null), 422, 'OIDC ID token subject is missing.');

        $jwksUrl = (string) $discovery['jwks_uri'];
        $jwksResponse = Http::withOptions($this->guard->httpOptions($jwksUrl))
            ->acceptJson()
            ->timeout(8)
            ->get($jwksUrl);

        abort_unless($jwksResponse->successful(), 422, 'OIDC signing-key request failed.');
        $keys = $jwksResponse->json('keys');
        abort_unless(is_array($keys), 422, 'OIDC signing-key response is invalid.');

        $jwk = collect($keys)->first(
            fn ($key) => is_array($key)
                && ($key['kid'] ?? null) === $header['kid']
                && ($key['kty'] ?? null) === 'RSA'
                && in_array($key['use'] ?? 'sig', ['sig', null], true)
        );

        abort_unless(is_array($jwk) && filled($jwk['n'] ?? null) && filled($jwk['e'] ?? null), 422, 'OIDC signing key was not found.');

        $publicKey = openssl_pkey_get_public($this->rsaJwkToPem($jwk));
        abort_unless($publicKey !== false, 422, 'OIDC signing key could not be loaded.');

        $verified = openssl_verify(
            $encodedHeader.'.'.$encodedPayload,
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        abort_unless($verified === 1, 422, 'OIDC ID token signature is invalid.');

        return $claims;
    }

    private function assertRequiredConfig(array $config): void
    {
        foreach (['client_id', 'issuer', 'authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $key) {
            abort_unless(filled($config[$key] ?? null), 422, "OIDC provider is missing {$key}.");
        }
    }

    private function assertOidcEndpoint(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        $allowLocalHttp = app()->environment(['local', 'testing'])
            && (bool) config('workintel.outbound.allow_private', false);

        abort_unless($scheme === 'https' || ($allowLocalHttp && $scheme === 'http'), 422, 'OIDC endpoints must use HTTPS.');
        $this->guard->assertSafe($url);
    }

    /** Return true only when signed ID-token authentication-method claims explicitly indicate MFA. */
    private function idTokenProvesMfa(array $claims): bool
    {
        $methods = array_map('strtolower', array_filter((array) ($claims['amr'] ?? []), 'is_string'));

        return count(array_intersect($methods, ['mfa', 'otp', 'totp', 'hwk', 'swk'])) > 0;
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);
        abort_unless($decoded !== false, 422, 'OIDC token encoding is invalid.');

        return $decoded;
    }

    /** Convert an RSA JWK into an X.509 SubjectPublicKeyInfo PEM key accepted by OpenSSL. */
    private function rsaJwkToPem(array $jwk): string
    {
        $modulus = $this->base64UrlDecode((string) $jwk['n']);
        $exponent = $this->base64UrlDecode((string) $jwk['e']);

        $rsaKey = $this->asn1Sequence(
            $this->asn1Integer($modulus).
            $this->asn1Integer($exponent)
        );

        $rsaAlgorithmIdentifier = hex2bin('300d06092a864886f70d0101010500');
        abort_unless($rsaAlgorithmIdentifier !== false, 422, 'OIDC RSA algorithm encoding failed.');

        $subjectPublicKeyInfo = $this->asn1Sequence(
            $rsaAlgorithmIdentifier.
            $this->asn1BitString($rsaKey)
        );

        return "-----BEGIN PUBLIC KEY-----\n".
            chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n").
            "-----END PUBLIC KEY-----\n";
    }

    private function asn1Integer(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }
        if ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".$this->asn1Length(strlen($bytes)).$bytes;
    }

    private function asn1Sequence(string $bytes): string
    {
        return "\x30".$this->asn1Length(strlen($bytes)).$bytes;
    }

    private function asn1BitString(string $bytes): string
    {
        $value = "\x00".$bytes;

        return "\x03".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $encoded = '';
        while ($length > 0) {
            $encoded = chr($length & 0xff).$encoded;
            $length >>= 8;
        }

        return chr(0x80 | strlen($encoded)).$encoded;
    }

    /** @return array{0:string,1:string} */
    private function names(array $profile): array
    {
        $first = trim((string) ($profile['given_name'] ?? ''));
        $last = trim((string) ($profile['family_name'] ?? ''));

        if (! $first) {
            $parts = preg_split('/\s+/', trim((string) ($profile['name'] ?? 'SSO User')));
            $first = array_shift($parts) ?: 'SSO';
            $last = implode(' ', $parts) ?: 'User';
        }

        return [$first, $last ?: 'User'];
    }
}
