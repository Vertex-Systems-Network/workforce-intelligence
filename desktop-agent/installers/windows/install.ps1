param(
  [Parameter(Mandatory=$false)][string]$ServerUrl = '',
  [Parameter(Mandatory=$true)][string]$EnrollmentCode,
  [string]$InstallDir = "$env:LOCALAPPDATA\WorkIntelAgent"
)
$ErrorActionPreference = 'Stop'
function Fail($m){ Write-Error $m; exit 1 }
function Normalize-ServerUrl([string]$Value) {
  try { $uri = [System.Uri]::new($Value.Trim()) } catch { Fail 'ServerUrl must be a valid absolute http:// or https:// URL.' }
  if (-not $uri.IsAbsoluteUri -or ($uri.Scheme -ne 'http' -and $uri.Scheme -ne 'https')) { Fail 'ServerUrl must use http:// or https://.' }
  if (-not [string]::IsNullOrWhiteSpace($uri.UserInfo)) { Fail 'ServerUrl must not contain credentials.' }
  $path = $uri.AbsolutePath.TrimEnd('/')
  $knownEnrollmentPaths = @('/api/v1/agent/enroll', '/api/v1/browser/enroll')
  if ($path -and $path -ne '' -and $knownEnrollmentPaths -notcontains $path) {
    Fail 'ServerUrl must be the Workforce server base URL (for example https://team.example.com), not an API path.'
  }
  $baseUrl = $uri.GetLeftPart([System.UriPartial]::Authority)
  if ($knownEnrollmentPaths -contains $path) { Write-Host "Normalized enrollment endpoint to server URL: $baseUrl" }
  return $baseUrl
}
function Get-BoundServerUrl {
  $agentRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
  $configPath = Join-Path $agentRoot 'workintel-server.txt'
  if (-not (Test-Path -LiteralPath $configPath -PathType Leaf)) { return '' }
  return (Get-Content -LiteralPath $configPath -Raw).Trim()
}
if ([string]::IsNullOrWhiteSpace($ServerUrl)) {
  $ServerUrl = Get-BoundServerUrl
  if ([string]::IsNullOrWhiteSpace($ServerUrl)) {
    Fail 'This package is not bound to a WorkIntel server. Download it from WorkIntel Downloads or pass -ServerUrl explicitly.'
  }
  Write-Host "Using server configured by WorkIntel Downloads: $ServerUrl"
}
$ServerUrl = Normalize-ServerUrl $ServerUrl
$node = (Get-Command node -ErrorAction SilentlyContinue).Source
if (-not $node) { Fail 'Node.js 20+ is required. Install Node.js LTS first.' }
$nodeVersion = (& $node --version).TrimStart('v')
$version = $nodeVersion.Split('.')[0]
if ([int]$version -lt 20) { Fail "Node.js 20+ required. Found $(& $node --version)." }
$curl = (Get-Command curl.exe -ErrorAction SilentlyContinue).Source
if (-not $curl) { Fail 'curl is required for managed agent updates.' }

# Node normally uses its bundled CA set. Newer Node releases can additionally use
# the Windows trusted certificate store, which is important for enterprise/local
# HTTPS certificates that Chrome/Edge already trust. Detect the flag rather than
# assuming a particular Node minor release so the Node 20+ contract remains valid.
$nodeHelp = (& $node --help | Out-String)
$nodeTlsArgs = @()
if ($nodeHelp -match '--use-system-ca') {
  $nodeTlsArgs += '--use-system-ca'
  Write-Host 'Node system CA trust enabled for Windows enrollment/runtime.'
}

$stateDir = Join-Path $InstallDir 'state'
$agentPath = Join-Path $InstallDir 'native-agent.mjs'
$runnerPath = Join-Path $InstallDir 'run-agent.ps1'
New-Item -ItemType Directory -Force -Path $InstallDir, $stateDir | Out-Null
Copy-Item "$PSScriptRoot\..\..\native-agent.mjs" $agentPath -Force

$env:WORKINTEL_AGENT_HOME = $stateDir
Write-Host "Enrolling WorkIntel Agent with $ServerUrl ..."
& $node @nodeTlsArgs $agentPath enroll $ServerUrl $EnrollmentCode
if ($LASTEXITCODE -ne 0) { Fail 'Enrollment failed. The service was not installed. If this is a locally trusted HTTPS server, confirm its CA is installed in the Windows Trusted Root store.' }

$escapedNode = $node.Replace("'", "''")
$escapedAgent = $agentPath.Replace("'", "''")
$escapedState = $stateDir.Replace("'", "''")
$nodeTlsFlag = if ($nodeTlsArgs.Count -gt 0) { ' --use-system-ca' } else { '' }
$runner = @"
`$ErrorActionPreference = 'Continue'
`$env:WORKINTEL_AGENT_HOME = '$escapedState'
while (`$true) {
  & '$escapedNode'$nodeTlsFlag '$escapedAgent' run
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
Write-Host "ServerUrl: $ServerUrl"
Write-Host "Node: $nodeVersion"
Write-Host "InstallDir: $InstallDir"
Write-Host "StateDir: $stateDir"
Write-Host "Task: $taskName"
