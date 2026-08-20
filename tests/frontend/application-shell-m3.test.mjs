import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'

/** Read one repository source file for M3 shell contract assertions. */
const read=file=>fs.readFileSync(file,'utf8')
const manifest=JSON.parse(read('resources/js/navigation.manifest.json'))
const architecture=JSON.parse(read('docs/architecture/workintel-modules.json'))

test('M3 tenant navigation is grouped by locked business modules rather than legacy feature buckets',()=>{
  const allowed=new Set([...architecture.modules.map(module=>module.id),'account-support'])
  for(const [role,groups] of Object.entries(manifest)){
    assert.equal(groups.some(group=>['work','people','operations','clients','content','money','insights','account'].includes(group.id)),false,`${role} has legacy group`)
    for(const group of groups){
      assert.ok(allowed.has(group.id),`${role} unknown ${group.id}`)
      for(const [page] of group.items){
        assert.notEqual(page,'platform','tenant navigation must not expose platform console')
        assert.equal(architecture.screenMap[page].target==='account-support'?'account-support':architecture.screenMap[page].target,group.id,`${page} classification`)
      }
    }
  }
})

test('M3 page metadata gives every shell destination an area description aliases and Lucide icon',()=>{
  const source=read('resources/js/moduleCatalog.ts')
  for(const [page,row] of Object.entries(architecture.screenMap)){
    if(row.target==='platform-console'||row.decision==='REMOVE')continue
    assert.match(source,new RegExp(`page:'${page.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')}'`),page)
  }
  for(const token of ['description:string','aliases:string[]','icon:LucideIcon','workspaceModuleForPage','localizedPageDescription'])assert.ok(source.includes(token),token)
})

test('M3 sidebar opens module homes keeps favorites and becomes a mobile overlay drawer',()=>{
  const sidebar=read('resources/js/components/Sidebar.tsx')
  for(const token of ['onOpenModule','activeModule','favoritePages','ui-sidebar-backdrop','is-mobile-open','ui-sidebar__module-row'])assert.ok(sidebar.includes(token),token)
  const topbar=read('resources/js/components/TopBar.tsx')
  for(const token of ['ui-topbar__mobile-menu','onOpenSidebar'])assert.ok(topbar.includes(token),token)
  assert.ok(read('resources/js/design-system/toolkit.css').includes('.ui-sidebar:not(.is-collapsed),.ui-sidebar.is-collapsed'))
})

test('M3 module homes explain purpose and expose recent favorites and accessible destinations',()=>{
  const home=read('resources/js/components/ModuleHome.tsx')
  for(const token of ['What you can do here','Favorites','Recent','localizedPageDescription','onToggleFavorite'])assert.ok(home.includes(token),token)
  const directory=read('resources/js/components/ModuleDirectory.tsx')
  const overview=read('resources/js/pages/Overview.tsx')
  assert.ok(directory.includes('onOpenModule'))
  assert.ok(overview.includes('onOpenModule'))
})

test('M3 global discovery searches modules pages and authorized workspace entities',()=>{
  const command=read('resources/js/components/CommandPalette.tsx')
  for(const token of ['workspaceModules','/api/v1/search','focusShellEntity','favorites','recent','onNavigateModule','entityRequest.current===requestId'])assert.ok(command.includes(token),token)
  const focus=read('resources/js/shellEntityFocus.ts');assert.ok(focus.includes('onFocus?.(value)'))
  assert.ok(read('resources/js/pages/Tasks.tsx').includes("setStatusFilter('')"))
  assert.ok(read('resources/js/pages/Clients.tsx').includes("setTab('clients')"))
  assert.ok(read('resources/js/pages/MediaLibrary.tsx').includes("setSection('all')"))
  const backend=read('app/Http/Controllers/Api/V1/GlobalSearchController.php')
  for(const token of ['scopePeople','scopeProjects','scopeTasks','clients.view','media.view'])assert.ok(backend.includes(token),token)
  assert.ok(read('routes/api.php').includes("Route::get('/search', GlobalSearchController::class)"))
})

test('M3 shell navigation is refreshable deep-linkable module-aware and browser Back aware',()=>{
  const router=read('resources/js/shellNavigation.ts')
  for(const token of ['shellDestinationFromLocation','writeShellHistory','window.history.pushState','window.history.replaceState','module/'])assert.ok(router.includes(token),token)
  const app=read('resources/js/WorkforceApp.tsx')
  for(const token of ['activeModule','navigateModule','hashchange','ModuleHome'])assert.ok(app.includes(token),token)
})

test('M3 personal recent and favorite state is scoped by workspace and user and never grants access',()=>{
  const source=read('resources/js/shellPreferences.ts')
  for(const token of ['workspaceId','userId','loadShellPreferences','toggleShellFavorite','recordRecentShellPage'])assert.ok(source.includes(token),token)
  const app=read('resources/js/WorkforceApp.tsx')
  assert.ok(app.includes('canAccessPage(currentWorkspace'))
  assert.ok(app.includes('canAccessModuleHome(currentWorkspace'))
})
