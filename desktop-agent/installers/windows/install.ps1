param(
  [Parameter(Mandatory=$true)][string]$ServerUrl,
  [Parameter(Mandatory=$true)][string]$EnrollmentCode,
  [string]$InstallDir = "$env:LOCALAPPDATA\WorkIntelAgent"
)
$ErrorActionPreference = 'Stop'
function Fail($m){ Write-Error $m; exit 1 }
$node = (Get-Command node -ErrorAction SilentlyContinue).Source
if (-not $node) { Fail 'Node.js 20+ is required. Install Node.js LTS first.' }
$version = (& $node --version).TrimStart('v').Split('.')[0]
if ([int]$version -lt 20) { Fail "Node.js 20+ required. Found $(& $node --version)." }
New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null
Copy-Item "$PSScriptRoot\..\..\native-agent.mjs" "$InstallDir\native-agent.mjs" -Force
$env:WORKINTEL_AGENT_HOME = "$InstallDir\state"
& $node "$InstallDir\native-agent.mjs" enroll $ServerUrl $EnrollmentCode
if ($LASTEXITCODE -ne 0) { Fail 'Enrollment failed. The service was not installed.' }
$taskName = 'WorkIntel Agent'
$action = "`"$node`" `"$InstallDir\native-agent.mjs`" run"
schtasks /Create /TN $taskName /SC ONLOGON /TR $action /RL LIMITED /F | Out-Null
schtasks /Run /TN $taskName | Out-Null
Write-Host "WorkIntel Agent installed for the current Windows user."
Write-Host "InstallDir: $InstallDir"
Write-Host "Task: $taskName"
