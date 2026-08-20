import { type FormEvent, useState } from 'react';
import { Check, ChevronDown, ChevronUp, Plus, Tag, Trash2, X } from 'lucide-react';
import { apiRequest } from '../../api/client';
import { useConfirmAction, Alert, Box, Button, Field, FormDialog, Grid, Input, Modal, Option, Pressable, Select } from '../../design-system';
import type { TaskStatus, TaskTag, WorkflowPayload } from '../../task-engine/types';
import { toggleId } from './support';
/** Handles the workflow manager operation for the WorkIntel client. */ export default function WorkflowManager({ open, onClose, workspaceId, workflow, onChanged }: {
    open: boolean;
    onClose: () => void;
    workspaceId: number;
    workflow: WorkflowPayload;
    onChanged: () => Promise<void>;
}) {
    const confirmAction = useConfirmAction();
    const [name, setName] = useState('');
    const [color, setColor] = useState('#64748b');
    const [group, setGroup] = useState('active');
    const [statusEdit, setStatusEdit] = useState<TaskStatus | null>(null);
    const [statusDraft, setStatusDraft] = useState({ name: '', color: '#64748b', group: 'active' });
    const [tagName, setTagName] = useState('');
    const [tagColor, setTagColor] = useState('#2563eb');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    /** Handles the create status operation for the WorkIntel client. */ const createStatus = async () => {
        if (!name.trim())
            return;
        setSaving(true);
        try {
            await apiRequest('/api/v1/task-workflow/statuses', { method: 'POST', workspaceId, body: JSON.stringify({ name: name.trim(), color, group, is_completed: group === 'done' }) });
            setName('');
            await onChanged();
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not create status.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the save transitions operation for the WorkIntel client. */ const saveTransitions = async (status: TaskStatus, ids: number[]) => {
        setSaving(true);
        try {
            await apiRequest(`/api/v1/task-workflow/statuses/${status.id}/transitions`, { method: 'PUT', workspaceId, body: JSON.stringify({ to_status_ids: ids }) });
            await onChanged();
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not save transitions.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the create tag operation for the WorkIntel client. */ const createTag = async () => {
        if (!tagName.trim())
            return;
        setSaving(true);
        try {
            await apiRequest('/api/v1/task-workflow/tags', { method: 'POST', workspaceId, body: JSON.stringify({ name: tagName.trim(), color: tagColor }) });
            setTagName('');
            await onChanged();
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not create tag.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Updates update status state for the current workflow. */ const updateStatus = async (status: TaskStatus, body: Record<string, unknown>) => {
        setSaving(true);
        try {
            await apiRequest(`/api/v1/task-workflow/statuses/${status.id}`, { method: 'PUT', workspaceId, body: JSON.stringify(body) });
            await onChanged();
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not update status.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Open a WorkIntel-owned status editor instead of browser-native prompts. */ const editStatus = (status: TaskStatus) => { setStatusEdit(status); setStatusDraft({ name: status.name, color: status.color, group: status.group }); };
    /** Save one workflow status from the shared form dialog. */ const saveStatusEdit = async (event: FormEvent) => {
        event.preventDefault();
        if (!statusEdit || !statusDraft.name.trim())
            return;
        await updateStatus(statusEdit, { name: statusDraft.name.trim(), color: statusDraft.color, group: statusDraft.group });
        setStatusEdit(null);
    };
    /** Handles the move status operation for the WorkIntel client. */ const moveStatus = async (status: TaskStatus, direction: -1 | 1) => {
        const ids = workflow.statuses.map(s => s.id);
        const index = ids.indexOf(status.id);
        const target = index + direction;
        if (index < 0 || target < 0 || target >= ids.length)
            return;
        [ids[index], ids[target]] = [ids[target], ids[index]];
        setSaving(true);
        try {
            await apiRequest('/api/v1/task-workflow/status-order', { method: 'PUT', workspaceId, body: JSON.stringify({ status_ids: ids }) });
            await onChanged();
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not reorder statuses.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the archive status operation for the WorkIntel client. */ const archiveStatus = async (status: TaskStatus) => {
        if (status.is_default)
            return;
        const fallback = workflow.statuses.find(s => s.is_default && s.id !== status.id) ?? workflow.statuses.find(s => s.id !== status.id);
        if (!fallback)
            return;
        if (!await confirmAction({ title: 'Archive task status?', description: `Archive ${status.name}? Tasks currently using it will move to ${fallback.name}.`, confirmLabel: 'Archive', danger: true }))
            return;
        setSaving(true);
        try {
            await apiRequest(`/api/v1/task-workflow/statuses/${status.id}`, { method: 'DELETE', workspaceId, body: JSON.stringify({ replacement_status_id: fallback.id }) });
            await onChanged();
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not archive status.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the archive tag operation for the WorkIntel client. */ const archiveTag = async (tag: TaskTag) => {
        if (!await confirmAction({ title: 'Remove task tag?', description: `Remove tag ${tag.name} from the active catalog?`, confirmLabel: 'Remove', danger: true }))
            return;
        setSaving(true);
        try {
            await apiRequest(`/api/v1/task-workflow/tags/${tag.id}`, { method: 'DELETE', workspaceId });
            await onChanged();
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not remove tag.');
        }
        finally {
            setSaving(false);
        }
    };
    return <><Modal open={open} onClose={onClose} title="Task workflow" description="Create custom statuses, define allowed transitions, and maintain the workspace tag catalog." size="lg"><div className="task-workflow-manager">{error && <Alert tone="danger">{error}</Alert>}<section><h4>Status workflow</h4><div className="task-workflow-create"><Input placeholder="Status name" value={name} onChange={e => setName(e.target.value)}/><Input type="color" value={color} onChange={e => setColor(e.target.value)}/><Select value={group} onChange={e => setGroup(e.target.value)}><Option value="backlog">Backlog</Option><Option value="todo">Todo</Option><Option value="active">Active</Option><Option value="review">Review</Option><Option value="blocked">Blocked</Option><Option value="done">Done</Option><Option value="cancelled">Cancelled</Option></Select><Button size="sm" variant="primary" loading={saving} onClick={() => void createStatus()}><Plus size={13}/> Add status</Button></div><div className="task-status-settings">{workflow.statuses.map(status => <div key={status.id}><div className="task-status-settings__head"><span className="task-status-pill"><Box as="i" bg={status.color}/>{status.name}</span><div className="task-status-actions"><small>{status.group}{status.is_default ? ' · Default' : ''}{status.is_completed ? ' · Completed' : ''}</small><Button size="sm" variant="ghost" iconOnly aria-label="Move status up" disabled={saving} onClick={() => void moveStatus(status, -1)}><ChevronUp size={12}/></Button><Button size="sm" variant="ghost" iconOnly aria-label="Move status down" disabled={saving} onClick={() => void moveStatus(status, 1)}><ChevronDown size={12}/></Button><Button size="sm" variant="ghost" onClick={() => editStatus(status)}>Edit</Button>{!status.is_default && <Button size="sm" variant="ghost" onClick={() => void updateStatus(status, { is_default: true })}>Make default</Button>}<Button size="sm" variant="ghost" onClick={() => void updateStatus(status, { is_completed: !status.is_completed })}>{status.is_completed ? 'Reopen type' : 'Completed type'}</Button>{!status.is_default && <Button size="sm" variant="ghost" iconOnly aria-label="Archive status" onClick={() => void archiveStatus(status)}><Trash2 size={12}/></Button>}</div></div><div><span>Allowed next statuses</span><div className="task-tag-picker">{workflow.statuses.filter(s => s.id !== status.id).map(target => { const active = status.allowed_to_ids.includes(target.id); return <Pressable key={target.id} type="button" className={active ? 'is-selected' : ''} disabled={saving} onClick={() => void saveTransitions(status, toggleId(status.allowed_to_ids, target.id))}><Box as="i" bg={target.color}/>{target.name}{active && <Check size={11}/>}</Pressable>; })}<span className="task-muted">No selected transitions = unrestricted.</span></div></div></div>)}</div></section><section><h4>Tags</h4><div className="task-workflow-create"><Input placeholder="Tag name" value={tagName} onChange={e => setTagName(e.target.value)}/><Input type="color" value={tagColor} onChange={e => setTagColor(e.target.value)}/><Button size="sm" variant="secondary" loading={saving} onClick={() => void createTag()}><Tag size={13}/> Add tag</Button></div><div className="task-inline-tags">{workflow.tags.map(tag => <span key={tag.id}><Box as="i" bg={tag.color}/>{tag.name}<Pressable type="button" aria-label={`Remove ${tag.name}`} onClick={() => void archiveTag(tag)}><X size={10}/></Pressable></span>)}</div></section></div></Modal><FormDialog open={!!statusEdit} onClose={() => setStatusEdit(null)} title="Edit task status" description="Rename or regroup this status without leaving the workflow manager." formId="task-status-edit" onSubmit={saveStatusEdit} submitLabel="Save status" loading={saving}><Field label="Status name"><Input required value={statusDraft.name} onChange={event => setStatusDraft(value => ({ ...value, name: event.target.value }))}/></Field><Grid columns="1fr 1fr" gap={10}><Field label="Color"><Input type="color" value={statusDraft.color} onChange={event => setStatusDraft(value => ({ ...value, color: event.target.value }))}/></Field><Field label="Group"><Select value={statusDraft.group} onChange={event => setStatusDraft(value => ({ ...value, group: event.target.value }))}><Option value="backlog">Backlog</Option><Option value="todo">Todo</Option><Option value="active">Active</Option><Option value="review">Review</Option><Option value="blocked">Blocked</Option><Option value="done">Done</Option><Option value="cancelled">Cancelled</Option></Select></Field></Grid></FormDialog></>;
}
