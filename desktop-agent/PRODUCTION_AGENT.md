# WorkIntel Native Agent 1.2.2

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

The supplied canonical packages are deployment sources, not vendor-signed MSI/PKG artifacts. Enterprise code signing, Apple notarization, Microsoft Authenticode and store signing require organization-owned signing identities and remain release-pipeline responsibilities.

## 1.2.2 server-bound deployment bootstrap

Agent 1.2.2 adds code-only setup for packages downloaded from the authenticated WorkIntel Downloads & Installation Center. The application verifies the canonical published ZIP, copies it to a private temporary deployment bundle, and injects only `desktop-agent/workintel-server.txt` containing the current WorkIntel request origin. The one-time enrollment code, device token and user secrets are never written into the package.

Windows, macOS and Linux installers read that server binding automatically, so the normal deployment command requires only the enrollment code. Explicit server URL arguments remain supported as a raw-canonical-package/legacy operator fallback and do not change the canonical managed-update trust model.

On Windows, the installer detects Node's `--use-system-ca` capability and enables it when available so HTTPS certificates already trusted by the Windows Trusted Root store can be used without disabling TLS validation.

The runtime-generated server-bound ZIP is not a new canonical release artifact. Its SHA differs because it contains the deployment origin; the immutable canonical release SHA remains the supply-chain provenance anchor and is verified before derivation.

## Managed update lifecycle

Agent 1.2.0 introduced the `self_update` capability; 1.2.1 hardened the managed download trust boundary. Agent 1.2.2 preserves that model. When an Admin sends `update_agent`, the running agent requests release metadata through its existing device token. The server chooses the release slug from the enrolled device platform; the command payload cannot provide an arbitrary update URL.

The agent accepts only the same-origin `/api/v1/agent/release/download` path, verifies the release metadata checksum format, and requires the operating-system `curl` command for managed downloads. The bearer token is passed to curl through standard input rather than process arguments; curl streams the archive into an exclusive file descriptor inside an isolated random temporary directory. The agent verifies response metadata and the downloaded SHA-256 before extraction, then checks that the candidate contains the expected `desktop-agent/native-agent.mjs`, verifies the candidate version and runs `node --check` before replacing the installed source.

Before replacement, the current source is copied to `native-agent.mjs.previous`. A replacement failure restores that copy. After a successful replacement the command is acknowledged and the process exits so the platform supervisor starts the new source. Windows uses the installed `run-agent.ps1` supervisor loop; macOS uses LaunchAgent `KeepAlive`; Linux uses systemd-user `Restart=always`.

Agents older than 1.2.0 do not advertise `self_update`. They require one manual upgrade from the verified Downloads & Installation Center package before managed updates can be used. Agent 1.2.0 can self-update through the hardened managed channel; 1.2.2 is the current stable native-agent release.

## Windows state continuity

The Windows installer stores enrollment state under `%LOCALAPPDATA%\WorkIntelAgent\state` and the Scheduled Task launches a wrapper that sets `WORKINTEL_AGENT_HOME` to that exact directory on every process start. This prevents the post-logon agent from falling back to a different `storage-native` directory and losing the enrolled device/token context.

## Screenshot notifications

The native agent consumes `capture_notification_mode` from workspace screenshot policy:
- `always`: show an OS notification after every successful capture/upload.
- `first_session`: notify only on the first successful capture after the agent starts.
- `silent`: do not show capture notifications.

When `notify_on_upload_failure` is enabled, the native agent also warns when a screenshot upload fails. Windows uses a native system popup fallback, macOS uses Notification Center through `osascript`, and Linux uses `notify-send` when available. Notification failure never blocks capture or upload.
