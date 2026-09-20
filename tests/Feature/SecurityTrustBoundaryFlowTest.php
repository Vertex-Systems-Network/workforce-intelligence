<?php

namespace Tests\Feature;

use App\Models\EnterpriseIdentityProvider;
use App\Models\EnterpriseSsoState;
use App\Models\User;
use App\Services\Commerce\PlatformOperatorService;
use App\Services\Enterprise\OidcService;
use App\Services\Security\OutboundUrlGuard;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Exercises high-impact authentication and outbound-network trust boundaries. */
class SecurityTrustBoundaryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** An allowlisted email cannot grant operator access until the account is verified. */
    public function test_unverified_allowlisted_platform_operator_is_denied(): void
    {
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        config([
            'workintel.commerce.operator_emails' => [$owner->email],
            'workintel.commerce.operator_user_ids' => [],
        ]);

        $operators = app(PlatformOperatorService::class);
        $this->assertTrue($operators->isOperator($owner));

        $owner->forceFill(['email_verified_at' => null])->save();
        $this->assertFalse($operators->isOperator($owner->fresh()));
    }

    /** User-controlled outbound destinations cannot target local/private/reserved networks or embed credentials. */
    public function test_outbound_guard_rejects_private_reserved_ipv6_and_embedded_credentials(): void
    {
        config(['workintel.outbound.allow_private' => false]);
        $guard = app(OutboundUrlGuard::class);

        foreach ([
            'http://127.0.0.1/internal',
            'http://10.0.0.1/internal',
            'http://169.254.169.254/latest/meta-data',
            'http://[::1]/internal',
            'http://[::ffff:127.0.0.1]/internal',
            'http://user:secret@1.1.1.1/',
        ] as $url) {
            try {
                $guard->assertSafe($url);
                $this->fail("Expected outbound URL to be rejected: {$url}");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $options = $guard->httpOptions('https://1.1.1.1/health');
        $this->assertFalse($options['allow_redirects']);

        $ipv6 = $guard->assertSafe('https://[2606:4700:4700::1111]/health');
        $this->assertSame('2606:4700:4700::1111', $ipv6['host']);
    }

    /** A valid signed ID token with matching nonce and UserInfo subject completes the OIDC flow. */
    public function test_oidc_accepts_only_signed_nonce_bound_identity_with_matching_userinfo_subject(): void
    {
        [$provider, $state, $idToken] = $this->oidcFixture('oidc-subject-123');
        $this->fakeOidcProvider($idToken, 'oidc-subject-123', true);

        $request = Request::create('/api/v1/enterprise-sso/oidc/'.$provider->id.'/callback', 'GET', [
            'state' => $state,
            'code' => 'authorization-code',
        ]);

        $user = app(OidcService::class)->callback($provider, $request);

        $this->assertSame('owner@acme.test', $user->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('enterprise_sso_states', ['state_hash' => hash('sha256', $state)]);
    }

    /** UserInfo cannot substitute another subject after a valid ID token has been signed. */
    public function test_oidc_rejects_userinfo_subject_mismatch_even_with_valid_signed_id_token(): void
    {
        [$provider, $state, $idToken] = $this->oidcFixture('signed-subject');
        $this->fakeOidcProvider($idToken, 'different-userinfo-subject', true);

        $request = Request::create('/api/v1/enterprise-sso/oidc/'.$provider->id.'/callback', 'GET', [
            'state' => $state,
            'code' => 'authorization-code',
        ]);

        try {
            app(OidcService::class)->callback($provider, $request);
            $this->fail('OIDC subject mismatch must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    /** OIDC login fails unless UserInfo explicitly asserts email_verified=true. */
    public function test_oidc_requires_explicit_verified_email_claim(): void
    {
        [$provider, $state, $idToken] = $this->oidcFixture('oidc-subject-verified-email');
        $this->fakeOidcProvider($idToken, 'oidc-subject-verified-email', false);

        $request = Request::create('/api/v1/enterprise-sso/oidc/'.$provider->id.'/callback', 'GET', [
            'state' => $state,
            'code' => 'authorization-code',
        ]);

        try {
            app(OidcService::class)->callback($provider, $request);
            $this->fail('OIDC login must require email_verified=true.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    /** @return array{0:EnterpriseIdentityProvider,1:string,2:string} */
    private function oidcFixture(string $subject): array
    {
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $workspace = $owner->memberships()->where('status', 'active')->firstOrFail()->workspace;

        $issuer = 'https://1.1.1.1';
        $config = [
            'client_id' => 'workintel-test-client',
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/authorize',
            'token_endpoint' => $issuer.'/token',
            'userinfo_endpoint' => $issuer.'/userinfo',
        ];

        $provider = EnterpriseIdentityProvider::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'name' => 'Security Test OIDC',
            'type' => 'oidc',
            'status' => 'active',
            'domains' => ['acme.test'],
            'config_encrypted' => Crypt::encryptString(json_encode($config, JSON_THROW_ON_ERROR)),
            'jit_provisioning' => true,
            'default_role_slug' => 'employee',
            'created_by' => $owner->id,
        ]);

        $state = 'state-'.Str::random(48);
        $nonce = 'nonce-'.Str::random(32);

        EnterpriseSsoState::create([
            'state_hash' => hash('sha256', $state),
            'enterprise_identity_provider_id' => $provider->id,
            'code_verifier_encrypted' => Crypt::encryptString('test-code-verifier'),
            'nonce' => $nonce,
            'redirect_uri' => 'https://workintel.test/api/v1/enterprise-sso/oidc/'.$provider->id.'/callback',
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
        ]);

        [$privateKey, $jwk] = $this->rsaSigningKey();
        $idToken = $this->signedJwt([
            'iss' => $issuer,
            'sub' => $subject,
            'aud' => 'workintel-test-client',
            'exp' => time() + 300,
            'iat' => time(),
            'nonce' => $nonce,
            'amr' => ['pwd', 'mfa'],
        ], $privateKey);

        config(['testing.oidc_jwk' => $jwk]);

        return [$provider, $state, $idToken];
    }

    /** Fake one complete OIDC provider response set without external network access. */
    private function fakeOidcProvider(string $idToken, string $userinfoSubject, bool $emailVerified): void
    {
        $issuer = 'https://1.1.1.1';
        $jwk = config('testing.oidc_jwk');

        Http::fake([
            $issuer.'/.well-known/openid-configuration' => Http::response([
                'issuer' => $issuer,
                'authorization_endpoint' => $issuer.'/authorize',
                'token_endpoint' => $issuer.'/token',
                'userinfo_endpoint' => $issuer.'/userinfo',
                'jwks_uri' => $issuer.'/jwks',
            ]),
            $issuer.'/token' => Http::response([
                'access_token' => 'access-token',
                'token_type' => 'Bearer',
                'id_token' => $idToken,
            ]),
            $issuer.'/jwks' => Http::response(['keys' => [$jwk]]),
            $issuer.'/userinfo' => Http::response([
                'sub' => $userinfoSubject,
                'email' => 'owner@acme.test',
                'email_verified' => $emailVerified,
                'given_name' => 'Sarah',
                'family_name' => 'Chen',
            ]),
        ]);
    }

    /** @return array{0:\OpenSSLAsymmetricKey,1:array<string,string>} */
    private function rsaSigningKey(): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($privateKey);

        $details = openssl_pkey_get_details($privateKey);
        $this->assertIsArray($details);
        $this->assertArrayHasKey('rsa', $details);

        return [$privateKey, [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => 'test-key-1',
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ]];
    }

    /** Create one RS256 JWT fixture using the generated test key. */
    private function signedJwt(array $claims, \OpenSSLAsymmetricKey $privateKey): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'test-key-1'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $input = $header.'.'.$payload;
        $signed = openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $this->assertTrue($signed);

        return $input.'.'.$this->base64UrlEncode($signature);
    }

    /** Encode fixture bytes using unpadded base64url. */
    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
