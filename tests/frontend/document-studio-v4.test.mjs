import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'
import { readSource } from './source-bundles.mjs'

const root=path.resolve(import.meta.dirname,'../..')
/** Read one project source file for dependency-free Document Studio V4 contracts. */
function read(relativePath){return readSource(relativePath)}

test('Document Studio preserves V4 block inspectors inside the V6 multi-page studio shell',()=>{
  const page=read('resources/js/pages/Documents.tsx')
  for(const token of ['document-v4-workspace','Multi-page live canvas','BlockInspector','PageInspector','RichTextEditor','MediaPicker','TableColumns','ChildBlocks'])assert.ok(page.includes(token),token)
  assert.ok(!page.includes('window.prompt('))
})

test('Document Studio V4 exposes governed generated-document workflows',()=>{
  const page=read('resources/js/pages/Documents.tsx')
  for(const token of ['Request review','Create secure share link','Request electronic signature','Compare template versions','Reusable components','Review timeline'])assert.ok(page.includes(token),token)
  assert.ok(page.includes('DataGrid'))
})

test('public signing uses a dedicated login-free web surface and hash-token API',()=>{
  const app=read('resources/js/app.tsx')
  const signer=read('resources/js/documents/PublicDocumentSignApp.tsx')
  const service=read('app/Services/Documents/DocumentStudioV4Service.php')
  const pageCopy=read('resources/js/i18n/pageCopy.ts')
  assert.ok(app.includes("path.startsWith('/document-sign/')"))
  assert.ok(app.includes('<PublicDocumentSignApp />'))
  assert.ok(signer.includes('/api/v1/public/documents/sign/'))
  assert.ok(signer.includes('I consent to use this electronic signature'))
  assert.ok(signer.includes('translatePageCopy'))
  assert.ok(signer.includes('Decline signature request'))
  assert.ok(!signer.includes('window.confirm('))
  assert.ok(pageCopy.includes('studiosPhrases'))
  for(const phrase of ['WorkIntel Secure Sign','Review and sign','Sign document','Document Studio V4'])assert.ok(pageCopy.includes(phrase),phrase)
  assert.ok(service.includes("'/document-sign/'.\$token"))
  assert.ok(service.includes("hash('sha256', \$token)"))
})

test('Document Studio V4 backend supports nested logic, Unicode PDF and optional code adapters',()=>{
  const renderer=read('app/Services/Documents/DocumentTemplateRenderer.php')
  const pdf=read('app/Services/Documents/DocumentPdfRenderer.php')
  const catalog=read('app/Services/Documents/DocumentTemplateCatalog.php')
  for(const token of ["$type === 'conditional'","$type === 'repeat'","$type === 'columns'","$type === 'reusable'",'sanitizeRichHtml'])assert.ok(renderer.includes(token),token)
  assert.match(pdf,/Chromium|Chrome|Edge/i)
  for(const block of ["'rich_text'","'image'","'formula'","'qr'","'barcode'","'signature'"])assert.ok(catalog.includes(block),block)
})
