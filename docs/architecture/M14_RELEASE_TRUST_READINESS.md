# M14 — Production Release Trust & Real-Target Readiness

**Module ID:** `M14-RELEASE-TRUST`  
**State:** `IMPLEMENTING`  
**Authority:** GitHub Issue #50  
**Owner approval:** `OWNER APPROVAL: M14-RELEASE-TRUST APPROVED FOR IMPLEMENTATION`  
**Starting protected-main SHA:** `7f46f9542bbab6fa210a5c9d30acb07d44b91fb4`  
**Implementation branch:** `m14/release-trust-readiness`  
**Risk:** HIGH  
**Independent review required:** YES  
**Real-target evidence required:** YES for `PRODUCTION_VERIFIED` / M14 `DONE`

## Objective

Turn WorkIntel's already deterministic M13 desktop-agent build into a verifiable enterprise distribution path without changing product behavior. The trusted release path must preserve source provenance, isolate signing authority from pull requests, fail closed when required platform trust operations cannot be completed, and keep hosted-CI evidence distinct from real-target production verification.

## Actors

- repository owner / release approver;
- trusted GitHub Actions release workflow;
- Windows organization code-signing identity;
- Apple Developer ID / Apple notary service;
- release operator reviewing exact-SHA evidence;
- production operator performing separately authorized real-target readiness verification.

Ordinary application users, tenant administrators, employees and tracked devices receive no new permissions or product workflow from M14.

## In scope

### M14A — Signed desktop distribution provenance

- retain the existing deterministic standalone build and exact Node/lockfile contract;
- introduce a separate trusted release workflow that is never triggered by `pull_request`;
- bind every trusted build to an exact source SHA that must be reachable from protected `main`;
- Windows Authenticode signing with an organization-controlled certificate, RFC 3161 timestamp and explicit signature verification;
- macOS Developer ID signing with hardened runtime, secure timestamp and Apple notarization through `notarytool`;
- Linux distribution remains checksum/provenance based and is not assigned an invented signing provider;
- calculate an unsigned/pre-trust SHA-256 and a final post-trust SHA-256;
- generate a machine-readable receipt for every trusted distribution artifact;
- keep signing/notary material in GitHub environment/repository secret references only; never commit it to source or upload it as an artifact.

### M14B — Release-state evidence and fail-closed publication

- distinguish `HASH_VERIFIED`, `SIGNED`, `NOTARIZED` and published release state instead of collapsing them into one PASS;
- publish only from an authorized `agent-v*` tag after all platform trust jobs succeed;
- require the tag version to equal the native-agent source version;
- require the exact source commit to be contained by protected `main`;
- create a new GitHub Release only after the platform artifacts are complete;
- refuse an already-existing release/tag publication target instead of overwriting assets;
- verify the complete expected trusted asset set before making a draft release public;
- clean up a newly-created draft release when publication validation fails;
- never mutate the M13 canonical ZIP catalog or same-version canonical bytes as part of trusted executable distribution.

### M14C — Real-target readiness evidence

Source implementation defines the evidence contract but does not fabricate external evidence. `PRODUCTION_VERIFIED` requires a separately authorized real target and must capture, as applicable:

- exact deployed revision and release artifact digest;
- `/health/live` and `/health/ready`;
- database connectivity and migration state;
- queue supervision/backlog;
- scheduler health;
- storage read/write path;
- release download path;
- critical authentication and workspace-isolation smoke;
- browser journey evidence when applicable;
- isolated/disposable backup-to-restore verification before any claim that recovery is verified.

## Explicit non-goals

- no new tenant/customer-facing feature;
- no React UX redesign or navigation change;
- no Laravel route/API/domain behavior change;
- no role, permission, entitlement or workspace-isolation semantic change;
- no payroll, billing, attendance, timekeeping or tracking semantic change;
- no browser-extension enrollment/runtime change;
- no native-agent enrollment, device-token, capture or managed-update semantic change;
- no database schema/data migration;
- no rewrite of `tools/build-releases.py` or weakening of M13 deterministic/canonical immutability;
- no self-hosted/Laragon release authority;
- no private key, certificate, token or enrollment secret committed to Git, logs, issue bodies or uploaded build artifacts;
- no destructive production restore authorized by this module.

## Affected architecture

Expected primary mutation surface:

1. `.github/workflows/desktop-agent-trusted-release.yml`;
2. `.github/workflows/ci.yml` governance assertions for the trusted release contract;
3. `tools/release-trust-receipt.mjs`;
4. `tests/frontend/release-trust-m14.test.mjs`;
5. `desktop-agent/standalone/README.md`;
6. `docs/PRODUCTION_CHECKLIST.md`;
7. `docs/operations/PRODUCTION_OPERATIONS_RUNBOOK.md`;
8. `docs/status/AI_CHECKPOINT.md`;
9. this specification.

Target change budget: approximately 6–12 primary repository paths. Material expansion requires renewed scope review.

## Explicitly unaffected architecture

- application controllers, policies, routes and domain services;
- React product screens and design-system behavior;
- database migrations/models/seed semantics;
- M13 canonical release ZIP construction and manifest/checksum transaction;
- browser extension code;
- `desktop-agent/native-agent.mjs` runtime semantics unless a separately reviewed signing-compatibility defect is proven.

## Roles, permissions, tenant and privacy impact

No application role or tenant permission changes are permitted.

Release authority is an operational security boundary:

- trusted release workflow must not expose signing/notary credentials to pull requests;
- signing jobs use the `production-release` GitHub environment so repository/environment protection can be applied outside source;
- secrets are consumed only by the platform step that requires them;
- no secret value is written to receipts;
- receipts may contain source SHA, release version, workflow/run identifiers, artifact digests, verification method and external notarization submission ID;
- no employee-monitoring payload, workspace data or device/enrollment token is part of release receipts.

## Data / schema / migration

`N/A` for application schema and data. M14 is a release-trust and operations change.

M13 canonical packages remain immutable. Trusted standalone executables are separately named distribution outputs and do not replace canonical ZIP entries in `storage/app/releases`.

## UI acceptance

No new application UI is required. Existing download/install UX remains unchanged unless a future separately approved scope decides to expose signed-distribution state in product UI.

## Workflow acceptance

### Authorization

1. trusted workflow trigger is `push` to `agent-v*` or explicit `workflow_dispatch`;
2. there is no `pull_request` trigger;
3. determine the exact source SHA;
4. source SHA must be a valid commit reachable from protected `main`;
5. release tag events must match the agent source version exactly;
6. every build job checks out that exact SHA.

### Platform trust

**Windows**

1. build deterministic standalone executable;
2. record unsigned SHA-256;
3. require organization certificate material and timestamp URL;
4. sign using SignTool with SHA-256 file digest and SHA-256 RFC 3161 timestamp digest;
5. verify Authenticode signature;
6. record final SHA-256 and receipt.

**macOS**

1. build deterministic standalone executable;
2. archive and record unsigned distribution SHA-256;
3. require organization Developer ID certificate and Apple notarization credentials;
4. sign with Developer ID, hardened runtime and secure timestamp;
5. verify code signature;
6. create final distribution ZIP;
7. submit with `notarytool --wait` and require `Accepted`;
8. record notarization submission ID, final SHA-256 and receipt.

**Linux**

1. build deterministic standalone executable;
2. record SHA-256 and provenance receipt;
3. do not claim platform signing/notarization not actually performed.

### Publication

- manual dispatch produces trusted candidate Actions artifacts but does not publish a GitHub Release;
- an `agent-v*` tag may publish only after all platform jobs pass;
- publication refuses an existing release target and never uses asset overwrite/clobber;
- release is created as draft, expected assets are verified, then it is made public;
- a failed draft publication is cleaned up rather than left as apparent success.

## Failure / retry / recovery

- missing required credential/variable: fail closed;
- signing command failure: fail closed, no unsigned fallback;
- Windows timestamp failure: fail closed;
- macOS notarization non-accepted result: fail closed;
- wrong source SHA/version/tag: fail before signing;
- artifact or receipt mismatch: fail before publication;
- existing release target: fail instead of overwrite;
- repeated release attempt uses a new authorized version/tag after correction, not same-version mutation;
- no infinite retries are implemented in source; provider/tool retries remain bounded to explicit command behavior;
- production rollback remains governed by the operations runbook and release/schema compatibility.

## Security / abuse / negative requirements

High-severity unresolved conditions block release:

1. signing authority accessible from PR/fork context;
2. signing/federated authority scoped more broadly than the trusted repository/ref/environment requires;
3. wrong revision or substituted artifact signed;
4. only pre-sign digest retained with no final digest;
5. key/token/certificate material leaked to logs or artifacts;
6. stale or non-main-contained source SHA released;
7. timestamp/notary partial failure represented as success;
8. artifact replaced after trust verification and before publication;
9. unpinned release-critical GitHub Action introduced;
10. retry overwrites an existing same-version asset;
11. existing M13 canonical same-version package bytes changed;
12. healthy HTTP endpoint used as substitute for DB/queue/scheduler/storage readiness;
13. backup creation represented as restore verification;
14. rollback target incompatible with schema/configuration.

## Observability / support contract

Each receipt records only non-secret evidence:

- schema version;
- artifact name/platform/trust state;
- exact source SHA;
- release version;
- unsigned/pre-trust SHA-256;
- final SHA-256 and byte size;
- whether trust processing changed bytes;
- verification method;
- external evidence ID when applicable (for example Apple notarization submission ID);
- GitHub repository/workflow/run/attempt/event/ref metadata.

Unavailable external signing/notary/real-target evidence is reported as `Not Verified`, never synthesized.

## Rollback classification

- source workflow/tooling before release: `SIMPLE_ROLLBACK` or controlled forward-fix;
- already published trusted artifact: immutable; correction requires a new version/release;
- schema/data rollback: N/A;
- production restore: outside source implementation authority and requires the existing privileged recovery contract.

## Verification

### Source / PR evidence

- frontend contract test for trusted workflow trigger/secret/publication invariants;
- behavioral tests for receipt creation and verification;
- existing M13 deterministic/immutability tests remain green;
- `npm run quality`;
- `npm run quality:full` before final browser certification;
- exact-head WorkIntel Code Quality;
- exact-head WorkIntel CI including governance;
- exact-head Windows Certification;
- trusted standalone 3-OS candidate workflow when credentials/environment are available;
- independent review with no unresolved high-severity trust finding.

### External release evidence

Required to claim platform trust or final production verification:

- actual Windows signature/timestamp verification;
- actual macOS Developer ID signature and accepted Apple notarization;
- final published artifact SHA-256 receipts;
- actual target identity/revision and production readiness evidence when `PRODUCTION_VERIFIED` is claimed;
- isolated/disposable restore evidence when recovery verification is claimed.

PR CI cannot substitute for these external evidence classes.

## Definition of Done

M14 is `DONE` only when:

- this approved scope is implemented without out-of-scope product/schema changes;
- PR/fork code cannot obtain production signing/notary authority;
- Windows and macOS distribution artifacts have actual platform trust evidence;
- Linux has truthful checksum/provenance evidence;
- every trusted artifact has a machine-readable receipt tied to exact source/run/final digest;
- same-version M13 canonical immutability remains intact;
- exact-head quality/CI/governance/Windows certification is green;
- independent review has no unresolved high-severity release-trust finding;
- required real-target evidence has actually executed before `PRODUCTION_VERIFIED` is claimed;
- no secret/key/token material is exposed.

A source merge alone may establish implementation completeness, but it must remain `PARTIALLY_COMPLETE` or `Not Verified` for external trust/real-target evidence until those checks actually run.