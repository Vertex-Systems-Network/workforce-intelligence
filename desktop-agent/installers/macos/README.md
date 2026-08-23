# macOS deployment

Requirements: macOS 12+, Node.js 20+, and curl.

The normal WorkIntel Downloads package is bound to the current WorkIntel origin. After extracting it, enter only the one-time enrollment code:

```bash
./install.command WI-XXXX-XXXX-XXXX
```

The installer reads `desktop-agent/workintel-server.txt`, enrolls automatically, and creates a per-user LaunchAgent. The server-bound package contains no enrollment token or user secret.

The canonical published release remains server-agnostic and immutable. Operators intentionally using that raw canonical package can still use the legacy/manual fallback:

```bash
./install.command https://time.example.com WI-XXXX-XXXX-XXXX
```

macOS Accessibility permission is needed for reliable foreground-app metadata; Screen Recording permission is needed only when screenshot tracking is enabled.

For production distribution, sign/notarize your final package with your Apple Developer identity in CI before broad deployment.
