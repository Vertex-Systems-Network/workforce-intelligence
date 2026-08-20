# Phase 23 — Enterprise Identity & Governance

## OIDC SSO

OIDC uses Authorization Code + PKCE. The authorization request has state and nonce; the verifier is encrypted; state is hash-addressed/one-time and consumed before token exchange. Issuer/endpoints pass outbound URL/SSRF validation. JIT workspace membership and allowed-domain/role rules are validated.

The owner has a break-glass SSO exemption so an SSO configuration mistake cannot permanently lock a workspace.

## SAML boundary

SAML provider configuration and SP metadata are present. Signed assertion processing is intentionally **not** implemented with hand-written XML-signature code. The ACS returns a clear not-ready response unless a standards-compliant `SamlAssertionAdapter` is installed. `require_sso` cannot be enabled using a SAML-only provider that lacks that adapter.

## MFA

Native TOTP supports enrollment, encrypted secret storage and single-use recovery-code hashes. Enterprise security policy can require MFA by role. Login session metadata records MFA verification.

## SCIM

SCIM access tokens are `wiscim_...` raw-once/hash-at-rest tokens. Supported scope checks:

- `users.read`
- `users.write`
- `groups.read`
- `groups.write`
- `*`

SCIM deactivation disables the workspace membership; it does not globally delete a shared User record. Role/group membership is workspace-scoped. Discovery endpoints expose ServiceProviderConfig, Schemas and ResourceTypes.

## Session/IP governance

Workspace policy can enforce session TTL, maximum sessions, revoked sessions, MFA/SSO and IP rules. IP allow/deny enforcement happens during workspace request resolution rather than existing as a settings-only flag.

## Organization hierarchy

Legal entities and business units can be assigned to members, projects and cost centers. Unit/entity mismatch is rejected server-side.

## ABAC

Attribute access policies can match role, legal entity, business unit, employment stage and IP. Deny wins. When allow policies exist for a resource/action, at least one matching allow is required. Phase 23 wires this to payroll, field/mobile and enterprise route groups and leaves the policy model extensible to other resources.

## Data governance

Retention policies support `hard_purge` and real `soft_then_purge` semantics. `soft_then_purge` first stages a tombstone and only deletes after a configured grace period. Legal hold stops staging and purging. Authentication artifacts such as expired OIDC state and old revoked bearer tokens are treated separately from legal/audit history.

A residency setting is governance metadata. Actual data residency requires matching database/object-storage/backup infrastructure placement; changing the setting alone does not physically relocate data.

## Doctors / maintenance

```bash
php artisan workintel:enterprise-doctor
php artisan workintel:security-maintenance
php artisan workintel:retention-maintenance
```

Security maintenance runs before retention maintenance. Governance-aware history cleanup is delegated to the retention service so legal hold is not bypassed.
