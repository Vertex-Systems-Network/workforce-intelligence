import { useEffect, useMemo, useState } from 'react';
import { Check, Clock3, GitBranch, History, Inbox, Plus, RefreshCw, Send, ShieldCheck, Trash2, X } from 'lucide-react';
import { useAuth } from '../auth/AuthContext';
import { apiRequest } from '../api/client';
import { useConfirmAction, EmptyState, Alert, Badge, Button, Card, CardBody, Field, Input, Modal, Page, PageHeader, Select, Switch, Tabs, Textarea, Box, Grid, Inline, Stack, Option, DataGrid, StatCard, FormDialog, type DataGridColumn, Text } from '../design-system';
type User = {
    first_name: string;
    last_name: string;
    email?: string;
};
type Member = {
    id: number;
    user: User;
};
type RequestStep = {
    id: number;
    position: number;
    name: string;
    status: string;
    due_at: string | null;
    assigned_member_ids: number[];
    required_approvals: number;
    approved_count: number;
};
type Approval = {
    id: number;
    uuid: string;
    title: string;
    summary: string | null;
    trigger_key: string;
    subject_type: string;
    status: string;
    current_step_position: number;
    submitted_at: string;
    due_at: string | null;
    requester?: Member;
    steps: RequestStep[];
    decisions?: Decision[];
};
type Decision = {
    id: number;
    decision: string;
    note: string | null;
    acted_at: string;
    actor?: Member;
    request?: {
        id: number;
        title: string;
        trigger_key: string;
        status: string;
    };
};
type StepDef = {
    name: string;
    approver_type: 'manager' | 'role' | 'member';
    approver_role_slug: string;
    approver_member_id: string;
    required_approvals: number;
    allow_self_approval: boolean;
};
type ConditionDef = {
    field: string;
    operator: 'eq' | 'neq' | 'gt' | 'gte' | 'lt' | 'lte' | 'in';
    value: string;
};
type WorkflowDraft = {
    name: string;
    trigger_key: string;
    description: string;
    status: 'active' | 'inactive';
    priority: number;
    sla_hours: number;
    escalation_role_slug: string;
    notify_requester: boolean;
    conditions: ConditionDef[];
    steps: StepDef[];
};
type Workflow = {
    id: number;
    name: string;
    trigger_key: string;
    description: string | null;
    status: 'active' | 'inactive';
    priority: number;
    sla_hours: number;
    system_key: string | null;
    escalation_role_slug: string | null;
    notify_requester: boolean;
    conditions?: {
        all?: Array<{
            field: string;
            operator: string;
            value: unknown;
        }>;
    };
    steps: Array<{
        id: number;
        position: number;
        name: string;
        approver_type: 'manager' | 'role' | 'member';
        approver_role_slug: string | null;
        approver_member_id: number | null;
        required_approvals: number;
        allow_self_approval: boolean;
    }>;
};
type Role = {
    id: number;
    name: string;
    slug: string;
};
type Delegation = {
    id: number;
    delegator_member_id: number;
    delegate_member_id: number;
    starts_at: string;
    ends_at: string;
    status: string;
    reason: string | null;
    delegator?: Member;
    delegate?: Member;
};
type InboxPayload = {
    inbox: Approval[];
    mine: Approval[];
    counts: {
        inbox: number;
        mine_pending: number;
    };
    can_review: boolean;
    can_manage_workflows: boolean;
    can_view_audit: boolean;
};
/** Handles the trigger label operation for the WorkIntel client. */ const triggerLabel = (key: string) => ({
    'leave.submitted': 'Leave', 'timesheet.submitted': 'Timesheet', 'project_expense.submitted': 'Expense', 'payroll.submitted': 'Payroll', 'schedule_change.submitted': 'Schedule change', 'attendance_correction.submitted': 'Attendance correction', 'expense_claim.submitted': 'Expense claim', 'purchase_request.submitted': 'Purchase request', 'compensation_review.submitted': 'Compensation review'
}[key] || key);
/** Handles the status tone operation for the WorkIntel client. */ const statusTone = (status: string): 'success' | 'warning' | 'danger' | 'neutral' | 'info' => status === 'approved' ? 'success' : status === 'rejected' ? 'danger' : status === 'pending' ? 'warning' : status === 'canceled' ? 'neutral' : 'info';
/** Handles the person operation for the WorkIntel client. */ const person = (member?: Member) => member ? `${member.user.first_name} ${member.user.last_name}` : 'Unknown';
/** Handles the when operation for the WorkIntel client. */ const when = (value?: string | null) => value ? new Date(value).toLocaleString() : '—';
/** Handles the approvals operation for the WorkIntel client. */ export default function Approvals() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId;
    const [payload, setPayload] = useState<InboxPayload | null>(null);
    const [tab, setTab] = useState<'inbox' | 'mine' | 'workflows' | 'delegations' | 'audit'>('inbox');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [selected, setSelected] = useState<Approval | null>(null);
    const [decisionNote, setDecisionNote] = useState('');
    const [saving, setSaving] = useState(false);
    const [workflows, setWorkflows] = useState<Workflow[]>([]);
    const [people, setPeople] = useState<Member[]>([]);
    const [roles, setRoles] = useState<Role[]>([]);
    const [triggers, setTriggers] = useState<Array<{
        key: string;
        label: string;
    }>>([]);
    const [delegations, setDelegations] = useState<Delegation[]>([]);
    const [audit, setAudit] = useState<Decision[]>([]);
    const [workflowOpen, setWorkflowOpen] = useState(false);
    const [editWorkflow, setEditWorkflow] = useState<Workflow | null>(null);
    /** Handles the blank step operation for the WorkIntel client. */ const blankStep = (): StepDef => ({ name: 'Manager review', approver_type: 'manager', approver_role_slug: '', approver_member_id: '', required_approvals: 1, allow_self_approval: false });
    const [wf, setWf] = useState<WorkflowDraft>({ name: '', trigger_key: 'leave.submitted', description: '', status: 'active', priority: 100, sla_hours: 24, escalation_role_slug: 'admin', notify_requester: true, conditions: [], steps: [blankStep()] });
    const [delegate, setDelegate] = useState({ delegate_member_id: '', starts_at: new Date().toISOString().slice(0, 10), ends_at: new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10), reason: '' });
    /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        setPayload(await apiRequest<InboxPayload>('/api/v1/approvals', { workspaceId }));
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load approvals.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Loads load workflows data required by the current view. */ const loadWorkflows = async () => { if (!workspaceId)
        return; try {
        const r = await apiRequest<{
            data: Workflow[];
            people: Member[];
            roles: Role[];
            triggers: Array<{
                key: string;
                label: string;
            }>;
        }>('/api/v1/approval-workflows', { workspaceId });
        setWorkflows(r.data);
        setPeople(r.people);
        setRoles(r.roles);
        setTriggers(r.triggers);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load workflows.');
    } };
    /** Loads load delegations data required by the current view. */ const loadDelegations = async () => { if (!workspaceId)
        return; try {
        const r = await apiRequest<{
            data: Delegation[];
            people: Member[];
        }>('/api/v1/approval-delegations', { workspaceId });
        setDelegations(r.data);
        if (!people.length)
            setPeople(r.people);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load delegations.');
    } };
    /** Loads load audit data required by the current view. */ const loadAudit = async () => { if (!workspaceId)
        return; try {
        const r = await apiRequest<{
            data: Decision[];
        }>('/api/v1/approvals/audit', { workspaceId });
        setAudit(r.data);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load audit trail.');
    } };
    useEffect(() => { if (tab === 'workflows' && payload?.can_manage_workflows)
        void loadWorkflows(); if (tab === 'delegations')
        void loadDelegations(); if (tab === 'audit' && payload?.can_view_audit)
        void loadAudit(); }, [tab, payload?.can_manage_workflows, payload?.can_view_audit, workspaceId]);
    /** Handles the decide operation for the WorkIntel client. */ const decide = async (decision: 'approved' | 'rejected') => { if (!workspaceId || !selected)
        return; setSaving(true); setError(''); try {
        await apiRequest(`/api/v1/approvals/${selected.id}/decision`, { method: 'POST', workspaceId, body: JSON.stringify({ decision, note: decisionNote || null }) });
        setSelected(null);
        setDecisionNote('');
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Decision failed.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the cancel operation for the WorkIntel client. */ const cancel = async (row: Approval) => { if (!workspaceId || !await confirmAction({ title: 'Cancel request?', description: `Cancel ${row.title}?`, confirmLabel: 'Cancel request', danger: true }))
        return; try {
        await apiRequest(`/api/v1/approvals/${row.id}/cancel`, { method: 'POST', workspaceId });
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not cancel request.');
    } };
    /** Handles the open workflow operation for the WorkIntel client. */ const openWorkflow = (row?: Workflow) => { setEditWorkflow(row || null); setWf(row ? { name: row.name, trigger_key: row.trigger_key, description: row.description || '', status: row.status, priority: row.priority, sla_hours: row.sla_hours, escalation_role_slug: row.escalation_role_slug || '', notify_requester: row.notify_requester, conditions: (row.conditions?.all || []).map(c => ({ field: c.field, operator: (c.operator || 'eq') as ConditionDef['operator'], value: Array.isArray(c.value) ? c.value.join(',') : String(c.value ?? '') })), steps: row.steps.map(s => ({ name: s.name, approver_type: s.approver_type, approver_role_slug: s.approver_role_slug || '', approver_member_id: s.approver_member_id ? String(s.approver_member_id) : '', required_approvals: s.required_approvals, allow_self_approval: s.allow_self_approval })) } : { name: '', trigger_key: triggers[0]?.key || 'leave.submitted', description: '', status: 'active', priority: 100, sla_hours: 24, escalation_role_slug: 'admin', notify_requester: true, conditions: [], steps: [blankStep()] }); setWorkflowOpen(true); };
    /** Handles the save workflow operation for the WorkIntel client. */ const saveWorkflow = async () => { if (!workspaceId)
        return; setSaving(true); setError(''); try {
        const body = { ...wf, conditions: wf.conditions.map(c => ({ ...c, value: c.operator === 'in' ? c.value.split(',').map(v => v.trim()).filter(Boolean) : c.value })), steps: wf.steps.map(s => ({ ...s, approver_role_slug: s.approver_type === 'role' ? s.approver_role_slug : null, approver_member_id: s.approver_type === 'member' ? Number(s.approver_member_id) : null })) };
        await apiRequest(editWorkflow ? `/api/v1/approval-workflows/${editWorkflow.id}` : '/api/v1/approval-workflows', { method: editWorkflow ? 'PUT' : 'POST', workspaceId, body: JSON.stringify(body) });
        setWorkflowOpen(false);
        await loadWorkflows();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not save workflow.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the remove workflow operation for the WorkIntel client. */ const removeWorkflow = async (row: Workflow) => { if (!workspaceId || !await confirmAction({ title: row.system_key ? 'Disable workflow?' : 'Delete workflow?', description: `${row.system_key ? 'Disable' : 'Delete'} ${row.name}?`, confirmLabel: row.system_key ? 'Disable' : 'Delete', danger: true }))
        return; try {
        await apiRequest(`/api/v1/approval-workflows/${row.id}`, { method: 'DELETE', workspaceId });
        await loadWorkflows();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not update workflow.');
    } };
    /** Handles the save delegation operation for the WorkIntel client. */ const saveDelegation = async () => { if (!workspaceId || !delegate.delegate_member_id)
        return; setSaving(true); try {
        await apiRequest('/api/v1/approval-delegations', { method: 'POST', workspaceId, body: JSON.stringify({ ...delegate, delegate_member_id: Number(delegate.delegate_member_id), starts_at: `${delegate.starts_at} 00:00:00`, ends_at: `${delegate.ends_at} 23:59:59` }) });
        setDelegate(v => ({ ...v, delegate_member_id: '', reason: '' }));
        await loadDelegations();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create delegation.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the revoke delegation operation for the WorkIntel client. */ const revokeDelegation = async (row: Delegation) => { if (!workspaceId)
        return; try {
        await apiRequest(`/api/v1/approval-delegations/${row.id}`, { method: 'DELETE', workspaceId });
        await loadDelegations();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not revoke delegation.');
    } };
    const tabs = useMemo(() => { const t: Array<{
        value: typeof tab;
        label: string;
    }> = [{ value: 'inbox', label: `Inbox${payload?.counts.inbox ? ` (${payload.counts.inbox})` : ''}` }, { value: 'mine', label: `My Requests${payload?.counts.mine_pending ? ` (${payload.counts.mine_pending})` : ''}` }, { value: 'delegations', label: 'Delegations' }]; if (payload?.can_manage_workflows)
        t.push({ value: 'workflows', label: 'Workflow Builder' }); if (payload?.can_view_audit)
        t.push({ value: 'audit', label: 'Audit' }); return t; }, [payload]);
    const rows = tab === 'inbox' ? payload?.inbox || [] : payload?.mine || [];
    const approvalColumns: DataGridColumn<Approval>[] = [
        { id: 'request', header: 'Request', searchValue: row => `${row.title} ${row.summary ?? ''} ${triggerLabel(row.trigger_key)} ${person(row.requester)}`, sortValue: row => row.title, cell: row => <Stack gap={2}><Text weight={650}>{row.title}</Text><Text size={10.5} color="var(--text-3)">{row.summary || `Step ${row.current_step_position}`}</Text></Stack> },
        { id: 'type', header: 'Type', filterValue: row => row.trigger_key, filter: { type: 'select', label: 'Type', options: [...new Map(rows.map(row => [row.trigger_key, { value: row.trigger_key, label: triggerLabel(row.trigger_key) }])).values()] }, cell: row => <Badge>{triggerLabel(row.trigger_key)}</Badge> },
        { id: 'requester', header: 'Requester', searchValue: row => person(row.requester), cell: row => person(row.requester) },
        { id: 'submitted', header: 'Submitted', sortValue: row => row.submitted_at, filterValue: row => row.submitted_at, filter: { type: 'dateRange', label: 'Submitted' }, cell: row => when(row.submitted_at) },
        { id: 'due', header: 'Due', sortValue: row => row.due_at ?? '', filterValue: row => row.due_at ?? '', filter: { type: 'dateRange', label: 'Due' }, cell: row => <Inline gap={6} align="center"><span>{when(row.due_at)}</span>{row.status === 'pending' && row.due_at && new Date(row.due_at).getTime() < Date.now() && <Badge tone="danger">Overdue</Badge>}</Inline> },
        { id: 'status', header: 'Status', filterValue: row => row.status, filter: { type: 'select', label: 'Status', options: ['pending', 'approved', 'rejected', 'canceled'].map(value => ({ value, label: value })) }, cell: row => <Badge tone={statusTone(row.status)} dot>{row.status}</Badge> },
        { id: 'actions', header: '', hideable: false, cell: row => <Inline gap={4}>{tab === 'inbox' && row.status === 'pending' && <Button size="sm" onClick={() => setSelected(row)}><ShieldCheck size={13}/> Review</Button>}{tab === 'mine' && row.status === 'pending' && <Button size="sm" variant="ghost" onClick={() => void cancel(row)}><X size={13}/> Cancel</Button>}</Inline> },
    ];
    const workflowColumns: DataGridColumn<Workflow>[] = [
        { id: 'workflow', header: 'Workflow', searchValue: row => `${row.name} ${row.description ?? ''}`, sortValue: row => row.name, cell: row => <Stack gap={2}><Text weight={650}>{row.name}</Text><Text size={10.5} color="var(--text-3)">{row.system_key ? 'System default' : 'Custom workflow'}</Text></Stack> },
        { id: 'trigger', header: 'Trigger', filterValue: row => row.trigger_key, cell: row => triggerLabel(row.trigger_key) },
        { id: 'steps', header: 'Steps', sortValue: row => row.steps.length, cell: row => row.steps.length },
        { id: 'sla', header: 'SLA', sortValue: row => row.sla_hours, cell: row => `${row.sla_hours}h` },
        { id: 'priority', header: 'Priority', sortValue: row => row.priority, cell: row => row.priority },
        { id: 'status', header: 'Status', filterValue: row => row.status, filter: { type: 'select', label: 'Status', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }] }, cell: row => <Badge tone={row.status === 'active' ? 'success' : 'neutral'} dot>{row.status}</Badge> },
        { id: 'actions', header: '', hideable: false, cell: row => <Inline gap={4}><Button size="sm" variant="ghost" onClick={() => openWorkflow(row)}><GitBranch size={13}/> Edit</Button><Button size="sm" variant="ghost" iconOnly aria-label={`Remove ${row.name}`} onClick={() => void removeWorkflow(row)}><Trash2 size={13}/></Button></Inline> },
    ];
    const delegationColumns: DataGridColumn<Delegation>[] = [
        { id: 'delegator', header: 'Delegator', searchValue: row => person(row.delegator), cell: row => person(row.delegator) },
        { id: 'delegate', header: 'Delegate', searchValue: row => person(row.delegate), cell: row => person(row.delegate) },
        { id: 'window', header: 'Window', sortValue: row => row.starts_at, cell: row => `${when(row.starts_at)} → ${when(row.ends_at)}` },
        { id: 'reason', header: 'Reason', searchValue: row => row.reason ?? '', cell: row => row.reason || '—' },
        { id: 'status', header: 'Status', filterValue: row => row.status, filter: { type: 'select', label: 'Status', options: [{ value: 'active', label: 'Active' }, { value: 'revoked', label: 'Revoked' }, { value: 'expired', label: 'Expired' }] }, cell: row => <Badge tone={row.status === 'active' ? 'success' : 'neutral'}>{row.status}</Badge> },
        { id: 'actions', header: '', hideable: false, cell: row => row.status === 'active' ? <Button size="sm" variant="ghost" onClick={() => void revokeDelegation(row)}>Revoke</Button> : null },
    ];
    const auditColumns: DataGridColumn<Decision>[] = [
        { id: 'when', header: 'When', sortValue: row => row.acted_at, filterValue: row => row.acted_at, filter: { type: 'dateRange', label: 'Decision date' }, cell: row => when(row.acted_at) },
        { id: 'request', header: 'Request', searchValue: row => row.request?.title ?? '', cell: row => row.request?.title || '—' },
        { id: 'decision', header: 'Decision', filterValue: row => row.decision, filter: { type: 'select', label: 'Decision', options: ['approved', 'rejected', 'canceled'].map(value => ({ value, label: value })) }, cell: row => <Badge tone={statusTone(row.decision)}>{row.decision}</Badge> },
        { id: 'actor', header: 'Actor', searchValue: row => person(row.actor), cell: row => person(row.actor) },
        { id: 'note', header: 'Note', searchValue: row => row.note ?? '', cell: row => row.note || '—' },
    ];
    return <Page><PageHeader title="Approvals" description="One inbox for leave, timesheets, expenses, payroll, schedule changes and attendance corrections." actions={<Button variant="outline" size="sm" onClick={() => void load()} loading={loading}><RefreshCw size={13}/> Refresh</Button>}/>
  {error && <Alert tone="danger">{error}</Alert>}<Grid columns="repeat(auto-fit,minmax(160px,1fr))" gap={9}><StatCard label="Waiting for you" value={String(payload?.counts.inbox ?? 0)} sub="Review inbox"/><StatCard label="My pending requests" value={String(payload?.counts.mine_pending ?? 0)} sub="Awaiting decisions"/><StatCard label="Active workflows" value={String(workflows.filter(row => row.status === 'active').length)} sub={payload?.can_manage_workflows ? 'Workflow builder available' : 'Managed by administrators'}/><StatCard label="Active delegations" value={String(delegations.filter(row => row.status === 'active').length)} sub="Temporary approval coverage"/></Grid><Tabs value={tab} tabs={tabs} onChange={setTab}/>
  {(tab === 'inbox' || tab === 'mine') && <DataGrid rows={rows} columns={approvalColumns} rowKey={row => row.id} persistKey={`approvals.${tab}`} loading={loading} defaultSort={{ id: 'submitted', direction: 'desc' }} searchPlaceholder="Search requests, people or types…" empty={<EmptyState icon={<Inbox size={24}/>} title="No requests here." text="Requests that match this approval view will appear here."/>} mobileCard={row => <Stack gap={5}><Inline justify="space-between" gap={8}><Text weight={650}>{row.title}</Text><Badge tone={statusTone(row.status)}>{row.status}</Badge></Inline><Text size={10.5} color="var(--text-3)">{triggerLabel(row.trigger_key)} · {person(row.requester)}</Text></Stack>}/>}
  {tab === 'workflows' && payload?.can_manage_workflows && <DataGrid rows={workflows} columns={workflowColumns} rowKey={row => row.id} persistKey="approvals.workflows" onRefresh={loadWorkflows} defaultSort={{ id: 'priority', direction: 'asc' }} toolbar={<Button onClick={() => openWorkflow()}><Plus size={13}/> New Workflow</Button>} empty={<EmptyState title="No approval workflows yet." text="Create a workflow to route new requests through consistent review steps."/>}/>}
  {tab === 'delegations' && <><Card><CardBody><Stack gap={10}><Text weight={650}>Delegate my approvals</Text><Text size={11} color="var(--text-3)">Use a bounded date window when someone else must review requests on your behalf.</Text><Grid columns="minmax(180px,1fr) 160px 160px minmax(180px,1fr) auto" gap={10} align="end"><Field label="Delegate"><Select value={delegate.delegate_member_id} onChange={e => setDelegate(v => ({ ...v, delegate_member_id: e.target.value }))}><Option value="">Select person</Option>{people.map(p => <Option key={p.id} value={p.id}>{person(p)}</Option>)}</Select></Field><Field label="From"><Input type="date" value={delegate.starts_at} onChange={e => setDelegate(v => ({ ...v, starts_at: e.target.value }))}/></Field><Field label="Until"><Input type="date" min={delegate.starts_at} value={delegate.ends_at} onChange={e => setDelegate(v => ({ ...v, ends_at: e.target.value }))}/></Field><Field label="Reason"><Input value={delegate.reason} onChange={e => setDelegate(v => ({ ...v, reason: e.target.value }))} placeholder="Vacation cover"/></Field><Button loading={saving} disabled={!delegate.delegate_member_id || delegate.ends_at < delegate.starts_at} onClick={() => void saveDelegation()}><Send size={13}/> Delegate</Button></Grid></Stack></CardBody></Card><DataGrid rows={delegations} columns={delegationColumns} rowKey={row => row.id} persistKey="approvals.delegations" onRefresh={loadDelegations} defaultSort={{ id: 'window', direction: 'desc' }} empty={<EmptyState title="No approval delegations." text="Create one above when temporary coverage is required."/>}/></>}
  {tab === 'audit' && payload?.can_view_audit && <DataGrid rows={audit} columns={auditColumns} rowKey={row => row.id} persistKey="approvals.audit" onRefresh={loadAudit} defaultSort={{ id: 'when', direction: 'desc' }} empty={<EmptyState title="No approval decisions recorded yet." text="Decision history will appear here as requests are reviewed."/>}/>}
  <Modal open={!!selected} onClose={() => setSelected(null)} title={selected?.title || 'Review request'} description={selected?.summary || undefined} footer={<><Button variant="outline" onClick={() => setSelected(null)}>Close</Button><Button variant="danger" loading={saving} onClick={() => void decide('rejected')}><X size={13}/> Reject</Button><Button loading={saving} onClick={() => void decide('approved')}><Check size={13}/> Approve</Button></>}><Stack gap={14}>{selected && <><Grid columns="1fr 1fr" gap={10}><Card><div className="ui-card-description">Requester</div><Box weight={650}>{person(selected.requester)}</Box></Card><Card><div className="ui-card-description">Due</div><Box weight={650}>{when(selected.due_at)}</Box></Card></Grid><div><Box weight={650} mb={8}>Approval chain</Box>{selected.steps.map(step => <Box key={step.id} display="flex" align="center" gap={10} p="8px 0" borderBottom="1px solid var(--border-muted)"><Clock3 size={14}/><Box as="span" flex={1}>{step.position}. {step.name}</Box><Badge tone={statusTone(step.status)}>{step.status}</Badge></Box>)}</div></>}<Field label="Decision note"><Textarea rows={4} value={decisionNote} onChange={e => setDecisionNote(e.target.value)} placeholder="Optional note for requester and audit trail"/></Field></Stack></Modal>
  <FormDialog open={workflowOpen} onClose={() => setWorkflowOpen(false)} title={editWorkflow ? 'Edit workflow' : 'New workflow'} description="Define matching conditions, approvers and escalation behavior." size="lg" formId="approval-workflow-form" onSubmit={event => { event.preventDefault(); void saveWorkflow(); }} submitLabel="Save Workflow" loading={saving}><Stack gap={14}><Grid columns="1fr 1fr" gap={12}><Field label="Name"><Input value={wf.name} onChange={e => setWf(v => ({ ...v, name: e.target.value }))}/></Field><Field label="Trigger"><Select value={wf.trigger_key} onChange={e => setWf(v => ({ ...v, trigger_key: e.target.value }))}>{triggers.map(t => <Option key={t.key} value={t.key}>{t.label}</Option>)}</Select></Field><Field label="SLA hours"><Input type="number" min={1} value={wf.sla_hours} onChange={e => setWf(v => ({ ...v, sla_hours: Number(e.target.value) }))}/></Field><Field label="Priority"><Input type="number" min={1} value={wf.priority} onChange={e => setWf(v => ({ ...v, priority: Number(e.target.value) }))}/></Field><Field label="Escalation role"><Select value={wf.escalation_role_slug} onChange={e => setWf(v => ({ ...v, escalation_role_slug: e.target.value }))}><Option value="">No escalation role</Option>{roles.map(r => <Option key={r.id} value={r.slug}>{r.name}</Option>)}</Select></Field><Field label="Status"><Select value={wf.status} onChange={e => setWf(v => ({ ...v, status: e.target.value as 'active' | 'inactive' }))}><Option value="active">Active</Option><Option value="inactive">Inactive</Option></Select></Field></Grid><Field label="Description"><Textarea rows={2} value={wf.description} onChange={e => setWf(v => ({ ...v, description: e.target.value }))}/></Field><div><Inline justify="space-between" align="center" mb={8}><strong>Conditions</strong><Button size="sm" variant="outline" onClick={() => setWf(v => ({ ...v, conditions: [...v.conditions, { field: 'amount', operator: 'gte', value: '' }] }))}><Plus size={13}/> Condition</Button></Inline>{!wf.conditions.length && <Box className="ui-card-description" mb={8}>No conditions: this workflow can match every request for the selected trigger.</Box>}{wf.conditions.map((condition, index) => <Card key={index}><Grid columns="1fr 140px 1fr auto" gap={10} align="end"><Field label="Field"><Select value={condition.field} onChange={e => setWf(v => ({ ...v, conditions: v.conditions.map((c, i) => i === index ? { ...c, field: e.target.value } : c) }))}>{['amount', 'currency', 'department_id', 'team_id', 'project_id', 'cost_center_id', 'category', 'request_type', 'leave_type_id'].map(f => <Option key={f} value={f}>{f}</Option>)}</Select></Field><Field label="Operator"><Select value={condition.operator} onChange={e => setWf(v => ({ ...v, conditions: v.conditions.map((c, i) => i === index ? { ...c, operator: e.target.value as ConditionDef['operator'] } : c) }))}>{['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in'].map(op => <Option key={op} value={op}>{op}</Option>)}</Select></Field><Field label="Value"><Input value={condition.value} onChange={e => setWf(v => ({ ...v, conditions: v.conditions.map((c, i) => i === index ? { ...c, value: e.target.value } : c) }))} placeholder={condition.operator === 'in' ? 'a,b,c' : 'Value'}/></Field><Button variant="ghost" iconOnly aria-label="Remove condition" onClick={() => setWf(v => ({ ...v, conditions: v.conditions.filter((_, i) => i !== index) }))}><Trash2 size={14}/></Button></Grid></Card>)}</div><Switch checked={wf.notify_requester} onChange={checked => setWf(v => ({ ...v, notify_requester: checked }))} label="Notify requester as workflow progresses"/><div><Inline justify="space-between" align="center" mb={8}><strong>Approval steps</strong><Button size="sm" variant="outline" onClick={() => setWf(v => ({ ...v, steps: [...v.steps, blankStep()] }))}><Plus size={13}/> Step</Button></Inline>{wf.steps.map((step, index) => <Card key={index}><Grid columns="1.3fr 1fr 1.2fr 100px auto" gap={10} align="end"><Field label={`Step ${index + 1}`}><Input value={step.name} onChange={e => setWf(v => ({ ...v, steps: v.steps.map((s, i) => i === index ? { ...s, name: e.target.value } : s) }))}/></Field><Field label="Approver"><Select value={step.approver_type} onChange={e => setWf(v => ({ ...v, steps: v.steps.map((s, i) => i === index ? { ...s, approver_type: e.target.value as StepDef['approver_type'] } : s) }))}><Option value="manager">Requester manager</Option><Option value="role">Role</Option><Option value="member">Specific member</Option></Select></Field>{step.approver_type === 'role' ? <Field label="Role"><Select value={step.approver_role_slug} onChange={e => setWf(v => ({ ...v, steps: v.steps.map((s, i) => i === index ? { ...s, approver_role_slug: e.target.value } : s) }))}><Option value="">Select role</Option>{roles.map(r => <Option key={r.id} value={r.slug}>{r.name}</Option>)}</Select></Field> : step.approver_type === 'member' ? <Field label="Member"><Select value={step.approver_member_id} onChange={e => setWf(v => ({ ...v, steps: v.steps.map((s, i) => i === index ? { ...s, approver_member_id: e.target.value } : s) }))}><Option value="">Select member</Option>{people.map(p => <Option key={p.id} value={p.id}>{person(p)}</Option>)}</Select></Field> : <div />}<Field label="Required"><Input type="number" min={1} value={step.required_approvals} onChange={e => setWf(v => ({ ...v, steps: v.steps.map((s, i) => i === index ? { ...s, required_approvals: Number(e.target.value) } : s) }))}/></Field><Button variant="ghost" iconOnly aria-label="Remove step" disabled={wf.steps.length === 1} onClick={() => setWf(v => ({ ...v, steps: v.steps.filter((_, i) => i !== index) }))}><Trash2 size={14}/></Button></Grid><Box mt={10}><Switch checked={step.allow_self_approval} onChange={checked => setWf(v => ({ ...v, steps: v.steps.map((s, i) => i === index ? { ...s, allow_self_approval: checked } : s) }))} label="Allow requester to approve their own request at this step"/></Box></Card>)}</div></Stack></FormDialog>
 </Page>;
}
