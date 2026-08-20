param(
    [ValidateSet('Release','Clean')][string]$Mode = 'Release',
    [switch]$ConfirmReset
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root
$ReportDir = Join-Path $Root 'storage\logs\runtime-closure'
New-Item -ItemType Directory -Force -Path $ReportDir | Out-Null
$Stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$Report = Join-Path $ReportDir ("block-j-{0}-{1}.log" -f $Mode.ToLowerInvariant(), $Stamp)

function Protect-LogLine([string]$Text) {
    $safe = $Text
    $safe = $safe -replace '(?i)(APP_KEY|DB_PASSWORD|MAIL_PASSWORD|AWS_SECRET_ACCESS_KEY|STRIPE_SECRET|PAYPAL_CLIENT_SECRET|CLIENT_SECRET)(\s*[:=]\s*)([^\s,;]+)', '$1$2[REDACTED]'
    $safe = $safe -replace '(?i)Bearer\s+[A-Za-z0-9._~+/-]+=*', 'Bearer [REDACTED]'
    $safe = $safe -replace '(?i)sk_(live|test)_[A-Za-z0-9]+', 'sk_$1_[REDACTED]'
    return $safe
}

function Write-ReportLine([string]$Text) {
    (Protect-LogLine $Text) | Tee-Object -FilePath $Report -Append
}

function Write-CommandOutput {
    process {
        $line = Protect-LogLine ([string]$_)
        $line | Tee-Object -FilePath $Report -Append
    }
}

Write-ReportLine '============================================================'
Write-ReportLine ("WorkIntel Block J Runtime Closure - {0}" -f $Mode)
Write-ReportLine ("Started: {0}" -f (Get-Date -Format o))
Write-ReportLine ("Root: {0}" -f $Root)
Write-ReportLine '============================================================'

$PreflightArgs = @('tools\runtime-closure-preflight.php')
& php @PreflightArgs 2>&1 | Write-CommandOutput
if ($LASTEXITCODE -ne 0) {
    Write-ReportLine '[FAIL] Runtime closure preflight failed. Fix the reported PHP/Node/database requirements first.'
    Write-Host "Report: $Report"
    exit $LASTEXITCODE
}

$Script = if ($Mode -eq 'Clean') { 'verify-clean-install.cmd' } else { 'verify-release.cmd' }
if ($Mode -eq 'Clean' -and $ConfirmReset) { $env:WORKINTEL_RESET_CONFIRM = 'RESET' }

Write-ReportLine ("[RUN] {0}" -f $Script)
& cmd.exe /d /c $Script 2>&1 | Write-CommandOutput
$ExitCode = $LASTEXITCODE
Write-ReportLine ("Finished: {0}" -f (Get-Date -Format o))
Write-ReportLine ("Exit code: {0}" -f $ExitCode)

$Lines = Get-Content $Report
$LastGate = $Lines | Where-Object { $_ -match '^\[RUN\]' } | Select-Object -Last 1
$Failure = $Lines | Where-Object { $_ -match '^\[FAIL\]|ERROR:|REFUSED:' } | Select-Object -Last 1
if ($LastGate) { Write-ReportLine ("Last gate: {0}" -f $LastGate) }
if ($Failure) { Write-ReportLine ("Failure summary: {0}" -f $Failure) }

Write-Host "`nBlock J report: $Report"
exit $ExitCode
