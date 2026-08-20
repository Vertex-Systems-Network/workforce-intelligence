import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'

/** Read one repository source file for DEV-04 ownership assertions. */
const read = file => fs.readFileSync(file, 'utf8')

test('DEV-04 retires the legacy tenant Platform page while preserving the dedicated seller shell', () => {
  const sidebar = read('resources/js/components/Sidebar.tsx')
  const catalog = read('resources/js/moduleCatalog.ts')
  const shell = read('resources/js/WorkforceApp.tsx')
  const app = read('resources/js/app.tsx')
  const topbar = read('resources/js/components/TopBar.tsx')
  assert.ok(!fs.existsSync('resources/js/pages/Platform.tsx'))
  assert.doesNotMatch(sidebar, /\| 'platform'/)
  assert.doesNotMatch(catalog, /platform:\{page:'platform'/)
  assert.doesNotMatch(shell, /pages\/Platform|case 'platform'|next === 'platform'|requested\.page !== 'platform'/)
  assert.match(app, /path === '\/seller'/)
  assert.match(app, /<SellerPlatformApp/)
  assert.match(topbar, /window\.location\.assign\('\/seller'\)/)
})

test('workspace platform APIs remain available as backend compatibility contracts', () => {
  const routes = read('routes/platform.php')
  assert.match(routes, /api\/v1|Route::prefix\('v1'\)/)
  assert.match(routes, /platform\/overview/)
  assert.match(routes, /platform\/branding/)
  assert.match(routes, /platform\/partners/)
})
