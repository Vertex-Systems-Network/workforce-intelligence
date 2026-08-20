import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root = path.resolve(import.meta.dirname, '../..')
/** Read one repository-relative UTF-8 source file for certification assertions. */
function read(relative) { return fs.readFileSync(path.join(root, relative), 'utf8') }

test('Block I registers Playwright certification and current release doctor', () => {
  const pkg = JSON.parse(read('package.json'))
  assert.ok(pkg.devDependencies['@playwright/test'])
  assert.ok(pkg.scripts['test:e2e:public'])
  assert.ok(pkg.scripts['test:e2e:full'])
  const doctor = read('app/Console/Commands/ProductionCertificationDoctor.php')
  assert.match(doctor, /workintel:production-doctor/)
  assert.match(doctor, /REQUIRED_TABLES/)
})

test('floating overlays stay open while scrolling and action menus are portal-backed', () => {
  const ui = read('resources/js/design-system/index.tsx')
  assert.match(ui, /window\.addEventListener\('scroll',update,true\)/)
  assert.match(ui, /ui-dropdown--portal/)
  assert.doesNotMatch(ui, /addEventListener\(['"]scroll['"][\s\S]{0,120}setOpen\(false\)/)
})

test('browser certification covers public, authenticated, RTL, table and seller journeys', () => {
  const publicSpec = read('tests/e2e/public-platform.spec.mjs')
  const authSpec = read('tests/e2e/authenticated-platform.spec.mjs')
  assert.match(publicSpec, /health\/live/)
  assert.match(publicSpec, /seller sign in/i)
  assert.match(authSpec, /mouse\.wheel/)
  assert.match(authSpec, /Actions for/)
  assert.match(authSpec, /العربية/)
  assert.match(authSpec, /Chat/)
  assert.match(authSpec, /WorkIntel Seller Platform/)
})
