import { randomUUID } from 'node:crypto'
import { arch, hostname, platform, release } from 'node:os'
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'

const root = dirname(new URL(import.meta.url).pathname.replace(/^\/(.:)/, '$1'))
const storageDir = resolve(root, 'storage')
const configFile = resolve(storageDir, 'device.json')
const queueFile = resolve(storageDir, 'events.json')
mkdirSync(storageDir, { recursive: true })

const serverUrl = (process.env.AGENT_SERVER_URL || '').replace(/\/$/, '')
if (!serverUrl) {
  console.error('AGENT_SERVER_URL is required, e.g. https://team.example.com')
  process.exit(1)
}
const version = process.env.AGENT_VERSION || '0.1.0'

/** Returns read json data required by the current workflow. */ function readJson(file, fallback) {
  if (!existsSync(file)) return fallback
  try { return JSON.parse(readFileSync(file, 'utf8')) } catch { return fallback }
}
/** Handles the write json operation for the WorkIntel application. */ function writeJson(file, value) { writeFileSync(file, `${JSON.stringify(value, null, 2)}\n`, 'utf8') }
/** Handles the device config operation for the WorkIntel application. */ function deviceConfig() { return readJson(configFile, null) }
/** Handles the event queue operation for the WorkIntel application. */ function eventQueue() { return readJson(queueFile, []) }
/** Handles the save queue operation for the WorkIntel application. */ function saveQueue(events) { writeJson(queueFile, events) }

/** Handles the queue event operation for the WorkIntel application. */ function queueEvent(type, payload = {}) {
  const events = eventQueue()
  events.push({ event_id: randomUUID(), type, occurred_at: new Date().toISOString(), payload })
  saveQueue(events)
}

/** Handles the json request operation for the WorkIntel application. */ async function jsonRequest(path, options = {}, token = null) {
  const headers = new Headers(options.headers || {})
  headers.set('Accept', 'application/json')
  headers.set('Content-Type', 'application/json')
  if (token) headers.set('Authorization', `Bearer ${token}`)
  const response = await fetch(`${serverUrl}${path}`, { ...options, headers })
  const payload = await response.json().catch(() => null)
  if (!response.ok) throw new Error(payload?.message || `HTTP ${response.status}`)
  return payload
}

/** Handles the enroll operation for the WorkIntel application. */ async function enroll(code) {
  if (!code) throw new Error('Usage: node reference-agent.mjs enroll WI-XXXX-XXXX-XXXX')
  const installationId = readJson(configFile, {})?.installation_id || randomUUID()
  const payload = await jsonRequest('/api/v1/agent/enroll', {
    method: 'POST',
    body: JSON.stringify({
      enrollment_code: code,
      installation_id: installationId,
      name: hostname(),
      platform: platform() === 'win32' ? 'windows' : platform() === 'darwin' ? 'macos' : 'linux',
      os_name: platform() === 'win32' ? 'Windows' : platform() === 'darwin' ? 'macOS' : 'Linux',
      os_version: release(),
      architecture: arch(),
      agent_version: version,
      capabilities: ['heartbeat', 'offline_sync', 'commands', 'app_tracking', 'domain_tracking', 'screenshots'],
    }),
  })
  writeJson(configFile, {
    installation_id: installationId,
    device_uuid: payload.device.uuid,
    access_token: payload.access_token,
    heartbeat_interval_seconds: payload.config.heartbeat_interval_seconds,
    remote_activity_config: payload.config.activity,
    remote_screenshot_config: payload.config.screenshots,
    tracking_status: 'stopped',
  })
  queueEvent('agent.enrolled', { device_uuid: payload.device.uuid })
  console.log(`Enrolled ${payload.device.name} (${payload.device.uuid})`)
}

/** Handles the sync events operation for the WorkIntel application. */ async function syncEvents(config) {
  const events = eventQueue()
  if (!events.length) return
  const batch = events.slice(0, 500)
  const result = await jsonRequest('/api/v1/agent/sync', {
    method: 'POST',
    body: JSON.stringify({ batch_id: randomUUID(), client_created_at: new Date().toISOString(), events: batch }),
  }, config.access_token)
  saveQueue(events.slice(batch.length))
  console.log(`Synced ${result.accepted} events (${result.duplicates} duplicates)`)
}

/** Handles the acknowledge operation for the WorkIntel application. */ async function acknowledge(config, command, status, result = {}) {
  await jsonRequest(`/api/v1/agent/commands/${command.uuid}/ack`, {
    method: 'POST', body: JSON.stringify({ status, result }),
  }, config.access_token)
}

/** Handles the process commands operation for the WorkIntel application. */ async function processCommands(config, commands) {
  for (const command of commands || []) {
    try {
      if (command.command_type === 'pause_tracking') config.tracking_status = 'paused'
      else if (command.command_type === 'resume_tracking') config.tracking_status = 'active'
      else if (command.command_type === 'restart_agent') {
        await acknowledge(config, command, 'acknowledged', { message: 'Restart requested. Reference agent exits for supervisor restart.' })
        writeJson(configFile, config)
        process.exit(0)
      } else if (command.command_type === 'update_agent') {
        await acknowledge(config, command, 'failed', { message: 'Updater is not implemented in the reference agent.' })
        continue
      }
      writeJson(configFile, config)
      await acknowledge(config, command, 'acknowledged', { tracking_status: config.tracking_status })
    } catch (error) {
      console.error(`Command ${command.uuid} failed:`, error.message)
    }
  }
}

/** Handles the heartbeat operation for the WorkIntel application. */ async function heartbeat(config) {
  const result = await jsonRequest('/api/v1/agent/heartbeat', {
    method: 'POST',
    body: JSON.stringify({
      agent_version: version,
      tracking_status: config.tracking_status || 'stopped',
      is_idle: false,
      offline_queue_size: eventQueue().length,
      os_version: release(),
      capabilities: ['heartbeat', 'offline_sync', 'commands', 'app_tracking', 'domain_tracking', 'screenshots'],
      metadata: { hostname: hostname() },
      current_app: process.env.AGENT_CURRENT_APP || null,
      current_domain: process.env.AGENT_CURRENT_DOMAIN || null,
      activity_percent: process.env.AGENT_ACTIVITY_PERCENT ? Number(process.env.AGENT_ACTIVITY_PERCENT) : null,
    }),
  }, config.access_token)
  config.heartbeat_interval_seconds = result.config.heartbeat_interval_seconds
  config.remote_activity_config = result.config.activity
  config.remote_screenshot_config = result.config.screenshots
  writeJson(configFile, config)
  await processCommands(config, result.commands)
}

/** Handles the queue tracked session operation for the WorkIntel application. */ function queueTrackedSession(type, target, seconds = 60) {
  const duration = Math.max(5, Number(seconds) || 60)
  const endedAt = new Date()
  const startedAt = new Date(endedAt.getTime() - duration * 1000)
  if (type === 'app.session') {
    queueEvent(type, {
      app_name: target,
      process_name: target.toLowerCase().replace(/\s+/g, '-') + (platform() === 'win32' ? '.exe' : ''),
      started_at: startedAt.toISOString(),
      ended_at: endedAt.toISOString(),
      active_seconds: duration,
      idle_seconds: 0,
    })
  } else {
    queueEvent(type, {
      domain: target,
      browser_name: 'Reference Browser',
      started_at: startedAt.toISOString(),
      ended_at: endedAt.toISOString(),
      active_seconds: duration,
      idle_seconds: 0,
    })
  }
}

/** Handles the upload screenshot operation for the WorkIntel application. */ async function uploadScreenshot(filePath) {
  const config = deviceConfig()
  if (!config?.access_token) throw new Error('Agent is not enrolled. Run the enroll command first.')
  if (!filePath || !existsSync(filePath)) throw new Error('Provide an existing PNG/JPEG/WebP file path.')
  if (config.remote_screenshot_config?.enabled === false) throw new Error('Screenshot capture is disabled by workspace policy.')
  const lower = filePath.toLowerCase()
  const mime = lower.endsWith('.png') ? 'image/png' : lower.endsWith('.webp') ? 'image/webp' : 'image/jpeg'
  const form = new FormData()
  form.set('image', new Blob([readFileSync(filePath)], { type: mime }), filePath.split(/[\\/]/).pop() || 'screenshot.jpg')
  form.set('captured_at', new Date().toISOString())
  form.set('monitor_index', '1')
  form.set('app_name', 'Reference Agent')
  form.set('activity_percent', '80')
  form.set('blurred', config.remote_screenshot_config?.blur_by_default ? '1' : '0')
  const response = await fetch(`${serverUrl}/api/v1/agent/screenshots`, { method: 'POST', headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${config.access_token}` }, body: form })
  const payload = await response.json().catch(() => null)
  if (!response.ok) throw new Error(payload?.message || `HTTP ${response.status}`)
  console.log(`Uploaded screenshot ${payload.screenshot.uuid}`)
}

/** Handles the run operation for the WorkIntel application. */ async function run() {
  const config = deviceConfig()
  if (!config?.access_token) throw new Error('Agent is not enrolled. Run the enroll command first.')
  queueEvent('agent.started', { version })
  let wasOffline = false
  while (true) {
    try {
      await heartbeat(config)
      if (wasOffline) {
        queueEvent('connectivity.online')
        wasOffline = false
      }
      await syncEvents(config)
    } catch (error) {
      if (!wasOffline) {
        queueEvent('connectivity.offline', { reason: error.message })
        wasOffline = true
      }
      console.error(`[${new Date().toISOString()}] ${error.message}`)
    }
    await new Promise(resolvePromise => setTimeout(resolvePromise, Math.max(10, config.heartbeat_interval_seconds || 30) * 1000))
  }
}

const [, , command, argument, seconds] = process.argv
if (command === 'enroll') await enroll(argument)
else if (command === 'run') await run()
else if (command === 'record-app') {
  queueTrackedSession('app.session', argument || 'Visual Studio Code', seconds)
  console.log('Queued application session. Run the agent to sync it.')
}
else if (command === 'upload-screenshot') {
  await uploadScreenshot(argument)
}
else if (command === 'record-domain') {
  queueTrackedSession('website.session', argument || 'github.com', seconds)
  console.log('Queued domain-only website session. Run the agent to sync it.')
}
else {
  console.log('WorkIntel reference desktop agent')
  console.log('  node reference-agent.mjs enroll WI-XXXX-XXXX-XXXX')
  console.log('  node reference-agent.mjs run')
  console.log('  node reference-agent.mjs record-app "Visual Studio Code" 120')
  console.log('  node reference-agent.mjs record-domain github.com 120')
  console.log('  node reference-agent.mjs upload-screenshot ./screen.png')
}
