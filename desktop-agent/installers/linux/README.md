# Linux deployment

Requirements: Node.js 20+, curl, and systemd user services. `xdotool` is recommended for X11 foreground-app tracking. Screenshot support uses the first available tool from `gnome-screenshot`, `grim`, or ImageMagick `import`; privacy blur on Linux requires ImageMagick `convert`.

```bash
./install.sh https://time.example.com WI-XXXX-XXXX-XXXX
```

Wayland desktop policies may restrict active-window metadata. The agent fails closed for privacy-sensitive screenshot blur: if the workspace requires blur and the platform cannot apply it, the screenshot is skipped rather than uploaded unblurred.
