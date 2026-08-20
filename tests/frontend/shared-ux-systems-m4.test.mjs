import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { readSource } from './source-bundles.mjs'

/** Read one repository source file for M4 shared UX contract assertions. */
const read=file=>readSource(file)

test('M4 exposes one shared contract for filters dates async states and consequential dialogs',()=>{
  const ui=read('resources/js/design-system/index.tsx')
  for(const component of ['FilterBar','DateRangeField','LoadingState','ErrorState','DialogActions','ConfirmDialog','ConfirmProvider','FormDialog','BooleanField','ChoiceList','ChoiceRow','SettingRow'])assert.match(ui,new RegExp(`export function ${component}\\b`),component)
  assert.match(ui,/data-grid-version="3"/)
  assert.match(ui,/<DateRangeField/)
})

test('M4 removes page-owned empty and generic toolbar implementations',()=>{
  const files=[]
  /** Collect feature pages for direct visual-state checks. */
  const walk=dir=>{for(const entry of fs.readdirSync(dir,{withFileTypes:true})){const full=path.join(dir,entry.name);if(entry.isDirectory())walk(full);else if(entry.name.endsWith('.tsx'))files.push(full)}}
  walk('resources/js/pages')
  const source=files.map(read).join('\n')
  assert.equal((source.match(/className=["']ui-empty["']/g)||[]).length,0)
  assert.equal((source.match(/className=["']ui-toolbar["']/g)||[]).length,0)
  assert.ok((source.match(/<FilterBar\b/g)||[]).length>=6)
  assert.ok((source.match(/<ErrorState\b/g)||[]).length>=10)
})

test('M4 destructive actions and organization forms use app-owned dialogs',()=>{
  const organization=read('resources/js/pages/Organization.tsx')
  assert.match(organization,/<ConfirmDialog/)
  assert.match(organization,/<FormDialog/)
  assert.match(organization,/<ChoiceList/)
  assert.doesNotMatch(organization,/window\.confirm\(/)
  assert.doesNotMatch(organization,/document\.getElementById\('organization-submit'\)/)
})

test('M4 release and npm gates enforce the shared UX audit',()=>{
  const pkg=JSON.parse(read('package.json'))
  assert.match(pkg.scripts.test,/shared-ux-audit\.mjs/)
  assert.match(pkg.scripts.typecheck,/shared-ux-audit\.mjs/)
  assert.match(pkg.scripts.build,/shared-ux-audit\.mjs/)
  assert.match(read('verify-release.cmd'),/M4 Shared UX Systems audit/)
  assert.match(read('verify-clean-install.cmd'),/M4 Shared UX Systems audit/)
})


test('M4 Batch 2 removes browser-native confirmations and DOM-click form submissions',()=>{
  const files=[]
  /** Collect feature pages for confirmation and form-dialog migration checks. */
  const walk=dir=>{for(const entry of fs.readdirSync(dir,{withFileTypes:true})){const full=path.join(dir,entry.name);if(entry.isDirectory())walk(full);else if(entry.name.endsWith('.tsx'))files.push(full)}}
  walk('resources/js/pages')
  const source=files.map(read).join('\n')
  assert.equal((source.match(/window\.confirm\(/g)||[]).length,0)
  assert.equal((source.match(/(?<![\w.])confirm\(/g)||[]).length,0)
  assert.equal((source.match(/document\.getElementById\([^\n]+\.click\(\)/g)||[]).length,0)
  assert.ok((source.match(/<FormDialog\b/g)||[]).length>=5)
})

test('M4 Batch 2 keeps Media Library selection and upload limits under one picker contract',()=>{
  const field=read('resources/js/media/MediaFileField.tsx')
  const picker=read('resources/js/media/MediaPicker.tsx')
  assert.match(field,/maxFiles=\{maxFiles\}/)
  assert.match(field,/useEffect\(\(\)=>setSelectedName/)
  assert.match(picker,/allowedCount=multiple\?Math\.max\(1,maxFiles\):1/)
  assert.match(picker,/current\.length>=Math\.max\(1,maxFiles\)/)
})


test('M4 closure widens FormDialog and DataGrid adoption while removing page-owned choice styling',()=>{
  const files=[]
  /** Collect feature pages for closure migration coverage. */
  const walk=dir=>{for(const entry of fs.readdirSync(dir,{withFileTypes:true})){const full=path.join(dir,entry.name);if(entry.isDirectory())walk(full);else if(entry.name.endsWith('.tsx'))files.push(full)}}
  walk('resources/js/pages')
  const source=files.map(read).join('\n')
  assert.ok((source.match(/<FormDialog\b/g)||[]).length>=12)
  assert.ok((source.match(/<DataGrid\b/g)||[]).length>=70)
  assert.ok((source.match(/<ChoiceList\b/g)||[]).length>=3)
  assert.ok((source.match(/<SettingRow\b/g)||[]).length>=3)
  assert.equal((source.match(/<TableWrap\b/g)||[]).length,0)
  assert.doesNotMatch(read('resources/js/pages/Projects.tsx'),/gridTemplateColumns:'repeat\(2,minmax\(0,1fr\)\)'.*member_ids/)
})
