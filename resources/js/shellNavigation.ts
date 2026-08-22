import type { AuthWorkspace } from './auth/types'
import { canAccessPage, isPageVisibleInNavigation } from './access'
import type { Page } from './components/Sidebar'
import { navigationForRole } from './navigation'
import { pageShell, workspaceModule, type WorkspaceModuleId } from './moduleCatalog'

/** A browser-addressable WorkIntel shell destination. */
export type ShellDestination=
  | {kind:'page';page:Page}
  | {kind:'module';module:WorkspaceModuleId}

let browserHistoryBridgeInstalled=false

/**
 * Browser history traversal emits popstate for pushState/replaceState entries, not
 * hashchange. The private shell historically listened to hashchange, so Back and
 * Forward could update the address bar without updating the rendered page. Bridge
 * popstate into the shell's existing location event once per browser session.
 */
function ensureBrowserHistoryBridge(){
  if(browserHistoryBridgeInstalled||typeof window==='undefined')return
  browserHistoryBridgeInstalled=true
  window.addEventListener('popstate',()=>window.dispatchEvent(new Event('hashchange')))
}

/** Resolve a page or module home from the current hash while rejecting arbitrary values. */
export function shellDestinationFromLocation():ShellDestination|null{
  ensureBrowserHistoryBridge()
  const raw=window.location.hash.replace(/^#/,'').trim()
  if(!raw)return null
  if(raw.startsWith('module/')){
    const id=raw.slice('module/'.length) as WorkspaceModuleId
    return workspaceModule(id)?{kind:'module',module:id}:null
  }
  return Object.prototype.hasOwnProperty.call(pageShell,raw)?{kind:'page',page:raw as Page}:null
}

/** Write one safe shell destination to browser history for refresh and Back/Forward support. */
export function writeShellHistory(destination:ShellDestination,mode:'push'|'replace'='push'){
  ensureBrowserHistoryBridge()
  const hash=destination.kind==='module'?`#module/${destination.module}`:`#${destination.page}`
  if(window.location.hash===hash)return
  const url=`${window.location.pathname}${window.location.search}${hash}`
  const state=destination.kind==='module'?{workintelModule:destination.module}:{workintelPage:destination.page}
  if(mode==='replace')window.history.replaceState(state,'',url)
  else window.history.pushState(state,'',url)
}

/** Return role/module-visible pages that may be opened from a module home. */
export function accessiblePagesForModule(workspace:AuthWorkspace,moduleId:WorkspaceModuleId):Page[]{
  const group=navigationForRole(workspace.role).find(item=>item.id===moduleId)
  if(!group)return []
  return group.items.map(item=>item.id).filter(page=>canAccessPage(workspace,page)&&isPageVisibleInNavigation(workspace,page))
}

/** Return true only when a module has at least one accessible destination for this workspace member. */
export function canAccessModuleHome(workspace:AuthWorkspace,moduleId:WorkspaceModuleId){return accessiblePagesForModule(workspace,moduleId).length>0}
