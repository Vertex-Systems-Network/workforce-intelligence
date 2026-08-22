const apiKey = process.env.WAVE_API_KEY?.trim() ?? ''
const targetUrl = (process.env.WORKINTEL_WAVE_URL || process.env.WAVE_TARGET_URL || '').trim()

/** Parse a bounded integer environment option. */
function integerOption(name, fallback, minimum, maximum) {
  const raw = process.env[name]?.trim()
  if (!raw) return fallback
  const value = Number.parseInt(raw, 10)
  if (!Number.isInteger(value) || value < minimum || value > maximum) {
    throw new Error(`${name} must be an integer between ${minimum} and ${maximum}`)
  }
  return value
}

/** Reject targets that the hosted WAVE API cannot reach. */
function validateTarget(raw) {
  if (!raw) throw new Error('WORKINTEL_WAVE_URL (or WAVE_TARGET_URL) is required')
  const url = new URL(raw)
  if (!['http:', 'https:'].includes(url.protocol)) throw new Error('WAVE target must use http:// or https://')
  const host = url.hostname.toLowerCase()
  const privateHost = host === 'localhost' || host === '127.0.0.1' || host === '::1' || host.endsWith('.test') || host.endsWith('.local')
  if (privateHost) throw new Error('Hosted WAVE requires a publicly reachable URL; local/test hosts cannot be scanned')
  return url
}

/** Return a numeric WAVE category count without assuming optional item detail fields exist. */
function categoryCount(payload, name) {
  return Number(payload?.categories?.[name]?.count ?? 0)
}

async function main() {
  if (!apiKey) throw new Error('WAVE_API_KEY is required. The key is never printed by this tool.')

  const target = validateTarget(targetUrl)
  const viewportWidth = integerOption('WAVE_VIEWPORT_WIDTH', 1280, 320, 3840)
  const evalDelay = integerOption('WAVE_EVAL_DELAY', 500, 0, 10000)
  const reportType = integerOption('WAVE_REPORT_TYPE', 1, 1, 4)
  const maxErrors = integerOption('WAVE_MAX_ERRORS', 0, 0, 100000)
  const maxContrastErrors = integerOption('WAVE_MAX_CONTRAST_ERRORS', 0, 0, 100000)

  const endpoint = new URL('https://wave.webaim.org/api/request')
  endpoint.searchParams.set('key', apiKey)
  endpoint.searchParams.set('url', target.href)
  endpoint.searchParams.set('format', 'json')
  endpoint.searchParams.set('reporttype', String(reportType))
  endpoint.searchParams.set('viewportwidth', String(viewportWidth))
  endpoint.searchParams.set('evaldelay', String(evalDelay))

  const response = await fetch(endpoint, { headers: { Accept: 'application/json' } })
  if (!response.ok) throw new Error(`WAVE API HTTP ${response.status}`)

  const payload = await response.json()
  if (!payload?.status?.success) throw new Error(`WAVE API request failed: ${payload?.status?.error || 'unknown error'}`)

  const pageStatus = Number(payload?.status?.httpstatuscode ?? 0)
  if (pageStatus >= 400) throw new Error(`WAVE target returned HTTP ${pageStatus}`)

  const errors = categoryCount(payload, 'error')
  const contrastErrors = categoryCount(payload, 'contrast')
  const alerts = categoryCount(payload, 'alert')
  const score = payload?.statistics?.AIMscore ?? 'n/a'

  console.log('WorkIntel WAVE accessibility audit')
  console.log(`Target: ${payload?.statistics?.pageurl || target.href}`)
  console.log(`AIM score: ${score}`)
  console.log(`Errors: ${errors} (allowed ${maxErrors})`)
  console.log(`Contrast errors: ${contrastErrors} (allowed ${maxContrastErrors})`)
  console.log(`Alerts: ${alerts} (reported for review, not automatically failed)`)
  if (payload?.statistics?.waveurl) console.log(`Report: ${payload.statistics.waveurl}`)

  if (errors > maxErrors || contrastErrors > maxContrastErrors) {
    console.error('WorkIntel WAVE accessibility audit: FAIL')
    process.exit(1)
  }

  console.log('WorkIntel WAVE accessibility audit: PASS')
}

main().catch(error => {
  console.error(`WorkIntel WAVE accessibility audit: ERROR - ${error.message}`)
  process.exit(2)
})
