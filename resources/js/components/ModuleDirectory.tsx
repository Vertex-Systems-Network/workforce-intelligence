import { ArrowRight, UserRound } from 'lucide-react'
import type { AuthWorkspace } from '../auth/types'
import type { Page } from './Sidebar'
import { canAccessPage, isPageVisibleInNavigation } from '../access'
import { navigationForRole, pageTranslationKey } from '../navigation'
import { workspaceModule, type WorkspaceModuleId } from '../moduleCatalog'
import { Badge, Card, CardBody, CardHeader, Grid, Inline, Pressable, Stack, Text } from '../design-system'
import { useLocalization } from '../i18n/LocalizationContext'

/** Present role-accessible business areas with plain-language purpose copy so the workspace is self-discoverable. */
export default function ModuleDirectory({workspace,onNavigate,onOpenModule,compact=false}:{workspace:AuthWorkspace;onNavigate:(page:Page)=>void;onOpenModule?:(moduleId:WorkspaceModuleId)=>void;compact?:boolean}){
  const {t,text}=useLocalization()
  const groups=navigationForRole(workspace.role).map(group=>{
    const items=group.items.filter(item=>canAccessPage(workspace,item.id)&&isPageVisibleInNavigation(workspace,item.id))
    if(!items.length)return null
    if(group.id==='account-support')return {id:group.id,label:text('Account & Support'),description:text('Personal account, installation and setup utilities.'),icon:UserRound,items}
    const module=workspaceModule(group.id as WorkspaceModuleId)
    return module?{id:group.id,label:text(module.label),description:text(module.description),icon:module.icon,items}:null
  }).filter(Boolean) as Array<{id:string;label:string;description:string;icon:any;items:Array<{id:Page;labelKey?:any}>}>
  return <Card className="ui-module-directory"><CardHeader title="Workspace areas" description="Choose a business area first. Only modules and pages available to your current role are shown."/><CardBody><Grid columns={compact?'repeat(auto-fit,minmax(210px,1fr))':'repeat(auto-fit,minmax(240px,1fr))'} gap={9}>{groups.map(group=>{const Icon=group.icon;const first=group.items[0];return <Pressable key={group.id} type="button" className="ui-module-tile" onClick={()=>group.id==='account-support'||!onOpenModule?onNavigate(first.id):onOpenModule(group.id as WorkspaceModuleId)}><Inline align="flex-start" gap={10}><span className="ui-module-tile__icon"><Icon size={16}/></span><Stack gap={4} minWidth={0}><Inline gap={6} align="center" wrap="wrap"><strong>{group.label}</strong><Badge>{group.items.length}</Badge></Inline><Text size={10.5} color="var(--text-3)" lineHeight={1.45}>{group.description}</Text><Text size={9.5} color="var(--text-3)" className="ui-module-tile__pages">{group.items.slice(0,3).map(item=>t(item.labelKey??pageTranslationKey[item.id])).join(' · ')}{group.items.length>3?' · …':''}</Text></Stack><ArrowRight size={14} className="ui-module-tile__arrow"/></Inline></Pressable>})}</Grid></CardBody></Card>
}
