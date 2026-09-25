# M14 Windows Public-Trust Signing Provider Compatibility Audit

**Scope:** publicly trusted Windows Authenticode signing for WorkIntel from GitHub Actions while keeping the subscriber private key inside a CA/B-compliant HSM/cloud-signing boundary.

**Audit date:** 2026-09-26  
**Issue:** #62  
**Protected-main baseline:** `71e9c970f7db34710dd42d0400fa772d81c30fd0`

## Executive conclusion

The current evidence supports this provider evaluation order:

1. **SSL.com eSigner for Code — first provider to qualify**
2. **DigiCert KeyLocker / Software Trust Manager — enterprise fallback**
3. **Sectigo + supported customer-controlled HSM — compatible but higher integration overhead**
4. **GlobalSign HSM-based Code Signing — viable alternative but less directly verified for our GitHub-hosted path**

This is a technical compatibility order, not a purchase authorization. No provider should be purchased until the legal entity is confirmed eligible and the provider confirms the exact CI/CD authentication model in writing.

## Non-negotiable architecture rule

Modern publicly trusted Code Signing keys cannot be treated as portable GitHub PFX secrets. CA/B-aligned providers require the subscriber private key to stay in approved hardware/HSM/cloud-signing protection.

Therefore the existing repository PFX path must **not** be populated for public-trust Windows signing unless the selected CA explicitly proves that the certificate/key provisioning method permits it under the current public-trust rules.

The target architecture is:

`GitHub production-release environment -> short/controlled provider authentication -> remote HSM signing -> Authenticode verification -> RFC3161 timestamp verification -> trusted receipt -> publication gate`

The public-trust private key itself must never enter GitHub.

---

## Provider matrix

| Provider | Public-trust key protection | GitHub/CI automation | Authenticode | Pakistan evidence | Cost signal | Audit result |
| --- | --- | --- | --- | --- | --- | --- |
| SSL.com eSigner for Code | SSL.com FIPS 140-2 Level 3 cloud HSM; key does not leave HSM | Explicit GitHub Actions / eSigner CKA / API support | Explicitly supported | Pakistan is explicitly listed in SSL.com's accepted country-code list | Public pricing available | **Best current fit to qualify first** |
| DigiCert KeyLocker / Software Trust Manager | DigiCert-hosted protected signing/keypair service | Current DigiCert Binary Signing GitHub Action; older action is retired/deprecated | Supported through Software Trust signing tooling | DigiCert lists Pakistan among EV-validation countries and not among comprehensive embargoes; specific Code Signing issuance should still be confirmed | KeyLocker includes 1,000 signatures per certificate; service pricing requires quote/account context | **Strong enterprise fallback** |
| Sectigo + supported HSM | Customer HSM/token; supports Luna, Google Cloud KMS, Fortanix, YubiHSM and other attested hardware | No equally direct first-party GitHub hosted-runner path found in this audit; customer must integrate HSM/KMS tooling | Code Signing/Authenticode supported | Pakistan is not on Sectigo's published banned-country list | Public certificate pricing exists; HSM/cloud infra is additional | **Compatible, but more engineering/ops work** |
| GlobalSign HSM-based Code Signing | Token or approved HSM; HSM-based workflow documented | Automation exists, including SignTool + cloud-HSM guidance, but no simpler first-party GitHub path was confirmed here | Supported | No Pakistan-specific Code Signing eligibility statement was found in current official docs reviewed; region must match legal registration | Public order flow exists; quote/region dependent | **Viable fallback after eligibility + CI proof** |

---

## Candidate 1 — SSL.com eSigner for Code

### Compatibility evidence

SSL.com currently documents eSigner for Code as:
- cloud HSM-backed code signing;
- FIPS 140-2 Level 3 HSM protection;
- private key never leaving the HSM;
- Windows Authenticode support;
- direct CI/CD support including GitHub Actions;
- eSigner CKA integration with `signtool.exe`;
- API/automation support.

SSL.com's accepted-country list explicitly includes **Pakistan (PK)**.

Official references:
- https://www.ssl.com/products/software-integrity/signing-service/
- https://www.ssl.com/how-to/cloud-code-signing-integration-with-github-actions/
- https://www.ssl.com/how-to/how-to-integrate-esigner-cka-with-ci-cd-tools-for-automated-code-signing/
- https://secure.ssl.com/csrs/country_codes

### Public pricing signal

Current public list pricing reviewed:
- OV Code Signing: **USD 129/year**
- eSigner Tier 1 annual subscription: **USD 180/year**
- Tier 1 includes **240 signings/year** and one signing credential.

Approximate entry public list cost is therefore about **USD 309/year before taxes, optional validation acceleration, extra credentials/signings, or other fees**. Checkout/provider confirmation remains authoritative.

Reference:
- https://www.ssl.com/guide/esigner-pricing-for-code-signing/
- https://www.ssl.com/products/software-integrity/signing-service/

### Integration/security concern to resolve before purchase

SSL.com's GitHub examples use provider credentials such as username/password, credential ID and TOTP secret. Before we commit to the provider, SSL.com must confirm the **current 2026 recommended non-interactive GitHub Actions authentication method**, rotation model, least-privilege controls, and whether the older credential pattern has a newer API/service-account alternative.

We must not copy a legacy sample blindly into `production-release`.

### Qualification questions to send SSL.com

Ask SSL.com to confirm in writing:

1. Can an SECP-registered Pakistani private limited company obtain an **OV public Code Signing certificate** with eSigner for Code?
2. Can the publisher subject exactly match the legal entity name supplied during validation?
3. Is GitHub-hosted Windows runner signing supported for Authenticode `.exe` files without any USB token?
4. What is the current recommended **headless/service authentication** for GitHub Actions?
5. Can credentials be scoped to one signing certificate/credential and rotated without replacing the signing certificate?
6. Is RFC3161 timestamping included, and what current HTTPS timestamp endpoint/flow should be used?
7. Does the signing API/CKA expose the leaf certificate so the workflow can pin and independently verify its SHA-256 fingerprint?
8. Is there an audit log that records who/what signed, timestamp, certificate identity and request/job ID?
9. Are there IP allowlists, signing-policy restrictions, approval gates or artifact-hash controls available?
10. Confirm total first-year and renewal cost for OV Code Signing + lowest eSigner tier suitable for GitHub CI.

### Decision gate

Do not purchase until answers 1, 3, 4, 6, 7 and 8 are affirmative and technically specific.

---

## Candidate 2 — DigiCert KeyLocker / Software Trust Manager

### Compatibility evidence

DigiCert supports:
- Code Signing and EV Code Signing with HSM/KeyLocker provisioning;
- GitHub Actions through the current **DigiCert Binary Signing** / `digicert/code-signing-software-trust-action@v1` flow;
- API key + client authentication certificate patterns in current GitHub integration docs;
- hash/keypair based remote signing without moving the private signing key to GitHub.

DigiCert also documents Pakistan in its EV-validation country list and Pakistan is not in its comprehensive embargo-country list reviewed in this audit.

Official references:
- https://docs.digicert.com/en/certcentral/order-and-manage-certificates/request-certificates/request-a-code-signing-or-ev-code-signing-certificate/request-code-signing-certificate.html
- https://docs.digicert.com/en/software-trust-manager/ci-cd-integrations-and-deployment-pipelines/plugins/github/binary-signing-using-github-actions.html
- https://docs.digicert.com/en/digicert-keylocker/overview/licensing.html
- https://docs.digicert.com/en/certcentral/order-and-manage-certificates/prepare-to-request-certificates/ev-certificate-countries.html
- https://knowledge.digicert.com/solution/embargoed-countries-and-regions

### Capacity signal

DigiCert currently documents **1,000 signatures per KeyLocker certificate** under standard licensing, with additional signature units available.

### Qualification caveat

The Pakistan evidence found is broad DigiCert/EV validation evidence, not an explicit statement that every public Code Signing/KeyLocker SKU is orderable for every Pakistani entity. Before purchase, obtain direct confirmation for:
- Code Signing organization validation;
- KeyLocker availability;
- GitHub-hosted runner usage;
- exact pricing;
- authentication model;
- audit-log availability;
- signer-fingerprint verification.

### Decision gate

Use DigiCert as the second provider to quote/qualify if SSL.com fails legal-entity eligibility, security-authentication, reliability or commercial requirements.

---

## Candidate 3 — Sectigo + customer-controlled HSM

### Compatibility evidence

Sectigo's 2026 documentation confirms publicly trusted Code Signing private keys must be generated/stored/used in suitable FIPS-compliant hardware.

Its key-attestation service supports multiple HSM options including:
- Luna HSM / Luna Cloud HSM;
- Google Cloud KMS/HSM;
- Fortanix DSM;
- YubiHSM 2;
- additional supported enterprise HSMs.

Sectigo's published banned-country list does **not** list Pakistan.

Official references:
- https://www.sectigo.com/ssl-certificates-tls/code-signing
- https://www.sectigo.com/knowledge-base/detail/How-How-to-Obtain-an-Attestation-File-and-CSR-from-Google-Cloud-HSMto-Obtain-an-Attestation-File-and-CSR-from-Google-Cloud-HSM
- https://docs.sectigo.com/scm/scm-administrator/understanding-code-signing-certificates
- https://www.sectigo.com/knowledge-base/detail/Banned-Country-List-1527076085907

### Tradeoff

This can be fully compliant, but it shifts HSM account/KMS configuration, key attestation, GitHub workload authentication, signing integration and operational monitoring onto us.

For our current stage this is more operational complexity than a managed signing service.

### Decision gate

Keep as a fallback if managed services fail eligibility or commercial requirements, or if we later require direct control of our own HSM.

---

## Candidate 4 — GlobalSign HSM-based Code Signing

### Compatibility evidence

GlobalSign supports:
- organization-only public Code Signing;
- token and HSM provisioning;
- Authenticode/SignTool workflows;
- HSM-based signing documentation;
- timestamping;
- cloud-HSM automation guidance.

Official references:
- https://shop.globalsign.com/en/code-signing
- https://support.globalsign.com/code-signing/installation/download-and-install-code-signing-certificate-hsm-based
- https://support.globalsign.com/code-signing/code-signing
- https://support.globalsign.com/code-signing/code-signing-faq

### Qualification caveat

The current public docs reviewed require selecting the sales region that matches the legal registration, but this audit did not find a current official statement explicitly confirming Pakistani Code Signing issuance and a first-party GitHub-hosted-runner integration equivalent to SSL.com/DigiCert.

### Decision gate

Do not prioritize until GlobalSign confirms both Pakistan legal-entity eligibility and an unattended GitHub Actions/HSM signing pattern that matches our controls.

---

## Recommended provider qualification sequence

### Phase 1 — no purchase

Contact **SSL.com** and **DigiCert** in parallel with the same written questionnaire.

Capture the answers in Issue #62 without any secret values.

Minimum acceptable provider response must confirm:
- Pakistani legal entity can be validated for the exact public Code Signing product;
- Windows Authenticode `.exe` signing from GitHub-hosted runners;
- private signing key remains inside compliant HSM/cloud signing boundary;
- RFC3161 timestamping;
- certificate identity/fingerprint can be independently verified;
- non-interactive CI authentication with documented rotation;
- audit logging;
- no USB token required for the CI path.

### Phase 2 — provider decision

If SSL.com confirms all gates and its current authentication model is acceptable, it is the **lowest-friction / lowest publicly visible entry-cost path found in this audit**.

If SSL.com fails a required gate, qualify DigiCert next.

Sectigo/GlobalSign remain alternatives if enterprise/self-managed-HSM requirements later justify the additional integration work.

### Phase 3 — repository integration

Only after a provider is selected:
1. create a provider-specific branch;
2. replace/bypass the public-trust Windows PFX step with remote HSM signing;
3. add a provider readiness workflow that performs no signing;
4. use `production-release` environment-only provider credentials;
5. pin provider actions/tools by commit/version where possible;
6. validate signer leaf certificate SHA-256;
7. perform actual Authenticode + timestamp verification;
8. emit a sanitized provider/signing evidence artifact;
9. preserve existing reviewer and publication gates.

## Repository secrets rule

Until a provider is selected, keep these **unset for public-trust Windows signing**:

- `WORKINTEL_WINDOWS_SIGNING_PFX_B64`
- `WORKINTEL_WINDOWS_SIGNING_PFX_PASSWORD`

Do not invent replacement provider-secret names yet. Define them only in the provider-specific integration PR after the provider confirms its current 2026 authentication contract.

## Audit disposition

**Provider selection status:** READY FOR EXTERNAL QUALIFICATION  
**Purchase status:** NOT AUTHORIZED / NOT PERFORMED  
**Credential placement status:** BLOCKED UNTIL PROVIDER SELECTED  
**Signing status:** NOT PERFORMED  
**Publication status:** NOT PERFORMED
