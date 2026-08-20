import fs from 'node:fs'
import path from 'node:path'

const root=process.cwd()
const failures=[]
/** Read one repository source file for M4 shared UX contract checks. */
const read=file=>fs.readFileSync(path.join(root,file),'utf8')
const ds=read('resources/js/design-system/index.tsx')
const css=read('resources/js/design-system/toolkit.css')
const required=['FilterBar','DateRangeField','LoadingState','ErrorState','DialogActions','ConfirmDialog','ConfirmProvider','FormDialog','BooleanField','ChoiceList','ChoiceRow','SettingRow','DataGrid','EmptyState']
for(const name of required){const direct=new RegExp(`export function ${name}\\b`);const reExport=new RegExp(`export\\s*\\{[^}]*\\b${name}\\b[^}]*\\}\\s*from\\s*['"]\\./[^'"]+['"]`,'s');if(!direct.test(ds)&&!reExport.test(ds))failures.push(`Missing shared UX export: ${name}`)}
if(!ds.includes('data-grid-version="3"'))failures.push('DataGrid V3 marker is missing.')
if(!ds.includes('<DateRangeField'))failures.push('DataGrid must reuse the shared DateRangeField contract.')
for(const marker of ['.ui-filter-bar','.ui-loading-state','.ui-error-state','.ui-date-range','.ui-dialog-actions','.ui-data-grid-v3','.ui-boolean-field','.ui-choice-list','.ui-choice-row','.ui-setting-row'])if(!css.includes(marker))failures.push(`Shared UX CSS marker missing: ${marker}`)
for(const marker of ['@media(max-width:760px)','prefers-reduced-motion:reduce'])if(!css.includes(marker))failures.push(`Shared UX responsive/accessibility marker missing: ${marker}`)

const featureFiles=[]
/** Collect workspace feature TSX files that must consume shared UX states instead of page-owned variants. */
const walk=dir=>{for(const entry of fs.readdirSync(dir,{withFileTypes:true})){const full=path.join(dir,entry.name);if(entry.isDirectory())walk(full);else if(entry.name.endsWith('.tsx'))featureFiles.push(full)}}
walk(path.join(root,'resources/js/pages'))
const joined=featureFiles.map(file=>fs.readFileSync(file,'utf8')).join('\n')
const directEmpty=(joined.match(/className=["']ui-empty["']/g)||[]).length
const directToolbar=(joined.match(/className=["']ui-toolbar["']/g)||[]).length
if(directEmpty)failures.push(`Page-owned ui-empty states remain: ${directEmpty}`)
if(directToolbar)failures.push(`Page-owned ui-toolbar layouts remain: ${directToolbar}`)
const filterBars=(joined.match(/<FilterBar\b/g)||[]).length
const errorStates=(joined.match(/<ErrorState\b/g)||[]).length
const confirmDialogs=(joined.match(/<ConfirmDialog\b/g)||[]).length
const dialogActions=(joined.match(/<DialogActions\b/g)||[]).length
if(filterBars<6)failures.push(`Expected at least 6 migrated FilterBar surfaces, found ${filterBars}.`)
if(errorStates<10)failures.push(`Expected at least 10 recoverable ErrorState surfaces, found ${errorStates}.`)
if(confirmDialogs<1)failures.push('No feature flow uses ConfirmDialog yet.')
if(!ds.includes('<DialogActions'))failures.push('FormDialog must compose the canonical DialogActions contract.')

const formDialogs=(joined.match(/<FormDialog\b/g)||[]).length
const domClickSubmits=(joined.match(/document\.getElementById\([^\n]+\.click\(\)/g)||[]).length
if(formDialogs<12)failures.push(`Expected at least 12 migrated FormDialog surfaces, found ${formDialogs}.`)
if(domClickSubmits)failures.push(`DOM-click form submit workarounds remain: ${domClickSubmits}.`)
const mediaField=read('resources/js/media/MediaFileField.tsx'),mediaPicker=read('resources/js/media/MediaPicker.tsx')
if(!mediaField.includes('maxFiles={maxFiles}'))failures.push('MediaFileField must pass maxFiles into MediaPicker.')
if(!mediaField.includes('useEffect(()=>setSelectedName'))failures.push('MediaFileField must synchronize controlled valueLabel changes.')
if(!mediaPicker.includes('allowedCount=multiple?Math.max(1,maxFiles):1'))failures.push('MediaPicker must enforce single/multi file count consistently for uploads.')
const choiceLists=(joined.match(/<ChoiceList\b/g)||[]).length
const settingRows=(joined.match(/<SettingRow\b/g)||[]).length
const dataGrids=(joined.match(/<DataGrid\b/g)||[]).length
const tableWraps=(joined.match(/<TableWrap\b/g)||[]).length
if(choiceLists<3)failures.push(`Expected at least 3 shared ChoiceList surfaces, found ${choiceLists}.`)
if(settingRows<3)failures.push(`Expected at least 3 shared SettingRow surfaces, found ${settingRows}.`)
if(dataGrids<70)failures.push(`Expected at least 70 DataGrid V3 surfaces after development closure, found ${dataGrids}.`)
if(tableWraps>0)failures.push(`Legacy TableWrap feature debt must remain zero after development closure: ${tableWraps}/0.`)
const browserConfirms=(joined.match(/window\.confirm\(/g)||[]).length+(joined.match(/(?<![\w.])confirm\(/g)||[]).length
if(browserConfirms>0)failures.push(`Browser-native confirm usage remains: ${browserConfirms}.`)

console.log(`M4 Shared UX audit: ${featureFiles.length} feature TSX files`)
console.log(`FilterBar ${filterBars}; ErrorState ${errorStates}; ConfirmDialog ${confirmDialogs}; FormDialog ${formDialogs}; DialogActions ${dialogActions}; ChoiceList ${choiceLists}; SettingRow ${settingRows}; DataGrid ${dataGrids}; TableWrap debt ${tableWraps}/0; browser-confirm debt ${browserConfirms}/0`)
console.log(`Page-owned ui-empty ${directEmpty}; ui-toolbar ${directToolbar}`)
if(failures.length){console.error(`M4 Shared UX audit: FAIL (${failures.length})`);for(const failure of failures)console.error(` - ${failure}`);process.exit(1)}
console.log('M4 Shared UX audit: PASS')
