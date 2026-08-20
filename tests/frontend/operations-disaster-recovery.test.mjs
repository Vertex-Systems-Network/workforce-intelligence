import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'

/** Read one repository file relative to the frontend contract test. */
const read = path => fs.readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8')

test('Block K seller operations surface exposes verified backup and restore controls', () => {
  const seller = read('resources/js/pages/SellerConsole.tsx')
  assert.match(seller, /Operations & Recovery/)
  assert.match(seller, /BACKUP NOW/)
  assert.match(seller, /PRUNE BACKUPS/)
  assert.match(seller, /prepareOpsRestore/)
  assert.match(seller, /Verified restore points/)
})

test('Block K backup service uses hash-only restore authorization and streaming checksums', () => {
  const service = read('app/Services/Operations/SystemOperationsService.php')
  assert.match(service, /hash\('sha256',\$raw\)/)
  assert.match(service, /readStream/)
  assert.match(service, /minimum_verified_copies/)
  assert.match(service, /WORKINTEL_ALLOW_DISASTER_RESTORE|restoreRequestForToken/)
})
