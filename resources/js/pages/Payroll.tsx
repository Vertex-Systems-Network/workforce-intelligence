import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Banknote, Calculator, CheckCircle2, CircleDollarSign, CreditCard, FileCheck2, History, Pencil, Plus, Receipt, Trash2, Users } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { useConfirmAction, EmptyState, Alert, Badge, Button, Drawer, Field, Input, Page, PageHeader, Segmented, Select, Tabs, Textarea, Pressable, Checkbox, Box, Grid, Inline, Stack, Text, Option, DataGrid, FormDialog, SettingRow, type DataGridColumn } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
import { type Adjustment, type CompensationForm, type CompensationProfile, type CompensationRow, type PayType, type PayrollItem, type PayrollRun, type RunStatus, dateLabel, emptyComp, hours, money, payLabel, statusLabel, statusTone } from './payroll/support';
/** Handles the payroll operation for the WorkIntel client. */ export default function Payroll() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const role = session?.user.workspaces.find(workspace => workspace.id === workspaceId)?.role ?? 'employee';
    const canManage = ['owner', 'admin', 'payroll-manager'].includes(role);
    const [tab, setTab] = useState<'runs' | 'compensation' | 'my-pay'>(canManage ? 'runs' : 'my-pay');
    const [runs, setRuns] = useState<PayrollRun[]>([]);
    const [selectedRun, setSelectedRun] = useState<PayrollRun | null>(null);
    const [compensation, setCompensation] = useState<CompensationRow[]>([]);
    const [myPay, setMyPay] = useState<PayrollItem[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [warning, setWarning] = useState('');
    const [runModal, setRunModal] = useState(false);
    const [compModal, setCompModal] = useState(false);
    const [adjustModal, setAdjustModal] = useState(false);
    const [selectedItem, setSelectedItem] = useState<PayrollItem | null>(null);
    const [editingComp, setEditingComp] = useState<CompensationRow | null>(null);
    const [currency, setCurrency] = useState('USD');
    const [runForm, setRunForm] = useState({ name: 'August Payroll', period_start: '2026-08-01', period_end: '2026-08-31', pay_date: '2026-09-05', note: '' });
    const [compForm, setCompForm] = useState<CompensationForm>(emptyComp('USD'));
    const [adjustForm, setAdjustForm] = useState({ category: 'bonus', direction: 'earning' as 'earning' | 'deduction', label: '', amount: '', note: '' });
    /** Loads load runs data required by the current view. */ const loadRuns = async (selectId?: number) => {
        if (!workspaceId || !canManage)
            return;
        const payload = await apiRequest<{
            data: PayrollRun[];
            workspace_currency: string;
        }>('/api/v1/payroll/runs', { workspaceId });
        setRuns(payload.data);
        setCurrency(payload.workspace_currency);
        const target = selectId ?? selectedRun?.id ?? payload.data[0]?.id;
        if (target) {
            const detail = await apiRequest<{
                data: PayrollRun;
            }>(`/api/v1/payroll/runs/${target}`, { workspaceId });
            setSelectedRun(detail.data);
        }
        else
            setSelectedRun(null);
    };
    /** Loads load compensation data required by the current view. */ const loadCompensation = async () => {
        if (!workspaceId || !canManage)
            return;
        const payload = await apiRequest<{
            data: CompensationRow[];
            workspace_currency: string;
        }>('/api/v1/payroll/compensation', { workspaceId });
        setCompensation(payload.data);
        setCurrency(payload.workspace_currency);
    };
    /** Loads load my pay data required by the current view. */ const loadMyPay = async () => { if (workspaceId) {
        const payload = await apiRequest<{
            data: PayrollItem[];
        }>('/api/v1/payroll/me', { workspaceId });
        setMyPay(payload.data);
    } };
    /** Handles the initial load operation for the WorkIntel client. */ const initialLoad = async () => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        await Promise.all([canManage ? loadRuns() : Promise.resolve(), canManage ? loadCompensation() : Promise.resolve(), loadMyPay()]);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load payroll.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void initialLoad(); }, [workspaceId]);
    const totals = useMemo(() => {
        const items = selectedRun?.items ?? [];
        return { net: items.reduce((s, item) => s + Number(item.net_pay), 0), gross: items.reduce((s, item) => s + Number(item.gross_pay), 0), overtime: items.reduce((s, item) => s + Number(item.overtime_pay) + Number(item.weekend_pay) + Number(item.holiday_pay), 0), tax: items.reduce((s, item) => s + Number(item.tax_total), 0) };
    }, [selectedRun]);
    /** Handles the open run operation for the WorkIntel client. */ const openRun = async (run: PayrollRun) => { setError(''); try {
        const payload = await apiRequest<{
            data: PayrollRun;
        }>(`/api/v1/payroll/runs/${run.id}`, { workspaceId });
        setSelectedRun(payload.data);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load payroll run.');
    } };
    /** Handles the create run operation for the WorkIntel client. */ const createRun = async (event: FormEvent) => { event.preventDefault(); setSaving(true); setError(''); try {
        const payload = await apiRequest<{
            data: PayrollRun;
        }>('/api/v1/payroll/runs', { method: 'POST', workspaceId, body: JSON.stringify({ ...runForm, currency }) });
        setRunModal(false);
        await loadRuns(payload.data.id);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not create payroll run.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the run action operation for the WorkIntel client. */ const runAction = async (action: 'calculate' | 'submit' | 'approve' | 'mark-paid') => { if (!selectedRun)
        return; setSaving(true); setError(''); setWarning(''); try {
        const payload = await apiRequest<{
            data: PayrollRun;
            missing_member_ids?: number[];
        }>(`/api/v1/payroll/runs/${selectedRun.id}/${action}`, { method: 'POST', workspaceId, body: JSON.stringify({}) });
        setSelectedRun(payload.data);
        await loadRuns(payload.data.id);
        if (payload.missing_member_ids?.length)
            setWarning(`${payload.missing_member_ids.length} employee(s) were skipped because compensation is missing or uses a different currency.`);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Payroll action failed.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the delete run operation for the WorkIntel client. */ const deleteRun = async () => { if (!selectedRun || !await confirmAction({ title: 'Delete payroll run?', description: `Delete ${selectedRun.name}?`, confirmLabel: 'Delete', danger: true }))
        return; setSaving(true); try {
        await apiRequest(`/api/v1/payroll/runs/${selectedRun.id}`, { method: 'DELETE', workspaceId });
        setSelectedRun(null);
        await loadRuns();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not delete payroll run.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the open comp operation for the WorkIntel client. */ const openComp = (row: CompensationRow) => { setEditingComp(row); const p = row.profile; setCompForm(p ? { pay_type: p.pay_type, currency: p.currency, hourly_rate: p.hourly_rate ?? '', daily_rate: p.daily_rate ?? '', monthly_salary: p.monthly_salary ?? '', annual_salary: p.annual_salary ?? '', project_rate: p.project_rate ?? '', premium_hourly_rate: p.premium_hourly_rate ?? '', standard_hours_per_day: String(p.standard_hours_per_day), standard_hours_per_week: String(p.standard_hours_per_week), overtime_multiplier: String(p.overtime_multiplier), weekend_multiplier: String(p.weekend_multiplier), holiday_multiplier: String(p.holiday_multiplier), default_tax_percent: String(p.default_tax_percent), deduct_unpaid_leave: p.deduct_unpaid_leave, proration_mode: p.proration_mode, effective_from: p.effective_from, note: p.note ?? '' } : emptyComp(currency)); setCompModal(true); };
    /** Handles the save comp operation for the WorkIntel client. */ const saveComp = async (event: FormEvent) => { event.preventDefault(); if (!editingComp)
        return; setSaving(true); setError(''); try {
        await apiRequest(`/api/v1/payroll/compensation/${editingComp.member_id}`, { method: 'PUT', workspaceId, body: JSON.stringify({ ...compForm, hourly_rate: compForm.hourly_rate || null, daily_rate: compForm.daily_rate || null, monthly_salary: compForm.monthly_salary || null, annual_salary: compForm.annual_salary || null, project_rate: compForm.project_rate || null, premium_hourly_rate: compForm.premium_hourly_rate || null }) });
        setCompModal(false);
        await loadCompensation();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not save compensation.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the open adjustment operation for the WorkIntel client. */ const openAdjustment = (item: PayrollItem) => { setSelectedItem(item); setAdjustForm({ category: 'bonus', direction: 'earning', label: '', amount: '', note: '' }); setAdjustModal(true); };
    /** Handles the save adjustment operation for the WorkIntel client. */ const saveAdjustment = async (event: FormEvent) => { event.preventDefault(); if (!selectedItem || !selectedRun)
        return; setSaving(true); setError(''); try {
        const payload = await apiRequest<{
            data: PayrollItem;
        }>(`/api/v1/payroll/items/${selectedItem.id}/adjustments`, { method: 'POST', workspaceId, body: JSON.stringify(adjustForm) });
        setSelectedItem(payload.data);
        setAdjustModal(false);
        await openRun(selectedRun);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not add payroll adjustment.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the remove adjustment operation for the WorkIntel client. */ const removeAdjustment = async (item: PayrollItem, adjustment: Adjustment) => { if (!selectedRun || !await confirmAction({ title: 'Remove payroll adjustment?', description: `Remove ${adjustment.label}?`, confirmLabel: 'Remove', danger: true }))
        return; setSaving(true); try {
        const payload = await apiRequest<{
            data: PayrollItem;
        }>(`/api/v1/payroll/items/${item.id}/adjustments/${adjustment.id}`, { method: 'DELETE', workspaceId });
        setSelectedItem(payload.data);
        await openRun(selectedRun);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not remove adjustment.');
    }
    finally {
        setSaving(false);
    } };
    const itemColumns: DataGridColumn<PayrollItem>[] = [
        { id: 'employee', header: 'Employee', searchValue: item => `${item.member.user.first_name} ${item.member.user.last_name} ${item.member.department?.name ?? ''}`, sortValue: item => `${item.member.user.first_name} ${item.member.user.last_name}`, cell: item => <Pressable type="button" onClick={() => setSelectedItem(item)}><Stack gap={2}><Text weight={650}>{item.member.user.first_name} {item.member.user.last_name}</Text><Text size={10.5} color="var(--text-3)">{item.member.department?.name ?? 'No department'}</Text></Stack></Pressable> },
        { id: 'pay_type', header: 'Pay Type', filterValue: item => item.pay_type, cell: item => <Badge>{payLabel[item.pay_type]}</Badge> },
        { id: 'tracked', header: 'Tracked', sortValue: item => item.tracked_seconds, cell: item => hours(item.tracked_seconds) },
        { id: 'base', header: 'Base', sortValue: item => Number(item.base_pay), cell: item => money(item.base_pay, item.currency) },
        { id: 'premiums', header: 'Premiums', sortValue: item => Number(item.overtime_pay) + Number(item.weekend_pay) + Number(item.holiday_pay), cell: item => money(Number(item.overtime_pay) + Number(item.weekend_pay) + Number(item.holiday_pay), item.currency) },
        { id: 'adjustments', header: 'Adjustments', sortValue: item => Number(item.bonus_total) + Number(item.commission_total) + Number(item.reimbursement_total) + Number(item.adjustment_total) - Number(item.deduction_total) - Number(item.unpaid_leave_deduction), cell: item => { const value = Number(item.bonus_total) + Number(item.commission_total) + Number(item.reimbursement_total) + Number(item.adjustment_total) - Number(item.deduction_total) - Number(item.unpaid_leave_deduction); return <Text color={value >= 0 ? 'var(--success)' : 'var(--danger)'}>{value >= 0 ? '+' : ''}{money(value, item.currency)}</Text>; } },
        { id: 'withholding', header: 'Withholding', sortValue: item => Number(item.tax_total), cell: item => money(item.tax_total, item.currency) },
        { id: 'net', header: 'Net Pay', sortValue: item => Number(item.net_pay), cell: item => <Text weight={700}>{money(item.net_pay, item.currency)}</Text> },
        { id: 'actions', header: '', hideable: false, cell: item => !selectedRun?.locked_at ? <Button variant="ghost" size="sm" iconOnly aria-label={`Add adjustment for ${item.member.user.first_name}`} onClick={() => openAdjustment(item)}><Plus size={13}/></Button> : null },
    ];
    const compensationColumns: DataGridColumn<CompensationRow>[] = [
        { id: 'employee', header: 'Employee', searchValue: row => `${row.name} ${row.job_title ?? ''} ${row.department ?? ''} ${row.email}`, sortValue: row => row.name, cell: row => <Stack gap={2}><Text weight={650}>{row.name}</Text><Text size={10.5} color="var(--text-3)">{row.job_title ?? row.department ?? row.email}</Text></Stack> },
        { id: 'type', header: 'Pay Type', filterValue: row => row.profile?.pay_type ?? 'unconfigured', cell: row => row.profile ? <Badge>{payLabel[row.profile.pay_type]}</Badge> : <Badge tone="warning">Not configured</Badge> },
        { id: 'rate', header: 'Current Rate', sortValue: row => row.profile ? rateDisplay(row.profile) : '', cell: row => row.profile ? rateDisplay(row.profile) : '—' },
        { id: 'premium', header: 'Premium Rates', cell: row => row.profile ? `OT ${row.profile.overtime_multiplier}× · Weekend ${row.profile.weekend_multiplier}× · Holiday ${row.profile.holiday_multiplier}×` : '—' },
        { id: 'effective', header: 'Effective From', sortValue: row => row.profile?.effective_from ?? '', filterValue: row => row.profile?.effective_from ?? '', filter: { type: 'dateRange', label: 'Effective' }, cell: row => row.profile ? dateLabel(row.profile.effective_from) : '—' },
        { id: 'actions', header: '', hideable: false, cell: row => <Button variant="ghost" size="sm" onClick={() => openComp(row)}><Pencil size={13}/> Configure</Button> },
    ];
    const myPayColumns: DataGridColumn<PayrollItem>[] = [
        { id: 'payroll', header: 'Payroll', searchValue: item => String((item as any).run?.name ?? 'Payroll'), cell: item => <Pressable type="button" onClick={() => setSelectedItem(item)}><Text weight={650}>{(item as any).run?.name ?? 'Payroll'}</Text></Pressable> },
        { id: 'period', header: 'Period', sortValue: item => (item as any).run?.period_start ?? '', cell: item => `${dateLabel((item as any).run?.period_start)} – ${dateLabel((item as any).run?.period_end)}` },
        { id: 'gross', header: 'Gross', sortValue: item => Number(item.gross_pay), cell: item => money(item.gross_pay, item.currency) },
        { id: 'tax', header: 'Withholding', sortValue: item => Number(item.tax_total), cell: item => money(item.tax_total, item.currency) },
        { id: 'net', header: 'Net Pay', sortValue: item => Number(item.net_pay), cell: item => <Text weight={700}>{money(item.net_pay, item.currency)}</Text> },
        { id: 'status', header: 'Status', filterValue: item => item.status, cell: item => <Badge tone={(item.status === 'paid' ? 'accent' : 'success') as 'accent' | 'success'}>{item.status}</Badge> },
    ];
    if (loading)
        return <PageLoadingState />;
    const selectedStatus = selectedRun?.status;
    return <Page>
    <PageHeader title="Payroll" description={canManage ? 'Compensation, calculated payroll and payment workflow.' : 'Your approved payroll history.'} actions={canManage ? <Button variant="primary" size="sm" onClick={() => setRunModal(true)}><Plus size={14}/> New Payroll Run</Button> : undefined}/>
    {error && <Alert tone="danger">{error}</Alert>}{warning && <Alert tone="warning">{warning}</Alert>}
    <Tabs value={tab} onChange={setTab} tabs={canManage ? [{ value: 'runs', label: 'Payroll Runs' }, { value: 'compensation', label: 'Compensation' }, { value: 'my-pay', label: 'My Pay' }] : [{ value: 'my-pay', label: 'My Pay' }]}/>

    {tab === 'runs' && canManage && <Grid columns="280px minmax(0,1fr)" gap={16} mt={16} align="start">
      <Box className="ui-card" p={8}>{runs.length ? runs.map(run => <Pressable key={run.id} onClick={() => void openRun(run)} width="100%" textAlign="left" p="11px 12px" border="none" radius={8} bg={selectedRun?.id === run.id ? 'var(--accent-dim)' : 'transparent'} color="var(--text)" cursor="pointer" fontFamily="inherit" mb={3}><Inline justify="space-between" gap={8} align="center"><Text as="strong" size={12.5}>{run.name}</Text><Badge tone={statusTone[run.status]}>{statusLabel[run.status]}</Badge></Inline><Box className="ui-card-description" mt={5}>{dateLabel(run.period_start)} – {dateLabel(run.period_end)}</Box><Box size={12} weight={650} mt={5}>{money(run.net_total, run.currency)}</Box></Pressable>) : <EmptyState title="No payroll runs yet."/>}</Box>
      <div>{selectedRun ? <>
        <Inline justify="space-between" align="flex-start" gap={12} mb={14}><div><Inline gap={8} align="center"><Box as="h2" m={0} size={18}>{selectedRun.name}</Box><Badge tone={statusTone[selectedRun.status]}>{statusLabel[selectedRun.status]}</Badge>{selectedRun.locked_at && <Badge tone="neutral">Locked</Badge>}</Inline><Box className="ui-card-description" mt={5}>{dateLabel(selectedRun.period_start)} – {dateLabel(selectedRun.period_end)} · Pay date {dateLabel(selectedRun.pay_date)}</Box></div><Inline gap={8} wrap="wrap" justify="flex-end">{selectedStatus === 'draft' && <Button variant="primary" loading={saving} onClick={() => void runAction('calculate')}><Calculator size={14}/> Calculate</Button>}{selectedStatus === 'calculated' && <><Button variant="outline" loading={saving} onClick={() => void runAction('calculate')}><Calculator size={14}/> Recalculate</Button><Button variant="primary" loading={saving} onClick={() => void runAction('submit')}><FileCheck2 size={14}/> Submit for Review</Button></>}{selectedStatus === 'review' && <Button variant="primary" loading={saving} onClick={() => void runAction('approve')}><CheckCircle2 size={14}/> Approve & Lock</Button>}{selectedStatus === 'approved' && <Button variant="primary" loading={saving} onClick={() => void runAction('mark-paid')}><CreditCard size={14}/> Mark Paid</Button>}{['draft', 'calculated', 'review'].includes(selectedStatus ?? '') && <Button variant="ghost" onClick={() => void deleteRun()} disabled={saving}><Trash2 size={14}/></Button>}</Inline></Inline>
        <Grid columns="repeat(4,minmax(0,1fr))" gap={10} mb={14}><Metric label="Net Payroll" value={money(totals.net, selectedRun.currency)} icon={<CircleDollarSign size={15}/>}/><Metric label="Gross Pay" value={money(totals.gross, selectedRun.currency)} icon={<Banknote size={15}/>}/><Metric label="Premium Pay" value={money(totals.overtime, selectedRun.currency)} icon={<Receipt size={15}/>}/><Metric label="Withholding" value={money(totals.tax, selectedRun.currency)} icon={<FileCheck2 size={15}/>}/></Grid>
        <DataGrid rows={selectedRun.items ?? []} columns={itemColumns} rowKey={item => item.id} persistKey={`payroll.run-items.${selectedRun.id}`} defaultSort={{ id: 'employee', direction: 'asc' }} empty={<EmptyState title="No payroll items yet." text="Calculate this payroll run to create employee items."/>}/>
        <Box className="ui-card" p={14} mt={14}><Inline gap={8} align="center" mb={8}><History size={15}/><Text as="strong" size={13}>Payroll History</Text></Inline>{(selectedRun.actions ?? []).length ? (selectedRun.actions ?? []).slice(0, 8).map(action => <Box key={action.id} display="flex" justify="space-between" gap={12} p="8px 0" borderTop="1px solid var(--border-muted)"><div><Box size={12.5} weight={550} textTransform="capitalize">{action.action.replaceAll('_', ' ')}</Box><div className="ui-card-description">{action.user ? `${action.user.first_name} ${action.user.last_name}` : 'System'}{action.note ? ` · ${action.note}` : ''}</div></div><div className="ui-card-description">{new Date(action.occurred_at).toLocaleString()}</div></Box>) : <div className="ui-card-description">No workflow history yet.</div>}</Box>
      </> : <EmptyState title="Select or create a payroll run."/>}</div>
    </Grid>}

    {tab === 'compensation' && canManage && <Box mt={16}><Box className="ui-card" p={14} mb={12}><Box size={13} weight={600}>Compensation profiles</Box><Box className="ui-card-description" mt={4}>Rates are effective-dated. Editing compensation creates a new profile so approved payroll snapshots remain unchanged. Currency conversion is intentionally disabled in this milestone.</Box></Box><DataGrid rows={compensation} columns={compensationColumns} rowKey={row => row.member_id} persistKey="payroll.compensation" defaultSort={{ id: 'employee', direction: 'asc' }} empty={<EmptyState title="No compensation profiles are visible."/>}/></Box>}

    {tab === 'my-pay' && <Box mt={16}><DataGrid rows={myPay} columns={myPayColumns} rowKey={item => item.id} persistKey="payroll.my-pay" defaultSort={{ id: 'period', direction: 'desc' }} empty={<EmptyState icon={<Banknote size={24}/>} title="No approved payroll yet" text="Approved or paid payroll items will appear here."/>}/></Box>}

    <FormDialog open={runModal} onClose={() => setRunModal(false)} title="Create payroll run" description="Create a period first, then calculate it from compensation and approved work data." formId="payroll-run-submit" onSubmit={createRun} submitLabel="Create Run" loading={saving}><Field label="Run name"><Input value={runForm.name} onChange={e => setRunForm({ ...runForm, name: e.target.value })} required/></Field><Grid columns="1fr 1fr" gap={10}><Field label="Period start"><Input type="date" value={runForm.period_start} onChange={e => setRunForm({ ...runForm, period_start: e.target.value })} required/></Field><Field label="Period end"><Input type="date" value={runForm.period_end} onChange={e => setRunForm({ ...runForm, period_end: e.target.value })} required/></Field></Grid><Field label="Pay date"><Input type="date" value={runForm.pay_date} onChange={e => setRunForm({ ...runForm, pay_date: e.target.value })}/></Field><Field label="Currency"><Input value={currency} disabled/></Field><Field label="Notes"><Textarea value={runForm.note} onChange={e => setRunForm({ ...runForm, note: e.target.value })}/></Field></FormDialog>

    <FormDialog open={compModal} onClose={() => setCompModal(false)} title={`Compensation · ${editingComp?.name ?? ''}`} description="This creates a new effective-dated compensation profile." size="lg" formId="comp-submit" onSubmit={saveComp} submitLabel="Save Compensation" loading={saving}><Grid columns="1fr 1fr" gap={10}><Field label="Pay type"><Select value={compForm.pay_type} onChange={e => setCompForm({ ...compForm, pay_type: e.target.value as PayType })}>{Object.entries(payLabel).map(([value, label]) => <Option key={value} value={value}>{label}</Option>)}</Select></Field><Field label="Effective from"><Input type="date" value={compForm.effective_from} onChange={e => setCompForm({ ...compForm, effective_from: e.target.value })} required/></Field></Grid><RateFields form={compForm} setForm={setCompForm}/><Grid columns="repeat(3,1fr)" gap={10}><Field label="Overtime multiplier"><Input type="number" min="1" step="0.1" value={compForm.overtime_multiplier} onChange={e => setCompForm({ ...compForm, overtime_multiplier: e.target.value })}/></Field><Field label="Weekend multiplier"><Input type="number" min="1" step="0.1" value={compForm.weekend_multiplier} onChange={e => setCompForm({ ...compForm, weekend_multiplier: e.target.value })}/></Field><Field label="Holiday multiplier"><Input type="number" min="1" step="0.1" value={compForm.holiday_multiplier} onChange={e => setCompForm({ ...compForm, holiday_multiplier: e.target.value })}/></Field></Grid><Grid columns="repeat(3,1fr)" gap={10}><Field label="Hours / day"><Input type="number" min="1" step="0.5" value={compForm.standard_hours_per_day} onChange={e => setCompForm({ ...compForm, standard_hours_per_day: e.target.value })}/></Field><Field label="Hours / week"><Input type="number" min="1" step="0.5" value={compForm.standard_hours_per_week} onChange={e => setCompForm({ ...compForm, standard_hours_per_week: e.target.value })}/></Field><Field label="Configured withholding %"><Input type="number" min="0" max="100" step="0.1" value={compForm.default_tax_percent} onChange={e => setCompForm({ ...compForm, default_tax_percent: e.target.value })}/></Field></Grid><Grid columns="1fr 1fr" gap={10}><Field label="Proration"><Select value={compForm.proration_mode} onChange={e => setCompForm({ ...compForm, proration_mode: e.target.value as 'calendar_days' | 'none' })}><Option value="calendar_days">Calendar-day proration</Option><Option value="none">No proration</Option></Select></Field><Field label="Premium hourly override"><Input type="number" min="0" step="0.01" value={compForm.premium_hourly_rate} onChange={e => setCompForm({ ...compForm, premium_hourly_rate: e.target.value })} placeholder="Optional"/></Field></Grid><SettingRow title="Deduct approved unpaid leave" description="Apply approved unpaid leave deductions for salary and daily profiles." control={<Checkbox checked={compForm.deduct_unpaid_leave} onChange={e => setCompForm({ ...compForm, deduct_unpaid_leave: e.target.checked })}/>}/><Field label="Notes"><Textarea value={compForm.note} onChange={e => setCompForm({ ...compForm, note: e.target.value })}/></Field><div className="ui-card-description">Configured withholding is a workspace payroll setting, not a jurisdiction-specific tax calculation or legal/tax advice.</div></FormDialog>

    <FormDialog open={adjustModal} onClose={() => setAdjustModal(false)} title="Add payroll adjustment" description={selectedItem ? `${selectedItem.member.user.first_name} ${selectedItem.member.user.last_name}` : undefined} formId="adjust-submit" onSubmit={saveAdjustment} submitLabel="Add Adjustment" loading={saving}><Grid columns="1fr 1fr" gap={10}><Field label="Category"><Select value={adjustForm.category} onChange={e => { const category = e.target.value; const deduction = ['deduction', 'tax', 'advance'].includes(category); setAdjustForm({ ...adjustForm, category, direction: deduction ? 'deduction' : 'earning' }); }}><Option value="bonus">Bonus</Option><Option value="commission">Commission</Option><Option value="reimbursement">Reimbursement</Option><Option value="deduction">Deduction</Option><Option value="tax">Tax / withholding</Option><Option value="advance">Salary advance</Option><Option value="adjustment">Custom adjustment</Option></Select></Field><Field label="Direction"><Select value={adjustForm.direction} disabled={adjustForm.category !== 'adjustment'} onChange={e => setAdjustForm({ ...adjustForm, direction: e.target.value as 'earning' | 'deduction' })}><Option value="earning">Earning</Option><Option value="deduction">Deduction</Option></Select></Field></Grid><Field label="Label"><Input value={adjustForm.label} onChange={e => setAdjustForm({ ...adjustForm, label: e.target.value })} required/></Field><Field label="Amount"><Input type="number" min="0.01" step="0.01" value={adjustForm.amount} onChange={e => setAdjustForm({ ...adjustForm, amount: e.target.value })} required/></Field><Field label="Note"><Textarea value={adjustForm.note} onChange={e => setAdjustForm({ ...adjustForm, note: e.target.value })}/></Field></FormDialog>

    <Drawer open={!!selectedItem && !adjustModal} onClose={() => setSelectedItem(null)} title={selectedItem ? `${selectedItem.member.user.first_name} ${selectedItem.member.user.last_name}` : 'Pay breakdown'} description={selectedItem ? `${payLabel[selectedItem.pay_type]} · ${selectedItem.status}` : undefined}>{selectedItem && <PayBreakdown item={selectedItem} locked={!!selectedRun?.locked_at || ['approved', 'paid'].includes(selectedItem.status) || !canManage} onRemove={adjustment => void removeAdjustment(selectedItem, adjustment)}/>}</Drawer>
  </Page>;
}
/** Handles the metric operation for the WorkIntel client. */ function Metric({ label, value, icon }: {
    label: string;
    value: string;
    icon: React.ReactNode;
}) { return <Box className="ui-card" p="14px 16px"><Box display="flex" justify="space-between" color="var(--text-3)" size={11}>{label}{icon}</Box><Box size={21} weight={750} mt={7}>{value}</Box></Box>; }
/** Handles the rate display operation for the WorkIntel client. */ function rateDisplay(profile: CompensationProfile) { const value = profile.pay_type === 'hourly' ? profile.hourly_rate : profile.pay_type === 'daily' ? profile.daily_rate : profile.pay_type === 'monthly' ? profile.monthly_salary : profile.pay_type === 'yearly' ? profile.annual_salary : profile.project_rate; const suffix = profile.pay_type === 'hourly' ? '/hr' : profile.pay_type === 'daily' ? '/day' : profile.pay_type === 'monthly' ? '/month' : profile.pay_type === 'yearly' ? '/year' : '/project'; return `${money(value, profile.currency)} ${suffix}`; }
/** Handles the rate fields operation for the WorkIntel client. */ function RateFields({ form, setForm }: {
    form: CompensationForm;
    setForm: (value: CompensationForm) => void;
}) { const mapping: {
    type: PayType;
    field: keyof CompensationForm;
    label: string;
}[] = [{ type: 'hourly', field: 'hourly_rate', label: 'Hourly rate' }, { type: 'daily', field: 'daily_rate', label: 'Daily rate' }, { type: 'monthly', field: 'monthly_salary', label: 'Monthly salary' }, { type: 'yearly', field: 'annual_salary', label: 'Annual salary' }, { type: 'project', field: 'project_rate', label: 'Completed project rate' }]; const selected = mapping.find(item => item.type === form.pay_type)!; return <Grid columns="1fr 1fr" gap={10}><Field label={selected.label}><Input type="number" min="0" step="0.01" value={String(form[selected.field])} onChange={e => setForm({ ...form, [selected.field]: e.target.value })} required/></Field><Field label="Currency"><Input value={form.currency} disabled/></Field></Grid>; }
/** Handles the pay breakdown operation for the WorkIntel client. */ function PayBreakdown({ item, locked, onRemove }: {
    item: PayrollItem;
    locked: boolean;
    onRemove: (adjustment: Adjustment) => void;
}) { const rows = [['Base pay', Number(item.base_pay)], ['Overtime premium', Number(item.overtime_pay)], ['Weekend premium', Number(item.weekend_pay)], ['Holiday premium', Number(item.holiday_pay)], ['Unpaid leave', -Number(item.unpaid_leave_deduction)], ['Bonus', Number(item.bonus_total)], ['Commission', Number(item.commission_total)], ['Reimbursements', Number(item.reimbursement_total)], ['Other adjustments', Number(item.adjustment_total)], ['Deductions', -Number(item.deduction_total)], ['Withholding', -Number(item.tax_total)]] as const; return <Stack gap={16}><Box className="ui-card" p={14}><Grid columns="1fr 1fr" gap={10}><Small label="Tracked" value={hours(item.tracked_seconds)}/><Small label="Attendance" value={`${Number(item.attendance_days).toFixed(0)} days`}/><Small label="Overtime" value={hours(item.overtime_seconds)}/><Small label="Projects" value={`${item.project_units}`}/></Grid></Box><div>{rows.map(([label, value]) => <Box key={label} display="flex" justify="space-between" p="8px 0" borderBottom="1px solid var(--border-muted)" size={12.5}><Text color="var(--text-2)">{label}</Text><Box as="strong" color={value < 0 ? 'var(--danger)' : value > 0 ? 'var(--text)' : 'var(--text-3)'}>{value >= 0 ? '+' : ''}{money(value, item.currency)}</Box></Box>)}<Box display="flex" justify="space-between" p="12px 0" size={14}><strong>Net Pay</strong><Text as="strong" size={18}>{money(item.net_pay, item.currency)}</Text></Box></div>{item.projects.length > 0 && <div><Text as="strong" size={12.5}>Project earnings</Text>{item.projects.map(project => <Inline key={project.id} className="ui-card" p={10} mt={7} justify="space-between"><span>{project.project?.name ?? 'Project'}</span><strong>{money(project.amount, item.currency)}</strong></Inline>)}</div>}<div><Text as="strong" size={12.5}>Manual adjustments</Text>{item.adjustments.length ? item.adjustments.map(adjustment => <Box key={adjustment.id} display="flex" justify="space-between" gap={8} p="9px 0" borderBottom="1px solid var(--border-muted)"><div><Box size={12.5} weight={550}>{adjustment.label}</Box><div className="ui-card-description">{adjustment.category} · {adjustment.direction}</div></div><Inline align="center" gap={6}><strong>{adjustment.direction === 'deduction' ? '-' : '+'}{money(adjustment.amount, item.currency)}</strong>{!locked && <Button variant="ghost" size="sm" onClick={() => onRemove(adjustment)}><Trash2 size={12}/></Button>}</Inline></Box>) : <Box className="ui-card-description" mt={7}>No manual adjustments.</Box>}</div><div className="ui-card-description">Approved payroll is locked. Compensation changes made later do not alter this payroll snapshot.</div></Stack>; }
/** Handles the small operation for the WorkIntel client. */ function Small({ label, value }: {
    label: string;
    value: string;
}) { return <div><div className="ui-card-description">{label}</div><Box weight={650} mt={3}>{value}</Box></div>; }
