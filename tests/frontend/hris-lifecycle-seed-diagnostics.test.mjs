import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import test from 'node:test'

const script = 'tools/hris-lifecycle-seed-state.php'
const source = fs.readFileSync(script, 'utf8')
const linux = fs.readFileSync('.github/workflows/ci.yml', 'utf8')
const windows = fs.readFileSync('.github/workflows/windows-certification.yml', 'utf8')
const hasPhp = spawnSync('php', ['-v'], { encoding: 'utf8' }).status === 0
const hasSqlite = hasPhp && spawnSync('php', ['-r', "echo extension_loaded('pdo_sqlite') ? 'yes' : 'no';"], { encoding: 'utf8' }).stdout === 'yes'

function probe(db) {
  const result = spawnSync('php', [script], {
    encoding: 'utf8', timeout: 5000, cwd: process.cwd(),
    env: { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: db },
  })
  assert.equal(result.stderr.trim(), '', result.stderr)
  return { status: result.status, data: JSON.parse(result.stdout) }
}

test('Issue 178 HRIS probe is read-only and cannot leak raw SQL exceptions', () => {
  for (const fragment of [
    'PRAGMA foreign_key_list(lifecycle_checklist_template_items)',
    'PRAGMA foreign_key_check(lifecycle_checklist_template_items)',
    'PRAGMA query_only = ON',
    "'probe_connection_foreign_keys_enabled'",
    "'probe_connection_query_only'",
    "'probe_connection_scope'",
    "'standard_onboarding'",
    "'template_ids'",
    "'item_parent_counts'",
    "'stage' => $stage",
    'catch (Throwable $exception)',
  ]) assert.ok(source.includes(fragment), 'missing diagnostic: ' + fragment)
  assert.equal(source.includes('$exception->getMessage()'), false)
  assert.equal(source.includes('INSERT INTO'), false)
  assert.equal(source.includes('DELETE FROM'), false)
  for (const [name, workflow, filename] of [
    ['linux', linux, 'workintel-ci-hris-seed-failure.json'],
    ['windows', windows, 'workintel-windows-hris-seed-failure.json'],
  ]) {
    assert.ok(workflow.includes('php tools/hris-lifecycle-seed-state.php'), name)
    assert.ok(workflow.includes(filename), name)
    assert.ok(workflow.includes("if: failure() && steps.sqlite_seed.outcome == 'failure'"), name)
    assert.ok(workflow.includes('php artisan migrate:fresh --seed --force'), name)
  }
})

test('HRIS probe preserves parent and child FK evidence without mutation', { skip: !hasSqlite }, () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-hris-probe-'))
  try {
    const db = path.join(dir, 'fixture.sqlite')
    const sql = [
      'PRAGMA foreign_keys = ON',
      'CREATE TABLE lifecycle_checklist_templates (id INTEGER PRIMARY KEY, workspace_id INTEGER, type TEXT, name TEXT)',
      'CREATE TABLE lifecycle_checklist_template_items (id INTEGER PRIMARY KEY, template_id INTEGER REFERENCES lifecycle_checklist_templates(id))',
      "INSERT INTO lifecycle_checklist_templates VALUES (3, 1, 'onboarding', 'Standard Onboarding')",
      'INSERT INTO lifecycle_checklist_template_items VALUES (1, 3)',
    ].join('; ')
    const created = spawnSync('php', ['-r', "$p = new PDO('sqlite:'.$argv[1]); $p->exec($argv[2]);", db, sql], { encoding: 'utf8' })
    assert.equal(created.status, 0, created.stderr)
    const before = fs.readFileSync(db)
    const result = probe(db)
    assert.equal(result.status, 0)
    assert.deepEqual(result.data.template_ids, [3])
    assert.equal(Number(result.data.standard_onboarding[0].id), 3)
    assert.equal(Number(result.data.item_parent_counts[0].row_count), 1)
    assert.equal(result.data.item_foreign_keys[0].table, 'lifecycle_checklist_templates')
    assert.deepEqual(result.data.foreign_key_violations, [])
    assert.equal(result.data.probe_connection_query_only, 1)
    assert.ok([0, 1].includes(result.data.probe_connection_foreign_keys_enabled))
    assert.match(result.data.probe_connection_scope, /Separate post-failure PDO connection/)
    assert.equal(Object.hasOwn(result.data, 'foreign_keys_enabled'), false)
    assert.deepEqual(fs.readFileSync(db), before)
  } finally { fs.rmSync(dir, { recursive: true, force: true }) }
})

test('corrupt SQLite yields a structured JSON failure instead of an uncaught exception', { skip: !hasPhp }, () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-hris-corrupt-'))
  try {
    const db = path.join(dir, 'corrupt.sqlite')
    fs.writeFileSync(db, 'not a sqlite database')
    const result = probe(db)
    assert.equal(result.status, 2)
    assert.equal(result.data.error, 'HRIS lifecycle state probe could not complete.')
    assert.ok(result.data.probe_failure.stage)
    assert.equal(fs.readFileSync(db, 'utf8'), 'not a sqlite database')
  } finally { fs.rmSync(dir, { recursive: true, force: true }) }
})
