import { useEffect, useMemo, useState } from 'react';
import { Activity as ActivityIcon, AppWindow, Clock3, Globe2, Info, RefreshCw, ShieldCheck } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { PageLoadingState } from '../components/LoadingStates';
import { Alert, Badge, Button, Card, CardBody, CardHeader, DataGrid, Page, PageHeader, Progress, StatCard, Box, Grid, Inline, Stack, type DataGridColumn } from '../design-system';
type Classification = 'productive' | 'neutral' | 'unproductive' | 'unclassified';
type UsageRow = {
    type: 'app' | 'domain';
    name: string;
    category: string;
    seconds: number;
    active_seconds: number;
    share: number;
    people: number;
    classification: Classification;
};
type Payload = {
    range: {
        from: string;
        to: string;
    };
    stats: {
        tracked_seconds: number;
        productive_percent: number;
        applications: number;
        domains: number;
    };
    usage: UsageRow[];
};
/** Formats format duration data for display. */ function formatDuration(seconds: number) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0)
        return `${hours}h ${minutes}m`;
    if (minutes > 0)
        return `${minutes}m`;
    return `${seconds}s`;
}
/** Handles the tone operation for the WorkIntel client. */ function tone(value: Classification): 'success' | 'warning' | 'danger' | 'neutral' {
    if (value === 'productive')
        return 'success';
    if (value === 'neutral')
        return 'warning';
    if (value === 'unproductive')
        return 'danger';
    return 'neutral';
}
/** Handles the activity operation for the WorkIntel client. */ export default function Activity() {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [data, setData] = useState<Payload | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    /** Loads load data required by the current view. */ const load = async (silent = false) => {
        if (!workspaceId)
            return;
        if (!silent)
            setLoading(true);
        setError('');
        try {
            setData(await apiRequest<Payload>('/api/v1/activity-tracking', { workspaceId, silent }));
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not load activity analytics.');
        }
        finally {
            if (!silent)
                setLoading(false);
        }
    };
    useEffect(() => { void load(); }, [workspaceId]);
    const metrics = useMemo(() => {
        const rows = data?.usage ?? [];
        const tracked = data?.stats.tracked_seconds ?? 0;
        const active = rows.reduce((sum, row) => sum + row.active_seconds, 0);
        const idle = Math.max(0, tracked - active);
        const productive = rows.filter(row => row.classification === 'productive').reduce((sum, row) => sum + row.seconds, 0);
        const neutral = rows.filter(row => row.classification === 'neutral').reduce((sum, row) => sum + row.seconds, 0);
        const unproductive = rows.filter(row => row.classification === 'unproductive').reduce((sum, row) => sum + row.seconds, 0);
        const unclassified = rows.filter(row => row.classification === 'unclassified').reduce((sum, row) => sum + row.seconds, 0);
        return { tracked, active, idle, productive, neutral, unproductive, unclassified };
    }, [data]);
    if (loading && !data)
        return <PageLoadingState />;
    const activePercent = metrics.tracked > 0 ? Math.round((metrics.active / metrics.tracked) * 100) : 0;
    const idlePercent = metrics.tracked > 0 ? 100 - activePercent : 0;
    const usageColumns = useMemo<DataGridColumn<UsageRow>[]>(() => [
        { id: 'source', header: 'Source', cell: row => <Inline gap={8} align="center">{row.type === 'app' ? <AppWindow size={14}/> : <Globe2 size={14}/>}<strong>{row.name}</strong></Inline>, searchValue: row => `${row.name} ${row.type}`, sortable: true },
        { id: 'category', header: 'Category', cell: row => row.category, searchValue: row => row.category, filter: { type: 'text', label: 'Category' }, sortable: true },
        { id: 'tracked', header: 'Tracked', cell: row => <span className="stat-num">{formatDuration(row.seconds)}</span>, sortValue: row => row.seconds },
        { id: 'active', header: 'Active', cell: row => <span className="stat-num">{formatDuration(row.active_seconds)}</span>, sortValue: row => row.active_seconds },
        { id: 'share', header: 'Share', cell: row => `${row.share}%`, sortValue: row => row.share },
        { id: 'people', header: 'People', cell: row => row.people, sortValue: row => row.people },
        { id: 'classification', header: 'Classification', cell: row => <Badge tone={tone(row.classification)}>{row.classification}</Badge>, filterValue: row => row.classification, filter: { type: 'select', label: 'Classification', options: [{ value: 'productive', label: 'Productive' }, { value: 'neutral', label: 'Neutral' }, { value: 'unproductive', label: 'Unproductive' }, { value: 'unclassified', label: 'Unclassified' }] }, sortable: true },
    ], []);
    return <Page>
    <PageHeader title="Activity Analytics" description={data ? `${data.range.from} → ${data.range.to}` : 'Tracked activity'} actions={<Button size="sm" variant="outline" loading={loading} onClick={() => void load()}><RefreshCw size={14}/> Refresh</Button>}/>

    {error && <Alert tone="danger">{error}</Alert>}
    <Alert tone="info" icon={<Info size={14}/>}><strong>Activity ≠ Productivity.</strong> Interaction and classified app/domain time explain work patterns; they are not a performance score. Deep reading, meetings and thinking can have low device activity.</Alert>

    <Grid columns="repeat(auto-fit,minmax(180px,1fr))" gap={12} m="14px 0 16px">
      <StatCard label="Tracked Usage" value={formatDuration(metrics.tracked)} sub="App + domain sessions" icon={<Clock3 size={16}/>}/>
      <StatCard label="Active Time" value={formatDuration(metrics.active)} sub={`${activePercent}% of tracked`} icon={<ActivityIcon size={16}/>}/>
      <StatCard label="Idle Time" value={formatDuration(metrics.idle)} sub={`${idlePercent}% of tracked`} icon={<Clock3 size={16}/>}/>
      <StatCard label="Productive Time" value={formatDuration(metrics.productive)} sub={`${data?.stats.productive_percent ?? 0}% of classified usage`} icon={<ShieldCheck size={16}/>}/>
      <StatCard label="Sources" value={`${data?.stats.applications ?? 0} / ${data?.stats.domains ?? 0}`} sub="Apps / domains" icon={<AppWindow size={16}/>}/>
    </Grid>

    <Grid columns="repeat(auto-fit,minmax(300px,1fr))" gap={14} mb={14}>
      <Card><CardHeader title="Interaction Split" description="Active vs idle seconds reported by collectors"/><CardBody>
        <Stack gap={14}>
          <div><Box display="flex" justify="space-between" mb={6} size={12}><span>Active</span><strong>{activePercent}%</strong></Box><Progress value={activePercent} tone="success"/></div>
          <div><Box display="flex" justify="space-between" mb={6} size={12}><span>Idle</span><strong>{idlePercent}%</strong></Box><Progress value={idlePercent} tone="warning"/></div>
        </Stack>
      </CardBody></Card>

      <Card><CardHeader title="Classification Split" description="Based on effective workspace/team/member/project rules"/><CardBody>
        <Stack gap={10}>
          {([
            ['Productive', metrics.productive, 'success'],
            ['Neutral', metrics.neutral, 'warning'],
            ['Unproductive', metrics.unproductive, 'danger'],
            ['Unclassified', metrics.unclassified, 'accent'],
        ] as const).map(([label, seconds, barTone]) => {
            const pct = metrics.tracked > 0 ? Math.round((Number(seconds) / metrics.tracked) * 100) : 0;
            return <div key={String(label)}><Box display="flex" justify="space-between" mb={5} size={12}><span>{label}</span><span className="stat-num">{formatDuration(Number(seconds))} · {pct}%</span></Box><Progress value={pct} tone={barTone}/></div>;
        })}
        </Stack>
      </CardBody></Card>
    </Grid>

    <Card>
      <CardHeader title="Top Applications & Websites" description="Current normalized activity dataset. Full browser URLs and typed content are not stored."/>
      <CardBody p={0}>
        <DataGrid rows={data?.usage ?? []} columns={usageColumns} rowKey={row => `${row.type}:${row.name}`} persistKey="activity-usage" defaultSort={{ id: 'tracked', direction: 'desc' }} searchPlaceholder="Search applications or websites…" empty={<Box p={24} className="ui-card-description">No tracked application or website sessions yet.</Box>} mobileCard={row => <Stack gap={6}><Inline justify="space-between" gap={8}><Inline gap={7} align="center">{row.type === 'app' ? <AppWindow size={14}/> : <Globe2 size={14}/>}<strong>{row.name}</strong></Inline><Badge tone={tone(row.classification)}>{row.classification}</Badge></Inline><Inline justify="space-between"><span className="ui-card-description">{row.category}</span><span className="stat-num">{formatDuration(row.seconds)} · {row.share}%</span></Inline></Stack>}/>
      </CardBody>
    </Card>
  </Page>;
}
