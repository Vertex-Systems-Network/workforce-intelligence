#!/bin/zsh
set -e
PLIST="$HOME/Library/LaunchAgents/com.workintel.agent.plist"
launchctl bootout gui/$(id -u) "$PLIST" >/dev/null 2>&1 || true
rm -f "$PLIST"
rm -rf "$HOME/Library/Application Support/WorkIntelAgent"
echo "WorkIntel Agent removed."
