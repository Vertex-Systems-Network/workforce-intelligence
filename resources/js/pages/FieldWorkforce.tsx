import { FormEvent, useEffect, useState } from 'react';
import { ClipboardCheck, FileText, MapPin, Plus, QrCode, ShieldAlert, Smartphone, Users } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { ErrorState, FormDialog, Alert, Badge, Button, Card, CardBody, CardHeader, Field, Input, Page, PageHeader, Select, StatCard, DataGrid, Tabs, Textarea, Grid, Inline, Stack, Option, type DataGridColumn } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
type Person = {
    id: number;
    employee_code: string | null;
    user: {
        first_name: string;
        last_name: string;
    };
};
type Project = {
    id: number;
    name: string;
    code: string | null;
};
type Client = {
    id: number;
    name: string;
};
type Assignment = {
    id: number;
    member_id: number;
    member?: Person;
};
type WorkOrder = {
    id: number;
    uuid: string;
    work_order_number: string;
    title: string;
    status: string;
    priority: string;
    due_at: string | null;
    site_name: string | null;
    site_address: string | null;
    project?: Project | null;
    client?: Client | null;
    assignees: Assignment[];
};
type Checkpoint = {
    id: number;
    uuid: string;
    project_id: number | null;
    name: string;
    type: string;
    token_prefix: string;
    latitude: string | null;
    longitude: string | null;
    radius_meters: number | null;
    status: string;
};
type FormTemplate = {
    id: number;
    uuid: string;
    name: string;
    category: string;
    requires_work_order: boolean;
    requires_location: boolean;
    status: string;
    fields: Array<{
        id: number;
        key: string;
        label: string;
        type: string;
        required: boolean;
    }>;
};
type Incident = {
    id: number;
    uuid: string;
    reporter_member_id: number;
    type: string;
    severity: string;
    title: string;
    description: string;
    status: string;
    occurred_at: string;
    resolution: string | null;
};
type Payload = {
    work_orders: WorkOrder[];
    incidents: Incident[];
    forms: FormTemplate[];
    checkpoints: Checkpoint[];
    people: Person[];
    projects: Project[];
    clients: Client[];
    can_manage: boolean;
};
type Tab = 'orders' | 'checkpoints' | 'forms' | 'incidents' | 'mobile';
/** Handles the person operation for the WorkIntel client. */ const person = (p?: Person) => p ? `${p.user.first_name} ${p.user.last_name}` : 'Unknown';
/** Handles the tone operation for the WorkIntel client. */ const tone = (value: string): 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'accent' => ['completed', 'resolved', 'closed', 'active'].includes(value) ? 'success' : ['urgent', 'critical', 'high', 'blocked'].includes(value) ? 'danger' : ['assigned', 'accepted', 'in_progress', 'investigating', 'medium'].includes(value) ? 'warning' : 'neutral';
/** Handles the field workforce operation for the WorkIntel client. */ export default function FieldWorkforce() {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [data, setData] = useState<Payload | null>(null), [tab, setTab] = useState<Tab>('orders'), [loading, setLoading] = useState(true), [saving, setSaving] = useState(false), [error, setError] = useState('');
    const [orderOpen, setOrderOpen] = useState(false), [checkpointOpen, setCheckpointOpen] = useState(false), [formOpen, setFormOpen] = useState(false), [lastToken, setLastToken] = useState('');
    const [order, setOrder] = useState({ title: '', description: '', project_id: '', client_id: '', priority: 'normal', scheduled_start_at: '', scheduled_end_at: '', due_at: '', site_name: '', site_address: '', latitude: '', longitude: '', geofence_radius_meters: '150', instructions: '', member_ids: [] as number[] });
    const [checkpoint, setCheckpoint] = useState({ name: '', type: 'qr', project_id: '', latitude: '', longitude: '', radius_meters: '150' });
    const [form, setForm] = useState({ name: 'Daily Safety Check', category: 'safety', requires_work_order: true, requires_location: false, field1: 'Safe working area?', field2: 'Notes' });
    /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        const p = await apiRequest<Payload>('/api/v1/field/overview', { workspaceId });
        setData(p);
        if (!order.member_ids.length && p.people[0])
            setOrder(v => ({ ...v, member_ids: [p.people[0].id] }));
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load field workforce.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Handles the save order operation for the WorkIntel client. */ const saveOrder = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest('/api/v1/field/work-orders', { method: 'POST', workspaceId, body: JSON.stringify({ ...order, project_id: order.project_id ? Number(order.project_id) : null, client_id: order.client_id ? Number(order.client_id) : null, scheduled_start_at: order.scheduled_start_at || null, scheduled_end_at: order.scheduled_end_at || null, due_at: order.due_at || null, latitude: order.latitude ? Number(order.latitude) : null, longitude: order.longitude ? Number(order.longitude) : null, geofence_radius_meters: order.geofence_radius_meters ? Number(order.geofence_radius_meters) : null }) });
        setOrderOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create work order.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the save checkpoint operation for the WorkIntel client. */ const saveCheckpoint = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        const r = await apiRequest<{
            scan_token: string;
        }>('/api/v1/field/checkpoints', { method: 'POST', workspaceId, body: JSON.stringify({ ...checkpoint, project_id: checkpoint.project_id ? Number(checkpoint.project_id) : null, latitude: checkpoint.latitude ? Number(checkpoint.latitude) : null, longitude: checkpoint.longitude ? Number(checkpoint.longitude) : null, radius_meters: checkpoint.radius_meters ? Number(checkpoint.radius_meters) : null }) });
        setLastToken(r.scan_token);
        setCheckpointOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create checkpoint.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the save form operation for the WorkIntel client. */ const saveForm = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest('/api/v1/field/forms', { method: 'POST', workspaceId, body: JSON.stringify({ name: form.name, category: form.category, requires_work_order: form.requires_work_order, requires_location: form.requires_location, fields: [{ key: 'safe_area', label: form.field1, type: 'boolean', required: true }, { key: 'notes', label: form.field2, type: 'text', required: false }] }) });
        setFormOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create field form.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the review incident operation for the WorkIntel client. */ const reviewIncident = async (row: Incident, status: 'investigating' | 'resolved') => { setSaving(true); try {
        await apiRequest(`/api/v1/field/incidents/${row.id}`, { method: 'PATCH', workspaceId, body: JSON.stringify({ status, resolution: status === 'resolved' ? 'Resolved by field operations review.' : null }) });
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not update incident.');
    }
    finally {
        setSaving(false);
    } };
    /** Shared DataGrid columns for field work orders. */ const workOrderColumns: DataGridColumn<WorkOrder>[] = [{ id: 'number', header: '#', value: r => r.work_order_number, cell: r => r.work_order_number }, { id: 'work', header: 'Work', searchValue: r => `${r.title} ${r.site_name ?? ''} ${r.site_address ?? ''}`, cell: r => <div><strong>{r.title}</strong><div className="ui-card-description">{r.site_name || r.site_address || 'No site'}</div></div> }, { id: 'context', header: 'Project / Client', searchValue: r => `${r.project?.name ?? ''} ${r.client?.name ?? ''}`, cell: r => <div>{r.project?.name || '—'}<div className="ui-card-description">{r.client?.name || ''}</div></div> }, { id: 'assignees', header: 'Assignees', searchValue: r => r.assignees.map(a => person(a.member)).join(' '), cell: r => r.assignees.map(a => person(a.member)).join(', ') || '—' }, { id: 'due', header: 'Due', sortValue: r => r.due_at ? new Date(r.due_at).getTime() : 0, cell: r => r.due_at ? new Date(r.due_at).toLocaleString() : '—' }, { id: 'status', header: 'Status', value: r => r.status, cell: r => <Badge tone={tone(r.status)}>{r.status}</Badge> }];
    /** Shared DataGrid columns for QR/NFC checkpoints. */ const checkpointColumns: DataGridColumn<Checkpoint>[] = [{ id: 'name', header: 'Name', value: r => r.name, cell: r => <strong>{r.name}</strong> }, { id: 'type', header: 'Type', value: r => r.type, cell: r => r.type.toUpperCase() }, { id: 'token', header: 'Token prefix', value: r => r.token_prefix, cell: r => <code>{r.token_prefix}</code> }, { id: 'geofence', header: 'Geofence', value: r => r.latitude && r.longitude ? `${r.radius_meters || 0}m` : 'No geofence', cell: r => r.latitude && r.longitude ? `${r.radius_meters || 0}m` : 'No geofence' }, { id: 'status', header: 'Status', value: r => r.status, cell: r => <Badge tone={tone(r.status)}>{r.status}</Badge> }];
    /** Shared DataGrid columns for field safety incidents. */ const incidentColumns: DataGridColumn<Incident>[] = [{ id: 'reporter', header: 'Reported by', searchValue: r => person(data?.people.find(p => p.id === r.reporter_member_id)), cell: r => person(data?.people.find(p => p.id === r.reporter_member_id)) }, { id: 'incident', header: 'Incident', searchValue: r => `${r.title} ${r.type}`, cell: r => <div><strong>{r.title}</strong><div className="ui-card-description">{r.type}</div></div> }, { id: 'severity', header: 'Severity', value: r => r.severity, cell: r => <Badge tone={tone(r.severity)}>{r.severity}</Badge> }, { id: 'occurred', header: 'Occurred', sortValue: r => new Date(r.occurred_at).getTime(), cell: r => new Date(r.occurred_at).toLocaleString() }, { id: 'status', header: 'Status', value: r => r.status, cell: r => <Badge tone={tone(r.status)}>{r.status}</Badge> }, { id: 'actions', header: '', sortable: false, hideable: false, cell: r => data?.can_manage && !['resolved', 'closed'].includes(r.status) ? <Inline gap={5}><Button size="sm" variant="ghost" onClick={() => void reviewIncident(r, 'investigating')}>Investigate</Button><Button size="sm" onClick={() => void reviewIncident(r, 'resolved')}>Resolve</Button></Inline> : null }];
    if (loading && !data)
        return <PageLoadingState />;
    if (!data)
        return <Page><ErrorState title="Field workforce unavailable" text={error || 'Field workforce data could not be loaded.'} retry={load}/></Page>;
    return <Page><PageHeader title={data.can_manage ? 'Field Workforce' : 'My Field Work'} description="Work orders, job sites, QR/NFC-ready checkpoints, safety forms, incidents and offline mobile sync" actions={data.can_manage ? <Button size="sm" variant="primary" onClick={() => setOrderOpen(true)}><Plus size={13}/>Work Order</Button> : undefined}/>{error && <Alert tone="danger" mb={12}>{error}</Alert>}{lastToken && <Alert tone="warning" mb={12}>Checkpoint scan token (shown once): <code>{lastToken}</code> <Button size="sm" variant="ghost" onClick={() => void navigator.clipboard.writeText(lastToken)}>Copy</Button></Alert>}
 <Grid columns="repeat(4,minmax(0,1fr))" gap={10} mb={12}><StatCard label="Open work orders" value={data.work_orders.filter(o => !['completed', 'canceled'].includes(o.status)).length} icon={<ClipboardCheck size={16}/>}/><StatCard label="Active checkpoints" value={data.checkpoints.filter(c => c.status === 'active').length} icon={<MapPin size={16}/>}/><StatCard label="Field forms" value={data.forms.length} icon={<FileText size={16}/>}/><StatCard label="Open incidents" value={data.incidents.filter(i => !['resolved', 'closed'].includes(i.status)).length} icon={<ShieldAlert size={16}/>}/></Grid>
 <Tabs value={tab} onChange={setTab} tabs={[{ value: 'orders', label: 'Work Orders' }, { value: 'checkpoints', label: 'Checkpoints' }, { value: 'forms', label: 'Forms' }, { value: 'incidents', label: 'Incidents' }, { value: 'mobile', label: 'Mobile & Offline' }]}/>
 {tab === 'orders' && <Card mt={12}><CardHeader title="Work orders" description="Assigned field jobs with project/client/site context."/><CardBody><DataGrid rows={data.work_orders} columns={workOrderColumns} rowKey={r => r.id} persistKey="field-work.orders" defaultSort={{ id: 'due', direction: 'asc' }} searchPlaceholder="Search work orders, sites, projects or assignees…" empty={<ErrorState title="No work orders" text="Create a work order to assign the first field job."/>}/></CardBody></Card>}
 {tab === 'checkpoints' && <Card mt={12}><CardHeader title="QR / NFC-ready checkpoints" description="Server stores only a SHA-256 token hash. Raw scan token is displayed once." action={data.can_manage ? <Button size="sm" onClick={() => setCheckpointOpen(true)}><QrCode size={13}/>Checkpoint</Button> : undefined}/><CardBody><DataGrid rows={data.checkpoints} columns={checkpointColumns} rowKey={r => r.id} persistKey="field-work.checkpoints" searchPlaceholder="Search checkpoints…"/></CardBody></Card>}
 {tab === 'forms' && <Card mt={12}><CardHeader title="Safety & checklist forms" description="Mobile workers can complete work-order/location-aware forms offline and sync later." action={data.can_manage ? <Button size="sm" onClick={() => setFormOpen(true)}><Plus size={13}/>Form</Button> : undefined}/><CardBody>{data.forms.map(f => <div className="schedule-list-row" key={f.id}><div><strong>{f.name}</strong><small>{f.category} · {f.fields.length} fields · {f.requires_work_order ? 'work order required' : 'standalone'}</small></div><Badge tone={tone(f.status)}>{f.status}</Badge></div>)}</CardBody></Card>}
 {tab === 'incidents' && <Card mt={12}><CardHeader title="Safety incidents" description="Incident records are separate from productivity tracking and preserve field safety evidence."/><CardBody><DataGrid rows={data.incidents} columns={incidentColumns} rowKey={r => r.id} persistKey="field-work.incidents" defaultSort={{ id: 'occurred', direction: 'desc' }} searchPlaceholder="Search incidents, reporter or type…"/></CardBody></Card>}
 {tab === 'mobile' && <Grid columns="1fr 1fr" gap={12} mt={12}><Card><CardHeader title="Native mobile authentication" description="Android/iOS clients use separate hash-only mobile bearer tokens."/><CardBody><Stack gap={9}><div className="schedule-list-row"><div><strong>Mobile login</strong><small>POST /api/v1/mobile/login</small></div><Smartphone size={17}/></div><div className="schedule-list-row"><div><strong>Offline sync</strong><small>Max 100 idempotent events per batch</small></div><Badge tone="success">Enabled</Badge></div><div className="schedule-list-row"><div><strong>Push token storage</strong><small>Encrypted at rest</small></div><Badge>Ready</Badge></div></Stack></CardBody></Card><Card><CardHeader title="Offline event contract" description="Retries are de-duplicated using event_uuid."/><CardBody><Stack gap={8}>{['work_order.status', 'checkpoint.visit', 'incident.report'].map(item => <div key={item} className="schedule-list-row"><code>{item}</code><Badge tone="info">idempotent</Badge></div>)}</Stack></CardBody></Card></Grid>}
 <FormDialog open={orderOpen} onClose={() => setOrderOpen(false)} title="Create field work order" size="lg" formId="field-order-form" onSubmit={saveOrder} submitLabel="Create" loading={saving}><Field label="Title"><Input value={order.title} onChange={e => setOrder({ ...order, title: e.target.value })} required/></Field><Field label="Description"><Textarea value={order.description} onChange={e => setOrder({ ...order, description: e.target.value })}/></Field><Grid columns="1fr 1fr" gap={8}><Field label="Project"><Select value={order.project_id} onChange={e => setOrder({ ...order, project_id: e.target.value })}><Option value="">No project</Option>{data.projects.map(p => <Option key={p.id} value={p.id}>{p.name}</Option>)}</Select></Field><Field label="Client"><Select value={order.client_id} onChange={e => setOrder({ ...order, client_id: e.target.value })}><Option value="">No client</Option>{data.clients.map(c => <Option key={c.id} value={c.id}>{c.name}</Option>)}</Select></Field></Grid><Field label="Assignee"><Select value={String(order.member_ids[0] || '')} onChange={e => setOrder({ ...order, member_ids: [Number(e.target.value)] })}>{data.people.map(p => <Option key={p.id} value={p.id}>{person(p)}</Option>)}</Select></Field><Grid columns="1fr 1fr" gap={8}><Field label="Priority"><Select value={order.priority} onChange={e => setOrder({ ...order, priority: e.target.value })}><Option value="normal">Normal</Option><Option value="high">High</Option><Option value="urgent">Urgent</Option></Select></Field><Field label="Due"><Input type="datetime-local" value={order.due_at} onChange={e => setOrder({ ...order, due_at: e.target.value })}/></Field></Grid><Field label="Site name"><Input value={order.site_name} onChange={e => setOrder({ ...order, site_name: e.target.value })}/></Field><Field label="Site address"><Input value={order.site_address} onChange={e => setOrder({ ...order, site_address: e.target.value })}/></Field><Field label="Instructions"><Textarea value={order.instructions} onChange={e => setOrder({ ...order, instructions: e.target.value })}/></Field></FormDialog>
 <FormDialog open={checkpointOpen} onClose={() => setCheckpointOpen(false)} title="Create checkpoint" description="Use the returned one-time token to encode a QR or NFC payload." formId="checkpoint-form" onSubmit={saveCheckpoint} submitLabel="Create" loading={saving}><Field label="Name"><Input value={checkpoint.name} onChange={e => setCheckpoint({ ...checkpoint, name: e.target.value })} required/></Field><Field label="Type"><Select value={checkpoint.type} onChange={e => setCheckpoint({ ...checkpoint, type: e.target.value })}><Option value="qr">QR</Option><Option value="nfc">NFC</Option><Option value="both">QR + NFC</Option></Select></Field><Field label="Project"><Select value={checkpoint.project_id} onChange={e => setCheckpoint({ ...checkpoint, project_id: e.target.value })}><Option value="">Any project</Option>{data.projects.map(p => <Option key={p.id} value={p.id}>{p.name}</Option>)}</Select></Field><Grid columns="1fr 1fr 1fr" gap={7}><Field label="Latitude"><Input value={checkpoint.latitude} onChange={e => setCheckpoint({ ...checkpoint, latitude: e.target.value })}/></Field><Field label="Longitude"><Input value={checkpoint.longitude} onChange={e => setCheckpoint({ ...checkpoint, longitude: e.target.value })}/></Field><Field label="Radius m"><Input type="number" value={checkpoint.radius_meters} onChange={e => setCheckpoint({ ...checkpoint, radius_meters: e.target.value })}/></Field></Grid></FormDialog>
 <FormDialog open={formOpen} onClose={() => setFormOpen(false)} title="Create safety form" formId="field-form" onSubmit={saveForm} submitLabel="Create" loading={saving}><Field label="Name"><Input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })}/></Field><Field label="Category"><Input value={form.category} onChange={e => setForm({ ...form, category: e.target.value })}/></Field><Field label="Required safety question"><Input value={form.field1} onChange={e => setForm({ ...form, field1: e.target.value })}/></Field><Field label="Optional notes field"><Input value={form.field2} onChange={e => setForm({ ...form, field2: e.target.value })}/></Field></FormDialog>
 </Page>;
}
