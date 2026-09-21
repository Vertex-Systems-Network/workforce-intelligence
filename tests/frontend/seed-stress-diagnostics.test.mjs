import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root = process.cwd()
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8')

const workflow = read('.github/workflows/seed-stress.yml')
const stress = read('tools/access-control-seed-stress.sh')
const state = read('tools/access-control-seed-state.php')
const registry = JSON.parse(read('benchmarks/runner/registry.json'))

test('Issue 70 seed stress lane remains diagnostic and deferred to final runner certification', () => {
  assert.ok(workflow.includes('WORKINTEL_SEED_STRESS_CYCLES: 12'))
  assert.ok(workflow.includes('bash tools/access-control-seed-stress.sh'))
  assert.equal(stress.includes('retry'), false)
  assert.ok(stress.includes('cycle-${cycle}-failure-state.json'))
  assert.ok(stress.includes('php artisan migrate:fresh --seed --force'))
  assert.ok(stress.includes('php tools/access-control-seed-state.php >"$failure_state_file" || true'))

  for (const marker of [
    'PRAGMA integrity_check',
    'PRAGMA foreign_key_check',
    'sqlite_sequence',
    'mr.is_primary',
    'mr.assigned_by',
    'workspace_owner_matches_demo_owner',
    'coordinator_is_not_workspace_owner',
    'owner_has_owner_role',
    'coordinator_has_project_coordinator_role',
    'sqlite_integrity_ok',
    'sqlite_foreign_keys_ok',
  ]) assert.ok(state.includes(marker), `missing seed diagnostic marker: ${marker}`)

  const benchmark = registry.entries.find(entry => entry.id === 'RB-005')
  assert.ok(benchmark)
  assert.equal(benchmark.execution_phase, 'final-runner-batch')
  assert.equal(benchmark.status, 'ready')
  assert.equal(benchmark.stale_when_head_moves, true)
  assert.ok(benchmark.commands.some(command => command.includes('seed-stress.yml')))
})
