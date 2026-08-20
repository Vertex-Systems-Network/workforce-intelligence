# macOS deployment

Requirements: macOS 12+, Node.js 20+.

```bash
./install.command https://time.example.com WI-XXXX-XXXX-XXXX
```

The installer creates a per-user LaunchAgent. macOS Accessibility permission is needed for reliable foreground-app metadata; Screen Recording permission is needed only when screenshot tracking is enabled.

For production distribution, sign/notarize your final package with your Apple Developer identity in CI before broad deployment.
