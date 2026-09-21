<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EnterpriseIdentityProvider;
use App\Models\Workspace;
use App\Services\Enterprise\OidcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Provides enterprise SSO controller behavior within the WorkIntel application. */
class EnterpriseSsoController extends Controller
{
    /** Start a configured enterprise SSO flow. */
    public function start(Request $request, string $workspace, EnterpriseIdentityProvider $provider, OidcService $oidc): RedirectResponse
    {
        $resolvedWorkspace = Workspace::query()->where('slug', $workspace)->firstOrFail();
        abort_unless(
            (int) $provider->workspace_id === (int) $resolvedWorkspace->id && $provider->status === 'active',
            404
        );

        if ($provider->type === 'oidc') {
            $authorization = $oidc->authorizationRequest($provider);
            $secure = $request->isSecure() || (bool) config('session.secure', false);
            $cookie = cookie(
                $authorization['cookie_name'],
                $authorization['cookie_value'],
                10,
                $authorization['cookie_path'],
                null,
                $secure,
                true,
                false,
                'lax'
            );

            return redirect()->away($authorization['url'])->withCookie($cookie);
        }

        abort(501, 'Direct SAML assertion runtime is not enabled. Use the provider metadata endpoint and configure the signed SAML adapter before activation.');
    }

    /** Complete an OIDC callback and clear its single-use browser-state cookie. */
    public function oidcCallback(Request $request, EnterpriseIdentityProvider $provider, OidcService $oidc): RedirectResponse
    {
        $oidc->callback($provider, $request);
        $secure = $request->isSecure() || (bool) config('session.secure', false);
        $expired = cookie(
            $oidc->browserStateCookieName($provider),
            '',
            -2628000,
            $oidc->browserStateCookiePath($provider),
            null,
            $secure,
            true,
            false,
            'lax'
        );

        return redirect(rtrim(config('app.url'), '/').'/app')->withCookie($expired);
    }

    /** Return the service-provider metadata for a configured SAML provider. */
    public function samlMetadata(EnterpriseIdentityProvider $provider): Response
    {
        abort_unless($provider->type === 'saml', 404);
        $entity = e(rtrim(config('app.url'), '/').'/saml/sp/'.$provider->uuid);
        $acs = e(route('enterprise.saml.acs', ['provider' => $provider->id]));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".
            '<EntityDescriptor xmlns="urn:oasis:names:tc:SAML:2.0:metadata" entityID="'.$entity.'"><SPSSODescriptor AuthnRequestsSigned="false" WantAssertionsSigned="true" protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol"><NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress</NameIDFormat><AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="'.$acs.'" index="1" isDefault="true"/></SPSSODescriptor></EntityDescriptor>';

        return response($xml, 200, ['Content-Type' => 'application/samlmetadata+xml']);
    }

    /** Fail closed until a signed SAML assertion runtime is installed. */
    public function samlAcs(Request $request, EnterpriseIdentityProvider $provider)
    {
        abort_unless($provider->type === 'saml', 404);

        return response()->json([
            'message' => 'SAML provider configuration is installed, but signed assertion processing is intentionally disabled until a standards-compliant SAML runtime adapter is installed and configured.',
        ], 501);
    }
}
