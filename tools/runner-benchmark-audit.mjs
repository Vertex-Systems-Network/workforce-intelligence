import fs from 'node:fs'
import path from 'node:path'

const root = process.cwd()
const registryPath = path.join(root, 'benchmarks/runner/registry.json')
const fail = message => {
  throw new Error(`[runner-benchmark-audit] ${message}`)
}

if (!fs.existsSync(registryPath)) fail('missing benchmarks/runner/registry.json')

let registry
try {
  registry = JSON.parse(fs.readFileSync(registryPath, 'utf8'))
} catch (error) {
  fail(`registry is not valid JSON: ${error instanceof Error ? error.message : String(error)}`)
}

if (registry.schema_version !== 1) fail('schema_version must be 1')
if (registry.execution_policy !== 'final-runner-batch') fail('execution_policy must be final-runner-batch')
if (!Array.isArray(registry.entries)) fail('entries must be an array')

const allowed = new Set(['queued', 'ready', 'running', 'blocked', 'passed', 'failed', 'superseded'])
const evidenceStates = new Set(['passed', 'failed'])
const ids = new Set()
const requiredString = (entry, field) => {
  if (typeof entry[field] !== 'string' || entry[field].trim() === '') fail(`${entry.id ?? '<unknown>'}: ${field} must be a non-empty string`)
}
const requiredStringArray = (entry, field) => {
  if (!Array.isArray(entry[field]) || entry[field].length === 0 || entry[field].some(value => typeof value !== 'string' || value.trim() === '')) {
    fail(`${entry.id ?? '<unknown>'}: ${field} must be a non-empty array of non-empty strings`)
  }
}

for (const entry of registry.entries) {
  if (!entry || typeof entry !== 'object' || Array.isArray(entry)) fail('every entry must be an object')
  requiredString(entry, 'id')
  if (!/^RB-\d{3,}$/.test(entry.id)) fail(`${entry.id}: id must match RB-###`)
  if (ids.has(entry.id)) fail(`${entry.id}: duplicate benchmark id`)
  ids.add(entry.id)

  for (const field of ['title', 'scope', 'defer_reason']) requiredString(entry, field)
  if (!entry.source || typeof entry.source !== 'object') fail(`${entry.id}: source must be an object`)
  if (typeof entry.source.kind !== 'string' || !entry.source.kind.trim()) fail(`${entry.id}: source.kind is required`)
  if (typeof entry.source.ref !== 'string' || !entry.source.ref.trim()) fail(`${entry.id}: source.ref is required`)

  requiredStringArray(entry, 'environment')
  requiredStringArray(entry, 'commands')
  requiredStringArray(entry, 'acceptance')
  if (!Array.isArray(entry.depends_on)) fail(`${entry.id}: depends_on must be an array`)
  if (entry.depends_on.some(value => typeof value !== 'string' || !value.trim())) fail(`${entry.id}: depends_on values must be non-empty strings`)

  if (typeof entry.required_for_release !== 'boolean') fail(`${entry.id}: required_for_release must be boolean`)
  if (entry.execution_phase !== 'final-runner-batch') fail(`${entry.id}: execution_phase must be final-runner-batch`)
  if (entry.stale_when_head_moves !== true) fail(`${entry.id}: stale_when_head_moves must be true`)
  if (!allowed.has(entry.status)) fail(`${entry.id}: unsupported status ${entry.status}`)

  const verification = entry.verification
  if (!verification || typeof verification !== 'object' || Array.isArray(verification)) fail(`${entry.id}: verification must be an object`)
  if (!Array.isArray(verification.evidence)) fail(`${entry.id}: verification.evidence must be an array`)
  if (verification.evidence.some(value => typeof value !== 'string' || !value.trim())) fail(`${entry.id}: verification.evidence values must be non-empty strings`)
  if (!Array.isArray(entry.history)) fail(`${entry.id}: history must be an array`)

  if (evidenceStates.has(entry.status)) {
    if (typeof verification.head_sha !== 'string' || !/^[0-9a-f]{40}$/i.test(verification.head_sha)) {
      fail(`${entry.id}: ${entry.status} requires a 40-character verification.head_sha`)
    }
    if (typeof verification.verified_at !== 'string' || Number.isNaN(Date.parse(verification.verified_at))) {
      fail(`${entry.id}: ${entry.status} requires a parseable verification.verified_at timestamp`)
    }
    if (verification.evidence.length === 0) fail(`${entry.id}: ${entry.status} requires at least one evidence reference`)
  } else if (verification.head_sha !== null || verification.verified_at !== null || verification.evidence.length !== 0) {
    fail(`${entry.id}: only passed/failed entries may carry current verification; move stale results to history first`)
  }
}

console.log(`Runner benchmark registry valid: ${registry.entries.length} entries, ${ids.size} unique IDs.`)
