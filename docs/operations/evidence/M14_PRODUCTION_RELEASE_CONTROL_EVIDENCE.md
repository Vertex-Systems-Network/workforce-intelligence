# M14 production-release control evidence

**Workflow:** M14 Production Release Control Evidence  
**Run:** 36176653160 / attempt 1  
**Source:** protected `main` at `a168673e3ac38f3b7cc966945ec9a0a23b45fbb5`  
**Result:** SUCCESS  
**Collected:** 2026-09-25T18:57:27Z

## Live API-verified controls

- immutable Releases: enabled;
- environment: `production-release`;
- required-reviewer rule present with `prevent_self_review=true`;
- wait timer rules: none;
- custom deployment policies enabled;
- exact deployment policy names: `main`, `agent-v*`;
- environment secret names: `WORKINTEL_RELEASE_POLICY_READ_TOKEN` only;
- repository secret names: `WORKINTEL_M14_ADMIN_AUDIT_TOKEN` only;
- environment variables: none;
- repository variables: none;
- auditor identity bound to the authenticated GitHub user;
- evidence bound to exact protected-main SHA.

## Scope caveat

This is the independent Gate B control-plane evidence subset. It does not prove GitHub facts that the read APIs do not expose strongly enough, such as administrator-bypass posture or branch-vs-tag policy type; those remain administrator-attested. It also does not prove Windows/Apple signer material, actual signing, notarization, publication, or real-target production readiness.
