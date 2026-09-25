# M14 external release administration evidence

This document defines a read-only evidence packet for Issue #62. It does not configure GitHub, publish a release, or expose secret values.

## Purpose

After an administrator configures M14 external release controls, the **authoritative** verifier performs the live GitHub collection itself. Do not pipe a caller-built evidence packet into the authoritative path:

```bash
export WORKINTEL_M14_ADMIN_AUDIT_TOKEN='<ephemeral read-only auditor token>'
node tools/verify-m14-release-admin-config.mjs \
  --repository Vertex-Systems-Network/workforce-intelligence \
  --source-sha <exact-M14-source-sha> \
  --attestation-file ./m14-admin-attestation.json
unset WORKINTEL_M14_ADMIN_AUDIT_TOKEN
```

The verifier calls the collector in-process. The collector hardcodes `https://api.github.com`, reads `GET /user` with the same auditor credential, sends that credential only in the Authorization header, disables redirects, pins GitHub API version `2026-03-10`, and never writes the token into the evidence packet. The verifier requires `attestation.audited_by` to equal the authenticated GitHub login returned by `/user`. All list endpoints are fully paginated until `total_count` is collected; changing counts, premature empty pages, over-returned items, or lists above the 10,000-item safety cap fail closed.

Archived packets may be checked only as **non-authoritative structural evidence**:

```bash
node tools/verify-m14-release-admin-config.mjs \
  --repository Vertex-Systems-Network/workforce-intelligence \
  --source-sha <exact-M14-source-sha> \
  --offline-structural true \
  < m14-release-admin-evidence.json
```

Structural mode returns `"authoritative": false` and `"provenance": "caller-supplied-structural-only"`. It cannot satisfy Issue #62 closure or substitute for a fresh live verification. The authoritative verifier expects schema `workintel.m14-release-admin-evidence.v2`, requires `github_api_version: "2026-03-10"`, and binds the evidence to both the exact source SHA and authenticated auditor identity.



## Independent Gate B1 immutable-release API evidence lane

For Gate B1 only, the repository also provides a narrow manual workflow:

`.github/workflows/m14-immutable-release-evidence.yml`

This workflow is intentionally separate from the trusted release workflow. It:

- can only be started with `workflow_dispatch`;
- must run from `refs/heads/main`;
- uses the protected `production-release` environment;
- scopes `WORKINTEL_RELEASE_POLICY_READ_TOKEN` to exactly one shell step;
- performs only `GET /repos/{owner}/{repo}/immutable-releases` with GitHub API version `2026-03-10`;
- fails unless the live response is an object with `enabled=true`;
- checks protected `main` immediately before and after collection so evidence cannot silently bind to a stale source SHA;
- writes only a sanitized JSON artifact containing repository/run metadata and `immutable_releases.enabled=true`; the credential and raw authorization header are never written to the artifact;
- has read-only workflow permissions and contains no release publication, signing, tag mutation, or repository write operation.

After this workflow is merged to protected `main`, run **Actions → M14 Immutable Release Policy Evidence → Run workflow** with branch `main`. If the `production-release` environment requires approval, complete that approval. The resulting `m14-immutable-release-evidence-*` artifact is the independent live API evidence for Gate B1.

This lane does not satisfy the broader M14 admin-evidence verifier by itself and does not replace signer, notarization, publication, or real-target evidence.



## Independent Gate B control-plane evidence lane

Before real Windows/macOS signer material exists, verify the independently attainable `production-release` control plane with:

`.github/workflows/m14-production-release-control-evidence.yml`

This lane does **not** weaken or replace the full `verify-m14-release-admin-config.mjs` closure verifier. It intentionally verifies only controls that can be proven without real signer credentials:

- required-reviewer protection exists and `prevent_self_review=true`;
- no wait timer is configured;
- custom deployment branch/tag policies are enabled;
- deployment policy activation is read from the top-level `deployment_branch_policy` object; `protection_rules` is not required to expose a synthetic `branch_policy` rule;
- the only deployment policy names are `main` and `agent-v*`;
- `WORKINTEL_RELEASE_POLICY_READ_TOKEN` exists at `production-release` environment scope;
- the release-policy token and all signer/notary secret names are absent at repository scope;
- signer fingerprint/timestamp variable names are absent at repository scope;
- immutable Releases remains enabled;
- the auditor identity and exact protected-main source SHA are recorded;
- the uploaded artifact contains names/metadata only and never credential values.

GitHub's deployment policy read response does not expose whether each existing name pattern was created as branch vs tag, so the already-recorded administrator attestation remains the evidence for `main=branch` and `agent-v*=tag`. Administrator-bypass posture and the least-privilege scope of the release-policy token likewise remain explicit administrator attestations where the read APIs do not prove them.

The workflow uses a separate **repository secret** named `WORKINTEL_M14_ADMIN_AUDIT_TOKEN`. Do not place that audit credential in `production-release`, because it is evidence-collection authority, not release authority. Use a fine-grained token limited to this repository with read-only permissions needed by the existing collector contract: Administration, Actions, Environments, Secrets, and Variables. This token is never passed to the trusted release workflow.

After this workflow is merged to protected `main`, manually dispatch it from `main`. The `production-release` reviewer gate still applies to the evidence job.

## API snapshots

Collect these read-only GitHub API responses with a dedicated administrator/auditor credential.

Do **not** reuse or broaden `WORKINTEL_RELEASE_POLICY_READ_TOKEN` for this collection. That trusted-workflow token remains limited to repository **Administration: read** for the immutable-release check.

Use a separate read-only evidence-collection credential with only the permissions required by the endpoints below:
- **Administration: read** — immutable Releases policy;
- **Actions: read** — environment metadata and deployment branch/tag policy reads;
- **Environments: read** — environment secret/variable metadata;
- **Secrets: read** — repository-scoped Actions secret-name metadata;
- **Variables: read** — repository-scoped Actions variable metadata.

Keep the auditor credential outside the trusted release workflow, do not store its value in the evidence packet, and attest that it was created with no additional write/broader permissions.


```bash
gh api -H 'X-GitHub-Api-Version: 2026-03-10' \
  repos/Vertex-Systems-Network/workforce-intelligence/immutable-releases

gh api -H 'X-GitHub-Api-Version: 2026-03-10' \
  repos/Vertex-Systems-Network/workforce-intelligence/environments/production-release

gh api -H 'X-GitHub-Api-Version: 2026-03-10' \
  'repos/Vertex-Systems-Network/workforce-intelligence/environments/production-release/deployment-branch-policies?per_page=100'

gh api -H 'X-GitHub-Api-Version: 2026-03-10' \
  'repos/Vertex-Systems-Network/workforce-intelligence/environments/production-release/secrets?per_page=100'

gh api -H 'X-GitHub-Api-Version: 2026-03-10' \
  'repos/Vertex-Systems-Network/workforce-intelligence/environments/production-release/variables?per_page=100'


gh api -H 'X-GitHub-Api-Version: 2026-03-10' \
  'repos/Vertex-Systems-Network/workforce-intelligence/actions/secrets?per_page=100'

gh api -H 'X-GitHub-Api-Version: 2026-03-10' \
  'repos/Vertex-Systems-Network/workforce-intelligence/actions/variables?per_page=100'
```

Environment secret list responses expose names/metadata only, not encrypted values. Environment variable responses expose non-secret values, so the verifier can validate signer fingerprints and the HTTPS timestamp endpoint.

## Evidence packet

The final JSON object contains `source_contract_sha`, which must equal the exact source SHA supplied to `--source-sha`. It also contains the minimized authenticated `auditor_identity` returned by GitHub `GET /user`. The verifier requires the attestation's `audited_by` value to match that live identity. This prevents a caller from fabricating an arbitrary auditor identity or reusing evidence for a different release-trust contract.

It also contains `collected_at`. The verifier binds verification time to its own system clock and rejects caller-controlled `--as-of` values, snapshots older than 30 minutes, administrator attestations older than 30 minutes, and future-dated values. Because both timestamps are independently constrained to the same non-future 30-minute verification window, no separate snapshot-to-attestation distance rule is needed. The attestation may be made shortly before or shortly after the live GitHub collection while remaining fresh.

The evidence schema and attestation object are exact-key allowlists. Unknown fields are rejected so tokens, passwords, or unrelated sensitive values cannot be accidentally serialized. Archived packets remain structural records only; authoritative verification always recollects live GitHub state.

The final JSON object also contains:

- `immutable_releases`: repository immutable-release response;
- `environment`: `production-release` environment response;
- `deployment_branch_policies`: custom deployment branch/tag policy list;
- `environment_secrets`: environment secret list;
- `environment_variables`: environment variable list;
- `repository_secrets`: repository-scoped Actions secret-name list;
- `repository_variables`: repository-scoped Actions variable list;
- `attestation`: administrator-only facts that GitHub read APIs do not prove strongly enough for M14.

Required auditor attestations:

- administrator bypass is disabled for the protected release environment;
- required reviewer independence from the release initiator/operator is verified;
- the `main` custom deployment policy is a branch policy;
- the `agent-v*` custom deployment policy is a tag policy;
- `WORKINTEL_RELEASE_POLICY_READ_TOKEN` was independently checked to have only the least privilege needed for the immutable-release Administration read;
- `WORKINTEL_WINDOWS_SIGNING_CERT_SHA256` was compared out of band to the approved organization Windows Code Signing certificate;
- `WORKINTEL_APPLE_SIGNING_CERT_SHA256` was compared out of band to the approved organization Apple Developer ID leaf certificate;
- no organization-scoped secret or variable grants the repository the same M14 release credentials outside the protected environment;
- the dedicated admin-audit credential is read-only and limited to the five required permission classes above, with no write/broader scope;
- real auditor identity and ISO-8601 audit time are recorded.

## Fail-closed checks

The verifier requires:

- immutable releases enabled;
- environment name exactly `production-release`, a positive environment id, and an API URL bound to the expected repository;
- at least one structurally valid User/Team required reviewer and `prevent_self_review=true`;
- custom deployment policies enabled;
- all list snapshots are complete (`total_count` exactly equals collected entries), with duplicate names rejected;
- exactly two deployment policies exist: `main` and `agent-v*`, each with API policy ids/node ids; no extra branch/tag deployment policy is allowed;
- exactly the nine M14 environment secret names are present; no unrelated secret is allowed in the privileged environment;
- exactly the three M14 environment variables are present; no unrelated variable is allowed;
- none of the nine M14 secrets or three M14 variables exist at repository scope;
- organization-scope absence is explicitly administrator-attested;
- `WORKINTEL_WINDOWS_TIMESTAMP_URL` uses HTTPS;
- both Windows and Apple approved signer fingerprints are exactly 64 hexadecimal SHA-256 characters;
- all nine administrator attestations are true and auditor metadata is valid;
- both the evidence snapshot and administrator attestation are independently fresh (maximum age 30 minutes) and future timestamps are rejected.

This evidence complements, but does not replace, `tools/verify-release-tag-protection.mjs` and the committed `M14_RELEASE_TAG_RULESET_ATTESTATION.json`. Real signing, notarization, publication, and real-target evidence remain separate gates.
