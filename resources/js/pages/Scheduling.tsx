import { FormEvent, useEffect, useMemo, useState, type ReactNode } from 'react';
import { DndContext, PointerSensor, KeyboardSensor, useDraggable, useDroppable, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { sortableKeyboardCoordinates } from '@dnd-kit/sortable';
import { CalendarDays, ChevronLeft, ChevronRight, CircleAlert, Clock3, Hand, Move, Plus, RefreshCcw, Send, Settings2 } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { hasPermission } from '../access';
import { ErrorState, FilterBar, EmptyState, Alert, Avatar, Badge, Button, Card, CardBody, CardHeader, Field, Input, Page, PageHeader, Select, StatCard, Switch, Tabs, Textarea, Pressable, Grid, Inline, Stack, Text, FormDialog, SettingRow, Option, Box} from '../design-system';
import { useLocalization } from '../i18n/LocalizationContext';
import { PageLoadingState } from '../components/LoadingStates';
type Person = {
    id: number;
    user: {
        first_name: string;
        last_name: string;
    };
    department?: {
        name: string;
    } | null;
};
type Shift = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    location_type: string;
};
type Project = {
    id: number;
    name: string;
};
type Assignment = {
    id: number;
    member_id: number;
    date: string;
    work_mode: string | null;
    status: string;
    shift: Shift;
    project?: Project | null;
    member?: Person;
};
type Availability = {
    id: number;
    member_id: number;
    date: string;
    status: 'available' | 'preferred' | 'unavailable';
    start_time: string | null;
    end_time: string | null;
    note: string | null;
};
type OpenShift = {
    id: number;
    shift_id: number;
    project_id: number | null;
    date: string;
    slots: number;
    claimed_slots: number;
    work_mode: string | null;
    status: string;
    note: string | null;
    shift: Shift;
    project?: Project | null;
};
type Swap = {
    id: number;
    assignment_id: number;
    requested_by_member_id: number;
    target_member_id: number | null;
    request_type: 'swap' | 'drop';
    status: string;
    message: string | null;
    assignment: Assignment;
    requester: {
        user: {
            first_name: string;
            last_name: string;
        };
    };
    target?: {
        user: {
            first_name: string;
            last_name: string;
        };
    } | null;
};
type Settings = {
    max_weekly_hours: number;
    overtime_warning_hours: number;
    minimum_rest_hours: number;
    daily_coverage_target: number;
    weekly_labor_budget: string | null;
    currency: string;
    allow_open_shift_claims: boolean;
    allow_shift_swaps: boolean;
};
type Analysis = {
    coverage: Array<{
        date: string;
        scheduled: number;
        target: number;
        gap: number;
    }>;
    member_hours: Array<{
        member_id: number;
        hours: number;
    }>;
    forecast_labor_cost: number;
    weekly_labor_budget: string | null;
    currency: string;
    warnings: Array<{
        type: string;
        member_id: number | null;
        date: string | null;
        message: string;
    }>;
};
type Payload = {
    week_start: string;
    week_end: string;
    people: Person[];
    shifts: Shift[];
    projects: Project[];
    assignments: Assignment[];
    availability: Availability[];
    open_shifts: OpenShift[];
    swap_requests: Swap[];
    settings: Settings;
    analysis: Analysis;
    can_manage: boolean;
    current_member_id: number;
};
/** Handles the add days operation for the WorkIntel client. */ const addDays = (date: string, days: number) => { const d = new Date(`${date}T00:00:00`); d.setDate(d.getDate() + days); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
/** Handles the fmt date operation for the WorkIntel client. */ const fmtDate = (date: string) => new Date(`${date.slice(0, 10)}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
/** Handles the name operation for the WorkIntel client. */ const name = (p?: Person) => p ? `${p.user.first_name} ${p.user.last_name}` : 'Unknown';
/** Handles the draggable shift operation for the WorkIntel client. */ function DraggableShift({ assignment, canManage, currentMemberId, allowSwaps, onChange }: {
    assignment: Assignment;
    canManage: boolean;
    currentMemberId: number;
    allowSwaps: boolean;
    onChange: () => void;
}) {
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({ id: `schedule-assignment:${assignment.id}`, disabled: !canManage, data: { assignmentId: assignment.id } });
    const style = transform ? { transform: `translate3d(${transform.x}px,${transform.y}px,0)`, opacity: isDragging ? .55 : 1, zIndex: isDragging ? 20 : undefined } : undefined;
    return <div ref={setNodeRef} style={style} className={`schedule-shift ${assignment.status === 'draft' ? 'is-draft' : ''}`} {...attributes} {...listeners}><div><strong>{assignment.shift.start_time.slice(0, 5)}–{assignment.shift.end_time.slice(0, 5)}</strong><Badge tone={assignment.status === 'draft' ? 'warning' : 'success'}>{assignment.status}</Badge></div><small>{assignment.shift.name} · {assignment.work_mode ?? assignment.shift.location_type}</small>{assignment.project && <small>{assignment.project.name}</small>}{assignment.member_id === currentMemberId && allowSwaps && <Pressable type="button" onPointerDown={e => e.stopPropagation()} onClick={e => { e.stopPropagation(); onChange(); }}><RefreshCcw size={11}/> Change</Pressable>}</div>;
}
/** Handles the schedule cell operation for the WorkIntel client. */ function ScheduleCell({ personId, date, unavailable, children, canManage }: {
    personId: number;
    date: string;
    unavailable: boolean;
    children: ReactNode;
    canManage: boolean;
}) {
    const { isOver, setNodeRef } = useDroppable({ id: `schedule-cell:${personId}:${date}`, disabled: !canManage, data: { personId, date } });
    return <div ref={setNodeRef} className={`schedule-cell${unavailable ? ' is-unavailable' : ''}${isOver ? ' is-drop-target' : ''}`}>{children}</div>;
}
/** Handles the scheduling operation for the WorkIntel client. */ export default function Scheduling() {
    const { session } = useAuth();
    const { t } = useLocalization();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const workspace = session?.user.workspaces.find(w => w.id === workspaceId);
    const canManage = hasPermission(workspace, 'scheduling.manage');
    const [data, setData] = useState<Payload | null>(null);
    const [start, setStart] = useState('');
    const [tab, setTab] = useState<'roster' | 'open' | 'availability' | 'swaps' | 'settings'>('roster');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));
    const [assignModal, setAssignModal] = useState(false);
    const [openModal, setOpenModal] = useState(false);
    const [availabilityModal, setAvailabilityModal] = useState(false);
    const [swapModal, setSwapModal] = useState(false);
    const [selectedAssignment, setSelectedAssignment] = useState<Assignment | null>(null);
    const [assignForm, setAssignForm] = useState({ shift_id: '', member_id: '', project_id: '', date: '', work_mode: 'office' });
    const [openForm, setOpenForm] = useState({ shift_id: '', project_id: '', date: '', slots: '1', work_mode: 'office', note: '' });
    const [availabilityForm, setAvailabilityForm] = useState({ date: '', status: 'available', start_time: '09:00', end_time: '18:00', note: '' });
    const [swapForm, setSwapForm] = useState({ request_type: 'swap', target_member_id: '', message: '' });
    /** Loads load data required by the current view. */ const load = async (nextStart?: string) => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        const query = nextStart !== undefined ? nextStart : start;
        const p = await apiRequest<Payload>(`/api/v1/scheduling/week${query ? `?start=${query}` : ''}`, { workspaceId });
        setData(p);
        setStart(p.week_start);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load schedule.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(''); }, [workspaceId]);
    const days = useMemo(() => data ? Array.from({ length: 7 }, (_, i) => addDays(data.week_start, i)) : [], [data?.week_start]);
    /** Handles the hours for operation for the WorkIntel client. */ const hoursFor = (id: number) => data?.analysis.member_hours.find(x => x.member_id === id)?.hours ?? 0;
    /** Handles the availability for operation for the WorkIntel client. */ const availabilityFor = (id: number, date: string) => data?.availability.find(a => a.member_id === id && a.date.slice(0, 10) === date);
    /** Handles the assignment for operation for the WorkIntel client. */ const assignmentFor = (id: number, date: string) => data?.assignments.find(a => a.member_id === id && a.date.slice(0, 10) === date);
    /** Handles the move operation for the WorkIntel client. */ const move = async (assignmentId: number, memberId: number, date: string) => { if (!workspaceId || !canManage)
        return; setSaving(true); setError(''); try {
        await apiRequest(`/api/v1/scheduling/assignments/${assignmentId}/move`, { method: 'PATCH', workspaceId, body: JSON.stringify({ member_id: memberId, date }) });
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not move shift.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the assign shift operation for the WorkIntel client. */ const assignShift = async (e: FormEvent) => { e.preventDefault(); if (!workspaceId)
        return; setSaving(true); setError(''); try {
        await apiRequest('/api/v1/scheduling/assignments', { method: 'POST', workspaceId, body: JSON.stringify({ ...assignForm, shift_id: Number(assignForm.shift_id), member_id: Number(assignForm.member_id), project_id: assignForm.project_id ? Number(assignForm.project_id) : null }) });
        setAssignModal(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not assign shift.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the create open operation for the WorkIntel client. */ const createOpen = async (e: FormEvent) => { e.preventDefault(); if (!workspaceId)
        return; setSaving(true); try {
        await apiRequest('/api/v1/scheduling/open-shifts', { method: 'POST', workspaceId, body: JSON.stringify({ ...openForm, shift_id: Number(openForm.shift_id), project_id: openForm.project_id ? Number(openForm.project_id) : null, slots: Number(openForm.slots) }) });
        setOpenModal(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create open shift.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the claim operation for the WorkIntel client. */ const claim = async (id: number) => { setSaving(true); setError(''); try {
        await apiRequest(`/api/v1/scheduling/open-shifts/${id}/claim`, { method: 'POST', workspaceId });
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not claim shift.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the save availability operation for the WorkIntel client. */ const saveAvailability = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest('/api/v1/scheduling/availability', { method: 'PUT', workspaceId, body: JSON.stringify(availabilityForm) });
        setAvailabilityModal(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not save availability.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the request swap operation for the WorkIntel client. */ const requestSwap = async (e: FormEvent) => { e.preventDefault(); if (!selectedAssignment)
        return; setSaving(true); try {
        await apiRequest('/api/v1/scheduling/swaps', { method: 'POST', workspaceId, body: JSON.stringify({ assignment_id: selectedAssignment.id, request_type: swapForm.request_type, target_member_id: swapForm.target_member_id ? Number(swapForm.target_member_id) : null, message: swapForm.message }) });
        setSwapModal(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not submit request.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the review operation for the WorkIntel client. */ const review = async (id: number, decision: 'approved' | 'rejected') => { setSaving(true); try {
        await apiRequest(`/api/v1/scheduling/swaps/${id}/review`, { method: 'PATCH', workspaceId, body: JSON.stringify({ decision }) });
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not review request.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the publish operation for the WorkIntel client. */ const publish = async () => { if (!data)
        return; setSaving(true); try {
        await apiRequest('/api/v1/scheduling/publish', { method: 'POST', workspaceId, body: JSON.stringify({ week_start: data.week_start }) });
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not publish schedule.');
    }
    finally {
        setSaving(false);
    } };
    /** Updates update setting state for the current workflow. */ const updateSetting = <K extends keyof Settings>(key: K, value: Settings[K]) => data && setData({ ...data, settings: { ...data.settings, [key]: value } });
    /** Handles the save settings operation for the WorkIntel client. */ const saveSettings = async () => { if (!data)
        return; setSaving(true); try {
        const s = data.settings;
        await apiRequest('/api/v1/scheduling/settings', { method: 'PUT', workspaceId, body: JSON.stringify({ ...s, weekly_labor_budget: s.weekly_labor_budget === '' ? null : Number(s.weekly_labor_budget) }) });
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not save scheduling policy.');
    }
    finally {
        setSaving(false);
    } };
    if (loading && !data)
        return <PageLoadingState />;
    if (!data)
        return <Page><ErrorState title="Scheduling unavailable" text={error || 'Scheduling data could not be loaded.'} retry={() => load()}/></Page>;
    const draftCount = data.assignments.filter(a => a.status === 'draft').length;
    return <Page><PageHeader title={t('scheduling.board')} description={t('scheduling.subtitle')} actions={<Inline gap={7}>{canManage && <Button variant="outline" size="sm" onClick={() => { setAssignForm({ shift_id: String(data.shifts[0]?.id ?? ''), member_id: String(data.people[0]?.id ?? ''), project_id: '', date: data.week_start, work_mode: data.shifts[0]?.location_type ?? 'office' }); setAssignModal(true); }}><Plus size={13}/> Assign Shift</Button>}{canManage && <Button variant="outline" size="sm" onClick={() => void publish()} disabled={!draftCount || saving}><Send size={13}/> Publish {draftCount || ''}</Button>}<Button size="sm" variant="primary" onClick={() => { setAvailabilityForm({ date: data.week_start, status: 'available', start_time: '09:00', end_time: '18:00', note: '' }); setAvailabilityModal(true); }}><Clock3 size={13}/> Availability</Button></Inline>}/>{error && <Alert tone="danger">{error}</Alert>}
 <FilterBar primary={<Inline align="center" gap={7}><Button variant="outline" size="sm" iconOnly aria-label="Previous week" onClick={() => void load(addDays(data.week_start, -7))}><ChevronLeft size={14}/></Button><Button variant="outline" size="sm" onClick={() => void load('')}><CalendarDays size={14}/>{fmtDate(data.week_start)} – {fmtDate(data.week_end)}</Button><Button variant="outline" size="sm" iconOnly aria-label="Next week" onClick={() => void load(addDays(data.week_start, 7))}><ChevronRight size={14}/></Button></Inline>} actions={<Tabs value={tab} onChange={setTab} tabs={[{ value: 'roster', label: 'Roster' }, { value: 'open', label: `Open Shifts (${data.open_shifts.length})` }, { value: 'availability', label: 'Availability' }, { value: 'swaps', label: `Swaps (${data.swap_requests.filter(s => s.status === 'pending').length})` }, ...(canManage ? [{ value: 'settings' as const, label: 'Policy' }] : [])]}/>}/>
 {canManage && <Grid columns="repeat(4,minmax(0,1fr))" gap={10} mb={12}><StatCard label="Scheduled" value={String(data.assignments.length)} sub={`${data.people.length} people in scope`}/><StatCard label="Coverage gaps" value={String(data.analysis.coverage.filter(c => c.gap > 0).length)} sub="Days below target"/><StatCard label="Forecast labor" value={`${data.analysis.currency} ${data.analysis.forecast_labor_cost.toLocaleString()}`} sub={data.analysis.weekly_labor_budget ? `Budget ${data.analysis.weekly_labor_budget}` : 'No budget set'}/><StatCard label="Warnings" value={String(data.analysis.warnings.length)} sub="Overtime, coverage & budget"/></Grid>}
 {data.analysis.warnings.length > 0 && canManage && <Card mb={12}><CardHeader title="Schedule warnings" description="Resolve these before publishing"/><CardBody><Stack gap={6}>{data.analysis.warnings.slice(0, 8).map((w, i) => <Box key={i} display="flex" align="center" gap={8} p={8} radius={7} bg="var(--warning-dim)" color="var(--warning)" size={11}><CircleAlert size={13}/>{w.message}</Box>)}</Stack></CardBody></Card>}
 {tab === 'roster' && <DndContext sensors={sensors} onDragEnd={(event: DragEndEvent) => { const assignmentId = Number(event.active.data.current?.assignmentId || 0); const personId = Number(event.over?.data.current?.personId || 0); const date = String(event.over?.data.current?.date || ''); if (assignmentId && personId && date)
        void move(assignmentId, personId, date); }}><Card><Box display="grid" gridColumns="210px repeat(7,minmax(130px,1fr))" overflowX="auto"><div className="schedule-grid-head">EMPLOYEE</div>{days.map(d => <div className="schedule-grid-head" key={d}>{fmtDate(d)}<small>{data.analysis.coverage.find(c => c.date === d)?.scheduled ?? 0}/{data.settings.daily_coverage_target} coverage</small></div>)}{data.people.map(person => <Box key={person.id} display="contents"><div className="schedule-person"><Avatar name={name(person)} size="sm"/><div><strong>{name(person)}</strong><small>{person.department?.name ?? 'No department'} · {hoursFor(person.id)}h</small></div></div>{days.map(date => { const a = assignmentFor(person.id, date); const avail = availabilityFor(person.id, date); return <ScheduleCell key={`${person.id}-${date}`} personId={person.id} date={date} unavailable={avail?.status === 'unavailable'} canManage={canManage}>{a ? <DraggableShift assignment={a} canManage={canManage} currentMemberId={data.current_member_id} allowSwaps={data.settings.allow_shift_swaps} onChange={() => { setSelectedAssignment(a); setSwapForm({ request_type: 'swap', target_member_id: '', message: '' }); setSwapModal(true); }}/> : <div className="schedule-empty">{avail?.status === 'unavailable' ? 'Unavailable' : canManage ? <><Move size={12}/> Drop shift here</> : '—'}</div>}</ScheduleCell>; })}</Box>)}</Box></Card></DndContext>}
 {tab === 'open' && <><Inline justify="flex-end" mb={10}>{canManage && <Button variant="primary" size="sm" onClick={() => { setOpenForm({ shift_id: String(data.shifts[0]?.id ?? ''), project_id: '', date: data.week_start, slots: '1', work_mode: data.shifts[0]?.location_type ?? 'office', note: '' }); setOpenModal(true); }}><Plus size={13}/> Create Open Shift</Button>}</Inline><Grid columns="repeat(auto-fill,minmax(260px,1fr))" gap={10}>{data.open_shifts.map(o => <Card key={o.id}><CardHeader title={`${fmtDate(o.date)} · ${o.shift.start_time.slice(0, 5)}–${o.shift.end_time.slice(0, 5)}`} description={`${o.shift.name}${o.project ? ` · ${o.project.name}` : ''}`}/><CardBody><Inline justify="space-between" align="center"><Badge tone={o.status === 'open' ? 'success' : 'neutral'}>{o.claimed_slots}/{o.slots} claimed</Badge>{!canManage && o.status === 'open' && <Button size="sm" variant="primary" disabled={saving} onClick={() => void claim(o.id)}><Hand size={12}/> Claim</Button>}</Inline>{o.note && <Text className="ui-card-description" as="p" mt={8}>{o.note}</Text>}</CardBody></Card>)}{!data.open_shifts.length && <EmptyState title="No open shifts this week." contextualHelp/>}</Grid></>}
 {tab === 'availability' && <Card><CardHeader title="Availability" description={canManage ? 'Team availability for this week' : 'Your submitted availability'}/><CardBody><Stack gap={7}>{data.availability.map(a => <div key={a.id} className="schedule-list-row"><div><strong>{data.people.find(p => p.id === a.member_id) ? name(data.people.find(p => p.id === a.member_id)) : a.member_id}</strong><small>{fmtDate(a.date)} · {a.start_time?.slice(0, 5) ?? 'All day'}{a.end_time ? `–${a.end_time.slice(0, 5)}` : ''}</small></div><Badge tone={a.status === 'unavailable' ? 'danger' : a.status === 'preferred' ? 'accent' : 'success'}>{a.status}</Badge></div>)}{!data.availability.length && <EmptyState title="No availability preferences submitted." contextualHelp/>}</Stack></CardBody></Card>}
 {tab === 'swaps' && <Card><CardHeader title="Shift change requests" description={canManage ? 'Review team swap/drop requests' : 'Your submitted requests'}/><CardBody><Stack gap={8}>{data.swap_requests.map(s => <div key={s.id} className="schedule-list-row"><div><strong>{s.requester.user.first_name} {s.requester.user.last_name} · {s.request_type}</strong><small>{fmtDate(s.assignment.date)} · {s.assignment.shift.name}{s.target ? ` → ${s.target.user.first_name} ${s.target.user.last_name}` : ''}</small>{s.message && <small>{s.message}</small>}</div><Inline gap={6} align="center"><Badge tone={s.status === 'approved' ? 'success' : s.status === 'rejected' ? 'danger' : s.status === 'pending' ? 'warning' : 'neutral'}>{s.status}</Badge>{canManage && s.status === 'pending' && <><Button size="sm" variant="ghost" onClick={() => void review(s.id, 'rejected')}>Reject</Button><Button size="sm" variant="primary" onClick={() => void review(s.id, 'approved')}>Approve</Button></>}</Inline></div>)}{!data.swap_requests.length && <EmptyState title="No shift change requests." contextualHelp/>}</Stack></CardBody></Card>}
 {tab === 'settings' && canManage && <Card><CardHeader title="Scheduling policy" description="Coverage, overtime and labor planning thresholds"/><CardBody><Grid columns="repeat(2,minmax(0,1fr))" gap={11}><Field label="Overtime warning hours"><Input type="number" value={data.settings.overtime_warning_hours} onChange={e => updateSetting('overtime_warning_hours', Number(e.target.value))}/></Field><Field label="Maximum weekly hours"><Input type="number" value={data.settings.max_weekly_hours} onChange={e => updateSetting('max_weekly_hours', Number(e.target.value))}/></Field><Field label="Minimum rest hours"><Input type="number" value={data.settings.minimum_rest_hours} onChange={e => updateSetting('minimum_rest_hours', Number(e.target.value))}/></Field><Field label="Daily coverage target"><Input type="number" value={data.settings.daily_coverage_target} onChange={e => updateSetting('daily_coverage_target', Number(e.target.value))}/></Field><Field label={`Weekly labor budget (${data.settings.currency})`}><Input type="number" min="0" value={data.settings.weekly_labor_budget ?? ''} onChange={e => updateSetting('weekly_labor_budget', e.target.value)}/></Field></Grid><Stack gap={8} mt={12}><SettingRow title="Open shift claiming" description="Employees can claim available open shifts." control={<Switch checked={data.settings.allow_open_shift_claims} onChange={v => updateSetting('allow_open_shift_claims', v)}/>}/><SettingRow title="Shift swaps" description="Employees can request swap/drop approval." control={<Switch checked={data.settings.allow_shift_swaps} onChange={v => updateSetting('allow_shift_swaps', v)}/>}/></Stack><Button variant="primary" loading={saving} onClick={() => void saveSettings()} mt={12}><Settings2 size={13}/> Save Policy</Button></CardBody></Card>}
 <FormDialog open={assignModal} onClose={() => !saving && setAssignModal(false)} title="Assign shift" description="Add or replace one employee shift in this roster. Changes stay draft until published." formId="assign-roster-form" onSubmit={assignShift} submitLabel="Save Draft" loading={saving}><Field label="Employee"><Select value={assignForm.member_id} onChange={e => setAssignForm({ ...assignForm, member_id: e.target.value })}>{data.people.map(p => <Option key={p.id} value={p.id}>{name(p)}</Option>)}</Select></Field><Field label="Shift"><Select value={assignForm.shift_id} onChange={e => { const sh = data.shifts.find(s => String(s.id) === e.target.value); setAssignForm({ ...assignForm, shift_id: e.target.value, work_mode: sh?.location_type ?? assignForm.work_mode }); }}>{data.shifts.map(sh => <Option key={sh.id} value={sh.id}>{sh.name} · {sh.start_time.slice(0, 5)}–{sh.end_time.slice(0, 5)}</Option>)}</Select></Field><Field label="Date"><Input type="date" value={assignForm.date} onChange={e => setAssignForm({ ...assignForm, date: e.target.value })}/></Field><Field label="Project"><Select value={assignForm.project_id} onChange={e => setAssignForm({ ...assignForm, project_id: e.target.value })}><Option value="">No project</Option>{data.projects.map(p => <Option key={p.id} value={p.id}>{p.name}</Option>)}</Select></Field><Field label="Work mode"><Select value={assignForm.work_mode} onChange={e => setAssignForm({ ...assignForm, work_mode: e.target.value })}><Option value="office">Office</Option><Option value="remote">Remote</Option><Option value="hybrid">Hybrid</Option><Option value="field">Field</Option></Select></Field></FormDialog>
 <FormDialog open={openModal} onClose={() => !saving && setOpenModal(false)} title="Create open shift" description="Publish an unassigned shift employees can claim." formId="open-shift-form" onSubmit={createOpen} submitLabel="Create" loading={saving}><Field label="Shift"><Select value={openForm.shift_id} onChange={e => setOpenForm({ ...openForm, shift_id: e.target.value })}>{data.shifts.map(s => <Option key={s.id} value={s.id}>{s.name} · {s.start_time.slice(0, 5)}–{s.end_time.slice(0, 5)}</Option>)}</Select></Field><Field label="Date"><Input type="date" value={openForm.date} onChange={e => setOpenForm({ ...openForm, date: e.target.value })}/></Field><Field label="Project"><Select value={openForm.project_id} onChange={e => setOpenForm({ ...openForm, project_id: e.target.value })}><Option value="">No project</Option>{data.projects.map(p => <Option key={p.id} value={p.id}>{p.name}</Option>)}</Select></Field><Grid columns="1fr 1fr" gap={8}><Field label="Slots"><Input type="number" min="1" value={openForm.slots} onChange={e => setOpenForm({ ...openForm, slots: e.target.value })}/></Field><Field label="Work mode"><Select value={openForm.work_mode} onChange={e => setOpenForm({ ...openForm, work_mode: e.target.value })}><Option value="office">Office</Option><Option value="remote">Remote</Option><Option value="hybrid">Hybrid</Option><Option value="field">Field</Option></Select></Field></Grid><Field label="Note"><Textarea value={openForm.note} onChange={e => setOpenForm({ ...openForm, note: e.target.value })}/></Field></FormDialog>
 <FormDialog open={availabilityModal} onClose={() => !saving && setAvailabilityModal(false)} title="Set availability" description="Tell scheduling managers when you can work." formId="availability-form" onSubmit={saveAvailability} submitLabel="Save" loading={saving}><Field label="Date"><Input type="date" value={availabilityForm.date} onChange={e => setAvailabilityForm({ ...availabilityForm, date: e.target.value })}/></Field><Field label="Status"><Select value={availabilityForm.status} onChange={e => setAvailabilityForm({ ...availabilityForm, status: e.target.value })}><Option value="available">Available</Option><Option value="preferred">Preferred</Option><Option value="unavailable">Unavailable</Option></Select></Field><Grid columns="1fr 1fr" gap={8}><Field label="From"><Input type="time" value={availabilityForm.start_time} onChange={e => setAvailabilityForm({ ...availabilityForm, start_time: e.target.value })}/></Field><Field label="To"><Input type="time" value={availabilityForm.end_time} onChange={e => setAvailabilityForm({ ...availabilityForm, end_time: e.target.value })}/></Field></Grid><Field label="Note"><Textarea value={availabilityForm.note} onChange={e => setAvailabilityForm({ ...availabilityForm, note: e.target.value })}/></Field></FormDialog>
 <FormDialog open={swapModal} onClose={() => !saving && setSwapModal(false)} title="Request shift change" description={selectedAssignment ? `${fmtDate(selectedAssignment.date)} · ${selectedAssignment.shift.name}` : ''} formId="swap-form" onSubmit={requestSwap} submitLabel="Submit" loading={saving}><Field label="Request"><Select value={swapForm.request_type} onChange={e => setSwapForm({ ...swapForm, request_type: e.target.value })}><Option value="swap">Swap with colleague</Option><Option value="drop">Drop shift</Option></Select></Field>{swapForm.request_type === 'swap' && <Field label="Swap with"><Select value={swapForm.target_member_id} onChange={e => setSwapForm({ ...swapForm, target_member_id: e.target.value })}><Option value="">Select employee</Option>{data.people.filter(p => p.id !== data.current_member_id).map(p => <Option key={p.id} value={p.id}>{name(p)}</Option>)}</Select></Field>}<Field label="Reason"><Textarea value={swapForm.message} onChange={e => setSwapForm({ ...swapForm, message: e.target.value })}/></Field></FormDialog>
 </Page>;
}
