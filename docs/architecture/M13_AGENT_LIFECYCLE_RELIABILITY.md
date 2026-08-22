# M13 — Agent Lifecycle Reliability

Updated: 2026-08-22

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

## Automated acceptance

The phase is protected by:

- `tests/Feature/AgentReleaseUpdateFlowTest.php` for device-token authentication, platform scoping, release metadata, download headers, and fail-closed tamper detection.
- `tests/frontend/agent-lifecycle-m13.test.mjs` for native-agent syntax, trusted route/source contracts, Windows state continuity, supervisor behavior, and release-version alignment.
- Existing repository quality gates: PHP documentation audit, JavaScript documentation audit, frontend contracts, TypeScript, accessibility/source audits, Laravel Pint for changed PHP, CodeQL, PHPUnit, production build, and browser certification.

No M13 item is considered certified until the exact final PR head passes the repository's required CI and code-quality workflows.
