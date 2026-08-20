import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root=path.resolve(import.meta.dirname,'../..')
/** Read one project source file for dependency-free Document Studio V6 contracts. */
function read(relativePath){const source=fs.readFileSync(path.join(root,relativePath),'utf8');return relativePath==='resources/js/pages/Documents.tsx'?source+'\n'+fs.readFileSync(path.join(root,'resources/js/documents/studio/DocumentStudioSupport.tsx'),'utf8'):source}

test('Document Studio V6 exposes pages layers blocks assets canvas and five inspector concerns',()=>{
  const page=read('resources/js/pages/Documents.tsx')
  assert.match(page,/type DesignerRailTab\s*=\s*'pages'\s*\|\s*'layers'\s*\|\s*'blocks'\s*\|\s*'assets'/)
  for(const token of ['Multi-page live canvas','Autosave','Server preflight','Batch generate','Available merge fields','PageInspector','CommentPanel'])assert.ok(page.includes(token),token)
  assert.ok(page.includes('normalizeV6Schema'))
  assert.ok(page.includes('document-v6-ruler'))
})

test('Document Studio V6 autosave is separate from immutable Save version',()=>{
  const page=read('resources/js/pages/Documents.tsx')
  const service=read('app/Services/Documents/DocumentStudioV6Service.php')
  const templateService=read('app/Services/Documents/DocumentTemplateService.php')
  assert.ok(page.includes('/draft'))
  assert.ok(page.includes('Save version'))
  assert.ok(service.includes('DocumentTemplateDraft'))
  assert.ok(service.includes("'revision'=>$draft->revision + 1"))
  assert.ok(templateService.includes("DocumentTemplateDraft::query()->where('document_template_id',$template->id)->delete()"))
})

test('Document Studio V6 renders explicit page containers and exposes server preflight plus batch generation',()=>{
  const renderer=read('app/Services/Documents/DocumentTemplateRenderer.php')
  const service=read('app/Services/Documents/DocumentStudioV6Service.php')
  const routes=read('routes/documents.php')
  assert.ok(renderer.includes("($block['type'] ?? null) === 'page'"))
  assert.ok(renderer.includes('data-page-id'))
  for(const token of ["/draft'","/preflight'","/batch-generate'"])assert.ok(routes.includes(token),token)
  assert.ok(service.includes('batchGenerate'))
  assert.ok(service.includes('preflight'))
  assert.ok(service.includes('A V6 document can contain at most 50 authored pages')||read('app/Services/Documents/DocumentTemplateService.php').includes('at most 50 authored pages'))
})

test('Document Studio V6 advanced authoring exposes brand kits page masters linked components and guides',()=>{
  const page=read('resources/js/pages/Documents.tsx')
  for(const token of ['Save current brand kit','Save current page master','Detach to local copy','Update source','Toggle canvas rulers','Toggle printable margin guides'])assert.ok(page.includes(token),token)
  assert.match(page,/inspectorTab\s*===\s*'brand'/)
  assert.ok(page.includes('workflow?.signature_required'))
  assert.ok(page.includes('label="Require signature"'))
})

test('Document Studio V6 supports formatted tables and persistent large batch jobs',()=>{
  const page=read('resources/js/pages/Documents.tsx')
  const service=read('app/Services/Documents/DocumentStudioV6Service.php')
  const renderer=read('app/Services/Documents/DocumentTemplateRenderer.php')
  assert.ok(page.includes('currency'))
  assert.ok(page.includes('documents.batch-jobs.v6'))
  assert.ok(page.includes('51–500'))
  assert.ok(service.includes('queueBatchGenerate'))
  assert.ok(service.includes('processQueuedBatches'))
  assert.ok(renderer.includes('formatTableValue'))
})

test('Document Studio V6 advanced resources are workspace scoped and scheduler backed',()=>{
  const migration=read('database/migrations/2026_08_20_001000_create_document_studio_v6_advanced_authoring.php')
  const routes=read('routes/documents.php')
  const consoleRoutes=read('routes/console.php')
  for(const table of ['document_brand_kits','document_page_masters','document_batch_jobs'])assert.ok(migration.includes(table),table)
  for(const uri of ['/documents/brand-kits','/documents/page-masters','/documents/templates/{template}/batch-jobs'])assert.ok(routes.includes(uri),uri)
  assert.ok(consoleRoutes.includes('workintel:process-document-batches'))
})

test('Document Studio V6 final closure exposes Media DAM brand logos and per-page master overrides',()=>{
  const page=read('resources/js/pages/Documents.tsx')
  const renderer=read('app/Services/Documents/DocumentTemplateRenderer.php')
  for(const token of ['Choose Brand Kit logo','Select from Media Library','Page master override','Override margins/background for this page'])assert.ok(page.includes(token),token)
  assert.ok(renderer.includes('brandLogoAssetId'))
  assert.ok(renderer.includes('pageSettings('))
  assert.ok(renderer.includes('page_master_id'))
})

test('Document Studio V6 final closure hardens batch retry idempotency and stale recovery',()=>{
  const migration=read('database/migrations/2026_08_20_001100_harden_document_studio_v6_batch_queue.php')
  const service=read('app/Services/Documents/DocumentStudioV6Service.php')
  const page=read('resources/js/pages/Documents.tsx')
  for(const token of ['client_request_id','heartbeat_at','attempt_count','last_error'])assert.ok(migration.includes(token),token)
  assert.ok(service.includes('recoverStaleBatches'))
  assert.ok(service.includes("firstOrCreate(['workspace_id'=>$actor->workspace_id,'client_request_id'=>$clientRequestId]"))
  assert.ok(page.includes('randomUUID'))
})

test('Document Studio V6 final closure snapshots and enforces generated workflow policy',()=>{
  const templateService=read('app/Services/Documents/DocumentTemplateService.php')
  const workflow=read('app/Services/Documents/DocumentStudioV4Service.php')
  const page=read('resources/js/pages/Documents.tsx')
  assert.ok(templateService.includes("'workflow_policy'=>"))
  assert.ok(workflow.includes('workflowPolicy'))
  assert.ok(workflow.includes('must complete review before approval'))
  assert.ok(workflow.includes('All required signatures must be completed before final lock'))
  assert.ok(page.includes('Policy snapshot'))
})

