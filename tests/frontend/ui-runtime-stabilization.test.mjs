import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(import.meta.dirname, '../..')
/** Read one project source file as UTF-8 for dependency-free UI contract assertions. */
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8')

test('Composer installation prepares Laravel runtime directories before package manifest discovery', () => {
  const composer = JSON.parse(read('composer.json'))
  const scripts = composer.scripts['post-autoload-dump']
  assert.equal(scripts[0], '@php tools/prepare-runtime.php')
  assert.ok(scripts.includes('@php tools/discover-packages.php'))
  assert.equal(scripts.some(item => item.includes('artisan package:discover')), false)
})

test('single selects and file inputs use the shared professional controls', () => {
  const toolkit = read('resources/js/design-system/index.tsx')
  assert.match(toolkit, /ui-select-menu/)
  assert.match(toolkit, /role="listbox"/)
  assert.match(toolkit, /ui-file-input__action/)
  assert.match(toolkit, /Browse/)
})

test('dashboard widget visibility and layout are persisted in the per-user page customization record', () => {
  const dashboard = read('resources/js/components/DashboardGrid.tsx')
  const customization = read('resources/js/design-system/PageCustomization.tsx')
  assert.match(dashboard, /visible_widgets/)
  assert.match(dashboard, /widget_layout/)
  assert.match(dashboard, /disableDrag: !editing/)
  assert.match(dashboard, /Manage dashboard widgets/)
  assert.match(customization, /\/api\/v1\/ui\/preferences\//)
})

test('errors and notifications use a dismissible auto-expiring toast viewport', () => {
  const toast = read('resources/js/design-system/toast.tsx')
  const api = read('resources/js/api/client.ts')
  assert.match(toast, /setTimeout/)
  assert.match(toast, /Dismiss notification/)
  assert.match(toast, /ui-toast__timer/)
  assert.match(api, /emitToast/)
})

test('motion system includes modal drawer page and reduced-motion behavior', () => {
  const css = read('resources/js/design-system/toolkit.css')
  assert.match(css, /wi-modal-in/)
  assert.match(css, /wi-drawer-in/)
  assert.match(css, /wi-page-enter/)
  assert.match(css, /prefers-reduced-motion/)
})

test('floating selects and action menus stay portal-mounted and reposition during scroll instead of closing', () => {
  const toolkit = read('resources/js/design-system/index.tsx')
  assert.match(toolkit, /createPortal/)
  assert.match(toolkit, /usePortalPosition/)
  assert.match(toolkit, /window\.addEventListener\('scroll'\s*,\s*(reposition|update)\s*,\s*true\)/)
  assert.doesNotMatch(toolkit, /closeOnScroll\s*=\s*\(\)\s*=>\s*setOpen\(false\)/)
  assert.match(toolkit, /ui-dropdown--portal/)
})

test('foundation exposes standardized responsive form spacing refresh feedback and automatic icon tooltips', () => {
  const toolkit = read('resources/js/design-system/index.tsx')
  const css = read('resources/js/design-system/toolkit.css')
  for (const token of ['FormSection', 'FormGrid', 'FormActions', 'RefreshButton']) assert.ok(toolkit.includes(token))
  assert.ok(toolkit.includes('tooltip ?? label'))
  assert.match(css, /ui-form-grid--2/)
  assert.match(css, /ui-form-actions--end/)
})

test('DataGrid V2 foundation provides TanStack sorting pagination visibility and People migration', () => {
  const toolkit = read('resources/js/design-system/index.tsx')
  const people = read('resources/js/pages/People.tsx')
  for (const token of ['DataGrid', 'useReactTable', 'pageSizeOptions', "t('common.columns')", "t('common.showing'", "t('common.saved_views')"]) assert.ok(toolkit.includes(token))
  assert.match(people, /<DataGrid rows=\{filtered\}/)
  assert.match(people, /defaultSort=\{\{\s*id:\s*'employee'\s*,\s*direction:\s*'asc'\s*\}\}/)
})
