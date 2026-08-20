import { useEffect, useMemo, useRef, useState } from 'react'
import type { LucideIcon } from 'lucide-react'
import { Building2, CheckSquare2, FolderKanban, Images, Search, Star, UserRound } from 'lucide-react'
import type { AuthWorkspace } from '../auth/types'
import { canAccessPage, isPageVisibleInNavigation } from '../access'
import type { Page } from './Sidebar'
import { Badge, Kbd, Pressable, Input, Box, Text } from '../design-system'
import { useFocusTrap } from '../design-system/accessibility'
import { pageTranslationKey } from '../navigation'
import { pageShell, workspaceModules, workspaceModuleForPage, type WorkspaceModuleId } from '../moduleCatalog'
import { accessiblePagesForModule } from '../shellNavigation'
import type { RecentShellPage } from '../shellPreferences'
import { focusShellEntity } from '../shellEntityFocus'
import { apiRequest } from '../api/client'
import { useLocalization } from '../i18n/LocalizationContext'

type EntityKind='person'|'project'|'task'|'client'|'media'
type EntityResult={kind:EntityKind;id:number;page:Page;title:string;subtitle:string}
type PaletteResult={key:string;kind:'page'|'module'|'entity';title:string;subtitle:string;icon:LucideIcon;page?:Page;module?:WorkspaceModuleId;entity?:EntityResult;badge:string}
const ENTITY_ICONS:Record<EntityKind,LucideIcon>={person:UserRound,project:FolderKanban,task:CheckSquare2,client:Building2,media:Images}

/** Render one module-aware command center with page, module, recent/favorite and authorized entity discovery. */
export default function CommandPalette({open,onClose,onNavigate,onNavigateModule,workspace,favorites=[],recent=[]}:{open:boolean;onClose:()=>void;onNavigate:(page:Page)=>void;onNavigateModule:(module:WorkspaceModuleId)=>void;workspace:AuthWorkspace;favorites?:Page[];recent?:RecentShellPage[]}){
 const {t,text}=useLocalization();const [query,setQuery]=useState('');const [selected,setSelected]=useState(0);const [entities,setEntities]=useState<EntityResult[]>([]);const [entityLoading,setEntityLoading]=useState(false);const inputRef=useRef<HTMLInputElement>(null);const dialogRef=useRef<HTMLElement>(null);const entityRequest=useRef(0);useFocusTrap(open,dialogRef,{onEscape:onClose,initialFocusRef:inputRef})
 const pages=useMemo(()=>Object.values(pageShell).filter(meta=>meta.page!=='shifts'&&canAccessPage(workspace,meta.page)&&isPageVisibleInNavigation(workspace,meta.page)).map(meta=>{const module=workspaceModuleForPage(meta.page);const moduleLabel=module?text(module.label):text('Account & Support');const label=t(pageTranslationKey[meta.page]);const description=meta.descriptionKey?t(meta.descriptionKey):text(meta.description);return {...meta,label,moduleLabel,description,searchText:[label,moduleLabel,description,...meta.aliases].join(' ').toLocaleLowerCase()}}),[workspace.id,workspace.role,workspace.permissions.join('|'),JSON.stringify(workspace.modules??{}),t,text])
 const modules=useMemo(()=>workspaceModules.filter(module=>accessiblePagesForModule(workspace,module.id).length>0).map(module=>({module,title:text(module.label),description:text(module.description),searchText:[text(module.label),text(module.description)].join(' ').toLocaleLowerCase()})),[workspace.id,workspace.role,workspace.permissions.join('|'),JSON.stringify(workspace.modules??{}),text])
 useEffect(()=>{if(open){setQuery('');setSelected(0);setEntities([]);setTimeout(()=>inputRef.current?.focus(),0)}},[open])
 useEffect(()=>{if(!open||query.trim().length<2){entityRequest.current+=1;setEntities([]);setEntityLoading(false);return}const requestId=++entityRequest.current;const controller=new AbortController();const timer=window.setTimeout(()=>{setEntityLoading(true);apiRequest<{data:EntityResult[]}>(`/api/v1/search?q=${encodeURIComponent(query.trim())}&limit=4`,{workspaceId:workspace.id,silent:true,signal:controller.signal}).then(payload=>{if(entityRequest.current===requestId)setEntities(payload.data.filter(item=>Object.prototype.hasOwnProperty.call(pageShell,item.page)))}).catch(error=>{if(entityRequest.current===requestId&&(error as Error)?.name!=='AbortError')setEntities([])}).finally(()=>{if(entityRequest.current===requestId)setEntityLoading(false)})},180);return()=>{window.clearTimeout(timer);controller.abort()}},[open,query,workspace.id])
 const results=useMemo<PaletteResult[]>(()=>{
   const needle=query.trim().toLocaleLowerCase()
   const pageById=new Map(pages.map(item=>[item.page,item]))
   if(!needle){
     const rows:PaletteResult[]=[];const seen=new Set<Page>()
     for(const page of favorites){const item=pageById.get(page);if(!item||seen.has(page))continue;seen.add(page);rows.push({key:`favorite:${page}`,kind:'page',title:item.label,subtitle:`${item.moduleLabel} · ${item.description}`,icon:item.icon,page,badge:'Favorite'})}
     for(const row of recent){const item=pageById.get(row.page);if(!item||seen.has(row.page))continue;seen.add(row.page);rows.push({key:`recent:${row.page}`,kind:'page',title:item.label,subtitle:`Recent · ${item.moduleLabel}`,icon:item.icon,page:row.page,badge:'Recent'})}
     for(const item of modules)rows.push({key:`module:${item.module.id}`,kind:'module',title:item.title,subtitle:item.description,icon:item.module.icon,module:item.module.id,badge:'Module'})
     return rows.slice(0,18)
   }
   const local:PaletteResult[]=[
     ...modules.filter(item=>item.searchText.includes(needle)).map(item=>({key:`module:${item.module.id}`,kind:'module' as const,title:item.title,subtitle:item.description,icon:item.module.icon,module:item.module.id,badge:'Module'})),
     ...pages.filter(item=>item.searchText.includes(needle)).map(item=>({key:`page:${item.page}`,kind:'page' as const,title:item.label,subtitle:`${item.moduleLabel} · ${item.description}`,icon:item.icon,page:item.page,badge:'Page'})),
   ]
   const remote=entities.map(entity=>({key:`entity:${entity.kind}:${entity.id}`,kind:'entity' as const,title:entity.title,subtitle:entity.subtitle||t(pageTranslationKey[entity.page]),icon:ENTITY_ICONS[entity.kind]??Search,entity,badge:entity.kind.charAt(0).toUpperCase()+entity.kind.slice(1)}))
   return [...local,...remote].slice(0,24)
 },[query,pages,modules,entities,favorites,recent,t])
 useEffect(()=>{setSelected(current=>Math.min(current,Math.max(0,results.length-1)))},[results.length])
 /** Execute one palette result through the same permission-aware shell navigation. */
 const choose=(item:PaletteResult)=>{if(item.kind==='module'&&item.module)onNavigateModule(item.module);else if(item.kind==='entity'&&item.entity){focusShellEntity({...item.entity,query:item.title});onNavigate(item.entity.page)}else if(item.page)onNavigate(item.page);onClose()}
 useEffect(()=>{if(!open)return;/** Handle keyboard navigation without rebuilding command definitions. */ const handler=(event:KeyboardEvent)=>{if(event.key==='ArrowDown'){event.preventDefault();setSelected(value=>Math.min(value+1,Math.max(0,results.length-1)))}if(event.key==='ArrowUp'){event.preventDefault();setSelected(value=>Math.max(value-1,0))}if(event.key==='Enter'){const item=results[selected];if(item)choose(item)}};window.addEventListener('keydown',handler);return()=>window.removeEventListener('keydown',handler)},[open,onClose,onNavigate,onNavigateModule,selected,results])
 if(!open)return null
 return <div className="ui-backdrop ui-command-backdrop" onMouseDown={event=>{if(event.currentTarget===event.target)onClose()}}><section ref={dialogRef} tabIndex={-1} className="ui-command" role="dialog" aria-modal="true" aria-label="Global WorkIntel search"><div className="ui-command__search"><Search size={16} color="var(--text-3)"/><Input ref={inputRef} value={query} onChange={event=>{setQuery(event.target.value);setSelected(0)}} className="ui-command__input" aria-label="Search pages modules and workspace records" placeholder="Search modules, people, projects, tasks, clients or files…"/><Kbd>ESC</Kbd></div><div className="ui-command__results"><div className="ui-command__label">{query.trim()?entityLoading?'Searching workspace…':'Pages, modules & workspace records':'Favorites, recent & modules'}</div>{results.map((item,index)=>{const Icon=item.icon;return <Pressable key={item.key} className={`ui-command__item${index===selected?' is-selected':''}`} onMouseEnter={()=>setSelected(index)} onClick={()=>choose(item)}><span className="ui-command__item-icon"><Icon size={15}/></span><span className="ui-command__item-main"><span className="ui-command__item-title">{item.title}</span><Text display="block" className="ui-command__item-sub">{item.subtitle}</Text></span><Badge>{item.badge==='Favorite'?<><Star size={10} fill="currentColor"/> Favorite</>:item.badge}</Badge></Pressable>})}{!results.length&&!entityLoading&&<Box className="ui-card-description" p={16}>No accessible module, page or workspace record matched your search.</Box>}</div><div className="ui-command__footer"><span><Kbd>↵</Kbd> {t('common.select')}</span><span><Kbd>↑↓</Kbd> {t('common.navigate')}</span><span><Kbd>ESC</Kbd> {t('common.close')}</span></div></section></div>
}
