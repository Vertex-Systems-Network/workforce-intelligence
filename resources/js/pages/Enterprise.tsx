import { FormEvent, useEffect, useState } from 'react';
import { Building2, Copy, KeyRound, LockKeyhole, Network, Plus, ShieldCheck, Smartphone, UserRoundCog } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { ErrorState, EmptyState, Alert, Badge, Button, Card, CardBody, CardHeader, Field, Input, Page, PageHeader, Select, StatCard, Switch, Tabs, Textarea, Grid, Stack, Text, Option, DataGrid, FormDialog, SettingRow, type DataGridColumn } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
import { type AccessPolicy, type BusinessUnit, type Governance, type IpRule, type LegalEntity, type MobileSession, type OrgCostCenter, type OrgMember, type OrgProject, type Payload, type Provider, type ScimToken, type SecurityPolicy, type Session, type Tab, tone } from './enterprise/support';
/** Handles the enterprise operation for the WorkIntel client. */ export default function Enterprise() {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [data, setData] = useState<Payload | null>(null), [tab, setTab] = useState<Tab>('identity'), [loading, setLoading] = useState(true), [saving, setSaving] = useState(false), [error, setError] = useState(''), [oneTimeToken, setOneTimeToken] = useState('');
    const [providerOpen, setProviderOpen] = useState(false), [ipOpen, setIpOpen] = useState(false), [scimOpen, setScimOpen] = useState(false), [entityOpen, setEntityOpen] = useState(false), [unitOpen, setUnitOpen] = useState(false), [governanceOpen, setGovernanceOpen] = useState(false), [accessOpen, setAccessOpen] = useState(false), [assignmentOpen, setAssignmentOpen] = useState(false);
    const [provider, setProvider] = useState({ name: 'Company OIDC', type: 'oidc', status: 'inactive', domains: '', client_id: '', client_secret: '', authorization_endpoint: '', token_endpoint: '', userinfo_endpoint: '', issuer: '', jit_provisioning: true, default_role_slug: 'employee' });
    const [ip, setIp] = useState({ name: 'Office network', cidr: '', action: 'allow', priority: '100', active: true });
    const [scim, setScim] = useState({ name: 'Directory provisioning', expires_at: '' });
    const [entity, setEntity] = useState({ code: 'HQ', name: 'Head Office', country_code: '', currency: 'USD', timezone: 'UTC', registration_number: '', tax_identifier: '' });
    const [unit, setUnit] = useState({ legal_entity_id: '', code: 'OPS', name: 'Operations', status: 'active' });
    const [gov, setGov] = useState({ dataset: 'audit_logs', retention_days: '365', residency_region: '', storage_class: 'standard', deletion_mode: 'soft_then_purge', legal_hold: false });
    const [access, setAccess] = useState({ name: 'Restrict payroll to payroll/admin roles', resource: 'payroll', effect: 'allow', priority: '100', role_slugs: 'owner,admin,payroll-manager', legal_entity_ids: '', business_unit_ids: '', ip_cidrs: '' });
    const [assignment, setAssignment] = useState<{
        kind: 'member' | 'project' | 'cost-center';
        id: number;
        label: string;
        legal_entity_id: string;
        business_unit_id: string;
    }>({ kind: 'member', id: 0, label: '', legal_entity_id: '', business_unit_id: '' });
    /** Loads load data required by the current view. */ const load = async () => {
        if (!workspaceId)
            return;
        setLoading(true);
        setError('');
        try {
            setData(await apiRequest<Payload>('/api/v1/enterprise/overview', { workspaceId }));
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not load enterprise governance.');
        }
        finally {
            setLoading(false);
        }
    };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Handles the save security operation for the WorkIntel client. */ const saveSecurity = async () => {
        if (!data)
            return;
        setSaving(true);
        try {
            await apiRequest('/api/v1/enterprise/security-policy', { method: 'PUT', workspaceId, body: JSON.stringify(data.security_policy) });
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not save security policy.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Updates update security state for the current workflow. */ const updateSecurity = <K extends keyof SecurityPolicy>(key: K, value: SecurityPolicy[K]) => data && setData({ ...data, security_policy: { ...data.security_policy, [key]: value } });
    /** Handles the save provider operation for the WorkIntel client. */ const saveProvider = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            const config = provider.type === 'oidc' ? { client_id: provider.client_id, client_secret: provider.client_secret || undefined, authorization_endpoint: provider.authorization_endpoint, token_endpoint: provider.token_endpoint, userinfo_endpoint: provider.userinfo_endpoint, issuer: provider.issuer || undefined } : { idp_entity_id: provider.issuer, sso_url: provider.authorization_endpoint, x509_certificate: provider.client_secret };
            await apiRequest('/api/v1/enterprise/identity-providers', { method: 'POST', workspaceId, body: JSON.stringify({ name: provider.name, type: provider.type, status: provider.status, domains: provider.domains.split(',').map(x => x.trim()).filter(Boolean), config, jit_provisioning: provider.jit_provisioning, default_role_slug: provider.default_role_slug }) });
            setProviderOpen(false);
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not save identity provider.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the test provider operation for the WorkIntel client. */ const testProvider = async (id: number) => {
        setSaving(true);
        try {
            const r = await apiRequest<{
                data: {
                    ok: boolean;
                    message?: string;
                    status?: number;
                };
            }>(`/api/v1/enterprise/identity-providers/${id}/test`, { method: 'POST', workspaceId });
            setError(r.data.ok ? '' : r.data.message || `Provider test returned ${r.data.status || 'an error'}.`);
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Provider test failed.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the save ip operation for the WorkIntel client. */ const saveIp = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            await apiRequest('/api/v1/enterprise/ip-rules', { method: 'POST', workspaceId, body: JSON.stringify({ ...ip, priority: Number(ip.priority) }) });
            setIpOpen(false);
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not create IP rule.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the save scim operation for the WorkIntel client. */ const saveScim = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            const r = await apiRequest<{
                token: string;
            }>('/api/v1/enterprise/scim-tokens', { method: 'POST', workspaceId, body: JSON.stringify({ name: scim.name, expires_at: scim.expires_at || null }) });
            setOneTimeToken(r.token);
            setScimOpen(false);
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not create SCIM token.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the revoke session operation for the WorkIntel client. */ const revokeSession = async (id: number) => {
        setSaving(true);
        try {
            await apiRequest(`/api/v1/enterprise/sessions/${id}/revoke`, { method: 'POST', workspaceId, body: JSON.stringify({ reason: 'Revoked from Enterprise Governance.' }) });
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not revoke session.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the revoke mobile operation for the WorkIntel client. */ const revokeMobile = async (id: number) => {
        setSaving(true);
        try {
            await apiRequest(`/api/v1/enterprise/mobile-sessions/${id}/revoke`, { method: 'POST', workspaceId });
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not revoke mobile session.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the save entity operation for the WorkIntel client. */ const saveEntity = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            await apiRequest('/api/v1/enterprise/legal-entities', { method: 'POST', workspaceId, body: JSON.stringify(entity) });
            setEntityOpen(false);
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not create legal entity.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the save unit operation for the WorkIntel client. */ const saveUnit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            await apiRequest('/api/v1/enterprise/business-units', { method: 'POST', workspaceId, body: JSON.stringify({ ...unit, legal_entity_id: unit.legal_entity_id ? Number(unit.legal_entity_id) : null }) });
            setUnitOpen(false);
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not create business unit.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the open assignment operation for the WorkIntel client. */ const openAssignment = (kind: 'member' | 'project' | 'cost-center', id: number, label: string, entityId: number | null, unitId: number | null) => { setAssignment({ kind, id, label, legal_entity_id: entityId ? String(entityId) : '', business_unit_id: unitId ? String(unitId) : '' }); setAssignmentOpen(true); };
    /** Handles the save assignment operation for the WorkIntel client. */ const saveAssignment = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            const base = assignment.kind === 'member' ? 'members' : assignment.kind === 'project' ? 'projects' : 'cost-centers';
            await apiRequest(`/api/v1/enterprise/${base}/${assignment.id}/organization`, { method: 'PUT', workspaceId, body: JSON.stringify({ legal_entity_id: assignment.legal_entity_id ? Number(assignment.legal_entity_id) : null, business_unit_id: assignment.business_unit_id ? Number(assignment.business_unit_id) : null }) });
            setAssignmentOpen(false);
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not update organization assignment.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the save governance operation for the WorkIntel client. */ const saveGovernance = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            await apiRequest('/api/v1/enterprise/governance-policies', { method: 'PUT', workspaceId, body: JSON.stringify({ ...gov, retention_days: gov.retention_days ? Number(gov.retention_days) : null }) });
            setGovernanceOpen(false);
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not save data policy.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the list operation for the WorkIntel client. */ const list = (v: string) => v.split(',').map(x => x.trim()).filter(Boolean);
    /** Handles the number list operation for the WorkIntel client. */ const numberList = (v: string) => list(v).map(Number).filter(Number.isFinite);
    /** Handles the save access operation for the WorkIntel client. */ const saveAccess = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            await apiRequest('/api/v1/enterprise/access-policies', { method: 'POST', workspaceId, body: JSON.stringify({ name: access.name, resource: access.resource, action: '*', effect: access.effect, priority: Number(access.priority), active: true, conditions: { role_slugs: list(access.role_slugs), legal_entity_ids: numberList(access.legal_entity_ids), business_unit_ids: numberList(access.business_unit_ids), ip_cidrs: list(access.ip_cidrs) } }) });
            setAccessOpen(false);
            await load();
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not create attribute policy.');
        }
        finally {
            setSaving(false);
        }
    };
    const providerColumns: DataGridColumn<Provider>[] = [
        { id: 'name', header: 'Name', searchValue: r => `${r.name} ${r.domains.join(' ')}`, sortValue: r => r.name, cell: r => <Text weight={650}>{r.name}</Text> },
        { id: 'type', header: 'Type', filterValue: r => r.type, cell: r => r.type.toUpperCase() },
        { id: 'domains', header: 'Domains', searchValue: r => r.domains.join(' '), cell: r => r.domains?.join(', ') || 'Any' },
        { id: 'jit', header: 'JIT', filterValue: r => r.jit_provisioning ? 'on' : 'off', cell: r => r.jit_provisioning ? 'On' : 'Off' },
        { id: 'runtime', header: 'Runtime', filterValue: r => r.runtime_ready ? 'ready' : 'adapter', cell: r => <Badge tone={r.runtime_ready ? 'success' : 'warning'}>{r.runtime_ready ? 'ready' : 'adapter required'}</Badge> },
        { id: 'status', header: 'Status', filterValue: r => r.status, cell: r => <Badge tone={tone(r.status)}>{r.status}</Badge> },
        { id: 'actions', header: '', hideable: false, cell: r => <Button size="sm" variant="ghost" loading={saving} onClick={() => void testProvider(r.id)}>Test</Button> },
    ];
    const scimColumns: DataGridColumn<ScimToken>[] = [
        { id: 'name', header: 'Name', searchValue: r => `${r.name} ${r.token_prefix}`, sortValue: r => r.name, cell: r => r.name },
        { id: 'prefix', header: 'Prefix', searchValue: r => r.token_prefix, cell: r => <code>{r.token_prefix}</code> },
        { id: 'scopes', header: 'Scopes', searchValue: r => r.scopes.join(' '), cell: r => r.scopes?.join(', ') },
        { id: 'last_used', header: 'Last used', sortValue: r => r.last_used_at ?? '', filterValue: r => r.last_used_at ?? '', filter: { type: 'dateRange', label: 'Last used' }, cell: r => r.last_used_at ? new Date(r.last_used_at).toLocaleString() : 'Never' },
        { id: 'expires', header: 'Expires', sortValue: r => r.expires_at ?? '', cell: r => r.expires_at ? new Date(r.expires_at).toLocaleDateString() : 'Never' },
        { id: 'status', header: 'Status', filterValue: r => r.revoked_at ? 'revoked' : 'active', cell: r => <Badge tone={r.revoked_at ? 'danger' : 'success'}>{r.revoked_at ? 'revoked' : 'active'}</Badge> },
    ];
    const governanceColumns: DataGridColumn<Governance>[] = [
        { id: 'dataset', header: 'Dataset', searchValue: r => r.dataset, sortValue: r => r.dataset, cell: r => <Text weight={650}>{r.dataset}</Text> },
        { id: 'retention', header: 'Retention', sortValue: r => r.retention_days ?? 999999, cell: r => r.retention_days ? `${r.retention_days} days` : 'Default' },
        { id: 'residency', header: 'Residency', filterValue: r => r.residency_region ?? 'deployment-default', cell: r => r.residency_region || 'Deployment default' },
        { id: 'storage', header: 'Storage', filterValue: r => r.storage_class, cell: r => r.storage_class },
        { id: 'hold', header: 'Legal hold', filterValue: r => r.legal_hold ? 'hold' : 'normal', cell: r => <Badge tone={r.legal_hold ? 'warning' : 'neutral'}>{r.legal_hold ? 'hold' : 'normal'}</Badge> },
    ];
    if (loading && !data)
        return <PageLoadingState />;
    if (!data)
        return <Page><ErrorState title="Enterprise governance unavailable" text={error || 'Enterprise governance could not be loaded.'} retry={load}/></Page>;
    return <Page><PageHeader title="Enterprise Identity & Governance" description="OIDC SSO, MFA, SCIM, sessions/devices, IP and attribute policies, legal entities and data governance"/>{error && <Alert tone="danger" mb={12}>{error}</Alert>}{oneTimeToken && <Alert tone="warning" mb={12}>One-time token: <code>{oneTimeToken}</code> <Button size="sm" variant="ghost" onClick={() => void navigator.clipboard.writeText(oneTimeToken)}><Copy size={12}/>Copy</Button></Alert>}
 <Grid columns="repeat(4,minmax(0,1fr))" gap={10} mb={12}><StatCard label="Identity providers" value={data.providers.length} icon={<KeyRound size={16}/>}/><StatCard label="Active sessions" value={data.sessions.filter(s => !s.revoked_at).length} icon={<UserRoundCog size={16}/>}/><StatCard label="Mobile devices" value={data.mobile_sessions.filter(s => !s.revoked_at).length} icon={<Smartphone size={16}/>}/><StatCard label="Legal entities" value={data.legal_entities.filter(e => e.status === 'active').length} icon={<Building2 size={16}/>}/></Grid>
 <Tabs value={tab} onChange={setTab} tabs={[{ value: 'identity', label: 'Identity & SSO' }, { value: 'security', label: 'Security & Sessions' }, { value: 'directory', label: 'SCIM' }, { value: 'organization', label: 'Entities & Units' }, { value: 'governance', label: 'Access & Data' }]}/>
 {tab === 'identity' && <DataGrid rows={data.providers} columns={providerColumns} rowKey={row => row.id} persistKey="enterprise.identity-providers" toolbar={<Button size="sm" onClick={() => setProviderOpen(true)}><Plus size={13}/>Provider</Button>} empty={<EmptyState title="No enterprise identity providers yet."/>}/>}
 {tab === 'security' && <Stack gap={12} mt={12}><Card><CardHeader title="Workspace security policy" description="Lockout-safe enforcement: SSO cannot be required without a usable provider and MFA cannot be required before targeted users enroll." action={<Button size="sm" loading={saving} onClick={() => void saveSecurity()}><ShieldCheck size={13}/>Save Policy</Button>}/><CardBody><Grid columns="repeat(2,minmax(0,1fr))" gap={10}><SettingRow title="Require MFA" description="Require MFA for targeted roles." control={<Switch checked={data.security_policy.require_mfa} onChange={v => updateSecurity('require_mfa', v)}/>}/><SettingRow title="Require enterprise SSO" description="Owner remains break-glass exempt." control={<Switch checked={data.security_policy.require_sso} onChange={v => updateSecurity('require_sso', v)}/>}/><SettingRow title="Allow password login" description="Can coexist with optional SSO." control={<Switch checked={data.security_policy.allow_password_login} onChange={v => updateSecurity('allow_password_login', v)}/>}/><Field label="Session TTL (minutes)"><Input type="number" value={data.security_policy.session_ttl_minutes} onChange={e => updateSecurity('session_ttl_minutes', Number(e.target.value))}/></Field><Field label="Max active sessions"><Input type="number" value={data.security_policy.max_active_sessions} onChange={e => updateSecurity('max_active_sessions', Number(e.target.value))}/></Field><Field label="Minimum password length"><Input type="number" value={data.security_policy.password_min_length} onChange={e => updateSecurity('password_min_length', Number(e.target.value))}/></Field></Grid></CardBody></Card><Card><CardHeader title="IP access rules" action={<Button size="sm" onClick={() => setIpOpen(true)}><Plus size={13}/>Rule</Button>}/><CardBody>{data.ip_rules.map(r => <div className="schedule-list-row" key={r.id}><div><strong>{r.name}</strong><small>{r.cidr} · priority {r.priority}</small></div><Badge tone={tone(r.action)}>{r.action}</Badge></div>)}</CardBody></Card><Grid columns="1fr 1fr" gap={12}><Card><CardHeader title="Browser sessions"/><CardBody>{data.sessions.slice(0, 50).map(s => <div className="schedule-list-row" key={s.id}><div><strong>{s.user ? `${s.user.first_name} ${s.user.last_name}` : `User ${s.user_id}`}</strong><small>{s.ip_address || 'No IP'} · {new Date(s.last_seen_at).toLocaleString()}</small></div>{s.revoked_at ? <Badge tone="danger">revoked</Badge> : <Button size="sm" variant="ghost" onClick={() => void revokeSession(s.id)}>Revoke</Button>}</div>)}</CardBody></Card><Card><CardHeader title="Mobile sessions"/><CardBody>{data.mobile_sessions.slice(0, 50).map(s => <div className="schedule-list-row" key={s.id}><div><strong>{s.device_name || s.platform} · {s.token_prefix}</strong><small>{s.last_used_ip || 'No IP'} · {s.last_used_at ? new Date(s.last_used_at).toLocaleString() : 'never used'}</small></div>{s.revoked_at ? <Badge tone="danger">revoked</Badge> : <Button size="sm" variant="ghost" onClick={() => void revokeMobile(s.id)}>Revoke</Button>}</div>)}</CardBody></Card></Grid></Stack>}
 {tab === 'directory' && <DataGrid rows={data.scim_tokens} columns={scimColumns} rowKey={row => row.id} persistKey="enterprise.scim-tokens" toolbar={<Button size="sm" onClick={() => setScimOpen(true)}><Plus size={13}/>SCIM Token</Button>} empty={<EmptyState title="No SCIM tokens yet." text="Create a hash-only provisioning token when directory synchronization is required."/>}/>}
 {tab === 'organization' && <Grid columns="1fr 1fr" gap={12} mt={12}><Card><CardHeader title="Legal entities" description="Entity-level country, currency and timezone context." action={<Button size="sm" onClick={() => setEntityOpen(true)}><Building2 size={13}/>Entity</Button>}/><CardBody>{data.legal_entities.map(e => <div className="schedule-list-row" key={e.id}><div><strong>{e.code} · {e.name}</strong><small>{e.country_code || 'No country'} · {e.currency} · {e.timezone}</small></div><Badge tone={tone(e.status)}>{e.status}</Badge></div>)}</CardBody></Card><Card><CardHeader title="Business units" action={<Button size="sm" onClick={() => setUnitOpen(true)}><Network size={13}/>Unit</Button>}/><CardBody>{data.business_units.map(u => <div className="schedule-list-row" key={u.id}><div><strong>{u.code} · {u.name}</strong><small>{u.legal_entity_id ? `Entity #${u.legal_entity_id}` : 'Workspace level'}</small></div><Badge tone={tone(u.status)}>{u.status}</Badge></div>)}</CardBody></Card><Card gridColumn="1 / -1"><CardHeader title="Organization assignments" description="Place employees, projects and cost centers into the correct legal entity / business unit for ABAC and reporting."/><CardBody><Grid columns="repeat(3,minmax(0,1fr))" gap={10}><div><Text as="strong" size={11}>Employees</Text>{data.organization_members.slice(0, 100).map(m => <div className="schedule-list-row" key={m.id}><div><strong>{m.user.first_name} {m.user.last_name}</strong><small>{m.employee_code || m.user.email} · entity {m.legal_entity_id || '—'} / unit {m.business_unit_id || '—'}</small></div><Button size="sm" variant="ghost" onClick={() => openAssignment('member', m.id, `${m.user.first_name} ${m.user.last_name}`, m.legal_entity_id, m.business_unit_id)}>Assign</Button></div>)}</div><div><Text as="strong" size={11}>Projects</Text>{data.organization_projects.slice(0, 100).map(p => <div className="schedule-list-row" key={p.id}><div><strong>{p.name}</strong><small>{p.code || 'No code'} · entity {p.legal_entity_id || '—'} / unit {p.business_unit_id || '—'}</small></div><Button size="sm" variant="ghost" onClick={() => openAssignment('project', p.id, p.name, p.legal_entity_id, p.business_unit_id)}>Assign</Button></div>)}</div><div><Text as="strong" size={11}>Cost centers</Text>{data.organization_cost_centers.slice(0, 100).map(c => <div className="schedule-list-row" key={c.id}><div><strong>{c.code} · {c.name}</strong><small>entity {c.legal_entity_id || '—'} / unit {c.business_unit_id || '—'}</small></div><Button size="sm" variant="ghost" onClick={() => openAssignment('cost-center', c.id, c.name, c.legal_entity_id, c.business_unit_id)}>Assign</Button></div>)}</div></Grid></CardBody></Card></Grid>}
 {tab === 'governance' && <Stack gap={12} mt={12}><Card><CardHeader title="Attribute-based access policies" description="Resource access can depend on role, legal entity, business unit, employment stage and IP range. Deny rules always win." action={<Button size="sm" onClick={() => setAccessOpen(true)}><LockKeyhole size={13}/>Policy</Button>}/><CardBody>{data.access_policies.map(p => <div className="schedule-list-row" key={p.id}><div><strong>{p.name}</strong><small>{p.resource}:{p.action} · priority {p.priority}</small></div><Badge tone={tone(p.effect)}>{p.effect}</Badge></div>)}</CardBody></Card><Card><CardHeader title="Data retention & residency policy" description="Legal hold prevents purge. Residency is deployment/storage metadata; physical regional placement must be provided by infrastructure." action={<Button size="sm" onClick={() => setGovernanceOpen(true)}><Plus size={13}/>Dataset Policy</Button>}/><CardBody><DataGrid rows={data.governance} columns={governanceColumns} rowKey={row => row.id} persistKey="enterprise.governance" defaultSort={{ id: 'dataset', direction: 'asc' }} empty={<EmptyState title="No data governance policies yet."/>}/></CardBody></Card></Stack>}
 <FormDialog open={providerOpen} onClose={() => setProviderOpen(false)} title="Add identity provider" size="lg" formId="provider-form" onSubmit={saveProvider} submitLabel="Save" loading={saving}><Field label="Name"><Input value={provider.name} onChange={e => setProvider({ ...provider, name: e.target.value })}/></Field><Field label="Protocol"><Select value={provider.type} onChange={e => setProvider({ ...provider, type: e.target.value })}><Option value="oidc">OIDC + PKCE</Option><Option value="saml">SAML configuration / metadata</Option></Select></Field><Field label="Allowed email domains"><Input value={provider.domains} onChange={e => setProvider({ ...provider, domains: e.target.value })} placeholder="company.com, subsidiary.com"/></Field>{provider.type === 'oidc' ? <><Field label="Client ID"><Input value={provider.client_id} onChange={e => setProvider({ ...provider, client_id: e.target.value })}/></Field><Field label="Client secret (optional for public PKCE clients)"><Input type="password" value={provider.client_secret} onChange={e => setProvider({ ...provider, client_secret: e.target.value })}/></Field><Field label="Authorization endpoint"><Input value={provider.authorization_endpoint} onChange={e => setProvider({ ...provider, authorization_endpoint: e.target.value })}/></Field><Field label="Token endpoint"><Input value={provider.token_endpoint} onChange={e => setProvider({ ...provider, token_endpoint: e.target.value })}/></Field><Field label="UserInfo endpoint"><Input value={provider.userinfo_endpoint} onChange={e => setProvider({ ...provider, userinfo_endpoint: e.target.value })}/></Field><Field label="Issuer (optional discovery/test)"><Input value={provider.issuer} onChange={e => setProvider({ ...provider, issuer: e.target.value })}/></Field></> : <><Field label="IdP entity ID"><Input value={provider.issuer} onChange={e => setProvider({ ...provider, issuer: e.target.value })}/></Field><Field label="SSO URL"><Input value={provider.authorization_endpoint} onChange={e => setProvider({ ...provider, authorization_endpoint: e.target.value })}/></Field><Field label="X.509 certificate"><Textarea rows={5} value={provider.client_secret} onChange={e => setProvider({ ...provider, client_secret: e.target.value })}/></Field></>}<SettingRow title="JIT provisioning" description="Create workspace membership for trusted SSO users." control={<Switch checked={provider.jit_provisioning} onChange={v => setProvider({ ...provider, jit_provisioning: v })}/>}/></FormDialog>
 <FormDialog open={ipOpen} onClose={() => setIpOpen(false)} title="Add IP rule" formId="ip-form" onSubmit={saveIp} submitLabel="Save" loading={saving}><Field label="Name"><Input value={ip.name} onChange={e => setIp({ ...ip, name: e.target.value })}/></Field><Field label="IP / CIDR"><Input value={ip.cidr} onChange={e => setIp({ ...ip, cidr: e.target.value })} placeholder="203.0.113.0/24"/></Field><Field label="Action"><Select value={ip.action} onChange={e => setIp({ ...ip, action: e.target.value })}><Option value="allow">Allow</Option><Option value="deny">Deny</Option></Select></Field></FormDialog>
 <FormDialog open={scimOpen} onClose={() => setScimOpen(false)} title="Create SCIM token" formId="scim-form" onSubmit={saveScim} submitLabel="Create" loading={saving}><Field label="Name"><Input value={scim.name} onChange={e => setScim({ ...scim, name: e.target.value })}/></Field><Field label="Expires"><Input type="datetime-local" value={scim.expires_at} onChange={e => setScim({ ...scim, expires_at: e.target.value })}/></Field></FormDialog>
 <FormDialog open={entityOpen} onClose={() => setEntityOpen(false)} title="Create legal entity" formId="entity-form" onSubmit={saveEntity} submitLabel="Create" loading={saving}><Grid columns="1fr 2fr" gap={8}><Field label="Code"><Input value={entity.code} onChange={e => setEntity({ ...entity, code: e.target.value })}/></Field><Field label="Name"><Input value={entity.name} onChange={e => setEntity({ ...entity, name: e.target.value })}/></Field></Grid><Grid columns="1fr 1fr 1fr" gap={8}><Field label="Country"><Input value={entity.country_code} onChange={e => setEntity({ ...entity, country_code: e.target.value.toUpperCase() })} maxLength={2}/></Field><Field label="Currency"><Input value={entity.currency} onChange={e => setEntity({ ...entity, currency: e.target.value.toUpperCase() })} maxLength={3}/></Field><Field label="Timezone"><Input value={entity.timezone} onChange={e => setEntity({ ...entity, timezone: e.target.value })}/></Field></Grid></FormDialog>
 <FormDialog open={unitOpen} onClose={() => setUnitOpen(false)} title="Create business unit" formId="unit-form" onSubmit={saveUnit} submitLabel="Create" loading={saving}><Field label="Legal entity"><Select value={unit.legal_entity_id} onChange={e => setUnit({ ...unit, legal_entity_id: e.target.value })}><Option value="">Workspace level</Option>{data.legal_entities.map(e => <Option key={e.id} value={e.id}>{e.name}</Option>)}</Select></Field><Grid columns="1fr 2fr" gap={8}><Field label="Code"><Input value={unit.code} onChange={e => setUnit({ ...unit, code: e.target.value })}/></Field><Field label="Name"><Input value={unit.name} onChange={e => setUnit({ ...unit, name: e.target.value })}/></Field></Grid></FormDialog>
 <FormDialog open={governanceOpen} onClose={() => setGovernanceOpen(false)} title="Dataset governance policy" formId="gov-form" onSubmit={saveGovernance} submitLabel="Save" loading={saving}><Field label="Dataset"><Select value={gov.dataset} onChange={e => setGov({ ...gov, dataset: e.target.value })}>{['audit_logs', 'security_events', 'webhook_deliveries', 'workspace_notifications', 'mobile_sync_events', 'field_work_order_events', 'workspace_access_sessions', 'automation_events', 'automation_runs', 'intelligence_runs', 'intelligence_insights', 'intelligence_snapshots'].map(x => <Option key={x}>{x}</Option>)}</Select></Field><Field label="Retention days"><Input type="number" min="1" value={gov.retention_days} onChange={e => setGov({ ...gov, retention_days: e.target.value })}/></Field><Field label="Residency region metadata"><Input value={gov.residency_region} onChange={e => setGov({ ...gov, residency_region: e.target.value })} placeholder="eu-west / uae / deployment-default"/></Field><SettingRow title="Legal hold" description="Stops destructive retention for this dataset." control={<Switch checked={gov.legal_hold} onChange={v => setGov({ ...gov, legal_hold: v })}/>}/></FormDialog>
 <FormDialog open={assignmentOpen} onClose={() => setAssignmentOpen(false)} title="Organization assignment" description={assignment.label} formId="organization-assignment-form" onSubmit={saveAssignment} submitLabel="Save" loading={saving}><Field label="Legal entity"><Select value={assignment.legal_entity_id} onChange={e => setAssignment({ ...assignment, legal_entity_id: e.target.value, business_unit_id: data.business_units.some(u => String(u.id) === assignment.business_unit_id && (!e.target.value || String(u.legal_entity_id || '') === e.target.value)) ? assignment.business_unit_id : '' })}><Option value="">Workspace / unassigned</Option>{data.legal_entities.map(e => <Option key={e.id} value={e.id}>{e.code} · {e.name}</Option>)}</Select></Field><Field label="Business unit"><Select value={assignment.business_unit_id} onChange={e => setAssignment({ ...assignment, business_unit_id: e.target.value })}><Option value="">No business unit</Option>{data.business_units.filter(u => !assignment.legal_entity_id || !u.legal_entity_id || String(u.legal_entity_id) === assignment.legal_entity_id).map(u => <Option key={u.id} value={u.id}>{u.code} · {u.name}</Option>)}</Select></Field></FormDialog>
 <FormDialog open={accessOpen} onClose={() => setAccessOpen(false)} title="Attribute access policy" description="Create narrowly-scoped allow/deny rules. Deny always wins." formId="access-form" onSubmit={saveAccess} submitLabel="Create" loading={saving}><Field label="Name"><Input value={access.name} onChange={e => setAccess({ ...access, name: e.target.value })}/></Field><Grid columns="1fr 1fr" gap={8}><Field label="Resource"><Select value={access.resource} onChange={e => setAccess({ ...access, resource: e.target.value })}><Option value="workspace">Workspace</Option><Option value="payroll">Payroll</Option><Option value="field">Field</Option><Option value="enterprise">Enterprise</Option><Option value="reports">Reports</Option><Option value="automations">Automations</Option><Option value="intelligence">Workforce Intelligence</Option><Option value="platform">Commercial Platform</Option></Select></Field><Field label="Effect"><Select value={access.effect} onChange={e => setAccess({ ...access, effect: e.target.value })}><Option value="allow">Allow only matches</Option><Option value="deny">Deny matches</Option></Select></Field></Grid><Field label="Role slugs (comma separated)"><Input value={access.role_slugs} onChange={e => setAccess({ ...access, role_slugs: e.target.value })}/></Field><Field label="Legal entity IDs"><Input value={access.legal_entity_ids} onChange={e => setAccess({ ...access, legal_entity_ids: e.target.value })}/></Field><Field label="Business unit IDs"><Input value={access.business_unit_ids} onChange={e => setAccess({ ...access, business_unit_ids: e.target.value })}/></Field><Field label="IP CIDRs"><Input value={access.ip_cidrs} onChange={e => setAccess({ ...access, ip_cidrs: e.target.value })}/></Field></FormDialog>
 </Page>;
}
