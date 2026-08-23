# Linux deployment

Requirements: Node.js 20+, curl, and systemd user services. `xdotool` is recommended for X11 foreground-app tracking. Screenshot support uses the first available tool from `gnome-screenshot`, `grim`, or ImageMagick `import`; privacy blur on Linux requires ImageMagick `convert`.

The normal WorkIntel Downloads package is bound to the current WorkIntel origin. After extracting it, enter only the one-time enrollment code:

```bash
./install.sh WI-XXXX-XXXX-XXXX
```

The installer reads `desktop-agent/workintel-server.txt`, enrolls automatically, and creates the systemd user service. The server-bound package contains no enrollment token or user secret.

The canonical published release remains server-agnostic and immutable. Operators intentionally using that raw canonical package can still use the legacy/manual fallback:

```bash
./install.sh https://time.example.com WI-XXXX-XXXX-XXXX
```

Wayland desktop policies may restrict active-window metadata. The agent fails closed for privacy-sensitive screenshot blur: if the workspace requires blur and the platform cannot apply it, the screenshot is skipped rather than uploaded unblurred.
