import { FormEvent, useEffect, useMemo, useState } from 'react';
import { CalendarDays, Check, Pencil, Plus, Scale, Umbrella, X } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { BooleanField, DataGrid, ErrorState, FilterBar, FormDialog, Alert, Avatar, Badge, Button, Card, CardBody, CardHeader, Field, Input, Page, PageHeader, Progress, Segmented, Select, Textarea, Box, Grid, Inline, Stack, Text, Option, type DataGridColumn } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
type Policy = {
    id: number;
    accrual_method: 'annual' | 'monthly' | 'none';
    monthly_accrual_days: string;
    carryover_days: string;
    min_notice_days: number;
    max_consecutive_days: number | null;
    probation_months: number;
    allow_negative_balance: boolean;
    requires_approval: boolean;
    exclude_weekends: boolean;
    exclude_holidays: boolean;
};
type LeaveType = {
    id: number;
    name: string;
    code: string;
    is_paid: boolean;
    annual_allowance_days: string;
    policy: Policy | null;
};
type Person = {
    id: number;
    user: {
        first_name: string;
        last_name: string;
    };
};
type LeaveRequest = {
    id: number;
    member: Person;
    leave_type: LeaveType;
    start_date: string;
    end_date: string;
    days: string;
    reason: string | null;
    status: string;
};
type Balance = {
    leave_type_id: number;
    name: string;
    year: number;
    allowance: number;
    opening: number;
    carried: number;
    accrued: number;
    adjustment: number;
    used: number;
    remaining: number;
    policy: Policy;
};
type Payload = {
    requests: LeaveRequest[];
    leave_types: LeaveType[];
    people: Person[];
    balances: Balance[];
    balance_year: number;
    current_member_id: number;
    can_manage: boolean;
};
type LeaveForm = {
    member_id: string;
    leave_type_id: string;
    start_date: string;
    end_date: string;
    reason: string;
};
type PolicyForm = {
    name: string;
    code: string;
    is_paid: boolean;
    annual_allowance_days: string;
    accrual_method: 'annual' | 'monthly' | 'none';
    monthly_accrual_days: string;
    carryover_days: string;
    min_notice_days: string;
    max_consecutive_days: string;
    probation_months: string;
    allow_negative_balance: boolean;
    requires_approval: boolean;
    exclude_weekends: boolean;
    exclude_holidays: boolean;
};
const emptyPolicy: PolicyForm = { name: '', code: '', is_paid: true, annual_allowance_days: '20', accrual_method: 'annual', monthly_accrual_days: '0', carryover_days: '0', min_notice_days: '0', max_consecutive_days: '', probation_months: '0', allow_negative_balance: false, requires_approval: true, exclude_weekends: true, exclude_holidays: true };
const tone: Record<string, 'warning' | 'success' | 'danger' | 'neutral' | 'info'> = { pending: 'warning', approved: 'success', rejected: 'danger', cancelled: 'neutral' };
/** Handles the person name operation for the WorkIntel client. */ const personName = (person: Person) => `${person.user.first_name} ${person.user.last_name}`;
/** Handles the leave operation for the WorkIntel client. */ export default function Leave() {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [data, setData] = useState<Payload | null>(null);
    const [view, setView] = useState<'requests' | 'calendar'>('requests');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [requestModal, setRequestModal] = useState(false);
    const [policyModal, setPolicyModal] = useState(false);
    const [adjustModal, setAdjustModal] = useState(false);
    const [editingType, setEditingType] = useState<LeaveType | null>(null);
    const [form, setForm] = useState<LeaveForm>({ member_id: '', leave_type_id: '', start_date: '', end_date: '', reason: '' });
    const [policyForm, setPolicyForm] = useState<PolicyForm>(emptyPolicy);
    const [adjustForm, setAdjustForm] = useState({ member_id: '', leave_type_id: '', year: String(new Date().getFullYear()), days: '0' });
    /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        setData(await apiRequest<Payload>('/api/v1/leave', { workspaceId }));
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load leave.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Handles the open request operation for the WorkIntel client. */ const openRequest = () => { if (!data)
        return; setForm({ member_id: String(data.current_member_id), leave_type_id: data.leave_types[0] ? String(data.leave_types[0].id) : '', start_date: '', end_date: '', reason: '' }); setError(''); setRequestModal(true); };
    /** Handles the save request operation for the WorkIntel client. */ const saveRequest = async (e: FormEvent) => { e.preventDefault(); if (!workspaceId)
        return; setSaving(true); setError(''); try {
        await apiRequest('/api/v1/leave', { method: 'POST', workspaceId, body: JSON.stringify({ member_id: form.member_id ? Number(form.member_id) : undefined, leave_type_id: Number(form.leave_type_id), start_date: form.start_date, end_date: form.end_date, reason: form.reason || null }) });
        setRequestModal(false);
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not create leave request.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the review operation for the WorkIntel client. */ const review = async (request: LeaveRequest, status: 'approved' | 'rejected') => { if (!workspaceId)
        return; setSaving(true); setError(''); try {
        await apiRequest(`/api/v1/leave/${request.id}/review`, { method: 'PATCH', workspaceId, body: JSON.stringify({ status }) });
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not review leave request.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the open policy operation for the WorkIntel client. */ const openPolicy = (type?: LeaveType) => { setEditingType(type ?? null); const policy = type?.policy; setPolicyForm(type ? { name: type.name, code: type.code, is_paid: type.is_paid, annual_allowance_days: String(type.annual_allowance_days), accrual_method: policy?.accrual_method ?? 'annual', monthly_accrual_days: String(policy?.monthly_accrual_days ?? 0), carryover_days: String(policy?.carryover_days ?? 0), min_notice_days: String(policy?.min_notice_days ?? 0), max_consecutive_days: policy?.max_consecutive_days ? String(policy.max_consecutive_days) : '', probation_months: String(policy?.probation_months ?? 0), allow_negative_balance: policy?.allow_negative_balance ?? false, requires_approval: policy?.requires_approval ?? true, exclude_weekends: policy?.exclude_weekends ?? true, exclude_holidays: policy?.exclude_holidays ?? true } : emptyPolicy); setPolicyModal(true); };
    /** Handles the save policy operation for the WorkIntel client. */ const savePolicy = async (e: FormEvent) => { e.preventDefault(); if (!workspaceId)
        return; setSaving(true); setError(''); try {
        const body = { name: policyForm.name, code: policyForm.code.toUpperCase(), is_paid: policyForm.is_paid, annual_allowance_days: Number(policyForm.annual_allowance_days), policy: { accrual_method: policyForm.accrual_method, monthly_accrual_days: Number(policyForm.monthly_accrual_days || 0), carryover_days: Number(policyForm.carryover_days || 0), min_notice_days: Number(policyForm.min_notice_days || 0), max_consecutive_days: policyForm.max_consecutive_days ? Number(policyForm.max_consecutive_days) : null, probation_months: Number(policyForm.probation_months || 0), allow_negative_balance: policyForm.allow_negative_balance, requires_approval: policyForm.requires_approval, exclude_weekends: policyForm.exclude_weekends, exclude_holidays: policyForm.exclude_holidays } };
        await apiRequest(editingType ? `/api/v1/leave/types/${editingType.id}` : '/api/v1/leave/types', { method: editingType ? 'PUT' : 'POST', workspaceId, body: JSON.stringify(body) });
        setPolicyModal(false);
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not save leave policy.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the open adjust operation for the WorkIntel client. */ const openAdjust = () => { if (!data)
        return; setAdjustForm({ member_id: String(data.current_member_id), leave_type_id: data.leave_types[0] ? String(data.leave_types[0].id) : '', year: String(data.balance_year), days: '0' }); setAdjustModal(true); };
    /** Handles the adjust balance operation for the WorkIntel client. */ const adjustBalance = async (e: FormEvent) => { e.preventDefault(); if (!workspaceId)
        return; setSaving(true); setError(''); try {
        await apiRequest('/api/v1/leave/balances/adjust', { method: 'POST', workspaceId, body: JSON.stringify({ member_id: Number(adjustForm.member_id), leave_type_id: Number(adjustForm.leave_type_id), year: Number(adjustForm.year), days: Number(adjustForm.days) }) });
        setAdjustModal(false);
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not adjust leave balance.');
    }
    finally {
        setSaving(false);
    } };
    const pending = useMemo(() => data?.requests.filter(item => item.status === 'pending').length ?? 0, [data]);
    const requestColumns: DataGridColumn<LeaveRequest>[] = [
        { id: 'employee', header: 'Employee', sortValue: request => personName(request.member), searchValue: request => personName(request.member), cell: request => { const employee = personName(request.member); return <Inline align="center" gap={8}><Avatar name={employee} size="sm"/><span>{employee}</span></Inline>; } },
        { id: 'type', header: 'Leave type', sortValue: request => request.leave_type.name, filterValue: request => String(request.leave_type.id), filter: { type: 'select', label: 'Leave type', options: (data?.leave_types ?? []).map(type => ({ value: String(type.id), label: type.name })) }, cell: request => <Stack gap={2}><Text weight={550}>{request.leave_type.name}</Text><Text size={10.5} color="var(--text-3)">{request.leave_type.is_paid ? 'Paid' : 'Unpaid'}</Text></Stack> },
        { id: 'dates', header: 'Dates', sortValue: request => request.start_date, filterValue: request => request.start_date, filter: { type: 'dateRange', label: 'Start date' }, cell: request => <Inline gap={5} align="center"><CalendarDays size={13}/><Text color="var(--text-2)">{request.start_date.slice(0, 10)} → {request.end_date.slice(0, 10)}</Text></Inline> },
        { id: 'days', header: 'Days', sortValue: request => Number(request.days), cell: request => <Text className="stat-num">{Number(request.days)}</Text> },
        { id: 'status', header: 'Status', sortValue: request => request.status, filterValue: request => request.status, filter: { type: 'select', label: 'Status', options: Object.keys(tone).map(value => ({ value, label: value[0].toUpperCase() + value.slice(1) })) }, cell: request => <Badge tone={tone[request.status] ?? 'neutral'} dot>{request.status}</Badge> },
        { id: 'reason', header: 'Reason', searchValue: request => request.reason ?? '', cell: request => <Text color="var(--text-2)">{request.reason || '—'}</Text> },
        { id: 'actions', header: '', hideable: false, cell: request => data?.can_manage && request.status === 'pending' ? <Inline gap={4}><Button variant="ghost" size="sm" iconOnly aria-label="Approve" disabled={saving} onClick={() => void review(request, 'approved')}><Check size={14} color="var(--success)"/></Button><Button variant="ghost" size="sm" iconOnly aria-label="Reject" disabled={saving} onClick={() => void review(request, 'rejected')}><X size={14} color="var(--danger)"/></Button></Inline> : null },
    ];
    if (loading && !data)
        return <PageLoadingState />;
    if (!data)
        return <Page><ErrorState title="Leave data unavailable" text={error || 'Leave data could not be loaded.'} retry={load}/></Page>;
    return <Page><PageHeader title="Leave" description="Policy-aware requests, approvals, accruals, carryover and employee balances" actions={<>{data.can_manage && <Button variant="outline" size="sm" onClick={() => openPolicy()}><Plus size={14}/> Leave Policy</Button>}{data.can_manage && <Button variant="outline" size="sm" onClick={openAdjust}><Scale size={14}/> Adjust Balance</Button>}<Button variant="primary" size="sm" onClick={openRequest} disabled={!data.leave_types.length}><Plus size={14}/> New Leave Request</Button></>}/>{error && <Alert tone="danger">{error}</Alert>}<FilterBar primary={<Segmented value={view} onChange={setView} options={[{ value: 'requests', label: 'Requests' }, { value: 'calendar', label: 'Calendar' }]}/>} actions={<Inline gap={8}><Badge tone="warning">{pending} awaiting approval</Badge><Badge tone="info">{data.leave_types.length} policies</Badge></Inline>}/>
 {view === 'requests' ? <><Grid columns="minmax(0,1fr) 320px" gap={14}><DataGrid rows={data.requests} columns={requestColumns} rowKey={request => request.id} persistKey="leave.requests" searchPlaceholder="Search employee, leave type or reason…" defaultSort={{ id: 'dates', direction: 'desc' }} onRefresh={load} empty={<Text color="var(--text-3)">No leave requests are visible in this scope.</Text>} mobileCard={request => <Stack gap={6}><Inline justify="space-between" align="center"><strong>{personName(request.member)}</strong><Badge tone={tone[request.status] ?? 'neutral'}>{request.status}</Badge></Inline><Text size={11} color="var(--text-2)">{request.leave_type.name} · {request.start_date.slice(0, 10)} → {request.end_date.slice(0, 10)} · {Number(request.days)}d</Text></Stack>}/><Stack gap={12}><Card><CardHeader title={`My Leave Balances · ${data.balance_year}`}/><CardBody><Stack gap={13}>{data.balances.map(balance => { const total = Math.max(0, balance.opening + balance.carried + balance.accrued + balance.adjustment); return <div key={balance.leave_type_id}><Inline justify="space-between" mb={5}><Text size={12}>{balance.name}</Text><span className="stat-num ui-card-description">{balance.remaining.toFixed(1)} days left</span></Inline><Progress value={total ? Math.max(0, Math.min(100, balance.used / total * 100)) : 0}/><Box className="ui-card-description" mt={4}>Accrued {balance.accrued.toFixed(1)} · Carryover {balance.carried.toFixed(1)} · Adjust {balance.adjustment.toFixed(1)}</Box></div>; })}</Stack></CardBody></Card><Card><CardBody><Inline gap={10}><Umbrella size={18} color="var(--accent)"/><div><Box weight={600}>Working-day calculation</Box><div className="ui-card-description">Policies can exclude weekends and workspace holidays automatically.</div></div></Inline></CardBody></Card></Stack></Grid>
 <Card mt={14}><CardHeader title="Leave Policies" description="Accrual, notice, probation, carryover and approval rules"/><CardBody><Grid columns="repeat(auto-fill,minmax(260px,1fr))" gap={9}>{data.leave_types.map(type => { const policy = type.policy; return <Box key={type.id} p={11} border="1px solid var(--border)" radius={8}><Inline justify="space-between" gap={8}><div><Box weight={650} size={12}>{type.name} <span className="ui-card-description">({type.code})</span></Box><div className="ui-card-description">{type.is_paid ? 'Paid' : 'Unpaid'} · {type.annual_allowance_days} annual days</div></div>{data.can_manage && <Button variant="ghost" size="sm" iconOnly aria-label="Edit policy" onClick={() => openPolicy(type)}><Pencil size={13}/></Button>}</Inline>{policy && <Box className="ui-card-description" mt={8} lineHeight={1.6}>{policy.accrual_method} accrual · {policy.carryover_days} carryover · {policy.min_notice_days}d notice · {policy.requires_approval ? 'Approval required' : 'Auto approve'} · {policy.exclude_weekends ? 'Weekends excluded' : 'Weekends counted'} · {policy.exclude_holidays ? 'Holidays excluded' : 'Holidays counted'}</Box>}</Box>; })}</Grid></CardBody></Card></> : <Card><CardBody><Grid columns="repeat(7,minmax(110px,1fr))" gap={8}>{Array.from({ length: 35 }, (_, index) => { const base = new Date(); base.setDate(1); base.setDate(base.getDate() + index); const date = base.toISOString().slice(0, 10); const away = data.requests.filter(item => item.status === 'approved' && item.start_date.slice(0, 10) <= date && item.end_date.slice(0, 10) >= date); return <Box key={date} minHeight={90} p={8} border="1px solid var(--border)" radius={7} bg="var(--surface)"><Box className="ui-card-description" mb={6}>{base.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric' })}</Box>{away.slice(0, 3).map(item => <Box key={item.id} size={10} p="3px 5px" radius={5} bg="var(--info-dim)" color="var(--info)" mb={3} whiteSpace="nowrap" overflow="hidden" textOverflow="ellipsis">{item.member.user.first_name} · {item.leave_type.code}</Box>)}</Box>; })}</Grid></CardBody></Card>}
 <FormDialog open={requestModal} onClose={() => setRequestModal(false)} title="New leave request" description="Working days and available balance are validated against the selected policy." formId="leave-request-submit" onSubmit={saveRequest} submitLabel="Submit Request" loading={saving}>{data.can_manage && <Field label="Employee"><Select value={form.member_id} onChange={e => setForm({ ...form, member_id: e.target.value })}>{data.people.map(person => <Option key={person.id} value={person.id}>{personName(person)}</Option>)}</Select></Field>}<Field label="Leave type"><Select value={form.leave_type_id} onChange={e => setForm({ ...form, leave_type_id: e.target.value })}>{data.leave_types.map(type => <Option key={type.id} value={type.id}>{type.name}{type.is_paid ? ' · Paid' : ' · Unpaid'}</Option>)}</Select></Field><Grid columns="1fr 1fr" gap={10}><Field label="Start date"><Input type="date" value={form.start_date} onChange={e => setForm({ ...form, start_date: e.target.value })} required/></Field><Field label="End date"><Input type="date" min={form.start_date || undefined} value={form.end_date} onChange={e => setForm({ ...form, end_date: e.target.value })} required/></Field></Grid><Field label="Reason"><Textarea value={form.reason} onChange={e => setForm({ ...form, reason: e.target.value })}/></Field></FormDialog>
 <FormDialog open={policyModal} onClose={() => setPolicyModal(false)} title={editingType ? 'Edit leave policy' : 'Create leave policy'} description="Rules are enforced by the backend when requests are submitted and approved." size="lg" formId="leave-policy-submit" onSubmit={savePolicy} submitLabel={editingType ? 'Save Policy' : 'Create Policy'} loading={saving}><Grid columns="2fr 1fr" gap={10}><Field label="Name"><Input value={policyForm.name} onChange={e => setPolicyForm({ ...policyForm, name: e.target.value })} required/></Field><Field label="Code"><Input value={policyForm.code} onChange={e => setPolicyForm({ ...policyForm, code: e.target.value })} required/></Field></Grid><Grid columns="repeat(3,1fr)" gap={10}><Field label="Annual allowance"><Input type="number" min="0" step="0.5" value={policyForm.annual_allowance_days} onChange={e => setPolicyForm({ ...policyForm, annual_allowance_days: e.target.value })}/></Field><Field label="Accrual"><Select value={policyForm.accrual_method} onChange={e => setPolicyForm({ ...policyForm, accrual_method: e.target.value as PolicyForm['accrual_method'] })}><Option value="annual">Annual</Option><Option value="monthly">Monthly</Option><Option value="none">No accrual</Option></Select></Field><Field label="Monthly accrual"><Input type="number" min="0" step="0.25" disabled={policyForm.accrual_method !== 'monthly'} value={policyForm.monthly_accrual_days} onChange={e => setPolicyForm({ ...policyForm, monthly_accrual_days: e.target.value })}/></Field></Grid><Grid columns="repeat(4,1fr)" gap={10}><Field label="Carryover days"><Input type="number" min="0" step="0.5" value={policyForm.carryover_days} onChange={e => setPolicyForm({ ...policyForm, carryover_days: e.target.value })}/></Field><Field label="Notice days"><Input type="number" min="0" value={policyForm.min_notice_days} onChange={e => setPolicyForm({ ...policyForm, min_notice_days: e.target.value })}/></Field><Field label="Max consecutive"><Input type="number" min="1" value={policyForm.max_consecutive_days} onChange={e => setPolicyForm({ ...policyForm, max_consecutive_days: e.target.value })}/></Field><Field label="Probation months"><Input type="number" min="0" value={policyForm.probation_months} onChange={e => setPolicyForm({ ...policyForm, probation_months: e.target.value })}/></Field></Grid><Grid columns="1fr 1fr" gap={8}>{[['Paid leave', 'is_paid'], ['Requires approval', 'requires_approval'], ['Exclude weekends', 'exclude_weekends'], ['Exclude holidays', 'exclude_holidays'], ['Allow negative balance', 'allow_negative_balance']].map(([label, key]) => <BooleanField key={key} label={label} checked={Boolean(policyForm[key as keyof PolicyForm])} onChange={value => setPolicyForm({ ...policyForm, [key]: value })}/>)}</Grid></FormDialog>
 <FormDialog open={adjustModal} onClose={() => setAdjustModal(false)} title="Adjust leave balance" description="Use positive values to grant extra days and negative values for manual deductions." formId="leave-adjust-submit" onSubmit={adjustBalance} submitLabel="Apply Adjustment" loading={saving}><Field label="Employee"><Select value={adjustForm.member_id} onChange={e => setAdjustForm({ ...adjustForm, member_id: e.target.value })}>{data.people.map(person => <Option key={person.id} value={person.id}>{personName(person)}</Option>)}</Select></Field><Field label="Leave type"><Select value={adjustForm.leave_type_id} onChange={e => setAdjustForm({ ...adjustForm, leave_type_id: e.target.value })}>{data.leave_types.map(type => <Option key={type.id} value={type.id}>{type.name}</Option>)}</Select></Field><Grid columns="1fr 1fr" gap={10}><Field label="Year"><Input type="number" value={adjustForm.year} onChange={e => setAdjustForm({ ...adjustForm, year: e.target.value })}/></Field><Field label="Adjustment days"><Input type="number" step="0.5" value={adjustForm.days} onChange={e => setAdjustForm({ ...adjustForm, days: e.target.value })}/></Field></Grid></FormDialog>
 </Page>;
}
