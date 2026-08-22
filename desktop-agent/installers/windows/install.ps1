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

$stateDir = Join-Path $InstallDir 'state'
$agentPath = Join-Path $InstallDir 'native-agent.mjs'
$runnerPath = Join-Path $InstallDir 'run-agent.ps1'
New-Item -ItemType Directory -Force -Path $InstallDir, $stateDir | Out-Null
Copy-Item "$PSScriptRoot\..\..\native-agent.mjs" $agentPath -Force

$env:WORKINTEL_AGENT_HOME = $stateDir
& $node $agentPath enroll $ServerUrl $EnrollmentCode
if ($LASTEXITCODE -ne 0) { Fail 'Enrollment failed. The service was not installed.' }

$escapedNode = $node.Replace("'", "''")
$escapedAgent = $agentPath.Replace("'", "''")
$escapedState = $stateDir.Replace("'", "''")
$runner = @"
`$ErrorActionPreference = 'Continue'
`$env:WORKINTEL_AGENT_HOME = '$escapedState'
while (`$true) {
  & '$escapedNode' '$escapedAgent' run
  Start-Sleep -Seconds 3
}
"@
Set-Content -LiteralPath $runnerPath -Value $runner -Encoding UTF8

$taskName = 'WorkIntel Agent'
$action = "powershell.exe -NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$runnerPath`""
schtasks /Create /TN $taskName /SC ONLOGON /TR $action /RL LIMITED /F | Out-Null
if ($LASTEXITCODE -ne 0) { Fail 'Could not register the WorkIntel Agent Scheduled Task.' }
schtasks /Run /TN $taskName | Out-Null
if ($LASTEXITCODE -ne 0) { Fail 'The WorkIntel Agent Scheduled Task was created but could not be started.' }

Write-Host "WorkIntel Agent installed for the current Windows user."
Write-Host "InstallDir: $InstallDir"
Write-Host "StateDir: $stateDir"
Write-Host "Task: $taskName"
