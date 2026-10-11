import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import test from 'node:test'

const script = 'tools/access-control-seed-state.php'
const source = fs.readFileSync(script, 'utf8')
const hasPhp = spawnSync('php', ['-v'], { encoding: 'utf8' }).status === 0
const hasSqlite = hasPhp && spawnSync('php', ['-r', "echo extension_loaded('pdo_sqlite') ? 'yes' : 'no';"], { encoding: 'utf8' }).stdout === 'yes'

const runProbe = (db, assertMode) => {
  const result = spawnSync('php', [script, ...(assertMode ? ['--assert'] : [])], {
    cwd: process.cwd(),
    encoding: 'utf8',
    env: { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: db },
    timeout: 5000,
  })
  assert.equal(result.stderr.trim(), '', result.stderr)
  return { status: result.status, payload: JSON.parse(result.stdout) }
}

test('Issue 70 collector preserves a JSON-only error boundary and absolute Windows paths', () => {
  for (const needle of [
    "stage' => $probeStage",
    "'kind' => $exception instanceof PDOException",
    "'code' => (string) $exception->getCode()",
    "exit(2);",
    '$isWindowsAbsolute',
    "in_array($dbPath[2], ['\\\\', '/'], true)",
  ]) assert.ok(source.includes(needle), 'missing diagnostic contract: ' + needle)
  assert.ok(source.includes('catch (Throwable $exception)'))
  assert.equal(source.includes('$exception->getMessage()'), false, 'never leak PDO messages into artifacts')
})

test('corrupt SQLite evidence remains parseable and fail-closed in capture and assertion modes', { skip: !hasPhp }, () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-sqlite-corrupt-'))
  try {
    const db = path.join(dir, 'corrupt.sqlite')
    fs.writeFileSync(db, 'This file is intentionally not a SQLite database.')
    for (const assertMode of [false, true]) {
      const result = runProbe(db, assertMode)
      assert.equal(result.status, 2)
      assert.equal(result.payload.database, db)
      assert.equal(result.payload.error, 'SQLite state probe could not complete.')
      assert.ok(result.payload.probe_failure?.stage, 'failure stage required for triage')
      assert.ok(['pdo_exception', 'runtime_exception'].includes(result.payload.probe_failure?.kind))
      assert.equal(fs.readFileSync(db, 'utf8'), 'This file is intentionally not a SQLite database.')
    }
  } finally {
    fs.rmSync(dir, { recursive: true, force: true })
  }
})

test('partially migrated schema returns one JSON diagnostic instead of an uncaught SQL exception', { skip: !hasSqlite }, () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-sqlite-partial-'))
  try {
    const db = path.join(dir, 'partial.sqlite')
    const php = `$pdo = new PDO('sqlite:'.$argv[1]); $pdo->exec("CREATE TABLE workspaces (id INTEGER PRIMARY KEY, slug TEXT)"); $pdo->exec("INSERT INTO workspaces(slug) VALUES ('acme-corp')");`
    const seed = spawnSync('php', ['-r', php, db], { encoding: 'utf8', timeout: 5000 })
    assert.equal(seed.status, 0, seed.stderr)
    for (const assertMode of [false, true]) {
      const result = runProbe(db, assertMode)
      assert.equal(result.status, 2)
      assert.equal(result.payload.error, 'SQLite state probe could not complete.')
      assert.equal(result.payload.probe_failure?.stage, 'workspace_query')
      assert.equal(result.payload.probe_failure?.kind, 'pdo_exception')
      assert.ok(result.payload.sqlite.integrity_check.includes('ok'))
      assert.equal(result.payload.database, db)
    }
  } finally {
    fs.rmSync(dir, { recursive: true, force: true })
  }
})

test('valid but unseeded SQLite remains a normal JSON assertion failure, never a false pass', { skip: !hasSqlite }, () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-sqlite-unseeded-'))
  try {
    const db = path.join(dir, 'unseeded.sqlite')
    const create = spawnSync('php', ['-r', "new PDO('sqlite:'.$argv[1]);", db], { encoding: 'utf8', timeout: 5000 })
    assert.equal(create.status, 0, create.stderr)
    const capture = runProbe(db, false)
    assert.equal(capture.status, 0)
    assert.equal(capture.payload.error, undefined)
    const asserted = runProbe(db, true)
    assert.equal(asserted.status, 3)
    assert.equal(asserted.payload.assertions.workspace_exists, false)
    assert.equal(asserted.payload.assertions.owner_has_owner_role, false)
  } finally {
    fs.rmSync(dir, { recursive: true, force: true })
  }
})
