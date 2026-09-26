# M14 Windows Provider Verified Contact Channels and Send Paths

**Purpose:** verified external-contact path for the provider qualification inquiries already documented in `M14_WINDOWS_PROVIDER_QUALIFICATION_INQUIRIES.md`.

**Audit date:** 2026-09-26  
**Issue:** #62  
**Protected-main baseline:** `607f48dcd53aa2813b808b0c167644bad1740126`

No provider contact is performed by this document. It only records the official channels and the recommended sending sequence.

---

## SSL.com — verified channels

Official SSL.com contact information currently publishes:

- **Sales email:** `sales@ssl.com`
- **Support email:** `support@ssl.com`
- **Validation email:** `validation@ssl.com`
- **Sales phone:** +1 877-775-7328
- **Main/support phone:** +1 775-237-8434
- **Official contact page:** SSL.com Contact Us
- **Official website sales/support chat:** available from the SSL.com contact/site interface

Official references:
- https://www.ssl.com/contact_us/
- https://secure.ssl.com/contact_us

SSL.com also explicitly directs Code Signing/custom integration/high-volume/quote questions to its **sales team**.

### Recommended SSL.com send path

1. Send the approved **SSL.com qualification inquiry** to `sales@ssl.com`.
2. Use subject:
   `Qualification inquiry — OV Code Signing + eSigner for GitHub Actions (Pakistan company)`
3. Do **not** attach identity documents, private keys, certificates, API credentials or GitHub secrets.
4. In the first email, ask Sales to keep the response in writing and route technical eSigner/GitHub questions to the correct specialist if needed.
5. On the same day, optionally use the official SSL.com sales chat/contact page and state:
   `We have emailed sales@ssl.com with a pre-purchase eSigner/OV Code Signing CI qualification request. Please help route it to the Code Signing/eSigner technical sales team.`
6. If Sales asks for an order number before one exists, clarify that this is **pre-purchase technical qualification**.
7. Use `support@ssl.com` only if Sales explicitly asks us to open a technical support thread or if a technical integration question is not answered by Sales.
8. Use `validation@ssl.com` only after SSL.com confirms the product path and requests organization-validation follow-up.
9. Record only non-secret response facts in Issue #62:
   - eligibility;
   - product/SKU;
   - supported GitHub integration;
   - HSM custody;
   - auth model;
   - timestamp method;
   - fingerprint verification;
   - audit logging;
   - pricing/limits;
   - onboarding timeline;
   - ticket/reference ID.
10. Do not purchase until all mandatory acceptance gates pass.

### SSL.com first-response acceptance marker

A useful first response must identify a human/team responsible for **Code Signing/eSigner technical sales** and must directly address Pakistan organization eligibility plus GitHub-hosted unattended signing. A generic marketing response is not sufficient evidence.

---

## DigiCert — verified channels

Official DigiCert contact information currently publishes:

- **Sales email:** `Sales@digicert.com`
- **Sales phone:** +1 801-770-1701
- **General support phone:** +1 801-701-9600
- **General support email:** `cc.standard.support@digicert.com`
- **DigiCert ONE / PKI support email:** `dc1.standard.support@digicert.com`
- **Official contact page:** DigiCert Contact Us
- **Official sales live chat / request-expert form:** available from the DigiCert contact page

Official references:
- https://www.digicert.com/contact-us
- https://www.digicert.com/support
- https://www.digicert.com/support/pki-support

### Recommended DigiCert send path

1. Send the approved **DigiCert qualification inquiry** to `Sales@digicert.com`.
2. Use subject:
   `Qualification inquiry — DigiCert Code Signing + KeyLocker / Software Trust Manager for GitHub Actions (Pakistan company)`
3. Do **not** attach identity documents, private keys, client certificates, API keys or GitHub secrets.
4. In the first email, explicitly ask Sales to route the inquiry to the **Code Signing / Software Trust Manager / KeyLocker technical sales specialist**.
5. On the same day, optionally submit the official DigiCert **Contact Sales / Schedule time with an expert** form with a short note that the detailed technical questionnaire has already been emailed to `Sales@digicert.com`.
6. If a DigiCert account already exists later, use the appropriate DigiCert ONE/PKI support channel only for implementation-specific technical questions after Sales confirms the product path.
7. Do not start with generic certificate support for this pre-purchase qualification unless Sales redirects us there.
8. Record only non-secret response facts in Issue #62:
   - Pakistan entity eligibility;
   - exact product/SKU;
   - organization-validation requirements;
   - GitHub-hosted runner support;
   - HSM/key custody;
   - API/client-auth model;
   - credential scope/rotation/revocation;
   - leaf certificate/fingerprint verification;
   - RFC3161 timestamping;
   - audit logs;
   - signature-unit pricing/limits;
   - onboarding timeline;
   - quote/reference ID.
9. Do not purchase until all mandatory acceptance gates pass.

### DigiCert first-response acceptance marker

A useful first response must explicitly address **public Code Signing + KeyLocker/Software Trust Manager** for a Pakistan-registered company and should identify the supported GitHub-hosted CI authentication path. A generic TLS/SSL response is not sufficient evidence.

---

## Exact outbound sequence

Use this order to avoid duplicate/conflicting tickets:

1. **SSL.com email first:** `sales@ssl.com`
2. **DigiCert email second:** `Sales@digicert.com`
3. Save both sent-message timestamps and subjects.
4. Wait for the automatic acknowledgement/ticket/reference IDs.
5. If no acknowledgement is generated, use the provider's official sales chat/contact form and reference the exact email subject.
6. Do not send the same questionnaire to Sales + Support + Validation simultaneously; this can create duplicate queues and inconsistent answers.
7. Once a provider replies, normalize the response against the acceptance checklist in `M14_WINDOWS_PROVIDER_QUALIFICATION_INQUIRIES.md`.
8. Any unresolved mandatory item is marked **UNCONFIRMED**, not assumed.
9. No commercial purchase or credential creation occurs until provider qualification is complete.
10. After provider selection, create a separate provider-specific integration PR.

## Safe metadata to archive in Issue #62

Allowed:
- provider name;
- response date;
- representative/team name;
- ticket/quote/reference number;
- non-secret technical statements;
- quoted pricing and limits;
- published product/SKU names;
- onboarding requirements;
- official documentation references.

Do not archive:
- API keys;
- passwords;
- TOTP seeds;
- private keys;
- client-auth private material;
- identity-document scans;
- bank/payment details;
- internal provider authentication links containing tokens.

## Current disposition

- SSL.com contact path: **VERIFIED**
- DigiCert contact path: **VERIFIED**
- Qualification inquiries: **READY TO SEND**
- Provider responses: **NOT YET RECEIVED**
- Provider selection: **NOT YET MADE**
- Purchase: **NOT AUTHORIZED / NOT PERFORMED**
- Credential placement: **NOT PERFORMED**
- Signing/publication: **NOT PERFORMED**
