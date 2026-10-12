import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'

const workflows = [
  '.github/workflows/ci.yml',
  '.github/workflows/windows-certification.yml',
  '.github/workflows/code-quality.yml',
  '.github/workflows/seed-stress.yml',
]

const immutableActions = new Map([
  ['actions/checkout', '3d3c42e5aac5ba805825da76410c181273ba90b1'],
  ['actions/setup-node', '949feb2413d6458794dcd2491c4babbbce0c15c1'],
  ['actions/upload-artifact', 'cf430e030ddbb5b0abf93d22962f4752f3646cd9'],
  ['shivammathur/setup-php', 'eb7c497e18156a6bbabfef1d3a82760b9eda3962'],
  ['github/codeql-action/init', '24c54180a607b1449ed407dd24f251e4e9147c8d'],
  ['github/codeql-action/analyze', '24c54180a607b1449ed407dd24f251e4e9147c8d'],
])

test('normal Linux, Windows, Code Quality and manual stress workflows pin remote actions to verified immutable SHAs', () => {
  const encountered = new Set()
  for (const path of workflows) {
    const source = fs.readFileSync(path, 'utf8')
    const actionLines = Array.from(source.matchAll(/^\s*(?:-\s*)?uses:\s+([^\s#]+)(?:\s*#.*)?$/gm))
    assert.ok(actionLines.length, path + ' must actually exercise action pin verification')
    for (const [, actionRef] of actionLines) {
      const match = actionRef.match(/^([A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+(?:\/[A-Za-z0-9_.-]+)?)@([0-9a-f]{40})$/)
      assert.ok(match, path + ' uses a mutable, local, or unexpected remote action: ' + actionRef)
      const [, action, sha] = match
      assert.ok(immutableActions.has(action), path + ' unexpected action: ' + action)
      assert.equal(sha, immutableActions.get(action), path + ' incorrect verified SHA: ' + action)
      encountered.add(action)
    }
  }
  assert.deepEqual([...encountered].sort(), [...immutableActions.keys()].sort(), 'all expected action families must remain pinned and exercised')
})

test('source-only action hardening never authorizes stress workflow or changes its manual trigger', () => {
  const stress = fs.readFileSync('.github/workflows/seed-stress.yml', 'utf8')
  assert.match(stress, /^on:\s*\n\s+workflow_dispatch:/m)
  assert.doesNotMatch(stress, /^\s+pull_request:|^\s+push:/m)
  assert.match(stress, /WORKINTEL_SEED_STRESS_ALLOW_DESTRUCTIVE: '1'/)
  const ci = fs.readFileSync('.github/workflows/ci.yml', 'utf8')
  assert.match(ci, /- id: sqlite_seed/)
  assert.match(ci, /- id: sqlite_seed_idempotency/)
  assert.match(ci, /- run: php artisan test/)
})
