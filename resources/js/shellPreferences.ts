import type { Page } from './components/Sidebar'
import { pageShell } from './moduleCatalog'

/** One recent page visit stored locally for fast personal shell navigation. */
export interface RecentShellPage{page:Page;visitedAt:number}
/** User-local shell preferences that never grant access or override server permissions. */
export interface ShellPreferences{favorites:Page[];recent:RecentShellPage[]}
const EMPTY:ShellPreferences={favorites:[],recent:[]}

/** Return a workspace/user-scoped local storage key without exposing preference state across accounts. */
function storageKey(workspaceId:number,userId:number){return `workintel-shell:${workspaceId}:${userId}:navigation`}
/** Keep only current known page IDs when browser storage contains stale or malformed data. */
function validPage(value:unknown):value is Page{return typeof value==='string'&&Object.prototype.hasOwnProperty.call(pageShell,value)}

/** Load safe navigation preferences from browser storage. */
export function loadShellPreferences(workspaceId:number,userId:number):ShellPreferences{
  if(typeof window==='undefined')return EMPTY
  try{
    const raw=JSON.parse(window.localStorage.getItem(storageKey(workspaceId,userId))||'null') as Partial<ShellPreferences>|null
    if(!raw)return EMPTY
    const favorites=Array.isArray(raw.favorites)?raw.favorites.filter(validPage).slice(0,12):[]
    const recent=Array.isArray(raw.recent)?raw.recent.filter(row=>row&&validPage(row.page)&&Number.isFinite(row.visitedAt)).slice(0,12):[]
    return {favorites:[...new Set(favorites)],recent}
  }catch{return EMPTY}
}

/** Persist personal shell navigation preferences; server authorization remains the source of truth. */
export function saveShellPreferences(workspaceId:number,userId:number,value:ShellPreferences){
  if(typeof window==='undefined')return
  window.localStorage.setItem(storageKey(workspaceId,userId),JSON.stringify(value))
}

/** Toggle one page in the favorites list while preserving deterministic order and limits. */
export function toggleShellFavorite(value:ShellPreferences,page:Page):ShellPreferences{
  const exists=value.favorites.includes(page)
  return {...value,favorites:exists?value.favorites.filter(item=>item!==page):[page,...value.favorites.filter(item=>item!==page)].slice(0,12)}
}

/** Record a successful page navigation as the most recent personal destination. */
export function recordRecentShellPage(value:ShellPreferences,page:Page):ShellPreferences{
  const recent=[{page,visitedAt:Date.now()},...value.recent.filter(item=>item.page!==page)].slice(0,12)
  return {...value,recent}
}
