import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root = path.resolve(import.meta.dirname, '../..')
/** Read one project source file used by the DEV-05 source-level contract tests. */
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8')

test('client portal delegates modal accessibility and state UX to shared primitives', () => {
  const portal = read('resources/js/client-portal/ClientPortalApp.tsx')
  const designSystem = read('resources/js/design-system/index.tsx')
  assert.match(portal, /<Modal\b/)
  assert.match(portal, /LoadingState/)
  assert.match(portal, /EmptyState/)
  assert.match(portal, /PageHeader/)
  assert.doesNotMatch(portal, /useFocusTrap/)
  assert.match(designSystem, /export function Modal/)
  assert.match(designSystem, /useFocusTrap\(open,dialogRef/)
  assert.match(designSystem, /aria-modal="true"/)
})

test('auth and seller surfaces share headings, loading, error and empty-state contracts', () => {
  const auth = read('resources/js/pages/auth/AuthPrimitives.tsx')
  const login = read('resources/js/pages/auth/Login.tsx')
  const register = read('resources/js/pages/auth/Register.tsx')
  const recovery = read('resources/js/pages/auth/PasswordRecovery.tsx')
  const seller = read('resources/js/pages/SellerConsole.tsx')
  const sellerShell = read('resources/js/seller/SellerPlatformApp.tsx')
  assert.match(auth, /AuthMobileBrand/)
  assert.match(auth, /AuthHeading/)
  assert.match(auth, /AuthBackButton/)
  for (const source of [login, register, recovery]) assert.match(source, /AuthHeading/)
  assert.match(seller, /LoadingState/)
  assert.match(seller, /ErrorState/)
  assert.match(seller, /EmptyState/)
  assert.match(sellerShell, /ErrorState/)
  assert.match(sellerShell, /PageHeader/)
})

test('public marketing and application error surfaces expose consistent responsive and recovery landmarks', () => {
  const marketing = read('resources/js/pages/MarketingWebsite.tsx')
  const boundary = read('resources/js/AppErrorBoundary.tsx')
  const css = read('resources/css/app.css')
  assert.match(marketing, /ui-skip-link/)
  assert.match(marketing, /id="marketing-main"/)
  assert.match(marketing, /marketing-header/)
  assert.match(css, /\.marketing-header/)
  assert.match(css, /@media\s*\(max-width:\s*900px\)/)
  assert.match(boundary, /ErrorState/)
  assert.match(boundary, /retry=/)
  assert.doesNotMatch(boundary, /#0c0c11/i)
})
