import fs from 'node:fs'

/** Read one repository source file for dependency-free M9 contract checks. */
const read=file=>{const source=fs.readFileSync(file,'utf8');return file==='resources/js/pages/Documents.tsx'?source+'\n'+fs.readFileSync('resources/js/documents/studio/DocumentStudioSupport.tsx','utf8'):source}
const failures=[]
/** Require every expected marker in one source file and collect missing-contract failures. */
const requireMarkers=(file,markers)=>{const source=read(file);for(const marker of markers)if(!source.includes(marker))failures.push(`${file} missing ${marker}`);return source}

const migration=requireMarkers('database/migrations/2026_08_20_000500_create_document_template_drafts.php',['document_template_drafts','revision','document_template_id'])
requireMarkers('app/Models/DocumentTemplateDraft.php',['class DocumentTemplateDraft','content_schema','updatedBy'])
const service=requireMarkers('app/Services/Documents/DocumentStudioV6Service.php',['saveDraft','draftPayload','discardDraft','preflight','batchGenerate','DocumentTemplateDraft'])
const templateService=requireMarkers('app/Services/Documents/DocumentTemplateService.php',['validatedSchema','validatedSettings','V6 page containers cannot be mixed','studio_version'])
const renderer=requireMarkers('app/Services/Documents/DocumentTemplateRenderer.php',['data-page-id=','legacy-page-1','wi-document-page'])
const routes=requireMarkers('routes/documents.php',['DocumentStudioV6Controller',"/documents/templates/{template}/draft","/documents/templates/{template}/preflight","/documents/templates/{template}/batch-generate"])
const ui=requireMarkers('resources/js/pages/Documents.tsx',['Document Studio V6','Pages','Layers','Blocks','Assets','Multi-page live canvas','Media Library','normalizeV6Schema','autosaveDraft','Run server preflight','Batch generate'])
requireMarkers('resources/js/pages/document-studio-v4.css',['document-v6-workspace','document-v6-page-list','document-v6-ruler','document-v6-preflight-list'])
requireMarkers('resources/js/documents/types.ts',['DocumentTemplateDraft','DocumentPreflightIssue','page_id?:string'])
requireMarkers('app/Services/Documents/DocumentTemplateCatalog.php',["'page' => 'Page'"])

if(!migration.includes('unique'))failures.push('V6 drafts must keep one mutable draft per template.')
if(!service.includes('count($ids) > 50'))failures.push('V6 batch generation must remain bounded to 50 source records.')
if(!templateService.includes('DocumentTemplateDraft::query()->where'))failures.push('Explicit template save must clear mutable autosave state.')
if(!renderer.includes("($block['type'] ?? null) === 'page'"))failures.push('Renderer does not recognize authored V6 page containers.')
if((ui.match(/window\.prompt\(/g)||[]).length)failures.push('Document Studio V6 must not use browser prompt().')
if((ui.match(/window\.confirm\(/g)||[]).length)failures.push('Document Studio V6 must not use browser confirm().')
if(!ui.includes("setRailTab('assets')"))failures.push('Media DAM insertion is not wired through the V6 Assets rail.')
if(!routes.includes("workspace.permission:documents.generate"))failures.push('Batch generation is missing the documents.generate permission gate.')

requireMarkers('database/migrations/2026_08_20_001000_create_document_studio_v6_advanced_authoring.php',['document_brand_kits','document_page_masters','document_batch_jobs','version'])
requireMarkers('app/Models/DocumentBrandKit.php',['class DocumentBrandKit','logo_media_asset_id'])
requireMarkers('app/Models/DocumentPageMaster.php',['class DocumentPageMaster','page_settings'])
requireMarkers('app/Models/DocumentBatchJob.php',['class DocumentBatchJob','source_ids','processed_count'])
requireMarkers('app/Services/Documents/DocumentStudioV6Service.php',['createBrandKit','createPageMaster','queueBatchGenerate','processQueuedBatches','brand_kit.missing','workflow.signature_block'])
requireMarkers('app/Services/Documents/DocumentTemplateRenderer.php',['DocumentBrandKit','DocumentPageMaster','formatTableValue'])
requireMarkers('resources/js/pages/Documents.tsx',['Save current brand kit','Save current page master','Detach to local copy','Update source','documents.batch-jobs.v6','Toggle canvas rulers'])
requireMarkers('routes/console.php',['workintel:process-document-batches'])
if(!routes.includes('/documents/templates/{template}/batch-jobs'))failures.push('Persistent V6 batch-job endpoint is missing.')
if(!ui.includes('51–500'))failures.push('Large-batch UX does not explain scheduler-backed processing.')
requireMarkers('database/migrations/2026_08_20_001100_harden_document_studio_v6_batch_queue.php',['client_request_id','heartbeat_at','attempt_count','last_error','doc_batch_workspace_client_uq'])
requireMarkers('app/Services/Documents/DocumentStudioV6Service.php',['recoverStaleBatches',"firstOrCreate(['workspace_id'=>$actor->workspace_id,'client_request_id'=>$clientRequestId]",'page.page_master_missing','brand_kit.logo_missing','settingsReferenceExists','pageMasterReferenceExists'])
requireMarkers('app/Services/Documents/DocumentTemplateService.php',["$schema,$settings", "'workflow_policy'=>",'page_master_id','Page-specific settings are too large'])
requireMarkers('app/Services/Documents/DocumentStudioV4Service.php',['workflowPolicy','must complete review before approval','Approve this document before requesting signatures','All required signatures must be completed before final lock'])
requireMarkers('app/Services/Documents/DocumentTemplateRenderer.php',['brandLogoAssetId','pageSettings','pageStyle'])
requireMarkers('resources/js/pages/Documents.tsx',['Choose Brand Kit logo','Page master override','Override margins/background for this page','Policy snapshot','randomUUID'])
console.log(`M9 Document Studio V6 audit: pages=${(ui.match(/railTab==='pages'/g)||[]).length}; drafts=${(service.match(/DocumentTemplateDraft/g)||[]).length}; prompts=${(ui.match(/window\.prompt\(/g)||[]).length}`)
if(failures.length){console.error(`M9 Document Studio V6 audit: FAIL (${failures.length})`);for(const failure of failures)console.error(` - ${failure}`);process.exit(1)}
console.log('M9 Document Studio V6 audit: PASS')
