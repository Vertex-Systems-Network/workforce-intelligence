# M13 — Agent Lifecycle Reliability

Updated: 2026-08-23

M13 is a post-M12 productization/hardening phase. It does not retroactively change the completed M0–M12 weighted maturity model. Its purpose is to remove production lifecycle gaps that were discovered after the original modular roadmap reached its active-scope closure.

## Batch 1 — Managed desktop-agent lifecycle

### Defects closed

1. The Devices & Agents surface could queue `update_agent`, but the production native agent only acknowledged the command and instructed the operator to install a package manually.
2. The Windows installer enrolled with `WORKINTEL_AGENT_HOME=<install>\state`, but its Scheduled Task did not preserve that environment value. A later task start could therefore read the default `storage-native` directory instead of the enrolled device state.
3. Native agent, release builder, server runtime defaults, and deployment documentation did not have a single regression contract preventing release-version drift.
4. Agent 1.2.0 initially staged verified response bytes through a JavaScript network-to-file sink. Agent 1.2.1 removes that sink while retaining checksum verification before extraction.

### Managed update trust model

- Only an authenticated, active WorkIntel device token can access the agent release channel.
- The server maps the enrolled device platform to one fixed stable release slug: Windows, macOS, or Linux.
- `update_agent` command payloads cannot select a URL, hostname, path, or package.
- The agent accepts only the same-origin `/api/v1/agent/release/download` path returned by the server.
- Managed package transfer does not follow redirects; the bearer token is supplied to curl over stdin and the archive is streamed into an exclusive descriptor inside a random private temporary directory.
- The server verifies the release binary against the SHA-256 stored in its release manifest before serving it.
- The agent independently verifies the response metadata and downloaded SHA-256 before extraction.
- The extracted candidate must contain `desktop-agent/native-agent.mjs`, must declare the expected release version, and must pass `node --check` before replacement.
- The current source is retained as `native-agent.mjs.previous`; replacement failures restore the previous source.
- A successful update is acknowledged before process exit. The installation supervisor is responsible for restarting the agent.

### Supervisor behavior

- Windows: Scheduled Task launches `run-agent.ps1`, which restores the enrolled `WORKINTEL_AGENT_HOME` and restarts the agent after an intentional update/restart exit.
- macOS: LaunchAgent `KeepAlive` restarts the process.
- Linux: systemd user service uses `Restart=always`.

### Compatibility boundary

Version 1.2.0 is the first native agent that advertises `self_update`. Existing agents without that capability require one verified manual upgrade before remote managed updates become available. The application must not represent legacy agents as remotely self-updatable when the capability is absent. Version 1.2.1 is the security-hardening patch release so deployed 1.2.0 agents can receive the hardened updater through the managed channel.

## Batch 2 — Deterministic release packaging

### Defect closed

The release builder previously used `ZipFile.write()`, which copied checkout-dependent file timestamps and filesystem metadata into generated ZIPs. Rebuilding unchanged browser-extension sources in a fresh checkout could therefore produce a different package SHA-256 even though the source bytes were identical. That weakens checksum stability, creates noisy release-catalog churn, and prevents package hashes from serving as reproducible supply-chain evidence.

### Reproducible package contract

- Every archive entry is sorted by its normalized archive path before writing.
- ZIP entry timestamps are fixed to the ZIP epoch instead of inheriting checkout mtimes.
- Release archives use stored ZIP entries so package bytes do not depend on zlib implementation/version differences.
- Unix file modes are normalized to `0644`, with shell/command installers explicitly normalized to `0755`.
- Archive entries are written through `ZipInfo` + `writestr`; raw `ZipFile.write()` is forbidden by the source contract.
- `tools/release-reproducibility-audit.py` builds all packages twice in an isolated repository fixture, deliberately changes every source mtime between builds, and requires every ZIP SHA-256 to remain identical.
- The same audit verifies `manifest.json` and `SHA256SUMS.txt` exactly match the generated package bytes.
- WorkIntel CI runs the functional reproducibility audit before the frontend/browser matrix, so timestamp-dependent packaging cannot merge unnoticed.

Already-published 1.2.1 artifacts are not silently rewritten under the same semantic version merely to adopt the deterministic container format. The deterministic writer applies to subsequent release builds; any package-content change intended for publication must use the appropriate new agent or browser-extension release version rather than mutating a published artifact in place.

## Batch 3 — Published release immutability

### Defect closed

Batch 2 made future ZIP generation deterministic, but the builder still deleted and rebuilt every package in `storage/app/releases`. That meant an operator could accidentally change packaged source while leaving the semantic version unchanged and then replace a published binary and checksum under the same version. Documentation prohibited that behavior, but the release tool itself did not enforce the boundary.

### Immutable publication contract

- The existing release manifest is authoritative for a published slug/version pair.
- Before a same-version release can be reused, the committed ZIP must match the manifest SHA-256 and byte size. Corruption or manual binary replacement fails closed.
- New candidate ZIPs are built only in an isolated temporary path until publication eligibility is known.
- Same-version candidates are compared by normalized archive entry names and payload bytes, allowing legacy container metadata to differ while forbidding actual packaged-content drift.
- If packaged content differs under the same semantic version, the builder aborts and requires a version bump. It does not rewrite the existing ZIP, manifest, or checksum catalog.
- If packaged content is unchanged, the existing published ZIP bytes and original `released_at` value are retained.
- A target filename that already exists without a matching manifest/version record is treated as untracked release state and is never overwritten automatically.
- `tools/release-immutability-audit.py` proves that unchanged rebuilds are byte-for-byte no-ops, agent/Chrome/Firefox same-version source changes are rejected without release-state mutation, and manifest/binary integrity mismatches are not silently repaired.
- WorkIntel CI runs both the deterministic reproducibility audit and the published-release immutability audit before dependency installation and the browser matrix.

This contract applies to the currently published agent 1.2.1 and browser-extension 1.0.0 artifacts and to subsequent releases. Package-content changes require a semantic version change before publication.

## Batch 4 — Transactional release publication

### Defect closed

Batch 3 validated each release before writing that release, but validation and publication were still interleaved across the five-package catalog. In a mixed build, an earlier package with a legitimate version bump could therefore be published before a later same-version package failed immutability validation. The command would fail while leaving a new unreferenced ZIP behind, which violated the release directory's all-or-nothing expectation.

### Transactional publication contract

- All Windows, macOS, Linux, Chrome/Edge, and Firefox candidates are built into one private staging directory before any new release ZIP is written to `storage/app/releases`.
- Every candidate must pass filename, manifest-integrity, same-version payload, and untracked-target validation before the commit phase starts.
- Catalog rows, `manifest.json`, and `SHA256SUMS.txt` are derived from the fully validated staged/existing package set before publication.
- New ZIPs are committed only after validation of the complete catalog succeeds.
- If a filesystem error occurs during the commit phase, newly-created ZIPs are removed and any catalog file already replaced is restored from its pre-commit bytes.
- Obsolete ZIP cleanup happens only after the new package/catalog transaction has committed successfully.
- The functional immutability audit now exercises a mixed transaction: it bumps the desktop-agent patch version while also mutating an unchanged-version browser package. The later browser rejection must leave the complete release directory byte-for-byte identical and must not leave any new-version agent ZIP behind.

This closes validation-time partial publication while preserving the same immutable semantic-version and deterministic-byte guarantees established by Batches 2 and 3.

## Automated acceptance

The phase is protected by:

- `tests/Feature/AgentReleaseUpdateFlowTest.php` for device-token authentication, platform scoping, release metadata, download headers, and fail-closed tamper detection.
- `tests/frontend/agent-lifecycle-m13.test.mjs` for native-agent syntax, trusted route/source contracts, Windows state continuity, supervisor behavior, secure managed-download behavior, and release-version alignment.
- `tests/frontend/release-packaging-m13.test.mjs` for deterministic ZIP invariants, published-release immutability, transactional publication source contracts, and CI wiring.
- `tools/release-reproducibility-audit.py` for actual repeated-build hash stability plus manifest/checksum consistency.
- `tools/release-immutability-audit.py` for same-version no-op preservation, packaged-content drift rejection, published binary integrity enforcement, and failed mixed-version transaction rollback.
- Existing repository quality gates: PHP documentation audit, JavaScript documentation audit, frontend contracts, TypeScript, accessibility/source audits, Laravel Pint for changed PHP, CodeQL, PHPUnit, production build, and browser certification.

Batch 1 is merged and certified on `main` through PR #17 with agent 1.2.1. Batch 2 is merged and exact-head certified on `main` through PR #18. Batch 3 is considered certified only after its exact final PR head passes WorkIntel Code Quality, WorkIntel CI including both release audits, and WorkIntel Windows Certification with no current unresolved security review findings. Batch 4 remains stacked on Batch 3 until Batch 3 is merged, then requires its own exact-head certification before merge.
