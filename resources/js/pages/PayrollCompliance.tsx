import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Download, FileCheck2, Plus, ReceiptText, RefreshCcw, Scale, UserRoundCog } from 'lucide-react';
import { apiDownload, apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { ErrorState, EmptyState, Alert, Badge, Button, Card, CardBody, CardHeader, Field, Input, FormDialog, Page, PageHeader, Select, StatCard, Switch, Tabs, Textarea, Grid, Inline, Stack, SettingRow, Option, DataGrid, Text, type DataGridColumn } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
type Member = {
    id: number;
    employee_code: string | null;
    employment_type: string;
    user: {
        first_name: string;
        last_name: string;
        email: string;
    };
};
type Rule = {
    id: number;
    code: string;
    name: string;
    category: string;
    calculation_type: string;
    basis: string;
    rate_percent: string | null;
    employer_rate_percent: string | null;
    fixed_amount: string | null;
    active: boolean;
};
type Pack = {
    id: number;
    name: string;
    country_code: string | null;
    version: string;
    currency: string;
    effective_from: string;
    effective_to: string | null;
    status: string;
    replace_default_tax: boolean;
    disclaimer: string | null;
    rules: Rule[];
};
type Assignment = {
    id: number;
    member_id: number;
    worker_classification: string;
    residency_status: string | null;
    effective_from: string;
    status: string;
    member?: Member;
    pack?: Pack | null;
};
type Benefit = {
    id: number;
    member_id: number;
    code: string;
    name: string;
    type: string;
    employee_amount: string;
    employer_amount: string;
    frequency: string;
    status: string;
    member?: Member;
};
type Retro = {
    id: number;
    member_id: number;
    amount: string;
    currency: string;
    source_period_start: string;
    source_period_end: string;
    reason: string;
    status: string;
    payroll_run_id: number | null;
};
type Settlement = {
    id: number;
    member_id: number;
    currency: string;
    termination_date: string;
    service_years: string;
    base_amount: string;
    leave_payout: string;
    other_earnings: string;
    deductions: string;
    total_amount: string;
    status: string;
};
type PayrollRun = {
    id: number;
    name: string;
    period_start: string;
    period_end: string;
    status: string;
    run_type: string;
    currency: string;
};
type ExportRow = {
    id: number;
    uuid: string;
    payroll_run_id: number;
    provider: string;
    format: string;
    file_name: string;
    sha256: string;
    size_bytes: number;
    created_at: string;
    run?: PayrollRun;
};
type Payload = {
    packs: Pack[];
    assignments: Assignment[];
    benefits: Benefit[];
    retro: Retro[];
    settlements: Settlement[];
    exports: ExportRow[];
    runs: PayrollRun[];
    members: Member[];
    can_manage: boolean;
};
type Tab = 'packs' | 'members' | 'adjustments' | 'exports';
/** Handles the person operation for the WorkIntel client. */ const person = (m?: Member) => m ? `${m.user.first_name} ${m.user.last_name}` : 'Unknown';
/** Handles the tone operation for the WorkIntel client. */ const tone = (status: string): 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'accent' => status === 'active' || status === 'approved' || status === 'applied' || status === 'paid' ? 'success' : status === 'draft' || status === 'pending' || status === 'calculated' || status === 'review' ? 'warning' : status === 'retired' || status === 'canceled' || status === 'rejected' ? 'danger' : 'neutral';
/** Handles the payroll compliance operation for the WorkIntel client. */ export default function PayrollCompliance() {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [data, setData] = useState<Payload | null>(null), [tab, setTab] = useState<Tab>('packs'), [loading, setLoading] = useState(true), [saving, setSaving] = useState(false), [error, setError] = useState('');
    const [packOpen, setPackOpen] = useState(false), [assignOpen, setAssignOpen] = useState(false), [benefitOpen, setBenefitOpen] = useState(false), [retroOpen, setRetroOpen] = useState(false), [terminationOpen, setTerminationOpen] = useState(false), [exportOpen, setExportOpen] = useState(false);
    const [pack, setPack] = useState({ name: '', country_code: '', version: '1.0', currency: 'USD', effective_from: new Date().toISOString().slice(0, 10), status: 'draft', replace_default_tax: false, disclaimer: 'Configured payroll rules require local legal/payroll review before production use.' });
    const [assignment, setAssignment] = useState({ member_id: '', payroll_compliance_pack_id: '', worker_classification: 'employee', residency_status: 'resident', effective_from: new Date().toISOString().slice(0, 10) });
    const [benefit, setBenefit] = useState({ member_id: '', code: 'ALLOWANCE', name: 'Monthly allowance', type: 'allowance', employee_amount: '0', employer_amount: '0', frequency: 'monthly', effective_from: new Date().toISOString().slice(0, 10), taxable: false, cash: true });
    const [retro, setRetro] = useState({ member_id: '', amount: '', source_period_start: '', source_period_end: '', reason: '' });
    const [termination, setTermination] = useState({ member_id: '', termination_date: new Date().toISOString().slice(0, 10), payroll_compliance_pack_id: '', days_per_service_year: '', leave_payout: '0', other_earnings: '0', deductions: '0' });
    const [exportForm, setExportForm] = useState({ payroll_run_id: '', provider: 'accounting_generic', format: 'csv' });
    /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        const p = await apiRequest<Payload>('/api/v1/payroll-compliance', { workspaceId });
        setData(p);
        const currency = p.packs[0]?.currency || p.runs[0]?.currency || 'USD';
        setPack(v => ({ ...v, currency }));
        if (!assignment.member_id && p.members[0])
            setAssignment(v => ({ ...v, member_id: String(p.members[0].id), payroll_compliance_pack_id: p.packs[0] ? String(p.packs[0].id) : '' }));
        if (!benefit.member_id && p.members[0])
            setBenefit(v => ({ ...v, member_id: String(p.members[0].id) }));
        if (!retro.member_id && p.members[0])
            setRetro(v => ({ ...v, member_id: String(p.members[0].id) }));
        if (!termination.member_id && p.members[0])
            setTermination(v => ({ ...v, member_id: String(p.members[0].id), payroll_compliance_pack_id: p.packs[0] ? String(p.packs[0].id) : '' }));
        if (!exportForm.payroll_run_id && p.runs[0])
            setExportForm(v => ({ ...v, payroll_run_id: String(p.runs[0].id) }));
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load payroll compliance.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    const activePacks = useMemo(() => data?.packs.filter(p => p.status === 'active').length ?? 0, [data]);
    const packColumns: DataGridColumn<Pack>[] = [
        { id: 'name', header: 'Pack', searchValue: r => `${r.name} ${r.disclaimer ?? ''}`, sortValue: r => r.name, cell: r => <Stack gap={2}><Text weight={650}>{r.name}</Text>{r.disclaimer && <Text size={10.5} color="var(--text-3)">{r.disclaimer}</Text>}</Stack> },
        { id: 'jurisdiction', header: 'Jurisdiction', filterValue: r => r.country_code || 'Generic', cell: r => r.country_code || 'Generic' },
        { id: 'version', header: 'Version', sortValue: r => r.version, cell: r => r.version },
        { id: 'effective', header: 'Effective', sortValue: r => r.effective_from, filterValue: r => r.effective_from, filter: { type: 'dateRange', label: 'Effective' }, cell: r => r.effective_from.slice(0, 10) },
        { id: 'rules', header: 'Rules', sortValue: r => r.rules.length, cell: r => r.rules.length },
        { id: 'status', header: 'Status', filterValue: r => r.status, filter: { type: 'select', label: 'Status', options: [{ value: 'active', label: 'Active' }, { value: 'draft', label: 'Draft' }, { value: 'retired', label: 'Retired' }] }, cell: r => <Badge tone={tone(r.status)}>{r.status}</Badge> },
    ];
    const assignmentColumns: DataGridColumn<Assignment>[] = [
        { id: 'employee', header: 'Employee', searchValue: r => person(r.member), sortValue: r => person(r.member), cell: r => person(r.member) },
        { id: 'classification', header: 'Classification', filterValue: r => r.worker_classification, cell: r => r.worker_classification },
        { id: 'pack', header: 'Pack', searchValue: r => r.pack?.name ?? '', cell: r => r.pack?.name || 'Default payroll' },
        { id: 'residency', header: 'Residency', filterValue: r => r.residency_status ?? '', cell: r => r.residency_status || '—' },
        { id: 'effective', header: 'Effective', sortValue: r => r.effective_from, filterValue: r => r.effective_from, filter: { type: 'dateRange', label: 'Effective' }, cell: r => r.effective_from.slice(0, 10) },
        { id: 'status', header: 'Status', filterValue: r => r.status, cell: r => <Badge tone={tone(r.status)}>{r.status}</Badge> },
    ];
    const benefitColumns: DataGridColumn<Benefit>[] = [
        { id: 'employee', header: 'Employee', searchValue: r => person(r.member), cell: r => person(r.member) },
        { id: 'item', header: 'Item', searchValue: r => `${r.name} ${r.code}`, sortValue: r => r.name, cell: r => <Stack gap={2}><Text weight={650}>{r.name}</Text><Text size={10.5} color="var(--text-3)">{r.code}</Text></Stack> },
        { id: 'type', header: 'Type', filterValue: r => r.type, cell: r => r.type },
        { id: 'employee_amount', header: 'Employee', sortValue: r => Number(r.employee_amount), cell: r => r.employee_amount },
        { id: 'employer_amount', header: 'Employer', sortValue: r => Number(r.employer_amount), cell: r => r.employer_amount },
        { id: 'frequency', header: 'Frequency', filterValue: r => r.frequency, cell: r => r.frequency },
    ];
    const exportColumns: DataGridColumn<ExportRow>[] = [
        { id: 'run', header: 'Run', searchValue: r => r.run?.name ?? `Run ${r.payroll_run_id}`, cell: r => r.run?.name || `Run #${r.payroll_run_id}` },
        { id: 'provider', header: 'Provider', filterValue: r => r.provider, cell: r => r.provider },
        { id: 'format', header: 'Format', filterValue: r => r.format, cell: r => r.format.toUpperCase() },
        { id: 'checksum', header: 'Checksum', searchValue: r => r.sha256, defaultHidden: true, cell: r => <code>{r.sha256.slice(0, 12)}…</code> },
        { id: 'created', header: 'Created', sortValue: r => r.created_at, filterValue: r => r.created_at, filter: { type: 'dateRange', label: 'Created' }, cell: r => new Date(r.created_at).toLocaleString() },
        { id: 'actions', header: '', hideable: false, cell: r => <Button size="sm" variant="ghost" onClick={() => void download(r)}><Download size={12}/>Download</Button> },
    ];
    /** Handles the save pack operation for the WorkIntel client. */ const savePack = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest('/api/v1/payroll-compliance/packs', { method: 'POST', workspaceId, body: JSON.stringify({ ...pack, country_code: pack.country_code || null, rules: [] }) });
        setPackOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create compliance pack.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the save assignment operation for the WorkIntel client. */ const saveAssignment = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest(`/api/v1/payroll-compliance/members/${assignment.member_id}/assignment`, { method: 'POST', workspaceId, body: JSON.stringify({ ...assignment, payroll_compliance_pack_id: assignment.payroll_compliance_pack_id ? Number(assignment.payroll_compliance_pack_id) : null }) });
        setAssignOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not assign payroll pack.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the save benefit operation for the WorkIntel client. */ const saveBenefit = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest(`/api/v1/payroll-compliance/members/${benefit.member_id}/benefits`, { method: 'POST', workspaceId, body: JSON.stringify({ ...benefit, employee_amount: Number(benefit.employee_amount || 0), employer_amount: Number(benefit.employer_amount || 0) }) });
        setBenefitOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not save benefit.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the save retro operation for the WorkIntel client. */ const saveRetro = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        const currency = data?.runs[0]?.currency || data?.packs[0]?.currency || 'USD';
        await apiRequest('/api/v1/payroll-compliance/retro', { method: 'POST', workspaceId, body: JSON.stringify({ ...retro, member_id: Number(retro.member_id), amount: Number(retro.amount), currency }) });
        setRetroOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create retro pay adjustment.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the preview termination operation for the WorkIntel client. */ const previewTermination = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest('/api/v1/payroll-compliance/termination/preview', { method: 'POST', workspaceId, body: JSON.stringify({ ...termination, member_id: Number(termination.member_id), payroll_compliance_pack_id: termination.payroll_compliance_pack_id ? Number(termination.payroll_compliance_pack_id) : null, days_per_service_year: termination.days_per_service_year ? Number(termination.days_per_service_year) : null, leave_payout: Number(termination.leave_payout || 0), other_earnings: Number(termination.other_earnings || 0), deductions: Number(termination.deductions || 0) }) });
        setTerminationOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not calculate termination settlement.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the approve settlement operation for the WorkIntel client. */ const approveSettlement = async (id: number) => { setSaving(true); try {
        await apiRequest(`/api/v1/payroll-compliance/termination/${id}/approve`, { method: 'POST', workspaceId });
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not approve settlement.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the export run operation for the WorkIntel client. */ const exportRun = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest(`/api/v1/payroll-compliance/runs/${exportForm.payroll_run_id}/exports`, { method: 'POST', workspaceId, body: JSON.stringify({ provider: exportForm.provider, format: exportForm.format }) });
        setExportOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create payroll export.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the download operation for the WorkIntel client. */ const download = async (row: ExportRow) => { try {
        const result = await apiDownload(`/api/v1/payroll-compliance/exports/${row.id}/download`, workspaceId);
        const url = URL.createObjectURL(result.blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = result.filename || row.file_name;
        a.click();
        URL.revokeObjectURL(url);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not download export.');
    } };
    if (loading && !data)
        return <PageLoadingState />;
    if (!data)
        return <Page><ErrorState title="Payroll compliance unavailable" text={error || 'Payroll compliance data could not be loaded.'} retry={load}/></Page>;
    return <Page><PageHeader title="Payroll Compliance" description="Effective-dated compliance packs, statutory snapshots, benefits, contractors, retro/off-cycle support and payroll exports" actions={data.can_manage ? <Button size="sm" variant="primary" onClick={() => setPackOpen(true)}><Plus size={13}/>Compliance Pack</Button> : undefined}/>{error && <Alert tone="danger" mb={12}>{error}</Alert>}
 <Grid columns="repeat(4,minmax(0,1fr))" gap={10} mb={12}><StatCard label="Active packs" value={activePacks}/><StatCard label="Member assignments" value={data.assignments.length}/><StatCard label="Benefits & allowances" value={data.benefits.filter(b => b.status === 'active').length}/><StatCard label="Pending retro/settlement" value={data.retro.filter(r => r.status === 'pending').length + data.settlements.filter(s => s.status === 'draft').length}/></Grid>
 <Tabs value={tab} onChange={setTab} tabs={[{ value: 'packs', label: 'Compliance Packs' }, { value: 'members', label: 'Members & Benefits' }, { value: 'adjustments', label: 'Retro & Termination' }, { value: 'exports', label: 'Exports' }]}/>
 {tab === 'packs' && <DataGrid rows={data.packs} columns={packColumns} rowKey={row => row.id} persistKey="payroll-compliance.packs" defaultSort={{ id: 'effective', direction: 'desc' }} empty={<EmptyState title="No compliance packs yet." text="Create a reviewed compliance pack to version payroll rules."/>}/>}
 {tab === 'members' && <Stack gap={12} mt={12}><DataGrid rows={data.assignments} columns={assignmentColumns} rowKey={row => row.id} persistKey="payroll-compliance.assignments" toolbar={data.can_manage ? <Button size="sm" onClick={() => setAssignOpen(true)}><UserRoundCog size={13}/>Assign Pack</Button> : undefined} empty={<EmptyState title="No compliance assignments yet." text="Assign a pack when a member needs jurisdiction-specific payroll rules."/>}/><DataGrid rows={data.benefits} columns={benefitColumns} rowKey={row => row.id} persistKey="payroll-compliance.benefits" toolbar={data.can_manage ? <Button size="sm" onClick={() => setBenefitOpen(true)}><Plus size={13}/>Benefit</Button> : undefined} empty={<EmptyState title="No benefits or allowances yet."/>}/></Stack>}
 {tab === 'adjustments' && <Grid columns="1fr 1fr" gap={12} mt={12}><Card><CardHeader title="Retro pay" description="Correction earnings are applied once to an unlocked payroll run." action={data.can_manage ? <Button size="sm" onClick={() => setRetroOpen(true)}><RefreshCcw size={13}/>Retro Pay</Button> : undefined}/><CardBody>{data.retro.map(r => <div className="schedule-list-row" key={r.id}><div><strong>{data.members.find(m => m.id === r.member_id) ? person(data.members.find(m => m.id === r.member_id)) : r.member_id} · {r.currency} {r.amount}</strong><small>{r.source_period_start.slice(0, 10)} → {r.source_period_end.slice(0, 10)} · {r.reason}</small></div><Badge tone={tone(r.status)}>{r.status}</Badge></div>)}</CardBody></Card><Card><CardHeader title="Termination settlements" description="Configurable estimate with immutable calculation snapshot; legal review is required before approval." action={data.can_manage ? <Button size="sm" onClick={() => setTerminationOpen(true)}><Scale size={13}/>Preview</Button> : undefined}/><CardBody>{data.settlements.map(s => <div className="schedule-list-row" key={s.id}><div><strong>{data.members.find(m => m.id === s.member_id) ? person(data.members.find(m => m.id === s.member_id)) : s.member_id} · {s.currency} {s.total_amount}</strong><small>{s.termination_date.slice(0, 10)} · {s.service_years} years service</small></div><Inline gap={6} align="center"><Badge tone={tone(s.status)}>{s.status}</Badge>{data.can_manage && s.status === 'draft' && <Button size="sm" onClick={() => void approveSettlement(s.id)}>Approve</Button>}</Inline></div>)}</CardBody></Card></Grid>}
 {tab === 'exports' && <DataGrid rows={data.exports} columns={exportColumns} rowKey={row => row.id} persistKey="payroll-compliance.exports" defaultSort={{ id: 'created', direction: 'desc' }} toolbar={<Button size="sm" onClick={() => setExportOpen(true)} disabled={!data.runs.length}><FileCheck2 size={13}/>Create Export</Button>} empty={<EmptyState title="No payroll exports yet." text="Create a private checksum-verified snapshot for an approved payroll run."/>}/>}
 <FormDialog open={packOpen} onClose={() => setPackOpen(false)} title="Create compliance pack" description="Use a reviewed jurisdiction configuration. Rules can be added through the API/workflow after the pack is created." formId="pack-form" onSubmit={savePack} submitLabel="Create" loading={saving}><Field label="Name"><Input value={pack.name} onChange={e => setPack({ ...pack, name: e.target.value })} required/></Field><Grid columns="1fr 1fr" gap={8}><Field label="Country (ISO2)"><Input value={pack.country_code} onChange={e => setPack({ ...pack, country_code: e.target.value.toUpperCase() })} maxLength={2}/></Field><Field label="Version"><Input value={pack.version} onChange={e => setPack({ ...pack, version: e.target.value })}/></Field></Grid><Grid columns="1fr 1fr" gap={8}><Field label="Currency"><Input value={pack.currency} onChange={e => setPack({ ...pack, currency: e.target.value.toUpperCase() })} maxLength={3}/></Field><Field label="Effective from"><Input type="date" value={pack.effective_from} onChange={e => setPack({ ...pack, effective_from: e.target.value })}/></Field></Grid><Field label="Status"><Select value={pack.status} onChange={e => setPack({ ...pack, status: e.target.value })}><Option value="draft">Draft</Option><Option value="active">Active</Option></Select></Field><SettingRow title="Replace legacy default withholding" description="Enable only when this pack fully owns statutory withholding." control={<Switch checked={pack.replace_default_tax} onChange={v => setPack({ ...pack, replace_default_tax: v })} label="Replace legacy default withholding"/>}/><Field label="Compliance disclaimer"><Textarea value={pack.disclaimer} onChange={e => setPack({ ...pack, disclaimer: e.target.value })}/></Field></FormDialog>
 <FormDialog open={assignOpen} onClose={() => setAssignOpen(false)} title="Assign payroll compliance" formId="assign-form" onSubmit={saveAssignment} submitLabel="Assign" loading={saving}><Field label="Employee"><Select value={assignment.member_id} onChange={e => setAssignment({ ...assignment, member_id: e.target.value })}>{data.members.map(m => <Option key={m.id} value={m.id}>{person(m)}</Option>)}</Select></Field><Field label="Compliance pack"><Select value={assignment.payroll_compliance_pack_id} onChange={e => setAssignment({ ...assignment, payroll_compliance_pack_id: e.target.value })}><Option value="">Default payroll only</Option>{data.packs.map(p => <Option key={p.id} value={p.id}>{p.name} · {p.version}</Option>)}</Select></Field><Field label="Worker classification"><Select value={assignment.worker_classification} onChange={e => setAssignment({ ...assignment, worker_classification: e.target.value })}><Option value="employee">Employee</Option><Option value="contractor">Contractor</Option></Select></Field><Field label="Residency status"><Input value={assignment.residency_status} onChange={e => setAssignment({ ...assignment, residency_status: e.target.value })}/></Field><Field label="Effective from"><Input type="date" value={assignment.effective_from} onChange={e => setAssignment({ ...assignment, effective_from: e.target.value })}/></Field></FormDialog>
 <FormDialog open={benefitOpen} onClose={() => setBenefitOpen(false)} title="Add benefit / allowance" formId="benefit-form" onSubmit={saveBenefit} submitLabel="Save" loading={saving}><Field label="Employee"><Select value={benefit.member_id} onChange={e => setBenefit({ ...benefit, member_id: e.target.value })}>{data.members.map(m => <Option key={m.id} value={m.id}>{person(m)}</Option>)}</Select></Field><Grid columns="1fr 1fr" gap={8}><Field label="Code"><Input value={benefit.code} onChange={e => setBenefit({ ...benefit, code: e.target.value })}/></Field><Field label="Name"><Input value={benefit.name} onChange={e => setBenefit({ ...benefit, name: e.target.value })}/></Field></Grid><Field label="Type"><Select value={benefit.type} onChange={e => setBenefit({ ...benefit, type: e.target.value })}><Option value="allowance">Allowance</Option><Option value="benefit">Benefit</Option><Option value="deduction">Deduction</Option></Select></Field><Grid columns="1fr 1fr" gap={8}><Field label="Employee amount"><Input type="number" min="0" value={benefit.employee_amount} onChange={e => setBenefit({ ...benefit, employee_amount: e.target.value })}/></Field><Field label="Employer amount"><Input type="number" min="0" value={benefit.employer_amount} onChange={e => setBenefit({ ...benefit, employer_amount: e.target.value })}/></Field></Grid><Field label="Frequency"><Select value={benefit.frequency} onChange={e => setBenefit({ ...benefit, frequency: e.target.value })}><Option value="payroll">Every payroll</Option><Option value="monthly">Monthly</Option><Option value="annual">Annual</Option><Option value="one_time">One time</Option></Select></Field><Field label="Effective from"><Input type="date" value={benefit.effective_from} onChange={e => setBenefit({ ...benefit, effective_from: e.target.value })}/></Field></FormDialog>
 <FormDialog open={retroOpen} onClose={() => setRetroOpen(false)} title="Create retro pay" formId="retro-form" onSubmit={saveRetro} submitLabel="Create" loading={saving}><Field label="Employee"><Select value={retro.member_id} onChange={e => setRetro({ ...retro, member_id: e.target.value })}>{data.members.map(m => <Option key={m.id} value={m.id}>{person(m)}</Option>)}</Select></Field><Field label="Amount"><Input type="number" min="0.01" step="0.01" value={retro.amount} onChange={e => setRetro({ ...retro, amount: e.target.value })}/></Field><Grid columns="1fr 1fr" gap={8}><Field label="Source period start"><Input type="date" value={retro.source_period_start} onChange={e => setRetro({ ...retro, source_period_start: e.target.value })}/></Field><Field label="Source period end"><Input type="date" value={retro.source_period_end} onChange={e => setRetro({ ...retro, source_period_end: e.target.value })}/></Field></Grid><Field label="Reason"><Textarea value={retro.reason} onChange={e => setRetro({ ...retro, reason: e.target.value })}/></Field></FormDialog>
 <FormDialog open={terminationOpen} onClose={() => setTerminationOpen(false)} title="Termination settlement preview" description="This is a configurable payroll estimate, not a jurisdiction legal determination." formId="termination-form" onSubmit={previewTermination} submitLabel="Calculate" loading={saving}><Field label="Employee"><Select value={termination.member_id} onChange={e => setTermination({ ...termination, member_id: e.target.value })}>{data.members.map(m => <Option key={m.id} value={m.id}>{person(m)}</Option>)}</Select></Field><Field label="Termination date"><Input type="date" value={termination.termination_date} onChange={e => setTermination({ ...termination, termination_date: e.target.value })}/></Field><Field label="Compliance pack"><Select value={termination.payroll_compliance_pack_id} onChange={e => setTermination({ ...termination, payroll_compliance_pack_id: e.target.value })}><Option value="">No pack</Option>{data.packs.map(p => <Option key={p.id} value={p.id}>{p.name}</Option>)}</Select></Field><Field label="Days per service year"><Input type="number" min="0" max="365" value={termination.days_per_service_year} onChange={e => setTermination({ ...termination, days_per_service_year: e.target.value })}/></Field></FormDialog>
 <FormDialog open={exportOpen} onClose={() => setExportOpen(false)} title="Create payroll export" formId="export-form" onSubmit={exportRun} submitLabel="Create" loading={saving}><Field label="Payroll run"><Select value={exportForm.payroll_run_id} onChange={e => setExportForm({ ...exportForm, payroll_run_id: e.target.value })}>{data.runs.map(r => <Option key={r.id} value={r.id}>{r.name} · {r.status}</Option>)}</Select></Field><Field label="Provider"><Input value={exportForm.provider} onChange={e => setExportForm({ ...exportForm, provider: e.target.value })}/></Field><Field label="Format"><Select value={exportForm.format} onChange={e => setExportForm({ ...exportForm, format: e.target.value })}><Option value="csv">CSV</Option><Option value="json">JSON</Option></Select></Field></FormDialog>
 </Page>;
}
