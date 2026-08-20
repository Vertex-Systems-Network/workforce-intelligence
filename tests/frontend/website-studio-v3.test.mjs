import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'

/** Read one source file for M8 Website Studio dependency-free contracts. */
const read=file=>{const source=fs.readFileSync(file,'utf8');return file==='resources/js/pages/WebsiteStudio.tsx'?source+'\n'+fs.readFileSync('resources/js/website/studio/WebsiteStudioSupport.tsx','utf8'):source}

test('M8 editor uses dedicated Pages Layers Blocks Assets and review-capable inspector IA',()=>{
 const source=read('resources/js/pages/WebsiteStudio.tsx')
 assert.match(source,/type LeftPanel\s*=\s*'pages'\s*\|\s*'layers'\s*\|\s*'blocks'\s*\|\s*'assets'/)
 assert.match(source,/type InspectorPanel\s*=\s*'content'\s*\|\s*'design'\s*\|\s*'settings'\s*\|\s*'effects'\s*\|\s*'seo'\s*\|\s*'review'/)
 for(const token of ['website-rail-tabs','website-inspector-tabs'])assert.ok(source.includes(token),token)
})

test('M8 autosave is mutable and staging/publish use immutable version boundaries',()=>{
 const source=read('resources/js/pages/WebsiteStudio.tsx'),service=read('app/Services/WebsiteBuilderService.php')
 assert.match(source,/\/draft/);assert.match(source,/Autosaved/);assert.match(source,/Save version/);assert.match(source,/Stage/);assert.match(source,/Publish staging/)
 assert.match(service,/saveDraft/);assert.match(service,/stagePage/);assert.match(service,/WebsitePageVersion::create/);assert.match(service,/staged_version/)
})

test('M8 publish preflight includes media rights forms URLs and dynamic binding validation',()=>{
 const service=read('app/Services/WebsiteBuilderService.php'),source=read('resources/js/pages/WebsiteStudio.tsx')
 for(const token of ['media.rights_expired','media.alt_missing','form.missing','url.unsafe','page.empty','binding.unknown'])assert.ok(service.includes(token),token)
 assert.match(source,/Run preflight/);assert.match(source,/Dynamic content tokens/)
})

test('M8 shareable staging previews are revocable public read-only delivery',()=>{
 const service=read('app/Services/WebsiteBuilderService.php'),routes=read('routes/website.php'),publicApp=read('resources/js/website/PublicWebsiteApp.tsx'),rootApp=read('resources/js/app.tsx')
 for(const token of ['createPreviewToken','revokePreviewToken','previewPayload','token_hash','expires_at'])assert.ok(service.includes(token),token)
 assert.match(routes,/public-websites/);assert.match(routes,/preview\/\{token\}/);assert.match(publicApp,/mode:'preview'/);assert.match(rootApp,/site-preview/)
})

test('M8 review comments linked components and responsive overrides are real contracts',()=>{
 const source=read('resources/js/pages/WebsiteStudio.tsx'),service=read('app/Services/WebsiteBuilderService.php'),css=read('resources/js/website/website-renderer.css')
 for(const token of ['ReviewInspector','linked_reusable_uuid','Push instance edits to global source','Responsive overrides'])assert.ok(source.includes(token),token)
 for(const token of ['syncReusableLinks','propagateReusableSection','WebsiteReusableSectionLink'])assert.ok(service.includes(token),token)
 assert.match(css,/--wi-section-padding-tablet/);assert.match(css,/is-preview-mobile/)
})

test('M8 effects and theme tokens have editor and public renderer parity',()=>{
 const source=read('resources/js/pages/WebsiteStudio.tsx'),renderer=read('resources/js/website/WebsiteRenderer.tsx'),css=read('resources/js/website/website-renderer.css')
 for(const token of ['Entrance effect','Heading scale','Section spacing','Button radius'])assert.ok(source.includes(token),token)
 for(const token of ['wi-site-effect','theme.heading_scale','theme.section_spacing','theme.button_radius'])assert.ok(renderer.includes(token),token)
 assert.match(css,/prefers-reduced-motion:reduce/)
})

test('M8 website editing remains on shared Media DAM and WorkIntel FormDialog contracts',()=>{
 const source=read('resources/js/pages/WebsiteStudio.tsx')
 assert.match(source,/MediaPicker/);assert.match(source,/website-page-create/);assert.match(source,/website-form-editor/);assert.doesNotMatch(source,/window\.prompt\(/)
})


test('M8 staging previews are explicitly non-cacheable non-indexable and archived pages cannot stage',()=>{
 const controller=read('app/Http/Controllers/Api/V1/PublicWebsiteController.php'),publicApp=read('resources/js/website/PublicWebsiteApp.tsx'),service=read('app/Services/WebsiteBuilderService.php')
 for(const token of ['Cache-Control','private, no-store, max-age=0','X-Robots-Tag','noindex, nofollow'])assert.ok(controller.includes(token),token)
 assert.match(publicApp,/setMeta\('robots','noindex,nofollow'\)/)
 assert.match(service,/Restore this page before staging it for review/)
})
