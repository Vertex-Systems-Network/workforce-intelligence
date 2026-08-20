#!/usr/bin/env bash
set -e
systemctl --user disable --now workintel-agent.service >/dev/null 2>&1 || true
rm -f "$HOME/.config/systemd/user/workintel-agent.service"
systemctl --user daemon-reload >/dev/null 2>&1 || true
rm -rf "$HOME/.local/share/workintel-agent" "$HOME/.local/state/workintel-agent"
echo "WorkIntel Agent removed."
