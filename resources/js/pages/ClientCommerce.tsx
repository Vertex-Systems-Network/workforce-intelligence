import { useEffect, useState, type FormEvent } from 'react';
import { Banknote, CircleDollarSign, CreditCard, Play, Plus, RefreshCw, Settings2 } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { hasPermission } from '../access';
import { Alert, Badge, Button, Card, CardBody, CardHeader, DataGrid, Field, Input, Page, PageHeader, Select, Switch, Textarea, type DataGridColumn, Checkbox, Inline, FormDialog, SettingRow, ChoiceList, ChoiceRow, Option } from '../design-system';
type Gateway = {
    id: number;
    uuid: string;
    provider: string;
    display_name: string;
    enabled: boolean;
    is_default: boolean;
    test_mode: boolean;
    client_portal_enabled: boolean;
    sort_order: number;
    settings: Record<string, any> | null;
    has_credentials: boolean;
    last_tested_at: string | null;
    health_status: string;
    health_message: string | null;
};
type GatewayCatalog = {
    key: string;
    name: string;
    hosted: boolean;
};
type Client = {
    id: number;
    name: string;
    company_name: string | null;
    currency: string;
};
type Schedule = {
    id: number;
    uuid: string;
    client_id: number;
    name: string;
    status: string;
    frequency: string;
    interval_count: number;
    due_days: number;
    currency: string;
    auto_send: boolean;
    next_run_at: string;
    last_run_at: string | null;
    client?: Client;
};
type Checkout = {
    id: number;
    uuid: string;
    provider: string;
    status: string;
    currency: string;
    amount: number;
    created_at: string;
    client?: Client;
    invoice?: {
        id: number;
        number: string;
        status: string;
        amount_due: number;
        currency: string;
    };
};
type Payload = {
    gateway_catalog: GatewayCatalog[];
    gateways: Gateway[];
    schedules: Schedule[];
    recent_checkouts: Checkout[];
};
/** Render workspace-owned client payment gateway and recurring invoice administration. */
export default function ClientCommerce() {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const workspace = session?.user.workspaces.find(w => w.id === workspaceId);
    const canPayments = hasPermission(workspace, 'client_payments.manage');
    const canRecurring = hasPermission(workspace, 'client_invoices.recurring_manage');
    const [data, setData] = useState<Payload | null>(null), [clients, setClients] = useState<Client[]>([]), [loading, setLoading] = useState(true), [busy, setBusy] = useState(false), [error, setError] = useState(''), [message, setMessage] = useState('');
    const [gatewayOpen, setGatewayOpen] = useState(false), [gatewayKey, setGatewayKey] = useState('bank_transfer'), [gatewayForm, setGatewayForm] = useState({ display_name: 'Bank Transfer', enabled: true, is_default: true, test_mode: true, client_portal_enabled: true, secret_key: '', client_id: '', client_secret: '', checkout_url: '', status_url: '', instructions: '', bank_details: '' });
    const [scheduleOpen, setScheduleOpen] = useState(false), [scheduleForm, setScheduleForm] = useState({ client_id: '', name: 'Monthly Retainer', frequency: 'monthly', interval_count: '1', due_days: '14', currency: 'USD', starts_at: new Date().toISOString().slice(0, 16), next_run_at: new Date().toISOString().slice(0, 16), description: 'Monthly services', quantity: '1', unit_price: '0', tax_percent: '0', discount_total: '0', auto_send: true, allowed_gateways: ['bank_transfer'] as string[] });
    const [settleOpen, setSettleOpen] = useState(false), [settlingCheckout, setSettlingCheckout] = useState<Checkout | null>(null), [settleReference, setSettleReference] = useState('');
    /** Load workspace client-commerce state and client choices. */
    const load = async () => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        const [commerce, clientRows] = await Promise.all([apiRequest<Payload>('/api/v1/client-commerce', { workspaceId }), apiRequest<{
                data: Client[];
            }>('/api/v1/clients', { workspaceId })]);
        setData(commerce);
        setClients(clientRows.data);
        if (!scheduleForm.client_id && clientRows.data[0])
            setScheduleForm(v => ({ ...v, client_id: String(clientRows.data[0].id), currency: clientRows.data[0].currency || 'USD' }));
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not load client payments.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Open a provider-specific gateway editor without exposing stored secrets. */
    const configureGateway = (key: string) => { const existing = data?.gateways.find(g => g.provider === key), catalog = data?.gateway_catalog.find(g => g.key === key); setGatewayKey(key); setGatewayForm({ display_name: existing?.display_name || catalog?.name || key, enabled: existing?.enabled ?? false, is_default: existing?.is_default ?? false, test_mode: existing?.test_mode ?? true, client_portal_enabled: existing?.client_portal_enabled ?? true, secret_key: '', client_id: '', client_secret: '', checkout_url: String(existing?.settings?.checkout_url ?? ''), status_url: String(existing?.settings?.status_url ?? ''), instructions: String(existing?.settings?.instructions ?? ''), bank_details: String(existing?.settings?.bank_details ?? '') }); setGatewayOpen(true); };
    /** Persist gateway settings while sending only newly entered credentials. */
    /** Save gateway settings and report whether a requested remote activation passed its automatic connection test. */
    const saveGateway = async (event: FormEvent) => { event.preventDefault(); setBusy(true); setError(''); setMessage(''); try {
        const credentials: any = {};
        if (gatewayKey === 'stripe' && gatewayForm.secret_key)
            credentials.secret_key = gatewayForm.secret_key;
        if (gatewayKey === 'paypal') {
            if (gatewayForm.client_id)
                credentials.client_id = gatewayForm.client_id;
            if (gatewayForm.client_secret)
                credentials.client_secret = gatewayForm.client_secret;
        }
        const settings: any = { instructions: gatewayForm.instructions || undefined, bank_details: gatewayForm.bank_details || undefined, checkout_url: gatewayForm.checkout_url || undefined, status_url: gatewayForm.status_url || undefined };
        const response = await apiRequest<{
            data: Gateway;
            activation_test?: {
                ok: boolean;
                message: string;
            } | null;
        }>(`/api/v1/client-commerce/gateways/${gatewayKey}`, { method: 'PUT', workspaceId, body: JSON.stringify({ display_name: gatewayForm.display_name, enabled: gatewayForm.enabled, is_default: gatewayForm.is_default, test_mode: gatewayForm.test_mode, client_portal_enabled: gatewayForm.client_portal_enabled, credentials: Object.keys(credentials).length ? credentials : null, settings }) });
        setGatewayOpen(false);
        if (response.activation_test && !response.activation_test.ok) {
            setMessage('Gateway settings saved.');
            setError(`Gateway remains disabled: ${response.activation_test.message}`);
        }
        else if (response.activation_test?.ok) {
            setMessage('Gateway saved, tested and enabled.');
        }
        else {
            setMessage('Client payment gateway saved.');
        }
        await load();
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not save gateway.');
    }
    finally {
        setBusy(false);
    } };
    /** Test the selected remote gateway before it can be enabled. */
    const testGateway = async (gateway: Gateway) => { setBusy(true); setError(''); try {
        await apiRequest(`/api/v1/client-commerce/gateways/${gateway.id}/test`, { method: 'POST', workspaceId });
        setMessage(`${gateway.display_name} connection tested.`);
        await load();
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Gateway test failed.');
    }
    finally {
        setBusy(false);
    } };
    /** Create a recurring invoice schedule using the existing client invoice engine. */
    const createSchedule = async (event: FormEvent) => { event.preventDefault(); setBusy(true); setError(''); try {
        await apiRequest('/api/v1/client-commerce/invoice-schedules', { method: 'POST', workspaceId, body: JSON.stringify({ client_id: Number(scheduleForm.client_id), name: scheduleForm.name, status: 'active', frequency: scheduleForm.frequency, interval_count: Number(scheduleForm.interval_count), due_days: Number(scheduleForm.due_days), currency: scheduleForm.currency, starts_at: scheduleForm.starts_at, next_run_at: scheduleForm.next_run_at, tax_percent: Number(scheduleForm.tax_percent), discount_total: Number(scheduleForm.discount_total), auto_send: scheduleForm.auto_send, include_unbilled_time: false, allowed_gateways: scheduleForm.allowed_gateways, lines: [{ description: scheduleForm.description, quantity: Number(scheduleForm.quantity), unit_price: Number(scheduleForm.unit_price) }] }) });
        setScheduleOpen(false);
        setMessage('Recurring invoice schedule created.');
        await load();
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not create recurring invoice.');
    }
    finally {
        setBusy(false);
    } };
    /** Pause or resume one recurring invoice schedule. */
    const toggleSchedule = async (schedule: Schedule) => { setBusy(true); try {
        await apiRequest(`/api/v1/client-commerce/invoice-schedules/${schedule.id}/status`, { method: 'PATCH', workspaceId, body: JSON.stringify({ status: schedule.status === 'active' ? 'paused' : 'active' }) });
        await load();
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not update schedule.');
    }
    finally {
        setBusy(false);
    } };
    /** Generate a scheduled invoice immediately for verification or ad-hoc billing. */
    const runSchedule = async (schedule: Schedule) => { setBusy(true); try {
        await apiRequest(`/api/v1/client-commerce/invoice-schedules/${schedule.id}/run`, { method: 'POST', workspaceId });
        setMessage('Scheduled invoice generated.');
        await load();
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not generate invoice.');
    }
    finally {
        setBusy(false);
    } };
    /** Open the audited manual-settlement form for a pending checkout. */
    const openSettlement = (checkout: Checkout) => { setSettlingCheckout(checkout); setSettleReference(`BANK-${checkout.uuid.slice(0, 8).toUpperCase()}`); setSettleOpen(true); };
    /** Settle a pending manual or bank transfer checkout after bank verification. */
    const settleCheckout = async (event: FormEvent) => { event.preventDefault(); if (!settlingCheckout || !settleReference.trim())
        return; setBusy(true); setError(''); try {
        await apiRequest(`/api/v1/client-commerce/checkouts/${settlingCheckout.id}/settle`, { method: 'POST', workspaceId, body: JSON.stringify({ reference: settleReference.trim() }) });
        setSettleOpen(false);
        setSettlingCheckout(null);
        setSettleReference('');
        setMessage('Client payment settled.');
        await load();
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not settle client payment.');
    }
    finally {
        setBusy(false);
    } };
    const scheduleColumns: DataGridColumn<Schedule>[] = [{ id: 'client', header: 'Client', cell: r => <strong>{r.client?.company_name || r.client?.name || '—'}</strong>, searchValue: r => r.client?.company_name || r.client?.name }, { id: 'name', header: 'Schedule', cell: r => <div><strong>{r.name}</strong><div className="ui-card-description">Every {r.interval_count > 1 ? `${r.interval_count} ` : ''}{r.frequency}</div></div>, searchValue: r => r.name }, { id: 'next', header: 'Next run', cell: r => new Date(r.next_run_at).toLocaleString(), sortValue: r => r.next_run_at }, { id: 'status', header: 'Status', cell: r => <Badge tone={r.status === 'active' ? 'success' : 'neutral'}>{r.status}</Badge>, filterValue: r => r.status, filter: { type: 'select', options: [{ value: 'active', label: 'Active' }, { value: 'paused', label: 'Paused' }] } }, { id: 'actions', header: '', sortable: false, hideable: false, cell: r => <Inline gap={5}><Button size="sm" variant="ghost" onClick={() => void runSchedule(r)} disabled={busy}><Play size={12}/> Run now</Button><Button size="sm" variant="outline" onClick={() => void toggleSchedule(r)} disabled={busy}>{r.status === 'active' ? 'Pause' : 'Resume'}</Button></Inline> }];
    const checkoutColumns: DataGridColumn<Checkout>[] = [{ id: 'invoice', header: 'Invoice', cell: r => <strong>{r.invoice?.number || '—'}</strong>, searchValue: r => r.invoice?.number }, { id: 'client', header: 'Client', cell: r => r.client?.company_name || r.client?.name || '—', searchValue: r => r.client?.company_name || r.client?.name }, { id: 'provider', header: 'Gateway', cell: r => r.provider, filterValue: r => r.provider, filter: { type: 'select', options: [{ value: 'manual', label: 'Manual' }, { value: 'bank_transfer', label: 'Bank transfer' }, { value: 'stripe', label: 'Stripe' }, { value: 'paypal', label: 'PayPal' }, { value: 'custom_http', label: 'Custom HTTP' }] } }, { id: 'amount', header: 'Amount', cell: r => new Intl.NumberFormat(undefined, { style: 'currency', currency: r.currency }).format(r.amount), sortValue: r => r.amount }, { id: 'status', header: 'Status', cell: r => <Badge tone={r.status === 'completed' ? 'success' : r.status === 'failed' ? 'danger' : 'warning'}>{r.status}</Badge>, filterValue: r => r.status, filter: { type: 'select', options: [{ value: 'pending', label: 'Pending' }, { value: 'completed', label: 'Completed' }, { value: 'failed', label: 'Failed' }] } }, { id: 'actions', header: '', sortable: false, cell: r => r.status === 'pending' && ['manual', 'bank_transfer'].includes(r.provider) ? <Button size="sm" variant="outline" onClick={() => openSettlement(r)}>Settle</Button> : null }];
    return <Page><PageHeader title="Client Payments" description="Workspace-owned payment gateways, client Pay Now controls and recurring invoice automation." actions={<Inline gap={7}>{canRecurring && <Button size="sm" onClick={() => setScheduleOpen(true)}><Plus size={13}/> Recurring invoice</Button>}<Button size="sm" variant="outline" loading={loading} onClick={() => void load()}><RefreshCw size={13}/> Refresh</Button></Inline>}/>{error && <Alert tone="danger">{error}</Alert>}{message && <Alert tone="success">{message}</Alert>}
    <div className="client-commerce-grid">{(data?.gateway_catalog ?? []).map(provider => { const gateway = data?.gateways.find(g => g.provider === provider.key); return <Card key={provider.key}><CardHeader title={gateway?.display_name || provider.name} description={provider.hosted ? 'Hosted client checkout' : 'Offline/manual settlement'} action={gateway?.is_default ? <Badge tone="accent">Default</Badge> : undefined}/><CardBody><Inline gap={6} wrap="wrap" mb={10}><Badge tone={gateway?.enabled ? 'success' : 'neutral'}>{gateway?.enabled ? 'enabled' : 'disabled'}</Badge><Badge tone={gateway?.health_status === 'healthy' ? 'success' : gateway?.health_status === 'failed' ? 'danger' : 'neutral'}>{gateway?.health_status || 'not configured'}</Badge>{gateway && <Badge>{gateway.test_mode ? 'test' : 'live'}</Badge>}</Inline>{canPayments && <Inline gap={6}><Button size="sm" variant="outline" onClick={() => configureGateway(provider.key)}><Settings2 size={12}/> Configure</Button>{gateway && provider.hosted && <Button size="sm" variant="ghost" loading={busy} onClick={() => void testGateway(gateway)}>Test</Button>}</Inline>}</CardBody></Card>; })}</div>
    <Card mt={14}><CardHeader title="Recurring invoices" description="Generate invoices on schedule, optionally send immediately, and control which gateways clients may use."/><CardBody><DataGrid rows={data?.schedules ?? []} columns={scheduleColumns} rowKey={r => r.id} persistKey="client-commerce-schedules" searchable searchPlaceholder="Search recurring invoices…" loading={loading}/></CardBody></Card>
    <Card mt={14}><CardHeader title="Recent client payment checkouts" description="Hosted and manual Pay Now attempts are separated from WorkIntel platform subscription payments."/><CardBody><DataGrid rows={data?.recent_checkouts ?? []} columns={checkoutColumns} rowKey={r => r.id} persistKey="client-commerce-checkouts" searchable searchPlaceholder="Search invoice or client…" loading={loading}/></CardBody></Card>
    <FormDialog open={gatewayOpen} onClose={() => setGatewayOpen(false)} title="Configure client payment gateway" description="Credentials are encrypted at rest and never returned by the API." formId="client-gateway-form" onSubmit={saveGateway} submitLabel="Save gateway" loading={busy}><Field label="Display name"><Input value={gatewayForm.display_name} onChange={e => setGatewayForm({ ...gatewayForm, display_name: e.target.value })}/></Field><SettingRow title="Accept payments" description="Enable this gateway for new client checkout attempts." control={<Switch checked={gatewayForm.enabled} onChange={v => setGatewayForm({ ...gatewayForm, enabled: v })} label="Accept payments"/>}/><SettingRow title="Client portal" description="Expose this gateway in client Pay Now checkout." control={<Switch checked={gatewayForm.client_portal_enabled} onChange={v => setGatewayForm({ ...gatewayForm, client_portal_enabled: v })} label="Show in Pay Now"/>}/><SettingRow title="Default gateway" description="Prefer this gateway when no invoice-specific preference exists." control={<Switch checked={gatewayForm.is_default} onChange={v => setGatewayForm({ ...gatewayForm, is_default: v })} label="Default gateway"/>}/><Field label="Mode"><Select value={gatewayForm.test_mode ? 'test' : 'live'} onChange={e => setGatewayForm({ ...gatewayForm, test_mode: e.target.value === 'test' })}><Option value="test">Test / Sandbox</Option><Option value="live">Live</Option></Select></Field>{gatewayKey === 'stripe' && <Field label="Stripe secret key" hint="Leave blank to keep the stored key."><Input type="password" value={gatewayForm.secret_key} onChange={e => setGatewayForm({ ...gatewayForm, secret_key: e.target.value })}/></Field>}{gatewayKey === 'paypal' && <><Field label="PayPal client ID"><Input value={gatewayForm.client_id} onChange={e => setGatewayForm({ ...gatewayForm, client_id: e.target.value })}/></Field><Field label="PayPal client secret"><Input type="password" value={gatewayForm.client_secret} onChange={e => setGatewayForm({ ...gatewayForm, client_secret: e.target.value })}/></Field></>}{gatewayKey === 'custom_http' && <><Field label="Checkout URL"><Input value={gatewayForm.checkout_url} onChange={e => setGatewayForm({ ...gatewayForm, checkout_url: e.target.value })}/></Field><Field label="Status URL"><Input value={gatewayForm.status_url} onChange={e => setGatewayForm({ ...gatewayForm, status_url: e.target.value })}/></Field></>}{['manual', 'bank_transfer'].includes(gatewayKey) && <><Field label="Payment instructions"><Textarea value={gatewayForm.instructions} onChange={e => setGatewayForm({ ...gatewayForm, instructions: e.target.value })}/></Field>{gatewayKey === 'bank_transfer' && <Field label="Bank details"><Textarea value={gatewayForm.bank_details} onChange={e => setGatewayForm({ ...gatewayForm, bank_details: e.target.value })}/></Field>}</>}</FormDialog>
    <FormDialog open={settleOpen} onClose={() => setSettleOpen(false)} title="Settle client payment" description="Record a verified bank or manual payment reference. The invoice is marked paid only through the server-side settlement workflow." formId="client-settlement-form" onSubmit={settleCheckout} submitLabel="Confirm settlement" loading={busy}><Field label="Invoice"><Input value={settlingCheckout?.invoice?.number || '—'} readOnly/></Field><Field label="Amount"><Input value={settlingCheckout ? new Intl.NumberFormat(undefined, { style: 'currency', currency: settlingCheckout.currency }).format(settlingCheckout.amount) : '—'} readOnly/></Field><Field label="Payment reference" hint="Use the bank transaction, receipt or reconciliation reference used by your finance team."><Input value={settleReference} onChange={e => setSettleReference(e.target.value)} required autoFocus/></Field></FormDialog>
    <FormDialog open={scheduleOpen} onClose={() => setScheduleOpen(false)} title="Create recurring invoice" description="Generate recurring invoices with explicit payment and sending controls." formId="schedule-form" onSubmit={createSchedule} submitLabel="Create schedule" loading={busy} size="lg"><Field label="Client"><Select value={scheduleForm.client_id} onChange={e => { const c = clients.find(x => x.id === Number(e.target.value)); setScheduleForm({ ...scheduleForm, client_id: e.target.value, currency: c?.currency || scheduleForm.currency }); }}>{clients.map(c => <Option key={c.id} value={c.id}>{c.company_name || c.name}</Option>)}</Select></Field><Field label="Schedule name"><Input value={scheduleForm.name} onChange={e => setScheduleForm({ ...scheduleForm, name: e.target.value })}/></Field><div className="ui-form-grid-2"><Field label="Frequency"><Select value={scheduleForm.frequency} onChange={e => setScheduleForm({ ...scheduleForm, frequency: e.target.value })}><Option value="weekly">Weekly</Option><Option value="monthly">Monthly</Option><Option value="quarterly">Quarterly</Option><Option value="yearly">Yearly</Option></Select></Field><Field label="Every"><Input type="number" min="1" max="24" value={scheduleForm.interval_count} onChange={e => setScheduleForm({ ...scheduleForm, interval_count: e.target.value })}/></Field><Field label="Next run"><Input type="datetime-local" value={scheduleForm.next_run_at} onChange={e => setScheduleForm({ ...scheduleForm, next_run_at: e.target.value, starts_at: e.target.value })}/></Field><Field label="Payment due after days"><Input type="number" min="0" value={scheduleForm.due_days} onChange={e => setScheduleForm({ ...scheduleForm, due_days: e.target.value })}/></Field></div><Field label="Line description"><Input value={scheduleForm.description} onChange={e => setScheduleForm({ ...scheduleForm, description: e.target.value })}/></Field><div className="ui-form-grid-2"><Field label="Quantity"><Input type="number" min="0" step="0.01" value={scheduleForm.quantity} onChange={e => setScheduleForm({ ...scheduleForm, quantity: e.target.value })}/></Field><Field label="Unit price"><Input type="number" min="0" step="0.01" value={scheduleForm.unit_price} onChange={e => setScheduleForm({ ...scheduleForm, unit_price: e.target.value })}/></Field><Field label="Tax %"><Input type="number" min="0" max="100" value={scheduleForm.tax_percent} onChange={e => setScheduleForm({ ...scheduleForm, tax_percent: e.target.value })}/></Field><Field label="Discount"><Input type="number" min="0" value={scheduleForm.discount_total} onChange={e => setScheduleForm({ ...scheduleForm, discount_total: e.target.value })}/></Field></div><Field label="Allowed Pay Now gateways"><ChoiceList columns={2}>{(data?.gateways ?? []).filter(g => g.enabled && g.client_portal_enabled).map(g => { const active = scheduleForm.allowed_gateways.includes(g.provider); return <ChoiceRow key={g.provider} selected={active}><Checkbox checked={active} onChange={e => setScheduleForm(v => ({ ...v, allowed_gateways: e.target.checked ? [...v.allowed_gateways, g.provider] : v.allowed_gateways.filter(x => x !== g.provider) }))}/><span>{g.display_name}</span></ChoiceRow>; })}</ChoiceList></Field><SettingRow title="Automatically send invoices" description="Send each generated invoice immediately after the schedule runs." control={<Switch checked={scheduleForm.auto_send} onChange={v => setScheduleForm({ ...scheduleForm, auto_send: v })} label="Automatically send generated invoices"/>}/></FormDialog>
  </Page>;
}
