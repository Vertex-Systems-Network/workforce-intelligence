import { lazy, Suspense, useEffect, useState } from 'react';
import { Pause, Play, Square, X } from 'lucide-react';
import Sidebar, { type Page } from './components/Sidebar';
import TopBar from './components/TopBar';
import CommandPalette from './components/CommandPalette';
import ShellContextBar from './components/ShellContextBar';
import ModuleHome from './components/ModuleHome';
import HelpCenter from './components/HelpCenter';
import FirstRunGuide from './components/FirstRunGuide';
import { AppBootLoader, BoardLoadingState, FormLoadingState, MediaLibraryLoadingState, PageLoadingState, ProfileLoadingState, TableLoadingState } from './components/LoadingStates';
const Overview = lazy(() => import('./pages/Overview'));
const Live = lazy(() => import('./pages/Live'));
const People = lazy(() => import('./pages/People'));
const Hris = lazy(() => import('./pages/Hris'));
const Performance = lazy(() => import('./pages/Performance'));
const FinanceOps = lazy(() => import('./pages/FinanceOps'));
const PayrollCompliance = lazy(() => import('./pages/PayrollCompliance'));
const FieldWorkforce = lazy(() => import('./pages/FieldWorkforce'));
const Enterprise = lazy(() => import('./pages/Enterprise'));
const Automations = lazy(() => import('./pages/Automations'));
const Organization = lazy(() => import('./pages/Organization'));
const Projects = lazy(() => import('./pages/Projects'));
const Tasks = lazy(() => import('./pages/Tasks'));
const Timesheets = lazy(() => import('./pages/Timesheets'));
const Activity = lazy(() => import('./pages/Activity'));
const AppsSites = lazy(() => import('./pages/AppsSites'));
const Screenshots = lazy(() => import('./pages/Screenshots'));
const Attendance = lazy(() => import('./pages/Attendance'));
const SchedulingHub = lazy(() => import('./pages/SchedulingHub'));
const Shifts = lazy(() => import('./pages/Shifts'));
const Leave = lazy(() => import('./pages/Leave'));
const Payroll = lazy(() => import('./pages/Payroll'));
const Reports = lazy(() => import('./pages/Reports'));
const Documents = lazy(() => import('./pages/Documents'));
const WebsiteStudio = lazy(() => import('./pages/WebsiteStudio'));
const MediaLibrary = lazy(() => import('./pages/MediaLibrary'));
const TrashCenter = lazy(() => import('./pages/TrashCenter'));
const Chat = lazy(() => import('./pages/Chat'));
const Insights = lazy(() => import('./pages/Insights'));
const Clients = lazy(() => import('./pages/Clients'));
const ClientCommerce = lazy(() => import('./pages/ClientCommerce'));
const Downloads = lazy(() => import('./pages/Downloads'));
const Devices = lazy(() => import('./pages/Devices'));
const Billing = lazy(() => import('./pages/Billing'));
const Settings = lazy(() => import('./pages/Settings'));
const AccessControl = lazy(() => import('./pages/AccessControl'));
const Modules = lazy(() => import('./pages/Modules'));
const MyAccess = lazy(() => import('./pages/MyAccess'));
const Approvals = lazy(() => import('./pages/Approvals'));
import AuthScreen from './pages/auth/AuthScreen';
import ForcePasswordChange from './pages/auth/ForcePasswordChange';
import { useAuth } from './auth/AuthContext';
import { apiRequest } from './api/client';
import { canAccessPage, defaultPageForRole } from './access';
import { Alert, Button, Drawer, Field, IconButton, Select, Switch, Pressable, Box, Inline, Text, Link, Option } from './design-system';
import { PageCustomizationShell } from './design-system/PageCustomization';
import { useLocalization } from './i18n/LocalizationContext';
import { type WorkspaceModuleId } from './moduleCatalog';
import { canAccessModuleHome, shellDestinationFromLocation, writeShellHistory } from './shellNavigation';
import { loadShellPreferences, recordRecentShellPage, saveShellPreferences, toggleShellFavorite, type ShellPreferences } from './shellPreferences';
/** Returns a loading skeleton whose geometry matches the destination page category. */
function pageLoadingFallback(page: Page) {
    if (page === 'media')
        return <MediaLibraryLoadingState />;
    if (page === 'tasks')
        return <BoardLoadingState />;
    if (page === 'account')
        return <ProfileLoadingState />;
    if (['people', 'clients', 'projects', 'time', 'activity', 'attendance', 'leave', 'payroll', 'reports', 'devices', 'approvals', 'trash'].includes(page))
        return <TableLoadingState />;
    if (['settings', 'access', 'modules', 'billing', 'website'].includes(page))
        return <FormLoadingState />;
    return <PageLoadingState />;
}
/** Handles the timer floating operation for the WorkIntel client. */ function TimerFloating({ onClose, workspaceId }: {
    onClose: () => void;
    workspaceId: number;
}) {
    const { t } = useLocalization();
    type TimerEvent = {
        id: number;
        event_type: string;
        occurred_at: string;
    };
    type Timer = {
        id: number;
        status: 'running' | 'paused';
        started_at: string;
        billable: boolean;
        project_id: number | null;
        task_id: number | null;
        project: {
            id: number;
            name: string;
        } | null;
        task: {
            id: number;
            title: string;
        } | null;
        events: TimerEvent[];
    };
    type ProjectOption = {
        id: number;
        name: string;
    };
    type TaskOption = {
        id: number;
        project_id: number;
        title: string;
        billable: boolean;
    };
    const [timer, setTimer] = useState<Timer | null>(null);
    const [projects, setProjects] = useState<ProjectOption[]>([]);
    const [tasks, setTasks] = useState<TaskOption[]>([]);
    const [projectId, setProjectId] = useState('');
    const [taskId, setTaskId] = useState('');
    const [billable, setBillable] = useState(true);
    const [now, setNow] = useState(Date.now());
    const [loading, setLoading] = useState(true);
    const [actionLoading, setActionLoading] = useState(false);
    const [error, setError] = useState('');
    /** Loads load data required by the current view. */ const load = async () => {
        if (!workspaceId)
            return;
        setLoading(true);
        setError('');
        try {
            const [timerPayload, projectPayload, taskPayload] = await Promise.all([
                apiRequest<{
                    timer: Timer | null;
                }>('/api/v1/timer', { workspaceId }),
                apiRequest<{
                    data: ProjectOption[];
                }>('/api/v1/projects', { workspaceId }),
                apiRequest<{
                    data: TaskOption[];
                }>('/api/v1/tasks', { workspaceId }),
            ]);
            setTimer(timerPayload.timer);
            setProjects(projectPayload.data);
            setTasks(taskPayload.data);
            if (!timerPayload.timer && !projectId && projectPayload.data[0])
                setProjectId(String(projectPayload.data[0].id));
        }
        catch (err) {
            setError(err instanceof Error ? err.message : t('shell.timer_load_error'));
        }
        finally {
            setLoading(false);
        }
    };
    useEffect(() => { void load(); }, [workspaceId]);
    useEffect(() => { /** Handles the handler event for the current workflow. */ const handler = () => void load(); window.addEventListener('workintel:timer-changed', handler); return () => window.removeEventListener('workintel:timer-changed', handler); }, [workspaceId]);
    useEffect(() => { if (!timer || timer.status !== 'running')
        return; const id = window.setInterval(() => setNow(Date.now()), 1000); return () => window.clearInterval(id); }, [timer]);
    const filteredTasks = tasks.filter(task => !projectId || task.project_id === Number(projectId));
    /** Handles the tracked seconds operation for the WorkIntel client. */ const trackedSeconds = () => {
        if (!timer)
            return 0;
        const end = now;
        const started = new Date(timer.started_at).getTime();
        let pausedAt: number | null = null;
        let paused = 0;
        for (const event of [...(timer.events || [])].sort((a, b) => new Date(a.occurred_at).getTime() - new Date(b.occurred_at).getTime())) {
            const time = new Date(event.occurred_at).getTime();
            if (event.event_type === 'timer.paused')
                pausedAt = time;
            if (event.event_type === 'timer.resumed' && pausedAt !== null) {
                paused += Math.max(0, time - pausedAt);
                pausedAt = null;
            }
        }
        if (pausedAt !== null)
            paused += Math.max(0, end - pausedAt);
        return Math.max(0, Math.floor((end - started - paused) / 1000));
    };
    /** Formats fmt data for display. */ const fmt = (value: number) => { const h = Math.floor(value / 3600).toString().padStart(2, '0'); const m = Math.floor((value % 3600) / 60).toString().padStart(2, '0'); const sec = (value % 60).toString().padStart(2, '0'); return `${h}:${m}:${sec}`; };
    /** Handles the start operation for the WorkIntel client. */ const start = async () => { if (!projectId)
        return; setActionLoading(true); setError(''); try {
        const selectedTask = tasks.find(item => item.id === Number(taskId));
        const payload = await apiRequest<{
            timer: Timer;
        }>('/api/v1/timer/start', { method: 'POST', workspaceId, body: JSON.stringify({ project_id: Number(projectId), task_id: taskId ? Number(taskId) : null, billable: selectedTask?.billable ?? billable }) });
        setTimer(payload.timer);
        setNow(Date.now());
    }
    catch (err) {
        setError(err instanceof Error ? err.message : t('shell.timer_start_error'));
    }
    finally {
        setActionLoading(false);
    } };
    /** Updates update timer state for the current workflow. */ const updateTimer = async (action: 'pause' | 'resume') => { if (!timer)
        return; setActionLoading(true); setError(''); try {
        const payload = await apiRequest<{
            timer: Timer;
        }>(`/api/v1/timer/${timer.id}/${action}`, { method: 'POST', workspaceId });
        setTimer(payload.timer);
        setNow(Date.now());
    }
    catch (err) {
        setError(err instanceof Error ? err.message : `Could not ${action} timer.`);
    }
    finally {
        setActionLoading(false);
    } };
    /** Handles the stop operation for the WorkIntel client. */ const stop = async () => { if (!timer)
        return; setActionLoading(true); setError(''); try {
        await apiRequest(`/api/v1/timer/${timer.id}/stop`, { method: 'POST', workspaceId });
        setTimer(null);
        setNow(Date.now());
        window.dispatchEvent(new CustomEvent('workintel:time-entry-created'));
    }
    catch (err) {
        setError(err instanceof Error ? err.message : t('shell.timer_stop_error'));
    }
    finally {
        setActionLoading(false);
    } };
    return <Box position="fixed" right={24} bottom={24} zIndex={70} width={360} maxWidth="calc(100vw - 48px)">
    <Box className="ui-card" boxShadow="var(--wi-shadow-popover)" overflow="hidden">
      <div className="ui-card__header"><div><h3 className="ui-card-title">{timer ? timer.status === 'paused' ? t('shell.timer_paused') : t('shell.timer_running') : t('shell.start_timer')}</h3><p className="ui-card-description">{timer ? `${timer.project?.name || t('shell.no_project')}${timer.task ? ` · ${timer.task.title}` : ''}` : t('shell.track_project_task')}</p></div><IconButton variant="ghost" size="sm" onClick={onClose} aria-label={t('shell.close_timer')}><X size={15}/></IconButton></div>
      <div className="ui-card__body">
        <Box className="stat-num" size={36} weight={700} textAlign="center" mb={18} color={timer ? 'var(--text)' : 'var(--text-3)'}>{fmt(trackedSeconds())}</Box>
        {error && <Alert tone="danger">{error}</Alert>}
        {!timer && <Box display="grid" gap={10} mt={error ? 10 : 0}>
          <Field label={t('field.project')}><Select value={projectId} onChange={e => { setProjectId(e.target.value); setTaskId(''); }} disabled={loading}><Option value="">{t('shell.select_project')}</Option>{projects.map(project => <Option key={project.id} value={project.id}>{project.name}</Option>)}</Select></Field>
          <Field label={t('field.task')}><Select value={taskId} onChange={e => { setTaskId(e.target.value); const task = tasks.find(item => item.id === Number(e.target.value)); if (task)
            setBillable(task.billable); }} disabled={loading || !projectId}><Option value="">{t('shell.no_task')}</Option>{filteredTasks.map(task => <Option key={task.id} value={task.id}>{task.title}</Option>)}</Select></Field>
          <Inline align="center" justify="space-between"><Text color="var(--text-2)" size={12}>{t('shell.billable_time')}</Text><Switch checked={billable} onChange={setBillable} label={t('shell.billable_time')}/></Inline>
        </Box>}
      </div>
      <Inline className="ui-card__footer" gap={8}>
        {timer ? timer.status === 'running' ? <><Button variant="secondary" loading={actionLoading} onClick={() => void updateTimer('pause')} flex={1}><Pause size={14}/> {t('shell.pause')}</Button><Button variant="danger" disabled={actionLoading} onClick={() => void stop()} flex={1}><Square size={13} fill="currentColor"/> {t('shell.stop')}</Button></> : <><Button variant="primary" loading={actionLoading} onClick={() => void updateTimer('resume')} flex={1}><Play size={14} fill="currentColor"/> {t('shell.resume')}</Button><Button variant="danger" disabled={actionLoading} onClick={() => void stop()} flex={1}><Square size={13} fill="currentColor"/> {t('shell.stop')}</Button></> : <Button variant="primary" loading={actionLoading || loading} disabled={!projectId} onClick={() => void start()} flex={1}><Play size={14} fill="currentColor"/> {t('shell.start_timer')}</Button>}
      </Inline>
    </Box>
  </Box>;
}
/** Handles the notification panel operation for the WorkIntel client. */ function NotificationPanel({ open, onClose, workspaceId, onCount }: {
    open: boolean;
    onClose: () => void;
    workspaceId: number;
    onCount: (count: number) => void;
}) {
    const { t } = useLocalization();
    type NotificationRow = {
        id: number;
        category: string;
        severity: string;
        title: string;
        body: string | null;
        read_at: string | null;
        created_at: string;
    };
    const [rows, setRows] = useState<NotificationRow[]>([]), [loading, setLoading] = useState(false);
    /** Loads load data required by the current view. */ const load = async (silent = false) => { if (!workspaceId)
        return; if (!silent)
        setLoading(true); try {
        const payload = await apiRequest<{
            data: NotificationRow[];
            unread_count: number;
        }>('/api/v1/notifications', { workspaceId, silent });
        setRows(payload.data);
        onCount(payload.unread_count);
    }
    finally {
        if (!silent)
            setLoading(false);
    } };
    useEffect(() => { void load(); const id = window.setInterval(() => void load(true), 30000); return () => window.clearInterval(id); }, [workspaceId]);
    useEffect(() => { if (open)
        void load(true); }, [open]);
    /** Returns read data required by the current workflow. */ const read = async (row: NotificationRow) => { if (row.read_at)
        return; await apiRequest(`/api/v1/notifications/${row.id}/read`, { method: 'POST', workspaceId, silent: true }); await load(true); };
    /** Returns read all data required by the current workflow. */ const readAll = async () => { await apiRequest('/api/v1/notifications/read-all', { method: 'POST', workspaceId, silent: true }); await load(true); };
    const severityColors: Record<string, string> = { warning: 'var(--warning)', info: 'var(--info)', danger: 'var(--danger)', critical: 'var(--danger)', success: 'var(--success)' };
    return <Drawer open={open} onClose={onClose} title={t('shell.notifications')} description={t('shell.workspace_alerts')} footer={<Button size="sm" variant="outline" onClick={() => void readAll()}>{t('shell.mark_all_read')}</Button>}>
    <Box m={-18} mt={-18}>{loading && !rows.length ? <Box p={18} className="ui-card-description">{t('shell.loading_notifications')}</Box> : rows.length ? rows.map(row => <Pressable key={row.id} type="button" onClick={() => void read(row)} width="100%" display="flex" gap={12} p="12px 18px" border={0} borderBottom="1px solid var(--border-muted)" bg={row.read_at ? 'transparent' : 'var(--elevated)'} cursor="pointer" textAlign="left" fontFamily="inherit"><Box as="span" width={8} height={8} mt={5} radius="50%" shrink={0} bg={severityColors[row.severity] || 'var(--text-3)'} opacity={row.read_at ? .5 : 1}/><Box as="span" flex={1}><Box as="span" display="block" color="var(--text)" size={13} weight={row.read_at ? 400 : 600}>{row.title}</Box>{row.body && <Text display="block" mt={3} color="var(--text-3)" size={12} lineHeight={1.5}>{row.body}</Text>}<Text display="block" mt={5} color="var(--text-3)" size={10}>{new Date(row.created_at).toLocaleString()} · {row.category}</Text></Box></Pressable>) : <Box p={18} className="ui-card-description">{t('shell.no_notifications')}</Box>}</Box>
  </Drawer>;
}
/** Handles the app operation for the WorkIntel client. */ export default function App() {
    const { t } = useLocalization();
    const { isAuthenticated, isReady, session, logout, switchWorkspace } = useAuth();
    const initialDestination = typeof window !== 'undefined' ? shellDestinationFromLocation() : null;
    const [page, setPage] = useState<Page>(() => initialDestination?.kind === 'page' ? initialDestination.page : 'overview');
    const [activeModule, setActiveModule] = useState<WorkspaceModuleId | null>(() => initialDestination?.kind === 'module' ? initialDestination.module : null);
    const [collapsed, setCollapsed] = useState(() => typeof window !== 'undefined' && window.localStorage.getItem('workintel-sidebar-collapsed') === '1');
    const [isMobile, setIsMobile] = useState(() => typeof window !== 'undefined' && window.matchMedia?.('(max-width: 900px)').matches === true);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [shellPrefs, setShellPrefs] = useState<ShellPreferences>({ favorites: [], recent: [] });
    const [cmdOpen, setCmdOpen] = useState(false);
    const [helpOpen, setHelpOpen] = useState(false);
    const [timerOpen, setTimerOpen] = useState(false);
    const [notifOpen, setNotifOpen] = useState(false);
    const [notifCount, setNotifCount] = useState(0);
    useEffect(() => {
        /** Handle global discovery shortcuts without stealing normal text-input keystrokes. */ const handler = (e: KeyboardEvent) => {
            const target = e.target as HTMLElement | null, input = target?.tagName === 'INPUT' || target?.tagName === 'TEXTAREA' || target?.tagName === 'SELECT' || target?.isContentEditable;
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setCmdOpen(o => !o);
                return;
            }
            if (!input && (e.key === 'F1' || ((e.metaKey || e.ctrlKey) && e.key === '/'))) {
                e.preventDefault();
                setHelpOpen(true);
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, []);
    useEffect(() => { /** Open contextual help from first-run and guided empty-state surfaces without duplicating shell state. */ const handler = () => setHelpOpen(true); window.addEventListener('workintel:open-help', handler); return () => window.removeEventListener('workintel:open-help', handler); }, []);
    useEffect(() => { window.localStorage.setItem('workintel-sidebar-collapsed', collapsed ? '1' : '0'); }, [collapsed]);
    useEffect(() => { const media = window.matchMedia('(max-width: 900px)'); /** Synchronize responsive shell state with the mobile navigation breakpoint. */ /** Synchronize responsive shell state with the mobile navigation breakpoint. */ const sync = () => { setIsMobile(media.matches); if (!media.matches)
        setMobileOpen(false); }; sync(); media.addEventListener?.('change', sync); return () => media.removeEventListener?.('change', sync); }, []);
    const currentWorkspace = session?.user.workspaces.find(workspace => workspace.id === session.user.activeWorkspaceId) ?? session?.user.workspaces[0];
    useEffect(() => { if (!currentWorkspace || !session)
        return; setShellPrefs(loadShellPreferences(currentWorkspace.id, session.user.id)); }, [currentWorkspace?.id, session?.user.id]);
    useEffect(() => {
        if (!currentWorkspace)
            return;
        const requested = shellDestinationFromLocation();
        if (requested?.kind === 'module' && canAccessModuleHome(currentWorkspace, requested.module)) {
            setActiveModule(requested.module);
            writeShellHistory(requested, 'replace');
            return;
        }
        const requestedPage = requested?.kind === 'page' ? requested.page : null;
        const next = requestedPage && canAccessPage(currentWorkspace, requestedPage) ? requestedPage : defaultPageForRole(currentWorkspace.role);
        setActiveModule(null);
        if (next !== page)
            setPage(next);
        writeShellHistory({ kind: 'page', page: next }, 'replace');
    }, [currentWorkspace?.id, currentWorkspace?.role, currentWorkspace?.permissions.join('|'), JSON.stringify(currentWorkspace?.modules ?? {})]);
    useEffect(() => {
        if (!currentWorkspace)
            return;
        /** Keep browser Back/Forward synchronized with permission-aware page and module-home destinations. */
        const sync = () => { const requested = shellDestinationFromLocation(); if (requested?.kind === 'module' && canAccessModuleHome(currentWorkspace, requested.module)) {
            setActiveModule(requested.module);
            return;
        } if (requested?.kind === 'page' && canAccessPage(currentWorkspace, requested.page)) {
            setActiveModule(null);
            setPage(requested.page);
        } };
        window.addEventListener('hashchange', sync);
        return () => window.removeEventListener('hashchange', sync);
    }, [currentWorkspace?.id, currentWorkspace?.role, currentWorkspace?.permissions.join('|'), JSON.stringify(currentWorkspace?.modules ?? {})]);
    useEffect(() => {
        /** Route cross-feature navigation through the same shell history and access guard. */ const handler = (event: Event) => {
            const next = (event as CustomEvent<Page>).detail;
            if (!next || !canAccessPage(currentWorkspace, next))
                return;
            setActiveModule(null);
            setPage(next);
            writeShellHistory({ kind: 'page', page: next });
        };
        window.addEventListener('workintel:navigate', handler);
        return () => window.removeEventListener('workintel:navigate', handler);
    }, [currentWorkspace?.id, currentWorkspace?.role, currentWorkspace?.permissions.join('|')]);
    if (!isReady)
        return <AppBootLoader label={t('shell.loading_workspace')}/>;
    if (!isAuthenticated || !session || !currentWorkspace)
        return <AuthScreen />;
    if (session.user.forcePasswordChange)
        return <ForcePasswordChange />;
    /** Persist a personal shell preference update without changing any server-side authorization. */ const updateShellPrefs = (change: (value: ShellPreferences) => ShellPreferences) => setShellPrefs(current => { const next = change(current); saveShellPreferences(currentWorkspace.id, session.user.id, next); return next; });
    /** Navigate through one permission-aware page contract and keep browser history usable. */ const navigate = (p: Page) => {
        if (!canAccessPage(currentWorkspace, p))
            return;
        setActiveModule(null);
        setPage(p);
        setMobileOpen(false);
        writeShellHistory({ kind: 'page', page: p });
        updateShellPrefs(value => recordRecentShellPage(value, p));
    };
    /** Open a self-documenting module home only when at least one destination is accessible. */ const navigateModule = (moduleId: WorkspaceModuleId) => {
        if (!canAccessModuleHome(currentWorkspace, moduleId))
            return;
        setActiveModule(moduleId);
        setMobileOpen(false);
        writeShellHistory({ kind: 'module', module: moduleId });
    };
    /** Toggle the current user's local favorite without affecting workspace data or permissions. */ const toggleFavorite = (favoritePage: Page) => updateShellPrefs(value => toggleShellFavorite(value, favoritePage));
    /** Handles the render page operation for the WorkIntel client. */ const renderPage = () => {
        switch (page) {
            case 'overview': return <Overview onNavigate={navigate} onOpenModule={navigateModule}/>;
            case 'live': return <Live />;
            case 'people': return <People />;
            case 'hris': return <Hris />;
            case 'performance': return <Performance />;
            case 'finance': return <FinanceOps />;
            case 'payroll-compliance': return <PayrollCompliance />;
            case 'field': return <FieldWorkforce />;
            case 'enterprise': return <Enterprise />;
            case 'automations': return <Automations />;
            case 'organization': return <Organization />;
            case 'projects': return <Projects />;
            case 'tasks': return <Tasks />;
            case 'clients': return <Clients />;
            case 'client-commerce': return <ClientCommerce />;
            case 'time': return <Timesheets />;
            case 'activity': return <Activity />;
            case 'apps': return <AppsSites />;
            case 'screenshots': return <Screenshots />;
            case 'attendance': return <Attendance />;
            case 'schedule': return <SchedulingHub />;
            case 'shifts': return <SchedulingHub initialTab="templates"/>;
            case 'leave': return <Leave />;
            case 'payroll': return <Payroll />;
            case 'reports': return <Reports />;
            case 'documents': return <Documents />;
            case 'website': return <WebsiteStudio />;
            case 'media': return <MediaLibrary />;
            case 'trash': return <TrashCenter />;
            case 'chat': return <Chat />;
            case 'insights': return <Insights />;
            case 'downloads': return <Downloads />;
            case 'devices': return <Devices />;
            case 'billing': return <Billing />;
            case 'settings': return <Settings />;
            case 'access': return <AccessControl />;
            case 'modules': return <Modules />;
            case 'approvals': return <Approvals />;
            case 'account': return <MyAccess />;
            default: return <Overview onNavigate={navigate} onOpenModule={navigateModule}/>;
        }
    };
    return <Box className="ui-app-shell" display="flex" height="100vh" overflow="hidden" bg="var(--bg)" fontFamily="var(--font-sans)">
    <Link className="ui-skip-link" href="#workintel-main">{t('common.skip_to_content')}</Link>
    <Sidebar page={page} setPage={navigate} collapsed={isMobile ? false : collapsed} setCollapsed={setCollapsed} workspace={currentWorkspace} activeModule={activeModule} onOpenModule={navigateModule} favoritePages={shellPrefs.favorites} mobileOpen={mobileOpen} onMobileClose={() => setMobileOpen(false)}/>
    <Box className="ui-app-shell__content" flex={1} display="flex" direction="column" overflow="hidden" minWidth={0}>
      <TopBar page={page} activeModule={activeModule} user={session.user} workspace={currentWorkspace} workspaces={session.user.workspaces} onWorkspaceChange={switchWorkspace} onSignOut={logout} onTimerClick={() => setTimerOpen(o => !o)} onCmdK={() => setCmdOpen(true)} onNotifications={() => setNotifOpen(o => !o)} onCustomize={() => window.dispatchEvent(new CustomEvent('workintel:customize-page'))} onNavigate={p => navigate(p as Page)} onOpenSidebar={() => setMobileOpen(true)} notifCount={notifCount}/>
      <ShellContextBar page={page} activeModule={activeModule} onOpenCommand={() => setCmdOpen(true)} onOpenHelp={() => setHelpOpen(true)} onOpenModule={navigateModule} isFavorite={!activeModule && shellPrefs.favorites.includes(page)} onToggleFavorite={!activeModule ? () => toggleFavorite(page) : undefined}/>
      <Box as="main" id="workintel-main" tabIndex={-1} flex={1} overflowY="auto" overflowX="hidden" bg="var(--bg)" minWidth={0}><FirstRunGuide workspace={currentWorkspace}/>{activeModule ? <ModuleHome workspace={currentWorkspace} moduleId={activeModule} favorites={shellPrefs.favorites} recent={shellPrefs.recent} onNavigate={navigate} onToggleFavorite={toggleFavorite}/> : <PageCustomizationShell page={page} workspaceId={currentWorkspace.id}><Suspense fallback={pageLoadingFallback(page)}>{renderPage()}</Suspense></PageCustomizationShell>}</Box>
    </Box>
    <CommandPalette open={cmdOpen} onClose={() => setCmdOpen(false)} onNavigate={navigate} onNavigateModule={navigateModule} workspace={currentWorkspace} favorites={shellPrefs.favorites} recent={shellPrefs.recent}/>
    <HelpCenter open={helpOpen} onClose={() => setHelpOpen(false)} workspace={currentWorkspace} page={page} activeModule={activeModule} onNavigate={navigate} onOpenModule={navigateModule}/>
    {timerOpen && <TimerFloating workspaceId={currentWorkspace.id} onClose={() => setTimerOpen(false)}/>}
    <NotificationPanel open={notifOpen} onClose={() => setNotifOpen(false)} workspaceId={currentWorkspace.id} onCount={setNotifCount}/>
  </Box>;
}
