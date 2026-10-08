import { spawn } from 'node:child_process'
import path from 'node:path'
import process from 'node:process'

const root = path.resolve(import.meta.dirname, '..')
const configuredBase = process.env.WORKINTEL_E2E_BASE_URL || 'http://127.0.0.1:8777'
const base = new URL(configuredBase)
const allowedHosts = new Set(['127.0.0.1', 'localhost', '::1'])

if (!allowedHosts.has(base.hostname)) {
  console.error('[e2e-server-supervisor] Refusing to start a local server for a non-local E2E base URL.')
  process.exit(1)
}

const host = base.hostname === 'localhost' ? '127.0.0.1' : base.hostname
const port = Number(base.port || (base.protocol === 'https:' ? 443 : 80))
const healthURL = new URL('/health/live', base).toString()
const probeIntervalMs = 2_000
const probeTimeoutMs = 3_000
const startupGraceMs = 45_000
const unhealthyProbeLimit = 3
const maxRestarts = 2

let server = null
let serverSpawnError = null
let serverExit = null
let serverStartedAt = 0
let consecutiveFailures = 0
let restartCount = 0
let stopping = false

const delay = ms => new Promise(resolve => setTimeout(resolve, ms))
const log = message => console.log(`[e2e-server-supervisor] ${message}`)

function startServer() {
  serverSpawnError = null
  serverExit = null
  serverStartedAt = Date.now()
  server = spawn('php', ['artisan', 'serve', '--no-reload', `--host=${host}`, `--port=${port}`], {
    cwd: root,
    env: process.env,
    stdio: 'inherit',
    windowsHide: true,
  })

  server.once('error', error => {
    serverSpawnError = error
    console.error(`[e2e-server-supervisor] Laravel server process error: ${error.message}`)
  })
  server.once('exit', (code, signal) => {
    serverExit = { code, signal }
    if (!stopping) log(`Laravel server exited (code=${code ?? 'null'}, signal=${signal ?? 'none'}).`)
  })
  log(`Started Laravel server PID ${server.pid ?? 'unavailable'} at http://${host}:${port}.`)
}

async function isHealthy() {
  const controller = new AbortController()
  const timeout = setTimeout(() => controller.abort(), probeTimeoutMs)

  try {
    const response = await fetch(healthURL, { signal: controller.signal })
    await response.body?.cancel()
    return response.ok
  } catch {
    return false
  } finally {
    clearTimeout(timeout)
  }
}

async function terminateServer() {
  const current = server
  if (!current) return

  const exited = new Promise(resolve => {
    if (current.exitCode !== null || current.signalCode !== null) {
      resolve()
    } else {
      current.once('exit', resolve)
    }
  })

  if (current.pid && process.platform === 'win32') {
    await new Promise(resolve => {
      const killer = spawn('taskkill.exe', ['/pid', String(current.pid), '/t', '/f'], {
        stdio: 'ignore',
        windowsHide: true,
      })
      killer.once('error', () => {
        current.kill('SIGTERM')
        resolve()
      })
      killer.once('close', resolve)
    })
  } else {
    current.kill('SIGTERM')
  }

  await Promise.race([exited, delay(5_000)])
  if (current.exitCode === null && current.signalCode === null) current.kill('SIGKILL')
  if (server === current) server = null
}

async function stop(exitCode = 0) {
  if (stopping) return
  stopping = true
  await terminateServer()
  process.exitCode = exitCode
}

async function recover(reason) {
  if (restartCount >= maxRestarts) {
    console.error(`[e2e-server-supervisor] Circuit breaker stopped recovery after ${restartCount} restarts: ${reason}`)
    await stop(1)
    return
  }

  restartCount += 1
  console.error(`[e2e-server-supervisor] Restarting Laravel server (${restartCount}/${maxRestarts}): ${reason}`)
  await terminateServer()
  await delay(500 * restartCount)
  consecutiveFailures = 0
  startServer()
}

async function supervise() {
  startServer()

  while (!stopping) {
    await delay(probeIntervalMs)
    if (stopping) break

    if (serverSpawnError || serverExit || !server || server.exitCode !== null || server.signalCode !== null) {
      const reason = serverSpawnError?.message || `process exit ${JSON.stringify(serverExit)}`
      await recover(reason)
      continue
    }

    const healthy = await isHealthy()
    if (healthy) {
      consecutiveFailures = 0
      continue
    }

    if (Date.now() - serverStartedAt < startupGraceMs) continue
    consecutiveFailures += 1
    if (consecutiveFailures >= unhealthyProbeLimit) {
      await recover(`health endpoint failed ${consecutiveFailures} consecutive probes`)
    }
  }
}

process.once('SIGINT', () => { void stop(0) })
process.once('SIGTERM', () => { void stop(0) })

supervise().catch(async error => {
  console.error(`[e2e-server-supervisor] Fatal supervisor error: ${error.stack || error.message}`)
  await stop(1)
})
