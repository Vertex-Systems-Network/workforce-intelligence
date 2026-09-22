# M14 external release administration evidence

This document defines a read-only evidence packet for Issue #62. It does not configure GitHub, publish a release, or expose secret values.

## Purpose

After an administrator configures M14 external release controls, collect GitHub REST snapshots and validate them with:

```bash
node tools/verify-m14-release-admin-config.mjs \
  --repository Vertex-Systems-Network/workforce-intelligence \
  --source-sha <exact-M14-source-sha> \
  --as-of "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  < m14-release-admin-evidence.json
```

The verifier expects schema `workintel.m14-release-admin-evidence.v1` and fails closed unless all source-visible M14 admin requirements are represented.

## API snapshots

Collect these read-only GitHub API responses with an appropriately scoped administrator/auditor token:

```bash
gh api -H 'X-GitHub-Api-Version: 2026-03-10' \
  repos/Vertex-Systems-Network/workforce-intelligence/immutable-releases

gh api -H 'X-GitHub-Api-Version: 2026-03-10' \
  repos/Vertex-Systems-Network/workforce-intelligence/environments/production-release

gh api -H 'X-GitHub-Api-Version: 2026-03-10' \
  repos/Vertex-Systems-Network/workforce-intelligence/environments/production-release/deployment-branch-policies

gh api -H 'X-GitHub-Api-Version: 2026-03-10' \
  repos/Vertex-Systems-Network/workforce-intelligence/environments/production-release/secrets?per_page=100

gh api -H 'X-GitHub-Api-Version: 2026-03-10' \
  repos/Vertex-Systems-Network/workforce-intelligence/environments/production-release/variables?per_page=30
```

Environment secret list responses expose names/metadata only, not encrypted values. Environment variable responses expose non-secret values, so the verifier can validate signer fingerprints and the HTTPS timestamp endpoint.

## Evidence packet

The final JSON object contains `source_contract_sha`, which must equal the exact source SHA supplied to `--source-sha`. This prevents an evidence packet collected for one release-trust contract from silently validating a later changed contract.

It also contains `collected_at`. The verifier requires an explicit `--as-of` timestamp and rejects evidence older than 30 minutes, evidence collected after the verifier time, attestations made before collection, or attestations dated after verification. This prevents a previously valid admin snapshot from being replayed after live GitHub configuration changes.

The final JSON object also contains:

- `immutable_releases`: repository immutable-release response;
- `environment`: `production-release` environment response;
- `deployment_branch_policies`: custom deployment branch/tag policy list;
- `environment_secrets`: environment secret list;
- `environment_variables`: environment variable list;
- `attestation`: administrator-only facts that GitHub read APIs do not prove strongly enough for M14.

Required auditor attestations:

- administrator bypass is disabled for the protected release environment;
- required reviewer independence from the release initiator/operator is verified;
- the `main` custom deployment policy is a branch policy;
- the `agent-v*` custom deployment policy is a tag policy;
- `WORKINTEL_RELEASE_POLICY_READ_TOKEN` was independently checked to have only the least privilege needed for the immutable-release Administration read;
- `WORKINTEL_WINDOWS_SIGNING_CERT_SHA256` was compared out of band to the approved organization Windows Code Signing certificate;
- `WORKINTEL_APPLE_SIGNING_CERT_SHA256` was compared out of band to the approved organization Apple Developer ID leaf certificate;
- real auditor identity and ISO-8601 audit time are recorded.

## Fail-closed checks

The verifier requires:

- immutable releases enabled;
- environment name exactly `production-release`;
- at least one required reviewer and `prevent_self_review=true`;
- custom deployment policies enabled;
- both `main` and `agent-v*` policies present;
- all nine M14 environment secret names present;
- `WORKINTEL_WINDOWS_TIMESTAMP_URL` uses HTTPS;
- both Windows and Apple approved signer fingerprints are exactly 64 hexadecimal SHA-256 characters;
- all seven administrator attestations are true and auditor metadata is valid;
- the evidence snapshot is fresh (maximum age 30 minutes) and audit timestamps are ordered correctly.

This evidence complements, but does not replace, `tools/verify-release-tag-protection.mjs` and the committed `M14_RELEASE_TAG_RULESET_ATTESTATION.json`. Real signing, notarization, publication, and real-target evidence remain separate gates.
