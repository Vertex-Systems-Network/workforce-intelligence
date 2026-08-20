import { useEffect, useMemo, useState } from 'react';
import { Activity, AlertTriangle, CheckCircle2, ChevronRight, CircleDot, Gauge, RefreshCw, Settings2, ShieldAlert, Users } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { ErrorState, EmptyState, Alert, Badge, Button, Card, CardBody, CardHeader, Field, Input, Page, PageHeader, Progress, Select, StatCard, Switch, Tabs, Pressable, Box, Grid, Inline, Stack, Text, Option, DataGrid, SettingRow, type DataGridColumn } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
type Metric = {
    value: number;
    unit: string | null;
    dimensions?: Record<string, unknown> | null;
};
type Insight = {
    id: number;
    uuid: string;
    category: string;
    insight_type: string;
    scope_type: string;
    scope_id: number | null;
    scope_label: string | null;
    severity: 'info' | 'warning' | 'danger' | 'critical';
    title: string;
    summary: string;
    explanation: string;
    metrics: Record<string, unknown>;
    source_refs: Array<Record<string, unknown>>;
    recommendations: string[];
    status: 'open' | 'acknowledged' | 'dismissed' | 'resolved';
    last_detected_at: string;
    detected_at: string;
};
type Rule = {
    id: number;
    rule_key: string;
    name: string;
    category: string;
    status: string;
    severity: string;
    window_days: number;
    threshold_value: string | null;
    threshold_secondary: string | null;
    config: Record<string, unknown> | null;
};
type Run = {
    id: number;
    uuid: string;
    trigger: string;
    status: string;
    started_at: string;
    completed_at: string | null;
    stats: Record<string, number> | null;
    error: string | null;
};
type Member = {
    id: number;
    job_title: string | null;
    user: {
        first_name: string;
        last_name: string;
    };
};
type Project = {
    id: number;
    name: string;
    code: string | null;
    status: string;
    currency: string;
};
type SnapshotGroup = {
    member_id?: number;
    project_id?: number;
    metrics: Record<string, Metric>;
};
type Settings = {
    id: number;
    enabled: boolean;
    run_interval_minutes: number;
    forecast_days: number;
    default_capacity_hours: string;
    automation_events_enabled: boolean;
    snapshot_retention_days: number;
};
type Payload = {
    stats: {
        open: number;
        acknowledged: number;
        critical: number;
        danger: number;
        warning: number;
    };
    by_category: Record<string, number>;
    insights: Insight[];
    member_snapshots: SnapshotGroup[];
    project_snapshots: SnapshotGroup[];
    members: Member[];
    projects: Project[];
    latest_run: Run | null;
    settings: Settings;
    can_manage: boolean;
    can_manage_rules: boolean;
    rules: Rule[];
};
/** Handles the severity tone operation for the WorkIntel client. */ const severityTone = (s: string): 'neutral' | 'info' | 'warning' | 'danger' | 'success' => s === 'critical' || s === 'danger' ? 'danger' : s === 'warning' ? 'warning' : s === 'info' ? 'info' : 'neutral';
/** Handles the label operation for the WorkIntel client. */ const label = (key: string) => key.replaceAll('_', ' ').replace(/\b\w/g, (c: string) => c.toUpperCase());
/** Formats fmt data for display. */ const fmt = (value: number | undefined, unit?: string | null) => value === undefined ? '—' : `${Number.isInteger(value) ? value : value.toFixed(1)}${unit === 'percent' || unit === 'percentage_points' ? '%' : unit === 'hours' ? 'h' : unit === 'days' ? 'd' : unit && unit !== 'count' ? ` ${unit}` : ''}`;
/** Handles the insights operation for the WorkIntel client. */ export default function Insights() {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [data, setData] = useState<Payload | null>(null), [tab, setTab] = useState<'signals' | 'capacity' | 'projects' | 'history' | 'rules'>('signals'), [loading, setLoading] = useState(true), [saving, setSaving] = useState(false), [error, setError] = useState(''), [runs, setRuns] = useState<Run[]>([]), [selected, setSelected] = useState<Insight | null>(null);
    /** Loads load data required by the current view. */ const load = async () => {
        if (!workspaceId)
            return;
        setLoading(true);
        try {
            setData(await apiRequest<Payload>('/api/v1/intelligence/overview', { workspaceId }));
            setError('');
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not load workforce intelligence.');
        }
        finally {
            setLoading(false);
        }
    };
    useEffect(() => { void load(); }, [workspaceId]);
    useEffect(() => {
        if (tab === 'history' && data && (data.can_manage || session?.user.workspaces.find(w => w.id === workspaceId)?.permissions.includes('intelligence.view_all')))
            void apiRequest<{
                runs: Run[];
            }>('/api/v1/intelligence/history', { workspaceId }).then(r => setRuns(r.runs)).catch(() => { });
    }, [tab, workspaceId, data?.can_manage]);
    /** Handles the run now operation for the WorkIntel client. */ const runNow = async () => {
        setSaving(true);
        setError('');
        try {
            await apiRequest('/api/v1/intelligence/run', { method: 'POST', workspaceId });
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Intelligence run failed.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the status operation for the WorkIntel client. */ const status = async (row: Insight, action: 'acknowledge' | 'dismiss' | 'resolve' | 'reopen') => {
        setSaving(true);
        try {
            await apiRequest(`/api/v1/intelligence/insights/${row.id}/status`, { method: 'PATCH', workspaceId, body: JSON.stringify({ action }) });
            setSelected(null);
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not update signal.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the save settings operation for the WorkIntel client. */ const saveSettings = async () => {
        if (!data)
            return;
        setSaving(true);
        try {
            const r = await apiRequest<{
                data: Settings;
            }>('/api/v1/intelligence/settings', { method: 'PUT', workspaceId, body: JSON.stringify(data.settings) });
            setData({ ...data, settings: r.data });
            setError('');
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not save intelligence settings.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Updates update rule state for the current workflow. */ const updateRule = async (rule: Rule) => {
        setSaving(true);
        try {
            await apiRequest(`/api/v1/intelligence/rules/${rule.id}`, { method: 'PATCH', workspaceId, body: JSON.stringify({ status: rule.status, severity: rule.severity, window_days: Number(rule.window_days), threshold_value: rule.threshold_value === '' ? null : Number(rule.threshold_value), threshold_secondary: rule.threshold_secondary === '' ? null : Number(rule.threshold_secondary) }) });
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not save rule.');
        }
        finally {
            setSaving(false);
        }
    };
    const memberMap = useMemo(() => new Map(data?.members.map(m => [m.id, m]) ?? []), [data?.members]);
    const projectMap = useMemo(() => new Map(data?.projects.map(p => [p.id, p]) ?? []), [data?.projects]);
    const capacityColumns: DataGridColumn<SnapshotGroup>[] = [
        { id: 'employee', header: 'Employee', searchValue: row => { const m = memberMap.get(row.member_id ?? 0); return m ? `${m.user.first_name} ${m.user.last_name} ${m.job_title ?? ''}` : `Member ${row.member_id}`; }, sortValue: row => { const m = memberMap.get(row.member_id ?? 0); return m ? `${m.user.first_name} ${m.user.last_name}` : `Member ${row.member_id}`; }, cell: row => { const m = memberMap.get(row.member_id ?? 0); return <Stack gap={2}><Text weight={650}>{m ? `${m.user.first_name} ${m.user.last_name}` : `Member ${row.member_id}`}</Text>{m?.job_title && <Text size={10.5} color="var(--text-3)">{m.job_title}</Text>}</Stack>; } },
        { id: 'capacity', header: 'Capacity', sortValue: row => row.metrics.capacity_hours?.value ?? 0, cell: row => fmt(row.metrics.capacity_hours?.value, 'hours') || '—' },
        { id: 'scheduled', header: 'Scheduled', sortValue: row => row.metrics.scheduled_hours?.value ?? 0, cell: row => fmt(row.metrics.scheduled_hours?.value, 'hours') },
        { id: 'assigned', header: 'Assigned tasks', sortValue: row => row.metrics.assigned_task_hours?.value ?? 0, cell: row => fmt(row.metrics.assigned_task_hours?.value, 'hours') },
        { id: 'tracked', header: 'Tracked 7d', sortValue: row => row.metrics.tracked_hours_7d?.value ?? 0, cell: row => fmt(row.metrics.tracked_hours_7d?.value, 'hours') },
        { id: 'utilization', header: 'Planned utilization', sortValue: row => row.metrics.member_utilization_pct?.value ?? 0, cell: row => { const u = row.metrics.member_utilization_pct?.value ?? 0; return <Stack gap={4}><Text size={11}>{u.toFixed(1)}%</Text><Progress value={Math.min(100, u)} tone={u > 110 ? 'warning' : 'accent'}/></Stack>; } },
    ];
    const runColumns: DataGridColumn<Run>[] = [
        { id: 'started', header: 'Started', sortValue: r => r.started_at, filterValue: r => r.started_at, filter: { type: 'dateRange', label: 'Started' }, cell: r => new Date(r.started_at).toLocaleString() },
        { id: 'trigger', header: 'Trigger', filterValue: r => r.trigger, cell: r => r.trigger },
        { id: 'status', header: 'Status', filterValue: r => r.status, filter: { type: 'select', label: 'Status', options: ['completed', 'failed', 'running', 'queued'].map(value => ({ value, label: value })) }, cell: r => <Badge tone={r.status === 'completed' ? 'success' : r.status === 'failed' ? 'danger' : 'warning'}>{r.status}</Badge> },
        { id: 'created', header: 'Created', sortValue: r => r.stats?.created ?? 0, cell: r => r.stats?.created ?? 0 },
        { id: 'reopened', header: 'Reopened', sortValue: r => r.stats?.reopened ?? 0, cell: r => r.stats?.reopened ?? 0 },
        { id: 'resolved', header: 'Resolved', sortValue: r => r.stats?.resolved ?? 0, cell: r => r.stats?.resolved ?? 0 },
        { id: 'snapshots', header: 'Snapshots', sortValue: r => r.stats?.snapshots ?? 0, cell: r => r.stats?.snapshots ?? 0 },
    ];
    if (loading && !data)
        return <PageLoadingState />;
    if (!data)
        return <Page><ErrorState title="Workforce Intelligence unavailable" text={error || 'Workforce Intelligence could not be loaded for this workspace.'} retry={load}/></Page>;
    const high = data.insights.filter(i => i.severity === 'critical' || i.severity === 'danger').length;
    return <Page><PageHeader title="Workforce Intelligence" description="Explainable operational signals calculated from schedules, attendance, projects, payroll and approved workforce data" actions={<Inline gap={7}><Button variant="outline" size="sm" onClick={() => void load()}><RefreshCw size={13}/>Refresh</Button>{data.can_manage && <Button variant="primary" size="sm" loading={saving} onClick={() => void runNow()}><Activity size={13}/>Recalculate</Button>}</Inline>}/>{error && <Alert tone="danger">{error}</Alert>}
  <Grid columns="repeat(4,minmax(0,1fr))" gap={10} m="14px 0"><StatCard label="Open signals" value={String(data.stats.open)} sub={`${data.stats.acknowledged} acknowledged`} icon={<CircleDot size={16}/>}/><StatCard label="High severity" value={String(high)} sub="Critical + danger" icon={<ShieldAlert size={16}/>}/><StatCard label="Warnings" value={String(data.stats.warning)} sub="Needs review" icon={<AlertTriangle size={16}/>}/><StatCard label="Last calculation" value={data.latest_run?.completed_at ? new Date(data.latest_run.completed_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'Not run'} sub={data.latest_run?.status ?? 'No snapshot'} icon={<Gauge size={16}/>}/></Grid>
  <Tabs value={tab} onChange={setTab} tabs={[{ value: 'signals', label: 'Signals' }, { value: 'capacity', label: 'Capacity & Workload' }, { value: 'projects', label: 'Project Risk' }, ...(data.can_manage || session?.user.workspaces.find(w => w.id === workspaceId)?.permissions.includes('intelligence.view_all') ? [{ value: 'history' as const, label: 'Run History' }] : []), ...(data.can_manage_rules ? [{ value: 'rules' as const, label: 'Rules & Thresholds' }] : [])]}/>
  {tab === 'signals' && <Box display="grid" gridColumns={selected ? 'minmax(0,1fr) minmax(330px,.7fr)' : '1fr'} gap={12} mt={12}><Stack gap={9}>{data.insights.length ? data.insights.map(row => <Pressable type="button" key={row.id} onClick={() => setSelected(row)} textAlign="left" border="1px solid var(--border)" bg={selected?.id === row.id ? 'var(--elevated)' : 'var(--surface)'} radius={9} p={12} color="var(--text)" cursor="pointer"><Inline justify="space-between" gap={10} align="flex-start"><div><Inline gap={6} align="center" wrap="wrap"><Badge tone={severityTone(row.severity)}>{row.severity}</Badge><Badge tone="neutral">{label(row.category)}</Badge>{row.status === 'acknowledged' && <Badge tone="info">acknowledged</Badge>}</Inline><Text as="strong" display="block" size={13} mt={7}>{row.title}</Text><Box className="ui-card-description" mt={4}>{row.summary}</Box><Text as="small" display="block" mt={7} color="var(--text-3)">{row.scope_label || row.scope_type} · {new Date(row.last_detected_at).toLocaleString()}</Text></div><ChevronRight size={16}/></Inline></Pressable>) : <Card><CardBody><EmptyState icon={<CheckCircle2 size={24}/>} title="No active signals" text="There are no active workforce signals in your current access scope."/></CardBody></Card>}</Stack>{selected && <SignalDetail row={selected} canManage={data.can_manage} saving={saving} onAction={status}/>}</Box>}
  {tab === 'capacity' && <DataGrid rows={data.member_snapshots} columns={capacityColumns} rowKey={row => row.member_id ?? 0} persistKey="intelligence.capacity" defaultSort={{ id: 'utilization', direction: 'desc' }} empty={<EmptyState title="No capacity snapshots in your access scope." text="Capacity appears after schedules or task estimates are available."/>}/>}
  {tab === 'projects' && <Grid columns="repeat(auto-fill,minmax(290px,1fr))" gap={10} mt={12}>{data.project_snapshots.map(group => { const p = projectMap.get(group.project_id ?? 0), cost = group.metrics.project_cost_utilization_pct?.value, margin = group.metrics.project_margin_pct?.value, progress = group.metrics.project_task_completion_pct?.value, forecast = group.metrics.project_forecast_cost?.value; return <Card key={group.project_id}><CardHeader title={p?.name || `Project ${group.project_id}`} description={p?.code || p?.status}/><CardBody><Grid columns="1fr 1fr" gap={8}><Mini label="Cost used" value={fmt(cost, 'percent')}/><Mini label="Margin" value={fmt(margin, 'percent')}/><Mini label="Tasks complete" value={fmt(progress, 'percent')}/><Mini label="Forecast cost" value={forecast !== undefined ? `${p?.currency || ''} ${forecast.toLocaleString()}` : '—'}/></Grid></CardBody></Card>; })}{!data.project_snapshots.length && <Card><CardBody><EmptyState title="No project intelligence is available in your access scope."/></CardBody></Card>}</Grid>}
  {tab === 'history' && <DataGrid rows={runs} columns={runColumns} rowKey={row => row.id} persistKey="intelligence.runs" defaultSort={{ id: 'started', direction: 'desc' }} empty={<EmptyState title="No intelligence runs yet." text="Run history appears after intelligence calculations execute."/>}/>}
  {tab === 'rules' && data.can_manage_rules && <Stack gap={12} mt={12}><Card><CardHeader title="Intelligence settings" description="Controls calculation cadence, capacity baseline and history. These settings do not change payroll or employee performance records." action={<Button size="sm" loading={saving} onClick={() => void saveSettings()}><Settings2 size={13}/>Save settings</Button>}/><CardBody><Grid columns="repeat(3,minmax(0,1fr))" gap={10}><Field label="Run interval (minutes)"><Input type="number" min="15" max="1440" value={data.settings.run_interval_minutes} onChange={e => setData({ ...data, settings: { ...data.settings, run_interval_minutes: Number(e.target.value) } })}/></Field><Field label="Forecast days"><Input type="number" min="7" max="60" value={data.settings.forecast_days} onChange={e => setData({ ...data, settings: { ...data.settings, forecast_days: Number(e.target.value) } })}/></Field><Field label="Default weekly capacity"><Input type="number" min="1" max="168" value={data.settings.default_capacity_hours} onChange={e => setData({ ...data, settings: { ...data.settings, default_capacity_hours: e.target.value } })}/></Field><Field label="Snapshot retention days"><Input type="number" min="30" max="3650" value={data.settings.snapshot_retention_days} onChange={e => setData({ ...data, settings: { ...data.settings, snapshot_retention_days: Number(e.target.value) } })}/></Field><SettingRow title="Intelligence enabled" description="Stops scheduled calculations when disabled." control={<Switch checked={data.settings.enabled} onChange={v => setData({ ...data, settings: { ...data.settings, enabled: v } })}/>}/><SettingRow title="Automation events" description="Expose new/resolved signals to workflow automations." control={<Switch checked={data.settings.automation_events_enabled} onChange={v => setData({ ...data, settings: { ...data.settings, automation_events_enabled: v } })}/>}/></Grid></CardBody></Card><Card><CardHeader title="Explainable rule thresholds" description="A rule only fires when its displayed threshold is met. Disable noisy rules instead of hiding their output."/><CardBody><Stack gap={8}>{data.rules.map((rule, index) => <Box key={rule.id} display="grid" gridColumns="minmax(220px,1.4fr) 110px 110px 110px 110px auto" gap={8} align="end" p="10px 0" borderBottom={index === data.rules.length - 1 ? '0' : '1px solid var(--border-muted)'}><div><Text as="strong" size={12}>{rule.name}</Text><Text as="small" display="block" color="var(--text-3)" mt={3}>{rule.rule_key} · {String(rule.config?.description ?? '')}</Text></div><Field label="Status"><Select value={rule.status} onChange={e => setData({ ...data, rules: data.rules.map(x => x.id === rule.id ? { ...x, status: e.target.value } : x) })}><Option value="active">Active</Option><Option value="disabled">Disabled</Option></Select></Field><Field label="Severity"><Select value={rule.severity} onChange={e => setData({ ...data, rules: data.rules.map(x => x.id === rule.id ? { ...x, severity: e.target.value } : x) })}><Option value="info">Info</Option><Option value="warning">Warning</Option><Option value="danger">Danger</Option><Option value="critical">Critical</Option></Select></Field><Field label="Window days"><Input type="number" min="1" value={rule.window_days} onChange={e => setData({ ...data, rules: data.rules.map(x => x.id === rule.id ? { ...x, window_days: Number(e.target.value) } : x) })}/></Field><Field label="Threshold"><Input type="number" value={rule.threshold_value ?? ''} onChange={e => setData({ ...data, rules: data.rules.map(x => x.id === rule.id ? { ...x, threshold_value: e.target.value } : x) })}/></Field><Button variant="outline" size="sm" loading={saving} onClick={() => void updateRule(rule)}>Save</Button></Box>)}</Stack></CardBody></Card></Stack>}
  </Page>;
}
/** Handles the mini operation for the WorkIntel client. */ function Mini({ label: caption, value }: {
    label: string;
    value: string;
}) { return <Box p={9} radius={7} bg="var(--bg)"><div className="ui-card-description">{caption}</div><Text as="strong" size={13} display="block" mt={4}>{value}</Text></Box>; }
/** Handles the signal detail operation for the WorkIntel client. */ function SignalDetail({ row, canManage, saving, onAction }: {
    row: Insight;
    canManage: boolean;
    saving: boolean;
    onAction: (row: Insight, action: 'acknowledge' | 'dismiss' | 'resolve' | 'reopen') => Promise<void>;
}) { const metrics = Object.entries(row.metrics ?? {}).filter(([, v]) => typeof v === 'string' || typeof v === 'number'); return <Card><CardHeader title={row.title} description={`${row.scope_label || row.scope_type} · ${new Date(row.last_detected_at).toLocaleString()}`} action={<Badge tone={severityTone(row.severity)}>{row.severity}</Badge>}/><CardBody><Box mb={14}><Box className="ui-card-description" mb={5}>Why this fired</Box><Box size={12} lineHeight={1.65}>{row.explanation}</Box></Box>{metrics.length > 0 && <Grid columns="repeat(2,minmax(0,1fr))" gap={7} mb={14}>{metrics.slice(0, 10).map(([k, v]) => <Mini key={k} label={label(k)} value={typeof v === 'number' ? v.toLocaleString() : String(v)}/>)}</Grid>}<Box mb={14}><Box className="ui-card-description" mb={6}>Recommended next actions</Box><Stack gap={6}>{(row.recommendations ?? []).map((r, i) => <Box key={i} display="flex" gap={7} size={11} lineHeight={1.5}><Box as="span" mt={2} color="var(--success)" flex="0 0 auto"><CheckCircle2 size={13}/></Box>{r}</Box>)}</Stack></Box><Inline gap={7} wrap="wrap">{row.status === 'open' && <Button size="sm" variant="outline" loading={saving} onClick={() => void onAction(row, 'acknowledge')}>Acknowledge</Button>}<Button size="sm" variant="ghost" onClick={() => void onAction(row, 'dismiss')}>Dismiss</Button>{canManage && row.status !== 'resolved' && <Button size="sm" variant="outline" onClick={() => void onAction(row, 'resolve')}>Resolve</Button>}{canManage && row.status === 'resolved' && <Button size="sm" variant="outline" onClick={() => void onAction(row, 'reopen')}>Reopen</Button>}</Inline></CardBody></Card>; }
