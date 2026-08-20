import { useEffect, useState } from 'react';
import { AreaChart, Area, BarChart, Bar, CartesianGrid, ResponsiveContainer, Tooltip as ChartTooltip, XAxis, YAxis } from 'recharts';
import { Activity, AlertTriangle, Banknote, CalendarCheck2, ClipboardCheck, Clock3, FolderKanban, ShieldCheck, Users, Zap } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import type { AuthWorkspace } from '../auth/types';
import { hasAnyPermission, hasPermission, roleLabel } from '../access';
import type { Page as AppPage } from '../components/Sidebar';
import { Alert, Badge, Button, Card, CardBody, CardHeader, Page, PageHeader, Progress, StatCard, Pressable, Grid, Inline, Stack, Box} from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
import DashboardGrid from '../components/DashboardGrid';
import ModuleDirectory from '../components/ModuleDirectory';
import type { WorkspaceModuleId } from '../moduleCatalog';
import RoleStartHere from '../components/RoleStartHere';
type Task = {
    id: number;
    title: string;
    status: string;
    priority: string;
    project?: {
        name: string;
    };
    assignees?: Array<{
        id: number;
    }>;
};
type AttendanceRow = {
    member_id: number;
    name: string;
    display_status: string;
    record: {
        clock_in_at: string | null;
        clock_out_at: string | null;
        worked_seconds: number;
        status: string;
        overtime_minutes?: number;
        flag_type?: string | null;
    } | null;
};
type AttendancePayload = {
    rows: AttendanceRow[];
    current_member_id: number;
    can_manage: boolean;
};
type LivePayload = {
    stats?: {
        working: number;
        idle: number;
        break: number;
        meeting: number;
        offline: number;
    };
    workers?: Array<{
        member_id: number;
        name?: string;
        status?: string;
    }>;
};
type TimerPayload = {
    timer: {
        id: number;
        status: string;
        project?: {
            name: string;
        } | null;
        task?: {
            title: string;
        } | null;
    } | null;
};
type PayrollPayload = {
    data: Array<{
        id: number;
        net_pay: number;
        currency: string;
        run?: {
            name: string;
            pay_date: string;
        } | null;
    }>;
};
type Project = {
    id: number;
    name: string;
    tasks_count: number;
    members_count: number;
    status: string;
    due_date?: string | null;
    budget_amount?: string | null;
    budget_type?: string;
};
type NotificationPayload = {
    data: Array<{
        id: number;
        title: string;
        body: string | null;
        severity: string;
        created_at: string;
    }>;
    unread_count: number;
};
/** Handles the hours operation for the WorkIntel client. */ const hours = (seconds: number) => `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`;
/** Handles the tone operation for the WorkIntel client. */ const tone = (severity: string): 'danger' | 'warning' | 'info' | 'neutral' => severity === 'critical' || severity === 'danger' ? 'danger' : severity === 'warning' ? 'warning' : severity === 'info' ? 'info' : 'neutral';
/** Handles the overview operation for the WorkIntel client. */ export default function Overview({ onNavigate, onOpenModule }: {
    onNavigate: (page: AppPage) => void;
    onOpenModule: (moduleId: WorkspaceModuleId) => void;
}) {
    const { session } = useAuth();
    const workspace = session?.user.workspaces.find(item => item.id === session.user.activeWorkspaceId);
    const [tasks, setTasks] = useState<Task[]>([]);
    const [attendance, setAttendance] = useState<AttendancePayload | null>(null);
    const [live, setLive] = useState<LivePayload | null>(null);
    const [timer, setTimer] = useState<TimerPayload['timer']>(null);
    const [pay, setPay] = useState<PayrollPayload['data']>([]);
    const [projects, setProjects] = useState<Project[]>([]);
    const [notifications, setNotifications] = useState<NotificationPayload>({ data: [], unread_count: 0 });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    useEffect(() => {
        if (!workspace)
            return;
        let active = true;
        setLoading(true);
        const jobs: Promise<void>[] = [];
        if (hasAnyPermission(workspace, ['tasks.view_own', 'tasks.view_team', 'tasks.view_all', 'tasks.view', 'tasks.manage_team', 'tasks.manage']))
            jobs.push(apiRequest<{
                data: Task[];
            }>('/api/v1/tasks', { workspaceId: workspace.id, silent: true }).then(r => { if (active)
                setTasks(r.data); }).catch(() => { }));
        if (hasAnyPermission(workspace, ['attendance.view_own', 'attendance.view_team', 'attendance.manage']))
            jobs.push(apiRequest<AttendancePayload>('/api/v1/attendance', { workspaceId: workspace.id, silent: true }).then(r => { if (active)
                setAttendance(r); }).catch(() => { }));
        if (hasAnyPermission(workspace, ['time.view_own', 'time.view_team', 'time.view_all', 'time.manage']))
            jobs.push(apiRequest<TimerPayload>('/api/v1/timer', { workspaceId: workspace.id, silent: true }).then(r => { if (active)
                setTimer(r.timer); }).catch(() => { }));
        if (hasAnyPermission(workspace, ['activity.view_team', 'activity.view_all', 'activity.manage']))
            jobs.push(apiRequest<LivePayload>('/api/v1/live-workforce', { workspaceId: workspace.id, silent: true }).then(r => { if (active)
                setLive(r); }).catch(() => { }));
        if (hasAnyPermission(workspace, ['payroll.view_own', 'payroll.view_all', 'payroll.manage']))
            jobs.push(apiRequest<PayrollPayload>('/api/v1/payroll/me', { workspaceId: workspace.id, silent: true }).then(r => { if (active)
                setPay(r.data); }).catch(() => { }));
        if (hasAnyPermission(workspace, ['projects.view_assigned', 'projects.view_all', 'projects.view', 'projects.manage']))
            jobs.push(apiRequest<{
                data: Project[];
            }>('/api/v1/projects', { workspaceId: workspace.id, silent: true }).then(r => { if (active)
                setProjects(r.data); }).catch(() => { }));
        if (workspace.role === 'owner' || workspace.role === 'admin')
            jobs.push(apiRequest<NotificationPayload>('/api/v1/notifications', { workspaceId: workspace.id, silent: true }).then(r => { if (active)
                setNotifications(r); }).catch(() => { }));
        Promise.all(jobs).catch(err => { if (active)
            setError(err instanceof Error ? err.message : 'Could not load dashboard.'); }).finally(() => { if (active)
            setLoading(false); });
        return () => { active = false; };
    }, [workspace?.id]);
    if (!workspace)
        return null;
    if (loading && !attendance && !tasks.length && !live)
        return <PageLoadingState />;
    if (workspace.role === 'owner' || workspace.role === 'admin')
        return <OwnerOverview workspace={workspace} workspaceId={workspace.id} workspaceName={workspace.name} firstName={session?.user.firstName ?? 'Owner'} tasks={tasks} attendance={attendance} live={live} projects={projects} notifications={notifications} error={error} onNavigate={onNavigate} onOpenModule={onOpenModule}/>;
    const me = attendance?.rows.find(row => row.member_id === attendance.current_member_id) ?? null;
    const pendingTasks = tasks.filter(task => task.status !== 'done').length;
    const teamPresent = attendance?.rows.filter(row => row.record?.clock_in_at && !row.record.clock_out_at).length ?? 0;
    const title = workspace.role === 'employee' ? 'My Work' : workspace.role === 'manager' || workspace.role === 'team-lead' ? 'Team Dashboard' : workspace.role === 'hr' ? 'People Dashboard' : workspace.role === 'payroll-manager' ? 'Payroll Dashboard' : 'Workspace Dashboard';
    const quick = (() => {
        const rows: Array<{
            page: AppPage;
            label: string;
            desc: string;
            icon: any;
        }> = [];
        if (hasAnyPermission(workspace, ['attendance.view_own', 'attendance.view_team', 'attendance.manage']))
            rows.push({ page: 'attendance', label: workspace.role === 'employee' ? 'My Attendance' : 'Team Attendance', desc: workspace.role === 'employee' ? 'Clock in/out and see today' : 'Review today’s team attendance', icon: CalendarCheck2 });
        if (hasAnyPermission(workspace, ['tasks.view_own', 'tasks.view_team', 'tasks.view_all', 'tasks.manage_team', 'tasks.manage']))
            rows.push({ page: 'tasks', label: workspace.role === 'employee' ? 'My Tasks' : 'Team Tasks', desc: 'Assigned work and priorities', icon: ClipboardCheck });
        if (hasAnyPermission(workspace, ['time.view_own', 'time.view_team', 'time.view_all', 'time.manage']))
            rows.push({ page: 'time', label: workspace.role === 'employee' ? 'My Timesheet' : 'Team Timesheets', desc: 'Tracked time and approvals', icon: Clock3 });
        if (hasAnyPermission(workspace, ['people.view_team', 'people.view_all', 'people.manage']))
            rows.push({ page: 'people', label: 'People', desc: 'Your visible people scope', icon: Users });
        if (hasAnyPermission(workspace, ['projects.view_assigned', 'projects.view_all', 'projects.manage']))
            rows.push({ page: 'projects', label: workspace.role === 'employee' ? 'My Projects' : 'Projects', desc: 'Assigned and managed projects', icon: FolderKanban });
        if (hasAnyPermission(workspace, ['payroll.view_own', 'payroll.view_all', 'payroll.manage']))
            rows.push({ page: 'payroll', label: workspace.role === 'employee' ? 'My Pay' : 'Payroll', desc: workspace.role === 'employee' ? 'Approved pay history' : 'Payroll runs and compensation', icon: Banknote });
        if (hasAnyPermission(workspace, ['activity.view_team', 'activity.view_all', 'activity.manage']))
            rows.push({ page: 'live', label: 'Live Team', desc: 'Working, idle, break and offline', icon: Zap });
        return rows;
    })();
    return <Page><PageHeader title={title} description={`${roleLabel(workspace.role)} · ${workspace.name}`}/>{error && <Alert tone="danger">{error}</Alert>}
  <RoleStartHere workspace={workspace} onNavigate={onNavigate}/><ModuleDirectory workspace={workspace} onNavigate={onNavigate} onOpenModule={onOpenModule} compact/>
  <Grid columns="repeat(auto-fit,minmax(190px,1fr))" gap={10} m="14px 0">
   {hasPermission(workspace, 'attendance.view_own') && <StatCard label="My attendance" value={me?.record?.clock_out_at ? 'Clocked out' : me?.record?.clock_in_at ? 'Clocked in' : 'Not clocked in'} sub={me?.record?.worked_seconds ? hours(me.record.worked_seconds) : me?.display_status ?? 'Today'}/>} 
   {hasAnyPermission(workspace, ['attendance.view_team', 'attendance.manage']) && <StatCard label="Present now" value={String(teamPresent)} sub={`${attendance?.rows.length ?? 0} visible team members`}/>} 
   {tasks.length > 0 && <StatCard label="Open tasks" value={String(pendingTasks)} sub={`${tasks.length} tasks visible to you`}/>} 
   {timer && <StatCard label="Current timer" value={timer.status} sub={timer.task?.title ?? timer.project?.name ?? 'Tracked work'}/>} 
   {live?.stats && <StatCard label="Working now" value={String(live.stats.working ?? 0)} sub={`${live.stats.idle ?? 0} idle · ${live.stats.break ?? 0} break`}/>} 
   {pay[0] && <StatCard label="Latest net pay" value={`${pay[0].currency} ${Number(pay[0].net_pay).toLocaleString()}`} sub={pay[0].run?.name ?? 'Approved payroll'}/>} 
  </Grid>
  <Card><CardHeader title="What you can do" description="Only actions available to your current role are shown."/><CardBody><Grid columns="repeat(auto-fill,minmax(220px,1fr))" gap={9}>{quick.map(item => { const Icon = item.icon; return <Pressable key={item.page} type="button" onClick={() => onNavigate(item.page)} className="ui-action-tile"><span className="ui-action-tile__icon"><Icon size={16}/></span><span><strong>{item.label}</strong><small>{item.desc}</small></span></Pressable>; })}</Grid></CardBody></Card>
 </Page>;
}
/** Render the owner/admin dashboard as a configurable library of essential and optional widgets. */ function OwnerOverview({ workspace, workspaceId, workspaceName, firstName, tasks, attendance, live, projects, notifications, error, onNavigate, onOpenModule }: {
    workspace: AuthWorkspace;
    workspaceId: number;
    workspaceName: string;
    firstName: string;
    tasks: Task[];
    attendance: AttendancePayload | null;
    live: LivePayload | null;
    projects: Project[];
    notifications: NotificationPayload;
    error: string;
    onNavigate: (page: AppPage) => void;
    onOpenModule: (moduleId: WorkspaceModuleId) => void;
}) {
    const greeting = new Date().getHours() < 12 ? 'Good morning' : new Date().getHours() < 18 ? 'Good afternoon' : 'Good evening';
    const activeNow = live?.stats?.working ?? 0;
    const present = attendance?.rows.filter(r => r.record?.clock_in_at && !r.record?.clock_out_at).length ?? 0;
    const tracked = attendance?.rows.reduce((sum, row) => sum + (row.record?.worked_seconds ?? 0), 0) ?? 0;
    const overtime = attendance?.rows.reduce((sum, row) => sum + (row.record?.overtime_minutes ?? 0), 0) ?? 0;
    const flags = attendance?.rows.filter(row => row.record?.flag_type).length ?? 0;
    const open = tasks.filter(task => task.status !== 'done').length;
    const activeProjects = projects.filter(project => project.status === 'active').length;
    const projectChart = projects.slice(0, 7).map(project => ({ name: project.name.length > 16 ? `${project.name.slice(0, 14)}…` : project.name, tasks: project.tasks_count ?? 0, members: project.members_count ?? 0 }));
    const statusChart = [['Working', live?.stats?.working ?? 0], ['Idle', live?.stats?.idle ?? 0], ['Break', live?.stats?.break ?? 0], ['Meeting', live?.stats?.meeting ?? 0], ['Offline', live?.stats?.offline ?? 0]].map(([name, value]) => ({ name, value: Number(value) }));
    const priorityChart = ['critical', 'high', 'medium', 'low'].map(priority => ({ name: priority.charAt(0).toUpperCase() + priority.slice(1), value: tasks.filter(task => task.status !== 'done' && task.priority === priority).length }));
    const upcoming = projects.filter(project => project.due_date && project.status === 'active').sort((a, b) => String(a.due_date).localeCompare(String(b.due_date))).slice(0, 6);
    const workforceTotal = statusChart.reduce((sum, item) => sum + item.value, 0);
    const healthyPercent = workforceTotal ? Math.round(((activeNow + (live?.stats?.meeting ?? 0)) / workforceTotal) * 100) : 0;
    return <Page><PageHeader title={`${greeting}, ${firstName}`} description={`${workspaceName} · Executive workspace overview`} actions={<Inline gap={7}><Button size="sm" variant="outline" onClick={() => onNavigate('reports')}>Reports</Button><Button size="sm" variant="primary" onClick={() => onNavigate('attendance')}>Attendance</Button></Inline>}/>{error && <Alert tone="danger">{error}</Alert>}
  <RoleStartHere workspace={workspace} onNavigate={onNavigate}/><ModuleDirectory workspace={workspace} onNavigate={onNavigate} onOpenModule={onOpenModule}/>
  <DashboardGrid items={[
            { id: 'active-now', title: 'Active now', description: 'Live workforce currently working', w: 3, h: 2, minW: 2, defaultVisible: true, content: <StatCard label="Active now" value={String(activeNow)} sub="Live workforce" icon={<Zap size={15}/>}/> },
            { id: 'present-today', title: 'Present today', description: 'Employees clocked in and not clocked out', w: 3, h: 2, minW: 2, defaultVisible: true, content: <StatCard label="Present today" value={String(present)} sub={`${attendance?.rows.length ?? 0} employees visible`} icon={<CalendarCheck2 size={15}/>}/> },
            { id: 'open-tasks', title: 'Open tasks', description: 'All visible tasks that are not complete', w: 3, h: 2, minW: 2, defaultVisible: true, content: <StatCard label="Open tasks" value={String(open)} sub={`${tasks.length} total tasks`} icon={<ClipboardCheck size={15}/>}/> },
            { id: 'active-projects', title: 'Active projects', description: 'Projects currently in active status', w: 3, h: 2, minW: 2, defaultVisible: true, content: <StatCard label="Active projects" value={String(activeProjects)} sub={`${projects.length} visible projects`} icon={<FolderKanban size={15}/>}/> },
            { id: 'tracked-today', title: 'Tracked today', description: 'Total attendance worked time', w: 3, h: 2, minW: 2, defaultVisible: false, content: <StatCard label="Tracked today" value={hours(tracked)} sub="Attendance worked time" icon={<Clock3 size={15}/>}/> },
            { id: 'overtime-today', title: 'Overtime today', description: 'Calculated overtime across visible attendance', w: 3, h: 2, minW: 2, defaultVisible: false, content: <StatCard label="Overtime today" value={`${Math.round(overtime / 60 * 10) / 10}h`} sub="Calculated attendance overtime" icon={<Activity size={15}/>}/> },
            { id: 'attendance-flags', title: 'Attendance flags', description: 'Attendance records needing review', w: 3, h: 2, minW: 2, defaultVisible: false, content: <StatCard label="Attendance flags" value={String(flags)} sub="Needs review" icon={<AlertTriangle size={15}/>}/> },
            { id: 'unread-alerts', title: 'Unread alerts', description: 'Unread workspace notifications and security alerts', w: 3, h: 2, minW: 2, defaultVisible: false, content: <StatCard label="Unread alerts" value={String(notifications.unread_count)} sub="Notifications & security" icon={<ShieldCheck size={15}/>}/> },
            { id: 'project-workload', title: 'Project workload', description: 'Task and team distribution across projects', w: 7, h: 5, minW: 5, defaultVisible: true, content: <Card><CardHeader title="Project workload" description="Task and team distribution across active projects"/><CardBody><ResponsiveContainer width="100%" height={245}><BarChart data={projectChart} margin={{ top: 4, right: 10, left: -24, bottom: 0 }}><CartesianGrid strokeDasharray="3 3" stroke="var(--border-muted)" vertical={false}/><XAxis dataKey="name" tick={{ fill: 'var(--text-3)', fontSize: 10 }} axisLine={false} tickLine={false}/><YAxis tick={{ fill: 'var(--text-3)', fontSize: 10 }} axisLine={false} tickLine={false}/><ChartTooltip /><Bar dataKey="tasks" name="Tasks" fill="var(--accent)" radius={[3, 3, 0, 0]}/><Bar dataKey="members" name="Members" fill="var(--success)" radius={[3, 3, 0, 0]}/></BarChart></ResponsiveContainer></CardBody></Card> },
            { id: 'live-workforce', title: 'Live workforce', description: 'Current working/idle/break/meeting/offline split', w: 5, h: 5, minW: 4, defaultVisible: true, content: <Card><CardHeader title="Live workforce" description="Current workforce status" action={<Badge tone="success" dot>{activeNow} working</Badge>}/><CardBody><ResponsiveContainer width="100%" height={185}><AreaChart data={statusChart}><CartesianGrid strokeDasharray="3 3" stroke="var(--border-muted)" vertical={false}/><XAxis dataKey="name" tick={{ fill: 'var(--text-3)', fontSize: 10 }} axisLine={false} tickLine={false}/><YAxis allowDecimals={false} tick={{ fill: 'var(--text-3)', fontSize: 10 }} axisLine={false} tickLine={false}/><ChartTooltip /><Area type="monotone" dataKey="value" name="People" stroke="var(--accent)" fill="var(--accent-dim)" strokeWidth={2}/></AreaChart></ResponsiveContainer><Grid columns="repeat(5,1fr)" gap={6} mt={9}>{statusChart.map(item => <div key={item.name} className="overview-mini-stat"><strong>{item.value}</strong><small>{item.name}</small></div>)}</Grid></CardBody></Card> },
            { id: 'task-priority', title: 'Task priorities', description: 'Open task load by priority', w: 4, h: 4, minW: 3, defaultVisible: true, content: <Card><CardHeader title="Task priorities" description="Open workload by urgency"/><CardBody><div className="overview-priority-list">{priorityChart.map(item => <div key={item.name}><span>{item.name}</span><strong>{item.value}</strong><Progress value={open ? Math.round(item.value / open * 100) : 0} tone={item.name === 'Critical' ? 'danger' : item.name === 'High' ? 'warning' : 'accent'}/></div>)}</div></CardBody></Card> },
            { id: 'recent-alerts', title: 'Recent alerts', description: 'Latest workspace notifications', w: 4, h: 4, minW: 3, defaultVisible: true, content: <Card><CardHeader title="Recent alerts" description="Latest workspace notifications" action={<Button variant="ghost" size="sm" onClick={() => onNavigate('settings')}>Review</Button>}/><CardBody><Stack gap={7}>{notifications.data.slice(0, 6).map(notification => <div key={notification.id} className="overview-alert"><Badge tone={tone(notification.severity)} dot>{notification.severity}</Badge><div><strong>{notification.title}</strong>{notification.body && <small>{notification.body}</small>}</div></div>)}{!notifications.data.length && <div className="ui-card-description">No recent alerts.</div>}</Stack></CardBody></Card> },
            { id: 'workforce-health', title: 'Workforce health', description: 'High-level live availability ratio', w: 4, h: 4, minW: 3, defaultVisible: false, content: <Card><CardHeader title="Workforce health" description="Working or in meetings versus visible workforce"/><CardBody><div className="overview-health-score"><strong>{healthyPercent}%</strong><span>available / engaged</span></div><Progress value={healthyPercent} tone={healthyPercent >= 75 ? 'success' : healthyPercent >= 50 ? 'warning' : 'danger'}/><div className="overview-health-meta"><span>{workforceTotal} visible</span><span>{flags} attendance flags</span></div></CardBody></Card> },
            { id: 'project-health', title: 'Project health', description: 'Current task volume for active projects', w: 5, h: 4, minW: 4, defaultVisible: false, content: <Card><CardHeader title="Project health" description="Current task load"/><CardBody><Stack gap={11}>{projects.slice(0, 5).map(project => { const value = Math.min(100, (project.tasks_count ?? 0) * 12); return <div key={project.id}><Box display="flex" justify="space-between" size={12} mb={5}><span>{project.name}</span><span className="stat-num">{project.tasks_count ?? 0} tasks</span></Box><Progress value={value}/></div>; })}{!projects.length && <div className="ui-card-description">No active projects.</div>}</Stack></CardBody></Card> },
            { id: 'upcoming-deadlines', title: 'Upcoming deadlines', description: 'Nearest active-project due dates', w: 4, h: 4, minW: 3, defaultVisible: false, content: <Card><CardHeader title="Upcoming deadlines" description="Nearest project due dates"/><CardBody><div className="overview-deadlines">{upcoming.map(project => <Pressable key={project.id} type="button" onClick={() => onNavigate('projects')}><span><strong>{project.name}</strong><small>{project.tasks_count ?? 0} tasks</small></span><time>{project.due_date ? new Date(project.due_date).toLocaleDateString() : '—'}</time></Pressable>)}{!upcoming.length && <div className="ui-card-description">No upcoming project deadlines.</div>}</div></CardBody></Card> },
            { id: 'admin-shortcuts', title: 'Admin shortcuts', description: 'Fast links to frequently used administration surfaces', w: 3, h: 4, minW: 3, defaultVisible: false, content: <Card><CardHeader title="Admin shortcuts" description="Workspace administration"/><CardBody><Grid columns="1fr" gap={8}>{[['People', 'people'], ['Projects', 'projects'], ['Payroll', 'payroll'], ['Roles & Access', 'access'], ['Scheduling', 'schedule'], ['Reports', 'reports']].map(([label, page]) => <Button key={page} variant="outline" size="sm" onClick={() => onNavigate(page as AppPage)}>{label}</Button>)}</Grid></CardBody></Card> },
        ]}/>
 </Page>;
}
