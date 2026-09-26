# M14 Remaining Critical-Path Audit

**Repository:** `Vertex-Systems-Network/workforce-intelligence`  
**Audit date:** 2026-09-26  
**Protected-main baseline:** `03c028c24987a0c83666d45bbbbdc053295b46cf`  
**Active issue:** #62  
**M14 progress:** 70%  
**Scope note:** the remaining 30% is a coarse module-progress remainder, not a repository-authoritative set of equal sub-percent weights. This audit does not invent per-gate percentages.

## Executive finding

M14 source trust and control-plane preparation are substantially complete. The remaining path is dominated by external trust material and real execution evidence, not additional product implementation.

The fastest safe path is **parallel**:

- **Windows lane:** wait for written SSL.com / DigiCert qualification, then select and integrate exactly one compliant remote-HSM provider.
- **Apple lane:** deferred to future Issue #123; it is no longer an active execution lane and is not represented as complete.
- The lanes converge before trusted candidate execution and immutable publication.
- Real-target production and restore evidence remain the final closure gate.

No provider purchase, Apple membership purchase, credential placement, trusted tag creation, release publication, or production/restore action is authorized by this audit.

## Owner scope override — Apple deferred to future Issue #123

On 2026-09-26 the owner explicitly deferred Apple/macOS signing and notarization because the required subscription/tooling is not currently available.

Effective active-scope rules:

- Apple Gate R3-A, Apple portion of R4, Apple portion of R5, and macOS publication/real-target evidence are moved to future Issue #123.
- These items are **DEFERRED**, not **COMPLETE** and not **VERIFIED**.
- Existing Apple fail-closed source remains intact and must not be weakened or removed merely because the lane is deferred.
- No Apple subscription purchase, organization enrollment, certificate/API-key creation, GitHub secret placement, signing or notarization is authorized in the active lane.
- The active M14 critical path is now Windows qualification/integration -> Windows readiness/signing plus Linux provenance -> trusted publication -> real-target/recovery evidence.
- If Issue #123 is resumed later, Apple must rejoin through its existing readiness/signing/notarization gates before any macOS completion claim.
- The M14 percentage remains 70%; scope deferral alone is not evidence and does not earn progress.

### Active dependency graph after deferral

```text
Windows:
Provider replies (R1)
  -> provider selection/integration (R2)
  -> real Windows authority (R3-W)
  -> Windows readiness (R4-W)
  -> actual Windows signing (R5-W)
                         \
                          -> active-scope trusted publication (R6)
                         /      -> real-target + restore evidence (R7)
Linux:                  /
Existing deterministic/provenance path
  -> trusted candidate evidence (R5-L)

Apple/macOS:
DEFERRED -> Issue #123 -> resume only on explicit owner authorization
```

## Already complete — do not repeat

The following work is already evidenced on protected main or in Issue #62 and is not part of the remaining critical path:

1. M14 trusted-release source workflow and fail-closed release contracts.
2. Dedicated immutable `agent-v*` tag protection / attestation lane.
3. GitHub immutable Releases live API verification.
4. `production-release` control-plane evidence lane and protected environment model.
5. Apple signer-material readiness workflow source.
6. Windows signer-material readiness workflow source.
7. Signer credential acquisition checklist.
8. Windows public-trust provider compatibility audit.
9. SSL.com and DigiCert qualification questionnaire package.
10. Verified SSL.com and DigiCert contact/send paths.
11. Qualification emails sent through verified Sales channels on 2026-09-26.
12. No public-trust private key has been placed in GitHub as a portable PFX.

## Remaining critical path

### Gate R1 — Written Windows provider qualification

**Current state:** WAITING_EXTERNAL.

Collect written non-secret response from SSL.com and/or DigiCert and evaluate every mandatory acceptance gate:

- Pakistan legal-entity eligibility for the exact public Code Signing product;
- exact validated publisher/legal-name support;
- GitHub-hosted Windows runner support;
- compliant provider-managed HSM/private-key custody;
- non-interactive CI authentication model;
- CI credential scope, rotation and revocation;
- public leaf signer identity / SHA-256 fingerprint verification;
- RFC3161 timestamping and verification;
- signing audit logging;
- first-year/renewal pricing, quotas/limits and onboarding timeline.

Any unresolved mandatory item remains `UNCONFIRMED`; do not select or purchase that provider.

**Blocks:** Windows provider selection and provider-specific Windows integration.

### Gate R2 — Select one Windows provider and define the provider-specific integration

**Precondition:** R1 passes for that provider.

After provider qualification:

1. record the selected exact product/service and written qualification evidence;
2. define provider-specific GitHub secret/variable names and least-privilege authentication contract;
3. update the trusted Windows signing path for the selected remote-HSM mechanism;
4. preserve exact-source binding, signer fingerprint pinning, RFC3161 verification, final digest receipt, protected `production-release` environment, and no PR signing authority;
5. exact-head certify and merge the provider-specific integration.

Do not retain the obsolete assumption that a public-trust Windows signing key will be supplied as a portable GitHub PFX if the selected CA requires remote HSM signing.

### Gate R3-W — Acquire and place real Windows organization signing authority

**Preconditions:** R1 + R2 and separately authorized purchase/onboarding.

Using the actual WorkIntel legal entity:

- complete provider organization validation;
- obtain the approved public Code Signing certificate / remote-HSM key identity;
- place only the selected provider's required CI credentials in the protected `production-release` environment;
- configure the approved signer SHA-256 fingerprint and provider-supported RFC3161 timestamp path;
- keep account-administration authority separate from production CI signing where supported;
- never commit private key material or credentials.

### Gate R3-A — Acquire and place real Apple organization signing/notary authority

**Dependency:** independent of R1/R2; can proceed in parallel, but paid enrollment or purchases require separate authorization.

Required organization-controlled material:

- Apple Developer Program organization enrollment;
- Developer ID Application certificate with matching private key, exported as protected P12 for the approved workflow model;
- approved Developer ID leaf SHA-256 fingerprint;
- Apple notary Team API key P8, key ID and issuer ID;
- signing identity value required by the workflow.

Placement remains only in the protected `production-release` environment. Raw P12/P8 material must never enter Git, issue bodies, logs or build artifacts.

### Gate R4 — Live signer-material readiness evidence

After real credentials are placed:

- run the protected **Windows signer material readiness** lane and require success;
- run the protected **Apple signer material readiness** lane and require success;
- archive only sanitized evidence;
- verify configured signer fingerprints against the actual approved organization certificates;
- fail closed on missing/mismatched/expired material.

A readiness pass proves material readiness only. It is not yet `SIGNED`, `NOTARIZED`, `RELEASED` or `PRODUCTION_VERIFIED`.

### Gate R5 — Actual trusted candidate signing/notarization

After R4:

- manually dispatch the trusted release workflow from the exact protected-main revision;
- Windows: produce and verify Authenticode signature + RFC3161 timestamp + signer fingerprint + final digest/receipt;
- macOS: produce and verify Developer ID signature + hardened runtime + secure timestamp + Apple notarization `Accepted` + final digest/receipt;
- Linux: produce final SHA-256/provenance receipt without inventing platform-signing claims;
- verify all trusted artifact receipts and M13 canonical ZIP immutability.

Manual dispatch is a trusted candidate evidence run and must not publish a GitHub Release.

### Gate R6 — Authorized immutable trusted publication

After R5 is successful:

1. choose a new authorized `agent-v<version>` matching the native-agent source version;
2. create the tag only through the approved tag-creation authority;
3. execute the trusted tag workflow;
4. require every platform trust job to pass;
5. create the release as draft;
6. verify exact remote asset set, upload state, byte sizes and server-reported SHA-256 against locally verified trusted files;
7. expose the draft only after final verification;
8. verify GitHub-native immutable-release postcondition and every attached artifact;
9. never overwrite an existing same-version release or canonical M13 bytes.

Successful publication yields `RELEASED`, not `PRODUCTION_VERIFIED`.

### Gate R7 — Real-target production + recovery verification

Final M14 closure requires separately authorized real-target evidence for the exact released revision/artifact, including as applicable:

- exact deployed revision and release digest;
- `/health/live` and `/health/ready`;
- database connectivity and migration state;
- queue supervision/backlog;
- scheduler health;
- storage read/write;
- release download path;
- critical authentication and workspace-isolation smoke;
- browser journey evidence where applicable;
- backup-to-restore verification on an isolated/disposable target before recovery is described as verified.

Only after this evidence is truthful and complete may the release state advance to `PRODUCTION_VERIFIED` / M14 `DONE`.

## Critical-path dependency graph

```text
Windows:
Provider replies (R1)
  -> provider selection/integration (R2)
  -> real Windows authority (R3-W)
  -> Windows readiness (R4-W)
  -> actual Windows signing (R5-W)
                       \
                        -> trusted publication (R6) -> real-target + restore evidence (R7) -> M14 DONE
                       /
Apple:
Organization credential acquisition (R3-A)
  -> Apple readiness (R4-A)
  -> actual signing/notarization (R5-A)

Linux:
Existing deterministic/provenance path
  -> trusted candidate evidence (R5-L)
```

## Work that is not on the M14 critical path

These may be handled separately and must not be used to falsely block or inflate M14 release-trust progress:

- Issue #70 intermittent `AccessControlSeeder` investigation; RB-005 remains blocked/not-authorized until deterministic evidence exists.
- Deferred dependency PRs (#74, #76, #81, #82, #83, #93).
- Draft README audit PR #78.
- Runner Benchmark tasks that are not explicitly authorized for the current gate.

A newly discovered security/release regression can still become a blocker if repository evidence proves it affects M14.

## Fastest safe execution order

1. Continue waiting for SSL.com/DigiCert written replies; evaluate immediately when received.
2. Keep Apple/macOS work parked in Issue #123 until the owner explicitly resumes it and the required subscription/tooling is available.
3. Do not build speculative provider-specific Windows integration before R1 selects a qualified provider.
4. Once a provider passes R1, perform R2 and provider onboarding as one bounded lane.
5. Run Windows material-readiness as soon as qualified provider credentials become available; Apple readiness remains deferred under Issue #123.
6. For the active Windows/Linux scope, converge at trusted candidate execution, then publication, then real-target/restore verification.

## Progress truthfulness

The repository's current top-level M14 module progress is **70%**. The remaining 30% is dominated by external evidence and execution gates and should not be incremented merely for planning documents, emails, or queued CI. Progress should advance only when one of the material trust gates above becomes verified.
