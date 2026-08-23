import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import test from 'node:test'

/** Read one repository file for M13 dependency-free lifecycle contracts. */
const read=file=>fs.readFileSync(file,'utf8')

test('M13 native agent exposes verified managed self-update without arbitrary update URLs',()=>{
  const agent=read('desktop-agent/native-agent.mjs')
  execFileSync(process.execPath,['--check','desktop-agent/native-agent.mjs'],{stdio:'pipe'})
  for(const token of ["const VERSION = '1.2.2'","'self_update'","'/api/v1/agent/release'","'/api/v1/agent/release/download'",'createHash','Downloaded update failed SHA-256 verification.','native-agent.mjs.previous',"['--check',candidate]"])assert.ok(agent.includes(token),token)
  for(const token of ['trustedRequestUrl','redirect:\'error\'','mkdtempSync',"openSync(archivePath,'wx',0o600)","spawnSync('curl'","'--header','@-'",'Agent request origin is not trusted.'])assert.ok(agent.includes(token),token)
  assert.ok(!agent.includes('writeFileSync(archivePath'))
  assert.ok(!agent.includes('response.arrayBuffer()'))
  assert.ok(!agent.includes("'--location'"))
  assert.ok(!agent.includes('Install the latest release package from Downloads or managed deployment.'))
  assert.ok(!agent.includes('command.payload.url'))
  assert.ok(!agent.includes('fetch(`${config.server_url}${managed.download_path}`'))
})

test('M13 agent release endpoint is device-authenticated platform-scoped and fail-closed on checksum mismatch',()=>{
  const controller=read('app/Http/Controllers/Api/V1/AgentReleaseController.php')
  const bootstrap=read('bootstrap/app.php')
  const routes=read('routes/agent-release.php')
  for(const token of ["'windows' => 'agent-windows-x64'","'macos' => 'agent-macos'","'linux' => 'agent-linux'",'hash_file','hash_equals','failed integrity verification'])assert.ok(controller.includes(token),token)
  for(const token of ["'agent.auth'","'workspace.module:devices'","prefix('api/v1/agent/release')",'routes/agent-release.php'])assert.ok(bootstrap.includes(token),token)
  assert.match(routes,/Route::get\('\/download'/)
})

test('M13 Windows Scheduled Task preserves enrolled state and restarts after managed replacement',()=>{
  const installer=read('desktop-agent/installers/windows/install.ps1')
  for(const token of ["$stateDir = Join-Path $InstallDir 'state'",'$env:WORKINTEL_AGENT_HOME = $stateDir',"$runnerPath = Join-Path $InstallDir 'run-agent.ps1'",'while (`$true)','Start-Sleep -Seconds 3','-File `"$runnerPath`"','Get-Command curl.exe'])assert.ok(installer.includes(token),token)
})

test('M13 managed-update deployment packages require curl across supported platforms',()=>{
  for(const file of ['desktop-agent/installers/windows/install.ps1','desktop-agent/installers/macos/install.command','desktop-agent/installers/linux/install.sh'])assert.ok(read(file).includes('curl'),file)
})

test('M13 legacy agents are never presented as remotely self-updatable',()=>{
  const devices=read('resources/js/pages/Devices.tsx')
  for(const token of ["row.capabilities.includes('self_update')",'Manual upgrade','Manual update required'])assert.ok(devices.includes(token),token)
})

test('M13 release version stays aligned with native source and runtime defaults',()=>{
  const agent=read('desktop-agent/native-agent.mjs')
  const build=read('tools/build-releases.py')
  const config=read('config/workintel.php')
  const env=read('.env.example')
  assert.match(agent,/const VERSION = '1\.2\.2'/)
  assert.match(build,/agent_match\s*=\s*re\.search/)
  assert.match(build,/agent_version\s*=\s*agent_match\.group\(1\)/)
  assert.ok(config.includes("WORKINTEL_AGENT_LATEST_VERSION', '1.2.2'"))
  assert.ok(env.includes('WORKINTEL_AGENT_LATEST_VERSION=1.2.2'))
})
