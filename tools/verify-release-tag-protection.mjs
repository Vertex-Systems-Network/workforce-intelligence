#!/usr/bin/env node

import process from 'node:process'

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

function verify(rulesets, tag) {
  if (!Array.isArray(rulesets)) fail('Expected a JSON array of detailed rulesets')

  const ref = `refs/tags/${tag}`
  const applicable = []
  const effectiveRules = new Set()

  for (const ruleset of rulesets) {
    if (!ruleset || typeof ruleset !== 'object') fail('Ruleset entries must be objects')
    if (ruleset.enforcement !== 'active' || ruleset.target !== 'tag') continue

    const id = String(ruleset.id ?? '')
    const label = id ? `ruleset ${id}` : 'tag ruleset'
    const refName = ruleset.conditions?.ref_name
    if (!refName || !Array.isArray(refName.include) || !Array.isArray(refName.exclude)) {
      fail(`${label} has malformed ref_name conditions`)
    }

    const included = refName.include.some(pattern => matchesPattern(ref, pattern))
    const excluded = refName.exclude.some(pattern => matchesPattern(ref, pattern))
    if (!included || excluded) continue

    if (!Array.isArray(ruleset.bypass_actors)) {
      fail(`${label} does not expose bypass_actors; immutable tag protection cannot be proven`)
    }
    if (ruleset.bypass_actors.length > 0) {
      fail(`${label} permits bypass actors; trusted release tag immutability requires none`)
    }
    if (!Array.isArray(ruleset.rules)) fail(`${label} has malformed rules`)

    const ruleTypes = [...new Set(ruleset.rules.map(rule => rule?.type).filter(Boolean))].sort()
    for (const type of ruleTypes) effectiveRules.add(type)
    applicable.push({
      id: ruleset.id ?? null,
      name: String(ruleset.name || ''),
      source_type: String(ruleset.source_type || ''),
      rules: ruleTypes,
    })
  }

  if (applicable.length === 0) {
    fail(`No active no-bypass tag ruleset applies to ${ref}`)
  }
  for (const requiredRule of ['update', 'deletion']) {
    if (!effectiveRules.has(requiredRule)) {
      fail(`Applicable tag rulesets do not enforce ${requiredRule} restriction for ${ref}`)
    }
  }

  console.log(JSON.stringify({
    tag,
    ref,
    protections: {
      update_restricted: true,
      deletion_restricted: true,
      bypass_actors: 0,
    },
    applicable_rulesets: applicable,
  }, null, 2))
}

const args = parseArgs(process.argv.slice(2))
const tag = requireArg(args, 'tag')
if (!/^agent-v\d+(?:\.\d+){1,3}$/.test(tag)) {
  fail('tag must match agent-v<numeric-version>')
}

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
  verify(payload, tag)
})
