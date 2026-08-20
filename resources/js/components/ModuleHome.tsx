import { ArrowRight, Clock3, Star } from 'lucide-react'
import type { AuthWorkspace } from '../auth/types'
import { canAccessPage, isPageVisibleInNavigation } from '../access'
import type { Page } from './Sidebar'
import { navigationForRole, pageTranslationKey } from '../navigation'
import { localizedPageDescription, pageShell, workspaceModule, type WorkspaceModuleId } from '../moduleCatalog'
import type { RecentShellPage } from '../shellPreferences'
import { Badge, Button, Card, CardBody, CardHeader, Grid, Inline, Pressable, Stack, Text } from '../design-system'
import { useLocalization } from '../i18n/LocalizationContext'
import WorkManagementHome from '../work-management/WorkManagementHome'
import TimeAttendanceHome from '../time-attendance/TimeAttendanceHome'
import PeopleHrHome from '../people-hr/PeopleHrHome'
import ClientsCommerceHome from '../clients-commerce/ClientsCommerceHome'
import FinancePayrollHome from '../finance-payroll/FinancePayrollHome'
import IntelligenceHome from '../intelligence/IntelligenceHome'
import AdministrationHome from '../administration/AdministrationHome'
import RoleStartHere from './RoleStartHere'

/** Render a self-documenting module home instead of dropping users directly into an arbitrary feature screen. */
export default function ModuleHome({workspace,moduleId,favorites,recent,onNavigate,onToggleFavorite}:{workspace:AuthWorkspace;moduleId:WorkspaceModuleId;favorites:Page[];recent:RecentShellPage[];onNavigate:(page:Page)=>void;onToggleFavorite:(page:Page)=>void}){
  const {t,text}=useLocalization()
  const module=workspaceModule(moduleId)
  const group=navigationForRole(workspace.role).find(item=>item.id===moduleId)
  const pages=(group?.items??[]).map(item=>item.id).filter(page=>canAccessPage(workspace,page)&&isPageVisibleInNavigation(workspace,page))
  if(!module||!pages.length)return null
  const Icon=module.icon
  const recentPages=recent.map(row=>row.page).filter(page=>pages.includes(page)).slice(0,5)
  const favoritePages=favorites.filter(page=>pages.includes(page))
  const primary=pages[0]
  return <div className="ui-page ui-module-home">
    <section className="ui-module-home__hero">
      <Inline gap={14} align="flex-start"><span className="ui-module-home__hero-icon"><Icon size={22}/></span><Stack gap={5} minWidth={0}><Inline gap={8} align="center" wrap="wrap"><h1>{text(module.label)}</h1><Badge>{pages.length} {pages.length===1?'area':'areas'}</Badge></Inline><Text size={13} color="var(--text-2)" lineHeight={1.6}>{text(module.description)}</Text><Inline gap={8} wrap="wrap" mt={3}><Button variant="primary" size="sm" onClick={()=>onNavigate(primary)}>Open {t(pageTranslationKey[primary])}<ArrowRight size={14}/></Button>{favoritePages.slice(0,2).map(page=><Button key={page} variant="outline" size="sm" onClick={()=>onNavigate(page)}><Star size={13} fill="currentColor"/> {t(pageTranslationKey[page])}</Button>)}</Inline></Stack></Inline>
    </section>

    <RoleStartHere workspace={workspace} moduleId={moduleId} onNavigate={onNavigate} compact/>

    {moduleId==='work-management'&&<WorkManagementHome workspace={workspace} onNavigate={onNavigate}/>}
    {moduleId==='time-attendance'&&<TimeAttendanceHome workspace={workspace} onNavigate={onNavigate}/>}
    {moduleId==='people-hr'&&<PeopleHrHome workspace={workspace} onNavigate={onNavigate}/>}
    {moduleId==='clients-commerce'&&<ClientsCommerceHome workspace={workspace} onNavigate={onNavigate}/>}
    {moduleId==='finance-payroll'&&<FinancePayrollHome workspace={workspace} onNavigate={onNavigate}/>}
    {moduleId==='intelligence'&&<IntelligenceHome workspace={workspace} onNavigate={onNavigate}/>}
    {moduleId==='administration'&&<AdministrationHome workspace={workspace} onNavigate={onNavigate}/>}

    {(favoritePages.length>0||recentPages.length>0)&&<Grid columns="repeat(auto-fit,minmax(260px,1fr))" gap={10}>
      {favoritePages.length>0&&<Card><CardHeader title="Favorites" description="Your pinned destinations in this module."/><CardBody><Stack gap={5}>{favoritePages.map(page=><Pressable key={page} className="ui-module-home__quick" onClick={()=>onNavigate(page)}><Star size={13} fill="currentColor"/><span>{t(pageTranslationKey[page])}</span><ArrowRight size={13}/></Pressable>)}</Stack></CardBody></Card>}
      {recentPages.length>0&&<Card><CardHeader title="Recent" description="Pages you visited most recently in this module."/><CardBody><Stack gap={5}>{recentPages.map(page=><Pressable key={page} className="ui-module-home__quick" onClick={()=>onNavigate(page)}><Clock3 size={13}/><span>{t(pageTranslationKey[page])}</span><ArrowRight size={13}/></Pressable>)}</Stack></CardBody></Card>}
    </Grid>}

    <Card><CardHeader title="What you can do here" description="Choose a destination by its purpose. Only pages available to your role and enabled modules are shown."/><CardBody><Grid columns="repeat(auto-fit,minmax(250px,1fr))" gap={9}>{pages.map(page=>{const meta=pageShell[page];const PageIcon=meta.icon;const favorite=favorites.includes(page);return <div className="ui-module-home__page" key={page}><Pressable className="ui-module-home__page-main" onClick={()=>onNavigate(page)}><span className="ui-module-home__page-icon"><PageIcon size={16}/></span><Stack gap={4} minWidth={0}><strong>{t(pageTranslationKey[page])}</strong><Text size={10.5} color="var(--text-3)" lineHeight={1.5}>{localizedPageDescription(page,t,text)}</Text></Stack><ArrowRight size={14}/></Pressable><Button variant="ghost" size="sm" className="ui-module-home__favorite" aria-pressed={favorite} aria-label={`${favorite?'Remove':'Add'} ${t(pageTranslationKey[page])} ${favorite?'from':'to'} favorites`} onClick={()=>onToggleFavorite(page)}><Star size={14} fill={favorite?'currentColor':'none'}/></Button></div>})}</Grid></CardBody></Card>
  </div>
}
