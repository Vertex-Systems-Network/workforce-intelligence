import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root=path.resolve(import.meta.dirname,'../..')
/** Read one source file used by the DEV-07 source-level contracts. */
const read=relative=>fs.readFileSync(path.join(root,relative),'utf8')

/** Recursively return all application TypeScript sources for native-dialog regression checks. */
function applicationSources(){const out=[];/** Recursively collect application TypeScript sources. */ const walk=relative=>{for(const entry of fs.readdirSync(path.join(root,relative),{withFileTypes:true})){const child=path.join(relative,entry.name);if(entry.isDirectory())walk(child);else if(/\.(?:ts|tsx)$/.test(entry.name))out.push([child.replaceAll('\\','/'),read(child)])}};walk('resources/js');return out}

test('destructive and prompt interactions never fall back to browser-native blocking dialogs',()=>{
 for(const [relative,source] of applicationSources())assert.doesNotMatch(source,/\bwindow\.(?:confirm|prompt|alert)\s*\(/,relative)
 const enterprise=read('resources/js/components/chat/EnterpriseControls.tsx')
 const editor=read('resources/js/components/RichTextEditor.tsx')
 assert.match(enterprise,/useConfirmAction/)
 assert.match(enterprise,/Revoke external collaborator\?/)
 assert.match(enterprise,/Release legal hold\?/)
 assert.match(editor,/<Modal\b[\s\S]*title="Edit link"/)
})

test('major async surfaces use shared loading and empty-state primitives',()=>{
 for(const relative of ['resources/js/pages/Documents.tsx','resources/js/pages/Modules.tsx','resources/js/pages/People.tsx','resources/js/pages/settings/M13Settings.tsx','resources/js/pages/settings/ScreenshotStorageSettings.tsx','resources/js/components/chat/EnterpriseControls.tsx'])assert.match(read(relative),/LoadingState/,relative)
 assert.match(read('resources/js/pages/Modules.tsx'),/EmptyState/)
 assert.match(read('resources/js/pages/settings/M13Settings.tsx'),/EmptyState/)
 const publicWebsite=read('resources/js/website/PublicWebsiteApp.tsx')
 assert.match(publicWebsite,/role="status"/)
 assert.match(publicWebsite,/role="alert"/)
})

test('settings save/reset flow tracks last server-confirmed values and blocks no-op saves',()=>{
 const settings=read('resources/js/pages/settings/M13Settings.tsx')
 assert.match(settings,/savedPrefs/)
 assert.match(settings,/const dirty = useMemo/)
 assert.match(settings,/onClick={reset}>Reset</)
 assert.match(settings,/disabled={!dirty}/)
})

test('shared DataGrid and Drawer retain filters bulk actions mobile empty recovery and overlay semantics',()=>{
 const ds=read('resources/js/design-system/index.tsx')
 assert.match(ds,/filterableColumns/)
 assert.match(ds,/bulkActions\.length/)
 assert.match(ds,/filteredEmpty\?\?/)
 assert.match(ds,/mobileCard&&/)
 assert.match(ds,/ui-data-grid-v2__pagination/)
 assert.match(ds,/export function Drawer[\s\S]*useFocusTrap\(open,drawerRef/)
 assert.match(ds,/export function Drawer[\s\S]*aria-modal="true"/)
})
