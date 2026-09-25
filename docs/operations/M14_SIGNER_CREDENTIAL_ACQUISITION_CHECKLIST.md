# M14 signer credential acquisition and production-release placement checklist

This checklist is the external-credential bridge for Issue #62. It defines what must be acquired, what must **not** be acquired, and where approved credential references belong in GitHub.

## Non-negotiable identity rule

Use only the legal entity that actually owns and distributes WorkIntel. Do not use a relative's identity, an unrelated company, or a mismatched legal entity to obtain signer credentials. Legal names, business registration, addresses, websites, domains and validation contacts must be truthful and consistent with the signer account.

## Apple — acquisition checklist

### 1. Organization enrollment

Enroll the software-owning legal entity in the Apple Developer Program as an **Organization**.

Apple currently requires organization applicants to have:
- legal-entity status;
- a D-U-N-S number;
- a person with legal authority to bind the organization;
- a work email on the organization domain;
- a public, functional organization website.

Apple Developer Program membership is currently USD 99/year (local pricing can vary).

Official references:
- https://developer.apple.com/help/account/membership/program-enrollment
- https://developer.apple.com/help/account/membership/D-U-N-S

### 2. Developer ID Application certificate

For software distributed outside the Mac App Store, use a **Developer ID Application** certificate.

Current Apple contract:
- Developer ID Application is the correct certificate type for a Mac app distributed outside the Mac App Store;
- the Account Holder can create Developer ID certificates;
- Apple allows multiple Developer ID Application certificates per team.

Preferred local-signing acquisition sequence:
1. On an organization-controlled Mac, create the certificate signing request/private key using the Apple-supported certificate flow.
2. In Certificates, Identifiers & Profiles, create a **Developer ID Application** certificate.
3. Install the downloaded certificate into the same keychain that holds the generated private key.
4. Confirm Keychain shows the certificate with its private key under **My Certificates**.
5. Export the certificate + private key as a password-protected PKCS#12/P12.
6. Store the original P12 and password in an organization-controlled password/secrets vault; GitHub receives only the copy required for the protected release environment.
7. Derive and independently record the SHA-256 fingerprint of the leaf Developer ID certificate.
8. Record the exact signing identity string shown by macOS, normally beginning with `Developer ID Application:`.

Official references:
- https://developer.apple.com/help/account/certificates/create-developer-id-certificates
- https://developer.apple.com/help/account/certificates/certificates-overview

### 3. Apple notarization API key

Use an **App Store Connect Team API key**, not an Individual API key, for the automated notarization lane.

Important current Apple facts:
- App Store Connect API access is requested by the Account Holder;
- Account Holder or Admin can generate Team keys;
- the private `.p8` key is downloadable only once;
- Apple explicitly states Individual API keys cannot use `notaryTool`.

Acquisition sequence:
1. App Store Connect → Users and Access → Integrations.
2. If API access is not enabled, Account Holder requests access first.
3. Create a **Team Key** with the least role that supports the notarization workflow.
4. Download the `.p8` exactly once and store the original in the organization secrets vault.
5. Record:
   - Key ID;
   - Issuer ID;
   - private `.p8` file.
6. Never commit the `.p8` file or paste it into issues, PRs, chat or README.

Official references:
- https://developer.apple.com/documentation/AppStoreConnectAPI/creating-api-keys-for-app-store-connect-api
- https://developer.apple.com/help/app-store-connect/get-started/app-store-connect-api
- https://developer.apple.com/documentation/security/notarizing-macos-software-before-distribution

## Apple — exact GitHub placement

GitHub → Repository → Settings → Environments → **production-release**

### Environment secrets

Add exactly these Apple signer/notary secrets when the real material exists:

- `WORKINTEL_APPLE_DEVELOPER_ID_P12_B64`
- `WORKINTEL_APPLE_DEVELOPER_ID_P12_PASSWORD`
- `WORKINTEL_APPLE_SIGNING_IDENTITY`
- `WORKINTEL_APPLE_NOTARY_KEY_P8_B64`
- `WORKINTEL_APPLE_NOTARY_KEY_ID`
- `WORKINTEL_APPLE_NOTARY_ISSUER_ID`

### Environment variable

Add:

- `WORKINTEL_APPLE_SIGNING_CERT_SHA256`

The fingerprint must be the approved lowercase 64-hex SHA-256 of the Developer ID **leaf** certificate.

### Apple placement rules

- Do not place Apple signer/notary secrets at repository scope.
- Do not place the Apple certificate fingerprint at repository-variable scope.
- Do not store raw P12/P8 files in Git.
- Preserve the existing `production-release` required-reviewer/self-review protections.
- After placement, run **M14 Apple Signer Material Readiness Evidence** from protected `main` before any real signing/notarization attempt.

## Windows — acquisition compatibility gate

### Critical 2026 public-trust rule

For publicly trusted Code Signing certificates, CA/B Forum requirements require the subscriber private key to be generated, stored and used in a compliant hardware crypto module / HSM-backed cloud or signing-service boundary.

For Code Signing certificates issued on or after 2026-03-01, the maximum validity is 460 days.

Therefore:

**Do not purchase a product on the assumption that the public-trust private key can be downloaded/exported as a normal portable PFX and then stored in GitHub Actions.**

The current repository PFX lane is mechanically useful for material validation and for private/internal PKI scenarios, but it must not be treated as proof that a modern publicly trusted Windows signing certificate can or should be exported into GitHub secrets.

Official reference:
- https://cabforum.org/working-groups/code-signing/requirements/

### 1. Choose an HSM/cloud signing route

Before buying a Windows certificate/signing subscription, obtain written confirmation from the selected CA/signing service that:

- it can validate and issue/sign for the actual WorkIntel legal entity and jurisdiction;
- the signing key remains inside a CA/B-compliant HSM/signing service;
- Windows Authenticode / PE executable signing is supported;
- RFC3161 timestamping is supported;
- unattended CI/CD signing from GitHub Actions is supported;
- the service can expose a stable signer/certificate identity that our workflow can pin;
- access can be limited to the production release workflow/environment;
- API/client credentials can be rotated and audited.

A current example of the correct architecture class is DigiCert KeyLocker, whose documentation supports GitHub Actions and HSM-backed remote signing credentials. Provider eligibility for the legal entity/jurisdiction must be confirmed before purchase.

References:
- https://docs.digicert.com/en/digicert-keylocker/overview/requirements.html
- https://docs.digicert.com/en/digicert-keylocker/ci-cd-integrations-and-deployment-pipelines/scripts/github/scripts-for-signing-using-pkcs11-library-on-github.html

### 2. Do not use Microsoft Artifact Signing as the assumed Pakistan route

Microsoft currently lists Public Trust Artifact Signing availability for organizations in specific countries/regions, and Pakistan is not in that published Public Trust list. Do not build the release plan around Microsoft Public Trust Artifact Signing unless the actual software-owning legal entity independently satisfies Microsoft's published geographic eligibility.

Reference:
- https://learn.microsoft.com/en-us/azure/artifact-signing/quickstart

### 3. Existing PFX variables — keep unpopulated for public-trust until architecture is resolved

Current repository names:

Environment secrets:
- `WORKINTEL_WINDOWS_SIGNING_PFX_B64`
- `WORKINTEL_WINDOWS_SIGNING_PFX_PASSWORD`

Environment variables:
- `WORKINTEL_WINDOWS_SIGNING_CERT_SHA256`
- `WORKINTEL_WINDOWS_TIMESTAMP_URL`

For a modern **public-trust** certificate, do **not** populate these PFX secrets merely to satisfy the existing workflow. First select the HSM/cloud signing provider and merge a provider-specific signing integration that never exports the public-trust private key.

The existing PFX lane may remain valid only when the selected certificate policy legitimately permits an organization-controlled exportable PFX, such as a private/internal PKI use case. Such a private certificate is not a substitute for public-trust distribution to Windows customers.

## Proposed Windows GitHub placement after provider selection

The exact secret names must be defined in a provider-specific PR. The design must follow these rules:

- provider authentication secrets: **production-release environment secrets only**;
- provider host/account/profile/keypair identifiers that are non-secret: **production-release environment variables**;
- no provider signing credentials at repository scope;
- no long-lived private signing key in GitHub;
- prefer short-lived/federated authentication when the provider supports it;
- pin the approved signer/certificate identity in a non-secret environment variable;
- preserve required-reviewer protection and `prevent_self_review=true`;
- use an HTTPS RFC3161 timestamp service or the provider's documented trusted timestamp path;
- readiness workflow first, real signing workflow second, publication only after signature verification.

## Combined production-release placement matrix

| Platform | Name | GitHub location | Add now? |
| --- | --- | --- | --- |
| Apple | `WORKINTEL_APPLE_DEVELOPER_ID_P12_B64` | Environment secret | Only after real P12 exists |
| Apple | `WORKINTEL_APPLE_DEVELOPER_ID_P12_PASSWORD` | Environment secret | Only after real P12 exists |
| Apple | `WORKINTEL_APPLE_SIGNING_IDENTITY` | Environment secret | Only after certificate exists |
| Apple | `WORKINTEL_APPLE_NOTARY_KEY_P8_B64` | Environment secret | Only after Team API key exists |
| Apple | `WORKINTEL_APPLE_NOTARY_KEY_ID` | Environment secret | Only after Team API key exists |
| Apple | `WORKINTEL_APPLE_NOTARY_ISSUER_ID` | Environment secret | Only after Team API key exists |
| Apple | `WORKINTEL_APPLE_SIGNING_CERT_SHA256` | Environment variable | Only after fingerprint independently verified |
| Windows | `WORKINTEL_WINDOWS_SIGNING_PFX_B64` | Environment secret | **No for public-trust until provider compatibility is proven** |
| Windows | `WORKINTEL_WINDOWS_SIGNING_PFX_PASSWORD` | Environment secret | **No for public-trust until provider compatibility is proven** |
| Windows | `WORKINTEL_WINDOWS_SIGNING_CERT_SHA256` | Environment variable | After signer identity is fixed |
| Windows | `WORKINTEL_WINDOWS_TIMESTAMP_URL` | Environment variable | After approved provider/timestamp route is fixed |

## Safe execution order

1. Verify legal entity identity package and business-domain email/website.
2. Complete Apple organization enrollment.
3. Acquire Apple Developer ID Application certificate and Team API notarization key.
4. Independently verify Apple certificate fingerprint.
5. Place Apple values only in `production-release`.
6. Run Apple readiness workflow from protected `main`.
7. For Windows, select a CA/B-compliant HSM/cloud signing provider that can onboard the actual legal entity.
8. **Before purchase**, confirm GitHub Actions automation and jurisdiction eligibility in writing.
9. Merge provider-specific Windows remote-signing integration; do not adapt by exporting a public-trust private key.
10. Place provider auth only in `production-release`.
11. Run Windows material/provider readiness evidence.
12. Only after both readiness paths are green, proceed to actual signing/notarization evidence.
13. Publish only after exact-head signature/notarization verification and existing release gates pass.

## Never do

- Never paste signer credentials into chat, issues, PR bodies, README or source files.
- Never use a relative's or unrelated entity's identity to pass business validation.
- Never weaken `production-release` reviewer controls to get a workflow through.
- Never fabricate a fingerprint or signer identity.
- Never treat a private/internal Windows certificate as public-trust evidence.
- Never run real signing/notarization/publication until the corresponding real material has passed the readiness lane.
