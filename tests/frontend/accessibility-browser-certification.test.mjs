import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root = path.resolve(import.meta.dirname, '../..')
/** Read one project source file for dependency-free accessibility contract assertions. */
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8')

test('shared UI primitives expose focus traps, dialog names and keyboard tab/menu semantics', () => {
  const accessibility = read('resources/js/design-system/accessibility.ts')
  const ui = read('resources/js/design-system/index.tsx')
  assert.match(accessibility, /useFocusTrap/)
  assert.match(accessibility, /returnFocusRef/)
  assert.match(ui, /aria-modal="true"/)
  assert.match(ui, /role="tablist"/)
  assert.match(ui, /role="menu" aria-label=/)
  assert.match(ui, /role="dialog" aria-label=\{resolvedAriaLabel\}/)
})

test('authenticated and public application surfaces expose skip links and main landmarks', () => {
  const cases = [
    ['resources/js/WorkforceApp.tsx', 'workintel-main'],
    ['resources/js/seller/SellerPlatformApp.tsx', 'seller-main'],
    ['resources/js/client-portal/ClientPortalApp.tsx', 'client-portal-main'],
    ['resources/js/website/WebsiteRenderer.tsx', 'website-main'],
    ['resources/js/documents/PublicDocumentSignApp.tsx', 'document-sign-main'],
  ]
  for (const [file, id] of cases) {
    const body = read(file)
    assert.match(body, /ui-skip-link/)
    assert.ok(body.includes(`id="${id}"`), `${file} should expose ${id}`)
  }
})

test('Block N CSS covers focus visibility, reduced motion, forced colors and coarse pointer targets', () => {
  const css = read('resources/css/app.css')
  for (const marker of [':focus-visible', 'prefers-reduced-motion:reduce', '@media(pointer:coarse)', '@media(forced-colors:active)', '.ui-sr-only']) assert.ok(css.includes(marker), marker)
})

test('Playwright accessibility profile covers Chrome or Chromium, Edge when installed, Firefox, reflow and touch', () => {
  const browser = read('tools/e2e-browser.mjs')
  const config = read('tools/playwright.config.mjs')
  const runner = read('tools/run-browser-certification.mjs')
  for (const marker of ['findChromeExecutable', 'findEdgeExecutable', 'findFirefoxExecutable']) assert.ok(browser.includes(marker))
  for (const marker of ['accessibilityProjects', 'firefox-desktop', 'reflow-200pct-equivalent', 'touch-mobile']) assert.ok(config.includes(marker))
  assert.match(runner, /--require-system-browsers/)
})

test('five core locales keep Block N accessibility-label parity', () => {
  const catalog = read('resources/js/i18n/locales/core.ts')
  for (const key of ['common.skip_to_content', 'common.data_table', 'common.toggle_setting', 'common.workspace_navigation', 'common.options', 'common.progress', 'common.tabs']) {
    assert.equal(catalog.split(`'${key}'`).length - 1, 5, `${key} locale parity`)
  }
})

test('default dark and light tokens keep muted text and solid primary actions on contrast-safe colors', () => {
  const css = read('resources/css/app.css')
  for (const marker of ['--text-3: #828296', '--accent: #7880f0', '--accent-solid: #5f62eb', '--text-3: #6a6e7f', '--accent-solid: #5558e8']) assert.ok(css.includes(marker), marker)
  const audit = read('tools/accessibility-source-audit.mjs')
  assert.match(audit, /ratio < 4\.5/)
  assert.match(audit, /accent-solid-hover/)
})
