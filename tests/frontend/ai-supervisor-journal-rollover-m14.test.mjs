import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'

const journal = fs.readFileSync('docs/ai-state/EXECUTION-JOURNAL.md', 'utf8')
const history = fs.readFileSync('docs/ai-state/archive/EXECUTION-JOURNAL-2026-09-23-to-2026-09-26.md', 'utf8')
const m14 = fs.readFileSync('docs/architecture/M14_RELEASE_TRUST_READINESS.md', 'utf8')

test('archived journal preserves M14 history and leaves bounded space for new checkpoints', () => {
  assert.ok(Buffer.byteLength(journal, 'utf8') < 32 * 1024)
  assert.ok(journal.includes('EXECUTION-JOURNAL-2026-09-23-to-2026-09-26.md'))
  assert.ok(history.includes('2026-09-24 — Issue #62 Gate A live verification'))
  assert.ok(history.includes('2026-09-26 — Apple/macOS release trust deferred to future scope'))
  assert.ok(!journal.includes('## 2026-09-26 —'))
  assert.ok(journal.includes('## 2026-10-11 — trusted-release YAML and compact history'))
})

test('M14 deferral clarification retains mandatory production trust and authorization boundaries', () => {
  for (const marker of [
    'Owner-approved active-scope boundary',
    '2026-10-08',
    'Issue #123',
    'Issue #62',
    '70% / WAITING_EXTERNAL',
    'must still fail closed',
    '## Definition of Done',
    '## GitHub immutable-release enforcement',
  ]) assert.ok(m14.includes(marker), 'missing M14 trust authority marker: ' + marker)
})
