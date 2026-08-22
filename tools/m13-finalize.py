#!/usr/bin/env python3
"""One-shot M13 security finalizer. Removed by the finalize workflow after use."""
from pathlib import Path


def replace_required(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text(encoding='utf-8')
    if old not in text:
        raise RuntimeError(f'Expected source fragment not found in {path}: {old[:80]!r}')
    file.write_text(text.replace(old, new, 1), encoding='utf-8')


agent_path = Path('desktop-agent/native-agent.mjs')
agent = agent_path.read_text(encoding='utf-8')
agent = agent.replace("const VERSION = '1.2.0'", "const VERSION = '1.2.1'", 1)
marker = "/** Downloads, verifies, stages, and atomically replaces the managed native-agent source. */ async function installManagedUpdate(config) {"
helper = r'''/**
 * Downloads one managed archive through curl into an already-open secure descriptor.
 * The device token travels over stdin so it is not exposed in the process argument list.
 */
function downloadManagedArchive(config, archivePath, headersPath) {
  if(!commandExists('curl'))throw new Error('curl is required for managed agent updates.')
  const endpoint=trustedRequestUrl(config,'/api/v1/agent/release/download')
  const token=String(config?.access_token||'')
  if(!token||/[\r\n\0]/u.test(token))throw new Error('Managed update token is invalid.')
  const archiveFd=openSync(archivePath,'wx',0o600)
  try{
    const result=spawnSync('curl',[
      '--fail','--silent','--show-error','--no-progress-meter','--connect-timeout','10','--max-time','120',
      '--proto',endpoint.protocol==='https:'?'=https':'=http','--dump-header',headersPath,
      '--header','Accept: application/zip','--header','@-',endpoint.toString()
    ],{input:`Authorization: Bearer ${token}\r\n`,stdio:['pipe',archiveFd,'pipe'],encoding:'utf8',windowsHide:true})
    if(result.error)throw result.error
    if(result.status!==0)throw new Error(`Update download failed: ${String(result.stderr||'curl failed').trim()}`)
  }finally{closeSync(archiveFd)}
  const headers=readFileSync(headersPath,'utf8')
  const statuses=[...headers.matchAll(/^HTTP\/\S+\s+(\d{3})/gmi)].map(match=>Number(match[1]))
  const status=statuses.at(-1)||0
  if(status<200||status>=300)throw new Error(`Update download failed with HTTP ${status||'unknown'}`)
  return {
    sha256:headers.match(/^x-release-sha256:\s*([^\r\n]+)/mi)?.[1]?.trim()||null,
    version:headers.match(/^x-workintel-version:\s*([^\r\n]+)/mi)?.[1]?.trim()||null,
  }
}

'''
if 'function downloadManagedArchive(' not in agent:
    if marker not in agent:
        raise RuntimeError('installManagedUpdate marker missing')
    agent = agent.replace(marker, helper + marker, 1)
old_download = r'''  const response=await authenticatedRequest(config,'/api/v1/agent/release/download',{headers:{Accept:'application/zip'}})
  if(!response.ok){const payload=await response.json().catch(()=>null);throw new Error(payload?.message||`Update download failed with HTTP ${response.status}`)}
  const headerHash=response.headers.get('x-release-sha256')
  const headerVersion=response.headers.get('x-workintel-version')
  if(headerHash&&headerHash.toLowerCase()!==managed.sha256.toLowerCase())throw new Error('Update response checksum does not match release metadata.')
  if(headerVersion&&headerVersion!==managed.version)throw new Error('Update response version does not match release metadata.')
  const archiveBytes=Buffer.from(await response.arrayBuffer())
  const actualHash=createHash('sha256').update(archiveBytes).digest('hex')
  if(actualHash.toLowerCase()!==managed.sha256.toLowerCase())throw new Error('Downloaded update failed SHA-256 verification.')

  const updateDir=mkdtempSync(resolve(tmpdir(),'workintel-update-'))
  const archivePath=resolve(updateDir,'release.zip')
  const extractDir=resolve(updateDir,'extract')
  const currentPath=resolve(root,'native-agent.mjs')
  const stagedPath=resolve(root,`native-agent.mjs.next-${randomUUID()}`)
  const backupPath=resolve(root,'native-agent.mjs.previous')
  mkdirSync(extractDir,{mode:0o700})
  try{
    writeFileSync(archivePath,archiveBytes,{flag:'wx',mode:0o600})
'''
new_download = r'''  const updateDir=mkdtempSync(resolve(tmpdir(),'workintel-update-'))
  const archivePath=resolve(updateDir,'release.zip')
  const headersPath=resolve(updateDir,'release.headers')
  const extractDir=resolve(updateDir,'extract')
  const currentPath=resolve(root,'native-agent.mjs')
  const stagedPath=resolve(root,`native-agent.mjs.next-${randomUUID()}`)
  const backupPath=resolve(root,'native-agent.mjs.previous')
  mkdirSync(extractDir,{mode:0o700})
  try{
    const responseMeta=downloadManagedArchive(config,archivePath,headersPath)
    if(responseMeta.sha256&&responseMeta.sha256.toLowerCase()!==managed.sha256.toLowerCase())throw new Error('Update response checksum does not match release metadata.')
    if(responseMeta.version&&responseMeta.version!==managed.version)throw new Error('Update response version does not match release metadata.')
    const actualHash=createHash('sha256').update(readFileSync(archivePath)).digest('hex')
    if(actualHash.toLowerCase()!==managed.sha256.toLowerCase())throw new Error('Downloaded update failed SHA-256 verification.')
'''
if old_download in agent:
    agent = agent.replace(old_download, new_download, 1)
if 'writeFileSync(archivePath,archiveBytes' in agent or 'response.arrayBuffer()' in agent:
    raise RuntimeError('Network-to-file sink remains after patch')
agent_path.write_text(agent, encoding='utf-8')

# Runtime and regression version alignment.
replace_required('config/workintel.php', "WORKINTEL_AGENT_LATEST_VERSION', '1.2.0'", "WORKINTEL_AGENT_LATEST_VERSION', '1.2.1'")
replace_required('.env.example', 'WORKINTEL_AGENT_LATEST_VERSION=1.2.0', 'WORKINTEL_AGENT_LATEST_VERSION=1.2.1')

test_path = Path('tests/frontend/agent-lifecycle-m13.test.mjs')
test = test_path.read_text(encoding='utf-8')
test = test.replace("const VERSION = '1.2.0'", "const VERSION = '1.2.1'")
test = test.replace("assert.match(agent,/const VERSION = '1\\.2\\.0'/)", "assert.match(agent,/const VERSION = '1\\.2\\.1'/)")
test = test.replace("WORKINTEL_AGENT_LATEST_VERSION', '1.2.0'", "WORKINTEL_AGENT_LATEST_VERSION', '1.2.1'")
test = test.replace('WORKINTEL_AGENT_LATEST_VERSION=1.2.0', 'WORKINTEL_AGENT_LATEST_VERSION=1.2.1')
old_tokens = "  for(const token of ['trustedRequestUrl','redirect:\\'error\\'','mkdtempSync',\"flag:'wx'\",\"mode:0o600\",'Agent request origin is not trusted.'])assert.ok(agent.includes(token),token)\n"
new_tokens = "  for(const token of ['trustedRequestUrl','redirect:\\'error\\'','mkdtempSync',\"openSync(archivePath,'wx',0o600)\",\"spawnSync('curl'\",\"'--header','@-'\",'Agent request origin is not trusted.'])assert.ok(agent.includes(token),token)\n  assert.ok(!agent.includes('writeFileSync(archivePath'))\n  assert.ok(!agent.includes('response.arrayBuffer()'))\n  assert.ok(!agent.includes(\"'--location'\"))\n"
if old_tokens not in test:
    raise RuntimeError('M13 updater token contract not found')
test = test.replace(old_tokens, new_tokens, 1)
test = test.replace("'Start-Sleep -Seconds 3','-File `\"$runnerPath`\"'", "'Start-Sleep -Seconds 3','-File `\"$runnerPath`\"','Get-Command curl.exe'")
insert_after = "test('M13 Windows Scheduled Task preserves enrolled state and restarts after managed replacement',()=>{\n  const installer=read('desktop-agent/installers/windows/install.ps1')\n  for(const token of [\"$stateDir = Join-Path $InstallDir 'state'\",'$env:WORKINTEL_AGENT_HOME = $stateDir',\"$runnerPath = Join-Path $InstallDir 'run-agent.ps1'\",'while (`$true)','Start-Sleep -Seconds 3','-File `\"$runnerPath`\"','Get-Command curl.exe'])assert.ok(installer.includes(token),token)\n})\n"
if insert_after not in test:
    raise RuntimeError('Windows M13 test block not found')
test = test.replace(insert_after, insert_after + "\ntest('M13 managed-update deployment packages require curl across supported platforms',()=>{\n  for(const file of ['desktop-agent/installers/windows/install.ps1','desktop-agent/installers/macos/install.command','desktop-agent/installers/linux/install.sh'])assert.ok(read(file).includes('curl'),file)\n})\n", 1)
test_path.write_text(test, encoding='utf-8')

# curl preflight and deployment requirements.
replace_required('desktop-agent/installers/windows/install.ps1', "if ([int]$version -lt 20) { Fail \"Node.js 20+ required. Found $(& $node --version).\" }", "if ([int]$version -lt 20) { Fail \"Node.js 20+ required. Found $(& $node --version).\" }\n$curl = (Get-Command curl.exe -ErrorAction SilentlyContinue).Source\nif (-not $curl) { Fail 'curl is required for managed agent updates.' }")
replace_required('desktop-agent/installers/macos/install.command', "if (( MAJOR < 20 )); then echo \"Node.js 20+ required.\"; exit 1; fi", "if (( MAJOR < 20 )); then echo \"Node.js 20+ required.\"; exit 1; fi\ncommand -v curl >/dev/null 2>&1 || { echo \"curl is required for managed agent updates.\"; exit 1; }")
replace_required('desktop-agent/installers/linux/install.sh', "MAJOR=\"$($NODE_BIN -p 'process.versions.node.split(\".\")[0]')\"; (( MAJOR >= 20 )) || { echo \"Node.js 20+ required.\"; exit 1; }", "MAJOR=\"$($NODE_BIN -p 'process.versions.node.split(\".\")[0]')\"; (( MAJOR >= 20 )) || { echo \"Node.js 20+ required.\"; exit 1; }\ncommand -v curl >/dev/null 2>&1 || { echo \"curl is required for managed agent updates.\"; exit 1; }")
replace_required('desktop-agent/installers/windows/README.md', 'Requirements: Windows 10/11, PowerShell 5+, Node.js 20+.', 'Requirements: Windows 10/11, PowerShell 5+, Node.js 20+, and curl.')
replace_required('desktop-agent/installers/macos/README.md', 'Requirements: macOS 12+, Node.js 20+.', 'Requirements: macOS 12+, Node.js 20+, and curl.')
replace_required('desktop-agent/installers/linux/README.md', 'Requirements: Node.js 20+ and systemd user services.', 'Requirements: Node.js 20+, curl, and systemd user services.')

replace_required('tools/build-releases.py', "'Windows 10/11, Node.js 20+, PowerShell 5+'", "'Windows 10/11, Node.js 20+, PowerShell 5+, curl'")
replace_required('tools/build-releases.py', "'macOS 12+, Node.js 20+'", "'macOS 12+, Node.js 20+, curl'")
replace_required('tools/build-releases.py', "'Modern Linux desktop, Node.js 20+, systemd user session'", "'Modern Linux desktop, Node.js 20+, curl, systemd user session'")

prod_path = Path('desktop-agent/PRODUCTION_AGENT.md')
prod = prod_path.read_text(encoding='utf-8')
prod = prod.replace('# WorkIntel Native Agent 1.2.0', '# WorkIntel Native Agent 1.2.1', 1)
prod = prod.replace('Agent 1.2.0 advertises the `self_update` capability.', 'Agent 1.2.0 introduced the `self_update` capability; 1.2.1 hardens the managed download trust boundary.')
old_para = 'The agent accepts only the same-origin `/api/v1/agent/release/download` path, verifies the release metadata checksum format, downloads with its device token, verifies the response version and SHA-256, extracts into an isolated temporary directory, checks that the candidate contains the expected `desktop-agent/native-agent.mjs`, verifies the candidate version and runs `node --check` before replacing the installed source.'
new_para = 'The agent accepts only the same-origin `/api/v1/agent/release/download` path, verifies the release metadata checksum format, and requires the operating-system `curl` command for managed downloads. The bearer token is passed to curl through standard input rather than process arguments; curl streams the archive into an exclusive file descriptor inside an isolated random temporary directory. The agent verifies response metadata and the downloaded SHA-256 before extraction, then checks that the candidate contains the expected `desktop-agent/native-agent.mjs`, verifies the candidate version and runs `node --check` before replacing the installed source.'
if old_para not in prod:
    raise RuntimeError('Production agent lifecycle paragraph not found')
prod = prod.replace(old_para, new_para, 1)
prod = prod.replace('before managed updates can be used.', 'before managed updates can be used. Agent 1.2.0 can self-update normally to the 1.2.1 security hardening release.', 1)
prod_path.write_text(prod, encoding='utf-8')

arch_path = Path('docs/architecture/M13_AGENT_LIFECYCLE_RELIABILITY.md')
arch = arch_path.read_text(encoding='utf-8')
arch = arch.replace('3. Native agent, release builder, server runtime defaults, and deployment documentation did not have a single regression contract preventing release-version drift.', '3. Native agent, release builder, server runtime defaults, and deployment documentation did not have a single regression contract preventing release-version drift.\n4. Agent 1.2.0 initially staged verified response bytes through a JavaScript network-to-file sink. Agent 1.2.1 removes that sink while retaining checksum verification before extraction.')
arch = arch.replace('- The agent accepts only the same-origin `/api/v1/agent/release/download` path returned by the server.', '- The agent accepts only the same-origin `/api/v1/agent/release/download` path returned by the server.\n- Managed package transfer does not follow redirects; the bearer token is supplied to curl over stdin and the archive is streamed into an exclusive descriptor inside a random private temporary directory.')
arch = arch.replace('when the capability is absent.', 'when the capability is absent. Version 1.2.1 is the security-hardening patch release so deployed 1.2.0 agents can receive the hardened updater through the managed channel.', 1)
arch_path.write_text(arch, encoding='utf-8')

print('M13 1.2.1 security finalizer applied.')
