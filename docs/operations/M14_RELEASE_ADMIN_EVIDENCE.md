# M14 external release administration evidence

This document defines a read-only evidence packet for Issue #62. It does not configure GitHub, publish a release, or expose secret values.

## Purpose

After an administrator configures M14 external release controls, collect GitHub REST snapshots and validate them with:

```bash
node tools/verify-m14-release-admin-config.mjs \
  --repository Vertex-Systems-Network/workforce-intelligence \
  --source-sha <exact-M14-source-sha> \
  < m14-release-admin-evidence.json
```

The verifier expects schema `workintel.m14-release-admin-evidence.v1`, requires `github_api_version: "2026-03-10"`, and fails closed unless all source-visible M14 admin requirements are represented. API-version binding prevents future response-schema changes from silently reinterpreting older evidence.

## API snapshots

Collect these read-only GitHub API responses with a dedicated administrator/auditor credential.

Do **not** reuse or broaden `WORKINTEL_RELEASE_POLICY_READ_TOKEN` for this collection. That trusted-workflow token remains limited to repository **Administration: read** for the immutable-release check. The separate evidence-collection credential needs the read permissions required by the endpoints below, including **Administration: read** for immutable releases and **Environments: read** for environment secrets/variables. Keep the auditor credential outside the trusted release workflow and do not store its value in the evidence packet.


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

The final JSON object contains `source_contract_sha`, which must equal the exact source SHA supplied to `--source-sha`. This prevents an evidence packet collected for one release-trust contract from silently validating a later changed contract.

It also contains `collected_at`. The verifier binds verification time to its own system clock and rejects caller-controlled `--as-of` values, evidence older than 30 minutes, evidence collected after the verifier time, attestations made before collection, or attestations dated after verification. This prevents a previously valid admin snapshot from being replayed after live GitHub configuration changes by supplying an old reference time.

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
- all eight administrator attestations are true and auditor metadata is valid;
- the evidence snapshot is fresh (maximum age 30 minutes) and audit timestamps are ordered correctly.

This evidence complements, but does not replace, `tools/verify-release-tag-protection.mjs` and the committed `M14_RELEASE_TAG_RULESET_ATTESTATION.json`. Real signing, notarization, publication, and real-target evidence remain separate gates.
