import { useCallback, useEffect, useMemo, useState } from 'react';
import { Activity, AppWindow, Camera, Clock3, Globe2, History, Laptop2, RefreshCw, Search, TimerReset, UserRoundCheck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Avatar, Badge, Button, DataGrid, Drawer, EmptyState, Input, Page, PageHeader, Progress, Segmented, Pressable, Box, Grid, Inline, Stack, type DataGridColumn } from '../design-system';
type Status = 'working' | 'idle' | 'break' | 'meeting' | 'offline';
type Worker = {
    member_id: number;
    name: string;
    role: string | null;
    department: string | null;
    teams: string[];
    status: Status;
    status_since: string | null;
    project: string | null;
    project_id: number | null;
    task: string | null;
    task_id: number | null;
    timer_seconds: number;
    activity_percent: number | null;
    app_name: string | null;
    domain: string | null;
    tracking_status: string | null;
    device: string | null;
    device_platform: string | null;
    last_seen_at: string | null;
    last_sync_at: string | null;
    last_screenshot_at: string | null;
    tracked_today_seconds: number;
    attendance: string | null;
};
type LivePayload = {
    server_time: string;
    refresh_after_seconds: number;
    revision: string;
    counts: Record<Status, number>;
    data: Worker[];
};
type TimelineEvent = {
    key: string;
    type: string;
    group: string;
    source: string;
    title: string;
    detail: string | null;
    started_at: string;
    ended_at: string | null;
    duration_seconds: number | null;
    activity_percent: number | null;
    project: string | null;
    task: string | null;
    device: string | null;
    metadata: Record<string, unknown> | null;
};
type TimelinePayload = {
    member: {
        id: number;
        name: string;
        role: string | null;
        department: string | null;
    };
    from: string;
    to: string;
    events: TimelineEvent[];
};
const statusTone: Record<Status, 'success' | 'warning' | 'info' | 'accent' | 'neutral'> = { working: 'success', idle: 'warning', break: 'info', meeting: 'accent', offline: 'neutral' };
const statusLabel: Record<Status, string> = { working: 'Working', idle: 'Idle', break: 'Break', meeting: 'Meeting', offline: 'Offline' };
const timelineGroups = ['applications', 'websites', 'tasks', 'attendance', 'screenshots', 'presence'] as const;
/** Handles the fmt seconds operation for the WorkIntel client. */ function fmtSeconds(value: number) {
    const seconds = Math.max(0, Math.floor(value || 0));
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return h > 0 ? `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}` : `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}
/** Handles the ago operation for the WorkIntel client. */ function ago(value: string | null) {
    if (!value)
        return '—';
    const diff = Math.max(0, Date.now() - new Date(value).getTime());
    const sec = Math.floor(diff / 1000);
    if (sec < 10)
        return 'just now';
    if (sec < 60)
        return `${sec}s ago`;
    const min = Math.floor(sec / 60);
    if (min < 60)
        return `${min}m ago`;
    const hr = Math.floor(min / 60);
    if (hr < 24)
        return `${hr}h ago`;
    return `${Math.floor(hr / 24)}d ago`;
}
/** Handles the clock operation for the WorkIntel client. */ function clock(value: string) { return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
/** Handles the live operation for the WorkIntel client. */ export default function Live() {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [filter, setFilter] = useState<'all' | Status>('all');
    const [view, setView] = useState<'cards' | 'table' | 'compact'>('cards');
    const [search, setSearch] = useState('');
    const [payload, setPayload] = useState<LivePayload | null>(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState('');
    const [selected, setSelected] = useState<Worker | null>(null);
    const [timeline, setTimeline] = useState<TimelinePayload | null>(null);
    const [timelineLoading, setTimelineLoading] = useState(false);
    const [timelineGroupsSelected, setTimelineGroupsSelected] = useState<string[]>([]);
    const [timelineDate, setTimelineDate] = useState(() => new Date().toISOString().slice(0, 10));
    const load = useCallback(async (silent = false) => {
        if (!workspaceId)
            return;
        silent ? setRefreshing(true) : setLoading(true);
        setError('');
        try {
            const qs = new URLSearchParams();
            if (search.trim())
                qs.set('search', search.trim());
            if (filter !== 'all')
                qs.set('status', filter);
            const next = await apiRequest<LivePayload>(`/api/v1/live-workforce${qs.size ? `?${qs}` : ''}`, { workspaceId });
            setPayload(next);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not load live workforce.');
        }
        finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, [workspaceId, search, filter]);
    useEffect(() => { const id = window.setTimeout(() => void load(false), search ? 250 : 0); return () => window.clearTimeout(id); }, [load]);
    useEffect(() => { if (!workspaceId)
        return; const id = window.setInterval(() => void load(true), 5000); return () => window.clearInterval(id); }, [workspaceId, load]);
    const loadTimeline = useCallback(async () => {
        if (!workspaceId || !selected)
            return;
        setTimelineLoading(true);
        try {
            const qs = new URLSearchParams({ from: timelineDate, to: timelineDate });
            if (timelineGroupsSelected.length)
                qs.set('groups', timelineGroupsSelected.join(','));
            const next = await apiRequest<TimelinePayload>(`/api/v1/live-workforce/${selected.member_id}/timeline?${qs}`, { workspaceId });
            setTimeline(next);
        }
        catch {
            setTimeline(null);
        }
        finally {
            setTimelineLoading(false);
        }
    }, [workspaceId, selected, timelineDate, timelineGroupsSelected]);
    useEffect(() => { if (selected)
        void loadTimeline(); }, [selected, loadTimeline]);
    const workers = payload?.data ?? [];
    const counts = payload?.counts ?? { working: 0, idle: 0, break: 0, meeting: 0, offline: 0 };
    const activeCount = counts.working + counts.idle + counts.break + counts.meeting;
    const avgActivity = useMemo(() => { const values = workers.map(w => w.activity_percent).filter((v): v is number => v !== null); return values.length ? Math.round(values.reduce((a, b) => a + b, 0) / values.length) : 0; }, [workers]);
    /** Handles the open worker operation for the WorkIntel client. */ const openWorker = (worker: Worker) => { setSelected(worker); setTimeline(null); setTimelineGroupsSelected([]); setTimelineDate(new Date().toISOString().slice(0, 10)); };
    /** Build the shared DataGrid columns for table and compact live-workforce views. */
    const liveColumns = useMemo<DataGridColumn<Worker>[]>(() => [
        { id: 'employee', header: 'Employee', value: row => row.name, cell: row => <Inline align="center" gap={8}><Avatar name={row.name} size="sm"/><div><Button size="sm" variant="ghost" onClick={() => openWorker(row)}>{row.name}</Button><div className="ui-card-description">{row.role || 'Team member'}</div></div></Inline> },
        { id: 'status', header: 'Status', value: row => statusLabel[row.status], cell: row => <Badge tone={statusTone[row.status]} dot>{statusLabel[row.status]}</Badge> },
        { id: 'work', header: 'Project / Task', searchValue: row => `${row.project ?? ''} ${row.task ?? ''}`, cell: row => <div><strong>{row.project || '—'}</strong><div className="ui-card-description">{row.task || ''}</div></div> },
        { id: 'timer', header: 'Timer', sortValue: row => row.timer_seconds, cell: row => <span className="stat-num">{fmtSeconds(row.timer_seconds)}</span> },
        { id: 'activity', header: 'Activity', sortValue: row => row.activity_percent ?? -1, cell: row => <Inline align="center" gap={7} minWidth={100}><Progress value={row.activity_percent ?? 0} tone={(row.activity_percent ?? 0) > 70 ? 'success' : (row.activity_percent ?? 0) > 40 ? 'warning' : 'danger'}/><span className="stat-num ui-card-description">{row.activity_percent === null ? '—' : `${row.activity_percent}%`}</span></Inline> },
        ...(view === 'table' ? [
            { id: 'today', header: 'Today', sortValue: (row: Worker) => row.tracked_today_seconds, cell: (row: Worker) => <span className="stat-num">{fmtSeconds(row.tracked_today_seconds)}</span> },
            { id: 'context', header: 'Current context', searchValue: (row: Worker) => `${row.app_name ?? ''} ${row.domain ?? ''}`, cell: (row: Worker) => <span className="ui-card-description">{row.app_name || row.domain || '—'}</span> },
            { id: 'device', header: 'Device', value: (row: Worker) => row.device ?? '', cell: (row: Worker) => <span className="ui-card-description">{row.device || '—'}</span> },
        ] as DataGridColumn<Worker>[] : []),
        { id: 'last-seen', header: 'Last seen', sortValue: row => row.last_seen_at ? new Date(row.last_seen_at).getTime() : 0, cell: row => <span className="ui-card-description">{ago(row.last_seen_at)}</span> },
    ], [view]);
    return <Page>
    <PageHeader title="Live Workforce" description="Current work, presence, activity and devices · refreshes every 5 seconds" actions={<><Badge tone="success" dot>{activeCount} active</Badge><Button size="sm" variant="outline" loading={refreshing} onClick={() => void load(true)}><RefreshCw size={13}/> Refresh</Button><Segmented value={view} onChange={setView} options={[{ value: 'cards', label: 'Cards' }, { value: 'table', label: 'Table' }, { value: 'compact', label: 'Compact' }]}/></>}/>

    <Grid columns="repeat(auto-fit,minmax(150px,1fr))" gap={10} mb={14}>
      {([['Active now', activeCount, UserRoundCheck], ['Working', counts.working, Activity], ['Idle', counts.idle, TimerReset], ['Average activity', `${avgActivity}%`, Activity], ['Offline', counts.offline, Laptop2]] as Array<[
        string,
        string | number,
        LucideIcon
    ]>).map(([label, value, Icon]) => <Box className="ui-card ui-card__body" key={String(label)} p={13}><Box className="ui-inline" justify="space-between"><div><div className="ui-card-description">{String(label)}</div><Box className="stat-num" size={19} weight={650} color="var(--text)" mt={3}>{String(value)}</Box></div><Icon size={17} color="var(--text-3)"/></Box></Box>)}
    </Grid>

    <Inline gap={8} align="center" wrap="wrap" mb={16}>
      <Box position="relative" width={230} maxWidth="100%"><Box as="span" position="absolute" left={10} top={10} color="var(--text-3)"><Search size={14}/></Box><Input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search workers" pl={31}/></Box>
      <Button size="sm" variant={filter === 'all' ? 'secondary' : 'outline'} onClick={() => setFilter('all')}>All <span className="stat-num">{workers.length}</span></Button>
      {(Object.keys(statusLabel) as Status[]).map(s => <Button key={s} size="sm" variant={filter === s ? 'secondary' : 'outline'} onClick={() => setFilter(s)}><Badge tone={statusTone[s]} dot>{statusLabel[s]} {counts[s]}</Badge></Button>)}
      <Box flex={1}/>
      {payload && <span className="ui-card-description">Updated {ago(payload.server_time)}</span>}
    </Inline>

    {error && <Box className="ui-alert ui-alert--danger" mb={14}>{error}</Box>}
    {loading && !payload ? <Grid columns="repeat(auto-fill,minmax(320px,1fr))" gap={12}>{Array.from({ length: 6 }).map((_, i) => <Box key={i} className="ui-card ui-card__body" height={210} opacity={.55}/>)}</Grid> : workers.length === 0 ? <EmptyState title="No workers match this view" text="Try a different status or search."/> : view === 'cards' ? <Grid columns="repeat(auto-fill,minmax(320px,1fr))" gap={12}>{workers.map(emp => <Pressable type="button" key={emp.member_id} className="ui-card ui-card--interactive" onClick={() => openWorker(emp)} p={17} textAlign="left" fontFamily="inherit" cursor="pointer">
      <Inline align="flex-start" gap={10} mb={13}><Avatar name={emp.name} size="lg"/><Box flex={1} minWidth={0}><Box color="var(--text)" size={13} weight={600}>{emp.name}</Box><div className="ui-card-description">{emp.role || 'Team member'}{emp.department ? ` · ${emp.department}` : ''}</div></Box><Badge tone={statusTone[emp.status]} dot>{statusLabel[emp.status]}</Badge></Inline>
      <Box p="10px 12px" bg="var(--bg)" radius={7} mb={12} minHeight={62}><div className="ui-card-description">Current work</div><Box mt={3} color="var(--text)" size={13} weight={500}>{emp.project || 'No active project'}</Box>{emp.task && <Box mt={2} color="var(--text-2)" size={12}>{emp.task}</Box>}</Box>
      <Grid columns="repeat(3,1fr)" gap={8} mb={10}>{[['Timer', fmtSeconds(emp.timer_seconds)], ['Activity', emp.activity_percent === null ? '—' : `${emp.activity_percent}%`], ['Today', fmtSeconds(emp.tracked_today_seconds)]].map(([l, v]) => <div key={l}><div className="ui-card-description">{l}</div><Box className="stat-num" mt={2} color="var(--text)" size={14} weight={600}>{v}</Box></div>)}</Grid>
      <Progress value={emp.activity_percent ?? 0} tone={(emp.activity_percent ?? 0) > 70 ? 'success' : (emp.activity_percent ?? 0) > 40 ? 'warning' : 'danger'}/>
      <Box display="flex" justify="space-between" gap={10} mt={10} color="var(--text-3)" size={10}><Box as="span" display="inline-flex" align="center" gap={4}><AppWindow size={11}/>{emp.app_name || emp.domain || 'No current app'}</Box><Box as="span" display="inline-flex" align="center" gap={4}><Camera size={11}/> {ago(emp.last_screenshot_at)}</Box><span>{ago(emp.last_seen_at)}</span></Box>
    </Pressable>)}</Grid> : <DataGrid rows={workers} columns={liveColumns} rowKey={row => row.member_id} persistKey={`live-workforce.${view}`} searchable={false} defaultPageSize={view === 'compact' ? 50 : 25} pageSizeOptions={[10, 25, 50, 100]} ariaLabel="Live workforce" mobileCard={row => <Stack gap={5}><Inline justify="space-between"><Button size="sm" variant="ghost" onClick={() => openWorker(row)}>{row.name}</Button><Badge tone={statusTone[row.status]}>{statusLabel[row.status]}</Badge></Inline><span className="ui-card-description">{row.project || 'No active project'}{row.task ? ` · ${row.task}` : ''}</span></Stack>}/>}

    <Drawer open={Boolean(selected)} onClose={() => setSelected(null)} title={selected?.name || 'Worker timeline'} description={selected ? `${selected.role || 'Team member'} · ${statusLabel[selected.status]}` : undefined}>
      {selected && <Box display="flex" direction="column" gap={16}>
        <Grid columns="repeat(3,1fr)" gap={8}>{[['Status', statusLabel[selected.status]], ['Today', fmtSeconds(selected.tracked_today_seconds)], ['Activity', selected.activity_percent === null ? '—' : `${selected.activity_percent}%`]].map(([k, v]) => <Box key={k} className="ui-card ui-card__body" p={10}><div className="ui-card-description">{k}</div><Box size={13} weight={600} color="var(--text)" mt={3}>{v}</Box></Box>)}</Grid>
        <Inline gap={8} align="center" wrap="wrap"><Input type="date" value={timelineDate} onChange={e => setTimelineDate(e.target.value)} width={155}/>{timelineGroups.map(group => <Button key={group} size="sm" variant={timelineGroupsSelected.includes(group) ? 'secondary' : 'outline'} onClick={() => setTimelineGroupsSelected(prev => prev.includes(group) ? prev.filter(x => x !== group) : [...prev, group])}>{group}</Button>)}</Inline>
        {timelineLoading ? <Box className="ui-card ui-card__body" height={140} opacity={.6}/> : !timeline || timeline.events.length === 0 ? <EmptyState icon={<History size={24}/>} title="No timeline events" text="No tracked events match this date and filter."/> : <Box position="relative" pl={20}><Box position="absolute" left={5} top={6} bottom={6} width={1} bg="var(--border)"/>{timeline.events.map(event => <Box key={event.key} position="relative" p="0 0 14px 15px"><Box as="span" position="absolute" left={-19} top={5} width={9} height={9} radius="50%" bg={event.group === 'applications' ? 'var(--accent)' : event.group === 'websites' ? 'var(--info)' : event.group === 'attendance' ? 'var(--success)' : event.group === 'screenshots' ? 'var(--warning)' : 'var(--text-3)'} border="2px solid var(--surface)"/><Box className="ui-card" p="10px 12px"><Inline justify="space-between" gap={12}><div><Box size={12} weight={600} color="var(--text)">{event.title}</Box><Box className="ui-card-description" mt={2}>{[event.project, event.task, event.detail].filter(Boolean).join(' · ') || event.group}</Box></div><Box textAlign="right" shrink={0}><div className="ui-card-description">{clock(event.started_at)}</div>{event.duration_seconds !== null && <Box className="stat-num" size={11} color="var(--text-2)" mt={2}>{fmtSeconds(event.duration_seconds)}</Box>}</Box></Inline>{event.activity_percent !== null && <Inline align="center" gap={7} mt={8}><Progress value={event.activity_percent}/><span className="ui-card-description">{event.activity_percent}%</span></Inline>}<Box display="flex" gap={10} mt={7} color="var(--text-3)" size={10}>{event.device && <span><Laptop2 size={10}/> {event.device}</span>}{event.type === 'website.session' && <span><Globe2 size={10}/> Domain only</span>}{event.type === 'app.session' && <span><AppWindow size={10}/> Application</span>}<span><Clock3 size={10}/> {event.source}</span></Box></Box></Box>)}</Box>}
      </Box>}
    </Drawer>
  </Page>;
}
