param([string]$InstallDir = "$env:LOCALAPPDATA\WorkIntelAgent")
$ErrorActionPreference='SilentlyContinue'
schtasks /End /TN 'WorkIntel Agent' | Out-Null
schtasks /Delete /TN 'WorkIntel Agent' /F | Out-Null
Remove-Item $InstallDir -Recurse -Force
Write-Host 'WorkIntel Agent removed for the current user.'
