import fs from 'node:fs'
import path from 'node:path'

const root=path.resolve(import.meta.dirname,'..')
const failures=[]
/** Read one source file for a DEV-07 consistency contract. */
const read=relative=>fs.readFileSync(path.join(root,relative),'utf8')
/** Register one required source contract without hiding the source that failed it. */
function requireMatch(relative,pattern,label){const source=read(relative);if(!pattern.test(source))failures.push(`${relative}: ${label}`)}
/** Register one forbidden source contract recursively across application TypeScript. */
function rejectApplicationPattern(pattern,label){
 const base=path.join(root,'resources/js')
 /** Recursively inspect application TypeScript for a forbidden interaction pattern. */
 const walk=directory=>{for(const entry of fs.readdirSync(directory,{withFileTypes:true})){const target=path.join(directory,entry.name);if(entry.isDirectory())walk(target);else if(/\.(?:ts|tsx)$/.test(entry.name)){const source=fs.readFileSync(target,'utf8');if(pattern.test(source))failures.push(`${path.relative(root,target).replaceAll('\\','/')}: ${label}`)}}}
 walk(base)
}

// Browser-native blocking dialogs bypass the shared overlay/focus/accessibility contract.
rejectApplicationPattern(/\bwindow\.(?:confirm|prompt|alert)\s*\(/,'native blocking browser dialog is forbidden; use shared WorkIntel UI')

// Cross-module async state ownership.
for(const relative of [
 'resources/js/pages/Documents.tsx',
 'resources/js/pages/Modules.tsx',
 'resources/js/pages/People.tsx',
 'resources/js/pages/settings/M13Settings.tsx',
 'resources/js/pages/settings/ScreenshotStorageSettings.tsx',
 'resources/js/components/chat/EnterpriseControls.tsx',
]) requireMatch(relative,/LoadingState/,'must use the shared LoadingState contract')
requireMatch('resources/js/pages/Modules.tsx',/EmptyState/,'module history must use the shared EmptyState contract')
requireMatch('resources/js/pages/settings/M13Settings.tsx',/EmptyState/,'settings empty collections must use the shared EmptyState contract')

// Destructive and text-entry overlays must stay inside accessible application UI.
requireMatch('resources/js/components/chat/EnterpriseControls.tsx',/useConfirmAction/,'enterprise destructive actions must use shared confirmation')
requireMatch('resources/js/components/RichTextEditor.tsx',/<Modal\b[\s\S]*title="Edit link"/,'rich-text link editing must use an in-app modal')

// Public website states remain independent visually but must expose equivalent accessibility semantics.
requireMatch('resources/js/website/PublicWebsiteApp.tsx',/role="status"[\s\S]*aria-live="polite"/,'public website loading state must announce progress')
requireMatch('resources/js/website/PublicWebsiteApp.tsx',/role="alert"/,'public website failure state must be announced')

// Save/reset behavior must preserve server-confirmed state and block no-op saves.
const settings=read('resources/js/pages/settings/M13Settings.tsx')
for(const [pattern,label] of [
 [/savedPrefs/,'notification settings must retain a server-confirmed baseline'],
 [/const dirty = useMemo/,'notification settings must derive dirty state'],
 [/onClick={reset}>Reset</,'notification settings must provide reset'],
 [/disabled={!dirty}/,'notification settings must block no-op saves'],
]) if(!pattern.test(settings))failures.push(`resources/js/pages/settings/M13Settings.tsx: ${label}`)

// Shared table and drawer primitives own filtering, bulk actions, empty recovery, mobile layout and modal focus semantics.
const ds=read('resources/js/design-system/index.tsx')
for(const [pattern,label] of [
 [/filterableColumns/,'DataGrid must retain shared filters'],
 [/bulkActions\.length/,'DataGrid must retain shared bulk actions'],
 [/filteredEmpty\?\?/,'DataGrid must retain filtered-empty recovery'],
 [/mobileCard&&/,'DataGrid must retain mobile-card rendering'],
 [/ui-data-grid-v2__pagination/,'DataGrid must retain pagination'],
 [/export function Drawer[\s\S]*useFocusTrap\(open,drawerRef/,'Drawer must retain focus trapping'],
 [/export function Drawer[\s\S]*aria-modal="true"/,'Drawer must remain modal to assistive technology'],
]) if(!pattern.test(ds))failures.push(`resources/js/design-system/index.tsx: ${label}`)

if(failures.length){console.error('DEV-07 cross-module UX audit: FAIL');for(const failure of failures)console.error(`- ${failure}`);process.exit(1)}
console.log('DEV-07 cross-module UX audit: PASS')
