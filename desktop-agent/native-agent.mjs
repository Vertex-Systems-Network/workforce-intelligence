import { createHash, randomUUID } from 'node:crypto'
import { arch, hostname, platform, release, tmpdir } from 'node:os'
import { copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync, unlinkSync, chmodSync, openSync, closeSync, renameSync, rmSync } from 'node:fs'
import { dirname, resolve, basename } from 'node:path'
import { execFileSync, spawnSync } from 'node:child_process'

const VERSION = '1.2.2'
const POLL_MS = 5000
const MAX_SESSION_SECONDS = 300
const AGENT_CAPABILITIES = ['heartbeat','offline_sync','commands','app_tracking','screenshots','idle_detection','native_service','self_update']
const root = dirname(new URL(import.meta.url).pathname.replace(/^\/(.:)/, '$1'))
const stateDir = resolve(process.env.WORKINTEL_AGENT_HOME || resolve(root, 'storage-native'))
const configFile = resolve(stateDir, 'device.json')
const queueFile = resolve(stateDir, 'events.json')
const lockFile = resolve(stateDir, 'agent.lock')
mkdirSync(stateDir, { recursive: true })

/** Handles the log operation for the WorkIntel application. */ function log(message, extra = '') { console.log(`[${new Date().toISOString()}] ${message}${extra ? ` ${extra}` : ''}`) }
/** Returns read json data required by the current workflow. */ function readJson(file, fallback) { if (!existsSync(file)) return fallback; try { return JSON.parse(readFileSync(file, 'utf8')) } catch { return fallback } }
/** Handles the write json operation for the WorkIntel application. */ function writeJson(file, value) { writeFileSync(file, `${JSON.stringify(value, null, 2)}\n`, 'utf8'); if (platform() !== 'win32') { try { chmodSync(file, 0o600) } catch {} } }
/** Returns get config data required by the current workflow. */ function getConfig() { return readJson(configFile, null) }
/** Handles the save config operation for the WorkIntel application. */ function saveConfig(config) { writeJson(configFile, config) }
/** Returns get queue data required by the current workflow. */ function getQueue() { return readJson(queueFile, []) }
/** Handles the save queue state for the WorkIntel application. */ function saveQueue(queue) { writeJson(queueFile, queue.slice(-10000)) }
/** Handles the queue event operation for the WorkIntel application. */ function queueEvent(type, payload = {}) { const q=getQueue(); q.push({event_id:randomUUID(),type,occurred_at:new Date().toISOString(),payload}); saveQueue(q) }
/** Handles the sleep operation for the WorkIntel application. */ function sleep(ms) { return new Promise(resolvePromise => setTimeout(resolvePromise, ms)) }

/** Handles the validate server url operation for the WorkIntel application. */ function validateServerUrl(value) {
  const url = new URL(String(value || '').trim())
  if (!['http:','https:'].includes(url.protocol)) throw new Error('Server must use http:// or https://')
  if (url.username || url.password) throw new Error('Server URL must not contain credentials.')
  const local = ['localhost','127.0.0.1','::1'].includes(url.hostname)
  if (url.protocol !== 'https:' && !local && process.env.WORKINTEL_ALLOW_INSECURE_HTTP !== 'true') {
    throw new Error('HTTPS is required for non-local servers. Set WORKINTEL_ALLOW_INSECURE_HTTP=true only for trusted development networks.')
  }
  url.hash = ''
  url.search = ''
  return url.toString().replace(/\/$/, '')
}

/** Resolves one API path against the validated enrolled origin and rejects origin changes. */ function trustedRequestUrl(config, path) {
  if (typeof path !== 'string' || !path.startsWith('/api/v1/')) throw new Error('Agent request path is not trusted.')
  const serverUrl = validateServerUrl(config?.server_url)
  const base = new URL(`${serverUrl}/`)
  const endpoint = new URL(path.replace(/^\//, ''), base)
  if (endpoint.origin !== base.origin || endpoint.username || endpoint.password) throw new Error('Agent request origin is not trusted.')
  return endpoint
}

/** Performs one authenticated request without following redirects away from the enrolled server. */ async function authenticatedRequest(config, path, options = {}) {
  const headers = new Headers(options.headers || {})
  if (config?.access_token) headers.set('Authorization', `Bearer ${config.access_token}`)
  return fetch(trustedRequestUrl(config, path), {...options, headers, redirect:'error'})
}

/** Handles the acquire lock operation for the WorkIntel application. */ function acquireLock() {
  if (existsSync(lockFile)) {
    const pid = Number(readFileSync(lockFile, 'utf8'))
    if (pid) { try { process.kill(pid, 0); throw new Error(`WorkIntel Agent is already running (PID ${pid}).`) } catch (error) { if (error?.code !== 'ESRCH') throw error } }
    try { unlinkSync(lockFile) } catch {}
  }
  const fd = openSync(lockFile, 'wx')
  writeFileSync(fd, String(process.pid)); closeSync(fd)
  /** Handles the cleanup operation for the WorkIntel application. */ const cleanup = () => { try { unlinkSync(lockFile) } catch {} }
  process.on('exit', cleanup); process.on('SIGINT', () => { cleanup(); process.exit(0) }); process.on('SIGTERM', () => { cleanup(); process.exit(0) })
}

/** Handles the json request operation for the WorkIntel application. */ async function jsonRequest(config, path, options = {}) {
  const headers = new Headers(options.headers || {})
  headers.set('Accept','application/json')
  if (options.body !== undefined && !(options.body instanceof FormData)) headers.set('Content-Type','application/json')
  const response = await authenticatedRequest(config, path, {...options, headers})
  const payload = await response.json().catch(() => null)
  if (!response.ok) throw new Error(payload?.message || `HTTP ${response.status}`)
  return payload
}

/** Compares stable semantic versions without trusting package-provided executable code. */ function compareVersions(left, right) {
  const parse = value => {
    const match = String(value || '').match(/^(\d+)\.(\d+)\.(\d+)(?:[-+][0-9A-Za-z.-]+)?$/)
    if (!match) throw new Error(`Invalid agent version: ${value}`)
    return match.slice(1, 4).map(Number)
  }
  const a=parse(left),b=parse(right)
  for(let i=0;i<3;i++){if(a[i]!==b[i])return a[i]>b[i]?1:-1}
  return 0
}

/**
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

/** Downloads, verifies, stages, and atomically replaces the managed native-agent source. */ async function installManagedUpdate(config) {
  const metadata=await jsonRequest(config,'/api/v1/agent/release')
  const managed=metadata?.release
  if(!managed||typeof managed.version!=='string'||typeof managed.sha256!=='string')throw new Error('Managed release metadata is incomplete.')
  if(managed.download_path!=='/api/v1/agent/release/download')throw new Error('Managed release download path is not trusted.')
  if(!/^[a-f0-9]{64}$/i.test(managed.sha256))throw new Error('Managed release checksum is invalid.')
  if(compareVersions(managed.version,VERSION)<=0)return {updated:false,from:VERSION,to:managed.version,sha256:managed.sha256}

  const updateDir=mkdtempSync(resolve(tmpdir(),'workintel-update-'))
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
    if(platform()==='win32'){
      const safeArchive=archivePath.replaceAll("'","''"),safeExtract=extractDir.replaceAll("'","''")
      powershell(`Expand-Archive -LiteralPath '${safeArchive}' -DestinationPath '${safeExtract}' -Force`)
    }else{
      if(!commandExists('unzip'))throw new Error('The unzip command is required for managed agent updates.')
      execFileSync('unzip',['-q',archivePath,'-d',extractDir],{stdio:'pipe',timeout:30000})
    }
    const candidate=resolve(extractDir,'desktop-agent','native-agent.mjs')
    if(!existsSync(candidate))throw new Error('Managed release does not contain desktop-agent/native-agent.mjs.')
    const candidateSource=readFileSync(candidate,'utf8')
    const candidateVersion=candidateSource.match(/const VERSION = ['"]([^'"]+)['"]/u)?.[1]
    if(candidateVersion!==managed.version)throw new Error('Managed release source version does not match release metadata.')
    execFileSync(process.execPath,['--check',candidate],{stdio:'pipe',timeout:15000})

    copyFileSync(candidate,stagedPath)
    if(platform()!=='win32')chmodSync(stagedPath,0o755)
    if(existsSync(backupPath))unlinkSync(backupPath)
    copyFileSync(currentPath,backupPath)
    try{
      unlinkSync(currentPath)
      renameSync(stagedPath,currentPath)
      if(platform()!=='win32')chmodSync(currentPath,0o755)
    }catch(error){
      try{copyFileSync(backupPath,currentPath)}catch{}
      throw error
    }
    return {updated:true,from:VERSION,to:managed.version,sha256:actualHash}
  }finally{
    try{if(existsSync(stagedPath))unlinkSync(stagedPath)}catch{}
    try{rmSync(updateDir,{recursive:true,force:true})}catch{}
  }
}

/** Handles the powershell operation for the WorkIntel application. */ function powershell(command) {
  return execFileSync('powershell.exe', ['-NoProfile','-NonInteractive','-ExecutionPolicy','Bypass','-Command', command], {encoding:'utf8',windowsHide:true,timeout:12000}).trim()
}
/** Handles the shell operation for the WorkIntel application. */ function shell(command,args=[]) { return execFileSync(command,args,{encoding:'utf8',timeout:10000}).trim() }
/** Handles the command exists operation for the WorkIntel application. */ function commandExists(command) { const result=spawnSync(platform()==='win32'?'where':'which',[command],{stdio:'ignore'}); return result.status===0 }

/** Handles the foreground windows operation for the WorkIntel application. */ function foregroundWindows() {
  const script = `$ErrorActionPreference='Stop'; Add-Type @'\nusing System; using System.Runtime.InteropServices;\npublic static class WI { [DllImport("user32.dll")] public static extern IntPtr GetForegroundWindow(); [DllImport("user32.dll")] public static extern uint GetWindowThreadProcessId(IntPtr hWnd, out uint pid); }\n'@; $h=[WI]::GetForegroundWindow(); [uint32]$pid=0; [WI]::GetWindowThreadProcessId($h,[ref]$pid)|Out-Null; $p=Get-Process -Id $pid; [pscustomobject]@{app=$p.ProcessName;process=($p.ProcessName+'.exe');title=$p.MainWindowTitle}|ConvertTo-Json -Compress`
  return JSON.parse(powershell(script))
}
/** Handles the idle windows operation for the WorkIntel application. */ function idleWindows() {
  const script = `Add-Type @'\nusing System; using System.Runtime.InteropServices; public static class WIIdle { [StructLayout(LayoutKind.Sequential)] public struct LASTINPUTINFO { public uint cbSize; public uint dwTime; } [DllImport("user32.dll")] public static extern bool GetLastInputInfo(ref LASTINPUTINFO plii); }\n'@; $x=New-Object WIIdle+LASTINPUTINFO; $x.cbSize=[Runtime.InteropServices.Marshal]::SizeOf($x); [WIIdle]::GetLastInputInfo([ref]$x)|Out-Null; [int]([Environment]::TickCount - $x.dwTime)`
  return Math.max(0, Number(powershell(script)))
}
/** Handles the foreground mac operation for the WorkIntel application. */ function foregroundMac() {
  const app=shell('osascript',['-e','tell application "System Events" to get name of first application process whose frontmost is true'])
  let title=''; try { title=shell('osascript',['-e','tell application "System Events" to tell (first application process whose frontmost is true) to get name of front window']) } catch {}
  return {app,process:app,title}
}
/** Handles the idle mac operation for the WorkIntel application. */ function idleMac() {
  try { const out=shell('ioreg',['-c','IOHIDSystem']); const match=out.match(/"HIDIdleTime" = (\d+)/); return match ? Number(match[1])/1e6 : 0 } catch { return 0 }
}
/** Handles the foreground linux operation for the WorkIntel application. */ function foregroundLinux() {
  if (!commandExists('xdotool')) return {app:'Unknown',process:'unknown',title:''}
  const win=shell('xdotool',['getactivewindow']); const pid=shell('xdotool',['getwindowpid',win]);
  let processName='unknown'; try { processName=readFileSync(`/proc/${pid}/comm`,'utf8').trim() } catch {}
  let title=''; try { title=shell('xdotool',['getwindowname',win]) } catch {}
  return {app:processName,process:processName,title}
}
/** Handles the idle linux operation for the WorkIntel application. */ function idleLinux() { if(commandExists('xprintidle')) { try { return Number(shell('xprintidle')) || 0 } catch {} } return 0 }
/** Handles the foreground operation for the WorkIntel application. */ function foreground() { try { return platform()==='win32'?foregroundWindows():platform()==='darwin'?foregroundMac():foregroundLinux() } catch { return {app:'Unknown',process:'unknown',title:''} } }
/** Handles the idle milliseconds operation for the WorkIntel application. */ function idleMilliseconds() { try { return platform()==='win32'?idleWindows():platform()==='darwin'?idleMac():idleLinux() } catch { return 0 } }

/** Handles the capture windows operation for the WorkIntel application. */ function captureWindows(path, allMonitors) {
  const bounds = allMonitors ? '[System.Windows.Forms.SystemInformation]::VirtualScreen' : '[System.Windows.Forms.Screen]::PrimaryScreen.Bounds'
  const script=`Add-Type -AssemblyName System.Windows.Forms; Add-Type -AssemblyName System.Drawing; $b=${bounds}; $bmp=New-Object System.Drawing.Bitmap $b.Width,$b.Height; $g=[System.Drawing.Graphics]::FromImage($bmp); $g.CopyFromScreen($b.Left,$b.Top,0,0,$b.Size); $bmp.Save('${path.replaceAll("'","''")}',[System.Drawing.Imaging.ImageFormat]::Png); $g.Dispose(); $bmp.Dispose()`
  powershell(script)
}
/** Handles the blur windows operation for the WorkIntel application. */ function blurWindows(path) {
  const p=path.replaceAll("'","''")
  const script=`Add-Type -AssemblyName System.Drawing; $src=[System.Drawing.Image]::FromFile('${p}'); $w=$src.Width; $h=$src.Height; $sw=[Math]::Max(20,[int]($w/18)); $sh=[Math]::Max(20,[int]($h/18)); $small=New-Object System.Drawing.Bitmap $sw,$sh; $g=[System.Drawing.Graphics]::FromImage($small); $g.DrawImage($src,0,0,$sw,$sh); $g.Dispose(); $src.Dispose(); $out=New-Object System.Drawing.Bitmap $w,$h; $g2=[System.Drawing.Graphics]::FromImage($out); $g2.InterpolationMode=[System.Drawing.Drawing2D.InterpolationMode]::NearestNeighbor; $g2.DrawImage($small,0,0,$w,$h); $g2.Dispose(); $small.Dispose(); $out.Save('${p}',[System.Drawing.Imaging.ImageFormat]::Png); $out.Dispose()`
  powershell(script)
}
/** Handles the capture mac operation for the WorkIntel application. */ function captureMac(path) { shell('screencapture',['-x','-m',path]) }
/** Handles the blur mac operation for the WorkIntel application. */ function blurMac(path) { shell('sips',['-Z','120',path]); shell('sips',['-Z','1600',path]) }
/** Handles the capture linux operation for the WorkIntel application. */ function captureLinux(path) {
  if(commandExists('gnome-screenshot')) shell('gnome-screenshot',['-f',path])
  else if(commandExists('grim')) shell('grim',[path])
  else if(commandExists('import')) shell('import',['-window','root',path])
  else throw new Error('No screenshot tool found (gnome-screenshot, grim, or ImageMagick import).')
}
/** Handles the blur linux operation for the WorkIntel application. */ function blurLinux(path) { if(!commandExists('convert')) throw new Error('ImageMagick convert is required for privacy blur.'); shell('convert',[path,'-scale','8%','-scale','1250%',path]) }
/** Handles the capture screenshot operation for the WorkIntel application. */ function captureScreenshot(path, allMonitors=false) { if(platform()==='win32')captureWindows(path,allMonitors);else if(platform()==='darwin')captureMac(path);else captureLinux(path) }
/** Handles the blur screenshot operation for the WorkIntel application. */ function blurScreenshot(path) { if(platform()==='win32')blurWindows(path);else if(platform()==='darwin')blurMac(path);else blurLinux(path) }
let screenshotNotificationShown=false
/** Handles the native notification operation for the WorkIntel application. */ function nativeNotification(title,message){try{if(platform()==='win32'){const safeTitle=String(title).replaceAll("'","''"),safeMessage=String(message).replaceAll("'","''");powershell(`$w=New-Object -ComObject WScript.Shell; $null=$w.Popup('${safeMessage}',3,'${safeTitle}',64)`)}else if(platform()==='darwin'){shell('osascript',['-e',`display notification ${JSON.stringify(String(message))} with title ${JSON.stringify(String(title))}`])}else if(commandExists('notify-send'))shell('notify-send',[String(title),String(message)])}catch(error){log('OS notification unavailable:',error.message)}}
/** Handles the notify screenshot operation for the WorkIntel application. */ function notifyScreenshot(policy,message='Screenshot captured.'){const mode=policy?.capture_notification_mode||'always';if(mode==='silent')return;if(mode==='first_session'&&screenshotNotificationShown)return;nativeNotification('WorkIntel',message);screenshotNotificationShown=true}

/** Handles the enroll operation for the WorkIntel application. */ async function enroll(server, code) {
  const serverUrl=validateServerUrl(server || process.env.AGENT_SERVER_URL)
  if(!code) throw new Error('Usage: node native-agent.mjs enroll https://server.example WI-XXXX-XXXX-XXXX')
  const previous=getConfig()||{}
  const installationId=previous.installation_id||randomUUID()
  const temp={server_url:serverUrl}
  const payload=await jsonRequest(temp,'/api/v1/agent/enroll',{method:'POST',body:JSON.stringify({
    enrollment_code:code,installation_id:installationId,name:hostname(),platform:platform()==='win32'?'windows':platform()==='darwin'?'macos':'linux',
    os_name:platform()==='win32'?'Windows':platform()==='darwin'?'macOS':'Linux',os_version:release(),architecture:arch(),agent_version:VERSION,
    capabilities:AGENT_CAPABILITIES
  })})
  const config={server_url:serverUrl,installation_id:installationId,device_uuid:payload.device.uuid,access_token:payload.access_token,heartbeat_interval_seconds:payload.config.heartbeat_interval_seconds,tracking_status:'active',remote_activity_config:payload.config.activity,remote_screenshot_config:payload.config.screenshots,enrolled_at:new Date().toISOString()}
  saveConfig(config); queueEvent('agent.enrolled',{device_uuid:payload.device.uuid,version:VERSION}); log(`Enrolled ${payload.device.name} (${payload.device.uuid})`)
}

/** Handles the sync operation for the WorkIntel application. */ async function sync(config) {
  const events=getQueue(); if(!events.length)return
  const batch=events.slice(0,Math.min(500,events.length))
  const result=await jsonRequest(config,'/api/v1/agent/sync',{method:'POST',body:JSON.stringify({batch_id:randomUUID(),client_created_at:new Date().toISOString(),events:batch})})
  saveQueue(events.slice(batch.length)); log(`Synced ${result.accepted} event(s), ${result.duplicates} duplicate(s).`)
}
/** Handles the ack operation for the WorkIntel application. */ async function ack(config,command,status,result={}) { await jsonRequest(config,`/api/v1/agent/commands/${command.uuid}/ack`,{method:'POST',body:JSON.stringify({status,result})}) }
/** Handles the commands operation for the WorkIntel application. */ async function commands(config,list=[]) {
  for(const command of list){
    try{
      if(command.command_type==='pause_tracking')config.tracking_status='paused'
      else if(command.command_type==='resume_tracking')config.tracking_status='active'
      else if(command.command_type==='restart_agent'){await ack(config,command,'acknowledged',{message:'Supervisor restart requested.'});saveConfig(config);process.exit(0)}
      else if(command.command_type==='update_agent'){
        const result=await installManagedUpdate(config)
        if(result.updated){
          queueEvent('agent.updated',{from:result.from,to:result.to,sha256:result.sha256})
          await ack(config,command,'acknowledged',{message:`Agent updated from ${result.from} to ${result.to}. Restarting under the installation supervisor.`,...result})
          saveConfig(config)
          process.exit(0)
        }
        await ack(config,command,'acknowledged',{message:`Agent ${VERSION} is already current for the managed stable channel.`,...result})
        continue
      }
      saveConfig(config);await ack(config,command,'acknowledged',{tracking_status:config.tracking_status})
    }catch(error){try{await ack(config,command,'failed',{message:error.message})}catch{}}
  }
}
/** Handles the heartbeat operation for the WorkIntel application. */ async function heartbeat(config, context, idle) {
  const result=await jsonRequest(config,'/api/v1/agent/heartbeat',{method:'POST',body:JSON.stringify({agent_version:VERSION,tracking_status:config.tracking_status,is_idle:idle,offline_queue_size:getQueue().length,os_version:release(),capabilities:AGENT_CAPABILITIES,metadata:{hostname:hostname()},current_app:context?.app||null,activity_percent:idle?0:100})})
  config.heartbeat_interval_seconds=result.config.heartbeat_interval_seconds;config.remote_activity_config=result.config.activity;config.remote_screenshot_config=result.config.screenshots;saveConfig(config);await commands(config,result.commands)
}

let currentSession=null
/** Handles the flush session operation for the WorkIntel application. */ function flushSession(now=new Date()) {
  if(!currentSession)return
  const seconds=Math.floor((now-currentSession.startedAt)/1000); const session=currentSession; currentSession=null
  if(seconds<Math.max(1,Number(session.minimumSeconds||5)))return
  queueEvent('app.session',{app_name:session.app,process_name:session.process,window_title:session.captureTitle?session.title:undefined,started_at:session.startedAt.toISOString(),ended_at:now.toISOString(),active_seconds:seconds,idle_seconds:0})
}
/** Updates update session state for the current workflow. */ function updateSession(config, context, idle) {
  const activity=config.remote_activity_config||{}
  if(config.tracking_status!=='active'||activity.application_tracking_enabled===false||idle){flushSession();return}
  const captureTitle=Boolean(activity.capture_window_titles)
  if(!currentSession||currentSession.process!==context.process){flushSession();currentSession={...context,startedAt:new Date(),captureTitle,minimumSeconds:activity.minimum_session_seconds||5}}
  else if((Date.now()-currentSession.startedAt.getTime())/1000>=MAX_SESSION_SECONDS){flushSession();currentSession={...context,startedAt:new Date(),captureTitle,minimumSeconds:activity.minimum_session_seconds||5}}
}

/** Handles the next screenshot at operation for the WorkIntel application. */ function nextScreenshotAt(config) {
  const policy=config.remote_screenshot_config||{}; const minutes=Math.max(1,Number(policy.interval_minutes||10));const jitter=Math.max(0,Number(policy.randomize_minutes||0));const offset=jitter?((Math.random()*2-1)*jitter):0;return Date.now()+Math.max(1,minutes+offset)*60000
}
/** Handles the maybe screenshot operation for the WorkIntel application. */ async function maybeScreenshot(config, dueAt) {
  const policy=config.remote_screenshot_config||{}
  if(config.tracking_status!=='active'||policy.enabled===false||Date.now()<dueAt)return dueAt
  const file=resolve(tmpdir(),`workintel-${randomUUID()}.png`)
  try{
    captureScreenshot(file,Boolean(policy.capture_all_monitors))
    let blurred=false
    if(policy.blur_by_default){try{blurScreenshot(file);blurred=true}catch(error){queueEvent('screenshot.skipped',{reason:`Privacy blur unavailable: ${error.message}`});return nextScreenshotAt(config)}}
    const form=new FormData();form.set('image',new Blob([readFileSync(file)],{type:'image/png'}),basename(file));form.set('captured_at',new Date().toISOString());form.set('monitor_index','1');form.set('app_name',foreground().app||'Unknown');form.set('activity_percent','100');form.set('blurred',blurred?'1':'0')
    await jsonRequest(config,'/api/v1/agent/screenshots',{method:'POST',body:form});queueEvent('screenshot.captured',{blurred});notifyScreenshot(policy)
  }catch(error){queueEvent('screenshot.failed',{reason:error.message});log('Screenshot failed:',error.message);if(policy.notify_on_upload_failure)nativeNotification('WorkIntel','Screenshot upload failed. The agent will retry on the next capture.') }finally{try{unlinkSync(file)}catch{}}
  return nextScreenshotAt(config)
}

/** Handles the run operation for the WorkIntel application. */ async function run() {
  acquireLock();const config=getConfig();if(!config?.access_token||!config?.server_url)throw new Error('Agent is not enrolled. Run enroll first.')
  validateServerUrl(config.server_url)
  queueEvent('agent.started',{version:VERSION,platform:platform(),architecture:arch()});let offline=false;let lastHeartbeat=0;let screenshotDue=nextScreenshotAt(config)
  while(true){
    const context=foreground();const idleThreshold=Math.max(60,Number(config.remote_activity_config?.idle_threshold_seconds||300))*1000;const idle=idleMilliseconds()>=idleThreshold
    updateSession(config,context,idle)
    try{
      if(Date.now()-lastHeartbeat>=Math.max(10,config.heartbeat_interval_seconds||30)*1000){await heartbeat(config,context,idle);lastHeartbeat=Date.now();if(offline){queueEvent('connectivity.online');offline=false}await sync(config)}
      screenshotDue=await maybeScreenshot(config,screenshotDue)
    }catch(error){if(!offline){queueEvent('connectivity.offline',{reason:error.message});offline=true}log('Agent cycle error:',error.message)}
    await sleep(POLL_MS)
  }
}

/** Handles the status operation for the WorkIntel application. */ function status(){const c=getConfig();console.log(JSON.stringify({version:VERSION,enrolled:Boolean(c?.access_token),server:c?.server_url||null,device_uuid:c?.device_uuid||null,tracking_status:c?.tracking_status||null,queued_events:getQueue().length,state_dir:stateDir},null,2))}

const [, , command, a, b]=process.argv
try{
  if(command==='enroll')await enroll(a,b)
  else if(command==='run')await run()
  else if(command==='status')status()
  else if(command==='once'){const c=getConfig();if(!c?.access_token)throw new Error('Not enrolled.');validateServerUrl(c.server_url);const ctx=foreground();const idle=idleMilliseconds()>=Math.max(60,Number(c.remote_activity_config?.idle_threshold_seconds||300))*1000;await heartbeat(c,ctx,idle);await sync(c);status()}
  else {console.log(`WorkIntel Native Agent ${VERSION}\n\nCommands:\n  node native-agent.mjs enroll https://your-server.example WI-XXXX-XXXX-XXXX\n  node native-agent.mjs run\n  node native-agent.mjs once\n  node native-agent.mjs status`)}
}catch(error){console.error(error instanceof Error?error.message:String(error));process.exit(1)}
