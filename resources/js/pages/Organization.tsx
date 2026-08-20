import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Building2, BriefcaseBusiness, Ellipsis, Network, Pencil, Plus, Search, Trash2, Users } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { hasPermission } from '../access';
import { ConfirmDialog, FormDialog, FilterBar, Alert, Avatar, Badge, Button, Card, CardBody, Dropdown, Field, Input, Page, PageHeader, SearchInput, Segmented, Select, Textarea, Checkbox, ChoiceList, ChoiceRow, Box, Grid, Inline, Option } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
type Department = {
    id: number;
    name: string;
    code: string | null;
    members_count: number;
};
type JobTitle = {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    status: string;
    members_count: number;
};
type Person = {
    id: number;
    name: string;
};
type TeamMember = {
    id: number;
    user: {
        first_name: string;
        last_name: string;
    };
};
type Team = {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    status: string;
    department_id: number | null;
    lead_id: number | null;
    members_count: number;
    department: {
        id: number;
        name: string;
    } | null;
    lead: {
        id: number;
        user: {
            first_name: string;
            last_name: string;
        };
    } | null;
    members: TeamMember[];
};
type Payload = {
    departments: Department[];
    job_titles: JobTitle[];
    teams: Team[];
    people: Person[];
};
type Mode = 'departments' | 'job_titles' | 'teams';
/** Handles the organization operation for the WorkIntel client. */ export default function Organization() {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const workspace = session?.user.workspaces.find(item => item.id === workspaceId);
    const canManage = hasPermission(workspace, 'organization.manage');
    const [data, setData] = useState<Payload | null>(null);
    const [mode, setMode] = useState<Mode>('departments');
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [modal, setModal] = useState(false);
    const [editing, setEditing] = useState<any>(null);
    const [pendingDelete, setPendingDelete] = useState<{
        item: any;
        endpoint: string;
    } | null>(null);
    const [form, setForm] = useState({ name: '', code: '', description: '', status: 'active', department_id: '', lead_id: '', member_ids: [] as number[] });
    /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        setData(await apiRequest<Payload>('/api/v1/organization', { workspaceId }));
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load organization.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Handles the open create operation for the WorkIntel client. */ const openCreate = () => { setEditing(null); setForm({ name: '', code: '', description: '', status: 'active', department_id: '', lead_id: '', member_ids: [] }); setError(''); setModal(true); };
    /** Handles the open edit operation for the WorkIntel client. */ const openEdit = (item: any) => { setEditing(item); setForm({ name: item.name ?? '', code: item.code ?? '', description: item.description ?? '', status: item.status ?? 'active', department_id: item.department_id ? String(item.department_id) : '', lead_id: item.lead_id ? String(item.lead_id) : '', member_ids: item.members?.map((m: TeamMember) => m.id) ?? [] }); setError(''); setModal(true); };
    const endpoint = mode === 'departments' ? 'departments' : mode === 'job_titles' ? 'job-titles' : 'teams';
    /** Handles the save operation for the WorkIntel client. */ const save = async (e: FormEvent) => { e.preventDefault(); if (!workspaceId)
        return; setSaving(true); setError(''); try {
        const body = mode === 'departments' ? { name: form.name, code: form.code || null } : mode === 'job_titles' ? { name: form.name, code: form.code || null, description: form.description || null, status: form.status } : { name: form.name, code: form.code || null, description: form.description || null, status: form.status, department_id: form.department_id ? Number(form.department_id) : null, lead_id: form.lead_id ? Number(form.lead_id) : null, member_ids: form.member_ids };
        await apiRequest(`/api/v1/organization/${endpoint}${editing ? `/${editing.id}` : ''}`, { method: editing ? 'PUT' : 'POST', workspaceId, body: JSON.stringify(body) });
        setModal(false);
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not save organization item.');
    }
    finally {
        setSaving(false);
    } };
    /** Delete the selected organization item after the shared confirmation dialog authorizes the action. */ const remove = async () => { const pending = pendingDelete; if (!workspaceId || !pending)
        return; setSaving(true); setError(''); try {
        await apiRequest(`/api/v1/organization/${pending.endpoint}/${pending.item.id}`, { method: 'DELETE', workspaceId });
        setPendingDelete(null);
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not delete item.');
    }
    finally {
        setSaving(false);
    } };
    const items = useMemo(() => { const base = mode === 'departments' ? data?.departments : mode === 'job_titles' ? data?.job_titles : data?.teams; return (base ?? []).filter((item: any) => [item.name, item.code ?? '', item.description ?? ''].some((value: string) => value.toLowerCase().includes(search.toLowerCase()))); }, [data, mode, search]);
    if (loading && !data)
        return <PageLoadingState />;
    return <Page><PageHeader title="Organization" description="Departments, job titles and teams used across people, scheduling and reporting" actions={canManage ? <Button variant="primary" size="sm" onClick={openCreate}><Plus size={14}/> Add {mode === 'departments' ? 'Department' : mode === 'job_titles' ? 'Job Title' : 'Team'}</Button> : undefined}/>{error && <Alert tone="danger">{error}</Alert>}
 <FilterBar primary={<SearchInput icon={<Search size={14}/>} value={search} onChange={e => setSearch(e.target.value)} placeholder="Search organization…"/>} actions={<Segmented value={mode} onChange={setMode} options={[{ value: 'departments', label: <><Building2 size={13}/> Departments</> }, { value: 'job_titles', label: <><BriefcaseBusiness size={13}/> Job Titles</> }, { value: 'teams', label: <><Network size={13}/> Teams</> }]}/>}/>
 <Grid columns="repeat(auto-fill,minmax(285px,1fr))" gap={12}>{items.map((item: any) => <Card key={item.id} interactive><CardBody><Inline align="flex-start" gap={10}><Box className="ui-empty__icon" m={0} width={36} height={36}>{mode === 'departments' ? <Building2 size={17}/> : mode === 'job_titles' ? <BriefcaseBusiness size={17}/> : <Users size={17}/>}</Box><Box flex={1} minWidth={0}><Inline justify="space-between" gap={8}><div><Box weight={650}>{item.name}</Box><div className="ui-card-description">{item.code || 'No code'}{mode === 'teams' && item.department?.name ? ` · ${item.department.name}` : ''}</div></div>{canManage && <Dropdown trigger={<Button variant="ghost" size="sm" iconOnly aria-label="Actions"><Ellipsis size={15}/></Button>} items={[{ label: 'Edit', icon: <Pencil size={13}/>, onClick: () => openEdit(item) }, { separator: true }, { label: 'Delete', danger: true, icon: <Trash2 size={13}/>, onClick: () => setPendingDelete({ item, endpoint }) }]}/>}</Inline>{item.description && <Box size={12} color="var(--text-2)" mt={10} lineHeight={1.5}>{item.description}</Box>}<Inline align="center" justify="space-between" mt={13}><Badge tone={item.status === 'inactive' ? 'neutral' : 'success'} dot>{item.status ?? 'active'}</Badge><span className="ui-card-description">{item.members_count ?? 0} {mode === 'job_titles' ? 'employees' : 'members'}</span></Inline>{mode === 'teams' && item.members?.length > 0 && <Inline align="center" gap={4} mt={12}>{item.members.slice(0, 5).map((member: TeamMember) => <Avatar key={member.id} name={`${member.user.first_name} ${member.user.last_name}`} size="sm"/>)}{item.members.length > 5 && <span className="ui-card-description">+{item.members.length - 5}</span>}</Inline>}</Box></Inline></CardBody></Card>)}</Grid>
 <FormDialog open={modal} onClose={() => setModal(false)} title={`${editing ? 'Edit' : 'Add'} ${mode === 'departments' ? 'department' : mode === 'job_titles' ? 'job title' : 'team'}`} description="Organization structure is shared across people, permissions, projects and attendance." size={mode === 'teams' ? 'lg' : 'md'} formId="organization-form" onSubmit={save} submitLabel={editing ? 'Save Changes' : 'Create'} loading={saving}>{error && <Alert tone="danger">{error}</Alert>}<Grid columns="2fr 1fr" gap={10}><Field label="Name"><Input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required/></Field><Field label="Code"><Input value={form.code} onChange={e => setForm({ ...form, code: e.target.value })}/></Field></Grid>{mode !== 'departments' && <Field label="Description"><Textarea value={form.description} onChange={e => setForm({ ...form, description: e.target.value })}/></Field>}{mode === 'teams' && <><Grid columns="1fr 1fr" gap={10}><Field label="Department"><Select value={form.department_id} onChange={e => setForm({ ...form, department_id: e.target.value })}><Option value="">No department</Option>{data?.departments.map(d => <Option key={d.id} value={d.id}>{d.name}</Option>)}</Select></Field><Field label="Team lead"><Select value={form.lead_id} onChange={e => setForm({ ...form, lead_id: e.target.value })}><Option value="">No team lead</Option>{data?.people.map(p => <Option key={p.id} value={p.id}>{p.name}</Option>)}</Select></Field></Grid><Field label="Members"><ChoiceList columns={2}>{data?.people.map(person => { const selected = form.member_ids.includes(person.id); return <ChoiceRow key={person.id} selected={selected}><Checkbox checked={selected} onChange={e => setForm({ ...form, member_ids: e.target.checked ? [...form.member_ids, person.id] : form.member_ids.filter(id => id !== person.id) })}/>{person.name}</ChoiceRow>; })}</ChoiceList></Field></>}{mode === 'job_titles' && <Field label="Status"><Select value={form.status} onChange={e => setForm({ ...form, status: e.target.value })}><Option value="active">Active</Option><Option value="inactive">Inactive</Option></Select></Field>}{mode === 'teams' && <Field label="Status"><Select value={form.status} onChange={e => setForm({ ...form, status: e.target.value })}><Option value="active">Active</Option><Option value="inactive">Inactive</Option></Select></Field>}</FormDialog><ConfirmDialog open={Boolean(pendingDelete)} onClose={() => !saving && setPendingDelete(null)} onConfirm={remove} title={pendingDelete ? `Delete ${pendingDelete.item.name}?` : 'Delete item?'} description="This organization item will be removed. Reassign dependent members first if the server requires it." confirmLabel="Delete" danger loading={saving}/>
 </Page>;
}
