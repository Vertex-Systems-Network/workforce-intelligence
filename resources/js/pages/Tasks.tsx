import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Activity, CalendarDays, Check, CheckCircle2, CheckSquare2, ChevronDown, ChevronRight, ChevronUp, Circle, Download, Ellipsis, Eye, FileText, GitBranch, LayoutGrid, List, MessageSquare, Paperclip, Pencil, Play, Plus, Repeat2, Search, Settings2, Tag, Trash2, Unlink, Upload, UsersRound, X } from 'lucide-react';
import { apiDownload, apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { hasAnyPermission, hasPermission } from '../access';
import { useConfirmAction, EmptyState, ErrorState, Alert, Avatar, Badge, Button, Card, CardBody, DataGrid, Drawer, Dropdown, Field, FormSection, Input, Modal, Page, PageHeader, Progress, SearchInput, Segmented, Select, Switch, Textarea, ViewModeToggle, type DataGridColumn, Pressable, Inline, Form, Option, FilterBar, StatCard, Grid, Stack, Text, FormDialog, ChoiceList, ChoiceRow, SettingRow, Checkbox, Box } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
import RichTextEditor from '../components/RichTextEditor';
import TaskBoard from '../components/TaskBoard';
import { MediaFileField } from '../media/MediaFileField';
import { Member, Project, Task, TaskChecklistItem, TaskDependency, TaskDetails, TaskRelation, TaskStatus, TaskTag, WorkflowPayload, memberLabel } from '../task-engine/types';
import './tasks-v2.css';
import { useShellEntitySearch } from '../shellEntityFocus';
import WorkflowManager from './tasks/WorkflowManager';
import { type Person, type TaskForm, MultiMemberPicker, TagPicker, emptyForm, formatMinutes, htmlDate, personName, toneForPriority, toggleId } from './tasks/support';
/** Handles the tasks operation for the WorkIntel client. */ export default function Tasks() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const workspace = session?.user.workspaces.find(w => w.id === workspaceId);
    const canManage = hasAnyPermission(workspace, ['tasks.manage', 'tasks.manage_team']);
    const canWorkflow = hasPermission(workspace, 'tasks.workflow_manage');
    const [tasks, setTasks] = useState<Task[]>([]);
    const [workflow, setWorkflow] = useState<WorkflowPayload>({ statuses: [], tags: [], can_manage_workflow: false });
    const [projects, setProjects] = useState<Project[]>([]);
    const [people, setPeople] = useState<Person[]>([]);
    const [view, setView] = useState<'list' | 'board'>('board');
    const [scope, setScope] = useState<'all' | 'my'>('all');
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [projectFilter, setProjectFilter] = useState('');
    useShellEntitySearch('tasks', setSearch, () => { setScope('all'); setStatusFilter(''); setProjectFilter(''); });
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Task | null>(null);
    const [form, setForm] = useState<TaskForm>(emptyForm);
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [details, setDetails] = useState<TaskDetails | null>(null);
    const [detailsLoading, setDetailsLoading] = useState(false);
    const [workflowOpen, setWorkflowOpen] = useState(false);
    const [comment, setComment] = useState('');
    const [checkTitle, setCheckTitle] = useState('');
    const [checkAssignee, setCheckAssignee] = useState('');
    const [checkDue, setCheckDue] = useState('');
    const [subtaskTitle, setSubtaskTitle] = useState('');
    const [dependencyId, setDependencyId] = useState('');
    const [relationId, setRelationId] = useState('');
    const [uploading, setUploading] = useState(false);
    const [recurrence, setRecurrence] = useState({ frequency: 'weekly', interval: '1', starts_on: '', ends_on: '' });
    /** Loads load data required by the current view. */ const load = async (silent = false) => {
        if (!workspaceId)
            return;
        if (!silent)
            setLoading(true);
        setError('');
        try {
            const [taskPayload, workflowPayload] = await Promise.all([
                apiRequest<{
                    data: Task[];
                }>(scope === 'my' ? '/api/v1/tasks?my=1' : '/api/v1/tasks', { workspaceId, silent }), apiRequest<WorkflowPayload>('/api/v1/task-workflow', { workspaceId, silent }),
            ]);
            setTasks(taskPayload.data);
            setWorkflow(workflowPayload);
            if (canManage) {
                const [p, m] = await Promise.all([apiRequest<{
                        data: Project[];
                    }>('/api/v1/projects', { workspaceId, silent }), apiRequest<{
                        data: Person[];
                    }>('/api/v1/people', { workspaceId, silent })]);
                setProjects(p.data);
                setPeople(m.data);
            }
            else {
                setProjects([]);
                setPeople([]);
            }
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not load tasks.');
        }
        finally {
            if (!silent)
                setLoading(false);
        }
    };
    useEffect(() => { void load(); }, [workspaceId, canManage, scope]);
    const filtered = useMemo(() => tasks.filter(task => {
        if (statusFilter && String(task.task_status_id) !== statusFilter)
            return false;
        if (projectFilter && String(task.project_id) !== projectFilter)
            return false;
        const needle = search.toLowerCase().trim();
        if (!needle)
            return true;
        return [task.title, task.project?.name ?? '', ...task.assignees.map(memberLabel), ...task.observers.map(memberLabel), ...task.tags.map(t => t.name)].some(v => v.toLowerCase().includes(needle));
    }), [tasks, statusFilter, projectFilter, search]);
    const taskHealth = useMemo(() => { const open = tasks.filter(task => !task.completed_at && !task.workflow_status?.is_completed); return { open: open.length, overdue: open.filter(task => task.due_at && new Date(task.due_at).getTime() < Date.now()).length, urgent: open.filter(task => task.priority === 'high' || task.priority === 'critical').length, unassigned: open.filter(task => !task.assignees.length).length }; }, [tasks]);
    /** Handles the open create operation for the WorkIntel client. */ const openCreate = (status?: TaskStatus) => { const defaultStatus = status ?? workflow.statuses.find(s => s.is_default) ?? workflow.statuses[0]; setEditing(null); setForm({ ...emptyForm, project_id: projects[0] ? String(projects[0].id) : '', status_id: defaultStatus ? String(defaultStatus.id) : '' }); setModalOpen(true); setError(''); };
    /** Handles the open edit operation for the WorkIntel client. */ const openEdit = (task: Task) => { setEditing(task); setForm({ project_id: String(task.project_id), parent_id: task.parent_id ? String(task.parent_id) : '', owner_member_id: task.owner_member_id ? String(task.owner_member_id) : '', title: task.title, description_html: task.description_html ?? '', status_id: task.task_status_id ? String(task.task_status_id) : '', priority: task.priority, estimated_hours: task.estimated_minutes != null ? String(task.estimated_minutes / 60) : '', start_at: htmlDate(task.start_at), due_at: htmlDate(task.due_at), billable: task.billable, client_visible: task.client_visible, assignee_ids: task.assignees.map(x => x.id), observer_ids: task.observers.map(x => x.id), tag_ids: task.tags.map(x => x.id) }); setModalOpen(true); setError(''); };
    /** Handles the save task operation for the WorkIntel client. */ const saveTask = async (event: FormEvent) => {
        event.preventDefault();
        if (!workspaceId)
            return;
        setSaving(true);
        setError('');
        try {
            await apiRequest(editing ? `/api/v1/tasks/${editing.id}` : '/api/v1/tasks', { method: editing ? 'PUT' : 'POST', workspaceId, body: JSON.stringify({ project_id: Number(form.project_id), parent_id: form.parent_id ? Number(form.parent_id) : null, owner_member_id: form.owner_member_id ? Number(form.owner_member_id) : null, title: form.title, description_html: form.description_html || null, status_id: Number(form.status_id), priority: form.priority, estimated_minutes: form.estimated_hours ? Math.round(Number(form.estimated_hours) * 60) : null, start_at: form.start_at || null, due_at: form.due_at || null, billable: form.billable, client_visible: form.client_visible, assignee_ids: form.assignee_ids, observer_ids: form.observer_ids, tag_ids: form.tag_ids }) });
            setModalOpen(false);
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not save task.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Moves a dependency-free task into the centralized recoverable Trash Center. */ const removeTask = async (task: Task) => {
        if (!await confirmAction({ title: 'Move task to Trash?', description: `Move “${task.title}” to Trash? You can restore it later.`, confirmLabel: 'Move to Trash', danger: true }))
            return;
        try {
            await apiRequest(`/api/v1/lifecycle/task/${task.id}/trash`, { method: 'POST', workspaceId });
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not move task to Trash.');
        }
    };
    /** Handles the move task operation for the WorkIntel client. */ const moveTask = async (task: Task, payload: {
        statusId: number;
        previousTaskId: number | null;
        nextTaskId: number | null;
    }) => {
        try {
            await apiRequest(`/api/v1/tasks/${task.id}/move`, { method: 'PATCH', workspaceId, body: JSON.stringify({ status_id: payload.statusId, previous_task_id: payload.previousTaskId, next_task_id: payload.nextTaskId }) });
            await load(true);
        }
        catch (err) {
            const message = err instanceof Error ? err.message : 'Could not move task.';
            setError(message);
            throw err;
        }
    };
    /** Handles the start timer operation for the WorkIntel client. */ const startTimer = async (task: Task) => {
        try {
            await apiRequest('/api/v1/timer/start', { method: 'POST', workspaceId, body: JSON.stringify({ project_id: task.project_id, task_id: task.id, billable: task.billable, note: task.title }) });
            window.dispatchEvent(new CustomEvent('workintel:timer-changed'));
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not start timer.');
        }
    };
    /** Loads load details data required by the current view. */ const loadDetails = async (task: Task) => {
        setDetailsOpen(true);
        setDetailsLoading(true);
        setError('');
        try {
            const payload = await apiRequest<{
                data: TaskDetails;
            }>(`/api/v1/tasks/${task.id}/details`, { workspaceId });
            setDetails(payload.data);
            const r = payload.data.recurrence;
            setRecurrence({ frequency: r?.frequency ?? 'weekly', interval: String(r?.interval ?? 1), starts_on: r?.starts_on?.slice(0, 10) ?? new Date().toISOString().slice(0, 10), ends_on: r?.ends_on?.slice(0, 10) ?? '' });
            setDependencyId('');
            setRelationId('');
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not load task details.');
        }
        finally {
            setDetailsLoading(false);
        }
    };
    /** Handles the refresh details operation for the WorkIntel client. */ const refreshDetails = async () => {
        if (!details)
            return;
        const p = await apiRequest<{
            data: TaskDetails;
        }>(`/api/v1/tasks/${details.id}/details`, { workspaceId, silent: true });
        setDetails(p.data);
    };
    /** Handles the mutate details operation for the WorkIntel client. */ const mutateDetails = async (path: string, method: string, body?: unknown) => {
        if (!details)
            return;
        setSaving(true);
        try {
            await apiRequest(path, { method, workspaceId, body: body === undefined ? undefined : JSON.stringify(body) });
            await Promise.all([refreshDetails(), load(true)]);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Task action failed.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the toggle checklist operation for the WorkIntel client. */ const toggleChecklist = (item: TaskChecklistItem) => mutateDetails(`/api/v1/tasks/${details!.id}/checklist/${item.id}`, 'PUT', { is_completed: !item.is_completed });
    /** Handles the add checklist operation for the WorkIntel client. */ const addChecklist = async () => {
        if (!details || !checkTitle.trim())
            return;
        await mutateDetails(`/api/v1/tasks/${details.id}/checklist`, 'POST', { title: checkTitle.trim(), assignee_member_id: checkAssignee ? Number(checkAssignee) : null, due_at: checkDue || null });
        setCheckTitle('');
        setCheckAssignee('');
        setCheckDue('');
    };
    /** Handles the add comment operation for the WorkIntel client. */ const addComment = async () => {
        if (!details || !comment.trim())
            return;
        await mutateDetails(`/api/v1/tasks/${details.id}/comments`, 'POST', { body: comment.trim() });
        setComment('');
    };
    /** Handles the add subtask operation for the WorkIntel client. */ const addSubtask = async () => {
        if (!details || !subtaskTitle.trim())
            return;
        const defaultStatus = workflow.statuses.find(x => x.is_default) ?? workflow.statuses[0];
        await mutateDetails(`/api/v1/tasks/${details.id}/subtasks`, 'POST', { title: subtaskTitle.trim(), status_id: defaultStatus?.id, priority: details.priority });
        setSubtaskTitle('');
    };
    /** Handles the add dependency operation for the WorkIntel client. */ const addDependency = async () => {
        if (details && dependencyId) {
            await mutateDetails(`/api/v1/tasks/${details.id}/dependencies`, 'POST', { depends_on_task_id: Number(dependencyId), type: 'finish_to_start' });
            setDependencyId('');
        }
    };
    /** Handles the add relation operation for the WorkIntel client. */ const addRelation = async () => {
        if (details && relationId) {
            await mutateDetails(`/api/v1/tasks/${details.id}/relations`, 'POST', { target_task_id: Number(relationId), type: 'related' });
            setRelationId('');
        }
    };
    /** Handles the save recurrence operation for the WorkIntel client. */ const saveRecurrence = () => details && mutateDetails(`/api/v1/tasks/${details.id}/recurrence`, 'PUT', { frequency: recurrence.frequency, interval: Number(recurrence.interval), starts_on: recurrence.starts_on, ends_on: recurrence.ends_on || null, active: true });
    /** Handles the upload attachment operation for the WorkIntel client. */ const uploadAttachment = async (file: File) => {
        if (!details)
            return;
        setUploading(true);
        try {
            const data = new FormData();
            data.append('file', file);
            await apiRequest(`/api/v1/tasks/${details.id}/attachments`, { method: 'POST', workspaceId, body: data });
            await refreshDetails();
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Upload failed.');
        }
        finally {
            setUploading(false);
        }
    };
    /** Handles the download attachment operation for the WorkIntel client. */ const downloadAttachment = async (id: number, name: string) => { const { blob, filename } = await apiDownload(`/api/v1/tasks/${details!.id}/attachments/${id}/download`, workspaceId); const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = filename || name; a.click(); URL.revokeObjectURL(url); };
    /** Define list-view columns for the shared DataGrid V2 without changing Kanban behavior. */
    const taskColumns: DataGridColumn<Task>[] = [
        { id: 'task', header: 'Task', searchValue: task => `${task.title} ${task.project?.name ?? ''} ${task.assignees.map(memberLabel).join(' ')} ${task.tags.map(tag => tag.name).join(' ')}`, sortValue: task => task.title, cell: task => <><Pressable className="task-title-link" onClick={() => void loadDetails(task)}>{task.title}</Pressable><div className="ui-card-description">{task.project?.name} · {formatMinutes(task.estimated_minutes)}</div></> },
        { id: 'status', header: 'Status', filterValue: task => String(task.task_status_id ?? ''), filter: { type: 'select', label: 'Status', options: workflow.statuses.map(status => ({ value: String(status.id), label: status.name })) }, cell: task => <span className="task-status-pill"><Box as="i" bg={task.workflow_status?.color ?? '#64748b'}/>{task.workflow_status?.name ?? task.status}</span> },
        { id: 'priority', header: 'Priority', filterValue: task => task.priority, filter: { type: 'select', label: 'Priority', options: ['low', 'medium', 'high', 'critical'].map(value => ({ value, label: value[0].toUpperCase() + value.slice(1) })) }, cell: task => <Badge tone={toneForPriority(task.priority)}>{task.priority}</Badge> },
        { id: 'people', header: 'People', searchValue: task => task.assignees.map(memberLabel).join(' '), cell: task => <div className="task-avatar-row">{task.assignees.slice(0, 3).map(member => <Avatar key={member.id} name={memberLabel(member)} size="sm"/>)}{!task.assignees.length && <span className="task-muted">Unassigned</span>}</div> },
        { id: 'tags', header: 'Tags', searchValue: task => task.tags.map(tag => tag.name).join(' '), defaultHidden: true, cell: task => <div className="task-inline-tags">{task.tags.slice(0, 3).map(tag => <span key={tag.id}><Box as="i" bg={tag.color}/>{tag.name}</span>)}</div> },
        { id: 'due', header: 'Due', sortValue: task => task.due_at ?? '', filterValue: task => task.due_at ?? '', filter: { type: 'dateRange', label: 'Due date' }, cell: task => task.due_at ? <Inline gap={6} align="center"><span>{new Date(task.due_at).toLocaleDateString()}</span>{!task.completed_at && !task.workflow_status?.is_completed && new Date(task.due_at).getTime() < Date.now() && <Badge tone="danger">Overdue</Badge>}</Inline> : '—' },
        { id: 'checklist', header: 'Checklist', sortValue: task => task.checklist_total ? ((task.checklist_completed ?? 0) / task.checklist_total) : 0, cell: task => { const total = task.checklist_total ?? 0, done = task.checklist_completed ?? 0; return total ? <div className="task-check-progress"><Progress value={done / total * 100}/><span>{done}/{total}</span></div> : '—'; } },
        { id: 'actions', header: '', hideable: false, cell: task => <Dropdown trigger={<Button variant="ghost" size="sm" iconOnly aria-label={`Actions for ${task.title}`}><Ellipsis size={14}/></Button>} items={[{ label: 'Open task', icon: <Eye size={13}/>, onClick: () => void loadDetails(task) }, { label: 'Start timer', icon: <Play size={13}/>, onClick: () => void startTimer(task) }, ...(canManage ? [{ label: 'Edit task', icon: <Pencil size={13}/>, onClick: () => openEdit(task) }, { separator: true }, { label: 'Move to Trash', icon: <Trash2 size={13}/>, danger: true, onClick: () => void removeTask(task) }] : [])]}/> },
    ];
    if (loading && !tasks.length)
        return <Page><PageLoadingState title="Loading Task Engine…"/></Page>;
    if (error && !tasks.length)
        return <Page><PageHeader title="Tasks" description="Task data could not be loaded."/><ErrorState text={error} retry={() => load()}/></Page>;
    return <Page>
    <PageHeader title="Tasks" description="Workflow-driven work management with custom statuses, drag-and-drop, ownership, observers, tags, checklists and dependencies." actions={<div className="task-header-actions">{canWorkflow && <Button variant="secondary" onClick={() => setWorkflowOpen(true)}><Settings2 size={14}/> Workflow</Button>}{canManage && <Button variant="primary" onClick={() => openCreate()}><Plus size={14}/> New task</Button>}</div>}/>
    {error && <Alert tone="danger">{error}</Alert>}
    <Grid columns="repeat(auto-fit,minmax(150px,1fr))" gap={9}><StatCard label="Open tasks" value={String(taskHealth.open)} sub={scope === 'my' ? 'Your current scope' : 'Visible workspace scope'}/><StatCard label="Overdue" value={String(taskHealth.overdue)} sub="Needs date attention"/><StatCard label="High priority" value={String(taskHealth.urgent)} sub="High + critical"/><StatCard label="Unassigned" value={String(taskHealth.unassigned)} sub="Open work without assignees"/></Grid>
    <FilterBar primary={<SearchInput value={search} onChange={e => setSearch(e.target.value)} placeholder="Search tasks, people or tags…"/>} filters={<><Select value={projectFilter} onChange={e => setProjectFilter(e.target.value)}><Option value="">All projects</Option>{[...new Map(tasks.map(t => [t.project.id, t.project])).values()].map(p => <Option key={p.id} value={p.id}>{p.name}</Option>)}</Select><Select value={statusFilter} onChange={e => setStatusFilter(e.target.value)}><Option value="">All statuses</Option>{workflow.statuses.map(s => <Option key={s.id} value={s.id}>{s.name}</Option>)}</Select></>} actions={<Inline gap={8} wrap="wrap"><Segmented value={scope} onChange={setScope} ariaLabel="Task scope" options={[{ value: 'all', label: 'All tasks' }, { value: 'my', label: <><UsersRound size={13}/> My tasks</> }]}/><ViewModeToggle value={view === 'board' ? 'grid' : 'table'} onChange={value => setView(value === 'grid' ? 'board' : 'list')} gridLabel="Board" tableLabel="List" ariaLabel="Task view"/></Inline>}/>

    {view === 'board' ? <TaskBoard tasks={filtered} statuses={workflow.statuses} canManage={canManage} onOpen={task => void loadDetails(task)} onMove={moveTask}/> : <DataGrid rows={filtered} columns={taskColumns} rowKey={task => task.id} persistKey="tasks.list" searchable={false} onRefresh={() => load(true)} defaultSort={{ id: 'due', direction: 'asc' }} empty={<EmptyState title="No tasks yet." text="Create a task or switch to the board to add work."/>} filteredEmpty={<EmptyState title="No tasks match the active filters." text="Clear project, status, search or table filters."/>} mobileCard={task => <div><Inline justify="space-between" gap={8}><Pressable className="task-title-link" onClick={() => void loadDetails(task)}>{task.title}</Pressable><Badge tone={toneForPriority(task.priority)}>{task.priority}</Badge></Inline><div className="ui-card-description">{task.project?.name} · {task.workflow_status?.name ?? task.status}</div><Inline justify="space-between" mt={8}><span>{task.assignees.length ? `${task.assignees.length} assignee(s)` : 'Unassigned'}</span><span>{task.due_at ? new Date(task.due_at).toLocaleDateString() : 'No due date'}</span></Inline></div>}/>}

    <FormDialog open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit task' : 'Create task'} description="Use workflow status, multiple assignees, observers and tags to define ownership and visibility." size="lg" formId="p5-task-submit" onSubmit={saveTask} submitLabel={editing ? 'Save changes' : 'Create task'} loading={saving}>
      <Stack gap={14} className="task-form-grid"><FormSection title="Task details" description="Define the work item and its project context."><Field label="Title"><Input required value={form.title} onChange={e => setForm({ ...form, title: e.target.value })}/></Field><div className="task-form-row task-form-row--two"><Field label="Project"><Select required value={form.project_id} onChange={e => setForm({ ...form, project_id: e.target.value, parent_id: '' })}><Option value="">Select project…</Option>{projects.map(p => <Option key={p.id} value={p.id}>{p.name}</Option>)}</Select></Field><Field label="Parent task"><Select value={form.parent_id} onChange={e => setForm({ ...form, parent_id: e.target.value })}><Option value="">No parent</Option>{tasks.filter(t => t.project_id === Number(form.project_id) && t.id !== editing?.id).map(t => <Option key={t.id} value={t.id}>{t.title}</Option>)}</Select></Field></div><Field label="Description"><RichTextEditor value={form.description_html} onChange={description_html => setForm({ ...form, description_html })}/></Field></FormSection><FormSection title="Ownership & workflow" description="Set responsibility, status, watchers and classification."><div className="task-form-row"><Field label="Status"><Select required value={form.status_id} onChange={e => setForm({ ...form, status_id: e.target.value })}>{workflow.statuses.map(s => <Option key={s.id} value={s.id}>{s.name}</Option>)}</Select></Field><Field label="Priority"><Select value={form.priority} onChange={e => setForm({ ...form, priority: e.target.value as Task['priority'] })}><Option value="low">Low</Option><Option value="medium">Medium</Option><Option value="high">High</Option><Option value="critical">Critical</Option></Select></Field><Field label="Task owner"><Select value={form.owner_member_id} onChange={e => setForm({ ...form, owner_member_id: e.target.value })}><Option value="">No owner</Option>{people.map(p => <Option key={p.id} value={p.id}>{personName(p)}</Option>)}</Select></Field></div><MultiMemberPicker label="Assignees" people={people} selected={form.assignee_ids} onChange={assignee_ids => setForm({ ...form, assignee_ids })}/><MultiMemberPicker label="Observers / watchers" people={people} selected={form.observer_ids} onChange={observer_ids => setForm({ ...form, observer_ids })}/><TagPicker tags={workflow.tags} selected={form.tag_ids} onChange={tag_ids => setForm({ ...form, tag_ids })}/></FormSection><FormSection title="Planning" description="Estimate effort and choose the working window."><div className="task-form-row"><Field label="Estimate (hours)"><Input type="number" min="0" step="0.25" value={form.estimated_hours} onChange={e => setForm({ ...form, estimated_hours: e.target.value })}/></Field><Field label="Start"><Input type="datetime-local" value={form.start_at} onChange={e => setForm({ ...form, start_at: e.target.value })}/></Field><Field label="Due"><Input type="datetime-local" min={form.start_at || undefined} value={form.due_at} onChange={e => setForm({ ...form, due_at: e.target.value })}/></Field></div></FormSection><FormSection title="Billing & client visibility" description="Control downstream time billing and Client Portal exposure."><SettingRow title="Billable time" description="Timer sessions inherit this setting." control={<Switch checked={form.billable} onChange={billable => setForm({ ...form, billable })} label="Billable"/>}/><SettingRow title="Visible to client" description="Expose title/status in Client Portal only when appropriate." control={<Switch checked={form.client_visible} onChange={client_visible => setForm({ ...form, client_visible })} label="Client visible"/>}/></FormSection></Stack>
    </FormDialog>

    <Drawer open={detailsOpen} onClose={() => setDetailsOpen(false)} title={details?.title ?? 'Task'} description={details?.project?.name ?? 'Task details'}>
      {detailsLoading || !details ? <EmptyState title="Loading task details…"/> : <div className="task-details">
        <div className="task-details__summary"><span className="task-status-pill"><Box as="i" bg={details.workflow_status?.color ?? '#64748b'}/>{details.workflow_status?.name ?? details.status}</span><Badge tone={toneForPriority(details.priority)}>{details.priority}</Badge>{details.owner && <span className="task-person"><Avatar name={memberLabel(details.owner)} size="sm"/>Owner: {memberLabel(details.owner)}</span>}{canManage && <Button size="sm" variant="secondary" onClick={() => openEdit(details)}><Pencil size={13}/> Edit</Button>}</div>
        {details.description_html ? <div className="task-rich-output" dangerouslySetInnerHTML={{ __html: details.description_html }}/> : <div className="task-muted">No description.</div>}
        <section><h4><UsersRound size={14}/> People</h4><div className="task-detail-people"><div><span>Assignees</span>{details.assignees.length ? details.assignees.map(m => <span key={m.id} className="task-person"><Avatar name={memberLabel(m)} size="sm"/>{memberLabel(m)}</span>) : <span className="task-muted">None</span>}</div><div><span>Observers</span>{details.observers.length ? details.observers.map(m => <span key={m.id} className="task-person"><Eye size={12}/>{memberLabel(m)}</span>) : <span className="task-muted">None</span>}</div></div></section>
        <section><h4><CheckSquare2 size={14}/> Checklist <span>{details.checklist_items.filter(x => x.is_completed).length}/{details.checklist_items.length}</span></h4>{canManage && <div className="task-checklist-create"><Input value={checkTitle} onChange={e => setCheckTitle(e.target.value)} placeholder="Checklist item…"/><Select value={checkAssignee} onChange={e => setCheckAssignee(e.target.value)}><Option value="">No assignee</Option>{people.map(p => <Option key={p.id} value={p.id}>{personName(p)}</Option>)}</Select><Input type="date" value={checkDue} onChange={e => setCheckDue(e.target.value)}/><Button size="sm" variant="secondary" loading={saving} onClick={() => void addChecklist()}><Plus size={13}/> Add</Button></div>}<div className="task-checklist">{details.checklist_items.map(item => <div key={item.id}><Pressable type="button" onClick={() => void toggleChecklist(item)} className={item.is_completed ? 'is-complete' : ''}>{item.is_completed ? <CheckCircle2 size={16}/> : <Circle size={16}/>}<span>{item.title}</span></Pressable>{item.assignee && <small>{memberLabel(item.assignee)}</small>}{item.due_at && <small>{new Date(item.due_at).toLocaleDateString()}</small>}{canManage && <Button variant="ghost" size="sm" iconOnly aria-label="Delete checklist item" onClick={() => void mutateDetails(`/api/v1/tasks/${details.id}/checklist/${item.id}`, 'DELETE')}><X size={12}/></Button>}</div>)}{!details.checklist_items.length && <span className="task-muted">No checklist items.</span>}</div></section>
        <section><h4><GitBranch size={14}/> Hierarchy & dependencies</h4>{details.parent && <div className="task-linked-row"><ChevronRight size={13}/><span>Parent: {details.parent.title}</span></div>}{canManage && <div className="task-inline-form"><Input value={subtaskTitle} onChange={e => setSubtaskTitle(e.target.value)} placeholder="New subtask…"/><Button size="sm" variant="secondary" onClick={() => void addSubtask()}><Plus size={13}/> Subtask</Button></div>}<div className="task-linked-list">{details.subtasks.map(t => <Pressable key={t.id} onClick={() => void loadDetails(t)}><Box as="span" className="task-status-dot" bg={t.workflow_status?.color}/>{t.title}</Pressable>)}</div>{canManage && <div className="task-inline-form"><Select value={dependencyId} onChange={e => setDependencyId(e.target.value)}><Option value="">Prerequisite task…</Option>{tasks.filter(t => t.id !== details.id && !details.dependencies.some(d => d.depends_on_task.id === t.id)).map(t => <Option key={t.id} value={t.id}>{t.title}</Option>)}</Select><Button size="sm" variant="secondary" disabled={!dependencyId} onClick={() => void addDependency()}><Plus size={13}/> Dependency</Button></div>}<div className="task-linked-list">{details.dependencies.map((d: TaskDependency) => <div key={d.id}><span>Blocked by: {d.depends_on_task.title}</span>{canManage && <Button variant="ghost" size="sm" iconOnly aria-label="Remove dependency" onClick={() => void mutateDetails(`/api/v1/tasks/${details.id}/dependencies/${d.id}`, 'DELETE')}><Unlink size={12}/></Button>}</div>)}{details.dependents.map(d => <div key={`dependent-${d.id}`}><span>Blocks: {d.task.title}</span></div>)}</div></section>
        <section><h4><GitBranch size={14}/> Related tasks</h4>{canManage && <div className="task-inline-form"><Select value={relationId} onChange={e => setRelationId(e.target.value)}><Option value="">Related task…</Option>{tasks.filter(t => t.id !== details.id).map(t => <Option key={t.id} value={t.id}>{t.title}</Option>)}</Select><Button size="sm" variant="secondary" disabled={!relationId} onClick={() => void addRelation()}><Plus size={13}/> Link</Button></div>}<div className="task-linked-list">{details.relations.map((r: TaskRelation) => <div key={r.id}><span>{r.type}: {r.target_task.title}</span>{canManage && <Button variant="ghost" size="sm" iconOnly aria-label="Unlink task" onClick={() => void mutateDetails(`/api/v1/tasks/${details.id}/relations/${r.id}`, 'DELETE')}><Unlink size={12}/></Button>}</div>)}{details.inverse_relations.map(r => <div key={`inv-${r.id}`}><span>{r.type}: {r.source_task.title}</span></div>)}</div></section>
        <section><h4><Repeat2 size={14}/> Recurrence</h4>{canManage && <><div className="task-form-row"><Field label="Frequency"><Select value={recurrence.frequency} onChange={e => setRecurrence({ ...recurrence, frequency: e.target.value })}><Option value="daily">Daily</Option><Option value="weekly">Weekly</Option><Option value="monthly">Monthly</Option></Select></Field><Field label="Every"><Input type="number" min="1" max="52" value={recurrence.interval} onChange={e => setRecurrence({ ...recurrence, interval: e.target.value })}/></Field></div><div className="task-form-row"><Field label="Starts"><Input type="date" value={recurrence.starts_on} onChange={e => setRecurrence({ ...recurrence, starts_on: e.target.value })}/></Field><Field label="Ends"><Input type="date" value={recurrence.ends_on} onChange={e => setRecurrence({ ...recurrence, ends_on: e.target.value })}/></Field></div><div className="task-inline-form"><Button size="sm" variant="secondary" onClick={() => void saveRecurrence()}>{details.recurrence ? 'Update recurrence' : 'Make recurring'}</Button>{details.recurrence && <Button size="sm" variant="ghost" onClick={() => void mutateDetails(`/api/v1/tasks/${details.id}/recurrence`, 'DELETE')}>Remove</Button>}</div></>}</section>
        <section><h4><MessageSquare size={14}/> Comments</h4><div className="task-comment-compose"><Textarea value={comment} onChange={e => setComment(e.target.value)} placeholder="Add a progress note, handoff, or decision…"/><Button size="sm" variant="secondary" loading={saving} onClick={() => void addComment()}>Post</Button></div><div className="task-comments">{details.comments.map(c => <div key={c.id}><Avatar name={memberLabel(c.member)} size="sm"/><div><strong>{memberLabel(c.member)}</strong><small>{new Date(c.created_at).toLocaleString()}</small><p>{c.body}</p></div></div>)}</div></section>
        <section><h4><Paperclip size={14}/> Attachments</h4>{canManage && <MediaFileField compact workspaceId={workspaceId} label="Task attachment" disabled={uploading} onFiles={async (files) => {
                    const file = files[0];
                    if (file)
                        await uploadAttachment(file);
                }}/>}<div className="task-files">{details.attachments.map(file => <div key={file.id}><FileText size={14}/><span>{file.original_name}</span><Button size="sm" variant="ghost" iconOnly aria-label="Download" onClick={() => void downloadAttachment(file.id, file.original_name)}><Download size={12}/></Button>{canManage && <Button size="sm" variant="ghost" iconOnly aria-label="Delete attachment" onClick={() => void mutateDetails(`/api/v1/tasks/${details.id}/attachments/${file.id}`, 'DELETE')}><Trash2 size={12}/></Button>}</div>)}</div></section>
        <section><h4><Activity size={14}/> Activity</h4><div className="task-activity">{details.activities.map(a => <div key={a.id}><span className="task-activity__dot"/><div><strong>{a.action.replace(/_/g, ' ')}</strong><span>{a.actor ? memberLabel(a.actor) : 'System'} · {new Date(a.created_at).toLocaleString()}</span></div></div>)}</div></section>
      </div>}
    </Drawer>

    <WorkflowManager open={workflowOpen} onClose={() => setWorkflowOpen(false)} workspaceId={workspaceId} workflow={workflow} onChanged={() => load(true)}/>
  </Page>;
}
