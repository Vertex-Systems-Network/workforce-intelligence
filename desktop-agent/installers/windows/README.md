# Windows deployment

Requirements: Windows 10/11, PowerShell 5+, Node.js 20+.

From an elevated or normal user PowerShell (the agent runs in the signed-in user's desktop session so it can observe foreground applications):

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\install.ps1 -ServerUrl "https://time.example.com" -EnrollmentCode "WI-XXXX-XXXX-XXXX"
```

The installer enrolls first, then registers a per-user **Scheduled Task at logon**. Tokens are stored under `%LOCALAPPDATA%\WorkIntelAgent\state`.

Uninstall:

```powershell
.\uninstall.ps1
```

For enterprise deployment, deploy Node.js LTS plus this package through Intune/GPO/RMM and invoke `install.ps1` in the target user context. Code signing of PowerShell/package artifacts must be done with your organization certificate in the release pipeline.
