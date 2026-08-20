const ext = globalThis.browser ?? globalThis.chrome
const VERSION = ext.runtime.getManifest().version
const QUEUE_KEY = 'workintel_browser_queue'
const CONFIG_KEY = 'workintel_browser_config'
let currentSession = null

/** Handles the uuid operation for the WorkIntel application. */ function uuid() { return crypto.randomUUID() }
/** Handles the normalized domain operation for the WorkIntel application. */ function normalizedDomain(url) {
  try {
    const parsed = new URL(url)
    if (!['http:', 'https:'].includes(parsed.protocol)) return null
    return parsed.hostname.toLowerCase().replace(/^www\./, '')
  } catch { return null }
}
/** Handles the browser name operation for the WorkIntel application. */ function browserName() {
  const ua = navigator.userAgent
  if (/Firefox\//i.test(ua)) return 'Firefox'
  if (/Edg\//i.test(ua)) return 'Microsoft Edge'
  if (/Chrome\//i.test(ua)) return 'Google Chrome'
  return 'Browser'
}
/** Handles the browser version operation for the WorkIntel application. */ function browserVersion() {
  const match = navigator.userAgent.match(/(?:Firefox|Edg|Chrome)\/([0-9.]+)/i)
  return (match?.[1] || '').slice(0, 40)
}
/** Returns get config data required by the current workflow. */ async function getConfig() { const result = await ext.storage.local.get(CONFIG_KEY); return result[CONFIG_KEY] || null }
/** Updates set config state for the current workflow. */ async function setConfig(config) { await ext.storage.local.set({ [CONFIG_KEY]: config }) }
/** Returns get queue data required by the current workflow. */ async function getQueue() { const result = await ext.storage.local.get(QUEUE_KEY); return Array.isArray(result[QUEUE_KEY]) ? result[QUEUE_KEY] : [] }
/** Updates set queue state for the current workflow. */ async function setQueue(queue) { await ext.storage.local.set({ [QUEUE_KEY]: queue }) }
/** Handles the enqueue operation for the WorkIntel application. */ async function enqueue(session) { const queue = await getQueue(); queue.push(session); await setQueue(queue.slice(-2500)) }
/** Handles the idle state operation for the WorkIntel application. */ async function idleState() { try { return await ext.idle.queryState(60) } catch { return 'active' } }

/** Handles the flush current session operation for the WorkIntel application. */ async function flushCurrentSession() {
  if (!currentSession) return
  const endedAt = Date.now()
  const duration = Math.floor((endedAt - currentSession.startedAt) / 1000)
  const previous = currentSession
  currentSession = null
  const config = await getConfig()
  const minimumSeconds = Math.max(1, config?.remote_config?.minimum_session_seconds || 5)
  if (duration < minimumSeconds) return
  const state = await idleState()
  const isIdle = state !== 'active'
  await enqueue({
    session_id: uuid(),
    domain: previous.domain,
    browser_name: browserName(),
    started_at: new Date(previous.startedAt).toISOString(),
    ended_at: new Date(endedAt).toISOString(),
    active_seconds: isIdle ? 0 : duration,
    idle_seconds: isIdle ? duration : 0
  })
}

/** Handles the start from tab operation for the WorkIntel application. */ async function startFromTab(tab) {
  if (!tab || tab.incognito) return
  const config = await getConfig()
  if (!config?.access_token || config?.remote_config?.website_tracking_enabled === false) return
  const domain = normalizedDomain(tab.url || '')
  if (!domain) return
  if (currentSession?.domain === domain) return
  await flushCurrentSession()
  currentSession = { domain, startedAt: Date.now() }
}

/** Handles the refresh active tab operation for the WorkIntel application. */ async function refreshActiveTab() {
  try {
    const tabs = await ext.tabs.query({ active: true, lastFocusedWindow: true })
    await startFromTab(tabs[0])
  } catch {}
}

/** Handles the json request operation for the WorkIntel application. */ async function jsonRequest(path, options = {}, token = null) {
  const config = await getConfig()
  if (!config?.server_url) throw new Error('Server URL is not configured.')
  const headers = new Headers(options.headers || {})
  headers.set('Accept', 'application/json')
  headers.set('Content-Type', 'application/json')
  if (token) headers.set('Authorization', `Bearer ${token}`)
  const response = await fetch(`${config.server_url}${path}`, { ...options, headers })
  const payload = await response.json().catch(() => null)
  if (!response.ok) throw new Error(payload?.message || `HTTP ${response.status}`)
  return payload
}

/** Handles the sync queue operation for the WorkIntel application. */ async function syncQueue() {
  const config = await getConfig()
  if (!config?.access_token) return
  const queue = await getQueue()
  if (!queue.length) return
  const batch = queue.slice(0, 250)
  const result = await jsonRequest('/api/v1/browser/sync', { method: 'POST', body: JSON.stringify({ sessions: batch }) }, config.access_token)
  await setQueue(queue.slice(batch.length))
  config.last_sync_at = new Date().toISOString()
  config.last_sync_result = result
  config.last_error = null
  await setConfig(config)
}

/** Handles the heartbeat operation for the WorkIntel application. */ async function heartbeat() {
  const config = await getConfig()
  if (!config?.access_token) return
  try {
    const result = await jsonRequest('/api/v1/browser/heartbeat', {
      method: 'POST', body: JSON.stringify({ extension_version: VERSION })
    }, config.access_token)
    config.last_seen_at = new Date().toISOString()
    config.remote_config = result.config
    config.last_error = null
    await setConfig(config)
  } catch (error) {
    config.last_error = error instanceof Error ? error.message : String(error)
    await setConfig(config)
  }
}

/** Handles the enroll operation for the WorkIntel application. */ async function enroll({ server_url, enrollment_code }) {
  const existing = await getConfig()
  const installationId = existing?.installation_id || uuid()
  const serverUrl = String(server_url || '').trim().replace(/\/$/, '')
  if (!/^https?:\/\//i.test(serverUrl)) throw new Error('Enter a valid http:// or https:// server URL.')
  const response = await fetch(`${serverUrl}/api/v1/browser/enroll`, {
    method: 'POST',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({
      enrollment_code,
      installation_id: installationId,
      browser_name: browserName(),
      browser_version: browserVersion(),
      extension_version: VERSION
    })
  })
  const payload = await response.json().catch(() => null)
  if (!response.ok) throw new Error(payload?.message || `HTTP ${response.status}`)
  await setConfig({
    server_url: serverUrl,
    installation_id: installationId,
    connection_uuid: payload.connection.uuid,
    access_token: payload.access_token,
    member_id: payload.connection.member_id,
    enrolled_at: new Date().toISOString(),
    last_error: null,
    remote_config: payload.config
  })
  await refreshActiveTab()
  return payload
}

ext.runtime.onMessage.addListener((message, sender, sendResponse) => {
  ;(async () => {
    if (message?.type === 'enroll') return await enroll(message.payload)
    if (message?.type === 'status') return { config: await getConfig(), queue_size: (await getQueue()).length }
    if (message?.type === 'disconnect') {
      await flushCurrentSession()
      await ext.storage.local.remove([CONFIG_KEY, QUEUE_KEY])
      return { ok: true }
    }
    if (message?.type === 'sync_now') {
      await flushCurrentSession(); await syncQueue(); await refreshActiveTab(); await heartbeat(); return { ok: true }
    }
    return { ok: false }
  })().then(sendResponse).catch(error => sendResponse({ error: error instanceof Error ? error.message : String(error) }))
  return true
})

ext.tabs.onActivated.addListener(() => void refreshActiveTab())
ext.tabs.onUpdated.addListener((tabId, changeInfo, tab) => { if (tab.active && (changeInfo.url || changeInfo.status === 'complete')) void startFromTab(tab) })
ext.windows.onFocusChanged.addListener(windowId => { if (windowId === ext.windows.WINDOW_ID_NONE) void flushCurrentSession(); else void refreshActiveTab() })
ext.idle.onStateChanged.addListener(() => { void flushCurrentSession().then(refreshActiveTab) })
ext.alarms.create('workintel-sync', { periodInMinutes: 1 })
ext.alarms.onAlarm.addListener(alarm => { if (alarm.name === 'workintel-sync') void flushCurrentSession().then(syncQueue).then(refreshActiveTab).then(heartbeat) })
ext.runtime.onStartup.addListener(() => void refreshActiveTab())
ext.runtime.onInstalled.addListener(() => void refreshActiveTab())
