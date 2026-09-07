#!/usr/bin/env node

import fs from 'node:fs'
import process from 'node:process'

const DEFAULT_ATTESTATION = 'docs/operations/M14_RELEASE_TAG_RULESET_ATTESTATION.json'
const ATTESTATION_SCHEMA = 'workintel.release-tag-ruleset-attestation.v1'

function fail(message) {
  console.error(`release-tag-protection: ${message}`)
  process.exit(1)
}

function parseArgs(values) {
  const args = {}
  for (let index = 0; index < values.length; index += 1) {
    const token = values[index]
    if (!token.startsWith('--')) fail(`Unexpected argument: ${token}`)
    const key = token.slice(2)
    const value = values[index + 1]
    if (!value || value.startsWith('--')) fail(`Missing value for --${key}`)
    args[key] = value
    index += 1
  }
  return args
}

function requireArg(args, key) {
  const value = args[key]
  if (!value) fail(`Missing required --${key}`)
  return value
}

function parseTimestamp(value, label) {
  if (typeof value !== 'string' || value.length < 20 || Number.isNaN(Date.parse(value))) {
    fail(`${label} must be an ISO-8601 timestamp`)
  }
  return value
}

function loadAttestation(path) {
  let raw
  try {
    raw = fs.readFileSync(path, 'utf8')
  } catch (error) {
    fail(`Could not read release-tag ruleset attestation ${path}: ${error.message}`)
  }

  let attestation
  try {
    attestation = JSON.parse(raw)
  } catch (error) {
    fail(`Could not parse release-tag ruleset attestation ${path}: ${error.message}`)
  }

  if (!attestation || typeof attestation !== 'object' || Array.isArray(attestation)) {
    fail('release-tag ruleset attestation must be a JSON object')
  }
  if (attestation.schema !== ATTESTATION_SCHEMA) {
    fail(`release-tag ruleset attestation schema must be ${ATTESTATION_SCHEMA}`)
  }
  if (attestation.status !== 'VERIFIED') {
    fail('release-tag ruleset attestation status must be VERIFIED')
  }
  if (!Number.isInteger(attestation.ruleset_id) || attestation.ruleset_id <= 0) {
    fail('release-tag ruleset attestation ruleset_id must be a positive integer')
  }
  parseTimestamp(attestation.ruleset_updated_at, 'release-tag ruleset attestation ruleset_updated_at')
  if (attestation.no_bypass_actors_attested !== true) {
    fail('release-tag ruleset attestation must explicitly attest no bypass actors')
  }
  if (typeof attestation.audited_by !== 'string' || attestation.audited_by.trim() === '') {
    fail('release-tag ruleset attestation audited_by is required')
  }
  parseTimestamp(attestation.audited_at, 'release-tag ruleset attestation audited_at')
  if (Date.parse(attestation.audited_at) < Date.parse(attestation.ruleset_updated_at)) {
    fail('release-tag ruleset attestation audited_at cannot predate the attested ruleset snapshot')
  }

  return attestation
}

function globToRegex(pattern) {
  if (pattern === '~ALL') return /^refs\/tags\/.+$/
  if (typeof pattern !== 'string' || !pattern.startsWith('refs/tags/')) return null

  let source = '^'
  for (let index = 0; index < pattern.length; index += 1) {
    const char = pattern[index]
    if (char === '*') {
      if (pattern[index + 1] === '*') {
        source += '.*'
        index += 1
      } else {
        source += '[^/]*'
      }
      continue
    }
    if (char === '?') {
      source += '[^/]'
      continue
    }
    if ('\\.^$+{}()|[]'.includes(char)) source += `\\${char}`
    else source += char
  }
  source += '$'
  return new RegExp(source)
}

function matchesPattern(ref, pattern) {
  const regex = globToRegex(pattern)
  return regex ? regex.test(ref) : false
}

function verify(rulesets, tag, attestation) {
  if (!Array.isArray(rulesets)) fail('Expected a JSON array of detailed rulesets')

  const ref = `refs/tags/${tag}`
  const ruleset = rulesets.find(candidate => candidate && candidate.id === attestation.ruleset_id)
  if (!ruleset || typeof ruleset !== 'object') {
    fail(`Attested ruleset ${attestation.ruleset_id} was not returned by GitHub`)
  }

  const label = `ruleset ${attestation.ruleset_id}`
  if (ruleset.enforcement !== 'active') fail(`${label} is not active`)
  if (ruleset.target !== 'tag') fail(`${label} does not target tags`)
  if (ruleset.updated_at !== attestation.ruleset_updated_at) {
    fail(`${label} updated_at changed from attested snapshot ${attestation.ruleset_updated_at} to ${String(ruleset.updated_at || 'missing')}`)
  }

  const refName = ruleset.conditions?.ref_name
  if (!refName || !Array.isArray(refName.include) || !Array.isArray(refName.exclude)) {
    fail(`${label} has malformed ref_name conditions`)
  }
  const included = refName.include.some(pattern => matchesPattern(ref, pattern))
  const excluded = refName.exclude.some(pattern => matchesPattern(ref, pattern))
  if (!included || excluded) fail(`${label} does not apply to ${ref}`)

  if (Object.hasOwn(ruleset, 'bypass_actors')) {
    if (!Array.isArray(ruleset.bypass_actors)) fail(`${label} exposes malformed bypass_actors`)
    if (ruleset.bypass_actors.length > 0) {
      fail(`${label} currently exposes bypass actors despite the no-bypass attestation`)
    }
  }

  if (!Array.isArray(ruleset.rules)) fail(`${label} has malformed rules`)
  const ruleTypes = [...new Set(ruleset.rules.map(rule => rule?.type).filter(Boolean))].sort()
  for (const requiredRule of ['update', 'deletion']) {
    if (!ruleTypes.includes(requiredRule)) {
      fail(`${label} does not enforce ${requiredRule} restriction for ${ref}`)
    }
  }

  console.log(JSON.stringify({
    tag,
    ref,
    ruleset: {
      id: ruleset.id,
      name: String(ruleset.name || ''),
      source_type: String(ruleset.source_type || ''),
      updated_at: ruleset.updated_at,
      rules: ruleTypes,
    },
    protections: {
      update_restricted: true,
      deletion_restricted: true,
      no_bypass_actors: true,
      bypass_evidence: Object.hasOwn(ruleset, 'bypass_actors') ? 'api-plus-attested-snapshot' : 'attested-snapshot',
    },
    attestation: {
      schema: attestation.schema,
      audited_by: attestation.audited_by,
      audited_at: attestation.audited_at,
    },
  }, null, 2))
}

const args = parseArgs(process.argv.slice(2))
const tag = requireArg(args, 'tag')
if (!/^agent-v\d+(?:\.\d+){1,3}$/.test(tag)) {
  fail('tag must match agent-v<numeric-version>')
}
const attestation = loadAttestation(args.attestation || DEFAULT_ATTESTATION)

let raw = ''
process.stdin.setEncoding('utf8')
process.stdin.on('data', chunk => { raw += chunk })
process.stdin.on('end', () => {
  let payload
  try {
    payload = JSON.parse(raw)
  } catch (error) {
    fail(`Could not parse ruleset JSON: ${error.message}`)
  }
  verify(payload, tag, attestation)
})
