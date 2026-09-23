# AI-Only Single-Maintainer Review Policy

**Status:** OWNER-AUTHORIZED  
**Authority:** GitHub Issue #96  
**Applies to:** repository review/closure decisions when no verified second human reviewer or team is available  
**Evidence label:** `AI-reviewed + owner-authorized`

## Decision

WorkIntel may use an AI-only single-maintainer review path instead of a mandatory second-human review when the repository owner explicitly authorizes that mode for the exact scope.

This policy does **not** convert self-review or AI review into "independent human review". Evidence must remain truthful: if no second human reviewed the exact SHA, the repository must not claim that one did.

## Mandatory closure gates

AI-only single-maintainer closure requires all of the following on the exact candidate head:

1. explicit repository-owner authorization recorded in a repository-native Issue or PR;
2. an AI security/quality/release review of the exact SHA with affected/unaffected scope and material risks stated;
3. every repository-required exact-head automated certification lane terminal green;
4. zero unresolved review threads;
5. no unresolved high-severity security, privacy, data-safety, release-trust, migration, or authorization finding;
6. current protected-main/head freshness reconciled before merge;
7. expected-head merge protection so a moved head cannot reuse older evidence;
8. any unavailable external/provider/admin/real-target evidence remains `Not Verified`;
9. destructive or privileged production actions retain their own explicit authorization boundary.

A newer source head invalidates older exact-head review and automated evidence.

## When human review is still required

A second-human review remains required when an applicable law, contract, customer commitment, organization policy, platform rule, or explicit repository authority specifically mandates a human reviewer. This policy cannot override a higher external requirement.

If a qualified second human reviewer is available, the owner may still choose independent human review as an additional assurance layer.

## High-risk scopes

For HIGH-risk security or release-trust work, AI review must explicitly inspect fail-closed behavior, secret/credential boundaries, exact-source binding, privilege separation, stale-state/replay risks, rollback/recovery, and evidence truthfulness.

Automation does not prove external facts. Green CI cannot by itself prove live GitHub administration settings, signing credentials, notarization, release publication, production health, or recovery success.

## M14 application

For M14 / PR #65:

- Issue #96 is the owner authorization for AI-only single-maintainer review.
- Issue #61 becomes an AI-review evidence gate rather than a second-human gate once this policy is present on the exact PR head.
- PR #65 must receive fresh exact-head certification after the governance change.
- Issue #62 remains a separate external configuration/evidence gate.
- Source merge must not be reported as `PRODUCTION_VERIFIED` or as proof of external release configuration.

## Risk acceptance

The owner accepts the reduced reviewer diversity created by removing a mandatory second-human gate. Compensating controls are exact-head automation, explicit AI security review, zero unresolved high-severity findings, stale-head protection, owner authorization, and truthful separation of source evidence from external production evidence.

## Review-mode terminology

Allowed:
- `AI-reviewed + owner-authorized`
- `single-maintainer AI review`
- `independent human review` only when a different human actually reviewed the exact SHA

Forbidden:
- describing AI/self review as independent human review;
- fabricating reviewer identities or approvals;
- weakening or skipping required exact-head CI to compensate for missing human review.
