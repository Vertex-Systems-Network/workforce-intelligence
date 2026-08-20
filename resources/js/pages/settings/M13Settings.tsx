import { FormEvent, useEffect, useMemo, useState } from 'react';
import { AlertTriangle, CheckCircle2, Copy, KeyRound, Link2, Plus, RefreshCw, ShieldAlert, Trash2, Webhook } from 'lucide-react';
import { apiRequest } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { useConfirmAction, Alert, Badge, Button, Card, CardBody, CardHeader, Field, Input, Modal, Select, Switch, DataGrid, EmptyState, LoadingState, Box, Grid, Inline, Stack, Text, Form, Label, Option, type DataGridColumn } from '../../design-system';
type Preference = {
    id: number;
    category: string;
    in_app: boolean;
    email: boolean;
    digest: 'immediate' | 'daily' | 'weekly';
};
type Integration = {
    id: number;
    provider: string;
    name: string;
    status: string;
    config_preview: Record<string, string>;
    last_tested_at: string | null;
    last_error: string | null;
};
type ApiKey = {
    id: number;
    name: string;
    prefix: string;
    scopes: string[];
    last_used_at: string | null;
    last_used_ip: string | null;
    expires_at: string | null;
    revoked_at: string | null;
};
type Hook = {
    id: number;
    name: string;
    url: string;
    secret_preview: string;
    events: string[];
    status: string;
    deliveries_count: number | null;
    last_success_at: string | null;
    last_failure_at: string | null;
};
type ConnectorField = {
    key: string;
    label: string;
    type: 'text' | 'secret' | 'url' | 'select';
    required: boolean;
    options?: string[];
};
type ConnectorProvider = {
    id: string;
    name: string;
    category: string;
    description: string;
    auth: string;
    config_fields: ConnectorField[];
    actions: Array<{
        key: string;
        name: string;
        fields: string[];
    }>;
};
type SecurityOverview = {
    integrations: Integration[];
    api_keys: ApiKey[];
    webhooks: Hook[];
    event_catalog: string[];
    api_scope_catalog: string[];
    providers: ConnectorProvider[];
};
type AuditRow = {
    id: number;
    created_at: string;
    action: string;
    category: string;
    method: string | null;
    path: string | null;
    status_code: number | null;
    ip_address: string | null;
    user_id: number | null;
    risk_level: string;
    metadata: Record<string, unknown> | null;
};
type SecurityRow = {
    id: number;
    created_at: string;
    event_type: string;
    severity: string;
    ip_address: string | null;
    user_id: number | null;
    resolved_at: string | null;
    metadata: Record<string, unknown> | null;
};
/** Formats fmt data for display. */ const fmt = (value: string | null) => value ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(value)) : '—';
const categories: Record<string, string> = { attendance: 'Attendance', payroll: 'Payroll', agents: 'Agent Health', reports: 'Reports', security: 'Security', clients: 'Clients', workspace: 'Workspace' };
/** Handles the heading operation for the WorkIntel client. */ function Heading({ title, text }: {
    title: string;
    text: string;
}) { return <Box mb={22}><Box as="h2" m={0} size={16} weight={650}>{title}</Box><Text className="ui-card-description" as="p" mt={4}>{text}</Text></Box>; }
/** Handles the notification settings m13 operation for the WorkIntel client. */ export function NotificationSettingsM13() {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [prefs, setPrefs] = useState<Preference[]>([]), [savedPrefs, setSavedPrefs] = useState<Preference[]>([]), [loading, setLoading] = useState(true), [saving, setSaving] = useState(false), [message, setMessage] = useState('');
    /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
        return; setLoading(true); try {
        const p = await apiRequest<{
            data: Preference[];
        }>('/api/v1/notification-preferences', { workspaceId, silent: true });
        setPrefs(p.data);
        setSavedPrefs(p.data);
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Handles the save operation for the WorkIntel client. */ const save = async () => { setSaving(true); setMessage(''); try {
        const p = await apiRequest<{
            data: Preference[];
        }>('/api/v1/notification-preferences', { method: 'PUT', workspaceId, body: JSON.stringify({ preferences: prefs.map(({ category, in_app, email, digest }) => ({ category, in_app, email, digest })) }) });
        setPrefs(p.data);
        setSavedPrefs(p.data);
        setMessage('Notification preferences saved.');
    }
    catch (e) {
        setMessage(e instanceof Error ? e.message : 'Could not save preferences.');
    }
    finally {
        setSaving(false);
    } };
    const dirty = useMemo(() => JSON.stringify(prefs) !== JSON.stringify(savedPrefs), [prefs, savedPrefs]);
    /** Restores notification preferences to the last server-confirmed values. */ const reset = () => { setPrefs(savedPrefs); setMessage(''); };
    return <><Heading title="Notifications" text="Control in-app alerts and optional immediate/digest email delivery per category."/>{message && <Alert tone={message.includes('saved') ? 'success' : 'danger'}>{message}</Alert>}<Card><CardHeader title="Workspace Alerts"/><CardBody>{loading ? <LoadingState compact title="Loading preferences…" text="Refreshing notification delivery rules."/> : prefs.map((pref, index) => <Box key={pref.category} display="grid" gridColumns="1fr auto auto 150px" align="center" gap={12} p="12px 0" borderBottom="1px solid var(--border-muted)"><div><Box weight={600}>{categories[pref.category] ?? pref.category}</Box><div className="ui-card-description">Operational alerts for {categories[pref.category]?.toLowerCase() ?? pref.category} events</div></div><Label display="flex" align="center" gap={6} size={11}><Switch checked={pref.in_app} onChange={value => setPrefs(rows => rows.map((row, i) => i === index ? { ...row, in_app: value } : row))}/> In app</Label><Label display="flex" align="center" gap={6} size={11}><Switch checked={pref.email} onChange={value => setPrefs(rows => rows.map((row, i) => i === index ? { ...row, email: value } : row))}/> Email</Label><Select value={pref.digest} onChange={e => setPrefs(rows => rows.map((row, i) => i === index ? { ...row, digest: e.target.value as Preference['digest'] } : row))}><Option value="immediate">Immediate</Option><Option value="daily">Daily digest</Option><Option value="weekly">Weekly digest</Option></Select></Box>)}<Inline justify="flex-end" gap={8} pt={14}><Button variant="outline" disabled={!dirty || saving} onClick={reset}>Reset</Button><Button variant="primary" loading={saving} disabled={!dirty} onClick={() => void save()}>Save Preferences</Button></Inline></CardBody></Card></>;
}
/** Handles the use security overview operation for the WorkIntel client. */ function useSecurityOverview() { const { session } = useAuth(); const workspaceId = session?.user.activeWorkspaceId ?? 0; const [data, setData] = useState<SecurityOverview | null>(null), [error, setError] = useState(''), [loading, setLoading] = useState(true); /** Loads load data required by the current view. */ /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
    return; setLoading(true); setError(''); try {
    setData(await apiRequest<SecurityOverview>('/api/v1/security-integrations', { workspaceId, silent: true }));
}
catch (e) {
    setError(e instanceof Error ? e.message : 'Could not load security integrations.');
}
finally {
    setLoading(false);
} }; useEffect(() => { void load(); }, [workspaceId]); return { workspaceId, data, error, loading, load }; }
/** Handles the integrations settings m13 operation for the WorkIntel client. */ export function IntegrationsSettingsM13() {
    const confirmAction = useConfirmAction();
    const { workspaceId, data, error, loading, load } = useSecurityOverview();
    const [open, setOpen] = useState(false), [busy, setBusy] = useState(false), [message, setMessage] = useState('');
    const [provider, setProvider] = useState('slack'), [name, setName] = useState('Slack Alerts'), [config, setConfig] = useState<Record<string, string>>({});
    const selected = data?.providers.find(item => item.id === provider) ?? data?.providers[0];
    useEffect(() => { if (data?.providers.length && !data.providers.some(item => item.id === provider)) {
        setProvider(data.providers[0].id);
        setConfig({});
    } }, [data?.providers.length]);
    /** Handles the save operation for the WorkIntel client. */ const save = async (e: FormEvent) => { e.preventDefault(); setBusy(true); setMessage(''); try {
        await apiRequest('/api/v1/integrations', { method: 'POST', workspaceId, body: JSON.stringify({ provider, name, config }) });
        setOpen(false);
        setConfig({});
        await load();
    }
    catch (err) {
        setMessage(err instanceof Error ? err.message : 'Could not connect integration.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the test operation for the WorkIntel client. */ const test = async (id: number) => { setBusy(true); setMessage(''); try {
        await apiRequest(`/api/v1/integrations/${id}/test`, { method: 'POST', workspaceId });
        setMessage('Connection test passed.');
        await load();
    }
    catch (e) {
        setMessage(e instanceof Error ? e.message : 'Connection test failed.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the remove operation for the WorkIntel client. */ const remove = async (id: number) => { if (!await confirmAction({ title: 'Remove integration?', description: 'Automations using it will stop until reconfigured.', confirmLabel: 'Remove', danger: true }))
        return; await apiRequest(`/api/v1/integrations/${id}`, { method: 'DELETE', workspaceId }); await load(); };
    /** Handles the open connect operation for the WorkIntel client. */ const openConnect = () => { const first = data?.providers[0]; setProvider(first?.id ?? 'slack'); setName(first ? `${first.name} Connection` : 'Integration'); setConfig({}); setOpen(true); };
    return <><Heading title="Integrations" text="Encrypted connector credentials shared by Phase 24 automations. Tokens are masked after save."/>{error && <Alert tone="danger">{error}</Alert>}{message && <Alert tone={message.includes('passed') ? 'success' : 'danger'}>{message}</Alert>}<Card><CardHeader title="Connections" description={`${data?.providers.length ?? 0} connector providers available`} action={<Button size="sm" variant="primary" onClick={openConnect}><Plus size={13}/> Connect</Button>}/><CardBody>{loading ? <LoadingState compact title="Loading settings…"/> : data?.integrations.length ? <Stack gap={8}>{data.integrations.map(row => <Box key={row.id} display="flex" align="center" gap={10} p={11} border="1px solid var(--border-muted)" radius={8}><Link2 size={15}/><Box flex={1} minWidth={0}><Box weight={600}>{row.name}</Box><div className="ui-card-description">{row.provider} · {Object.entries(row.config_preview).map(([k, v]) => `${k}: ${v}`).join(' · ')}</div>{row.last_error && <Box size={10} color="var(--danger)" mt={3}>{row.last_error}</Box>}</Box><Badge tone={row.status === 'active' ? 'success' : 'neutral'}>{row.status}</Badge><Button size="sm" variant="outline" loading={busy} onClick={() => void test(row.id)}>Test</Button><Button size="sm" variant="ghost" onClick={() => void remove(row.id)}><Trash2 size={13}/></Button></Box>)}</Stack> : <EmptyState title="No integrations configured" text="Connect Slack, Teams, Jira, GitHub, Google Workspace, Microsoft 365, accounting or project-management providers."/>}</CardBody></Card><Modal open={open} onClose={() => !busy && setOpen(false)} title="Connect integration" description={selected?.description}><Form onSubmit={save} gap={10}><Field label="Provider"><Select value={provider} onChange={e => { const id = e.target.value; setProvider(id); setName(`${data?.providers.find(p => p.id === id)?.name ?? 'Integration'} Connection`); setConfig({}); }}>{(data?.providers ?? []).map(p => <Option key={p.id} value={p.id}>{p.name} · {p.category}</Option>)}</Select></Field><Field label="Connection name"><Input value={name} onChange={e => setName(e.target.value)} required/></Field>{selected?.config_fields.map(field => <Field key={field.key} label={field.label}>{field.type === 'select' ? <Select value={config[field.key] ?? field.options?.[0] ?? ''} onChange={e => setConfig({ ...config, [field.key]: e.target.value })}>{field.options?.map(option => <Option key={option} value={option}>{option}</Option>)}</Select> : <Input type={field.type === 'secret' ? 'password' : 'text'} value={config[field.key] ?? ''} onChange={e => setConfig({ ...config, [field.key]: e.target.value })} placeholder={field.type === 'url' ? 'https://…' : undefined} required={field.required}/>}</Field>)}<div className="ui-card-description">Authentication: {selected?.auth?.replaceAll('_', ' ')}. OAuth access-token connectors expect your organization to refresh/rotate tokens outside WorkIntel until a provider OAuth app is configured.</div><Button type="submit" variant="primary" loading={busy}>Save Connection</Button></Form></Modal></>;
}
/** Handles the api settings m13 operation for the WorkIntel client. */ export function ApiSettingsM13() {
    const confirmAction = useConfirmAction();
    const { workspaceId, data, error, loading, load } = useSecurityOverview();
    const [keyOpen, setKeyOpen] = useState(false), [hookOpen, setHookOpen] = useState(false), [busy, setBusy] = useState(false), [secret, setSecret] = useState(''), [message, setMessage] = useState('');
    const [keyForm, setKeyForm] = useState({ name: 'External API', scopes: ['people.read', 'projects.read'] as string[] });
    const [hookForm, setHookForm] = useState({ name: 'Operations Webhook', url: '', events: ['time.started'] as string[] });
    /** Handles the create key operation for the WorkIntel client. */ const createKey = async (e: FormEvent) => { e.preventDefault(); setBusy(true); try {
        const p = await apiRequest<{
            token: string;
        }>('/api/v1/api-keys', { method: 'POST', workspaceId, body: JSON.stringify(keyForm) });
        setSecret(p.token);
        setKeyOpen(false);
        await load();
    }
    catch (e) {
        setMessage(e instanceof Error ? e.message : 'Could not create key.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the rotate operation for the WorkIntel client. */ const rotate = async (id: number) => { if (!await confirmAction({ title: 'Rotate API key?', description: 'Existing clients will immediately stop working until updated.', confirmLabel: 'Rotate key', danger: true }))
        return; setBusy(true); try {
        const p = await apiRequest<{
            token: string;
        }>(`/api/v1/api-keys/${id}/rotate`, { method: 'POST', workspaceId });
        setSecret(p.token);
        await load();
    }
    finally {
        setBusy(false);
    } };
    /** Handles the revoke operation for the WorkIntel client. */ const revoke = async (id: number) => { if (!await confirmAction({ title: 'Revoke API key?', description: 'Applications using this key will immediately lose access.', confirmLabel: 'Revoke', danger: true }))
        return; await apiRequest(`/api/v1/api-keys/${id}`, { method: 'DELETE', workspaceId }); await load(); };
    /** Handles the create hook operation for the WorkIntel client. */ const createHook = async (e: FormEvent) => { e.preventDefault(); setBusy(true); try {
        const p = await apiRequest<{
            signing_secret: string;
        }>('/api/v1/webhooks', { method: 'POST', workspaceId, body: JSON.stringify(hookForm) });
        setSecret(p.signing_secret);
        setHookOpen(false);
        await load();
    }
    catch (e) {
        setMessage(e instanceof Error ? e.message : 'Could not create webhook.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the test hook operation for the WorkIntel client. */ const testHook = async (id: number) => { setBusy(true); try {
        await apiRequest(`/api/v1/webhooks/${id}/test`, { method: 'POST', workspaceId });
        setMessage('Webhook delivered successfully.');
        await load();
    }
    catch (e) {
        setMessage(e instanceof Error ? e.message : 'Webhook test failed.');
    }
    finally {
        setBusy(false);
    } };
    return <><Heading title="API & Webhooks" text="Scoped API keys, HMAC-signed outbound webhooks and delivery health."/>{error && <Alert tone="danger">{error}</Alert>}{message && <Alert tone={message.includes('success') ? 'success' : 'danger'}>{message}</Alert>}{secret && <Alert tone="warning"><strong>Copy this secret now.</strong> It will not be shown again.<Inline gap={8} mt={8}><Box as="code" wordBreak="break-all">{secret}</Box><Button size="sm" variant="outline" onClick={() => void navigator.clipboard.writeText(secret)}><Copy size={12}/> Copy</Button></Inline></Alert>}<Card><CardHeader title="API Keys" action={<Button size="sm" variant="primary" onClick={() => setKeyOpen(true)}><Plus size={13}/> Create Key</Button>}/><CardBody>{loading ? <LoadingState compact title="Loading settings…"/> : data?.api_keys.length ? <Stack gap={8}>{data.api_keys.map(row => <Box key={row.id} display="flex" align="center" gap={10} p={10} border="1px solid var(--border-muted)" radius={8}><KeyRound size={15}/><Box flex={1}><Box weight={600}>{row.name}</Box><div className="ui-card-description stat-num">{row.prefix}•••• · {row.scopes.join(', ')}</div><div className="ui-card-description">Last used {fmt(row.last_used_at)} {row.last_used_ip ? `· ${row.last_used_ip}` : ''}</div></Box><Badge tone={row.revoked_at ? 'neutral' : 'success'}>{row.revoked_at ? 'Revoked' : 'Active'}</Badge>{!row.revoked_at && <><Button size="sm" variant="outline" loading={busy} onClick={() => void rotate(row.id)}><RefreshCw size={12}/> Rotate</Button><Button size="sm" variant="ghost" onClick={() => void revoke(row.id)}><Trash2 size={12}/></Button></>}</Box>)}</Stack> : <EmptyState title="No API keys" text="Create a scoped key when an external application needs workspace API access."/>}</CardBody></Card><Card mt={14}><CardHeader title="Outbound Webhooks" action={<Button size="sm" variant="primary" onClick={() => setHookOpen(true)}><Webhook size={13}/> Add Endpoint</Button>}/><CardBody>{data?.webhooks.length ? <Stack gap={8}>{data.webhooks.map(row => <Box key={row.id} display="flex" align="center" gap={10} p={10} border="1px solid var(--border-muted)" radius={8}><Webhook size={15}/><Box flex={1} minWidth={0}><Box weight={600}>{row.name}</Box><Box className="ui-card-description" overflow="hidden" textOverflow="ellipsis">{row.url}</Box><div className="ui-card-description">{row.events.join(', ')} · secret {row.secret_preview}</div></Box><Badge tone={row.status === 'active' ? 'success' : 'neutral'}>{row.status}</Badge><Button size="sm" variant="outline" loading={busy} onClick={() => void testHook(row.id)}>Test</Button></Box>)}</Stack> : <EmptyState title="No webhook endpoints" text="Add an HTTPS endpoint to receive signed workspace events."/>}</CardBody></Card><Modal open={keyOpen} onClose={() => setKeyOpen(false)} title="Create API key"><Form onSubmit={createKey} gap={10}><Field label="Name"><Input value={keyForm.name} onChange={e => setKeyForm({ ...keyForm, name: e.target.value })}/></Field><Field label="Scopes"><Select multiple value={keyForm.scopes} onChange={e => setKeyForm({ ...keyForm, scopes: Array.from(e.target.selectedOptions).map(o => o.value) })} minHeight={150}>{(data?.api_scope_catalog ?? []).map(scope => <Option key={scope} value={scope}>{scope}</Option>)}</Select></Field><Button type="submit" variant="primary" loading={busy}>Create Key</Button></Form></Modal><Modal open={hookOpen} onClose={() => setHookOpen(false)} title="Create webhook"><Form onSubmit={createHook} gap={10}><Field label="Name"><Input value={hookForm.name} onChange={e => setHookForm({ ...hookForm, name: e.target.value })}/></Field><Field label="HTTPS endpoint"><Input value={hookForm.url} onChange={e => setHookForm({ ...hookForm, url: e.target.value })} placeholder="https://api.example.com/workintel"/></Field><Field label="Events"><Select multiple value={hookForm.events} onChange={e => setHookForm({ ...hookForm, events: Array.from(e.target.selectedOptions).map(o => o.value) })} minHeight={170}>{(data?.event_catalog ?? []).map(event => <Option key={event} value={event}>{event}</Option>)}</Select></Field><Button type="submit" variant="primary" loading={busy}>Create Endpoint</Button></Form></Modal></>;
}
/** Handles the use audit data operation for the WorkIntel client. */ function useAuditData() { const { session } = useAuth(); const workspaceId = session?.user.activeWorkspaceId ?? 0; const [audit, setAudit] = useState<AuditRow[]>([]), [security, setSecurity] = useState<SecurityRow[]>([]), [error, setError] = useState(''), [loading, setLoading] = useState(true); /** Loads load data required by the current view. */ /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
    return; setLoading(true); setError(''); try {
    const [a, s] = await Promise.all([apiRequest<{
            data: AuditRow[];
        }>('/api/v1/audit-logs', { workspaceId, silent: true }), apiRequest<{
            data: SecurityRow[];
        }>('/api/v1/security-events', { workspaceId, silent: true })]);
    setAudit(a.data);
    setSecurity(s.data);
}
catch (e) {
    setError(e instanceof Error ? e.message : 'Could not load security data.');
}
finally {
    setLoading(false);
} }; useEffect(() => { void load(); }, [workspaceId]); return { workspaceId, audit, security, error, loading, load }; }
/** Handles the security settings m13 operation for the WorkIntel client. */ export function SecuritySettingsM13() { const { workspaceId, security, error, loading, load } = useAuditData(); /** Handles the resolve operation for the WorkIntel client. */ /** Handles the resolve operation for the WorkIntel client. */ const resolve = async (id: number) => { await apiRequest(`/api/v1/security-events/${id}/resolve`, { method: 'POST', workspaceId }); await load(); }; const unresolved = security.filter(row => !row.resolved_at); const columns: DataGridColumn<SecurityRow>[] = [{ id: 'time', header: 'Time', sortValue: r => new Date(r.created_at).getTime(), cell: r => fmt(r.created_at) }, { id: 'event', header: 'Event', value: r => r.event_type, cell: r => r.event_type }, { id: 'severity', header: 'Severity', value: r => r.severity, cell: r => <Badge tone={r.severity === 'critical' ? 'danger' : r.severity === 'warning' ? 'warning' : 'info'}>{r.severity}</Badge> }, { id: 'ip', header: 'IP', value: r => r.ip_address ?? '', cell: r => r.ip_address ?? '—' }, { id: 'status', header: 'Status', value: r => r.resolved_at ? 'resolved' : 'open', cell: r => r.resolved_at ? <Badge tone="success">Resolved</Badge> : <Badge tone="warning">Open</Badge> }, { id: 'actions', header: '', sortable: false, hideable: false, cell: r => !r.resolved_at ? <Button size="sm" variant="outline" onClick={() => void resolve(r.id)}><CheckCircle2 size={12}/> Resolve</Button> : null }]; return <><Heading title="Security" text="Authentication events and security findings recorded by the platform."/>{error && <Alert tone="danger">{error}</Alert>}<Grid columns="repeat(3,minmax(0,1fr))" gap={10} mb={14}><Card><CardBody><div className="ui-card-description">Unresolved</div><Box className="stat-num" size={24} weight={700}>{unresolved.length}</Box></CardBody></Card><Card><CardBody><div className="ui-card-description">Warnings</div><Box className="stat-num" size={24} weight={700}>{security.filter(x => x.severity === 'warning').length}</Box></CardBody></Card><Card><CardBody><div className="ui-card-description">Critical</div><Box className="stat-num" size={24} weight={700}>{security.filter(x => x.severity === 'critical').length}</Box></CardBody></Card></Grid><Card><CardHeader title="Security Events"/><CardBody>{loading ? <LoadingState compact title="Loading settings…"/> : <DataGrid rows={security.slice(0, 100)} columns={columns} rowKey={r => r.id} persistKey="settings.security-events" defaultSort={{ id: 'time', direction: 'desc' }} searchPlaceholder="Search security events…" ariaLabel="Security events"/>}</CardBody></Card></>; }
/** Handles the audit settings m13 operation for the WorkIntel client. */ export function AuditSettingsM13() { const { audit, error, loading } = useAuditData(); const columns: DataGridColumn<AuditRow>[] = [{ id: 'time', header: 'Time', sortValue: r => new Date(r.created_at).getTime(), cell: r => fmt(r.created_at) }, { id: 'action', header: 'Action', searchValue: r => `${r.action} ${r.path ?? ''}`, cell: r => <div><strong>{r.action}</strong><div className="ui-card-description">{r.path}</div></div> }, { id: 'category', header: 'Category', value: r => r.category, cell: r => r.category }, { id: 'method', header: 'Method', value: r => r.method ?? '', cell: r => r.method ?? '—' }, { id: 'status', header: 'Status', sortValue: r => r.status_code ?? 0, cell: r => r.status_code ?? '—' }, { id: 'ip', header: 'IP', value: r => r.ip_address ?? '', cell: r => r.ip_address ?? '—' }, { id: 'risk', header: 'Risk', value: r => r.risk_level, cell: r => <Badge tone={r.risk_level === 'elevated' ? 'warning' : 'neutral'}>{r.risk_level}</Badge> }]; return <><Heading title="Audit Logs" text="Sanitized immutable history of mutating workspace API actions."/>{error && <Alert tone="danger">{error}</Alert>}<Card><CardHeader title="Recent Events" description="Passwords, tokens, secrets and credentials are redacted before metadata is written."/><CardBody>{loading ? <LoadingState compact title="Loading settings…"/> : <DataGrid rows={audit.slice(0, 300)} columns={columns} rowKey={r => r.id} persistKey="settings.audit-log" defaultSort={{ id: 'time', direction: 'desc' }} searchPlaceholder="Search audit actions, categories or paths…" ariaLabel="Audit log"/>}</CardBody></Card></>; }
