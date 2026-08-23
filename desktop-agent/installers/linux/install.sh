#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
CONFIG_PATH="$ROOT/workintel-server.txt"
SERVER_URL=""; CODE=""
if [[ $# -eq 1 ]]; then
  CODE="$1"
  [[ -f "$CONFIG_PATH" ]] || { echo "This package is not bound to a WorkIntel server. Download it from WorkIntel Downloads or pass the server URL explicitly."; exit 1; }
  SERVER_URL="$(tr -d '\r\n' < "$CONFIG_PATH")"
elif [[ $# -eq 2 ]]; then
  SERVER_URL="$1"; CODE="$2"
else
  echo "Usage: ./install.sh WI-XXXX-XXXX-XXXX"
  echo "Legacy/raw package fallback: ./install.sh https://server.example WI-XXXX-XXXX-XXXX"
  exit 1
fi
[[ -n "$SERVER_URL" && -n "$CODE" ]] || { echo "Server URL and enrollment code are required."; exit 1; }
NODE_BIN="$(command -v node || true)"; [[ -n "$NODE_BIN" ]] || { echo "Node.js 20+ required."; exit 1; }
MAJOR="$($NODE_BIN -p 'process.versions.node.split(".")[0]')"; (( MAJOR >= 20 )) || { echo "Node.js 20+ required."; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "curl is required for managed agent updates."; exit 1; }
INSTALL_DIR="$HOME/.local/share/workintel-agent"; STATE_DIR="$HOME/.local/state/workintel-agent"; SERVICE_DIR="$HOME/.config/systemd/user"
mkdir -p "$INSTALL_DIR" "$STATE_DIR" "$SERVICE_DIR"
cp "$ROOT/native-agent.mjs" "$INSTALL_DIR/native-agent.mjs"
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
echo "WorkIntel Agent installed for $SERVER_URL. xdotool improves active-window tracking; gnome-screenshot/grim/ImageMagick enable screenshots depending on the desktop session."
