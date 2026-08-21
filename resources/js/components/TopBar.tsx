import { Bell, Boxes, ChevronDown, LogOut, Menu, Moon, Play, Search, ShieldCheck, SlidersHorizontal, Sun } from 'lucide-react'
import type { AuthUser, AuthWorkspace } from '../auth/types'
import { hasAnyPermission, roleLabel } from '../access'
import AttendanceQuickAction from './AttendanceQuickAction'
import { Avatar, Button, Dropdown, IconButton, Kbd, Tooltip, Pressable } from '../design-system'
import { useTheme } from '../theme'
import { useLocalization } from '../i18n/LocalizationContext'
import type { TranslationKey } from '../i18n/catalog'
import { pageTranslationKey } from '../navigation'
import { pageShell, workspaceModule, workspaceModuleForPage, type WorkspaceModuleId } from '../moduleCatalog'
import type { Page } from './Sidebar'
import LanguageSwitcher from '../i18n/LanguageSwitcher'
import './TopBar.css'

/** Describes the top bar props data contract used by the WorkIntel client. */ interface TopBarProps {
  page:Page; activeModule?:WorkspaceModuleId|null; user:AuthUser; workspace:AuthWorkspace; workspaces:AuthWorkspace[]
  onWorkspaceChange:(workspaceId:number)=>void; onSignOut:()=>void; onTimerClick:()=>void; onCmdK:()=>void; onNotifications:()=>void; onCustomize:()=>void; onNavigate:(page:string)=>void; onOpenSidebar:()=>void; notifCount:number
}
const roleKeys:Record<string,TranslationKey>={owner:'role.owner',admin:'role.admin',hr:'role.hr',manager:'role.manager','team-lead':'role.team_lead','payroll-manager':'role.payroll_manager',employee:'role.employee',client:'role.client'}
/** Handles the top bar operation for the WorkIntel client. */ export default function TopBar({page,activeModule,user,workspace,workspaces,onWorkspaceChange,onSignOut,onTimerClick,onCmdK,onNotifications,onCustomize,onNavigate,onOpenSidebar,notifCount}:TopBarProps){
 const {t,text}=useLocalization();const {theme,toggleTheme}=useTheme();const fullName=`${user.firstName} ${user.lastName}`
 /** Handles the localized role operation for the WorkIntel client. */ const localizedRole=(role:string)=>roleKeys[role]?t(roleKeys[role]):roleLabel(role)
 const workspaceItems=[{header:true,label:t('common.workspaces')},...workspaces.map(item=>({label:item.name,meta:item.id===workspace.id?t('common.current'):`${localizedRole(item.role)} · ${item.plan}`,onClick:()=>onWorkspaceChange(item.id)}))]
 const profileItems=[{header:true,label:fullName},{label:t('common.my_access_role'),icon:<ShieldCheck size={14}/>,meta:localizedRole(workspace.role),onClick:()=>onNavigate('account')},...(user.platformOperator?[{label:text('Platform Console'),icon:<Boxes size={14}/>,meta:text('Global operator surface'),onClick:()=>window.location.assign('/seller')}]:[]),{separator:true},{label:t('common.sign_out'),icon:<LogOut size={14}/>,onClick:onSignOut}]
 const canTrackTime=hasAnyPermission(workspace,['time.view_own','time.view_team','time.view_all','time.manage'])
 const module=activeModule?workspaceModule(activeModule):workspaceModuleForPage(page);const areaLabel=module?text(module.label):text('Account & Support');const pageLabel=activeModule?text('Module home'):t(pageTranslationKey[page])
 return <header className="ui-topbar">
  <IconButton className="ui-topbar__mobile-menu" variant="ghost" aria-label="Open navigation" onClick={onOpenSidebar}><Menu size={18}/></IconButton>
  <Dropdown align="left" trigger={<Button variant="outline" size="sm"><span className="ui-topbar__workspace-logo">{workspace.name.charAt(0).toUpperCase()}</span><span>{workspace.name}</span><ChevronDown size={12}/></Button>} items={workspaceItems}/>
  <div className="ui-topbar__crumb"><span className="ui-topbar__crumb-module">{areaLabel}</span><span>/</span><span className="ui-topbar__crumb-page">{pageLabel}</span></div><div className="ui-topbar__spacer"/>
  <AttendanceQuickAction/>
  <Button variant="outline" size="sm" className="ui-topbar__search" onClick={onCmdK}><Search size={13}/><span className="ui-topbar__search-label">{t('common.search_pages')}</span><Kbd>⌘K</Kbd></Button>
  <LanguageSwitcher compact/>
  {canTrackTime&&<Button variant="primary" size="sm" onClick={onTimerClick}><Play size={13} fill="currentColor"/> {t('common.timer')}</Button>}
  <Tooltip content={theme==='dark'?t('common.switch_light'):t('common.switch_dark')}><IconButton variant="outline" onClick={toggleTheme} aria-label={t('common.toggle_theme')}>{theme==='dark'?<Sun size={15}/>:<Moon size={15}/>}</IconButton></Tooltip>
  <Tooltip content={t('common.customize_page')}><IconButton variant="outline" onClick={onCustomize} aria-label={t('common.customize_page')}><SlidersHorizontal size={15}/></IconButton></Tooltip>
  <Tooltip content={t('common.notifications')}><IconButton variant="outline" onClick={onNotifications} aria-label={t('common.notifications')} className="ui-topbar__notification"><Bell size={15}/>{notifCount>0&&<span className="ui-topbar__notification-dot" aria-hidden="true"/>}</IconButton></Tooltip>
  <Dropdown trigger={<Pressable type="button" className="ui-avatar-trigger" aria-label={`${fullName} account menu`}><Avatar name={fullName}/></Pressable>} items={profileItems}/>
 </header>
}
