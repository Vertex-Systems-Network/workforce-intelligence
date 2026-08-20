import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root = path.resolve(import.meta.dirname, '../..')
/** Read one repository-relative source file for Block J runtime-closure assertions. */
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8')

test('Block J runtime closure captures reports and protects destructive verification', () => {
  const runner = read('tools/run-runtime-closure.ps1')
  const clean = read('verify-clean-install.cmd')
  const guard = read('tools/assert-disposable-database.php')
  assert.match(runner, /runtime-closure/)
  assert.match(runner, /Tee-Object/)
  assert.match(runner, /Protect-LogLine/)
  assert.match(runner, /REDACTED/)
  assert.match(clean, /assert-disposable-database\.php/)
  assert.match(clean, /WORKINTEL_RESET_CONFIRM/)
  assert.match(guard, /APP_ENV/)
  assert.match(guard, /production/)
})

test('Block J locks historical runtime regressions for stateless registration and gold payroll entitlement', () => {
  const auth = read('app/Http/Controllers/Api/V1/AuthController.php')
  const plans = read('app/Support/PlanCatalog.php')
  assert.match(auth, /Auth::login\(\$user\);[\s\S]{0,160}\$request->hasSession\(\)/)
  assert.match(plans, /'gold'[\s\S]{0,1800}'feature\.payroll'\s*=>\s*true/)
})

test('Block J preflight reports CLI ini PDO test driver and deterministic npm lock state', () => {
  const preflight = read('tools/runtime-closure-preflight.php')
  assert.match(preflight, /php_ini_loaded_file/)
  assert.match(preflight, /phpunit_sqlite/)
  assert.match(preflight, /package-lock\.json/)
  assert.match(preflight, /20\.19\.0/)
  assert.match(preflight, /22\.12\.0/)
})

test('Block J allows generated npm lockfiles on repeat certification runs', () => {
  const integrity = read('tools/audit-source-integrity.mjs')
  assert.match(integrity, /'package-lock\.json'/)
})
