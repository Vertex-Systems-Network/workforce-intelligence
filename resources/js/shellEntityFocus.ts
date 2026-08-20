import { useEffect } from 'react'
import type { Page } from './components/Sidebar'

/** Minimal cross-page entity focus contract used by global shell search. */
export interface ShellEntityFocus{page:Page;id:number;kind:string;title:string;query:string}
const KEY='workintel-shell-entity-focus'

/** Queue and broadcast an entity focus so both mounted and lazy-mounted destination pages can consume it. */
export function focusShellEntity(value:ShellEntityFocus){
  try{window.sessionStorage.setItem(KEY,JSON.stringify(value))}catch{}
  window.dispatchEvent(new CustomEvent<ShellEntityFocus>('workintel:entity-focus',{detail:value}))
}

/** Consume a queued entity focus only on its intended destination page. */
function consumeShellEntityFocus(page:Page):ShellEntityFocus|null{
  try{
    const raw=window.sessionStorage.getItem(KEY)
    if(!raw)return null
    const value=JSON.parse(raw) as ShellEntityFocus
    if(value?.page!==page)return null
    window.sessionStorage.removeItem(KEY)
    return value
  }catch{return null}
}

/** Apply global entity discovery to a page's existing local search field without coupling page data to the shell. */
export function useShellEntitySearch(page:Page,setSearch:(value:string)=>void,onFocus?:(value:ShellEntityFocus)=>void){
  useEffect(()=>{
    /** Apply the result query and allow the destination to clear filters that could hide the chosen entity. */
    const apply=(value:ShellEntityFocus)=>{setSearch(value.query);onFocus?.(value)}
    const queued=consumeShellEntityFocus(page)
    if(queued)apply(queued)
    /** Apply same-page entity discovery immediately while preserving queued navigation for lazy pages. */
    const handler=(event:Event)=>{const value=(event as CustomEvent<ShellEntityFocus>).detail;if(value?.page===page){apply(value);try{window.sessionStorage.removeItem(KEY)}catch{}}}
    window.addEventListener('workintel:entity-focus',handler)
    return()=>window.removeEventListener('workintel:entity-focus',handler)
  },[page,setSearch,onFocus])
}

