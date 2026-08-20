# WorkIntel Native Agent 1.0.0

`native-agent.mjs` is the Milestone 14 production runtime. It uses only Node.js built-ins plus operating-system commands.

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

The browser extension remains the source of domain-only browser activity; the native agent does not collect full URLs, typed text, form values, clipboard data, or keystrokes.

The supplied packages are deployment packages, not vendor-signed MSI/PKG artifacts. Enterprise code signing, Apple notarization, Microsoft Authenticode and store signing require organization-owned signing identities and are intentionally CI/release-pipeline steps.

## P8 screenshot notifications

The native agent consumes `capture_notification_mode` from workspace screenshot policy:
- `always`: show an OS notification after every successful capture/upload.
- `first_session`: notify only on the first successful capture after the agent starts.
- `silent`: do not show capture notifications.

When `notify_on_upload_failure` is enabled, the native agent also warns when a screenshot upload fails. Windows uses a native system popup fallback, macOS uses Notification Center through `osascript`, and Linux uses `notify-send` when available. Notification failure never blocks capture or upload.
