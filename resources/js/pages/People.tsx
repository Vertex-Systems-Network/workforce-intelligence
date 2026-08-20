import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Ban, Copy, Ellipsis, ImagePlus, KeyRound, Link2, Mail, Pencil, RotateCcw, Settings2, ShieldCheck, Trash2, UserCheck, UserPlus } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { hasAnyPermission, hasPermission } from '../access';
import { useConfirmAction, FormDialog, FilterBar, EmptyState, Alert, Avatar, Badge, Button, DataGrid, Dropdown, Field, Input, Modal, Page, PageHeader, SearchInput, Select, Switch, ViewModeToggle, type DataGridColumn, Box, Grid, Inline, Stack, SettingRow, Option } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
import { MediaPicker } from '../media/MediaPicker';
import type { MediaAsset } from '../media/types';
import { useShellEntitySearch } from '../shellEntityFocus';
type Person = {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    email: string;
    phone: string | null;
    avatar_url: string | null;
    locale: string | null;
    email_verified: boolean;
    force_password_change: boolean;
    employee_code: string | null;
    job_title_id: number | null;
    job_title: string | null;
    department_id: number | null;
    department: string | null;
    manager_id: number | null;
    manager: string | null;
    employment_type: string;
    status: string;
    roles: string[];
    joining_date: string | null;
    timezone: string | null;
};
type PersonOptions = {
    departments: Array<{
        id: number;
        name: string;
        code: string | null;
    }>;
    job_titles: Array<{
        id: number;
        name: string;
        code: string | null;
    }>;
    roles: Array<{
        id: number;
        name: string;
        slug: string;
    }>;
    managers: Array<{
        id: number;
        name: string;
    }>;
};
type PersonForm = {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    locale: string;
    password: string;
    employee_code: string;
    job_title_id: string;
    job_title: string;
    department_id: string;
    manager_id: string;
    role_slug: string;
    employment_type: string;
    joining_date: string;
    timezone: string;
    status: string;
};
type RegistrationSettings = {
    id: number;
    mode: string;
    default_role_slug: string;
    allowed_domains: string[] | null;
    require_email_verification: boolean;
    invite_expires_hours: number;
    allow_existing_users: boolean;
};
type Invitation = {
    id: number;
    uuid: string;
    email: string | null;
    role_slug: string;
    token_prefix: string;
    expires_at: string;
    accepted_at: string | null;
    created_at: string;
};
type SecuritySession = {
    id: number;
    uuid: string;
    ip_address: string | null;
    user_agent: string | null;
    last_seen_at: string;
    expires_at: string | null;
    revoked_at: string | null;
    revoke_reason: string | null;
};
type SecurityInfo = {
    member_id: number;
    status: string;
    email_verified: boolean;
    email_verified_at: string | null;
    force_password_change: boolean;
    password_changed_at: string | null;
    last_login_at: string | null;
    mfa_enabled: boolean;
    sessions: SecuritySession[];
};
const emptyForm: PersonForm = { first_name: '', last_name: '', email: '', phone: '', locale: 'en', password: '', employee_code: '', job_title_id: '', job_title: '', department_id: '', manager_id: '', role_slug: 'employee', employment_type: 'full_time', joining_date: '', timezone: Intl.DateTimeFormat().resolvedOptions().timeZone, status: 'active' };
const statusTone: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = { active: 'success', invited: 'warning', suspended: 'danger', archived: 'neutral' };
const employmentLabels: Record<string, string> = { full_time: 'Full time', part_time: 'Part time', contractor: 'Contractor', intern: 'Intern' };
/** Handles the people operation for the WorkIntel client. */ export default function People() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const workspace = session?.user.workspaces.find(x => x.id === workspaceId);
    const canManage = hasPermission(workspace, 'people.manage');
    const canMediaManage = hasPermission(workspace, 'media.manage');
    const canSettings = hasPermission(workspace, 'settings.manage');
    const canSecurity = hasAnyPermission(workspace, ['settings.manage', 'enterprise.security.manage']);
    const teamScoped = !canManage && !hasPermission(workspace, 'people.view_all') && !hasPermission(workspace, 'people.view');
    const [people, setPeople] = useState<Person[]>([]), [options, setOptions] = useState<PersonOptions>({ departments: [], job_titles: [], roles: [], managers: [] }), [search, setSearch] = useState(''), [view, setView] = useState<'table' | 'grid'>('table'), [loading, setLoading] = useState(true), [saving, setSaving] = useState(false), [error, setError] = useState('');
    useShellEntitySearch('people', setSearch);
    const [modalOpen, setModalOpen] = useState(false), [editing, setEditing] = useState<Person | null>(null), [form, setForm] = useState<PersonForm>(emptyForm), [memberMediaPicker, setMemberMediaPicker] = useState(false);
    const [regOpen, setRegOpen] = useState(false), [reg, setReg] = useState<RegistrationSettings | null>(null), [joinUrl, setJoinUrl] = useState(''), [domains, setDomains] = useState(''), [invitations, setInvitations] = useState<Invitation[]>([]), [inviteEmail, setInviteEmail] = useState(''), [inviteRole, setInviteRole] = useState('employee'), [inviteLink, setInviteLink] = useState('');
    const [securityOpen, setSecurityOpen] = useState(false), [securityPerson, setSecurityPerson] = useState<Person | null>(null), [security, setSecurity] = useState<SecurityInfo | null>(null), [tempPassword, setTempPassword] = useState('');
    /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        const p = await apiRequest<{
            data: Person[];
        }>('/api/v1/people', { workspaceId });
        setPeople(p.data);
        if (canManage)
            setOptions(await apiRequest<PersonOptions>('/api/v1/people/options', { workspaceId }));
        else
            setOptions({ departments: [], job_titles: [], roles: [], managers: [] });
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load employees.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    const filtered = useMemo(() => people.filter(p => [p.name, p.email, p.phone ?? '', p.job_title ?? '', p.department ?? ''].some(v => v.toLowerCase().includes(search.toLowerCase()))), [people, search]);
    /** Handles the open create operation for the WorkIntel client. */ const openCreate = () => { setEditing(null); setForm({ ...emptyForm, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone }); setError(''); setModalOpen(true); };
    /** Handles the open edit operation for the WorkIntel client. */ const openEdit = (p: Person) => { setEditing(p); setForm({ first_name: p.first_name, last_name: p.last_name, email: p.email, phone: p.phone ?? '', locale: p.locale ?? 'en', password: '', employee_code: p.employee_code ?? '', job_title_id: p.job_title_id ? String(p.job_title_id) : '', job_title: p.job_title ?? '', department_id: p.department_id ? String(p.department_id) : '', manager_id: p.manager_id ? String(p.manager_id) : '', role_slug: p.roles[0] ?? 'employee', employment_type: p.employment_type, joining_date: p.joining_date ?? '', timezone: p.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone, status: p.status }); setModalOpen(true); };
    /** Handles the save person operation for the WorkIntel client. */ const savePerson = async (e: FormEvent) => { e.preventDefault(); setSaving(true); setError(''); try {
        const body = { ...form, job_title_id: form.job_title_id ? Number(form.job_title_id) : null, department_id: form.department_id ? Number(form.department_id) : null, manager_id: form.manager_id ? Number(form.manager_id) : null, password: editing ? undefined : form.password, phone: form.phone || null };
        await apiRequest(editing ? `/api/v1/people/${editing.id}` : '/api/v1/people', { method: editing ? 'PUT' : 'POST', workspaceId, body: JSON.stringify(body) });
        setModalOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not save employee.');
    }
    finally {
        setSaving(false);
    } };
    /** Assigns an existing Media Library image as the edited member's profile photo. */ const setMemberAvatar = async (asset: MediaAsset) => { if (!editing)
        return; setSaving(true); setError(''); try {
        const response = await apiRequest<{
            data: {
                avatar_url: string;
            };
        }>(`/api/v1/people/${editing.id}/avatar`, { method: 'POST', workspaceId, body: JSON.stringify({ media_asset_id: asset.id }) });
        setEditing({ ...editing, avatar_url: response.data.avatar_url });
        setMemberMediaPicker(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not update profile photo.');
    }
    finally {
        setSaving(false);
    } };
    /** Removes the edited member's Media Library backed profile photo. */ const clearMemberAvatar = async () => { if (!editing)
        return; setSaving(true); setError(''); try {
        await apiRequest(`/api/v1/people/${editing.id}/avatar`, { method: 'DELETE', workspaceId });
        setEditing({ ...editing, avatar_url: null });
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not remove profile photo.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the lifecycle operation for the WorkIntel client. */ const lifecycle = async (p: Person, status: 'active' | 'suspended' | 'archived') => { if (status === 'archived' && !await confirmAction({ title: 'Archive employee?', description: `Archive ${p.name}?`, confirmLabel: 'Archive', danger: true }))
        return; setError(''); try {
        await apiRequest(`/api/v1/people/${p.id}/lifecycle`, { method: 'PATCH', workspaceId, body: JSON.stringify({ status }) });
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not update employee status.');
    } };
    /** Loads load registration data required by the current view. */ const loadRegistration = async () => { if (!canSettings)
        return; setSaving(true); setError(''); try {
        const [settings, invites] = await Promise.all([apiRequest<{
                data: RegistrationSettings;
                join_url: string;
            }>('/api/v1/people/registration', { workspaceId }), apiRequest<{
                data: Invitation[];
            }>('/api/v1/people/invitations', { workspaceId })]);
        setReg(settings.data);
        setJoinUrl(settings.join_url);
        setDomains((settings.data.allowed_domains ?? []).join(', '));
        setInvitations(invites.data);
        setInviteRole(settings.data.default_role_slug);
        setRegOpen(true);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load registration settings.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the save registration operation for the WorkIntel client. */ const saveRegistration = async () => { if (!reg)
        return; setSaving(true); try {
        const payload = { ...reg, allowed_domains: domains.split(',').map(x => x.trim()).filter(Boolean) };
        const r = await apiRequest<{
            data: RegistrationSettings;
            join_url: string;
        }>('/api/v1/people/registration', { method: 'PUT', workspaceId, body: JSON.stringify(payload) });
        setReg(r.data);
        setJoinUrl(r.join_url);
        setDomains((r.data.allowed_domains ?? []).join(', '));
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not save registration policy.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the create invite operation for the WorkIntel client. */ const createInvite = async () => { setSaving(true); setInviteLink(''); try {
        const r = await apiRequest<{
            data: Invitation;
            invite_url: string;
        }>('/api/v1/people/invitations', { method: 'POST', workspaceId, body: JSON.stringify({ email: inviteEmail || null, role_slug: inviteRole }) });
        setInviteLink(r.invite_url);
        setInviteEmail('');
        const rows = await apiRequest<{
            data: Invitation[];
        }>('/api/v1/people/invitations', { workspaceId });
        setInvitations(rows.data);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create invitation.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the revoke invite operation for the WorkIntel client. */ const revokeInvite = async (row: Invitation) => { await apiRequest(`/api/v1/people/invitations/${row.id}`, { method: 'DELETE', workspaceId }); setInvitations(v => v.filter(x => x.id !== row.id)); };
    /** Handles the open security operation for the WorkIntel client. */ const openSecurity = async (p: Person) => { setSecurityPerson(p); setSecurityOpen(true); setSecurity(null); setTempPassword(''); setError(''); try {
        const r = await apiRequest<{
            data: SecurityInfo;
        }>(`/api/v1/people/${p.id}/security`, { workspaceId });
        setSecurity(r.data);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load user security.');
    } };
    /** Handles the security action operation for the WorkIntel client. */ const securityAction = async (action: 'reset-password' | 'send-reset' | 'revoke-sessions' | 'reset-mfa') => { if (!securityPerson)
        return; setSaving(true); setTempPassword(''); try {
        const r = await apiRequest<any>(`/api/v1/people/${securityPerson.id}/security/${action}`, { method: 'POST', workspaceId, body: action === 'reset-password' ? JSON.stringify({ force_change: true }) : undefined });
        if (r.temporary_password)
            setTempPassword(r.temporary_password);
        const fresh = await apiRequest<{
            data: SecurityInfo;
        }>(`/api/v1/people/${securityPerson.id}/security`, { workspaceId });
        setSecurity(fresh.data);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Security action failed.');
    }
    finally {
        setSaving(false);
    } };
    if (loading && !people.length)
        return <PageLoadingState />;
    const actions = canManage ? <Inline gap={7}>{canSettings && <Button variant="outline" size="sm" onClick={() => void loadRegistration()}><Settings2 size={14}/> Registration</Button>}<Button variant="primary" size="sm" onClick={openCreate}><UserPlus size={14}/> Add Employee</Button></Inline> : undefined;
    /** Handles the menu operation for the WorkIntel client. */ const menu = (p: Person) => { const items: any[] = [{ label: 'Edit employee', icon: <Pencil size={14}/>, onClick: () => openEdit(p) }]; if (canSecurity)
        items.push({ label: 'Security & sessions', icon: <ShieldCheck size={14}/>, onClick: () => void openSecurity(p) }); items.push({ separator: true }); if (p.status === 'active')
        items.push({ label: 'Suspend access', icon: <Ban size={14}/>, danger: true, onClick: () => void lifecycle(p, 'suspended') });
    else
        items.push({ label: 'Reactivate', icon: <UserCheck size={14}/>, onClick: () => void lifecycle(p, 'active') }); if (p.status !== 'archived')
        items.push({ label: 'Archive', icon: <Trash2 size={14}/>, danger: true, onClick: () => void lifecycle(p, 'archived') }); return items; };
    /** Defines the active workspace session columns for the security inspector. */ const securitySessionColumns: DataGridColumn<SecuritySession>[] = [
        { id: 'ip', header: 'IP', value: r => r.ip_address || '', cell: r => r.ip_address || '—', sortable: true },
        { id: 'last_seen', header: 'Last seen', value: r => r.last_seen_at, cell: r => new Date(r.last_seen_at).toLocaleString(), sortable: true },
        { id: 'expires', header: 'Expires', value: r => r.expires_at || '', cell: r => r.expires_at ? new Date(r.expires_at).toLocaleString() : '—', sortable: true },
        { id: 'status', header: 'Status', value: r => r.revoked_at ? 'revoked' : 'active', cell: r => <Badge tone={r.revoked_at ? 'danger' : 'success'}>{r.revoked_at ? 'Revoked' : 'Active'}</Badge>, sortable: true, filter: { type: 'select', options: [{ label: 'Active', value: 'active' }, { label: 'Revoked', value: 'revoked' }] } }
    ];
    /** Define sortable, hideable People columns for the shared DataGrid V2 surface. */ const peopleColumns: DataGridColumn<Person>[] = [
        { id: 'employee', header: 'Employee', sortValue: p => p.name, cell: p => <Inline align="center" gap={9}><Avatar name={p.name} src={p.avatar_url ?? undefined}/><div><Box weight={550}>{p.name}</Box><div className="ui-card-description">{p.job_title || 'Team member'} · {p.email}{p.force_password_change ? ' · password change required' : ''}</div></div></Inline> },
        { id: 'department', header: 'Department', sortValue: p => p.department ?? '', filterValue: p => p.department_id ? String(p.department_id) : '', filter: { type: 'select', label: 'Department', options: options.departments.map(item => ({ value: String(item.id), label: item.name })) }, cell: p => p.department || '—' },
        { id: 'manager', header: 'Manager', sortValue: p => p.manager ?? '', cell: p => p.manager || '—' },
        { id: 'employment', header: 'Employment', sortValue: p => employmentLabels[p.employment_type] ?? p.employment_type, filterValue: p => p.employment_type, filter: { type: 'select', label: 'Employment', options: Object.entries(employmentLabels).map(([value, label]) => ({ value, label })) }, cell: p => <Badge>{employmentLabels[p.employment_type] ?? p.employment_type}</Badge> },
        { id: 'role', header: 'Role', sortValue: p => p.roles[0] ?? 'employee', filterValue: p => p.roles[0] ?? 'employee', filter: { type: 'select', label: 'Role', options: options.roles.map(role => ({ value: role.slug, label: role.name })) }, cell: p => <Box as="span" textTransform="capitalize">{p.roles[0] ?? 'employee'}</Box> },
        { id: 'status', header: 'Status', sortValue: p => p.status, filterValue: p => p.status, filter: { type: 'select', label: 'Status', options: Object.keys(statusTone).map(value => ({ value, label: value[0].toUpperCase() + value.slice(1) })) }, cell: p => <Badge tone={statusTone[p.status] ?? 'neutral'} dot>{p.status}</Badge> },
        { id: 'actions', header: '', hideable: false, cell: p => canManage ? <Dropdown trigger={<Button variant="ghost" size="sm" iconOnly aria-label={`Actions for ${p.name}`}><Ellipsis size={15}/></Button>} items={menu(p)}/> : null },
    ];
    return <Page><PageHeader title={teamScoped ? 'My Team' : 'People'} description={teamScoped ? `${people.length} team members visible to you` : `${people.length} employees${canManage ? ` · ${options.departments.length} departments` : ''}`} actions={actions}/>{error && <Alert tone="danger">{error}</Alert>}<FilterBar primary={<SearchInput value={search} onChange={e => setSearch(e.target.value)} placeholder="Search employees…"/>} actions={<ViewModeToggle value={view} onChange={setView} ariaLabel="People view"/>}/>
 {view === 'table' ? <DataGrid rows={filtered} columns={peopleColumns} rowKey={p => p.id} persistKey="people" searchable={false} onRefresh={load} defaultSort={{ id: 'employee', direction: 'asc' }} empty={<EmptyState title={search ? 'No employees match your search.' : 'No employees yet.'} text={search ? 'Try a different name, email, department or role.' : 'Add the first employee or configure workspace registration.'}/>}/> : <Grid columns="repeat(auto-fill,minmax(260px,1fr))" gap={12}>{filtered.map(p => <Box key={p.id} className="ui-card" p={18}><Inline gap={11} align="center"><Avatar name={p.name} size="lg"/><Box flex={1}><Box weight={650}>{p.name}</Box><div className="ui-card-description">{p.job_title || 'Team member'}</div></Box><Badge tone={statusTone[p.status] ?? 'neutral'}>{p.status}</Badge></Inline><Box display="grid" gap={6} mt={13} size={12} color="var(--text-2)"><span>{p.email}</span><span>{p.phone || 'No phone'}</span><span>{p.department || 'No department'} · {p.roles[0] ?? 'employee'}</span></Box>{canManage && <Inline gap={7} mt={13}><Button size="sm" variant="outline" onClick={() => openEdit(p)}><Pencil size={13}/> Edit</Button>{canSecurity && <Button size="sm" variant="ghost" onClick={() => void openSecurity(p)}><ShieldCheck size={13}/> Security</Button>}</Inline>}</Box>)}</Grid>}
 <FormDialog open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit employee' : 'Add employee'} description={editing ? 'Update identity and workspace membership. Password/security actions are managed separately.' : 'Create a login with a temporary password. The user must choose a new password on first sign-in.'} formId="person-form" onSubmit={savePerson} submitLabel={editing ? 'Save Changes' : 'Create Employee'} loading={saving} gap={11}><Grid columns="1fr 1fr" gap={9}><Field label="First name"><Input value={form.first_name} onChange={e => setForm({ ...form, first_name: e.target.value })} required/></Field><Field label="Last name"><Input value={form.last_name} onChange={e => setForm({ ...form, last_name: e.target.value })} required/></Field></Grid><Field label="Work email"><Input type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} required/></Field><Grid columns="1fr 1fr" gap={9}><Field label="Phone"><Input value={form.phone} onChange={e => setForm({ ...form, phone: e.target.value })}/></Field><Field label="Language"><Input value={form.locale} onChange={e => setForm({ ...form, locale: e.target.value })} placeholder="en"/></Field></Grid>{editing && <div className="profile-photo-editor profile-photo-editor--compact"><Avatar name={editing.name} src={editing.avatar_url ?? undefined}/><div><strong>Profile photo</strong><p>Use a managed workspace image instead of an external URL.</p><div className="profile-photo-editor__actions">{canMediaManage && <Button type="button" variant="outline" size="sm" onClick={() => setMemberMediaPicker(true)}><ImagePlus size={13}/> Choose or upload photo</Button>}{editing.avatar_url && canMediaManage && <Button type="button" variant="ghost" size="sm" onClick={() => void clearMemberAvatar()}><Trash2 size={13}/> Remove photo</Button>}{!canMediaManage && <span className="ui-card-description">Media Manage permission is required to change another user's photo.</span>}</div></div></div>}{!editing && <Field label="Temporary password" hint="Minimum 12 characters with upper/lowercase, number and symbol. User is forced to change it on first sign-in."><Input type="password" value={form.password} onChange={e => setForm({ ...form, password: e.target.value })} required/></Field>}<Grid columns="1fr 1fr" gap={9}><Field label="Job title"><Select value={form.job_title_id} onChange={e => { const x = options.job_titles.find(x => String(x.id) === e.target.value); setForm({ ...form, job_title_id: e.target.value, job_title: x?.name ?? '' }); }}><Option value="">No job title</Option>{options.job_titles.map(x => <Option key={x.id} value={x.id}>{x.name}</Option>)}</Select></Field><Field label="Employee code"><Input value={form.employee_code} onChange={e => setForm({ ...form, employee_code: e.target.value })}/></Field></Grid><Grid columns="1fr 1fr" gap={9}><Field label="Department"><Select value={form.department_id} onChange={e => setForm({ ...form, department_id: e.target.value })}><Option value="">No department</Option>{options.departments.map(x => <Option key={x.id} value={x.id}>{x.name}</Option>)}</Select></Field><Field label="Manager"><Select value={form.manager_id} onChange={e => setForm({ ...form, manager_id: e.target.value })}><Option value="">No manager</Option>{options.managers.filter(x => x.id !== editing?.id).map(x => <Option key={x.id} value={x.id}>{x.name}</Option>)}</Select></Field></Grid><Grid columns="1fr 1fr" gap={9}><Field label="Role"><Select value={form.role_slug} onChange={e => setForm({ ...form, role_slug: e.target.value })}>{options.roles.map(x => <Option key={x.id} value={x.slug}>{x.name}</Option>)}</Select></Field><Field label="Employment"><Select value={form.employment_type} onChange={e => setForm({ ...form, employment_type: e.target.value })}><Option value="full_time">Full time</Option><Option value="part_time">Part time</Option><Option value="contractor">Contractor</Option><Option value="intern">Intern</Option></Select></Field></Grid><Grid columns="1fr 1fr" gap={9}><Field label="Joining date"><Input type="date" value={form.joining_date} onChange={e => setForm({ ...form, joining_date: e.target.value })}/></Field><Field label="Timezone"><Input value={form.timezone} onChange={e => setForm({ ...form, timezone: e.target.value })}/></Field></Grid></FormDialog>
 <Modal open={regOpen} onClose={() => !saving && setRegOpen(false)} title="Workspace Registration" description="Control how new people can join this workspace." size="lg" footer={<><Button variant="outline" onClick={() => setRegOpen(false)}>Close</Button><Button variant="primary" loading={saving} onClick={() => void saveRegistration()}>Save Policy</Button></>}>{reg && <Stack gap={14}><Grid columns="1fr 1fr" gap={10}><Field label="Registration mode"><Select value={reg.mode} onChange={e => setReg({ ...reg, mode: e.target.value })}><Option value="disabled">Disabled</Option><Option value="invite_only">Invite only</Option><Option value="invite_link">Invite link only</Option><Option value="approved_domains">Approved domains</Option><Option value="public">Public registration</Option><Option value="sso_only">SSO only</Option></Select></Field><Field label="Default role"><Select value={reg.default_role_slug} onChange={e => setReg({ ...reg, default_role_slug: e.target.value })}>{options.roles.map(x => <Option key={x.id} value={x.slug}>{x.name}</Option>)}</Select></Field></Grid>{reg.mode === 'approved_domains' && <Field label="Approved domains" hint="Comma separated, e.g. acme.com, subsidiary.com"><Input value={domains} onChange={e => setDomains(e.target.value)}/></Field>}<Grid columns="1fr 1fr" gap={10}><Field label="Invite expiry (hours)"><Input type="number" min={1} max={2160} value={reg.invite_expires_hours} onChange={e => setReg({ ...reg, invite_expires_hours: Number(e.target.value) })}/></Field><Stack gap={8}><SettingRow title="Email verification" description="Public/domain registrations verify ownership before activation." control={<Switch checked={reg.require_email_verification} onChange={v => setReg({ ...reg, require_email_verification: v })}/>}/><SettingRow title="Existing users may join" description="They must enter their current password." control={<Switch checked={reg.allow_existing_users} onChange={v => setReg({ ...reg, allow_existing_users: v })}/>}/></Stack></Grid><Field label="Public join URL"><Inline gap={7}><Input readOnly value={joinUrl}/><Button iconOnly aria-label="Copy join URL" onClick={() => void navigator.clipboard.writeText(joinUrl)}><Copy size={14}/></Button></Inline></Field><Box borderTop="1px solid var(--border)" pt={14}><Box weight={650} mb={9}>Create invitation</Box><Grid columns="1fr 180px auto" gap={8}><Input type="email" value={inviteEmail} onChange={e => setInviteEmail(e.target.value)} placeholder="Email (blank for generic link)"/><Select value={inviteRole} onChange={e => setInviteRole(e.target.value)}>{options.roles.map(x => <Option key={x.id} value={x.slug}>{x.name}</Option>)}</Select><Button variant="primary" onClick={() => void createInvite()} loading={saving}><Mail size={13}/> Invite</Button></Grid>{inviteLink && <Alert tone="success" mt={9}><Inline gap={7} align="center" width="100%"><Link2 size={14}/><Box as="code" flex={1} overflow="hidden" textOverflow="ellipsis">{inviteLink}</Box><Button size="sm" onClick={() => void navigator.clipboard.writeText(inviteLink)}>Copy</Button></Inline></Alert>}<Stack gap={6} mt={10}>{invitations.slice(0, 8).map(row => <div key={row.id} className="schedule-list-row"><div><strong>{row.email || 'Generic invite link'}</strong><small>{row.role_slug} · expires {new Date(row.expires_at).toLocaleString()}</small></div>{row.accepted_at ? <Badge tone="success">Accepted</Badge> : <Button size="sm" variant="ghost" onClick={() => void revokeInvite(row)}>Revoke</Button>}</div>)}</Stack></Box></Stack>}</Modal>
 <MediaPicker open={memberMediaPicker} workspaceId={workspaceId} imagesOnly title={editing ? `Choose photo for ${editing.name}` : 'Choose profile photo'} onClose={() => setMemberMediaPicker(false)} onSelect={asset => void setMemberAvatar(asset)}/>
 <Modal open={securityOpen} onClose={() => !saving && setSecurityOpen(false)} title={`Security${securityPerson ? ` · ${securityPerson.name}` : ''}`} description="Password, MFA and active workspace sessions." size="lg" footer={<Button variant="outline" onClick={() => setSecurityOpen(false)}>Close</Button>}>{security ? <Stack gap={14}><Grid columns="repeat(4,1fr)" gap={8}><Box className="ui-card" p={10}><small>Email</small><div><Badge tone={security.email_verified ? 'success' : 'warning'}>{security.email_verified ? 'Verified' : 'Unverified'}</Badge></div></Box><Box className="ui-card" p={10}><small>MFA</small><div><Badge tone={security.mfa_enabled ? 'success' : 'neutral'}>{security.mfa_enabled ? 'Enabled' : 'Off'}</Badge></div></Box><Box className="ui-card" p={10}><small>Password</small><div><Badge tone={security.force_password_change ? 'warning' : 'success'}>{security.force_password_change ? 'Change required' : 'Normal'}</Badge></div></Box><Box className="ui-card" p={10}><small>Status</small><div><Badge tone={statusTone[security.status] ?? 'neutral'}>{security.status}</Badge></div></Box></Grid><Inline gap={7} wrap="wrap"><Button size="sm" variant="outline" loading={saving} onClick={() => void securityAction('reset-password')}><KeyRound size={13}/> Temporary Password</Button><Button size="sm" variant="outline" onClick={() => void securityAction('send-reset')}><Mail size={13}/> Send Reset Link</Button><Button size="sm" variant="outline" onClick={() => void securityAction('revoke-sessions')}><RotateCcw size={13}/> Revoke Sessions</Button><Button size="sm" variant="danger" onClick={async () => { if (await confirmAction({ title: 'Reset MFA enrollment?', description: 'The user will need to enroll an authenticator again before using MFA.', confirmLabel: 'Reset MFA', danger: true }))
        await securityAction('reset-mfa'); }}><ShieldCheck size={13}/> Reset MFA</Button></Inline>{tempPassword && <Alert tone="warning"><div><strong>Temporary password — copy now</strong><Inline gap={7} mt={6}><Box as="code" flex={1}>{tempPassword}</Box><Button size="sm" onClick={() => void navigator.clipboard.writeText(tempPassword)}>Copy</Button></Inline></div></Alert>}<div><Box weight={650} mb={8}>Workspace sessions</Box><DataGrid rows={security.sessions} columns={securitySessionColumns} rowKey={r => r.id} persistKey={`people.security.sessions.${security.member_id}`} searchable searchPlaceholder="Search sessions" defaultSort={{ id: 'last_seen', direction: 'desc' }} empty="No workspace sessions." ariaLabel="Workspace security sessions"/></div></Stack> : <div className="ui-card-description">Loading security details…</div>}</Modal>
 </Page>;
}
