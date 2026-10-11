import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'

const criticalPath = fs.readFileSync('docs/operations/M14_REMAINING_CRITICAL_PATH_AUDIT.md', 'utf8')
const architecture = fs.readFileSync('docs/architecture/M14_RELEASE_TRUST_READINESS.md', 'utf8')

test('Issue 162 current owner deferral supersedes historical Windows provider execution guidance', () => {
  const current = criticalPath.indexOf('## Current owner authority override — 2026-10-11')
  const historic = criticalPath.indexOf('## Executive finding')
  assert.ok(current > 0 && current < historic, 'current scope override must precede historical instructions')
  for (const marker of [
    '2026-09-26',
    '2026-10-08',
    'SUPERSEDED as an active instruction',
    'DEFERRED / NOT CURRENTLY AUTHORIZED',
    'CI, source-quality, dependencies, diagnostics',
    'future conditional dependency map only',
    '70% WAITING_EXTERNAL',
    'Issue #62',
    'Issue #123',
  ]) assert.ok(criticalPath.includes(marker), 'missing current authority override: ' + marker)
})

test('historical release gates and fail-closed production trust remain intact', () => {
  for (const marker of ['### Gate R1', '### Gate R2', '### Gate R3-W', '### Gate R5', '### Gate R6', '### Gate R7', '## Fastest safe execution order']) {
    assert.ok(criticalPath.includes(marker), 'lost historical future gate: ' + marker)
  }
  for (const marker of ['Owner-approved active-scope boundary', '## Definition of Done', '## GitHub immutable-release enforcement']) {
    assert.ok(architecture.includes(marker), 'lost authoritative trust boundary: ' + marker)
  }
  assert.ok(criticalPath.includes('do not infer readiness or dispatch privileged release operations'))
})
