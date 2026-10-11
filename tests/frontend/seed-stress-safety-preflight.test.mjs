import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import test from 'node:test'

const read = file => fs.readFileSync(file, 'utf8')
const stress = read('tools/access-control-seed-stress.sh')
const workflow = read('.github/workflows/seed-stress.yml')

test('RB-005 remains manual only, while source preflight pins a disposable SQLite/testing target', () => {
  assert.ok(workflow.includes('workflow_dispatch:'))
  assert.ok(workflow.includes('path: storage/logs/seed-stress'))
  assert.equal(workflow.includes('path: storage/framework/testing'), false, 'never upload the scratch database with failure logs')
  assert.ok(stress.includes('db_dir="storage/framework/testing"'), 'database must be stored separately from uploaded logs')
  assert.equal(workflow.includes('pull_request:'), false)
  assert.equal(workflow.includes('push:'), false)
  for (const marker of [
    'APP_ENV: testing',
    'DB_CONNECTION: sqlite',
    'DB_DATABASE: storage/framework/testing/seed-stress.sqlite',
    "WORKINTEL_SEED_STRESS_ALLOW_DESTRUCTIVE: '1'",
    'WORKINTEL_SEED_STRESS_CYCLES: 12',
  ]) assert.ok(workflow.includes(marker), 'missing manual workflow isolation: ' + marker)
  for (const marker of [
    'explicit local opt-in is required',
    'APP_ENV must equal testing',
    'DB_CONNECTION must equal sqlite',
    'database URL overrides are forbidden',
    "DB_URL=''",
    "DATABASE_URL=''",
    'export DB_CONNECTION DB_DATABASE APP_ENV DB_URL DATABASE_URL',
    'DB_DATABASE must be the dedicated scratch path',
    'cached Laravel configuration can override',
    'symlinked diagnostic path component is forbidden',
    'dedicated scratch database already exists; refusing overwrite',
    'cycle count cannot exceed 12',
    'php artisan migrate:fresh --seed --force',
    'cycle-${cycle}-failure-state.json',
    'php tools/access-control-seed-state.php >"$failure_state_file" || true',
  ]) assert.ok(stress.includes(marker), 'missing fail-closed contract: ' + marker)
  assert.ok(stress.indexOf('[[ ! -e "$safe_db" ]]') < stress.indexOf('mkdir -p "$log_dir"'))
  assert.ok(stress.indexOf('mkdir -p "$log_dir"') < stress.indexOf(': > "$db_path"'))
  assert.equal(/\bretry\b/i.test(stress), false)
})

test('unsafe database targets are rejected without touching an existing sentinel file', { skip: process.platform === 'win32' }, () => {
  const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-seed-safety-'))
  try {
    const sentinel = path.join(temp, 'production.sqlite')
    const contents = 'PRESERVE_THIS_FILE\n'
    fs.writeFileSync(sentinel, contents)
    const baseEnv = {
      ...process.env,
      APP_ENV: 'testing',
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: sentinel,
      DB_URL: '',
      DATABASE_URL: '',
      WORKINTEL_SEED_STRESS_ALLOW_DESTRUCTIVE: '1',
      WORKINTEL_SEED_STRESS_CYCLES: '12',
    }
    const unsafe = [
      baseEnv,
      {...baseEnv, DB_DATABASE: 'storage/framework/testing/seed-stress.sqlite', DB_CONNECTION: 'mysql'},
      {...baseEnv, DB_DATABASE: 'storage/framework/testing/seed-stress.sqlite', APP_ENV: 'production'},
      {...baseEnv, DB_DATABASE: 'storage/framework/testing/seed-stress.sqlite', DB_URL: 'mysql://example.invalid/test'},
      {...baseEnv, DB_DATABASE: 'storage/framework/testing/seed-stress.sqlite', WORKINTEL_SEED_STRESS_ALLOW_DESTRUCTIVE: '0'},
      {...baseEnv, DB_DATABASE: 'storage/framework/testing/seed-stress.sqlite', WORKINTEL_SEED_STRESS_CYCLES: '500'},
    ]
    for (const env of unsafe) {
      const run = spawnSync('bash', ['tools/access-control-seed-stress.sh'], { cwd: process.cwd(), env, encoding: 'utf8', timeout: 5000 })
      assert.equal(run.status, 2, run.stderr || String(run.error))
      assert.match(run.stderr, /Refused unsafe diagnostic target/)
      assert.equal(fs.readFileSync(sentinel, 'utf8'), contents, 'preflight must never mutate supplied target')
    }
  } finally {
    fs.rmSync(temp, {recursive: true, force: true})
  }
})


test('pre-existing isolated scratch database is never overwritten, even with otherwise valid opt-in', { skip: process.platform === 'win32' }, () => {
  const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-seed-existing-'))
  try {
    const toolsDir = path.join(temp, 'tools')
    const dbDir = path.join(temp, 'storage', 'framework', 'testing')
    fs.mkdirSync(toolsDir, {recursive: true})
    fs.mkdirSync(dbDir, {recursive: true})
    fs.copyFileSync('tools/access-control-seed-stress.sh', path.join(toolsDir, 'access-control-seed-stress.sh'))
    fs.writeFileSync(path.join(temp, 'artisan'), 'placeholder; this must never execute')
    fs.writeFileSync(path.join(toolsDir, 'access-control-seed-state.php'), 'placeholder; this must never execute')
    const db = path.join(dbDir, 'seed-stress.sqlite')
    const contents = 'PRESERVE_EXISTING_SCRATCH_DB\\n'
    fs.writeFileSync(db, contents)
    const env = {
      ...process.env,
      APP_ENV: 'testing',
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: 'storage/framework/testing/seed-stress.sqlite',
      DB_URL: '',
      DATABASE_URL: '',
      WORKINTEL_SEED_STRESS_ALLOW_DESTRUCTIVE: '1',
      WORKINTEL_SEED_STRESS_CYCLES: '12',
    }
    const run = spawnSync('bash', ['tools/access-control-seed-stress.sh'], { cwd: temp, env, encoding: 'utf8', timeout: 5000 })
    assert.equal(run.status, 2, run.stderr || String(run.error))
    assert.match(run.stderr, /dedicated scratch database already exists; refusing overwrite/)
    assert.equal(fs.readFileSync(db, 'utf8'), contents)
    assert.equal(fs.existsSync(path.join(temp, 'storage', 'logs', 'seed-stress')), false, 'denial must precede log creation')
  } finally {
    fs.rmSync(temp, {recursive: true, force: true})
  }
})
