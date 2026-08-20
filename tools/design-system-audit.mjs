import fs from 'node:fs'
import path from 'node:path'

const root=process.cwd()
const failures=[]
const dsRoot=path.join(root,'resources/js/design-system')
const requiredFiles=['index.tsx','tokens.css','toolkit.css','README.md']
for(const file of requiredFiles) if(!fs.existsSync(path.join(dsRoot,file))) failures.push(`Missing design-system file: ${file}`)
if(fs.existsSync(path.join(root,'resources/js/ui'))) failures.push('Legacy resources/js/ui directory must not exist after M2 promotion.')

const index=fs.readFileSync(path.join(dsRoot,'index.tsx'),'utf8')
const requiredExports=['Button','IconButton','Pressable','Input','Checkbox','Radio','ChoiceInput','HiddenFileInput','Select','Option','Textarea','Field','Form','Label','Link','FormSection','FormGrid','FormActions','Modal','Drawer','Dropdown','Popover','Tooltip','Tabs','DataGrid','TableWrap','ViewModeToggle','Image','ProgressBar','EmptyState','FilterBar','DateRangeField','LoadingState','ErrorState','DialogActions','ConfirmDialog','Page','PageHeader','Box','Stack','Inline','Grid','Text']
for(const name of requiredExports){
  const direct=new RegExp(`export (?:function|const|type|interface) ${name}\\b`)
  const reExport=new RegExp(`export\\s*\\{[^}]*\\b${name}\\b[^}]*\\}\\s*from\\s*['"]\\./[^'"]+['"]`,'s')
  if(!direct.test(index) && !reExport.test(index)) failures.push(`Design-system export missing: ${name}`)
}

const sourceFiles=[]
/** Recursively collect TypeScript and TSX source files for the design-system policy audit. */
const walk=dir=>{for(const entry of fs.readdirSync(dir,{withFileTypes:true})){const full=path.join(dir,entry.name);if(entry.isDirectory())walk(full);else if(/\.(ts|tsx)$/.test(entry.name))sourceFiles.push(full)}}
walk(path.join(root,'resources/js'))
const rawControl=/<(button|input|select|option|textarea|table|img|form|label|a|progress)\b/g
const rendererException=path.normalize(path.join(root,'resources/js/website/WebsiteRenderer.tsx'))
let rawCount=0, lucideImports=0
for(const file of sourceFiles){
  const rel=path.relative(root,file).replaceAll('\\','/')
  const text=fs.readFileSync(file,'utf8')
  if(rel.includes('/design-system/')) continue
  if(/from\s+['"][.\/]*ui(?:\/|['"])/.test(text)) failures.push(`${rel}: imports legacy ui path instead of design-system.`)
  for(const match of text.matchAll(/from\s+['"]([^'"]+)['"]/g)){
    const pkg=match[1]
    if(pkg==='lucide-react') lucideImports++
    if(/(?:react-icons|heroicons|fontawesome|material-icons|@mdi|phosphor)/i.test(pkg)) failures.push(`${rel}: non-Lucide icon dependency '${pkg}'.`)
  }
  if(/<svg\b/.test(text)) failures.push(`${rel}: handwritten SVG is not allowed in feature source; use lucide-react or a design-system media component.`)
  if(path.normalize(file)!==rendererException){
    const matches=[...text.matchAll(rawControl)]
    rawCount+=matches.length
    for(const match of matches) failures.push(`${rel}: raw <${match[1]}> control/media element must be rendered through WorkIntel Design System.`)
  }
}

const baselinePath=path.join(root,'docs/architecture/M2_INLINE_STYLE_BASELINE.json')
const baseline=JSON.parse(fs.readFileSync(baselinePath,'utf8'))
let inlineTotal=0
for(const file of sourceFiles){
  const rel=path.relative(root,file).replaceAll('\\','/')
  if(rel.includes('/design-system/')||path.normalize(file)===rendererException) continue
  const text=fs.readFileSync(file,'utf8')
  const current=(text.match(/style=\{\{/g)||[]).length
  inlineTotal+=current
  const allowed=baseline.files[rel]??0
  if(current>allowed) failures.push(`${rel}: inline styles increased from M2 baseline ${allowed} to ${current}; add/reuse a design-system layout/style contract instead.`)
}
if(inlineTotal>baseline.total) failures.push(`Inline style total increased from M2 baseline ${baseline.total} to ${inlineTotal}.`)

const toolkit=fs.readFileSync(path.join(dsRoot,'toolkit.css'),'utf8')
const stateMarkers=[
  ['button hover','.ui-button:hover'],['keyboard focus',':focus-visible'],['disabled controls','.ui-input:disabled'],
  ['invalid controls','[aria-invalid=\"true\"]'],['loading buttons','.ui-button.is-loading'],['modal overlay','.ui-modal'],
  ['tooltip overlay','.ui-tooltip'],['RTL support','[dir=\"rtl\"]'],['reduced motion','prefers-reduced-motion:reduce'],['mobile adaptation','@media(max-width:760px)']
]
for(const [label,marker] of stateMarkers) if(!toolkit.includes(marker)) failures.push(`Design-system visual state missing: ${label} (${marker}).`)

const allowedPageCss=new Set(['document-studio-v4.css','hris.css','tasks-v2.css','website-studio.css'])
const pageCssDir=path.join(root,'resources/js/pages')
for(const file of fs.readdirSync(pageCssDir).filter(name=>name.endsWith('.css'))){
  if(!allowedPageCss.has(file)) failures.push(`Unregistered page-level CSS '${file}'; reusable states belong in design-system/toolkit.css.`)
  const css=fs.readFileSync(path.join(pageCssDir,file),'utf8')
  if(/(?:^|})\s*\.ui-[^{]+\{/m.test(css)) failures.push(`${file}: top-level .ui-* override is forbidden; page CSS must scope any design-system override to its module root.`)
}

const packageJson=JSON.parse(fs.readFileSync(path.join(root,'package.json'),'utf8'))
for(const [name] of Object.entries(packageJson.dependencies??{})) if(/react-icons|heroicons|fontawesome|material-icons|@mdi|phosphor/i.test(name)) failures.push(`package.json contains non-Lucide icon dependency '${name}'.`)

console.log(`M2 Design System audit: ${sourceFiles.length} TS/TSX files`)
console.log(`Feature raw interactive/media tags: ${rawCount}; lucide-react imports: ${lucideImports}; inline styles: ${inlineTotal}/${baseline.total} baseline`)
if(failures.length){console.error(`M2 Design System audit: FAIL (${failures.length})`);for(const failure of failures)console.error(` - ${failure}`);process.exit(1)}
console.log('M2 Design System audit: PASS')
