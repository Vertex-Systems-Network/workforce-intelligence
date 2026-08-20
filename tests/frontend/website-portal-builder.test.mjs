import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'
import { readSource } from './source-bundles.mjs'

const root=path.resolve(import.meta.dirname,'../..')
/** Reads one project source file for dependency-free Website Studio contracts. */
function read(relative){return readSource(relative)}

test('website studio uses dnd-kit ordered sections and the same renderer as public delivery',()=>{
  const studio=read('resources/js/pages/WebsiteStudio.tsx')
  assert.match(studio,/DndContext/)
  assert.match(studio,/SortableContext/)
  assert.match(studio,/WebsiteRenderer/)
  assert.doesNotMatch(studio,/GridStack/)
  assert.match(studio,/Save as reusable component/)
  assert.match(studio,/Archive page/)
})

test('editor website preview cannot submit public forms or navigate live links',()=>{
  const renderer=read('resources/js/website/WebsiteRenderer.tsx')
  const css=read('resources/js/website/website-renderer.css')
  assert.match(renderer,/if\(preview\)return/)
  assert.match(renderer,/disabled=\{preview/)
  assert.match(renderer,/preview=\{preview\}/)
  assert.match(css,/\.wi-site\.is-preview/)
  assert.match(css,/pointer-events:none/)
})

test('website route is module and plan gated but public delivery is outside tenant auth',()=>{
  const routes=read('routes/website.php')
  const access=read('resources/js/access.ts')
  const manifest=read('resources/js/navigation.manifest.json')
  for(const token of ['workspace.module:website','feature.website_builder','website.forms_manage','website.submissions_view']) assert.ok(routes.includes(token),token)
  assert.match(access,/feature\.website_builder/)
  assert.match(manifest,/"website"/)
  assert.match(routes,/v1\/public-websites/)
})

test('custom domains and server SEO metadata are resolved before the React shell boots',()=>{
  const web=read('routes/web.php')
  const blade=read('resources/views/app.blade.php')
  const app=read('resources/js/app.tsx')
  assert.match(web,/where\('purpose', 'website'\)/)
  assert.match(web,/publicWebsiteMeta/)
  assert.match(blade,/__WORKINTEL_PUBLIC_WEBSITE_HOST__/)
  assert.match(blade,/og:title/)
  assert.match(blade,/canonical/)
  assert.match(app,/__WORKINTEL_PUBLIC_WEBSITE_HOST__/)
})

test('website localization and automation event contracts are registered',()=>{
  const catalog=read('resources/js/i18n/locales/studios.ts')
  const webhooks=read('app/Services/Integrations/WebhookService.php')
  for(const token of ["'nav.website'","'page.website.title'","'page.website.desc'"]) assert.ok(catalog.includes(token),token)
  assert.equal((catalog.match(/'nav\.website'/g)||[]).length,5)
  assert.equal((catalog.match(/'page\.website\.title'/g)||[]).length,5)
  assert.match(webhooks,/website\.page_published/)
  assert.match(webhooks,/website\.lead_received/)
})
