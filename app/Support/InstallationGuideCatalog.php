<?php
namespace App\Support;
/** Provides installation guide catalog behavior within the WorkIntel application. */ final class InstallationGuideCatalog
{
    public const GUIDES=[
      'windows-agent'=>['title'=>'Windows Desktop Agent','platform'=>'windows','release_slug'=>'agent-windows-x64','audience'=>'Employee / IT','summary'=>'Install the desktop agent for the signed-in Windows user and register it as a Scheduled Task.','requirements'=>['Windows 10/11','Node.js 20+','PowerShell 5+'],'steps'=>[
        ['id'=>'download','title'=>'Download the Windows agent','text'=>'Download the server-bound Windows package from WorkIntel. The package already contains this WorkIntel server address; the canonical published release remains immutable.'],
        ['id'=>'verify','title'=>'Canonical release verified before binding','text'=>'WorkIntel verifies the canonical published ZIP against its release SHA-256 before it creates your server-bound deployment copy. The dashboard shows the canonical base SHA for provenance.'],
        ['id'=>'extract','title'=>'Extract the package','text'=>'Extract the ZIP to a local folder. Do not run the installer directly from inside the ZIP.'],
        ['id'=>'enroll','title'=>'Create a one-time enrollment code','text'=>'Use Create Enrollment Code below. The server is already configured in the package; the enrollment code is the only setup value you need to enter.'],
        ['id'=>'install','title'=>'Run the installer','text'=>'Open PowerShell in the extracted package folder and run the command below in the current user context.','command'=>'Set-ExecutionPolicy -Scope Process Bypass; .\\desktop-agent\\installers\\windows\\install.ps1 -EnrollmentCode "{{enrollment_code}}"'],
        ['id'=>'startup','title'=>'Confirm startup registration','text'=>'The installer creates a per-user Scheduled Task named WorkIntel Agent.','command'=>'schtasks /Query /TN "WorkIntel Agent" /V /FO LIST'],
        ['id'=>'status','title'=>'Check agent status','text'=>'The status command must show enrolled=true and the WorkIntel server bound into the downloaded package.','command'=>'node "$env:LOCALAPPDATA\\WorkIntelAgent\\native-agent.mjs" status'],
        ['id'=>'test','title'=>'Test activity and screenshot flow','text'=>'Use Test Installation below. Open another app for activity tracking and wait for the configured screenshot interval, then refresh the test.'],
      ]],
      'macos-agent'=>['title'=>'macOS Desktop Agent','platform'=>'macos','release_slug'=>'agent-macos','audience'=>'Employee / IT','summary'=>'Install the per-user LaunchAgent and grant the macOS permissions required for foreground activity and screenshot capture.','requirements'=>['macOS 12+','Node.js 20+','Terminal'],'steps'=>[
        ['id'=>'download','title'=>'Download the macOS agent','text'=>'Download the server-bound macOS package from WorkIntel. The server address is embedded as deployment configuration.'],
        ['id'=>'verify','title'=>'Canonical release verified before binding','text'=>'WorkIntel verifies the canonical published ZIP against its release SHA-256 before it creates your server-bound deployment copy. The dashboard shows the canonical base SHA for provenance.'],
        ['id'=>'extract','title'=>'Extract the package','text'=>'Extract the ZIP and open Terminal in the extracted folder.'],
        ['id'=>'permissions','title'=>'Allow required macOS permissions','text'=>'In System Settings → Privacy & Security, allow Screen Recording for the terminal/runtime used by the WorkIntel agent. Accessibility may also be required for foreground window metadata depending on macOS policy.'],
        ['id'=>'enroll','title'=>'Create a one-time enrollment code','text'=>'Generate your own code below. The package already knows which WorkIntel server to call.'],
        ['id'=>'install','title'=>'Run the installer','text'=>'Install in the signed-in user session so the LaunchAgent can observe that desktop session.','command'=>'chmod +x ./desktop-agent/installers/macos/install.command && ./desktop-agent/installers/macos/install.command "{{enrollment_code}}"'],
        ['id'=>'startup','title'=>'Confirm LaunchAgent','text'=>'Confirm the WorkIntel LaunchAgent is loaded for the current user.','command'=>'launchctl list | grep -i workintel'],
        ['id'=>'test','title'=>'Test the installation','text'=>'Use Test Installation and confirm heartbeat/activity/screenshot timestamps begin appearing.'],
      ]],
      'linux-agent'=>['title'=>'Linux Desktop Agent','platform'=>'linux','release_slug'=>'agent-linux','audience'=>'Employee / IT','summary'=>'Install the WorkIntel desktop agent as a systemd user service in the graphical user session.','requirements'=>['Modern Linux desktop','Node.js 20+','systemd user session'],'steps'=>[
        ['id'=>'download','title'=>'Download the Linux agent','text'=>'Download the server-bound Linux package from WorkIntel. The server address is embedded as deployment configuration.'],
        ['id'=>'verify','title'=>'Canonical release verified before binding','text'=>'WorkIntel verifies the canonical published ZIP against its release SHA-256 before it creates your server-bound deployment copy. The dashboard shows the canonical base SHA for provenance.'],
        ['id'=>'dependencies','title'=>'Check desktop capture tools','text'=>'Depending on the desktop/session, install xdotool/xprintidle and a supported screenshot tool such as gnome-screenshot, grim or ImageMagick.'],
        ['id'=>'enroll','title'=>'Create a one-time enrollment code','text'=>'Generate your own code below. The package already knows which WorkIntel server to call.'],
        ['id'=>'install','title'=>'Run the installer','text'=>'Run the installation in the target graphical user account.','command'=>'chmod +x ./desktop-agent/installers/linux/install.sh && ./desktop-agent/installers/linux/install.sh "{{enrollment_code}}"'],
        ['id'=>'startup','title'=>'Confirm the user service','text'=>'The agent should be enabled and running as a systemd user service.','command'=>'systemctl --user status workintel-agent --no-pager'],
        ['id'=>'test','title'=>'Test the installation','text'=>'Use Test Installation and confirm the latest heartbeat. Then verify activity and screenshot data.'],
      ]],
      'chrome-edge-extension'=>['title'=>'Chrome / Edge Browser Extension','platform'=>'browser','release_slug'=>'browser-chrome-edge','audience'=>'Employee / IT','summary'=>'Load the server-bound WorkIntel Manifest V3 browser tracker and enroll it for the current user.','requirements'=>['Google Chrome or Microsoft Edge','Extension developer mode for unpacked deployment unless centrally packaged'],'steps'=>[
        ['id'=>'download','title'=>'Download the server-bound extension','text'=>'Download the Chrome / Edge package from WorkIntel. Its runtime server address is already configured by the dashboard.'],
        ['id'=>'extract','title'=>'Extract the extension','text'=>'Extract the archive to a stable local folder.'],
        ['id'=>'load','title'=>'Load the extension','text'=>'Open chrome://extensions or edge://extensions, enable Developer mode, choose Load unpacked and select the extracted folder. For production fleets, use enterprise extension deployment instead.'],
        ['id'=>'enroll','title'=>'Create an enrollment code','text'=>'Generate the code below and enter only that code in the WorkIntel extension popup. The extension reads its server from the downloaded package.'],
        ['id'=>'test','title'=>'Test browser tracking','text'=>'Browse to a normal website, wait for sync, then use Test Installation to confirm the browser connection.'],
      ]],
      'firefox-extension'=>['title'=>'Firefox Browser Extension','platform'=>'browser','release_slug'=>'browser-firefox','audience'=>'Employee / IT','summary'=>'Install the server-bound Firefox browser tracker package.','requirements'=>['Firefox 128+'],'steps'=>[
        ['id'=>'download','title'=>'Download the server-bound Firefox extension','text'=>'Download the package from WorkIntel. Its runtime server address is already configured by the dashboard.'],
        ['id'=>'extract','title'=>'Extract the extension','text'=>'Extract the package to a local folder.'],
        ['id'=>'load','title'=>'Load for testing or deploy centrally','text'=>'For temporary testing use about:debugging → This Firefox → Load Temporary Add-on and choose manifest.json. Use organization-managed extension deployment for production persistence.'],
        ['id'=>'enroll','title'=>'Create an enrollment code','text'=>'Generate the code below and enter only that code in the extension popup.'],
        ['id'=>'test','title'=>'Test browser tracking','text'=>'Visit a normal site and confirm the browser connection in Test Installation.'],
      ]],
      'admin-production'=>['title'=>'Admin Production Deployment','platform'=>'admin','release_slug'=>null,'audience'=>'Owner / Admin / IT','summary'=>'Production checklist for controlled agent rollout across an organization.','requirements'=>['Signed release artifacts recommended','Managed deployment tooling recommended'],'steps'=>[
        ['id'=>'build','title'=>'Build release artifacts','text'=>'Run the release build tool on the production source and publish the generated release manifest with its ZIP packages. WorkIntel Downloads creates server-bound copies at request time without rewriting those canonical artifacts.','command'=>'python tools/build-releases.py'],
        ['id'=>'sign','title'=>'Sign/notarize artifacts','text'=>'Code-sign PowerShell/packages and notarize/sign macOS artifacts in your organization release pipeline.'],
        ['id'=>'pilot','title'=>'Pilot with a small group','text'=>'Validate installation, permissions, heartbeat, activity, screenshot policy and uninstall/repair before broad rollout.'],
        ['id'=>'deploy','title'=>'Deploy in user context','text'=>'Use Intune/GPO/RMM for Windows, MDM for macOS, or your Linux configuration-management system. The desktop tracker must run in the signed-in graphical user session.'],
        ['id'=>'monitor','title'=>'Monitor Devices & Agents','text'=>'Review agent versions, last heartbeat, offline devices and update-required status after rollout.'],
      ]],
      'repair-uninstall'=>['title'=>'Repair & Uninstall','platform'=>'support','release_slug'=>null,'audience'=>'Employee / IT','summary'=>'Repair a broken installation or completely remove the desktop agent.','requirements'=>[],'steps'=>[
        ['id'=>'diagnose','title'=>'Check current status','text'=>'Use Test Installation and the native-agent status command before reinstalling.'],
        ['id'=>'repair','title'=>'Repair by reinstalling','text'=>'Re-download the current server-bound package, generate a fresh enrollment code if the device was revoked, and rerun the platform installer using only the new code.'],
        ['id'=>'windows','title'=>'Windows uninstall','text'=>'Run the included uninstall script in PowerShell.','command'=>'.\\desktop-agent\\installers\\windows\\uninstall.ps1'],
        ['id'=>'mac','title'=>'macOS uninstall','text'=>'Run the included uninstall command from Terminal.','command'=>'chmod +x ./desktop-agent/installers/macos/uninstall.command && ./desktop-agent/installers/macos/uninstall.command'],
        ['id'=>'linux','title'=>'Linux uninstall','text'=>'Run the included uninstall script.','command'=>'chmod +x ./desktop-agent/installers/linux/uninstall.sh && ./desktop-agent/installers/linux/uninstall.sh'],
        ['id'=>'revoke','title'=>'Revoke old device access if needed','text'=>'An Admin can revoke the old device from Devices & Agents. Re-enrollment is required after revocation.'],
      ]],
    ];
    /** Handles the all operation for the current WorkIntel workflow. */ public static function all():array{return self::GUIDES;}
    /** Returns get data required by the current WorkIntel workflow. */ public static function get(string $key):?array{return self::GUIDES[$key]??null;}
    /** Handles the keys operation for the current WorkIntel workflow. */ public static function keys():array{return array_keys(self::GUIDES);}
}
