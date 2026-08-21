import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Activity, Banknote, BarChart3, CalendarClock, Check, Clock3, Download, FileChartColumnIncreasing, FolderKanban, History, Play, Plus, Save, Settings2, Trash2, Users, } from 'lucide-react';
import { Area, AreaChart as ReAreaChart, Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip as ChartTooltip, XAxis, YAxis, } from 'recharts';
import { apiDownload, apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { PageLoadingState } from '../components/LoadingStates';
import { useConfirmAction, ErrorState, Alert, Badge, Button, Card, CardBody, CardHeader, Drawer, EmptyState, Field, Input, Page, PageHeader, Segmented, Select, Textarea, Pressable, Checkbox, Box, Grid, Inline, Stack, Label, Option, DataGrid, FormDialog, SettingRow, Text, type DataGridColumn } from '../design-system';
import { type Catalog, type Column, type Dataset, type FilterDef, type Preview, type ReportConfig, type ReportExport, type ReportRun, type SavedReport, type Schedule, type Visualization, datasetIcons, dateTime, daysAgo, defaultConfig, formatCell, money, triggerDownload } from './reports/support';
/** Handles the reports operation for the WorkIntel client. */ export default function Reports() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [mode, setMode] = useState<'overview' | 'builder' | 'saved' | 'schedules' | 'history'>('overview');
    const [catalog, setCatalog] = useState<Catalog | null>(null);
    const [saved, setSaved] = useState<SavedReport[]>([]);
    const [runs, setRuns] = useState<ReportRun[]>([]);
    const [schedules, setSchedules] = useState<Schedule[]>([]);
    const [config, setConfig] = useState<ReportConfig | null>(null);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [overview, setOverview] = useState<Preview | null>(null);
    const [loading, setLoading] = useState(true);
    const [previewing, setPreviewing] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [saveModal, setSaveModal] = useState(false);
    const [scheduleModal, setScheduleModal] = useState(false);
    const [runDrawer, setRunDrawer] = useState(false);
    const [selectedRun, setSelectedRun] = useState<ReportRun | null>(null);
    const [saveForm, setSaveForm] = useState({ name: 'Custom Workforce Report', description: '', is_shared: true });
    const [scheduleForm, setScheduleForm] = useState({ saved_report_id: '', name: 'Scheduled Report', frequency: 'weekly' as Schedule['frequency'], time_of_day: '08:00', day_of_week: '1', day_of_month: '1', timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC', export_format: 'pdf' as ReportExport['format'], active: true });
    const currentDataset = useMemo(() => catalog?.datasets.find(item => item.key === config?.dataset) ?? null, [catalog, config?.dataset]);
    const canManage = Boolean(catalog?.can_manage);
    const navOptions = useMemo(() => [
        { value: 'overview' as const, label: 'Overview' }, { value: 'builder' as const, label: 'Builder' }, { value: 'saved' as const, label: 'Saved' },
        ...(canManage ? [{ value: 'schedules' as const, label: 'Schedules' }] : []), { value: 'history' as const, label: 'History' },
    ], [canManage]);
    /** Loads load data required by the current view. */ const load = async () => {
        if (!workspaceId)
            return;
        setLoading(true);
        setError('');
        try {
            const catalogPayload = await apiRequest<Catalog>('/api/v1/reports/catalog', { workspaceId });
            setCatalog(catalogPayload);
            const first = catalogPayload.datasets[0];
            if (first && !config)
                setConfig(defaultConfig(first));
            const [savedPayload, runsPayload, schedulePayload] = await Promise.all([
                apiRequest<{
                    data: SavedReport[];
                }>('/api/v1/reports/saved', { workspaceId }),
                apiRequest<{
                    data: ReportRun[];
                }>('/api/v1/reports/runs', { workspaceId }),
                catalogPayload.can_manage ? apiRequest<{
                    data: Schedule[];
                }>('/api/v1/reports/schedules', { workspaceId }) : Promise.resolve({ data: [] as Schedule[] }),
            ]);
            setSaved(savedPayload.data);
            setRuns(runsPayload.data);
            setSchedules(schedulePayload.data);
            const timeDataset = catalogPayload.datasets.find(item => item.key === 'time_entries') ?? first;
            if (timeDataset) {
                const summaryConfig = { ...defaultConfig(timeDataset), date_preset: 'custom' as const, date_from: daysAgo(180), dimensions: ['month'], metrics: timeDataset.metrics.some(m => m.key === 'billable_hours') ? ['tracked_hours', 'billable_hours'] : [timeDataset.default_metrics[0]], visualization: { type: 'bar' as const, x: 'month', y: 'tracked_hours' } };
                try {
                    setOverview(await apiRequest<Preview>('/api/v1/reports/preview', { method: 'POST', workspaceId, silent: true, body: JSON.stringify({ configuration: summaryConfig }) }));
                }
                catch { /* overview chart is optional */ }
            }
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not load reports.');
        }
        finally {
            setLoading(false);
        }
    };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Handles the select dataset operation for the WorkIntel client. */ const selectDataset = (key: string) => { const dataset = catalog?.datasets.find(item => item.key === key); if (dataset) {
        setConfig(defaultConfig(dataset));
        setPreview(null);
    } };
    /** Handles the toggle list operation for the WorkIntel client. */ const toggleList = (key: 'dimensions' | 'metrics', value: string) => { if (!config)
        return; const list = config[key]; const next = list.includes(value) ? list.filter(item => item !== value) : [...list, value]; if (key === 'metrics' && next.length === 0)
        return; setConfig({ ...config, [key]: next, sort: key === 'metrics' && !next.includes(config.sort.key) ? { key: next[0], direction: 'desc' } : config.sort, visualization: { ...config.visualization, x: key === 'dimensions' && !next.includes(config.visualization.x ?? '') ? (next[0] ?? null) : config.visualization.x, y: key === 'metrics' && !next.includes(config.visualization.y ?? '') ? (next[0] ?? null) : config.visualization.y } }); };
    /** Updates set filter state for the current workflow. */ const setFilter = (key: string, value: unknown) => { if (config)
        setConfig({ ...config, filters: { ...config.filters, [key]: value } }); };
    /** Handles the run preview operation for the WorkIntel client. */ const runPreview = async () => { if (!config)
        return; setPreviewing(true); setError(''); try {
        const data = await apiRequest<Preview>('/api/v1/reports/preview', { method: 'POST', workspaceId, body: JSON.stringify({ configuration: config }) });
        setPreview(data);
        setConfig(data.configuration);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not preview report.');
    }
    finally {
        setPreviewing(false);
    } };
    /** Handles the run ad hoc operation for the WorkIntel client. */ const runAdHoc = async () => { if (!config)
        return; setSaving(true); setError(''); try {
        const payload = await apiRequest<{
            data: ReportRun;
        }>('/api/v1/reports/runs', { method: 'POST', workspaceId, body: JSON.stringify({ name: saveForm.name || 'Ad-hoc Report', configuration: config }) });
        await refreshRuns();
        await openRun(payload.data.id);
        setMode('history');
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not run report.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the save report operation for the WorkIntel client. */ const saveReport = async (event: FormEvent) => { event.preventDefault(); if (!config)
        return; setSaving(true); setError(''); try {
        await apiRequest('/api/v1/reports/saved', { method: 'POST', workspaceId, body: JSON.stringify({ ...saveForm, configuration: config }) });
        setSaveModal(false);
        setMessage('Report saved.');
        await refreshSaved();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not save report.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the refresh saved operation for the WorkIntel client. */ const refreshSaved = async () => { const payload = await apiRequest<{
        data: SavedReport[];
    }>('/api/v1/reports/saved', { workspaceId, silent: true }); setSaved(payload.data); };
    /** Handles the refresh runs operation for the WorkIntel client. */ const refreshRuns = async () => { const payload = await apiRequest<{
        data: ReportRun[];
    }>('/api/v1/reports/runs', { workspaceId, silent: true }); setRuns(payload.data); };
    /** Handles the refresh schedules operation for the WorkIntel client. */ const refreshSchedules = async () => { if (!canManage)
        return; const payload = await apiRequest<{
        data: Schedule[];
    }>('/api/v1/reports/schedules', { workspaceId, silent: true }); setSchedules(payload.data); };
    /** Handles the run saved operation for the WorkIntel client. */ const runSaved = async (report: SavedReport) => { setSaving(true); setError(''); try {
        const payload = await apiRequest<{
            data: ReportRun;
        }>(`/api/v1/reports/saved/${report.id}/run`, { method: 'POST', workspaceId });
        await Promise.all([refreshRuns(), refreshSaved()]);
        await openRun(payload.data.id);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not run saved report.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the edit saved operation for the WorkIntel client. */ const editSaved = (report: SavedReport) => { setConfig(report.configuration); setSaveForm({ name: `${report.name} Copy`, description: report.description ?? '', is_shared: report.is_shared }); setPreview(null); setMode('builder'); };
    /** Handles the delete saved operation for the WorkIntel client. */ const deleteSaved = async (report: SavedReport) => { if (!await confirmAction({ title: 'Delete saved report?', description: `Delete ${report.name}?`, confirmLabel: 'Delete', danger: true }))
        return; try {
        await apiRequest(`/api/v1/reports/saved/${report.id}`, { method: 'DELETE', workspaceId });
        await Promise.all([refreshSaved(), refreshSchedules()]);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not delete report.');
    } };
    /** Handles the open run operation for the WorkIntel client. */ const openRun = async (id: number) => { try {
        const payload = await apiRequest<{
            data: ReportRun;
        }>(`/api/v1/reports/runs/${id}`, { workspaceId });
        setSelectedRun(payload.data);
        setRunDrawer(true);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load report run.');
    } };
    /** Handles the export run operation for the WorkIntel client. */ const exportRun = async (run: ReportRun, format: ReportExport['format']) => { setSaving(true); setError(''); try {
        const created = await apiRequest<{
            data: ReportExport;
        }>(`/api/v1/reports/runs/${run.id}/exports`, { method: 'POST', workspaceId, body: JSON.stringify({ format }) });
        const file = await apiDownload(`/api/v1/reports/exports/${created.data.id}/download`, workspaceId);
        triggerDownload(file.blob, file.filename);
        if (selectedRun?.id === run.id)
            await openRun(run.id);
        await refreshRuns();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not export report.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the save schedule operation for the WorkIntel client. */ const saveSchedule = async (event: FormEvent) => { event.preventDefault(); setSaving(true); setError(''); try {
        await apiRequest('/api/v1/reports/schedules', { method: 'POST', workspaceId, body: JSON.stringify({ ...scheduleForm, saved_report_id: Number(scheduleForm.saved_report_id), day_of_week: scheduleForm.frequency === 'weekly' ? Number(scheduleForm.day_of_week) : null, day_of_month: scheduleForm.frequency === 'monthly' ? Number(scheduleForm.day_of_month) : null }) });
        setScheduleModal(false);
        await refreshSchedules();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not save schedule.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the run schedule now operation for the WorkIntel client. */ const runScheduleNow = async (schedule: Schedule) => { setSaving(true); try {
        await apiRequest(`/api/v1/reports/schedules/${schedule.id}/run-now`, { method: 'POST', workspaceId });
        await Promise.all([refreshSchedules(), refreshRuns()]);
        setMessage('Scheduled report generated.');
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not run schedule.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the delete schedule operation for the WorkIntel client. */ const deleteSchedule = async (schedule: Schedule) => { if (!await confirmAction({ title: 'Delete report schedule?', description: `Delete ${schedule.name}?`, confirmLabel: 'Delete', danger: true }))
        return; try {
        await apiRequest(`/api/v1/reports/schedules/${schedule.id}`, { method: 'DELETE', workspaceId });
        await refreshSchedules();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not delete schedule.');
    } };
    if (loading)
        return <PageLoadingState title="Loading reporting workspace" description="Preparing datasets, saved reports and run history."/>;
    if (!catalog || !config || !currentDataset)
        return <Page><ErrorState title="Reporting unavailable" text={error || 'No reporting datasets are available for your permissions.'} retry={load}/></Page>;
    return <Page>
    <PageHeader title="Reports" description="Build, save, schedule and export permission-aware workforce reports" actions={<Segmented value={mode} onChange={setMode} options={navOptions}/>}/>
    {error && <Alert tone="danger" mb={12}>{error}</Alert>}{message && <Alert tone="success" mb={12}>{message}</Alert>}

    {mode === 'overview' && <Overview catalog={catalog} overview={overview} saved={saved} runs={runs} onRun={runSaved} onOpen={openRun} onBuilder={() => setMode('builder')} saving={saving}/>} 
    {mode === 'builder' && <Builder catalog={catalog} dataset={currentDataset} config={config} setConfig={setConfig} toggleList={toggleList} setFilter={setFilter} preview={preview} onPreview={runPreview} previewing={previewing} onRun={runAdHoc} onSave={() => setSaveModal(true)} saving={saving}/>} 
    {mode === 'saved' && <SavedReports saved={saved} catalog={catalog} onRun={runSaved} onEdit={editSaved} onDelete={deleteSaved} canManage={canManage} saving={saving}/>} 
    {mode === 'schedules' && canManage && <Schedules schedules={schedules} saved={saved} onAdd={() => { setScheduleForm({ ...scheduleForm, saved_report_id: String(saved[0]?.id ?? '') }); setScheduleModal(true); }} onRun={runScheduleNow} onDelete={deleteSchedule} saving={saving}/>} 
    {mode === 'history' && <RunHistory runs={runs} onOpen={openRun} onExport={exportRun} saving={saving}/>} 

    <FormDialog open={saveModal} onClose={() => setSaveModal(false)} title="Save report" description="Save this exact report configuration for reuse and scheduling." formId="save-report-form" onSubmit={saveReport} submitLabel="Save Report" loading={saving}><Field label="Name"><Input value={saveForm.name} onChange={e => setSaveForm({ ...saveForm, name: e.target.value })} required/></Field><Field label="Description"><Textarea value={saveForm.description} onChange={e => setSaveForm({ ...saveForm, description: e.target.value })}/></Field><SettingRow title="Shared report" description="Allow workspace members with dataset access to use this report." control={<Checkbox checked={saveForm.is_shared} onChange={e => setSaveForm({ ...saveForm, is_shared: e.target.checked })}/>}/></FormDialog>

    <FormDialog open={scheduleModal} onClose={() => setScheduleModal(false)} title="Schedule report" description="Generate a fresh report and export automatically on a recurring schedule." formId="schedule-report-form" onSubmit={saveSchedule} submitLabel="Create Schedule" loading={saving}>
        <Field label="Saved report"><Select value={scheduleForm.saved_report_id} onChange={e => setScheduleForm({ ...scheduleForm, saved_report_id: e.target.value })} required><Option value="">Select report</Option>{saved.map(r => <Option key={r.id} value={r.id}>{r.name}</Option>)}</Select></Field>
        <Field label="Schedule name"><Input value={scheduleForm.name} onChange={e => setScheduleForm({ ...scheduleForm, name: e.target.value })} required/></Field>
        <Grid columns="1fr 1fr" gap={10}><Field label="Frequency"><Select value={scheduleForm.frequency} onChange={e => setScheduleForm({ ...scheduleForm, frequency: e.target.value as Schedule['frequency'] })}><Option value="daily">Daily</Option><Option value="weekly">Weekly</Option><Option value="monthly">Monthly</Option></Select></Field><Field label="Time"><Input type="time" value={scheduleForm.time_of_day} onChange={e => setScheduleForm({ ...scheduleForm, time_of_day: e.target.value })}/></Field></Grid>
        {scheduleForm.frequency === 'weekly' && <Field label="Day"><Select value={scheduleForm.day_of_week} onChange={e => setScheduleForm({ ...scheduleForm, day_of_week: e.target.value })}>{['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'].map((label, index) => <Option key={label} value={index}>{label}</Option>)}</Select></Field>}
        {scheduleForm.frequency === 'monthly' && <Field label="Day of month"><Input type="number" min={1} max={28} value={scheduleForm.day_of_month} onChange={e => setScheduleForm({ ...scheduleForm, day_of_month: e.target.value })}/></Field>}
        <Grid columns="1fr 1fr" gap={10}><Field label="Timezone"><Input value={scheduleForm.timezone} onChange={e => setScheduleForm({ ...scheduleForm, timezone: e.target.value })}/></Field><Field label="Export"><Select value={scheduleForm.export_format} onChange={e => setScheduleForm({ ...scheduleForm, export_format: e.target.value as ReportExport['format'] })}><Option value="pdf">PDF</Option><Option value="xlsx">Excel (.xlsx)</Option><Option value="csv">CSV</Option></Select></Field></Grid>
      </FormDialog>

    <Drawer open={runDrawer} onClose={() => setRunDrawer(false)} title={selectedRun?.name ?? 'Report Run'} description={selectedRun ? `${selectedRun.row_count} rows · ${dateTime(selectedRun.completed_at)}` : undefined} footer={selectedRun?.status === 'completed' ? <Inline gap={8}><Button size="sm" variant="outline" loading={saving} onClick={() => selectedRun && exportRun(selectedRun, 'csv')}><Download size={13}/> CSV</Button><Button size="sm" variant="outline" loading={saving} onClick={() => selectedRun && exportRun(selectedRun, 'xlsx')}><Download size={13}/> Excel</Button><Button size="sm" variant="primary" loading={saving} onClick={() => selectedRun && exportRun(selectedRun, 'pdf')}><Download size={13}/> PDF</Button></Inline> : undefined}>
      {selectedRun && <ResultTable result={{ dataset: selectedRun.dataset, range: { from: selectedRun.configuration.date_from, to: selectedRun.configuration.date_to }, configuration: selectedRun.configuration, columns: selectedRun.columns ?? [], rows: selectedRun.rows ?? [], row_count: selectedRun.row_count, summary: selectedRun.summary ?? {} }} compact/>}
    </Drawer>
  </Page>;
}
/** Handles the overview operation for the WorkIntel client. */ function Overview({ catalog, overview, saved, runs, onRun, onOpen, onBuilder, saving }: {
    catalog: Catalog;
    overview: Preview | null;
    saved: SavedReport[];
    runs: ReportRun[];
    onRun: (report: SavedReport) => void;
    onOpen: (id: number) => void;
    onBuilder: () => void;
    saving: boolean;
}) {
    const chartRows = overview?.rows ?? [];
    return <>
    <Card mb={16}><CardHeader title="Workforce reporting" description="One reporting layer across time, attendance, payroll, activity, projects and people." action={<Button variant="primary" onClick={onBuilder}><Plus size={14}/> Build Report</Button>}/><CardBody>
      <Grid columns="repeat(auto-fit,minmax(150px,1fr))" gap={10}>{catalog.datasets.map(dataset => { const Icon = datasetIcons[dataset.key] ?? FileChartColumnIncreasing; return <Box key={dataset.key} p={12} border="1px solid var(--border)" radius={8} bg="var(--bg)"><Icon size={17}/><Box size={13} weight={600} mt={8}>{dataset.label}</Box><Box className="ui-card-description" mt={3}>{dataset.metrics.length} metrics · {dataset.dimensions.length} dimensions</Box></Box>; })}</Grid>
    </CardBody></Card>
    <Card mb={16}><CardHeader title="Tracked vs Billable Hours" description="Recent monthly trend from real time entries"/><CardBody>{chartRows.length ? <ResponsiveContainer width="100%" height={220}><BarChart data={chartRows}><CartesianGrid strokeDasharray="3 3" stroke="var(--border-muted)" vertical={false}/><XAxis dataKey="month" tick={{ fill: 'var(--text-3)', fontSize: 11 }}/><YAxis tick={{ fill: 'var(--text-3)', fontSize: 11 }}/><ChartTooltip contentStyle={{ background: 'var(--elevated)', border: '1px solid var(--border)', borderRadius: 8 }}/><Bar dataKey="tracked_hours" name="Tracked" fill="var(--accent)" radius={[3, 3, 0, 0]}/>{overview?.configuration.metrics.includes('billable_hours') && <Bar dataKey="billable_hours" name="Billable" fill="var(--success)" radius={[3, 3, 0, 0]}/>}</BarChart></ResponsiveContainer> : <EmptyState title="No tracked time in this range" text="The overview chart will populate as time entries are recorded."/>}</CardBody></Card>
    <Grid columns="minmax(0,1fr) minmax(0,1fr)" gap={16}>
      <Card><CardHeader title="Saved Reports" description={`${saved.length} reusable configuration${saved.length === 1 ? '' : 's'}`}/><CardBody>{saved.slice(0, 6).map(report => <Box key={report.id} className="ui-menu-item" minHeight={52}><Save size={15}/><Box flex={1}><Box size={13} weight={600}>{report.name}</Box><div className="ui-card-description">{catalog.datasets.find(d => d.key === report.dataset)?.label ?? report.dataset} · {report.last_run_at ? `Last run ${dateTime(report.last_run_at)}` : 'Never run'}</div></Box><Button size="sm" variant="ghost" loading={saving} onClick={() => onRun(report)}><Play size={13}/> Run</Button></Box>)}{!saved.length && <EmptyState title="No saved reports" text="Build and save a report to reuse it later."/>}</CardBody></Card>
      <Card><CardHeader title="Recent Runs" description="Latest generated snapshots"/><CardBody>{runs.slice(0, 6).map(run => <Pressable key={run.id} type="button" className="ui-menu-item" onClick={() => onOpen(run.id)} width="100%" border={0} textAlign="left" minHeight={52}><History size={15}/><Box flex={1}><Box size={13} weight={600}>{run.name}</Box><div className="ui-card-description">{run.row_count} rows · {dateTime(run.created_at)}</div></Box><Badge tone={run.status === 'completed' ? 'success' : run.status === 'failed' ? 'danger' : 'warning'}>{run.status}</Badge></Pressable>)}{!runs.length && <EmptyState title="No report runs" text="Run a saved or ad-hoc report to create a historical snapshot."/>}</CardBody></Card>
    </Grid>
  </>;
}
/** Handles the builder operation for the WorkIntel client. */ function Builder({ catalog, dataset, config, setConfig, toggleList, setFilter, preview, onPreview, previewing, onRun, onSave, saving }: {
    catalog: Catalog;
    dataset: Dataset;
    config: ReportConfig;
    setConfig: (config: ReportConfig) => void;
    toggleList: (key: 'dimensions' | 'metrics', value: string) => void;
    setFilter: (key: string, value: unknown) => void;
    preview: Preview | null;
    onPreview: () => void;
    previewing: boolean;
    onRun: () => void;
    onSave: () => void;
    saving: boolean;
}) {
    return <Grid columns="minmax(280px,340px) minmax(0,1fr)" gap={16} align="start">
    <Card><CardHeader title="Report Builder" description="Configure one reusable report definition."/><CardBody><Stack gap={15}>
      <Field label="Dataset"><Select value={config.dataset} onChange={e => { const ds = catalog.datasets.find(item => item.key === e.target.value); if (ds)
        setConfig(defaultConfig(ds)); }}>{catalog.datasets.map(ds => <Option key={ds.key} value={ds.key}>{ds.label}</Option>)}</Select></Field>
      <Field label="Date Range"><Select value={config.date_preset} onChange={e => setConfig({ ...config, date_preset: e.target.value as ReportConfig['date_preset'] })}><Option value="last_7_days">Last 7 days</Option><Option value="last_30_days">Last 30 days</Option><Option value="this_week">This week</Option><Option value="last_week">Last week</Option><Option value="this_month">This month</Option><Option value="last_month">Last month</Option><Option value="custom">Custom</Option></Select></Field>
      {config.date_preset === 'custom' && <Grid columns="1fr 1fr" gap={8}><Field label="From"><Input type="date" value={config.date_from} onChange={e => setConfig({ ...config, date_from: e.target.value, date_preset: 'custom' })}/></Field><Field label="To"><Input type="date" value={config.date_to} onChange={e => setConfig({ ...config, date_to: e.target.value, date_preset: 'custom' })}/></Field></Grid>}
      <OptionButtons title="Dimensions" items={dataset.dimensions} selected={config.dimensions} onToggle={value => toggleList('dimensions', value)}/>
      <OptionButtons title="Metrics" items={dataset.metrics} selected={config.metrics} onToggle={value => toggleList('metrics', value)}/>
      <div><Box className="ui-label" mb={7}>Filters</Box><Stack gap={8}>{dataset.filters.map(filter => <FilterControl key={filter.key} filter={filter} catalog={catalog} dataset={dataset.key} value={config.filters[filter.key]} onChange={value => setFilter(filter.key, value)}/>)}</Stack></div>
      <Grid columns="1fr 1fr" gap={8}><Field label="Visualization"><Select value={config.visualization.type} onChange={e => setConfig({ ...config, visualization: { ...config.visualization, type: e.target.value as Visualization['type'] } })}><Option value="table">Table</Option><Option value="bar">Bar Chart</Option><Option value="line">Line Chart</Option><Option value="area">Area Chart</Option></Select></Field><Field label="Sort"><Select value={`${config.sort.key}:${config.sort.direction}`} onChange={e => { const [key, direction] = e.target.value.split(':'); setConfig({ ...config, sort: { key, direction: direction as 'asc' | 'desc' } }); }}>{[...config.dimensions, ...config.metrics].flatMap(key => [<Option key={`${key}-desc`} value={`${key}:desc`}>{key} ↓</Option>, <Option key={`${key}-asc`} value={`${key}:asc`}>{key} ↑</Option>])}</Select></Field></Grid>
      <Inline gap={8} wrap="wrap"><Button variant="primary" loading={previewing} onClick={onPreview}><BarChart3 size={14}/> Preview</Button><Button variant="outline" loading={saving} onClick={onRun}><Play size={14}/> Run Snapshot</Button>{catalog.can_manage && <Button variant="secondary" onClick={onSave}><Save size={14}/> Save</Button>}</Inline>
    </Stack></CardBody></Card>
    <Card><CardHeader title="Preview" description={preview ? `${preview.row_count} grouped row${preview.row_count === 1 ? '' : 's'} · ${preview.range.from} → ${preview.range.to}` : 'Run a preview to inspect this configuration.'}/><CardBody>{preview ? <><ReportVisualization result={preview}/><Box mt={14}><ResultTable result={preview}/></Box></> : <EmptyState icon={<FileChartColumnIncreasing size={30}/>} title="Report preview" text="Select dimensions, metrics and filters, then preview the live data."/>}</CardBody></Card>
  </Grid>;
}
/** Handles the option buttons operation for the WorkIntel client. */ function OptionButtons({ title, items, selected, onToggle }: {
    title: string;
    items: Array<{
        key: string;
        label: string;
    }>;
    selected: string[];
    onToggle: (key: string) => void;
}) { return <div><Box className="ui-label" mb={7}>{title}</Box><Inline gap={6} wrap="wrap">{items.map(item => <Pressable key={item.key} type="button" onClick={() => onToggle(item.key)} display="inline-flex" align="center" gap={5} p="6px 8px" radius={6} border={`1px solid ${selected.includes(item.key) ? 'var(--accent)' : 'var(--border)'}`} bg={selected.includes(item.key) ? 'var(--accent-dim)' : 'var(--bg)'} color={selected.includes(item.key) ? 'var(--accent)' : 'var(--text-2)'} fontFamily="inherit" size={11} cursor="pointer">{selected.includes(item.key) && <Check size={11}/>} {item.label}</Pressable>)}</Inline></div>; }
/** Handles the filter control operation for the WorkIntel client. */ function FilterControl({ filter, catalog, dataset, value, onChange }: {
    filter: FilterDef;
    catalog: Catalog;
    dataset: string;
    value: unknown;
    onChange: (value: unknown) => void;
}) {
    if (filter.type === 'boolean')
        return <Field label={filter.label}><Select value={value === true ? 'true' : value === false ? 'false' : ''} onChange={e => onChange(e.target.value === '' ? '' : e.target.value === 'true')}><Option value="">All</Option><Option value="true">Billable</Option><Option value="false">Non-billable</Option></Select></Field>;
    const staticOptions: Record<string, string[]> = { statuses: dataset === 'attendance' ? ['present', 'late', 'absent', 'missing_clock_out'] : dataset === 'payroll' ? ['draft', 'calculated', 'review', 'approved', 'paid'] : dataset === 'projects' ? ['active', 'on_hold', 'completed'] : dataset === 'employees' ? ['active', 'inactive'] : ['draft', 'submitted', 'approved', 'rejected'], sources: ['web', 'desktop', 'manual', 'api'], classifications: ['productive', 'neutral', 'unproductive', 'unclassified'] };
    const sourceOptions = filter.source === 'members' ? catalog.options.members : filter.source === 'departments' ? catalog.options.departments : filter.source === 'projects' ? catalog.options.projects : filter.source === 'clients' ? catalog.options.clients : null;
    const selected = Array.isArray(value) ? value.map(String) : [];
    return <Field label={filter.label}><Select multiple value={selected} onChange={e => onChange(Array.from(e.target.selectedOptions).map(option => sourceOptions ? Number(option.value) : option.value))} minHeight={72}>{sourceOptions ? sourceOptions.map(option => <Option key={option.id} value={option.id}>{option.name}</Option>) : (staticOptions[filter.source] ?? []).map(option => <Option key={option} value={option}>{option.replaceAll('_', ' ')}</Option>)}</Select></Field>;
}
/** Render saved report definitions through the shared DataGrid V3 contract. */ function SavedReports({ saved, catalog, onRun, onEdit, onDelete, canManage, saving }: {
    saved: SavedReport[];
    catalog: Catalog;
    onRun: (report: SavedReport) => void;
    onEdit: (report: SavedReport) => void;
    onDelete: (report: SavedReport) => void;
    canManage: boolean;
    saving: boolean;
}) {
    const columns: DataGridColumn<SavedReport>[] = [
        { id: 'report', header: 'Report', searchValue: r => `${r.name} ${r.description ?? ''}`, sortValue: r => r.name, cell: r => <Stack gap={2}><Text weight={650}>{r.name}</Text><Text size={10.5} color="var(--text-3)">{r.description || 'No description'}</Text></Stack> },
        { id: 'dataset', header: 'Dataset', filterValue: r => r.dataset, cell: r => catalog.datasets.find(d => d.key === r.dataset)?.label ?? r.dataset },
        { id: 'shared', header: 'Access', filterValue: r => r.is_shared ? 'workspace' : 'private', cell: r => <Badge tone={r.is_shared ? 'success' : 'neutral'}>{r.is_shared ? 'Workspace' : 'Private'}</Badge> },
        { id: 'schedules', header: 'Schedules', sortValue: r => r.schedules_count, cell: r => r.schedules_count },
        { id: 'last_run', header: 'Last run', sortValue: r => r.last_run_at ?? '', filterValue: r => r.last_run_at ?? '', filter: { type: 'dateRange', label: 'Last run' }, cell: r => dateTime(r.last_run_at) },
        { id: 'actions', header: '', hideable: false, cell: r => <Inline gap={5}><Button size="sm" variant="ghost" loading={saving} onClick={() => onRun(r)}><Play size={13}/> Run</Button>{canManage && <><Button size="sm" variant="ghost" iconOnly aria-label={`Edit ${r.name}`} onClick={() => onEdit(r)}><Settings2 size={13}/></Button><Button size="sm" variant="ghost" iconOnly aria-label={`Delete ${r.name}`} onClick={() => onDelete(r)}><Trash2 size={13}/></Button></>}</Inline> },
    ];
    return <DataGrid rows={saved} columns={columns} rowKey={row => row.id} persistKey="reports.saved" defaultSort={{ id: 'report', direction: 'asc' }} empty={<EmptyState title="No saved reports" text="Use Report Builder to create the first reusable report."/>}/>;
}
/** Render scheduled report jobs through DataGrid V3. */ function Schedules({ schedules, saved, onAdd, onRun, onDelete, saving }: {
    schedules: Schedule[];
    saved: SavedReport[];
    onAdd: () => void;
    onRun: (schedule: Schedule) => void;
    onDelete: (schedule: Schedule) => void;
    saving: boolean;
}) {
    const columns: DataGridColumn<Schedule>[] = [
        { id: 'schedule', header: 'Schedule', searchValue: r => `${r.name} ${r.timezone}`, sortValue: r => r.name, cell: r => <Stack gap={2}><Text weight={650}>{r.name}</Text><Text size={10.5} color="var(--text-3)">{r.timezone} · {r.time_of_day}</Text></Stack> },
        { id: 'report', header: 'Report', searchValue: r => r.saved_report?.name ?? '', cell: r => r.saved_report?.name || '—' },
        { id: 'frequency', header: 'Frequency', filterValue: r => r.frequency, cell: r => `${r.frequency}${r.frequency === 'weekly' ? ` · day ${r.day_of_week}` : r.frequency === 'monthly' ? ` · day ${r.day_of_month}` : ''}` },
        { id: 'export', header: 'Export', filterValue: r => r.export_format, cell: r => r.export_format.toUpperCase() },
        { id: 'next', header: 'Next run', sortValue: r => r.next_run_at ?? '', filterValue: r => r.next_run_at ?? '', filter: { type: 'dateRange', label: 'Next run' }, cell: r => dateTime(r.next_run_at) },
        { id: 'status', header: 'Status', filterValue: r => r.active ? 'active' : 'paused', cell: r => <Badge tone={r.active ? 'success' : 'neutral'}>{r.active ? 'Active' : 'Paused'}</Badge> },
        { id: 'actions', header: '', hideable: false, cell: r => <Inline gap={5}><Button size="sm" variant="ghost" loading={saving} onClick={() => onRun(r)}><Play size={13}/> Run Now</Button><Button size="sm" variant="ghost" iconOnly aria-label={`Delete ${r.name}`} onClick={() => onDelete(r)}><Trash2 size={13}/></Button></Inline> },
    ];
    return <DataGrid rows={schedules} columns={columns} rowKey={row => row.id} persistKey="reports.schedules" toolbar={<Button variant="primary" onClick={onAdd} disabled={!saved.length}><Plus size={14}/> Schedule</Button>} defaultSort={{ id: 'next', direction: 'asc' }} empty={<EmptyState icon={<CalendarClock size={28}/>} title="No report schedules" text={saved.length ? 'Schedule a saved report for daily, weekly or monthly generation.' : 'Save a report first, then create a schedule.'}/>}/>;
}
/** Render historical report snapshots through DataGrid V3. */ function RunHistory({ runs, onOpen, onExport, saving }: {
    runs: ReportRun[];
    onOpen: (id: number) => void;
    onExport: (run: ReportRun, format: ReportExport['format']) => void;
    saving: boolean;
}) {
    const columns: DataGridColumn<ReportRun>[] = [
        { id: 'run', header: 'Run', searchValue: r => `${r.name} ${r.dataset}`, sortValue: r => r.name, cell: r => <Pressable type="button" onClick={() => onOpen(r.id)}><Text weight={650}>{r.name}</Text></Pressable> },
        { id: 'dataset', header: 'Dataset', filterValue: r => r.dataset, cell: r => r.dataset.replaceAll('_', ' ') },
        { id: 'status', header: 'Status', filterValue: r => r.status, cell: r => <Badge tone={r.status === 'completed' ? 'success' : r.status === 'failed' ? 'danger' : 'warning'}>{r.status}</Badge> },
        { id: 'rows', header: 'Rows', sortValue: r => r.row_count, cell: r => r.row_count },
        { id: 'generated', header: 'Generated', sortValue: r => r.completed_at ?? r.created_at, filterValue: r => r.completed_at ?? r.created_at, filter: { type: 'dateRange', label: 'Generated' }, cell: r => dateTime(r.completed_at ?? r.created_at) },
        { id: 'exports', header: 'Exports', cell: r => <Inline gap={4} wrap="wrap">{r.exports?.filter(item => item.status === 'completed').map(item => <Badge key={item.id}>{item.format.toUpperCase()}</Badge>)}</Inline> },
        { id: 'actions', header: '', hideable: false, cell: r => <Inline gap={4}>{(['csv', 'xlsx', 'pdf'] as const).map(format => <Button key={format} size="sm" variant="ghost" loading={saving} onClick={() => onExport(r, format)}>{format.toUpperCase()}</Button>)}</Inline> },
    ];
    return <DataGrid rows={runs} columns={columns} rowKey={row => row.id} persistKey="reports.runs" defaultSort={{ id: 'generated', direction: 'desc' }} empty={<EmptyState title="No report runs" text="Generate a report snapshot from Builder or Saved Reports."/>}/>;
}
/** Handles the report visualization operation for the WorkIntel client. */ function ReportVisualization({ result }: {
    result: Preview;
}) {
    const { visualization } = result.configuration;
    if (visualization.type === 'table' || !visualization.x || !visualization.y || !result.rows.length)
        return null;
    const common = <><CartesianGrid strokeDasharray="3 3" stroke="var(--border-muted)" vertical={false}/><XAxis dataKey={visualization.x} tick={{ fill: 'var(--text-3)', fontSize: 10 }}/><YAxis tick={{ fill: 'var(--text-3)', fontSize: 10 }}/><ChartTooltip contentStyle={{ background: 'var(--elevated)', border: '1px solid var(--border)', borderRadius: 8 }}/></>;
    return <Box height={260} mb={12}><ResponsiveContainer width="100%" height="100%">{visualization.type === 'line' ? <LineChart data={result.rows}>{common}<Line type="monotone" dataKey={visualization.y} stroke="var(--accent)" strokeWidth={2}/></LineChart> : visualization.type === 'area' ? <ReAreaChart data={result.rows}>{common}<Area type="monotone" dataKey={visualization.y} stroke="var(--accent)" fill="var(--accent-dim)"/></ReAreaChart> : <BarChart data={result.rows}>{common}<Bar dataKey={visualization.y} fill="var(--accent)" radius={[3, 3, 0, 0]}/></BarChart>}</ResponsiveContainer></Box>;
}
/** Render dynamic report results through DataGrid V3 while preserving report-specific formatting. */ function ResultTable({ result, compact = false }: {
    result: Preview;
    compact?: boolean;
}) {
    if (!result.columns.length)
        return <EmptyState title="No report columns"/>;
    const currencyDimension = result.columns.find(column => column.key === 'currency');
    const columns: DataGridColumn<Record<string, any>>[] = result.columns.map(column => ({ id: column.key, header: column.label, searchValue: row => String(row[column.key] ?? ''), sortValue: row => row[column.key], cell: row => formatCell(row[column.key], column, row.currency ? String(row.currency) : 'USD') }));
    return <div><Inline gap={8} wrap="wrap" mb={10}>{result.columns.filter(c => c.type === 'metric').map(column => <Badge key={column.key} tone="info">{column.label}: {formatCell(result.summary[column.key], column, currencyDimension && result.rows[0]?.currency ? String(result.rows[0].currency) : 'USD')}</Badge>)}</Inline><DataGrid rows={result.rows} columns={columns} rowKey={row => result.rows.indexOf(row)} persistKey={`reports.result.${result.dataset}`} searchable={!compact} defaultPageSize={compact ? 10 : 25} empty={<EmptyState title="No rows match these filters"/>}/></div>;
}
