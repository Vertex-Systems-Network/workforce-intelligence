import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Archive, CalendarDays, DollarSign, Ellipsis, Pencil, Plus, Receipt, Search, Trash2, TrendingUp } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { hasAnyPermission, hasPermission } from '../access';
import { useConfirmAction, FormDialog, FilterBar, EmptyState, ErrorState, Alert, Badge, Button, Card, CardBody, DataGrid, Drawer, Dropdown, Field, Input, Page, PageHeader, Progress, SearchInput, Select, StatCard, Switch, Textarea, ViewModeToggle, type DataGridColumn, Pressable, Checkbox, ChoiceList, ChoiceRow, SettingRow, Box, Grid, Inline, Stack, Text, Option } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
import { useShellEntitySearch } from '../shellEntityFocus';
type Client = {
    id: number;
    name: string;
};
type Person = {
    id: number;
    name: string;
};
type ProjectMember = {
    id: number;
    user: {
        first_name: string;
        last_name: string;
    };
};
type Project = {
    id: number;
    client_id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    status: string;
    priority: string;
    start_date: string | null;
    due_date: string | null;
    budget_type: string;
    budget_amount: string | null;
    estimated_minutes: number | null;
    billable: boolean;
    client_visible: boolean;
    currency: string;
    tasks_count: number;
    members_count: number;
    client: Client | null;
    members: ProjectMember[];
};
type ProjectForm = {
    client_id: string;
    name: string;
    code: string;
    description: string;
    status: string;
    priority: string;
    start_date: string;
    due_date: string;
    budget_type: string;
    budget_amount: string;
    billable: boolean;
    client_visible: boolean;
    currency: string;
    member_ids: number[];
};
type Expense = {
    id: number;
    name: string;
    category: string;
    amount: string;
    currency: string;
    incurred_on: string;
    note: string | null;
    approval_status?: string;
};
type FinancialMember = {
    member_id: number;
    name: string;
    tracked_seconds: number;
    hourly_cost: number;
    billing_rate: number;
    labor_cost: number;
    revenue: number;
};
type Financials = {
    project: {
        id: number;
        name: string;
        budget_type: string;
        budget_amount: string | null;
        currency: string;
    };
    tracked_seconds: number;
    billable_seconds: number;
    labor_cost: number;
    expenses_total: number;
    total_cost: number;
    billable_revenue: number;
    profit: number;
    profit_margin: number;
    budget_used: number;
    budget_remaining: number | null;
    members: FinancialMember[];
    expenses: Expense[];
};
const emptyForm: ProjectForm = { client_id: '', name: '', code: '', description: '', status: 'active', priority: 'medium', start_date: '', due_date: '', budget_type: 'hours', budget_amount: '', billable: true, client_visible: true, currency: 'USD', member_ids: [] };
const statusTone: Record<string, 'success' | 'warning' | 'info' | 'neutral'> = { active: 'success', on_hold: 'warning', completed: 'info', archived: 'neutral' };
const priorityTone: Record<string, 'neutral' | 'warning' | 'danger'> = { low: 'neutral', medium: 'neutral', high: 'warning', critical: 'danger' };
/** Classify a project due date without inventing delivery progress. */ const projectDateState = (project: Project) => { if (!project.due_date || ['completed', 'archived'].includes(project.status))
    return 'none' as const; const due = new Date(project.due_date).getTime(), now = Date.now(); if (!Number.isFinite(due))
    return 'none' as const; if (due < now)
    return 'overdue' as const; if (due - now <= 7 * 86400000)
    return 'soon' as const; return 'future' as const; };
/** Handles the projects operation for the WorkIntel client. */ export default function Projects() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const workspace = session?.user.workspaces.find(item => item.id === workspaceId);
    const canManage = hasPermission(workspace, 'projects.manage');
    const canViewFinancials = hasAnyPermission(workspace, ['projects.manage', 'projects.view_all', 'projects.view']);
    const [projects, setProjects] = useState<Project[]>([]);
    const [clients, setClients] = useState<Client[]>([]);
    const [people, setPeople] = useState<Person[]>([]);
    const [search, setSearch] = useState('');
    const [view, setView] = useState<'table' | 'grid'>('table');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Project | null>(null);
    const [form, setForm] = useState<ProjectForm>(emptyForm);
    useShellEntitySearch('projects', setSearch);
    const [financialOpen, setFinancialOpen] = useState(false);
    const [financialProject, setFinancialProject] = useState<Project | null>(null);
    const [financials, setFinancials] = useState<Financials | null>(null);
    const [financialLoading, setFinancialLoading] = useState(false);
    const [expenseModal, setExpenseModal] = useState(false);
    const [expenseForm, setExpenseForm] = useState({ name: '', category: 'other', amount: '', currency: 'USD', incurred_on: new Date().toISOString().slice(0, 10), note: '' });
    /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        const projectPayload = await apiRequest<{
            data: Project[];
        }>('/api/v1/projects', { workspaceId });
        setProjects(projectPayload.data);
        if (canManage) {
            const [clientPayload, peoplePayload] = await Promise.all([apiRequest<{
                    data: Client[];
                }>('/api/v1/clients', { workspaceId }), apiRequest<{
                    data: Person[];
                }>('/api/v1/people', { workspaceId })]);
            setClients(clientPayload.data);
            setPeople(peoplePayload.data);
        }
        else {
            setClients([]);
            setPeople([]);
        }
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load projects.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    const filtered = useMemo(() => projects.filter(project => [project.name, project.code ?? '', project.client?.name ?? ''].some(value => value.toLowerCase().includes(search.toLowerCase()))), [projects, search]);
    const projectHealth = useMemo(() => ({ active: projects.filter(p => p.status === 'active').length, onHold: projects.filter(p => p.status === 'on_hold').length, overdue: projects.filter(p => projectDateState(p) === 'overdue').length, dueSoon: projects.filter(p => projectDateState(p) === 'soon').length, tasks: projects.reduce((sum, p) => sum + (p.tasks_count ?? 0), 0) }), [projects]);
    /** Handles the open create operation for the WorkIntel client. */ const openCreate = () => { setEditing(null); setForm({ ...emptyForm, member_ids: [] }); setError(''); setModalOpen(true); };
    /** Handles the open edit operation for the WorkIntel client. */ const openEdit = (p: Project) => { setEditing(p); setForm({ client_id: p.client_id ? String(p.client_id) : '', name: p.name, code: p.code ?? '', description: p.description ?? '', status: p.status, priority: p.priority, start_date: p.start_date?.slice(0, 10) ?? '', due_date: p.due_date?.slice(0, 10) ?? '', budget_type: p.budget_type, budget_amount: p.budget_amount ?? '', billable: p.billable, client_visible: p.client_visible, currency: p.currency, member_ids: p.members?.map(member => member.id) ?? [] }); setError(''); setModalOpen(true); };
    /** Handles the save operation for the WorkIntel client. */ const save = async (event: FormEvent) => { event.preventDefault(); if (!workspaceId)
        return; setSaving(true); setError(''); try {
        await apiRequest(editing ? `/api/v1/projects/${editing.id}` : '/api/v1/projects', { method: editing ? 'PUT' : 'POST', workspaceId, body: JSON.stringify({ ...form, client_id: form.client_id ? Number(form.client_id) : null, budget_amount: form.budget_amount ? Number(form.budget_amount) : null, estimated_minutes: form.budget_type === 'hours' && form.budget_amount ? Math.round(Number(form.budget_amount) * 60) : null }) });
        setModalOpen(false);
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not save project.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the archive operation for the WorkIntel client. */ const archive = async (project: Project) => { if (!workspaceId || !await confirmAction({ title: 'Archive project?', description: `Archive ${project.name}?`, confirmLabel: 'Archive', danger: true }))
        return; setError(''); try {
        await apiRequest(`/api/v1/projects/${project.id}`, { method: 'DELETE', workspaceId });
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not archive project.');
    } };
    /** Moves a dependency-free project into the centralized recoverable Trash Center. */ const trashProject = async (project: Project) => { if (!workspaceId || !await confirmAction({ title: 'Move project to Trash?', description: `Move ${project.name} to Trash? You can restore it later.`, confirmLabel: 'Move to Trash', danger: true }))
        return; setError(''); try {
        await apiRequest(`/api/v1/lifecycle/project/${project.id}/trash`, { method: 'POST', workspaceId });
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not move project to Trash.');
    } };
    /** Loads load financials data required by the current view. */ const loadFinancials = async (project: Project) => { if (!workspaceId)
        return; setFinancialProject(project); setFinancialOpen(true); setFinancialLoading(true); setError(''); try {
        const payload = await apiRequest<{
            data: Financials;
        }>(`/api/v1/projects/${project.id}/financials`, { workspaceId });
        setFinancials(payload.data);
        setExpenseForm(form => ({ ...form, currency: project.currency }));
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load project financials.');
    }
    finally {
        setFinancialLoading(false);
    } };
    /** Handles the save expense operation for the WorkIntel client. */ const saveExpense = async (event: FormEvent) => { event.preventDefault(); if (!workspaceId || !financialProject)
        return; setSaving(true); setError(''); try {
        await apiRequest(`/api/v1/projects/${financialProject.id}/expenses`, { method: 'POST', workspaceId, body: JSON.stringify({ ...expenseForm, amount: Number(expenseForm.amount), currency: expenseForm.currency.toUpperCase() }) });
        setExpenseModal(false);
        setExpenseForm({ name: '', category: 'other', amount: '', currency: financialProject.currency, incurred_on: new Date().toISOString().slice(0, 10), note: '' });
        await loadFinancials(financialProject);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not add project expense.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the remove expense operation for the WorkIntel client. */ const removeExpense = async (expense: Expense) => { if (!workspaceId || !financialProject || !await confirmAction({ title: 'Delete project expense?', description: `Delete expense ${expense.name}?`, confirmLabel: 'Delete', danger: true }))
        return; setSaving(true); try {
        await apiRequest(`/api/v1/projects/${financialProject.id}/expenses/${expense.id}`, { method: 'DELETE', workspaceId });
        await loadFinancials(financialProject);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not delete expense.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the money operation for the WorkIntel client. */ const money = (value: number, currency = financialProject?.currency ?? 'USD') => new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(value);
    /** Handles the duration operation for the WorkIntel client. */ const duration = (seconds: number) => `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`;
    /** Define project list columns for DataGrid V2 while keeping financial actions permission-aware. */
    const financialMemberColumns: DataGridColumn<FinancialMember>[] = [
        { id: 'member', header: 'Member', searchValue: member => member.name, sortValue: member => member.name, cell: member => <Text weight={600}>{member.name}</Text> },
        { id: 'tracked', header: 'Tracked', sortValue: member => member.tracked_seconds, cell: member => <Text>{duration(member.tracked_seconds)}</Text> },
        { id: 'cost_rate', header: 'Cost/hr', sortValue: member => member.hourly_cost, cell: member => <Text>{money(member.hourly_cost)}</Text> },
        { id: 'bill_rate', header: 'Bill/hr', sortValue: member => member.billing_rate, cell: member => <Text>{money(member.billing_rate)}</Text> },
        { id: 'cost', header: 'Cost', sortValue: member => member.labor_cost, cell: member => <Text>{money(member.labor_cost)}</Text> },
        { id: 'revenue', header: 'Revenue', sortValue: member => member.revenue, cell: member => <Text>{money(member.revenue)}</Text> },
    ];
    const projectColumns: DataGridColumn<Project>[] = [
        { id: 'project', header: 'Project', searchValue: p => `${p.name} ${p.code ?? ''}`, sortValue: p => p.name, cell: p => <><Box weight={600}>{p.name}</Box><div className="ui-card-description">{p.code || 'No code'} · {p.billable ? 'Billable' : 'Internal'}</div></> },
        { id: 'client', header: 'Client', searchValue: p => p.client?.name ?? '', sortValue: p => p.client?.name ?? '', cell: p => <Text color="var(--text-2)">{p.client?.name || 'Internal'}</Text> },
        { id: 'status', header: 'Status', filterValue: p => p.status, filter: { type: 'select', label: 'Status', options: Object.keys(statusTone).map(value => ({ value, label: value.replaceAll('_', ' ') })) }, cell: p => <Badge tone={statusTone[p.status] ?? 'neutral'} dot>{p.status.replace('_', ' ')}</Badge> },
        { id: 'priority', header: 'Priority', filterValue: p => p.priority, filter: { type: 'select', label: 'Priority', options: Object.keys(priorityTone).map(value => ({ value, label: value[0].toUpperCase() + value.slice(1) })) }, cell: p => <Badge tone={priorityTone[p.priority] ?? 'neutral'}>{p.priority}</Badge> },
        { id: 'tasks', header: 'Tasks', sortValue: p => p.tasks_count ?? 0, cell: p => <span className="stat-num">{p.tasks_count ?? 0}</span> },
        { id: 'members', header: 'Members', sortValue: p => p.members_count ?? 0, cell: p => <span className="stat-num">{p.members_count ?? 0}</span> },
        ...(canViewFinancials ? [{ id: 'budget', header: 'Budget', sortValue: (p: Project) => Number(p.budget_amount ?? 0), cell: (p: Project) => <Box minWidth={120}><Text weight={600}>{p.budget_amount ? `${p.budget_amount} ${p.budget_type === 'money' ? p.currency : 'hours'}` : 'No budget'}</Text><Text display="block" size={10.5} color="var(--text-3)">Actual usage is shown in Financials.</Text></Box> } as DataGridColumn<Project>] : []),
        { id: 'due', header: 'Due', sortValue: p => p.due_date ?? '', filterValue: p => p.due_date ?? '', filter: { type: 'dateRange', label: 'Due date' }, cell: p => <Inline gap={6} align="center"><CalendarDays size={13}/><Text color="var(--text-2)">{p.due_date?.slice(0, 10) || '—'}</Text>{projectDateState(p) === 'overdue' && <Badge tone="danger">Overdue</Badge>}{projectDateState(p) === 'soon' && <Badge tone="warning">Due soon</Badge>}</Inline> },
        { id: 'actions', header: '', hideable: false, cell: p => (canManage || canViewFinancials) ? <Dropdown trigger={<Button variant="ghost" size="sm" iconOnly aria-label={`Actions for ${p.name}`}><Ellipsis size={15}/></Button>} items={canManage ? [{ label: 'Project financials', icon: <DollarSign size={14}/>, onClick: () => void loadFinancials(p) }, { label: 'Edit project', icon: <Pencil size={14}/>, onClick: () => openEdit(p) }, { separator: true }, { label: 'Archive project', icon: <Archive size={14}/>, onClick: () => void archive(p) }, { label: 'Move to Trash', icon: <Trash2 size={14}/>, danger: true, onClick: () => void trashProject(p) }] : [{ label: 'Project financials', icon: <DollarSign size={14}/>, onClick: () => void loadFinancials(p) }]}/> : null },
    ];
    if (loading && !projects.length)
        return <PageLoadingState />;
    const assignedOnly = !canManage && !canViewFinancials;
    if (error && !projects.length)
        return <Page><PageHeader title={assignedOnly ? 'My Projects' : 'Projects'} description="Projects could not be loaded."/><ErrorState text={error} retry={load}/></Page>;
    return <Page><PageHeader title={assignedOnly ? 'My Projects' : 'Projects'} description={assignedOnly ? `${projects.length} projects assigned to you` : `${projects.length} active workspace projects`} actions={canManage ? <Button variant="primary" size="sm" onClick={openCreate}><Plus size={14}/> New Project</Button> : undefined}/>{error && <Alert tone="danger">{error}</Alert>}<Grid columns="repeat(auto-fit,minmax(150px,1fr))" gap={9}><StatCard label="Active" value={String(projectHealth.active)} sub={`${projectHealth.tasks} visible tasks`}/><StatCard label="On hold" value={String(projectHealth.onHold)} sub="Paused delivery"/><StatCard label="Due soon" value={String(projectHealth.dueSoon)} sub="Within 7 days"/><StatCard label="Overdue" value={String(projectHealth.overdue)} sub="Needs date attention"/></Grid><FilterBar primary={<SearchInput icon={<Search size={14}/>} value={search} onChange={e => setSearch(e.target.value)} placeholder="Search projects…"/>} actions={<ViewModeToggle value={view} onChange={setView} tableLabel="List" gridLabel="Grid" ariaLabel="Project view"/>}/>
 {view === 'table' ? <DataGrid rows={filtered} columns={projectColumns} rowKey={project => project.id} persistKey="projects" searchable={false} onRefresh={load} defaultSort={{ id: 'project', direction: 'asc' }} empty={<EmptyState title="No projects yet." text="Create the first project to start organizing work."/>} filteredEmpty={<EmptyState title="No projects match the current view." text="Clear the search or table filters to see more projects."/>} mobileCard={project => <div><Inline justify="space-between" gap={8}><strong>{project.name}</strong><Badge tone={statusTone[project.status] ?? 'neutral'}>{project.status.replace('_', ' ')}</Badge></Inline><div className="ui-card-description">{project.client?.name || 'Internal'} · {project.code || 'No code'}</div><Inline gap={12} mt={8}><span>{project.tasks_count ?? 0} tasks</span><span>{project.members_count ?? 0} members</span></Inline></div>}/> : <Grid columns="repeat(auto-fill,minmax(290px,1fr))" gap={12}>{filtered.map(project => <Card key={project.id} interactive><CardBody><Inline justify="space-between" gap={10} mb={12}><div><Box weight={650} size={14}>{project.name}</Box><div className="ui-card-description">{project.client?.name || 'Internal'} · {project.code || 'No code'}</div></div><Badge tone={statusTone[project.status] ?? 'neutral'} dot>{project.status.replace('_', ' ')}</Badge></Inline><Grid columns="1fr 1fr" gap={8} mb={13}><Box p={9} radius={7} bg="var(--bg)"><div className="ui-card-description">Tasks</div><Box className="stat-num" weight={650} mt={2}>{project.tasks_count ?? 0}</Box></Box><Box p={9} radius={7} bg="var(--bg)"><div className="ui-card-description">Members</div><Box className="stat-num" weight={650} mt={2}>{project.members_count ?? 0}</Box></Box></Grid><Inline justify="space-between" align="center"><Badge tone={priorityTone[project.priority] ?? 'neutral'}>{project.priority}</Badge><Inline gap={5}>{canViewFinancials && <Button variant="ghost" size="sm" onClick={() => void loadFinancials(project)}><DollarSign size={13}/> Financials</Button>}{canManage && <Button variant="ghost" size="sm" onClick={() => openEdit(project)}><Pencil size={13}/> Edit</Button>}</Inline></Inline></CardBody></Card>)}</Grid>}
 <FormDialog open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit project' : 'Create project'} description="Project ownership, dates, budget and billing settings." size="lg" formId="project-form-submit" onSubmit={save} submitLabel={editing ? 'Save Changes' : 'Create Project'} loading={saving}>{error && <Alert tone="danger">{error}</Alert>}<Grid columns="2fr 1fr" gap={10}><Field label="Project name"><Input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required/></Field><Field label="Code"><Input value={form.code} onChange={e => setForm({ ...form, code: e.target.value.toUpperCase() })}/></Field></Grid><Field label="Client"><Select value={form.client_id} onChange={e => setForm({ ...form, client_id: e.target.value })}><Option value="">Internal project</Option>{clients.map(client => <Option key={client.id} value={client.id}>{client.name}</Option>)}</Select></Field><Field label="Description"><Textarea value={form.description} onChange={e => setForm({ ...form, description: e.target.value })}/></Field><Field label="Project members"><ChoiceList columns={2}>{people.map(person => { const selected = form.member_ids.includes(person.id); return <ChoiceRow key={person.id} selected={selected}><Checkbox checked={selected} onChange={e => setForm({ ...form, member_ids: e.target.checked ? [...form.member_ids, person.id] : form.member_ids.filter(id => id !== person.id) })}/>{person.name}</ChoiceRow>; })}</ChoiceList></Field><Grid columns="1fr 1fr" gap={10}><Field label="Status"><Select value={form.status} onChange={e => setForm({ ...form, status: e.target.value })}><Option value="active">Active</Option><Option value="on_hold">On hold</Option><Option value="completed">Completed</Option></Select></Field><Field label="Priority"><Select value={form.priority} onChange={e => setForm({ ...form, priority: e.target.value })}><Option value="low">Low</Option><Option value="medium">Medium</Option><Option value="high">High</Option><Option value="critical">Critical</Option></Select></Field></Grid><Grid columns="1fr 1fr" gap={10}><Field label="Start date"><Input type="date" value={form.start_date} onChange={e => setForm({ ...form, start_date: e.target.value })}/></Field><Field label="Due date"><Input type="date" value={form.due_date} onChange={e => setForm({ ...form, due_date: e.target.value })}/></Field></Grid><Grid columns="1fr 1fr 1fr" gap={10}><Field label="Budget type"><Select value={form.budget_type} onChange={e => setForm({ ...form, budget_type: e.target.value })}><Option value="hours">Hours</Option><Option value="money">Money</Option><Option value="none">No budget</Option></Select></Field><Field label="Budget amount"><Input type="number" min="0" step="0.01" value={form.budget_amount} disabled={form.budget_type === 'none'} onChange={e => setForm({ ...form, budget_amount: e.target.value })}/></Field><Field label="Currency"><Input maxLength={3} value={form.currency} onChange={e => setForm({ ...form, currency: e.target.value.toUpperCase() })}/></Field></Grid><SettingRow title="Billable project" description="Time entries can be included in client billing." control={<Switch checked={form.billable} onChange={billable => setForm({ ...form, billable })} label="Billable project"/>}/><SettingRow title="Visible in Client Portal" description="When linked to a client, expose project progress in that client portal." control={<Switch checked={form.client_visible} onChange={client_visible => setForm({ ...form, client_visible })} label="Client-visible project"/>}/></FormDialog> <Drawer open={financialOpen} onClose={() => setFinancialOpen(false)} title={financialProject ? `${financialProject.name} Financials` : 'Project Financials'} description="Labor cost, billable revenue, expenses, budget use and project profitability">{financialLoading || !financials ? <EmptyState title="Loading project financials…"/> : <Stack gap={14}><Grid columns="1fr 1fr" gap={9}><StatCard label="Tracked" value={duration(financials.tracked_seconds)} sub={`Billable ${duration(financials.billable_seconds)}`}/><StatCard label="Labor Cost" value={money(financials.labor_cost)} sub="Member hourly cost"/><StatCard label="Revenue" value={money(financials.billable_revenue)} sub="Billable rate estimate"/><StatCard label="Profit" value={money(financials.profit)} sub={`${financials.profit_margin}% margin`}/></Grid><Card><CardBody><Inline justify="space-between" mb={7}><div><Box weight={650} size={12}>Budget usage</Box><div className="ui-card-description">{financials.project.budget_type === 'money' ? 'Cost budget' : 'Tracked-hour budget'}</div></div><span className="stat-num">{financials.budget_used.toFixed(1)} / {financials.project.budget_amount ?? '—'} {financials.project.budget_type === 'hours' ? 'hours' : financials.project.currency}</span></Inline>{Number(financials.project.budget_amount) > 0 && <Progress value={Math.max(0, Math.min(100, financials.budget_used / Number(financials.project.budget_amount) * 100))}/>}</CardBody></Card><section><Inline justify="space-between" align="center" mb={8}><Box display="flex" align="center" gap={6} weight={650} size={12}><Receipt size={14}/> Expenses</Box>{canManage && <Button variant="secondary" size="sm" onClick={() => setExpenseModal(true)}><Plus size={13}/> Add Expense</Button>}</Inline><Stack gap={6}>{financials.expenses.map(expense => <Box key={expense.id} display="flex" gap={8} align="center" p="8px 9px" border="1px solid var(--border)" radius={7}><Receipt size={14} color="var(--text-3)"/><Box flex={1}><Box size={12} weight={550}>{expense.name}</Box><div className="ui-card-description">{expense.category} · {expense.incurred_on.slice(0, 10)}</div></Box><Badge tone={expense.approval_status === 'approved' ? 'success' : expense.approval_status === 'rejected' ? 'danger' : 'warning'}>{expense.approval_status || 'approved'}</Badge><span className="stat-num">{money(Number(expense.amount), expense.currency)}</span>{canManage && ['rejected', 'canceled'].includes(expense.approval_status || '') && <Button variant="ghost" size="sm" iconOnly aria-label="Delete expense" onClick={() => void removeExpense(expense)}><Trash2 size={13}/></Button>}</Box>)}{!financials.expenses.length && <div className="ui-card-description">No direct project expenses.</div>}</Stack></section><section><Box display="flex" align="center" gap={6} weight={650} size={12} mb={8}><TrendingUp size={14}/> Member cost & revenue</Box><DataGrid rows={financials.members} columns={financialMemberColumns} rowKey={member => member.member_id} persistKey="projects.financials.members" searchable={false} defaultSort={{ id: 'member', direction: 'asc' }} empty={<EmptyState title="No tracked member costs yet." text="Member cost and revenue appear after time is recorded for this project."/>}/></section></Stack>}</Drawer>
 <FormDialog open={expenseModal} onClose={() => setExpenseModal(false)} title="Add project expense" description="New expenses enter the unified approval inbox and count toward project cost only after approval." formId="expense-submit" onSubmit={saveExpense} submitLabel="Add Expense" loading={saving}><Field label="Expense name"><Input value={expenseForm.name} onChange={e => setExpenseForm({ ...expenseForm, name: e.target.value })} required/></Field><Grid columns="1fr 1fr" gap={10}><Field label="Category"><Select value={expenseForm.category} onChange={e => setExpenseForm({ ...expenseForm, category: e.target.value })}><Option value="software">Software</Option><Option value="contractor">Contractor</Option><Option value="travel">Travel</Option><Option value="hardware">Hardware</Option><Option value="service">Service</Option><Option value="other">Other</Option></Select></Field><Field label="Date"><Input type="date" value={expenseForm.incurred_on} onChange={e => setExpenseForm({ ...expenseForm, incurred_on: e.target.value })}/></Field></Grid><Grid columns="2fr 1fr" gap={10}><Field label="Amount"><Input type="number" min="0.01" step="0.01" value={expenseForm.amount} onChange={e => setExpenseForm({ ...expenseForm, amount: e.target.value })} required/></Field><Field label="Currency"><Input maxLength={3} value={expenseForm.currency} onChange={e => setExpenseForm({ ...expenseForm, currency: e.target.value.toUpperCase() })}/></Field></Grid><Field label="Note"><Textarea value={expenseForm.note} onChange={e => setExpenseForm({ ...expenseForm, note: e.target.value })}/></Field></FormDialog>
 </Page>;
}
