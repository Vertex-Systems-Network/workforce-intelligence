param()

$ErrorActionPreference = 'Stop'
$root = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $root

$logDir = Join-Path $root 'storage/logs/certification'
New-Item -ItemType Directory -Force -Path $logDir | Out-Null
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $logDir "laragon-release-$stamp.log"
$successMarker = 'WORKINTEL LARAGON RELEASE CERTIFICATION PASSED'

Start-Transcript -Path $logFile -Force | Out-Null
$exitCode = 1

try {
    function Invoke-Gate {
        param(
            [Parameter(Mandatory = $true)][string] $Name,
            [Parameter(Mandatory = $true)][scriptblock] $Command
        )

        Write-Host ""
        Write-Host "[RUN] $Name"
        & $Command
        if ($LASTEXITCODE -ne 0) {
            throw "$Name failed with exit code $LASTEXITCODE."
        }
        Write-Host '[PASS]'
    }

    Write-Host '============================================================'
    Write-Host 'WorkIntel - LARAGON TARGET RELEASE CERTIFICATION'
    Write-Host '============================================================'
    Write-Host 'Mode: non-destructive existing-install verification'

    Invoke-Gate 'Windows + MySQL target preflight' { php tools\laragon-release-preflight.php }
    Invoke-Gate 'Actual Chrome Edge Firefox inventory' { node tools\e2e-browser-doctor.mjs --require-all }

    $env:WORKINTEL_REQUIRE_CROSS_BROWSER = '1'
    Invoke-Gate 'Full WorkIntel release verification' { cmd /d /c verify-release.cmd }

    $exitCode = 0
}
catch {
    Write-Error $_ -ErrorAction Continue
    $exitCode = 1
}
finally {
    Stop-Transcript | Out-Null

    if ($exitCode -eq 0) {
        try {
            Add-Content -Path $logFile -Value $successMarker -Encoding UTF8
            $tail = Get-Content -Path $logFile -Tail 1
            if ($tail -ne $successMarker) {
                Write-Error 'Evidence log tail does not match the required Laragon release success marker.' -ErrorAction Continue
                $exitCode = 1
            }
        }
        catch {
            Write-Error ('Unable to seal Laragon certification evidence log: ' + $_.Exception.Message) -ErrorAction Continue
            $exitCode = 1
        }
    }

    if ($exitCode -eq 0) {
        Write-Host ""
        Write-Host '============================================================'
        Write-Host $successMarker
        Write-Host '============================================================'
    }

    Write-Host "Evidence log: $logFile"
}

exit $exitCode
