import fs from 'node:fs'
import path from 'node:path'

const root=process.cwd()
const manifest=JSON.parse(fs.readFileSync(path.join(root,'docs/architecture/workintel-modules.json'),'utf8'))
const sidebar=fs.readFileSync(path.join(root,'resources/js/components/Sidebar.tsx'),'utf8')
const app=fs.readFileSync(path.join(root,'resources/js/WorkforceApp.tsx'),'utf8')
const nav=JSON.parse(fs.readFileSync(path.join(root,'resources/js/navigation.manifest.json'),'utf8'))

const pageType=(sidebar.match(/export type Page\s*=([\s\S]*?)(?:\n\n|\/\*\*)/)||[])[1]||''
const pageIds=[...pageType.matchAll(/'([^']+)'/g)].map(match=>match[1])
const appCases=[...app.matchAll(/case\s+'([^']+)'\s*:/g)].map(match=>match[1])
const navIds=[...new Set(Object.values(nav).flatMap(groups=>groups.flatMap(group=>group.items.map(item=>item[0]))))]
const mapped=Object.keys(manifest.screenMap)
const targets=new Set([...manifest.modules.map(module=>module.id),...manifest.surfaces.map(surface=>surface.id)])
const failures=[]

for(const id of pageIds) if(!manifest.screenMap[id]) failures.push(`Sidebar Page '${id}' is missing from architecture.screenMap.`)
for(const id of mapped){
  const row=manifest.screenMap[id]
  if(row.target==='platform-console'||row.decision==='REMOVE')continue
  if(!pageIds.includes(id)) failures.push(`Architecture screen '${id}' is not declared in Sidebar Page.`)
}
for(const id of appCases) if(!manifest.screenMap[id]) failures.push(`WorkforceApp case '${id}' is missing from architecture.screenMap.`)
for(const id of navIds) if(!manifest.screenMap[id]) failures.push(`Navigation item '${id}' is missing from architecture.screenMap.`)
for(const [id,row] of Object.entries(manifest.screenMap)){
  if(!targets.has(row.target)) failures.push(`Screen '${id}' targets unknown module/surface '${row.target}'.`)
  if(!['KEEP','MERGE','RENAME','MOVE','REMOVE'].includes(row.decision)) failures.push(`Screen '${id}' has invalid decision '${row.decision}'.`)
  if(!row.description?.trim()) failures.push(`Screen '${id}' has no purpose description.`)
  if(row.decision==='MERGE'&&!row.mergeInto) failures.push(`Screen '${id}' is MERGE without mergeInto.`)
}
const duplicateModuleIds=manifest.modules.map(module=>module.id).filter((id,index,all)=>all.indexOf(id)!==index)
if(duplicateModuleIds.length) failures.push(`Duplicate target modules: ${duplicateModuleIds.join(', ')}`)
const moduleCatalog=fs.readFileSync(path.join(root,'app/Support/ModuleCatalog.php'),'utf8')
const moduleBlock=(moduleCatalog.split('public const DEFINITIONS = [')[1]||'').split('];')[0]||''
const currentModuleKeys=[...moduleBlock.matchAll(/^        '([^']+)'\s*=>\s*\[/gm)].map(match=>match[1])
for(const key of currentModuleKeys) if(!manifest.currentModuleMap?.[key]) failures.push(`Current module '${key}' is missing from currentModuleMap.`)
for(const key of Object.keys(manifest.currentModuleMap||{})) if(!currentModuleKeys.includes(key)) failures.push(`currentModuleMap references unknown module '${key}'.`)

const permissionCatalog=fs.readFileSync(path.join(root,'app/Support/PermissionCatalog.php'),'utf8')
const permissionBlock=(permissionCatalog.split('public const ITEMS = [')[1]||'').split('];')[0]||''
const permissionGroups=[...new Set([...permissionBlock.matchAll(/\['([^']+)',\s*'[^']+'\]/g)].map(match=>match[1]))]
for(const group of permissionGroups) if(!manifest.permissionGroupMap?.[group]) failures.push(`Permission group '${group}' is missing from permissionGroupMap.`)
for(const group of Object.keys(manifest.permissionGroupMap||{})) if(!permissionGroups.includes(group)) failures.push(`permissionGroupMap references unknown group '${group}'.`)

const routeFiles=fs.readdirSync(path.join(root,'routes')).filter(file=>file.endsWith('.php'))
for(const file of routeFiles) if(!manifest.routeFileMap[file]) failures.push(`Route file '${file}' is missing from routeFileMap.`)
for(const file of Object.keys(manifest.routeFileMap)) if(!routeFiles.includes(file)) failures.push(`routeFileMap references missing route file '${file}'.`)

console.log(`M1 module architecture: ${manifest.modules.length} workspace modules + ${manifest.surfaces.length} special surfaces`)
console.log(`Sidebar pages: ${pageIds.length}; app cases: ${appCases.length}; navigation IDs: ${navIds.length}; mapped screens: ${mapped.length}`)
console.log(`Backend route files: ${routeFiles.length}; classified route files: ${Object.keys(manifest.routeFileMap).length}`)
console.log(`Current module keys: ${currentModuleKeys.length}; permission groups: ${permissionGroups.length}`)
if(failures.length){
  console.error(`Module architecture audit: FAIL (${failures.length})`)
  for(const failure of failures) console.error(` - ${failure}`)
  process.exit(1)
}
console.log('Module architecture audit: PASS')
