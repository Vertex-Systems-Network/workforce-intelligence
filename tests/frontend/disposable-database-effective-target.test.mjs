import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import test from 'node:test'

const source = fs.readFileSync('tools/assert-disposable-database.php', 'utf8')

test('destructive clean-install guard rejects process overrides, connection URLs and cached target config', () => {
  for (const marker of [
    "'APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD', 'DB_SOCKET'",
    'process environment overrides the approved .env database target selector',
    "'DB_URL', 'DATABASE_URL'",
    'database URL override',
    "bootstrap/cache/config.php",
    'environment-specific dotenv file would override the approved .env target',
    'cached Laravel configuration',
  ]) assert.ok(source.includes(marker), 'missing guard: ' + marker)
  const runner = fs.readFileSync('verify-clean-install.cmd', 'utf8')
  assert.ok(runner.indexOf('php tools\\assert-disposable-database.php') < runner.indexOf('php artisan migrate:fresh --seed --force'))
})

test('destructive reset preflight rejects effective target ambiguity without running Artisan', () => {
  const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-disposable-guard-'))
  const dotenv = [
    'APP_ENV=local',
    'DB_CONNECTION=sqlite',
    'DB_DATABASE=database/database.sqlite',
    'DB_HOST=127.0.0.1',
    'DB_PORT=3306',
    'DB_USERNAME=demo',
    'DB_PASSWORD=',
    'DB_SOCKET=',
    'DB_URL=',
    'DATABASE_URL=',
    '',
  ].join('\n')
  const root = path.join(temp, 'repo')
  try {
    fs.mkdirSync(path.join(root, 'tools'), { recursive: true })
    fs.writeFileSync(path.join(root, '.env'), dotenv)
    fs.copyFileSync('tools/assert-disposable-database.php', path.join(root, 'tools', 'assert-disposable-database.php'))
    const baseline = {
      ...process.env,
      APP_ENV: 'local',
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: 'database/database.sqlite',
      DB_HOST: '127.0.0.1',
      DB_PORT: '3306',
      DB_USERNAME: 'demo',
      DB_PASSWORD: '',
      DB_SOCKET: '',
      DB_URL: '',
      DATABASE_URL: '',
      WORKINTEL_ALLOW_DESTRUCTIVE_RESET: '',
    }
    const run = env => spawnSync('php', ['tools/assert-disposable-database.php'], {
      cwd: root, env, encoding: 'utf8', timeout: 5000,
    })
    const good = run(baseline)
    assert.equal(good.status, 0, good.stderr || String(good.error))
    assert.match(good.stdout, /Disposable database guard passed/)

    const invalid = [
      { label: 'production environment', env: { ...baseline, APP_ENV: 'production' } },
      { label: 'database driver', env: { ...baseline, DB_CONNECTION: 'mysql' } },
      { label: 'database name', env: { ...baseline, DB_DATABASE: 'real_customer_db' } },
      { label: 'database host', env: { ...baseline, DB_HOST: 'prod.example.invalid' } },
      { label: 'database port', env: { ...baseline, DB_PORT: '4406' } },
      { label: 'database user', env: { ...baseline, DB_USERNAME: 'prod-operator' } },
      { label: 'database password', env: { ...baseline, DB_PASSWORD: 'secret_prod_password' } },
      { label: 'database socket', env: { ...baseline, DB_SOCKET: '/tmp/production.sock' } },
      { label: 'empty env override', env: { ...baseline, DB_CONNECTION: '' } },
      { label: 'Laravel URL', env: { ...baseline, DB_URL: 'mysql://secret@prod.example.invalid/workforce' } },
      { label: 'second database URL', env: { ...baseline, DATABASE_URL: 'mysql://secret@prod.example.invalid/workforce' } },
      { label: 'override cannot bypass target guard', env: { ...baseline, DB_CONNECTION: 'mysql', WORKINTEL_ALLOW_DESTRUCTIVE_RESET: '1' } },
    ]
    for (const row of invalid) {
      const result = run(row.env)
      assert.equal(result.status, 1, row.label + ': ' + (result.stderr || String(result.error)))
      assert.match(result.stderr, /REFUSED:/, row.label)
      assert.doesNotMatch(result.stderr + result.stdout, /secret@prod|secret_prod_password/, 'must not log database credentials')
    }
    fs.writeFileSync(path.join(root, '.env'), dotenv.replace('DB_URL=\n', 'DB_URL=mysql://secret@prod.example.invalid/workforce\n'))
    const urlDotenv = run(baseline)
    assert.equal(urlDotenv.status, 1, urlDotenv.stderr)
    assert.match(urlDotenv.stderr, /database URL override/)
    assert.doesNotMatch(urlDotenv.stderr, /secret@prod/)
    fs.writeFileSync(path.join(root, '.env'), dotenv)
    fs.writeFileSync(path.join(root, '.env.local'), 'DB_CONNECTION=mysql\\nDB_HOST=prod.example.invalid\\n')
    const alternate = run(baseline)
    assert.equal(alternate.status, 1, alternate.stderr)
    assert.match(alternate.stderr, /environment-specific dotenv file would override/)
    fs.rmSync(path.join(root, '.env.local'))
    fs.mkdirSync(path.join(root, 'bootstrap', 'cache'), { recursive: true })
    fs.writeFileSync(path.join(root, 'bootstrap', 'cache', 'config.php'), '<?php return [];')
    const cached = run(baseline)
    assert.equal(cached.status, 1, cached.stderr)
    assert.match(cached.stderr, /cached Laravel configuration/)
  } finally {
    fs.rmSync(temp, { recursive: true, force: true })
  }
})
