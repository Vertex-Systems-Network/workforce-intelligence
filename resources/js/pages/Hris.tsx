import { FormEvent, useEffect, useMemo, useState } from 'react';
import { BadgeCheck, ClipboardCheck, Download, FileText, FolderPlus, Laptop, Plus, ShieldCheck, UserCog } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { hasPermission } from '../access';
import { Alert, Badge, Button, Card, CardBody, CardHeader, Field, Input, Page, PageHeader, Select, Tabs, Textarea, Pressable, Box, Grid, Inline, Stack, FormDialog, Option } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
import { MediaFileField } from '../media/MediaFileField';
import './hris.css';
type Member = {
    id: number;
    employee_code: string | null;
    name: string;
    email: string | null;
    job_title: string | null;
    department: string | null;
    employment_type: string;
    employment_stage: string;
    joining_date: string | null;
    probation_end_date: string | null;
    termination_date: string | null;
    status: string;
};
type Contact = {
    id: number;
    name: string;
    relationship: string;
    phone: string;
    email: string | null;
    is_primary: boolean;
};
type Dependent = {
    id: number;
    name: string;
    relationship: string;
    date_of_birth: string | null;
    benefits_eligible: boolean;
};
type History = {
    id: number;
    event_type: string;
    effective_date: string;
    from_value?: string | null;
    to_value?: string | null;
    note?: string | null;
};
type CustomField = {
    id: number;
    label: string;
    key: string;
    field_type: string;
    visibility: string;
    required: boolean;
    value: string | null;
};
type Profile = {
    member: Member;
    manager: {
        id: number;
        name: string;
    } | null;
    custom_fields: CustomField[];
    can_view_sensitive: boolean;
    can_manage: boolean;
    emergency_contacts?: Contact[];
    dependents?: Dependent[];
    employment_history: History[];
};
type DocumentRow = {
    id: number;
    uuid: string;
    title: string;
    document_type: string;
    file_name: string;
    expires_on: string | null;
    visibility: string;
    folder?: {
        id: number;
        name: string;
    } | null;
};
type Contract = {
    id: number;
    version: number;
    title: string;
    contract_type: string;
    effective_from: string;
    effective_to: string | null;
    status: string;
    salary_amount: string | null;
    salary_currency: string | null;
    salary_period: string | null;
    document?: {
        id: number;
        title: string;
        file_name: string;
    } | null;
};
type DocumentPayload = {
    folders: Array<{
        id: number;
        name: string;
        category: string;
        documents_count: number;
    }>;
    documents: DocumentRow[];
    contracts: Contract[];
    can_manage_documents: boolean;
};
type ChecklistItem = {
    id: number;
    title: string;
    owner_type: string;
    due_date: string | null;
    status: string;
    required: boolean;
};
type Checklist = {
    id: number;
    uuid: string;
    type: string;
    name: string;
    effective_date: string;
    status: string;
    items: ChecklistItem[];
};
type Template = {
    id: number;
    name: string;
    type: string;
    status: string;
    items: Array<{
        id: number;
        title: string;
        owner_type: string;
        due_offset_days: number;
    }>;
};
type Asset = {
    id: number;
    uuid: string;
    asset_tag: string;
    name: string;
    category: string;
    serial_number: string | null;
    status: string;
    warranty_expires_on: string | null;
    assignments: Array<{
        id: number;
        member_id: number;
        assigned_on: string;
        returned_on: string | null;
        member?: {
            user?: {
                first_name: string;
                last_name: string;
            };
        };
    }>;
};
type Policy = {
    id: number;
    uuid: string;
    policy_key: string;
    version: number;
    title: string;
    content: string;
    status: string;
    acknowledgement_required: boolean;
    published_at: string | null;
    acknowledgements_count: number;
    acknowledged_at: string | null;
};
type Tab = 'profile' | 'documents' | 'lifecycle' | 'assets' | 'policies' | 'setup';
/** Handles the date only operation for the WorkIntel client. */ const dateOnly = (v: string | null | undefined) => v ? v.slice(0, 10) : '—';
/** Handles the money operation for the WorkIntel client. */ const money = (amount: string | null, currency: string | null, period: string | null) => amount ? `${currency ?? ''} ${Number(amount).toLocaleString()}${period ? ` / ${period}` : ''}` : '—';
/** Handles the hris operation for the WorkIntel client. */ export default function Hris() {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const workspace = session?.user.workspaces.find(w => w.id === workspaceId);
    const canManage = hasPermission(workspace, 'hris.manage'), canDocs = hasPermission(workspace, 'hris.documents.manage'), canAssets = hasPermission(workspace, 'hris.assets.manage'), canPolicies = hasPermission(workspace, 'hris.policies.manage'), canLifecycle = hasPermission(workspace, 'hris.lifecycle.manage');
    const [tab, setTab] = useState<Tab>('profile'), [members, setMembers] = useState<Member[]>([]), [memberId, setMemberId] = useState<number | 0>(0), [profile, setProfile] = useState<Profile | null>(null), [documents, setDocuments] = useState<DocumentPayload | null>(null), [checklists, setChecklists] = useState<Checklist[]>([]), [templates, setTemplates] = useState<Template[]>([]), [assets, setAssets] = useState<Asset[]>([]), [policies, setPolicies] = useState<Policy[]>([]), [loading, setLoading] = useState(true), [error, setError] = useState(''), [saving, setSaving] = useState(false);
    const [modal, setModal] = useState<'contact' | 'dependent' | 'document' | 'contract' | 'checklist' | 'asset' | 'assignAsset' | 'policy' | 'field' | 'transition' | null>(null);
    const [form, setForm] = useState<any>({}), [documentFile, setDocumentFile] = useState<File | null>(null), [ackPolicy, setAckPolicy] = useState<Policy | null>(null), [ackName, setAckName] = useState('');
    /** Loads load members data required by the current view. */ const loadMembers = async () => { const p = await apiRequest<{
        data: Member[];
    }>('/api/v1/hris/members', { workspaceId, silent: true }); setMembers(p.data); setMemberId(id => id || p.data[0]?.id || 0); return p.data; };
    /** Loads load member data required by the current view. */ const loadMember = async (id: number) => { if (!id)
        return; const [p, d, c] = await Promise.all([apiRequest<Profile>(`/api/v1/hris/members/${id}`, { workspaceId, silent: true }), apiRequest<DocumentPayload>(`/api/v1/hris/members/${id}/documents`, { workspaceId, silent: true }).catch(() => null), apiRequest<{
            data: Checklist[];
        }>(`/api/v1/hris/members/${id}/checklists`, { workspaceId, silent: true })]); setProfile(p); setDocuments(d); setChecklists(c.data); };
    /** Loads load global data required by the current view. */ const loadGlobal = async () => { const [t, a, p] = await Promise.all([apiRequest<{
            data: Template[];
        }>('/api/v1/hris/lifecycle/templates', { workspaceId, silent: true }), apiRequest<{
            data: Asset[];
        }>('/api/v1/hris/assets', { workspaceId, silent: true }), apiRequest<{
            data: Policy[];
        }>('/api/v1/hris/policies', { workspaceId, silent: true })]); setTemplates(t.data); setAssets(a.data); setPolicies(p.data); };
    /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        const ms = await loadMembers();
        const id = memberId || ms[0]?.id || 0;
        if (id)
            await loadMember(id);
        await loadGlobal();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load HRIS.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    useEffect(() => { if (memberId)
        void loadMember(memberId).catch(e => setError(e instanceof Error ? e.message : 'Could not load employee HR profile.')); }, [memberId]);
    const selected = useMemo(() => members.find(m => m.id === memberId) ?? null, [members, memberId]);
    /** Handles the refresh member operation for the WorkIntel client. */ const refreshMember = async () => { if (memberId)
        await loadMember(memberId); await loadMembers(); };
    /** Handles the save json operation for the WorkIntel client. */ const saveJson = async (path: string, method: string, body: any, after?: () => Promise<void>) => { setSaving(true); setError(''); try {
        await apiRequest(path, { method, workspaceId, body: JSON.stringify(body) });
        setModal(null);
        if (after)
            await after();
        else
            await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Action failed.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the upload document operation for the WorkIntel client. */ const uploadDocument = async (e: FormEvent) => { e.preventDefault(); if (!memberId)
        return; const el = e.currentTarget as HTMLFormElement; const fd = new FormData(el); if (documentFile)
        fd.append('file', documentFile); setSaving(true); try {
        await apiRequest(`/api/v1/hris/members/${memberId}/documents`, { method: 'POST', workspaceId, body: fd });
        setModal(null);
        setDocumentFile(null);
        await refreshMember();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Document upload failed.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the download doc operation for the WorkIntel client. */ const downloadDoc = async (row: DocumentRow) => { setError(''); try {
        const res = await fetch(`/api/v1/hris/documents/${row.id}/download`, { credentials: 'same-origin', headers: { 'X-Workspace-Id': String(workspaceId), 'Accept': 'application/octet-stream' } });
        if (!res.ok)
            throw new Error('Could not download document.');
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = row.file_name;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not download document.');
    } };
    /** Opens a policy acknowledgement form without relying on a browser prompt. */ const acknowledge = (policy: Policy) => { setAckPolicy(policy); setAckName(''); };
    /** Submits a policy acknowledgement using the employee's typed legal name. */ const submitAcknowledgement = async (e: FormEvent) => { e.preventDefault(); if (!ackPolicy || !ackName.trim())
        return; setSaving(true); setError(''); try {
        await apiRequest(`/api/v1/hris/policies/${ackPolicy.id}/acknowledge`, { method: 'POST', workspaceId, body: JSON.stringify({ signed_name: ackName.trim() }) });
        setAckPolicy(null);
        setAckName('');
        await loadGlobal();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not acknowledge policy.');
    }
    finally {
        setSaving(false);
    } };
    if (loading && !profile)
        return <PageLoadingState />;
    const tabs: Array<{
        value: Tab;
        label: string;
    }> = [{ value: 'profile', label: 'Employee Profile' }, { value: 'documents', label: 'Documents & Contracts' }, { value: 'lifecycle', label: 'Lifecycle' }, { value: 'assets', label: 'Assets' }, { value: 'policies', label: 'Policies' }, ...(canManage ? [{ value: 'setup' as Tab, label: 'HRIS Setup' }] : [])];
    return <Page><PageHeader title={canManage ? 'HRIS' : 'My HR'} description={canManage ? 'Employee records, lifecycle, contracts, assets and policies' : 'Your employment information, documents, assets and policy acknowledgements'} actions={members.length > 1 ? <Select value={memberId} onChange={e => setMemberId(Number(e.target.value))}>{members.map(m => <Option key={m.id} value={m.id}>{m.name} · {m.job_title ?? 'Employee'}</Option>)}</Select> : undefined}/>{error && <Alert tone="danger">{error}</Alert>}<Box m="12px 0"><Tabs value={tab} onChange={setTab} tabs={tabs}/></Box>

 {tab === 'profile' && profile && <Stack gap={12}><Card><CardHeader title={profile.member.name} description={`${profile.member.job_title ?? 'Employee'} · ${profile.member.department ?? 'No department'}`} action={<Badge tone={profile.member.employment_stage === 'active' ? 'success' : profile.member.employment_stage === 'notice' ? 'warning' : 'neutral'}>{profile.member.employment_stage}</Badge>}/><CardBody><Grid columns="repeat(auto-fit,minmax(160px,1fr))" gap={10}><Info label="Employee ID" value={profile.member.employee_code ?? '—'}/><Info label="Employment" value={profile.member.employment_type}/><Info label="Joined" value={dateOnly(profile.member.joining_date)}/><Info label="Probation end" value={dateOnly(profile.member.probation_end_date)}/><Info label="Manager" value={profile.manager?.name ?? '—'}/><Info label="Status" value={profile.member.status}/></Grid>{canManage && <Box mt={12}><Button size="sm" variant="outline" onClick={() => { setForm({ employment_stage: profile.member.employment_stage, effective_date: new Date().toISOString().slice(0, 10), note: '' }); setModal('transition'); }}><UserCog size={13}/> Change Employment Stage</Button></Box>}</CardBody></Card>
 {profile.custom_fields.length > 0 && <Card><CardHeader title="Custom Employee Fields" description="Workspace-specific employee information"/><CardBody><Grid columns="repeat(auto-fit,minmax(200px,1fr))" gap={10}>{profile.custom_fields.map(f => <Info key={f.id} label={f.label} value={f.value || '—'}/>)}</Grid></CardBody></Card>}
 {profile.can_view_sensitive && <Grid columns="1fr 1fr" gap={12}><Card><CardHeader title="Emergency Contacts" action={<Button size="sm" variant="ghost" onClick={() => { setForm({ name: '', relationship: '', phone: '', email: '', is_primary: false }); setModal('contact'); }}><Plus size={13}/> Add</Button>}/><CardBody>{profile.emergency_contacts?.length ? <Stack gap={8}>{profile.emergency_contacts.map(c => <div key={c.id} className="hris-row"><div><strong>{c.name}</strong><small>{c.relationship} · {c.phone}</small></div>{c.is_primary && <Badge tone="accent">Primary</Badge>}</div>)}</Stack> : <Empty text="No emergency contacts."/>}</CardBody></Card><Card><CardHeader title="Dependents" action={<Button size="sm" variant="ghost" onClick={() => { setForm({ name: '', relationship: '', date_of_birth: '', benefits_eligible: false }); setModal('dependent'); }}><Plus size={13}/> Add</Button>}/><CardBody>{profile.dependents?.length ? <Stack gap={8}>{profile.dependents.map(d => <div key={d.id} className="hris-row"><div><strong>{d.name}</strong><small>{d.relationship} · {dateOnly(d.date_of_birth)}</small></div>{d.benefits_eligible && <Badge tone="success">Benefits</Badge>}</div>)}</Stack> : <Empty text="No dependents."/>}</CardBody></Card></Grid>}
 <Card><CardHeader title="Employment History" description="Immutable lifecycle and status timeline"/><CardBody>{profile.employment_history.length ? <div className="hris-timeline">{profile.employment_history.map(h => <div key={h.id} className="hris-timeline-item"><span /><div><strong>{h.event_type.replaceAll('_', ' ')}</strong><small>{dateOnly(h.effective_date)}{h.to_value ? ` · ${h.to_value}` : ''}</small>{h.note && <p>{h.note}</p>}</div></div>)}</div> : <Empty text="No employment history yet."/>}</CardBody></Card></Stack>}

 {tab === 'documents' && documents && <Stack gap={12}><Card><CardHeader title="Employee Documents" description="Private HR files with expiry tracking" action={<Inline gap={7}>{canDocs && <Button size="sm" variant="outline" onClick={() => { setForm({ name: 'Employment', category: 'employment' }); setModal('document'); }}><FolderPlus size={13}/> Upload</Button>}{canManage && <Button size="sm" variant="primary" onClick={() => { setForm({ title: 'Employment Agreement', contract_type: 'employment', effective_from: new Date().toISOString().slice(0, 10), salary_amount: '', salary_currency: 'USD', salary_period: 'monthly', document_id: '', activate: true }); setModal('contract'); }}><FileText size={13}/> New Contract</Button>}</Inline>}/><CardBody>{documents.documents.length ? <Stack gap={7}>{documents.documents.map(d => <div key={d.id} className="hris-row"><div><strong>{d.title}</strong><small>{d.document_type} · {d.file_name}{d.expires_on ? ` · expires ${dateOnly(d.expires_on)}` : ''}</small></div><Button size="sm" variant="ghost" onClick={() => void downloadDoc(d)}><Download size={13}/> Download</Button></div>)}</Stack> : <Empty text="No documents uploaded."/>}</CardBody></Card><Card><CardHeader title="Contract History" description="Versioned employment agreements; previous versions are retained"/><CardBody>{documents.contracts.length ? <Stack gap={7}>{documents.contracts.map(c => <div key={c.id} className="hris-row"><div><strong>v{c.version} · {c.title}</strong><small>{dateOnly(c.effective_from)} → {dateOnly(c.effective_to)} · {money(c.salary_amount, c.salary_currency, c.salary_period)}</small></div><Badge tone={c.status === 'active' ? 'success' : c.status === 'draft' ? 'warning' : 'neutral'}>{c.status}</Badge></div>)}</Stack> : <Empty text="No contract history."/>}</CardBody></Card></Stack>}

 {tab === 'lifecycle' && <Stack gap={12}><Card><CardHeader title="Employee Lifecycle" description="Onboarding, offboarding, probation, promotion and role-change checklists" action={canLifecycle ? <Button size="sm" variant="primary" onClick={() => { setForm({ template_id: templates[0]?.id ?? '', effective_date: new Date().toISOString().slice(0, 10) }); setModal('checklist'); }}><ClipboardCheck size={13}/> Start Checklist</Button> : undefined}/><CardBody>{checklists.length ? <Stack gap={10}>{checklists.map(c => { const done = c.items.filter(i => i.status === 'completed').length; return <div key={c.id} className="hris-checklist"><div className="hris-row"><div><strong>{c.name}</strong><small>{c.type} · effective {dateOnly(c.effective_date)} · {done}/{c.items.length} complete</small></div><Badge tone={c.status === 'completed' ? 'success' : 'warning'}>{c.status}</Badge></div><Stack gap={5} mt={8}>{c.items.map(i => <Pressable key={i.id} type="button" className={`hris-task${i.status === 'completed' ? ' is-done' : ''}`} onClick={() => i.status !== 'completed' && void saveJson(`/api/v1/hris/checklist-items/${i.id}`, 'PATCH', { status: 'completed' }, refreshMember)}><span>{i.status === 'completed' ? '✓' : '○'}</span><div><strong>{i.title}</strong><small>{i.owner_type} · due {dateOnly(i.due_date)}</small></div></Pressable>)}</Stack></div>; })}</Stack> : <Empty text="No lifecycle checklist started for this employee."/>}</CardBody></Card></Stack>}

 {tab === 'assets' && <Card><CardHeader title={canAssets ? 'Asset Inventory' : 'My Assigned Assets'} description="Equipment issue, assignment and return history" action={canAssets ? <Button size="sm" variant="primary" onClick={() => { setForm({ asset_tag: '', name: '', category: 'Laptop', serial_number: '', purchased_on: '', purchase_cost: '', currency: 'USD' }); setModal('asset'); }}><Plus size={13}/> Add Asset</Button> : undefined}/><CardBody>{assets.length ? <Grid columns="repeat(auto-fill,minmax(260px,1fr))" gap={10}>{assets.map(a => { const active = a.assignments?.find(x => !x.returned_on); return <div key={a.id} className="hris-asset"><div className="hris-asset-icon"><Laptop size={18}/></div><Box flex={1}><strong>{a.asset_tag} · {a.name}</strong><small>{a.category}{a.serial_number ? ` · ${a.serial_number}` : ''}</small><Inline mt={6} gap={6} align="center"><Badge tone={a.status === 'available' ? 'success' : a.status === 'assigned' ? 'accent' : 'neutral'}>{a.status}</Badge>{active?.member?.user && <small>{active.member.user.first_name} {active.member.user.last_name}</small>}</Inline></Box>{canAssets && a.status === 'available' && <Button size="sm" variant="ghost" onClick={() => { setForm({ asset_id: a.id, member_id: memberId, assigned_on: new Date().toISOString().slice(0, 10) }); setModal('assignAsset'); }}>Assign</Button>}</div>; })}</Grid> : <Empty text="No assets."/>}</CardBody></Card>}

 {tab === 'policies' && <Card><CardHeader title="Company Policies" description="Versioned policies with employee acknowledgement evidence" action={canPolicies ? <Button size="sm" variant="primary" onClick={() => { setForm({ policy_key: '', title: '', content: '', acknowledgement_required: true, publish: true }); setModal('policy'); }}><ShieldCheck size={13}/> New Policy</Button> : undefined}/><CardBody>{policies.length ? <Stack gap={9}>{policies.map(p => <div key={p.id} className="hris-policy"><div><Inline gap={7} align="center"><strong>{p.title}</strong><Badge tone={p.status === 'published' ? 'success' : 'neutral'}>v{p.version} {p.status}</Badge></Inline><p>{p.content}</p><small>{p.acknowledgements_count} acknowledgement(s)</small></div>{p.status === 'published' && p.acknowledgement_required && !p.acknowledged_at && <Button size="sm" variant="primary" onClick={() => void acknowledge(p)}><BadgeCheck size={13}/> Acknowledge</Button>}{p.acknowledged_at && <Badge tone="success">Acknowledged</Badge>}</div>)}</Stack> : <Empty text="No policies published."/>}</CardBody></Card>}

 {tab === 'setup' && canManage && <Grid columns="1fr 1fr" gap={12}><Card><CardHeader title="Custom Employee Fields" description="Add company-specific fields without schema changes" action={<Button size="sm" variant="primary" onClick={() => { setForm({ label: '', key: '', field_type: 'text', visibility: 'hr', required: false }); setModal('field'); }}><Plus size={13}/> Field</Button>}/><CardBody><p className="ui-card-description">Create self-service, manager-visible or HR-only employee fields. Values appear on the employee HR profile.</p></CardBody></Card><Card><CardHeader title="Lifecycle Templates" description="Reusable employee onboarding/offboarding flows"/><CardBody><Stack gap={7}>{templates.map(t => <div key={t.id} className="hris-row"><div><strong>{t.name}</strong><small>{t.type} · {t.items.length} task(s)</small></div><Badge>{t.status}</Badge></div>)}</Stack></CardBody></Card></Grid>}

 <HrisFormDialog open={modal === 'contact'} title="Add emergency contact" saving={saving} onClose={() => setModal(null)} onSubmit={() => void saveJson(`/api/v1/hris/members/${memberId}/emergency-contacts`, 'POST', form, refreshMember)}><Field label="Name"><Input value={form.name ?? ''} onChange={e => setForm({ ...form, name: e.target.value })}/></Field><Field label="Relationship"><Input value={form.relationship ?? ''} onChange={e => setForm({ ...form, relationship: e.target.value })}/></Field><Field label="Phone"><Input value={form.phone ?? ''} onChange={e => setForm({ ...form, phone: e.target.value })}/></Field><Field label="Email"><Input type="email" value={form.email ?? ''} onChange={e => setForm({ ...form, email: e.target.value })}/></Field></HrisFormDialog>
 <HrisFormDialog open={modal === 'dependent'} title="Add dependent" saving={saving} onClose={() => setModal(null)} onSubmit={() => void saveJson(`/api/v1/hris/members/${memberId}/dependents`, 'POST', form, refreshMember)}><Field label="Name"><Input value={form.name ?? ''} onChange={e => setForm({ ...form, name: e.target.value })}/></Field><Field label="Relationship"><Input value={form.relationship ?? ''} onChange={e => setForm({ ...form, relationship: e.target.value })}/></Field><Field label="Date of birth"><Input type="date" value={form.date_of_birth ?? ''} onChange={e => setForm({ ...form, date_of_birth: e.target.value })}/></Field></HrisFormDialog>
 <FormDialog open={modal === 'document'} onClose={() => setModal(null)} title="Upload employee document" description="Add a private employee file to the managed HR document record." formId="hris-doc-upload" onSubmit={uploadDocument} submitLabel="Upload" loading={saving}><Field label="Title"><Input name="title" required/></Field><Field label="Type"><Select name="document_type"><Option value="general">General</Option><Option value="identity">Identity</Option><Option value="employment">Employment</Option><Option value="certificate">Certificate</Option><Option value="visa">Visa / Work Permit</Option></Select></Field><MediaFileField workspaceId={workspaceId} label="File" valueLabel={documentFile?.name} onFiles={files => setDocumentFile(files[0] ?? null)}/><Field label="Expires on"><Input name="expires_on" type="date"/></Field></FormDialog>
 <HrisFormDialog open={modal === 'contract'} title="Create contract version" saving={saving} onClose={() => setModal(null)} onSubmit={() => void saveJson(`/api/v1/hris/members/${memberId}/contracts`, 'POST', form, refreshMember)}><Field label="Title"><Input value={form.title ?? ''} onChange={e => setForm({ ...form, title: e.target.value })}/></Field><Field label="Effective from"><Input type="date" value={form.effective_from ?? ''} onChange={e => setForm({ ...form, effective_from: e.target.value })}/></Field><Grid columns="1fr 1fr" gap={8}><Field label="Salary"><Input type="number" value={form.salary_amount ?? ''} onChange={e => setForm({ ...form, salary_amount: e.target.value })}/></Field><Field label="Period"><Select value={form.salary_period ?? 'monthly'} onChange={e => setForm({ ...form, salary_period: e.target.value })}><Option value="hourly">Hourly</Option><Option value="daily">Daily</Option><Option value="monthly">Monthly</Option><Option value="yearly">Yearly</Option><Option value="project">Project</Option></Select></Field></Grid></HrisFormDialog>
 <HrisFormDialog open={modal === 'checklist'} title="Start lifecycle checklist" saving={saving} onClose={() => setModal(null)} onSubmit={() => void saveJson(`/api/v1/hris/members/${memberId}/checklists`, 'POST', form, refreshMember)}><Field label="Template"><Select value={form.template_id ?? ''} onChange={e => setForm({ ...form, template_id: Number(e.target.value) })}>{templates.map(t => <Option key={t.id} value={t.id}>{t.name}</Option>)}</Select></Field><Field label="Effective date"><Input type="date" value={form.effective_date ?? ''} onChange={e => setForm({ ...form, effective_date: e.target.value })}/></Field></HrisFormDialog>
 <HrisFormDialog open={modal === 'asset'} title="Add company asset" saving={saving} onClose={() => setModal(null)} onSubmit={() => void saveJson('/api/v1/hris/assets', 'POST', form, loadGlobal)}><Field label="Asset tag"><Input value={form.asset_tag ?? ''} onChange={e => setForm({ ...form, asset_tag: e.target.value })}/></Field><Field label="Name"><Input value={form.name ?? ''} onChange={e => setForm({ ...form, name: e.target.value })}/></Field><Field label="Category"><Input value={form.category ?? ''} onChange={e => setForm({ ...form, category: e.target.value })}/></Field><Field label="Serial number"><Input value={form.serial_number ?? ''} onChange={e => setForm({ ...form, serial_number: e.target.value })}/></Field></HrisFormDialog>
 <HrisFormDialog open={modal === 'assignAsset'} title="Assign asset" saving={saving} onClose={() => setModal(null)} onSubmit={() => void saveJson(`/api/v1/hris/assets/${form.asset_id}/assign`, 'POST', form, loadGlobal)}><Field label="Employee"><Select value={form.member_id ?? memberId} onChange={e => setForm({ ...form, member_id: Number(e.target.value) })}>{members.map(m => <Option key={m.id} value={m.id}>{m.name}</Option>)}</Select></Field><Field label="Assigned on"><Input type="date" value={form.assigned_on ?? ''} onChange={e => setForm({ ...form, assigned_on: e.target.value })}/></Field></HrisFormDialog>
 <HrisFormDialog open={modal === 'policy'} title="Create policy version" saving={saving} onClose={() => setModal(null)} onSubmit={() => void saveJson('/api/v1/hris/policies', 'POST', form, loadGlobal)}><Field label="Policy key"><Input placeholder="remote-work" value={form.policy_key ?? ''} onChange={e => setForm({ ...form, policy_key: e.target.value })}/></Field><Field label="Title"><Input value={form.title ?? ''} onChange={e => setForm({ ...form, title: e.target.value })}/></Field><Field label="Policy content"><Textarea rows={8} value={form.content ?? ''} onChange={e => setForm({ ...form, content: e.target.value })}/></Field></HrisFormDialog>
 <HrisFormDialog open={modal === 'field'} title="Create custom employee field" saving={saving} onClose={() => setModal(null)} onSubmit={() => void saveJson('/api/v1/hris/custom-fields', 'POST', form, load)}><Field label="Label"><Input value={form.label ?? ''} onChange={e => setForm({ ...form, label: e.target.value })}/></Field><Field label="Type"><Select value={form.field_type ?? 'text'} onChange={e => setForm({ ...form, field_type: e.target.value })}><Option value="text">Text</Option><Option value="textarea">Long text</Option><Option value="number">Number</Option><Option value="date">Date</Option><Option value="select">Select</Option><Option value="boolean">Yes / No</Option></Select></Field><Field label="Visibility"><Select value={form.visibility ?? 'hr'} onChange={e => setForm({ ...form, visibility: e.target.value })}><Option value="self">Employee self-service</Option><Option value="team">Employee + manager</Option><Option value="hr">HR only</Option></Select></Field></HrisFormDialog>
 <FormDialog open={Boolean(ackPolicy)} onClose={() => !saving && setAckPolicy(null)} title="Acknowledge policy" description={ackPolicy ? `Confirm that you have read ${ackPolicy.title}. Your typed name is stored as acknowledgement evidence.` : ''} formId="policy-acknowledgement-form" onSubmit={submitAcknowledgement} submitLabel="Acknowledge" loading={saving}><Field label="Full legal name" hint="Type your name exactly as you want it recorded in the acknowledgement evidence."><Input value={ackName} onChange={e => setAckName(e.target.value)} autoComplete="name" required/></Field></FormDialog>
 <HrisFormDialog open={modal === 'transition'} title="Change employment stage" saving={saving} onClose={() => setModal(null)} onSubmit={() => void saveJson(`/api/v1/hris/members/${memberId}/employment-stage`, 'PATCH', form, refreshMember)}><Field label="Stage"><Select value={form.employment_stage ?? 'active'} onChange={e => setForm({ ...form, employment_stage: e.target.value })}><Option value="preboarding">Preboarding</Option><Option value="onboarding">Onboarding</Option><Option value="probation">Probation</Option><Option value="active">Active</Option><Option value="leave">Leave</Option><Option value="notice">Notice</Option><Option value="terminated">Terminated</Option><Option value="alumni">Alumni</Option></Select></Field><Field label="Effective date"><Input type="date" value={form.effective_date ?? ''} onChange={e => setForm({ ...form, effective_date: e.target.value })}/></Field><Field label="Note"><Textarea value={form.note ?? ''} onChange={e => setForm({ ...form, note: e.target.value })}/></Field></HrisFormDialog>
 </Page>;
}
/** Handles the info operation for the WorkIntel client. */ function Info({ label, value }: {
    label: string;
    value: string;
}) { return <div className="hris-info"><small>{label}</small><strong>{value}</strong></div>; }
/** Handles the empty operation for the WorkIntel client. */ function Empty({ text }: {
    text: string;
}) { return <Box p="18px 4px" textAlign="center" color="var(--text-3)" size={11}>{text}</Box>; }
/** Renders the standard HRIS form-dialog wrapper for lifecycle save workflows. */ function HrisFormDialog({ open, title, saving, onClose, onSubmit, children }: {
    open: boolean;
    title: string;
    saving: boolean;
    onClose: () => void;
    onSubmit: () => void;
    children: React.ReactNode;
}) { return <FormDialog open={open} onClose={onClose} title={title} formId={`hris-${title.toLowerCase().replace(/[^a-z0-9]+/g, '-')}-form`} onSubmit={e => { e.preventDefault(); onSubmit(); }} submitLabel="Save" loading={saving}><Stack gap={10}>{children}</Stack></FormDialog>; }
