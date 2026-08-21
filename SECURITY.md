# Security Policy

## Reporting a vulnerability

Do not disclose credentials, tokens, personal data, exploit details, or sensitive production information in a public issue or pull request.

Use GitHub's private security reporting / Security Advisory flow for repository vulnerabilities when available. If private reporting is not enabled, contact a repository administrator through an existing private organizational channel and provide the minimum information needed to reproduce and assess the issue.

Include:

- affected component and version/commit;
- reproduction steps;
- expected versus actual behavior;
- security impact;
- whether exploitation requires authentication or special permissions;
- a proposed remediation, if known.

## Handling

Security fixes should use a dedicated branch and pull request or a private security-advisory fork when confidentiality is required. Do not weaken authentication, authorization, audit logging, tenant isolation, encryption, validation, rate limits, or certification gates merely to make a failing test pass.

Secrets must never be committed to the repository. Rotate any credential immediately if exposure is suspected.
