import { useCallback, useEffect, useMemo, useState } from 'react'
import { apiRequest } from '../api/client'
import type { AuthWorkspace } from '../auth/types'
import { roleGuideForWorkspace } from './roleHelpCatalog'

export type RoleExperienceState={completed:string[];dismissedHelp:string[];seen:boolean;startedAt:string|null;completedAt:string|null;roleSeen:string|null;version:number}
const empty:RoleExperienceState={completed:[],dismissedHelp:[],seen:false,startedAt:null,completedAt:null,roleSeen:null,version:1}
const pageKey='role-help-v1'

/** Normalize persisted generic page-preference JSON into the M11 role-experience contract. */
function normalize(value:any):RoleExperienceState{return{completed:Array.isArray(value?.onboarding_completed)?value.onboarding_completed.filter((v:any)=>typeof v==='string'):[],dismissedHelp:Array.isArray(value?.help_dismissed)?value.help_dismissed.filter((v:any)=>typeof v==='string'):[],seen:Boolean(value?.help_seen),startedAt:typeof value?.onboarding_started_at==='string'?value.onboarding_started_at:null,completedAt:typeof value?.onboarding_completed_at==='string'?value.onboarding_completed_at:null,roleSeen:typeof value?.role_seen==='string'?value.role_seen:null,version:Number(value?.checklist_version)||1}}
/** Convert M11 role experience into the allowlisted page-preference payload. */
function encode(value:RoleExperienceState){return{onboarding_completed:value.completed,help_dismissed:value.dismissedHelp,help_seen:value.seen,onboarding_started_at:value.startedAt,onboarding_completed_at:value.completedAt,role_seen:value.roleSeen,checklist_version:value.version}}

/** Loads and persists per-user/per-workspace M11 onboarding/help progress using the existing UI preference store. */
export function useRoleExperience(workspace:AuthWorkspace|undefined){
 const [state,setState]=useState<RoleExperienceState>(empty),[loading,setLoading]=useState(true),[error,setError]=useState('')
 const guide=useMemo(()=>workspace?roleGuideForWorkspace(workspace):null,[workspace?.id,workspace?.role,workspace?.permissions.join('|'),JSON.stringify(workspace?.modules??{})])
 const load=useCallback(async()=>{if(!workspace)return;setLoading(true);setError('');try{const response=await apiRequest<{data:Record<string,unknown>}>(`/api/v1/ui/preferences/${pageKey}`,{workspaceId:workspace.id,silent:true});setState(normalize(response.data))}catch(reason){setError(reason instanceof Error?reason.message:'Could not load your Start Here progress.')}finally{setLoading(false)}},[workspace?.id])
 useEffect(()=>{void load()},[load])
 useEffect(()=>{/** Reload personal M11 state after another in-app surface saves progress. */ const handler=()=>void load();window.addEventListener('workintel:role-experience-changed',handler);return()=>window.removeEventListener('workintel:role-experience-changed',handler)},[load])
 /** Persist one complete role-experience state and synchronize other open M11 surfaces. */
 const save=async(next:RoleExperienceState)=>{if(!workspace)return;setState(next);try{await apiRequest(`/api/v1/ui/preferences/${pageKey}`,{method:'PUT',workspaceId:workspace.id,silent:true,body:JSON.stringify({settings:encode(next)})});window.dispatchEvent(new CustomEvent('workintel:role-experience-changed'))}catch(reason){setError(reason instanceof Error?reason.message:'Could not save your Start Here progress.') ;await load()}}
 /** Toggle one checklist task without changing authorization or shared workspace data. */
 const toggleTask=async(id:string)=>{const complete=state.completed.includes(id),completed=complete?state.completed.filter(item=>item!==id):[...state.completed,id];const visibleIds=new Set(guide?.tasks.map(task=>task.id)??[]),allDone=visibleIds.size>0&&[...visibleIds].every(task=>completed.includes(task));await save({...state,completed,seen:true,startedAt:state.startedAt??new Date().toISOString(),completedAt:allDone?new Date().toISOString():null,roleSeen:workspace?.role??state.roleSeen})}
 /** Mark the M11 experience as seen without pretending checklist work is complete. */
 const markSeen=async()=>save({...state,seen:true,startedAt:state.startedAt??new Date().toISOString(),roleSeen:workspace?.role??state.roleSeen})
 /** Dismiss one contextual help hint for this member only. */
 const dismissHelp=async(key:string)=>save({...state,dismissedHelp:state.dismissedHelp.includes(key)?state.dismissedHelp:[...state.dismissedHelp,key].slice(-80),seen:true,roleSeen:workspace?.role??state.roleSeen})
 /** Reset personal M11 progress so the guided setup can be replayed. */
 const reset=async()=>{if(!workspace)return;await apiRequest(`/api/v1/ui/preferences/${pageKey}`,{method:'DELETE',workspaceId:workspace.id});setState(empty);window.dispatchEvent(new CustomEvent('workintel:role-experience-changed'))}
 const visibleCompleted=guide?.tasks.filter(task=>state.completed.includes(task.id)).length??0
 return{state,guide,loading,error,visibleCompleted,total:guide?.tasks.length??0,toggleTask,markSeen,dismissHelp,reset,reload:load}
}
