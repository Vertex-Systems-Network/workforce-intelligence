import { FormEvent, useEffect, useMemo, useState } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight, Clock3, MapPin, Pencil, Plus, Trash2, Users } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { hasPermission } from '../access';
import { useConfirmAction, FormDialog, ErrorState, FilterBar, Alert, Avatar, Badge, Button, Card, CardBody, Field, Input, Modal, Page, PageHeader, Select, StatCard, Pressable, Checkbox, ChoiceList, ChoiceRow, Box, Grid, Inline, Form, Option } from '../design-system';
import { useLocalization } from '../i18n/LocalizationContext';
import { PageLoadingState } from '../components/LoadingStates';
type Shift = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    break_minutes: number;
    grace_minutes: number;
    location_type: string;
    timezone: string | null;
    status: string;
};
type Person = {
    id: number;
    user: {
        first_name: string;
        last_name: string;
    };
    department: {
        id: number;
        name: string;
    } | null;
};
type Assignment = {
    id: number;
    member_id: number;
    date: string;
    work_mode: string | null;
    shift: Shift;
    member: Person;
};
type Payload = {
    week_start: string;
    week_end: string;
    shifts: Shift[];
    assignments: Assignment[];
    people: Person[];
};
type ShiftForm = {
    name: string;
    start_time: string;
    end_time: string;
    break_minutes: string;
    grace_minutes: string;
    location_type: string;
    timezone: string;
};
type AssignForm = {
    shift_id: string;
    member_ids: number[];
    dates: string[];
    work_mode: string;
};
/** Handles the add days operation for the WorkIntel client. */ const addDays = (date: string, days: number) => { const d = new Date(`${date}T00:00:00`); d.setDate(d.getDate() + days); return d.toISOString().slice(0, 10); };
/** Handles the person name operation for the WorkIntel client. */ const personName = (person: Person) => `${person.user.first_name} ${person.user.last_name}`;
/** Handles the shifts operation for the WorkIntel client. */ export default function Shifts() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const { t } = useLocalization();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const workspace = session?.user.workspaces.find(item => item.id === workspaceId);
    const canManage = hasPermission(workspace, 'attendance.manage');
    const [data, setData] = useState<Payload | null>(null);
    const [start, setStart] = useState('');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [shiftModal, setShiftModal] = useState(false);
    const [assignModal, setAssignModal] = useState(false);
    const [editing, setEditing] = useState<Shift | null>(null);
    const [shiftForm, setShiftForm] = useState<ShiftForm>({ name: '', start_time: '09:00', end_time: '18:00', break_minutes: '60', grace_minutes: '10', location_type: 'office', timezone: Intl.DateTimeFormat().resolvedOptions().timeZone });
    const [assignForm, setAssignForm] = useState<AssignForm>({ shift_id: '', member_ids: [], dates: [], work_mode: 'office' });
    /** Loads load data required by the current view. */ const load = async (nextStart?: string) => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        const query = nextStart !== undefined ? nextStart : start;
        const payload = await apiRequest<Payload>(`/api/v1/shifts${query ? `?start=${query}` : ''}`, { workspaceId });
        setData(payload);
        setStart(payload.week_start);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load shifts.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(''); }, [workspaceId]);
    const days = useMemo(() => data ? Array.from({ length: 7 }, (_, i) => addDays(data.week_start, i)) : [], [data]);
    /** Handles the open shift operation for the WorkIntel client. */ const openShift = (shift?: Shift) => { setEditing(shift ?? null); setShiftForm(shift ? { name: shift.name, start_time: shift.start_time.slice(0, 5), end_time: shift.end_time.slice(0, 5), break_minutes: String(shift.break_minutes), grace_minutes: String(shift.grace_minutes), location_type: shift.location_type, timezone: shift.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone } : { name: '', start_time: '09:00', end_time: '18:00', break_minutes: '60', grace_minutes: '10', location_type: 'office', timezone: Intl.DateTimeFormat().resolvedOptions().timeZone }); setError(''); setShiftModal(true); };
    /** Handles the save shift operation for the WorkIntel client. */ const saveShift = async (e: FormEvent) => { e.preventDefault(); if (!workspaceId)
        return; setSaving(true); setError(''); try {
        await apiRequest(editing ? `/api/v1/shifts/${editing.id}` : '/api/v1/shifts', { method: editing ? 'PUT' : 'POST', workspaceId, body: JSON.stringify({ ...shiftForm, break_minutes: Number(shiftForm.break_minutes), grace_minutes: Number(shiftForm.grace_minutes), status: 'active' }) });
        setShiftModal(false);
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not save shift.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the delete shift operation for the WorkIntel client. */ const deleteShift = async (shift: Shift) => { if (!workspaceId || !await confirmAction({ title: 'Archive shift template?', description: `Archive ${shift.name}?`, confirmLabel: 'Archive', danger: true }))
        return; try {
        await apiRequest(`/api/v1/shifts/${shift.id}`, { method: 'DELETE', workspaceId });
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not archive shift.');
    } };
    /** Handles the open assign operation for the WorkIntel client. */ const openAssign = () => { if (!data?.shifts.length)
        return; setAssignForm({ shift_id: String(data.shifts[0].id), member_ids: [], dates: [data.week_start], work_mode: data.shifts[0].location_type }); setError(''); setAssignModal(true); };
    /** Handles the save assignment operation for the WorkIntel client. */ const saveAssignment = async (e: FormEvent) => { e.preventDefault(); if (!workspaceId)
        return; setSaving(true); setError(''); try {
        await apiRequest('/api/v1/shift-assignments', { method: 'POST', workspaceId, body: JSON.stringify({ ...assignForm, shift_id: Number(assignForm.shift_id) }) });
        setAssignModal(false);
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not assign shift.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the remove assignment operation for the WorkIntel client. */ const removeAssignment = async (assignment: Assignment) => { if (!workspaceId)
        return; try {
        await apiRequest(`/api/v1/shift-assignments/${assignment.id}`, { method: 'DELETE', workspaceId });
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not remove assignment.');
    } };
    if (loading && !data)
        return <PageLoadingState />;
    if (!data)
        return <Page><ErrorState title="Shift data unavailable" text={error || 'Shift data could not be loaded.'} retry={() => load()}/></Page>;
    const scheduledMembers = new Set(data.assignments.map(item => item.member_id)).size;
    return <Page><PageHeader title={t('scheduling.templates')} description={t('scheduling.subtitle')} actions={canManage ? <><Button variant="outline" size="sm" onClick={() => openShift()}><Plus size={14}/> Shift Template</Button><Button variant="primary" size="sm" onClick={openAssign} disabled={!data.shifts.length}><Users size={14}/> Assign Shift</Button></> : <Badge>Read only</Badge>}/>{error && <Alert tone="danger">{error}</Alert>}
 <FilterBar primary={<Inline align="center" gap={7}><Button variant="outline" size="sm" iconOnly aria-label="Previous week" onClick={() => void load(addDays(data.week_start, -7))}><ChevronLeft size={14}/></Button><Button variant="outline" size="sm" onClick={() => void load('')}><CalendarDays size={14}/>{new Date(`${data.week_start}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} – {new Date(`${data.week_end}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}</Button><Button variant="outline" size="sm" iconOnly aria-label="Next week" onClick={() => void load(addDays(data.week_start, 7))}><ChevronRight size={14}/></Button></Inline>} actions={<Inline gap={7} wrap="wrap">{data.shifts.map(shift => canManage ? <Button key={shift.id} variant="ghost" size="sm" onClick={() => openShift(shift)}><Pencil size={12}/>{shift.name}</Button> : <Badge key={shift.id}>{shift.name}</Badge>)}</Inline>}/>
 <Card><Box display="grid" gridColumns="190px repeat(7,minmax(120px,1fr))" overflowX="auto"><Box p={12} borderRight="1px solid var(--border)" borderBottom="1px solid var(--border)" color="var(--text-3)" size={11} weight={600}>EMPLOYEE</Box>{days.map(date => <Box key={date} p={12} textAlign="center" borderRight="1px solid var(--border-muted)" borderBottom="1px solid var(--border)" size={11} color="var(--text-2)" weight={600}>{new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', day: 'numeric' })}</Box>)}{data.people.map(person => <Box key={person.id} display="contents"><Box p={10} borderRight="1px solid var(--border)" borderBottom="1px solid var(--border-muted)" display="flex" align="center" gap={8}><Avatar name={personName(person)} size="sm"/><div><Box size={12} weight={550}>{personName(person)}</Box><div className="ui-card-description">{person.department?.name ?? 'No department'}</div></div></Box>{days.map(date => { const assignment = data.assignments.find(item => item.member_id === person.id && item.date.slice(0, 10) === date); return <Box key={`${person.id}-${date}`} minHeight={76} p={7} borderRight="1px solid var(--border-muted)" borderBottom="1px solid var(--border-muted)" bg={assignment ? 'transparent' : 'var(--bg)'}>{assignment && <Box height="100%" p={8} border="1px solid var(--border)" radius={7} bg={assignment.work_mode === 'remote' ? 'var(--info-dim)' : 'var(--accent-dim)'} position="relative">{canManage && <Pressable type="button" onClick={() => void removeAssignment(assignment)} aria-label="Remove shift" position="absolute" right={5} top={4} border={0} bg="transparent" color="var(--text-3)" cursor="pointer" p={2}><Trash2 size={11}/></Pressable>}<Box size={11} weight={650} color={assignment.work_mode === 'remote' ? 'var(--info)' : 'var(--accent)'} pr={15}>{assignment.shift.start_time.slice(0, 5)} – {assignment.shift.end_time.slice(0, 5)}</Box><Box display="flex" align="center" gap={4} mt={5} size={10} color="var(--text-3)"><MapPin size={10}/>{assignment.work_mode ?? assignment.shift.location_type}</Box><Box className="ui-card-description" mt={3}>{assignment.shift.name}</Box></Box>}</Box>; })}</Box>)}</Box></Card>
 <Grid columns="repeat(3,minmax(0,1fr))" gap={12} mt={14}><StatCard label="Scheduled people" value={String(scheduledMembers)} sub={`${data.people.length} active employees`}/><StatCard label="Assignments" value={String(data.assignments.length)} sub="Across this week"/><StatCard label="Shift templates" value={String(data.shifts.length)} sub="Reusable schedules"/></Grid>
 <FormDialog open={shiftModal} onClose={() => setShiftModal(false)} title={editing ? 'Edit shift template' : 'Create shift template'} description="Define scheduled hours, break allowance and grace period." formId="shift-form" onSubmit={saveShift} submitLabel={editing ? 'Save Changes' : 'Create Shift'} loading={saving}>{error && <Alert tone="danger">{error}</Alert>}<Field label="Shift name"><Input value={shiftForm.name} onChange={e => setShiftForm({ ...shiftForm, name: e.target.value })} required/></Field><Grid columns="1fr 1fr" gap={10}><Field label="Start"><Input type="time" value={shiftForm.start_time} onChange={e => setShiftForm({ ...shiftForm, start_time: e.target.value })} required/></Field><Field label="End"><Input type="time" value={shiftForm.end_time} onChange={e => setShiftForm({ ...shiftForm, end_time: e.target.value })} required/></Field></Grid><Grid columns="1fr 1fr" gap={10}><Field label="Break minutes"><Input type="number" min="0" value={shiftForm.break_minutes} onChange={e => setShiftForm({ ...shiftForm, break_minutes: e.target.value })}/></Field><Field label="Grace minutes"><Input type="number" min="0" value={shiftForm.grace_minutes} onChange={e => setShiftForm({ ...shiftForm, grace_minutes: e.target.value })}/></Field></Grid><Field label="Default work mode"><Select value={shiftForm.location_type} onChange={e => setShiftForm({ ...shiftForm, location_type: e.target.value })}><Option value="office">Office</Option><Option value="remote">Remote</Option><Option value="hybrid">Hybrid</Option><Option value="field">Field</Option></Select></Field></FormDialog>
 <FormDialog open={assignModal} onClose={() => setAssignModal(false)} title="Assign shifts" description="Assign one shift to multiple people and days in the selected week." size="lg" formId="assign-shift-form" onSubmit={saveAssignment} submitLabel="Assign" loading={saving}>{error && <Alert tone="danger">{error}</Alert>}<Field label="Shift"><Select value={assignForm.shift_id} onChange={e => { const shift = data.shifts.find(item => String(item.id) === e.target.value); setAssignForm({ ...assignForm, shift_id: e.target.value, work_mode: shift?.location_type ?? assignForm.work_mode }); }}>{data.shifts.map(shift => <Option key={shift.id} value={shift.id}>{shift.name} · {shift.start_time.slice(0, 5)}–{shift.end_time.slice(0, 5)}</Option>)}</Select></Field><Field label="Employees"><ChoiceList columns={2} maxHeight="md">{data.people.map(person => { const selected = assignForm.member_ids.includes(person.id); return <ChoiceRow key={person.id} selected={selected}><Checkbox checked={selected} onChange={e => setAssignForm({ ...assignForm, member_ids: e.target.checked ? [...assignForm.member_ids, person.id] : assignForm.member_ids.filter(id => id !== person.id) })}/>{personName(person)}</ChoiceRow>; })}</ChoiceList></Field><Field label="Days"><ChoiceList columns={4} maxHeight="sm">{days.map(date => { const selected = assignForm.dates.includes(date); return <ChoiceRow key={date} selected={selected}><Checkbox checked={selected} onChange={e => setAssignForm({ ...assignForm, dates: e.target.checked ? [...assignForm.dates, date] : assignForm.dates.filter(item => item !== date) })}/>{new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', day: 'numeric' })}</ChoiceRow>; })}</ChoiceList></Field><Field label="Work mode"><Select value={assignForm.work_mode} onChange={e => setAssignForm({ ...assignForm, work_mode: e.target.value })}><Option value="office">Office</Option><Option value="remote">Remote</Option><Option value="hybrid">Hybrid</Option><Option value="field">Field</Option></Select></Field></FormDialog>
 </Page>;
}
