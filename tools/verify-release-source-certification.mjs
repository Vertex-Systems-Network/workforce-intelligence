#!/usr/bin/env node

import fs from 'node:fs'
import process from 'node:process'

const requiredWorkflows = [
  { name: 'WorkIntel CI', path: '.github/workflows/ci.yml' },
  { name: 'WorkIntel Code Quality', path: '.github/workflows/code-quality.yml' },
  { name: 'WorkIntel Windows Certification', path: '.github/workflows/windows-certification.yml' },
]

/** Terminates release authorization with one stable, non-secret diagnostic. */
function fail(message) {
  console.error(`release-source-certification: ${message}`)
  process.exit(1)
}

/** Returns one required CLI value and fails closed when it is absent. */
function requireArg(values, name) {
  const index = values.indexOf(`--${name}`)
  if (index < 0 || !values[index + 1] || values[index + 1].startsWith('--')) {
    fail(`Missing required --${name}`)
  }
  return values[index + 1]
}

/** Reads and validates the GitHub Actions workflow-runs response from stdin. */
function readWorkflowRuns() {
  let payload
  try {
    payload = JSON.parse(fs.readFileSync(0, 'utf8'))
  } catch (error) {
    fail(`Could not parse GitHub Actions workflow-runs response: ${error.message}`)
  }
  if (!payload || !Array.isArray(payload.workflow_runs)) {
    fail('GitHub Actions response has no workflow_runs array')
  }
  return payload.workflow_runs
}

/** Converts a GitHub timestamp into a sortable numeric value without trusting missing data. */
function runTimestamp(run) {
  const value = Date.parse(run?.run_started_at || run?.created_at || '')
  return Number.isFinite(value) ? value : 0
}

/** Returns the newest exact-source push run for one required workflow identity. */
function newestMatchingRun(runs, required, sourceSha) {
  const matches = runs.filter(run => (
    run
    && run.name === required.name
    && run.path === required.path
    && run.event === 'push'
    && run.head_branch === 'main'
    && String(run.head_sha || '').toLowerCase() === sourceSha
  ))

  matches.sort((left, right) => {
    const timestampDelta = runTimestamp(right) - runTimestamp(left)
    if (timestampDelta !== 0) return timestampDelta
    return Number(right.id || 0) - Number(left.id || 0)
  })

  return matches[0] || null
}

/** Verifies that every release-critical certification passed for the exact source SHA. */
function verifyExactSourceCertification(runs, sourceSha) {
  const evidence = []

  for (const required of requiredWorkflows) {
    const run = newestMatchingRun(runs, required, sourceSha)
    if (!run) {
      fail(`Missing exact-source main push certification: ${required.name}`)
    }
    if (run.status !== 'completed' || run.conclusion !== 'success') {
      fail(`${required.name} exact-source certification is not successful (status=${run.status || 'missing'}, conclusion=${run.conclusion || 'missing'})`)
    }
    evidence.push({
      name: required.name,
      path: required.path,
      run_id: run.id,
      run_number: run.run_number,
      run_attempt: run.run_attempt,
      head_sha: sourceSha,
    })
  }

  return evidence
}

const sourceSha = requireArg(process.argv.slice(2), 'source-sha').toLowerCase()
if (!/^[0-9a-f]{40}$/.test(sourceSha)) {
  fail('source-sha must be one exact lowercase 40-hex Git commit SHA')
}

const evidence = verifyExactSourceCertification(readWorkflowRuns(), sourceSha)
process.stdout.write(`${JSON.stringify({ source_sha: sourceSha, certifications: evidence })}\n`)
