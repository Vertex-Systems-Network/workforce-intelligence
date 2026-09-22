import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root = process.cwd()
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8')

const workflow = read('.github/workflows/seed-stress.yml')
const ci = read('.github/workflows/ci.yml')
const windows = read('.github/workflows/windows-certification.yml')
const stress = read('tools/access-control-seed-stress.sh')
const state = read('tools/access-control-seed-state.php')
const accessSeeder = read('database/seeders/AccessControlSeeder.php')
const registry = JSON.parse(read('benchmarks/runner/registry.json'))

test('Issue 70 seed stress lane remains fail-fast and manually gated by Runner authority', () => {
  assert.ok(workflow.includes('workflow_dispatch:'))
  assert.equal(workflow.includes('pull_request:'), false)
  assert.ok(workflow.includes('WORKINTEL_SEED_STRESS_CYCLES: 12'))
  assert.ok(workflow.includes('bash tools/access-control-seed-stress.sh'))
  assert.equal(stress.includes('retry'), false)
  assert.ok(stress.includes('cycle-${cycle}-failure-state.json'))
  assert.ok(stress.includes('php artisan migrate:fresh --seed --force'))
  assert.ok(stress.includes('php tools/access-control-seed-state.php >"$failure_state_file" || true'))

  for (const marker of [
    'PRAGMA integrity_check',
    'PRAGMA foreign_key_check',
    "'php_version' => PHP_VERSION",
    'PDO::ATTR_CLIENT_VERSION',
    'SELECT sqlite_version()',
    'SELECT sqlite_source_id()',
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

  for (const marker of [
    'coordinator_model_id',
    'coordinator_persisted_id',
    'member_model_user_id',
    'member_persisted_user_id',
    'AccessControlSeeder identity precondition failed:',
  ]) assert.ok(accessSeeder.includes(marker), `missing in-process seed identity marker: ${marker}`)

  const benchmark = registry.entries.find(entry => entry.id === 'RB-005')
  assert.ok(benchmark)
  assert.equal(benchmark.execution_policy, 'immediate')
  assert.equal(benchmark.immediate_reason, 'incident-recovery-and-data-safety')
  assert.equal(benchmark.authorization.state, 'not-authorized')
  assert.equal(benchmark.definition_status, 'blocked')
  assert.equal(benchmark.security_critical, true)
  assert.equal(benchmark.result_recording.mode, 'external-exact-head-envelope')
  assert.ok(benchmark.commands.some(command => command.includes('seed-stress.yml')))
})


test('normal CI captures Issue 70 seed state without retrying or weakening the owner invariant', () => {
  for (const [name, source, artifact] of [
    ['linux', ci, 'workintel-ci-seed-failure.json'],
    ['windows', windows, 'workintel-windows-seed-failure.json'],
  ]) {
    assert.ok(source.includes('id: sqlite_seed'), `${name} seed step must expose an outcome id`)
    assert.ok(source.includes('php artisan migrate:fresh --seed --force'), `${name} must keep the real seed command`)
    assert.ok(source.includes('php tools/access-control-seed-state.php'), `${name} must capture access-control state`)
    assert.ok(source.includes(artifact), `${name} must retain a dedicated JSON artifact`)
    assert.ok(source.includes("if: failure() && steps.sqlite_seed.outcome == 'failure'"), `${name} upload must be failure-only`)
  }

  const linuxSeedBlock = ci.slice(ci.indexOf('- id: sqlite_seed'), ci.indexOf('- run: php artisan test'))
  const windowsSeedBlock = windows.slice(windows.indexOf('- id: sqlite_seed'), windows.indexOf('- name: Full PHPUnit suite'))
  assert.equal(/\bretry\b/i.test(linuxSeedBlock), false)
  assert.equal(/\bretry\b/i.test(windowsSeedBlock), false)

  for (const [name, source, artifact] of [
    ['linux idempotency', ci, 'workintel-ci-idempotency-seed-failure.json'],
    ['windows idempotency', windows, 'workintel-windows-idempotency-seed-failure.json'],
  ]) {
    assert.ok(source.includes('id: sqlite_seed_idempotency'), `${name} seed step must expose an outcome id`)
    assert.ok(source.includes('php artisan db:seed --force'), `${name} must keep the real idempotency seed command`)
    assert.ok(source.includes(artifact), `${name} must retain a dedicated JSON artifact`)
    assert.ok(source.includes("if: failure() && steps.sqlite_seed_idempotency.outcome == 'failure'"), `${name} upload must be failure-only`)
  }
})
