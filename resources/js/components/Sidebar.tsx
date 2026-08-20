import { useEffect, useState } from 'react';
import type { LucideIcon } from 'lucide-react';
import { ChevronDown, ChevronRight, Gauge, PanelLeftClose, PanelLeftOpen, Star, UserRound, X } from 'lucide-react';
import type { AuthWorkspace } from '../auth/types';
import { canAccessPage, isPageVisibleInNavigation, moduleLabelForPage, roleLabel } from '../access';
import { Badge, IconButton, Tooltip, Pressable, Image, Box, Inline } from '../design-system';
import { useLocalization } from '../i18n/LocalizationContext';
import { navigationForRole, pageTranslationKey } from '../navigation';
import { pageShell, shellAreaForPage, workspaceModule, type WorkspaceModuleId } from '../moduleCatalog';
import type { TranslationKey } from '../i18n/catalog';
export type Page = 'overview' | 'live' | 'people' | 'hris' | 'performance' | 'finance' | 'payroll-compliance' | 'field' | 'enterprise' | 'automations' | 'organization' | 'projects' | 'tasks' | 'clients' | 'client-commerce' | 'time' | 'activity' | 'apps' | 'screenshots' | 'attendance' | 'schedule' | 'shifts' | 'leave' | 'payroll' | 'reports' | 'insights' | 'documents' | 'website' | 'media' | 'trash' | 'chat' | 'downloads' | 'devices' | 'billing' | 'settings' | 'access' | 'modules' | 'approvals' | 'account';
/** Describes the sidebar props data contract used by the WorkIntel client. */
interface SidebarProps {
    page: Page;
    setPage: (p: Page) => void;
    collapsed: boolean;
    setCollapsed: (v: boolean) => void;
    workspace: AuthWorkspace;
    activeModule?: WorkspaceModuleId | null;
    onOpenModule?: (id: WorkspaceModuleId) => void;
    favoritePages?: Page[];
    mobileOpen?: boolean;
    onMobileClose?: () => void;
}
/** Describes one rendered navigation item after locale and workspace overrides are resolved. */
interface NavItem {
    id: Page;
    label: string;
    icon: LucideIcon;
}
const roleKeys: Record<string, TranslationKey> = { owner: 'role.owner', admin: 'role.admin', hr: 'role.hr', manager: 'role.manager', 'team-lead': 'role.team_lead', 'payroll-manager': 'role.payroll_manager', employee: 'role.employee', client: 'role.client' };
/** Render one accessible sidebar navigation button with automatic collapsed-state tooltip. */
function NavButton({ item, active, collapsed, onClick }: {
    item: NavItem;
    active: boolean;
    collapsed: boolean;
    onClick: () => void;
}) {
    const Icon = item.icon;
    const button = <Pressable type="button" className={`ui-nav-item${active ? ' is-active' : ''}`} onClick={onClick} aria-current={active ? 'page' : undefined} aria-label={collapsed ? item.label : undefined}><span className="ui-nav-item__icon" aria-hidden="true"><Icon size={16} strokeWidth={1.75}/></span>{!collapsed && <span className="ui-nav-item__label">{item.label}</span>}</Pressable>;
    return collapsed ? <Tooltip content={item.label} placement="right">{button}</Tooltip> : button;
}
/** Return copy and icon for one module group while account/support remains a special utility surface. */
function groupPresentation(id: string, text: (value: string) => string) {
    if (id === 'account-support')
        return { label: text('Account & Support'), description: text('Personal account, installation and setup utilities.'), icon: UserRound };
    const module = workspaceModule(id as WorkspaceModuleId);
    return module ? { label: text(module.label), description: text(module.description), icon: module.icon } : { label: id, description: '', icon: Gauge };
}
/** Render role-aware module navigation, personal favorites and a true mobile drawer. */
export default function Sidebar({ page, setPage, collapsed, setCollapsed, workspace, activeModule, onOpenModule, favoritePages = [], mobileOpen = false, onMobileClose }: SidebarProps) {
    const { t, text } = useLocalization();
    const localizedRole = roleKeys[workspace.role] ? t(roleKeys[workspace.role]) : roleLabel(workspace.role);
    const activeArea = activeModule ?? shellAreaForPage(page);
    const [openGroups, setOpenGroups] = useState<Set<string>>(() => new Set([activeArea]));
    useEffect(() => {
        setOpenGroups(current => current.has(activeArea) ? current : new Set([...current, activeArea]));
    }, [activeArea]);
    const groups = navigationForRole(workspace.role).map(group => ({
        id: group.id,
        ...groupPresentation(group.id, text),
        items: group.items
            .filter(item => canAccessPage(workspace, item.id) && isPageVisibleInNavigation(workspace, item.id))
            .map(item => {
            const translated = t(item.labelKey ?? pageTranslationKey[item.id]);
            const configured = moduleLabelForPage(workspace, item.id, translated);
            return { id: item.id, label: configured, icon: pageShell[item.id].icon };
        }),
    })).filter(group => group.items.length);
    const favorites = favoritePages.filter(item => canAccessPage(workspace, item) && isPageVisibleInNavigation(workspace, item)).slice(0, 5).map(id => ({ id, label: t(pageTranslationKey[id]), icon: pageShell[id].icon }));
    /** Toggle one module group without affecting the currently active destination. */
    const toggleGroup = (id: string) => setOpenGroups(current => {
        const next = new Set(current);
        if (next.has(id))
            next.delete(id);
        else
            next.add(id);
        return next;
    });
    /** Navigate and dismiss the mobile drawer in one predictable action. */
    const navigate = (next: Page) => { setPage(next); onMobileClose?.(); };
    return <>
    {mobileOpen && <Pressable type="button" className="ui-sidebar-backdrop" aria-label="Close navigation" onClick={() => onMobileClose?.()}/>} 
    <Box as="aside" className={`ui-sidebar${collapsed ? ' is-collapsed' : ''}${mobileOpen ? ' is-mobile-open' : ''}`} width={collapsed ? 68 : 248} minWidth={collapsed ? 68 : 248}>
      <div className="ui-sidebar__brand"><div className="ui-sidebar__logo">{workspace.settings?.logoUrl ? <Image src={workspace.settings.logoUrl} alt="" width={22} height={22} objectFit="contain"/> : <Gauge size={16} strokeWidth={2}/>}</div>{!collapsed && <Box minWidth={0}><div className="ui-sidebar__brand-name">{workspace.settings?.appTitle || workspace.branding?.productName || workspace.name || 'WorkIntel'}</div><div className="ui-sidebar__brand-plan">{workspace.workspaceType === 'sandbox' ? 'Sandbox · ' : ''}{localizedRole} · {workspace.plan}</div></Box>}<IconButton className="ui-sidebar__mobile-close" variant="ghost" aria-label="Close navigation" onClick={() => onMobileClose?.()}><X size={17}/></IconButton></div>
      {!collapsed && <Box p="0 12px 8px"><Badge tone={workspace.role === 'owner' || workspace.role === 'admin' ? 'accent' : 'neutral'}>{localizedRole}</Badge></Box>}
      <nav className="ui-sidebar__scroll" aria-label={t('common.workspace_navigation')}>
        {!collapsed && favorites.length > 0 && <div className="ui-sidebar__favorites"><div className="ui-sidebar__favorites-title"><Star size={12} fill="currentColor"/> Favorites</div>{favorites.map(item => <NavButton key={`favorite-${item.id}`} item={item} active={!activeModule && (page === item.id || page === 'shifts' && item.id === 'schedule')} collapsed={false} onClick={() => navigate(item.id)}/>)}</div>}
        {groups.map(group => { const GroupIcon = group.icon; const open = collapsed || openGroups.has(group.id); const isActive = activeArea === group.id; return <div className={`ui-sidebar__section${open ? ' is-open' : ''}${isActive ? ' is-active' : ''}`} key={group.id}>{!collapsed && <div className="ui-sidebar__module-row"><Tooltip content={group.description} placement="right"><Pressable type="button" className="ui-sidebar__module" aria-current={isActive && activeModule ? 'page' : undefined} onClick={() => group.id === 'account-support' ? navigate(group.items[0].id) : onOpenModule?.(group.id as WorkspaceModuleId)}><Inline gap={8} align="center" minWidth={0}><span className="ui-sidebar__module-icon"><GroupIcon size={14}/></span><span className="ui-sidebar__module-label">{group.label}</span></Inline><span className="ui-sidebar__module-count">{group.items.length}</span></Pressable></Tooltip><IconButton size="sm" variant="ghost" className="ui-sidebar__module-toggle" aria-label={`${open ? 'Collapse' : 'Expand'} ${group.label}`} aria-expanded={open} onClick={() => toggleGroup(group.id)}>{open ? <ChevronDown size={13}/> : <ChevronRight size={13}/>}</IconButton></div>}{open && <div className="ui-sidebar__module-items">{group.items.map(item => <NavButton key={item.id} item={item} active={!activeModule && (page === item.id || page === 'shifts' && item.id === 'schedule')} collapsed={collapsed} onClick={() => navigate(item.id)}/>)}</div>}</div>; })}
      </nav>
      <div className="ui-sidebar__collapse"><Tooltip content={collapsed ? t('common.expand_sidebar') : t('common.collapse_sidebar')} placement={collapsed ? 'right' : 'top'}><IconButton variant="ghost" aria-label={collapsed ? t('common.expand_sidebar') : t('common.collapse_sidebar')} onClick={() => setCollapsed(!collapsed)} width="100%">{collapsed ? <PanelLeftOpen size={16}/> : <PanelLeftClose size={16}/>}</IconButton></Tooltip></div>
    </Box>
  </>;
}
