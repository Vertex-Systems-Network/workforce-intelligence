# WorkIntel Native Agent 1.2.0

`native-agent.mjs` is the production desktop runtime. It uses Node.js built-ins plus operating-system commands and remains a single cross-platform agent source for Windows, macOS, and Linux.

Features:
- one-time device enrollment
- dedicated hashed server-side device token
- single-instance lock
- heartbeat and live presence
- foreground application sessions
- idle detection
- durable offline queue
- command acknowledgements
- screenshot capture with workspace policy enforcement
- privacy blur fail-closed behavior
- reconnect recovery
- per-user native service installers for Windows/macOS/Linux
- authenticated, platform-scoped managed self-update with SHA-256 verification
- staged source replacement with a retained previous-agent rollback copy

The browser extension remains the source of domain-only browser activity; the native agent does not collect full URLs, typed text, form values, clipboard data, or keystrokes.

The supplied packages are deployment packages, not vendor-signed MSI/PKG artifacts. Enterprise code signing, Apple notarization, Microsoft Authenticode and store signing require organization-owned signing identities and remain release-pipeline responsibilities.

## Managed update lifecycle

Agent 1.2.0 advertises the `self_update` capability. When an Admin sends `update_agent`, the running agent requests release metadata through its existing device token. The server chooses the release slug from the enrolled device platform; the command payload cannot provide an arbitrary update URL.

The agent accepts only the same-origin `/api/v1/agent/release/download` path, verifies the release metadata checksum format, downloads with its device token, verifies the response version and SHA-256, extracts into an isolated temporary directory, checks that the candidate contains the expected `desktop-agent/native-agent.mjs`, verifies the candidate version and runs `node --check` before replacing the installed source.

Before replacement, the current source is copied to `native-agent.mjs.previous`. A replacement failure restores that copy. After a successful replacement the command is acknowledged and the process exits so the platform supervisor starts the new source. Windows uses the installed `run-agent.ps1` supervisor loop; macOS uses LaunchAgent `KeepAlive`; Linux uses systemd-user `Restart=always`.

Agents older than 1.2.0 do not advertise `self_update`. They require one manual upgrade from the verified Downloads & Installation Center package before managed updates can be used.

## Windows state continuity

The Windows installer stores enrollment state under `%LOCALAPPDATA%\WorkIntelAgent\state` and the Scheduled Task now launches a wrapper that sets `WORKINTEL_AGENT_HOME` to that exact directory on every process start. This prevents the post-logon agent from falling back to a different `storage-native` directory and losing the enrolled device/token context.

## Screenshot notifications

The native agent consumes `capture_notification_mode` from workspace screenshot policy:
- `always`: show an OS notification after every successful capture/upload.
- `first_session`: notify only on the first successful capture after the agent starts.
- `silent`: do not show capture notifications.

When `notify_on_upload_failure` is enabled, the native agent also warns when a screenshot upload fails. Windows uses a native system popup fallback, macOS uses Notification Center through `osascript`, and Linux uses `notify-send` when available. Notification failure never blocks capture or upload.
