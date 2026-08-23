#!/bin/zsh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
CONFIG_PATH="$ROOT/workintel-server.txt"
SERVER_URL=""
ENROLLMENT_CODE=""
if [[ $# -eq 1 ]]; then
  ENROLLMENT_CODE="$1"
  [[ -f "$CONFIG_PATH" ]] || { echo "This package is not bound to a WorkIntel server. Download it from WorkIntel Downloads or pass the server URL explicitly."; exit 1; }
  SERVER_URL="$(tr -d '\r\n' < "$CONFIG_PATH")"
elif [[ $# -eq 2 ]]; then
  SERVER_URL="$1"
  ENROLLMENT_CODE="$2"
else
  echo "Usage: ./install.command WI-XXXX-XXXX-XXXX"
  echo "Legacy/raw package fallback: ./install.command https://server.example WI-XXXX-XXXX-XXXX"
  exit 1
fi
[[ -n "$SERVER_URL" && -n "$ENROLLMENT_CODE" ]] || { echo "Server URL and enrollment code are required."; exit 1; }
NODE_BIN="$(command -v node || true)"
if [[ -z "$NODE_BIN" ]]; then echo "Node.js 20+ is required."; exit 1; fi
MAJOR="$($NODE_BIN -p 'process.versions.node.split(".")[0]')"
if (( MAJOR < 20 )); then echo "Node.js 20+ required."; exit 1; fi
command -v curl >/dev/null 2>&1 || { echo "curl is required for managed agent updates."; exit 1; }
INSTALL_DIR="$HOME/Library/Application Support/WorkIntelAgent"
STATE_DIR="$INSTALL_DIR/state"
PLIST="$HOME/Library/LaunchAgents/com.workintel.agent.plist"
mkdir -p "$INSTALL_DIR" "$STATE_DIR" "$HOME/Library/LaunchAgents"
cp "$ROOT/native-agent.mjs" "$INSTALL_DIR/native-agent.mjs"
WORKINTEL_AGENT_HOME="$STATE_DIR" "$NODE_BIN" "$INSTALL_DIR/native-agent.mjs" enroll "$SERVER_URL" "$ENROLLMENT_CODE"
cat > "$PLIST" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
<key>Label</key><string>com.workintel.agent</string>
<key>ProgramArguments</key><array><string>$NODE_BIN</string><string>$INSTALL_DIR/native-agent.mjs</string><string>run</string></array>
<key>EnvironmentVariables</key><dict><key>WORKINTEL_AGENT_HOME</key><string>$STATE_DIR</string></dict>
<key>RunAtLoad</key><true/><key>KeepAlive</key><true/>
<key>StandardOutPath</key><string>$INSTALL_DIR/agent.log</string><key>StandardErrorPath</key><string>$INSTALL_DIR/agent-error.log</string>
</dict></plist>
EOF
launchctl bootout gui/$(id -u) "$PLIST" >/dev/null 2>&1 || true
launchctl bootstrap gui/$(id -u) "$PLIST"
echo "WorkIntel Agent installed for $SERVER_URL. macOS may request Accessibility/Screen Recording permissions when those features are enabled."
