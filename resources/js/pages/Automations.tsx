import { FormEvent, useEffect, useMemo, useState } from 'react';
import { AlertTriangle, Archive, Braces, CheckCircle2, Copy, History, Link2, Play, Plus, RefreshCw, RotateCcw, Settings2, Trash2, Webhook, Workflow, XCircle, Zap } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { useConfirmAction, ErrorState, EmptyState, Alert, Badge, Button, Card, CardBody, CardHeader, Field, Input, Modal, Page, PageHeader, Select, StatCard, Switch, Tabs, Textarea, DataGrid, FormDialog, type DataGridColumn, Pressable, Box, Grid, Inline, Stack, Text, Label, Option } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
import ActionEditor from './automations/ActionEditor';
import { type ActionForm, type Condition, type DeadRow, type HookRow, type Overview, type RunRow, type Template, type WorkflowForm, type WorkflowRow, emptyAction, emptyForm, fmt, maybeJson, normalizeConfig, tone } from './automations/support';
/** Handles the automations operation for the WorkIntel client. */ export default function Automations() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [data, setData] = useState<Overview | null>(null), [loading, setLoading] = useState(true), [error, setError] = useState(''), [message, setMessage] = useState(''), [busy, setBusy] = useState(false);
    const [tab, setTab] = useState<'workflows' | 'runs' | 'incoming' | 'dead' | 'connectors'>('workflows'), [workflowOpen, setWorkflowOpen] = useState(false), [hookOpen, setHookOpen] = useState(false), [runOpen, setRunOpen] = useState<RunRow | null>(null), [secret, setSecret] = useState('');
    const [editing, setEditing] = useState<WorkflowRow | null>(null), [form, setForm] = useState(emptyForm()), [testPayload, setTestPayload] = useState('{"test":true}');
    const [hookForm, setHookForm] = useState({ name: 'External Event', event_name: 'incoming.received', workflow_id: '', rate_limit_per_minute: '60' });
    /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        setData(await apiRequest<Overview>('/api/v1/automations/overview', { workspaceId, silent: true }));
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load automations.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Handles the provider for operation for the WorkIntel client. */ const providerFor = (integrationId: number | null) => { const i = data?.integrations.find(x => x.id === integrationId); return data?.connectors.find(c => c.id === i?.provider); };
    /** Handles the start new operation for the WorkIntel client. */ const startNew = () => { setEditing(null); setForm(emptyForm()); setWorkflowOpen(true); };
    /** Handles the edit operation for the WorkIntel client. */ const edit = (row: WorkflowRow) => { setEditing(row); setForm({ name: row.name, description: row.description ?? '', status: row.status === 'archived' ? 'paused' : row.status, trigger_type: row.trigger_type, trigger_event: row.trigger_event ?? '', trigger_config: row.trigger_config ?? { frequency: 'daily', at: '09:00' }, conditions: row.conditions ?? [], condition_mode: row.condition_mode ?? 'all', failure_policy: row.failure_policy ?? 'stop', max_run_seconds: row.max_run_seconds ?? 30, actions: (row.actions ?? []).map(a => ({ ...a, config: a.config ?? {} })) }); setWorkflowOpen(true); };
    /** Handles the submit workflow operation for the WorkIntel client. */ const submitWorkflow = async (e: FormEvent) => { e.preventDefault(); setBusy(true); setError(''); try {
        const payload = { ...form, actions: form.actions.map(a => ({ ...a, config: normalizeConfig(a.config) })) };
        await apiRequest(editing ? `/api/v1/automations/${editing.id}` : '/api/v1/automations', { method: editing ? 'PUT' : 'POST', workspaceId, body: JSON.stringify(payload) });
        setWorkflowOpen(false);
        setMessage(editing ? 'Workflow updated.' : 'Workflow created.');
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not save workflow.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the remove operation for the WorkIntel client. */ const remove = async (row: WorkflowRow) => { if (!await confirmAction({ title: 'Remove automation?', description: `Archive/delete ${row.name}?`, confirmLabel: 'Remove', danger: true }))
        return; setBusy(true); try {
        await apiRequest(`/api/v1/automations/${row.id}`, { method: 'DELETE', workspaceId });
        await load();
    }
    finally {
        setBusy(false);
    } };
    /** Handles the test operation for the WorkIntel client. */ const test = async (row: WorkflowRow) => { setBusy(true); setError(''); try {
        const payload = maybeJson(testPayload);
        const r = await apiRequest<{
            data: RunRow;
        }>(`/api/v1/automations/${row.id}/test`, { method: 'POST', workspaceId, body: JSON.stringify({ payload: typeof payload === 'object' ? payload : { value: payload } }) });
        setRunOpen(r.data);
        setTab('runs');
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Test run failed.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the show run operation for the WorkIntel client. */ const showRun = async (row: RunRow) => { const p = await apiRequest<{
        data: RunRow;
    }>(`/api/v1/automation-runs/${row.id}`, { workspaceId }); setRunOpen(p.data); };
    /** Handles the retry run operation for the WorkIntel client. */ const retryRun = async (row: RunRow) => { setBusy(true); try {
        await apiRequest(`/api/v1/automation-runs/${row.id}/retry`, { method: 'POST', workspaceId });
        setMessage('Retry queued.');
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not retry run.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the add condition operation for the WorkIntel client. */ const addCondition = () => setForm({ ...form, conditions: [...form.conditions, { field: 'payload.status', operator: 'eq', value: 'approved' }] });
    /** Updates update condition state for the current workflow. */ const updateCondition = (i: number, patch: Partial<Condition>) => setForm({ ...form, conditions: form.conditions.map((c, x) => x === i ? { ...c, ...patch } : c) });
    /** Handles the add action operation for the WorkIntel client. */ const addAction = () => setForm({ ...form, actions: [...form.actions, emptyAction()] });
    /** Updates update action state for the current workflow. */ const updateAction = (i: number, patch: Partial<ActionForm>) => setForm({ ...form, actions: form.actions.map((a, x) => x === i ? { ...a, ...patch } : a) });
    /** Handles the apply template operation for the WorkIntel client. */ const applyTemplate = (t: Template) => { setEditing(null); setForm({ ...emptyForm(), name: t.name, description: t.description, trigger_event: t.trigger_event, conditions: t.conditions, actions: [{ ...emptyAction(), ...t.action, config: { ...t.action.config } }] }); setWorkflowOpen(true); };
    /** Handles the create hook operation for the WorkIntel client. */ const createHook = async (e: FormEvent) => { e.preventDefault(); setBusy(true); try {
        const p = await apiRequest<{
            data: HookRow;
            token: string;
        }>('/api/v1/automation-incoming-hooks', { method: 'POST', workspaceId, body: JSON.stringify({ name: hookForm.name, event_name: hookForm.event_name, workflow_id: hookForm.workflow_id ? Number(hookForm.workflow_id) : null, rate_limit_per_minute: Number(hookForm.rate_limit_per_minute) }) });
        setSecret(p.token);
        setHookOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create incoming hook.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the rotate hook operation for the WorkIntel client. */ const rotateHook = async (h: HookRow) => { if (!await confirmAction({ title: 'Rotate incoming-hook token?', description: 'The existing sender will immediately stop working until updated.', confirmLabel: 'Rotate token', danger: true }))
        return; const p = await apiRequest<{
        token: string;
    }>(`/api/v1/automation-incoming-hooks/${h.id}/rotate`, { method: 'POST', workspaceId }); setSecret(p.token); await load(); };
    /** Handles the delete hook operation for the WorkIntel client. */ const deleteHook = async (h: HookRow) => { if (!await confirmAction({ title: 'Delete incoming hook?', description: `Delete ${h.name}?`, confirmLabel: 'Delete', danger: true }))
        return; await apiRequest(`/api/v1/automation-incoming-hooks/${h.id}`, { method: 'DELETE', workspaceId }); await load(); };
    if (loading && !data)
        return <PageLoadingState title="Loading automations" description="Loading workflows, connectors and execution history."/>;
    if (!data)
        return <Page><ErrorState title="Automation platform unavailable" text={error || 'Automation workflows could not be loaded.'} retry={load}/></Page>;
    const succeeded = data.runs.filter(r => r.status === 'succeeded').length, failed = data.runs.filter(r => r.status === 'failed').length, active = data.workflows.filter(w => w.status === 'active').length;
    const workflowColumns: DataGridColumn<WorkflowRow>[] = [
        { id: 'name', header: 'Workflow', searchValue: w => `${w.name} ${w.description ?? ''} ${w.uuid}`, sortValue: w => w.name, cell: w => <Stack gap={2}><Text weight={650}>{w.name}</Text><Text size={10.5} color="var(--text-3)">{w.description || w.uuid}</Text></Stack> },
        { id: 'trigger', header: 'Trigger', searchValue: w => `${w.trigger_type} ${w.trigger_event ?? ''}`, filterValue: w => w.trigger_type, filter: { type: 'select', label: 'Trigger type', options: [{ value: 'event', label: 'Event' }, { value: 'schedule', label: 'Schedule' }, { value: 'incoming', label: 'Incoming' }] }, cell: w => w.trigger_type === 'schedule' ? `Schedule · ${String((w.trigger_config as any)?.frequency ?? '')}` : w.trigger_event || w.trigger_type },
        { id: 'status', header: 'Status', filterValue: w => w.status, filter: { type: 'select', label: 'Status', options: ['draft', 'active', 'paused', 'archived'].map(value => ({ value, label: value })) }, cell: w => <Badge tone={tone(w.status)}>{w.status}</Badge> },
        { id: 'actions_count', header: 'Actions', sortValue: w => w.actions_count, cell: w => w.actions_count },
        { id: 'runs_count', header: 'Runs', sortValue: w => w.runs_count, cell: w => w.runs_count },
        { id: 'last_run', header: 'Last run', sortValue: w => w.last_run_at ?? '', filterValue: w => w.last_run_at ?? '', filter: { type: 'dateRange', label: 'Last run' }, cell: w => fmt(w.last_run_at) },
        { id: 'actions', header: '', hideable: false, cell: w => data.can_manage ? <Inline gap={5}><Button size="sm" variant="ghost" onClick={() => void test(w)} disabled={busy}><Play size={12}/> Test</Button><Button size="sm" variant="outline" onClick={() => void edit(w)}><Settings2 size={12}/> Edit</Button><Button size="sm" variant="ghost" iconOnly aria-label={`Archive ${w.name}`} onClick={() => void remove(w)}><Archive size={12}/></Button></Inline> : null },
    ];
    const runColumns: DataGridColumn<RunRow>[] = [
        { id: 'time', header: 'Time', sortValue: r => r.created_at, filterValue: r => r.created_at, filter: { type: 'dateRange', label: 'Run time' }, cell: r => fmt(r.created_at) },
        { id: 'workflow', header: 'Workflow', searchValue: r => r.workflow?.name ?? String(r.automation_workflow_id), cell: r => r.workflow?.name ?? r.automation_workflow_id },
        { id: 'event', header: 'Event', searchValue: r => r.trigger_event ?? '', cell: r => r.trigger_event || '—' },
        { id: 'status', header: 'Status', filterValue: r => r.status, filter: { type: 'select', label: 'Status', options: ['queued', 'running', 'succeeded', 'partial', 'failed'].map(value => ({ value, label: value })) }, cell: r => <Badge tone={tone(r.status)}>{r.status}</Badge> },
        { id: 'attempts', header: 'Attempts', sortValue: r => r.attempts, cell: r => r.attempts },
        { id: 'error', header: 'Error', searchValue: r => r.error ?? '', defaultHidden: true, cell: r => r.error ?? '—' },
        { id: 'actions', header: '', hideable: false, cell: r => <Button size="sm" variant="outline" onClick={() => void showRun(r)}><History size={12}/> Details</Button> },
    ];
    return <Page><PageHeader title="Automation Studio" description="Trigger → condition → action workflows across WorkIntel and connected services" actions={data.can_manage ? <Button variant="primary" size="sm" onClick={startNew}><Plus size={13}/> New Automation</Button> : undefined}/>{error && <Alert tone="danger">{error}</Alert>}{message && <Alert tone="success">{message}</Alert>}{secret && <Alert tone="warning"><strong>Copy this incoming-hook token now.</strong> It is stored hash-only and cannot be shown again.<Inline gap={8} mt={7}><Box as="code" wordBreak="break-all">{secret}</Box><Button size="sm" variant="outline" onClick={() => void navigator.clipboard.writeText(secret)}><Copy size={12}/> Copy</Button></Inline></Alert>}
 <Grid columns="repeat(4,minmax(0,1fr))" gap={10} m="14px 0"><StatCard label="Active workflows" value={String(active)} sub={`${data.workflows.length} total`}/><StatCard label="Successful runs" value={String(succeeded)} sub="Recent history"/><StatCard label="Failed runs" value={String(failed)} sub={`${data.dead_letters.filter(x => !x.resolved_at).length} open dead letters`}/><StatCard label="Connectors" value={String(data.integrations.filter(i => i.status === 'active').length)} sub={`${data.connectors.length} providers supported`}/></Grid>
 <Tabs value={tab} onChange={setTab} tabs={[{ value: 'workflows', label: 'Workflows' }, { value: 'runs', label: 'Run History' }, { value: 'incoming', label: 'Incoming Hooks' }, { value: 'dead', label: 'Dead Letters' }, { value: 'connectors', label: 'Connectors' }]}/>
 <Box mt={12}>{tab === 'workflows' && <Stack gap={12}>{data.can_manage && data.templates.length > 0 && <Card><CardHeader title="Starter Templates" description="Use a template, then choose the connector or adjust conditions."/><CardBody><Grid columns="repeat(auto-fit,minmax(220px,1fr))" gap={8}>{data.templates.map(t => <Pressable key={t.key} className="automation-template" onClick={() => applyTemplate(t)}><Zap size={15}/><span><strong>{t.name}</strong><small>{t.description}</small></span></Pressable>)}</Grid></CardBody></Card>}<DataGrid rows={data.workflows} columns={workflowColumns} rowKey={row => row.id} persistKey="automations.workflows" onRefresh={load} defaultSort={{ id: 'name', direction: 'asc' }} empty={<EmptyState title="No automation workflows yet." text="Create the first workflow or start from a template."/>}/></Stack>}
 {tab === 'runs' && <DataGrid rows={data.runs} columns={runColumns} rowKey={row => row.id} persistKey="automations.runs" onRefresh={load} defaultSort={{ id: 'time', direction: 'desc' }} empty={<EmptyState title="No automation runs yet." text="Run history appears here after workflows execute or are tested."/>}/>}
 {tab === 'incoming' && <Card><CardHeader title="Incoming Webhooks" description="Bearer-token authenticated inbound events. Token is hash-only after creation." action={data.can_manage ? <Button size="sm" variant="primary" onClick={() => setHookOpen(true)}><Webhook size={12}/> Add Hook</Button> : undefined}/><CardBody><Stack gap={8}>{data.hooks.map(h => <div key={h.id} className="automation-row"><Webhook size={15}/><Box flex={1} minWidth={0}><strong>{h.name}</strong><small>{h.event_name} · {h.endpoint}</small><small>Token {h.token_prefix}•••• · last used {fmt(h.last_used_at)}</small></Box><Badge tone={h.status === 'active' ? 'success' : 'neutral'}>{h.status}</Badge>{data.can_manage && <><Button size="sm" variant="outline" onClick={() => void rotateHook(h)}><RotateCcw size={12}/> Rotate</Button><Button size="sm" variant="ghost" onClick={() => void deleteHook(h)}><Trash2 size={12}/></Button></>}</div>)}{!data.hooks.length && <EmptyState title="No incoming hooks." contextualHelp/>}</Stack></CardBody></Card>}
 {tab === 'dead' && <Card><CardHeader title="Dead Letter Queue" description="Runs that exhausted action retries or stopped on a terminal error."/><CardBody><Stack gap={8}>{data.dead_letters.map(d => <div key={d.id} className="automation-row"><AlertTriangle size={15}/><Box flex={1}><strong>{d.run?.workflow?.name ?? `Run ${d.automation_run_id}`}</strong><small>{d.reason} · {fmt(d.created_at)} · retries {d.retry_count}</small></Box>{d.resolved_at ? <Badge tone="success">Resolved</Badge> : <><Badge tone="danger">Open</Badge>{data.can_manage && d.run && <Button size="sm" variant="primary" onClick={() => void retryRun(d.run!)}><RefreshCw size={12}/> Retry</Button>}</>}</div>)}{!data.dead_letters.length && <EmptyState title="No dead-lettered runs." contextualHelp/>}</Stack></CardBody></Card>}
 {tab === 'connectors' && <Grid columns="repeat(auto-fit,minmax(240px,1fr))" gap={10}>{data.connectors.map(c => { const connections = data.integrations.filter(i => i.provider === c.id); return <Card key={c.id}><CardHeader title={c.name} description={c.description}/><CardBody><Inline gap={6} wrap="wrap" mb={8}><Badge tone="neutral">{c.category}</Badge><Badge tone={connections.some(x => x.status === 'active') ? 'success' : 'neutral'}>{connections.length} connection{connections.length === 1 ? '' : 's'}</Badge></Inline><div className="ui-card-description">Actions: {c.actions.map(a => a.name).join(', ')}</div><Box className="ui-card-description" mt={6}>Configure credentials in Settings → Integrations.</Box></CardBody></Card>; })}</Grid>}</Box>
 <FormDialog open={workflowOpen} onClose={() => setWorkflowOpen(false)} title={editing ? 'Edit automation' : 'New automation'} description="Configure trigger, optional conditions and ordered actions." formId="automation-form" onSubmit={submitWorkflow} submitLabel="Save Workflow" loading={busy} size="lg"><Grid columns="2fr 1fr" gap={9}><Field label="Name"><Input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required/></Field><Field label="Status"><Select value={form.status} onChange={e => setForm({ ...form, status: e.target.value })}><Option value="draft">Draft</Option><Option value="active">Active</Option><Option value="paused">Paused</Option></Select></Field></Grid><Field label="Description"><Textarea value={form.description} onChange={e => setForm({ ...form, description: e.target.value })}/></Field><div className="automation-builder-section"><h4>1. Trigger</h4><Grid columns="1fr 2fr" gap={8}><Field label="Type"><Select value={form.trigger_type} onChange={e => setForm({ ...form, trigger_type: e.target.value })}><Option value="event">WorkIntel Event</Option><Option value="schedule">Schedule</Option><Option value="incoming">Linked Incoming Hook</Option></Select></Field>{form.trigger_type === 'event' ? <Field label="Event / wildcard"><Input list="automation-events" value={form.trigger_event} onChange={e => setForm({ ...form, trigger_event: e.target.value })}/><datalist id="automation-events">{data.triggers.map(x => <Option key={x} value={x}/>)}</datalist></Field> : form.trigger_type === 'schedule' ? <Grid columns="1fr 1fr" gap={8}><Field label="Frequency"><Select value={String((form.trigger_config as any).frequency ?? 'daily')} onChange={e => setForm({ ...form, trigger_config: { ...form.trigger_config, frequency: e.target.value } })}><Option value="every_15_minutes">Every 15 minutes</Option><Option value="hourly">Hourly</Option><Option value="daily">Daily</Option><Option value="weekly">Weekly</Option><Option value="monthly">Monthly</Option></Select></Field><Field label="At"><Input type="time" value={String((form.trigger_config as any).at ?? '09:00')} onChange={e => setForm({ ...form, trigger_config: { ...form.trigger_config, at: e.target.value } })}/></Field>{String((form.trigger_config as any).frequency) === 'weekly' && <Field label="Weekday"><Select value={String((form.trigger_config as any).weekday ?? 1)} onChange={e => setForm({ ...form, trigger_config: { ...form.trigger_config, weekday: Number(e.target.value) } })}><Option value="1">Monday</Option><Option value="2">Tuesday</Option><Option value="3">Wednesday</Option><Option value="4">Thursday</Option><Option value="5">Friday</Option><Option value="6">Saturday</Option><Option value="0">Sunday</Option></Select></Field>}{String((form.trigger_config as any).frequency) === 'monthly' && <Field label="Day of month"><Input type="number" min={1} max={28} value={Number((form.trigger_config as any).day ?? 1)} onChange={e => setForm({ ...form, trigger_config: { ...form.trigger_config, day: Number(e.target.value) } })}/></Field>}</Grid> : <Box className="ui-card-description" pt={28}>Create an Incoming Hook and link it to this workflow after saving.</Box>}</Grid></div><div className="automation-builder-section"><Inline justify="space-between" align="center"><h4>2. Conditions</h4><Button type="button" size="sm" variant="outline" onClick={addCondition}><Plus size={12}/> Condition</Button></Inline>{form.conditions.map((c, i) => <div className="automation-condition" key={i}><Input value={c.field} onChange={e => updateCondition(i, { field: e.target.value })} placeholder="payload.status"/><Select value={c.operator} onChange={e => updateCondition(i, { operator: e.target.value })}>{data.condition_operators.map(op => <Option key={op} value={op}>{op}</Option>)}</Select><Input value={Array.isArray(c.value) ? c.value.join(',') : String(c.value ?? '')} onChange={e => updateCondition(i, { value: ['in', 'not_in'].includes(c.operator) ? e.target.value.split(',').map(x => x.trim()) : e.target.value })}/><Button type="button" variant="ghost" size="sm" iconOnly aria-label="Remove condition" onClick={() => setForm({ ...form, conditions: form.conditions.filter((_, x) => x !== i) })}><Trash2 size={12}/></Button></div>)}{!form.conditions.length && <div className="ui-card-description">No conditions. Every matching trigger will run.</div>}<Box mt={7}><Field label="Match"><Select value={form.condition_mode} onChange={e => setForm({ ...form, condition_mode: e.target.value as 'all' | 'any' })}><Option value="all">All conditions</Option><Option value="any">Any condition</Option></Select></Field></Box></div><div className="automation-builder-section"><Inline justify="space-between" align="center"><h4>3. Actions</h4><Button type="button" size="sm" variant="outline" onClick={addAction}><Plus size={12}/> Action</Button></Inline>{form.actions.map((a, i) => <ActionEditor key={i} index={i} action={a} data={data} onChange={patch => updateAction(i, patch)} onDelete={() => setForm({ ...form, actions: form.actions.filter((_, x) => x !== i) })}/>)}</div><Grid columns="1fr 1fr" gap={9}><Field label="On action failure"><Select value={form.failure_policy} onChange={e => setForm({ ...form, failure_policy: e.target.value as 'stop' | 'continue' })}><Option value="stop">Stop workflow</Option><Option value="continue">Continue remaining actions</Option></Select></Field><Field label="Maximum run seconds"><Input type="number" min={5} max={120} value={form.max_run_seconds} onChange={e => setForm({ ...form, max_run_seconds: Number(e.target.value) })}/></Field></Grid>{editing && <Field label="Test payload (JSON)"><Textarea value={testPayload} onChange={e => setTestPayload(e.target.value)} rows={4}/></Field>}</FormDialog>
 <FormDialog open={hookOpen} onClose={() => setHookOpen(false)} title="Create incoming hook" description="Use Authorization: Bearer <token>. Raw token is shown once." formId="hook-form" onSubmit={createHook} submitLabel="Create Hook" loading={busy}><Field label="Name"><Input value={hookForm.name} onChange={e => setHookForm({ ...hookForm, name: e.target.value })}/></Field><Field label="Event name"><Input value={hookForm.event_name} onChange={e => setHookForm({ ...hookForm, event_name: e.target.value })}/></Field><Field label="Linked workflow (optional)"><Select value={hookForm.workflow_id} onChange={e => setHookForm({ ...hookForm, workflow_id: e.target.value })}><Option value="">Match by event name</Option>{data.workflows.filter(w => w.status === 'active').map(w => <Option key={w.id} value={w.id}>{w.name}</Option>)}</Select></Field><Field label="Requests per minute"><Input type="number" min={1} max={600} value={hookForm.rate_limit_per_minute} onChange={e => setHookForm({ ...hookForm, rate_limit_per_minute: e.target.value })}/></Field></FormDialog>
 <Modal open={!!runOpen} onClose={() => setRunOpen(null)} title={runOpen ? `Run ${runOpen.uuid}` : 'Run'} description={runOpen?.trigger_event ?? ''}>{runOpen && <Stack gap={10}><Inline gap={8}><Badge tone={tone(runOpen.status)}>{runOpen.status}</Badge><span className="ui-card-description">Created {fmt(runOpen.created_at)} · attempts {runOpen.attempts}</span></Inline>{runOpen.error && <Alert tone="danger">{runOpen.error}</Alert>}<Stack gap={7}>{runOpen.steps?.map(step => <div key={step.id} className="automation-row"><span>{step.status === 'succeeded' ? <CheckCircle2 size={15}/> : step.status === 'failed' ? <XCircle size={15}/> : <History size={15}/>}</span><Box flex={1}><strong>{step.position}. {step.name}</strong><small>{step.status} · {step.attempts} attempt{step.attempts === 1 ? '' : 's'}</small>{step.error && <Text as="small" color="var(--danger)">{step.error}</Text>}</Box><Badge tone={tone(step.status)}>{step.status}</Badge></div>)}</Stack>{runOpen.dead_letter && data.can_manage && <Button variant="primary" loading={busy} onClick={() => void retryRun(runOpen)}><RefreshCw size={13}/> Retry from Dead Letter</Button>}</Stack>}</Modal>
 </Page>;
}
