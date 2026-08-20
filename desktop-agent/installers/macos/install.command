#!/bin/zsh
set -euo pipefail
SERVER_URL="${1:-}"
ENROLLMENT_CODE="${2:-}"
if [[ -z "$SERVER_URL" || -z "$ENROLLMENT_CODE" ]]; then echo "Usage: ./install.command https://server.example WI-XXXX-XXXX-XXXX"; exit 1; fi
NODE_BIN="$(command -v node || true)"
if [[ -z "$NODE_BIN" ]]; then echo "Node.js 20+ is required."; exit 1; fi
MAJOR="$($NODE_BIN -p 'process.versions.node.split(".")[0]')"
if (( MAJOR < 20 )); then echo "Node.js 20+ required."; exit 1; fi
INSTALL_DIR="$HOME/Library/Application Support/WorkIntelAgent"
STATE_DIR="$INSTALL_DIR/state"
PLIST="$HOME/Library/LaunchAgents/com.workintel.agent.plist"
mkdir -p "$INSTALL_DIR" "$STATE_DIR" "$HOME/Library/LaunchAgents"
cp "$(cd "$(dirname "$0")/../.." && pwd)/native-agent.mjs" "$INSTALL_DIR/native-agent.mjs"
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
echo "WorkIntel Agent installed. macOS may request Accessibility/Screen Recording permissions when those features are enabled."
