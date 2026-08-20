import fs from 'node:fs'
import path from 'node:path'

const root=process.cwd()
/** Read one repository file for dependency-free shell verification. */
const read=relative=>fs.readFileSync(path.join(root,relative),'utf8')
const failures=[]
const manifest=JSON.parse(read('resources/js/navigation.manifest.json'))
const architecture=JSON.parse(read('docs/architecture/workintel-modules.json'))
const allowed=new Set([...architecture.modules.map(module=>module.id),'account-support'])
const legacyGroups=new Set(['work','people','operations','clients','content','money','insights','account','automation'])

for(const [role,groups] of Object.entries(manifest)){
  const groupIds=groups.map(group=>group.id)
  if(groupIds.length!==new Set(groupIds).size)failures.push(`${role}: duplicate module groups`)
  for(const group of groups){
    if(!allowed.has(group.id))failures.push(`${role}: unknown module group ${group.id}`)
    if(legacyGroups.has(group.id))failures.push(`${role}: legacy feature-bucket group ${group.id}`)
    for(const [page] of group.items){
      const row=architecture.screenMap[page]
      if(!row){failures.push(`${role}: unclassified page ${page}`);continue}
      const expected=row.target==='account-support'?'account-support':row.target
      if(expected!==group.id)failures.push(`${role}: ${page} belongs to ${expected}, found in ${group.id}`)
      if(page==='platform')failures.push(`${role}: Platform must not be mixed into tenant navigation`)
      if(page==='shifts')failures.push(`${role}: legacy shifts destination must not be a sidebar item`)
    }
  }
}

const catalog=read('resources/js/moduleCatalog.ts')
for(const module of architecture.modules)if(!catalog.includes(`id:'${module.id}'`))failures.push(`moduleCatalog missing ${module.id}`)
for(const [page,row] of Object.entries(architecture.screenMap)){
  if(row.target==='platform-console'||row.decision==='REMOVE')continue
  if(!catalog.includes(`page:'${page}'`))failures.push(`moduleCatalog missing page ${page}`)
}
for(const marker of ['aliases:string[]','description:string','workspaceModuleForPage','localizedPageDescription'])if(!catalog.includes(marker))failures.push(`moduleCatalog missing ${marker}`)

const sidebar=read('resources/js/components/Sidebar.tsx')
for(const marker of ['ui-sidebar__module-row','onOpenModule','activeModule','favoritePages','ui-sidebar-backdrop','is-mobile-open'])if(!sidebar.includes(marker))failures.push(`Sidebar missing ${marker}`)
const context=read('resources/js/components/ShellContextBar.tsx')
for(const marker of ['localizedPageDescription','ui-shell-context__crumbs','ui-shell-context__description','help.find_anything','onToggleFavorite','onOpenModule'])if(!context.includes(marker))failures.push(`ShellContextBar missing ${marker}`)
const moduleHome=read('resources/js/components/ModuleHome.tsx')
for(const marker of ['What you can do here','Favorites','Recent','onToggleFavorite','localizedPageDescription'])if(!moduleHome.includes(marker))failures.push(`ModuleHome missing ${marker}`)
const preferences=read('resources/js/shellPreferences.ts')
for(const marker of ['loadShellPreferences','toggleShellFavorite','recordRecentShellPage','workspaceId','userId'])if(!preferences.includes(marker))failures.push(`Shell preferences missing ${marker}`)
const shellNavigation=read('resources/js/shellNavigation.ts')
for(const marker of ['ShellDestination','shellDestinationFromLocation','writeShellHistory','window.history.pushState','window.history.replaceState','accessiblePagesForModule'])if(!shellNavigation.includes(marker))failures.push(`Shell navigation missing ${marker}`)
const command=read('resources/js/components/CommandPalette.tsx')
for(const marker of ['pageShell','workspaceModules','favorites','recent','/api/v1/search','focusShellEntity','onNavigateModule','Searching workspace','entityRequest.current===requestId'])if(!command.includes(marker))failures.push(`CommandPalette missing ${marker}`)
const entityFocus=read('resources/js/shellEntityFocus.ts')
for(const marker of ['workintel:entity-focus','sessionStorage','useShellEntitySearch','onFocus?.(value)'])if(!entityFocus.includes(marker))failures.push(`Entity focus missing ${marker}`)
const app=read('resources/js/WorkforceApp.tsx')
for(const marker of ['shellDestinationFromLocation','writeShellHistory','ModuleHome','activeModule','mobileOpen','shellPrefs','navigateModule'])if(!app.includes(marker))failures.push(`WorkforceApp missing ${marker}`)
const overview=read('resources/js/pages/Overview.tsx')
if(!overview.includes('onOpenModule'))failures.push('Overview does not route Workspace Areas into module homes')
const topbar=read('resources/js/components/TopBar.tsx')
for(const marker of ["window.location.assign('/seller')",'ui-topbar__mobile-menu','onOpenSidebar'])if(!topbar.includes(marker))failures.push(`TopBar missing ${marker}`)

const searchController=read('app/Http/Controllers/Api/V1/GlobalSearchController.php')
for(const marker of ['scopePeople','scopeProjects','scopeTasks','clients.view','media.view','workspace_id'])if(!searchController.includes(marker))failures.push(`GlobalSearchController missing ${marker}`)
const routes=read('routes/api.php')
if(!routes.includes("Route::get('/search', GlobalSearchController::class)"))failures.push('Global entity search route is not registered')
if(architecture.routePrefixMap['api/v1/search']!=='home')failures.push('Global search route is not owned by Home & Command Center')

const css=read('resources/js/design-system/toolkit.css')
for(const marker of ['.ui-sidebar__module-row{','.ui-module-home{','.ui-sidebar-backdrop{','.ui-topbar__mobile-menu','.ui-sidebar.is-mobile-open','.ui-sidebar:not(.is-collapsed),.ui-sidebar.is-collapsed'])if(!css.includes(marker))failures.push(`Shell CSS missing ${marker}`)

console.log(`M3 Application Shell audit: ${Object.keys(manifest).length} roles; ${Object.keys(architecture.screenMap).length} classified pages`)
if(failures.length){for(const failure of failures)console.error(`FAIL: ${failure}`);process.exit(1)}
console.log('M3 Application Shell audit: PASS')
