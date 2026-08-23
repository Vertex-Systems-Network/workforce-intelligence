# Windows deployment

Requirements: Windows 10/11, PowerShell 5+, Node.js 20+, and curl.

## WorkIntel Downloads package

The normal WorkIntel Downloads flow produces a temporary **server-bound** ZIP. It contains `desktop-agent/workintel-server.txt` with the current WorkIntel origin and no enrollment secret. After extracting the ZIP, the user supplies only the one-time code:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\install.ps1 -EnrollmentCode "WI-XXXX-XXXX-XXXX"
```

The installer reads the bound server, enrolls against the correct `/api/v1/agent/enroll` API, then registers a per-user **Scheduled Task at logon**. Tokens are stored under `%LOCALAPPDATA%\WorkIntelAgent\state`.

On supported Node releases the installer enables `--use-system-ca` so locally/enterprise-trusted HTTPS certificates in the Windows Trusted Root store can be honored without disabling TLS verification.

## Raw canonical package fallback

The canonical published release remains server-agnostic and immutable. Operators who intentionally use that raw package may still provide the server origin explicitly:

```powershell
.\install.ps1 -ServerUrl "https://time.example.com" -EnrollmentCode "WI-XXXX-XXXX-XXXX"
```

`-ServerUrl` is a base origin, not an API route. Enrollment endpoints are normalized to the origin for backward compatibility.

Uninstall:

```powershell
.\uninstall.ps1
```

For enterprise deployment, deploy Node.js LTS plus the server-bound package through Intune/GPO/RMM and invoke `install.ps1` in the target user context. Code signing of PowerShell/package artifacts must be done with your organization certificate in the release pipeline.
