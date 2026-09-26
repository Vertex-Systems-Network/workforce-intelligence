# M14 Windows Public-Trust Provider Qualification Inquiry Drafts

**Purpose:** obtain written confirmation before any Windows public-trust code-signing purchase, credential placement or provider-specific repository integration.

**Repository:** `Vertex-Systems-Network/workforce-intelligence`  
**Issue:** #62  
**Protected-main baseline:** `06db81372dbac02a94f9869abaead8392bcf9aeb`

These drafts intentionally request technical and commercial confirmation without sharing secrets, identity documents, certificate material or credentials.

---

## Draft A — SSL.com eSigner qualification inquiry

**Subject:** Qualification inquiry — OV Code Signing + eSigner for GitHub Actions (Pakistan company)

Hello SSL.com Team,

We are evaluating SSL.com OV Code Signing with eSigner for an automated Windows release pipeline.

Our company is a Pakistan-registered private limited software company, and our Windows application releases are built and verified through GitHub Actions. Before placing an order, we need written confirmation that the following deployment model is supported.

### Legal-entity and product eligibility

1. Can a Pakistan-registered private limited company obtain your current **OV Code Signing certificate** and use it with **eSigner for Code**?
2. Can the certificate publisher/subject be issued to the exact validated legal company name?
3. Which company-validation documents and contacts are normally required for a Pakistani organization?
4. Are there any Pakistan-specific restrictions, manual review requirements, additional verification steps, or product limitations that we should know about before purchasing?

### HSM / private-key custody

5. Please confirm that the public-trust Code Signing private key remains inside your compliant HSM/cloud-signing service and is never exported to our GitHub runner or downloaded as a portable PFX.
6. Please confirm the current HSM/FIPS compliance level used for eSigner Code Signing.
7. Can signing access be restricted to one Code Signing credential/certificate and audited per signing request?

### GitHub Actions / unattended signing

8. Do you currently support Windows Authenticode signing of `.exe`, `.msi`, and related Windows release artifacts from **GitHub-hosted Windows runners** without a USB token?
9. What is the current recommended **non-interactive/headless authentication model** for GitHub Actions in 2026?
10. Does the recommended CI method require a username/password/TOTP combination, or is there a service credential/API/client-certificate/OIDC-style alternative?
11. Which provider credentials are required in GitHub Actions, and which of those can be scoped, rotated, revoked, or limited to one signing credential?
12. Can the authentication credentials be rotated without replacing the public Code Signing certificate?

### Certificate identity verification

13. Can our CI workflow retrieve or otherwise verify the public leaf Code Signing certificate used for signing?
14. Can we independently pin and verify its **SHA-256 fingerprint** before accepting a signed artifact?
15. Is the publisher/subject identity exposed in a deterministic way for post-signing verification?

### Timestamping

16. Is **RFC3161 timestamping** supported for Windows Authenticode?
17. What exact timestamp endpoint/flow do you recommend for automated SignTool-based signing?
18. Does successful timestamping allow an already-signed application to remain valid after the Code Signing certificate later expires, assuming the certificate was valid at signing time?

### Audit and security controls

19. Do you provide audit logs showing the signing credential/certificate used, request time, result, account/operator identity, and a unique request/job identifier?
20. Are IP restrictions, signing-policy restrictions, approval policies, file/hash restrictions, or similar controls available?
21. Can we restrict CI signing credentials to production use and keep them separate from interactive account administration?
22. What is your recommended recovery/revocation process if a CI authentication credential is exposed?

### Pricing / limits / onboarding

23. Please provide the current first-year and renewal price for:
   - one OV Code Signing certificate;
   - the lowest eSigner plan suitable for GitHub Actions;
   - included annual signing operations;
   - any required validation/onboarding fee;
   - additional signing-operation pricing.
24. Are failed signing attempts counted against the annual signing quota?
25. Are there rate limits or concurrency limits relevant to CI/CD?
26. What is the normal validation/onboarding timeline for a Pakistani private limited company?

Our target architecture is:

`GitHub protected production-release environment -> provider authentication -> SSL.com HSM signing -> Authenticode verification -> RFC3161 timestamp verification -> release publication gate`

We will not place any private Code Signing key in GitHub.

Please confirm whether this architecture is supported and point us to the current production documentation for the recommended GitHub Actions integration.

Regards,  
**Vertex Systems Network (Pvt.) Ltd.**  
Software / SaaS Company  
Pakistan

---

## Draft B — DigiCert KeyLocker / Software Trust Manager qualification inquiry

**Subject:** Qualification inquiry — DigiCert Code Signing + KeyLocker / Software Trust Manager for GitHub Actions (Pakistan company)

Hello DigiCert Team,

We are evaluating DigiCert public Code Signing with KeyLocker / Software Trust Manager for an automated Windows release pipeline.

Our company is a Pakistan-registered private limited software company, and our Windows application releases are built and verified through GitHub Actions. Before purchase, we need written confirmation that the following deployment model is supported.

### Legal-entity and product eligibility

1. Can a Pakistan-registered private limited company obtain your current **public Code Signing certificate** and use it with **KeyLocker / Software Trust Manager**?
2. Can the publisher/subject be issued to the exact validated legal company name?
3. Which company-validation documents and contacts are normally required for a Pakistani organization?
4. Are there any Pakistan-specific restrictions, additional verification requirements, or product limitations for Code Signing, KeyLocker, or Software Trust Manager?

### HSM / private-key custody

5. Please confirm that the public-trust Code Signing private key remains inside DigiCert-managed compliant HSM/keypair protection and is not exported to our GitHub runner as a normal portable PFX.
6. Please confirm the current compliance/HSM assurance used for this service.
7. Can a Code Signing keypair be restricted to specific signing policies, applications, teams, or CI identities?

### GitHub Actions / unattended signing

8. Do you currently support Windows Authenticode signing of `.exe`, `.msi`, and related artifacts from **GitHub-hosted Windows runners** using your current GitHub Binary Signing / Software Trust integration?
9. What is the current recommended **non-interactive/headless authentication model** for GitHub Actions?
10. Which credentials are required — for example API key, client-authentication certificate, service user, or another method?
11. Can those CI credentials be scoped to one project/keypair/signing policy and rotated independently of the Code Signing certificate?
12. Do you support short-lived/federated authentication for GitHub Actions, or are long-lived client credentials currently required?

### Certificate identity verification

13. Can our workflow retrieve or verify the public leaf Code Signing certificate associated with the remote signing keypair?
14. Can we independently pin and verify its **SHA-256 fingerprint** before accepting a signed artifact?
15. Can our verification step deterministically validate the expected publisher/subject identity after signing?

### Timestamping

16. Is **RFC3161 timestamping** supported with your recommended Windows signing path?
17. What timestamp endpoint/flow should be used with the current DigiCert tooling?
18. Please confirm the recommended way to verify that both Authenticode signature and timestamp are valid before publication.

### Audit and security controls

19. Do KeyLocker / Software Trust Manager logs include the signing keypair, signer identity/service identity, timestamp, request/job ID, result, and relevant policy decision?
20. Can we restrict signing to approved CI identities, repositories, branches, signing policies, hashes, or artifact types?
21. What credential-revocation/recovery process is recommended if a CI API key or client-authentication credential is exposed?
22. Can production CI signing access be kept separate from DigiCert account administration access?

### Pricing / limits / onboarding

23. Please provide current first-year and renewal pricing for:
   - one public Code Signing certificate;
   - KeyLocker / Software Trust Manager usage required for GitHub Actions;
   - included signing operations/signature units;
   - additional signature-unit pricing;
   - any setup/onboarding/validation fees.
24. Are failed signing attempts charged against signature units?
25. Are there signing rate/concurrency limits relevant to CI/CD?
26. What is the normal validation/onboarding timeline for a Pakistani private limited company?

Our target architecture is:

`GitHub protected production-release environment -> DigiCert CI authentication -> remote HSM/keypair signing -> Authenticode verification -> RFC3161 timestamp verification -> release publication gate`

We will not place any public-trust private signing key in GitHub.

Please confirm whether this architecture is supported and point us to the current production documentation for the recommended GitHub Actions integration.

Regards,  
**Vertex Systems Network (Pvt.) Ltd.**  
Software / SaaS Company  
Pakistan

---

## Provider response acceptance checklist

A provider should not be selected until its written response clearly confirms all of these minimum gates:

- Pakistan legal entity is eligible for the exact public Code Signing product.
- Certificate publisher/subject can match the validated legal company name.
- GitHub-hosted Windows runners are supported for unattended Authenticode signing.
- Public-trust private key stays in compliant HSM/cloud-signing protection.
- CI authentication model is documented and suitable for a protected GitHub environment.
- CI credentials can be rotated/revoked and preferably scoped.
- Leaf signer certificate identity/fingerprint can be independently verified.
- RFC3161 timestamping is supported and verifiable.
- Audit logging is available for signing activity.
- Commercial limits, signature quotas, first-year price and renewal price are disclosed.

If any required gate is unclear, treat that provider as **not yet qualified** rather than making an assumption.

## Handling provider replies

- Do not paste secrets, API keys, client certificates, private keys or identity-document scans into Issue #62.
- Archive only the provider's non-secret written confirmation, pricing, product scope and integration requirements.
- If a provider supplies confidential onboarding documents, keep those outside the repository.
- Any future provider-specific secret names must be introduced only by the provider-integration PR.
- No provider credential should be added to `production-release` until the selected provider integration has passed source review and readiness controls.
