param(
    [string] $Repository = 'Vertex-Systems-Network/workforce-intelligence',
    [string] $Branch = 'main'
)

$ErrorActionPreference = 'Stop'
$apiVersion = '2026-03-10'

function Fail([string] $Message) {
    Write-Error $Message -ErrorAction Continue
    exit 1
}

if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
    Fail 'GitHub CLI (gh) is required. Install it, authenticate with an account/token that has repository Administration: write, then run this script again.'
}

& gh auth status 1>$null 2>$null
if ($LASTEXITCODE -ne 0) {
    Fail 'GitHub CLI is not authenticated. Run gh auth login with repository Administration: write access.'
}

Write-Host "Applying protected-branch policy to ${Repository}:$Branch"

$payload = [ordered]@{
    required_status_checks = [ordered]@{
        strict = $true
        contexts = @('test', 'windows-certification')
    }
    enforce_admins = $true
    required_pull_request_reviews = [ordered]@{
        dismissal_restrictions = [ordered]@{
            users = @()
            teams = @()
            apps = @()
        }
        dismiss_stale_reviews = $true
        require_code_owner_reviews = $false
        required_approving_review_count = 1
        require_last_push_approval = $false
        bypass_pull_request_allowances = [ordered]@{
            users = @()
            teams = @()
            apps = @()
        }
    }
    restrictions = $null
    required_linear_history = $false
    allow_force_pushes = $false
    allow_deletions = $false
    block_creations = $false
    required_conversation_resolution = $true
    lock_branch = $false
    allow_fork_syncing = $true
}

$tempFile = [System.IO.Path]::GetTempFileName()
try {
    $json = $payload | ConvertTo-Json -Depth 10
    [System.IO.File]::WriteAllText($tempFile, $json, (New-Object System.Text.UTF8Encoding($false)))

    & gh api --method PUT `
        -H 'Accept: application/vnd.github+json' `
        -H "X-GitHub-Api-Version: $apiVersion" `
        "repos/$Repository/branches/$Branch/protection" `
        --input $tempFile 1>$null

    if ($LASTEXITCODE -ne 0) {
        Fail 'GitHub rejected the branch-protection update. Confirm the authenticated account/token has repository Administration: write and the repository plan supports the requested controls.'
    }
}
finally {
    Remove-Item -Force -ErrorAction SilentlyContinue $tempFile
}

$branchState = (& gh api -H 'Accept: application/vnd.github+json' -H "X-GitHub-Api-Version: $apiVersion" "repos/$Repository/branches/$Branch") | ConvertFrom-Json
if (-not $branchState.protected) {
    Fail 'Branch API still reports protected=false after the update.'
}

$protection = (& gh api -H 'Accept: application/vnd.github+json' -H "X-GitHub-Api-Version: $apiVersion" "repos/$Repository/branches/$Branch/protection") | ConvertFrom-Json
$contexts = @($protection.required_status_checks.contexts)
$missing = @('test', 'windows-certification') | Where-Object { $_ -notin $contexts }

if ($missing.Count -gt 0) {
    Fail ('Required status checks missing after update: ' + ($missing -join ', '))
}
if (-not $protection.required_status_checks.strict) {
    Fail 'Required branches-up-to-date enforcement is not enabled.'
}
if (-not $protection.enforce_admins.enabled) {
    Fail 'Administrator enforcement is not enabled.'
}
if ($protection.required_pull_request_reviews.required_approving_review_count -lt 1) {
    Fail 'At least one approving review is not enforced.'
}
if (-not $protection.required_pull_request_reviews.dismiss_stale_reviews) {
    Fail 'Dismiss-stale-approvals is not enabled.'
}
if (-not $protection.required_conversation_resolution.enabled) {
    Fail 'Conversation resolution is not required.'
}
if ($protection.allow_force_pushes.enabled) {
    Fail 'Force pushes are still allowed.'
}
if ($protection.allow_deletions.enabled) {
    Fail 'Branch deletion is still allowed.'
}

Write-Host 'MAIN BRANCH PROTECTION CERTIFICATION PASSED'
Write-Host "Protected branch: ${Repository}:$Branch"
Write-Host 'Required checks: test, windows-certification'
