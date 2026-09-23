# WorkIntel production checklist

Before traffic:

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] strong `APP_KEY`
- [ ] HTTPS `APP_URL`
- [ ] database backup completed
- [ ] `php artisan workintel:migration-doctor` reviewed
- [ ] `php artisan migrate --force` completes
- [ ] `npm run typecheck` and `npm run build` complete
- [ ] `php artisan test` passes
- [ ] `php artisan optimize` completes
- [ ] scheduler runs once per minute
- [ ] queue worker is supervised
- [ ] `/health/live` returns 200
- [ ] `/health/ready` returns 200
- [ ] SMTP/provider settings configured where needed
- [ ] screenshot/report storage selected for deployment topology
- [ ] webhook private-network access remains disabled unless intentionally required
- [ ] manual SaaS invoice settlement remains disabled unless this is an internal deployment
- [ ] desktop/browser canonical release checksums verified
- [ ] trusted standalone artifact source SHA matches the intended release revision
- [ ] one dedicated active repository/organization tag ruleset is externally verified to apply to authorized `agent-v*` release tags with both tag-update and tag-deletion restrictions and **no bypass actors**
- [ ] GitHub **immutable releases** are enabled for this repository (directly or by organization policy) before any trusted release is published
- [ ] `production-release` stores `WORKINTEL_RELEASE_POLICY_READ_TOKEN` with the minimum Administration **read** capability needed to verify `GET /repos/{owner}/{repo}/immutable-releases`
- [ ] trusted release workflow fails closed if immutable-release policy cannot be verified before trust work and rechecks it immediately before public exposure
- [ ] after draft-to-public transition, GitHub native release verification proves the published immutable release and every attached local artifact; an ambiguous non-zero publish response is accepted only when that final immutable postcondition verifies
- [ ] the exact audited ruleset ID and GitHub `updated_at` snapshot are recorded in `docs/operations/M14_RELEASE_TAG_RULESET_ATTESTATION.json` with `status=VERIFIED`, `no_bypass_actors_attested=true`, auditor identity and an audit timestamp at or after the ruleset snapshot
- [ ] the attestation update itself is committed through normal exact-head source certification and the active repository review policy (AI-only single-maintainer review under Issue #96, or independent human review when a higher requirement mandates it); do not edit release trust evidence directly on protected `main`
- [ ] release-tag creation authority is restricted to the intended operator/process; tag creation policy is reviewed separately from post-creation update/deletion immutability
- [ ] the trusted tag workflow's automated proof succeeds using the ordinary metadata-read GitHub token; no repository-Administration/ruleset-write PAT or App token is exposed to the unprivileged authorization job
- [ ] automated proof matches the attested ruleset ID and exact `updated_at`, verifies active tag/ref applicability plus update/deletion restrictions, and fails closed if the snapshot changes; if GitHub exposes `bypass_actors` to the caller it must also be empty
- [ ] `production-release` environment deployment rules are externally verified to allow only the intended `main` manual-dispatch branch and authorized `agent-v*` release tags before signing/notary secrets are attached; environment deployment rules do not substitute for tag immutability rules
- [ ] `production-release` environment review posture is explicitly documented: when a human required-reviewer control is configured, self-review prevention and administrator bypass are disabled where supported; in AI-only single-maintainer mode, do not claim a human environment-review control and retain the exact-source/tag/immutable-release gates plus explicit owner authorization
- [ ] environment signing/notary secrets are unavailable until the configured deployment protection rules pass
- [ ] `production-release` defines `WORKINTEL_WINDOWS_SIGNING_CERT_SHA256` as the approved 64-hex SHA-256 fingerprint for the organization Windows Code Signing certificate
- [ ] Windows standalone distribution has a verified Authenticode signature and RFC 3161 timestamp, and the imported signing certificate fingerprint exactly matches `WORKINTEL_WINDOWS_SIGNING_CERT_SHA256`, when Windows enterprise distribution is in scope
- [ ] `production-release` defines `WORKINTEL_APPLE_SIGNING_CERT_SHA256` as the approved 64-hex SHA-256 fingerprint for the organization Developer ID certificate
- [ ] macOS standalone distribution has a verified Developer ID signature, the imported leaf certificate fingerprint exactly matches `WORKINTEL_APPLE_SIGNING_CERT_SHA256`, and Apple notarization returns Accepted when macOS enterprise distribution is in scope
- [ ] Linux standalone distribution has final SHA-256/provenance evidence and is not described as platform-signed unless such signing actually occurred
- [ ] every trusted standalone artifact has a machine-readable M14 receipt whose final digest matches the distributed bytes; Windows SIGNED receipts bind `authenticode-cert-sha256:<fingerprint>`, and macOS NOTARIZED receipts bind both Apple notarization ID and `developer-id-cert-sha256:<fingerprint>` evidence
- [ ] M13 canonical ZIP bytes/version immutability remains unchanged by trusted standalone distribution
- [ ] backup and rollback procedure tested
- [ ] backup-to-restore evidence exists before recovery is described as `restore verified`

## Release-state truthfulness

Do not collapse these evidence states:

- `BUILT` — deterministic source build completed;
- `HASH_VERIFIED` — final artifact checksum/provenance verified;
- `SIGNED` — platform signature actually applied and verified;
- `NOTARIZED` — Apple notary service actually returned an accepted result for the macOS distribution;
- `RELEASED` — the intended immutable distribution assets were actually published;
- `PRODUCTION_VERIFIED` — the exact released revision/artifact was actually verified on the stated real target.

Hosted CI or a merged PR can prove source/build contracts but cannot, by itself, prove signing credentials existed, Apple notarization ran, a release was published, release-tag protections/attestation are configured correctly, the `production-release` environment is correctly protected, or a production target was healthy. Missing evidence remains `Not Verified`.

## Block I certification

- [ ] `php artisan workintel:production-doctor --json --require-build` passes
- [ ] `npm run test:e2e:public` passes on the deployment host or release candidate
- [ ] `npm run test:e2e:full` passes on a disposable seeded certification database
- [ ] dropdowns remain open/anchored during nested page scroll
- [ ] table row-action menus are not clipped by table overflow
- [ ] desktop, tablet and mobile browser projects have no viewport-wide overflow
- [ ] repeated EN/TR/AR/UR/RU switching does not duplicate navigation
- [ ] Arabic RTL browser journey passes
- [ ] Seller Platform remains separate from tenant navigation
- [ ] final release archive was re-extracted and dependency-free audits were rerun
