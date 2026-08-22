#!/usr/bin/env bash
set -euo pipefail
SERVER_URL="${1:-}"; CODE="${2:-}"
if [[ -z "$SERVER_URL" || -z "$CODE" ]]; then echo "Usage: ./install.sh https://server.example WI-XXXX-XXXX-XXXX"; exit 1; fi
NODE_BIN="$(command -v node || true)"; [[ -n "$NODE_BIN" ]] || { echo "Node.js 20+ required."; exit 1; }
MAJOR="$($NODE_BIN -p 'process.versions.node.split(".")[0]')"; (( MAJOR >= 20 )) || { echo "Node.js 20+ required."; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "curl is required for managed agent updates."; exit 1; }
INSTALL_DIR="$HOME/.local/share/workintel-agent"; STATE_DIR="$HOME/.local/state/workintel-agent"; SERVICE_DIR="$HOME/.config/systemd/user"
mkdir -p "$INSTALL_DIR" "$STATE_DIR" "$SERVICE_DIR"
cp "$(cd "$(dirname "$0")/../.." && pwd)/native-agent.mjs" "$INSTALL_DIR/native-agent.mjs"
WORKINTEL_AGENT_HOME="$STATE_DIR" "$NODE_BIN" "$INSTALL_DIR/native-agent.mjs" enroll "$SERVER_URL" "$CODE"
cat > "$SERVICE_DIR/workintel-agent.service" <<EOF
[Unit]
Description=WorkIntel Desktop Agent
After=network-online.target graphical-session.target

[Service]
Type=simple
Environment=WORKINTEL_AGENT_HOME=$STATE_DIR
ExecStart=$NODE_BIN $INSTALL_DIR/native-agent.mjs run
Restart=always
RestartSec=10

[Install]
WantedBy=default.target
EOF
systemctl --user daemon-reload
systemctl --user enable --now workintel-agent.service
echo "WorkIntel Agent installed. xdotool improves active-window tracking; gnome-screenshot/grim/ImageMagick enable screenshots depending on the desktop session."
