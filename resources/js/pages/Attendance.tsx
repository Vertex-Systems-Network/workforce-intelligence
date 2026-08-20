import { FormEvent, useEffect, useMemo, useState } from 'react';
import { CalendarDays, CalendarPlus, ChevronLeft, ChevronRight, Coffee, LogIn, LogOut, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { BooleanField, useConfirmAction, FormDialog, ErrorState, Alert, Avatar, Badge, Button, Card, CardBody, CardHeader, DataGrid, Field, Input, Page, PageHeader, Select, StatCard, Box, Grid, Inline, Stack, Text, Option, type DataGridColumn } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
import AttendancePolicyPanel from '../components/AttendancePolicyPanel';
import { attendanceActionLocation, type AttendancePolicyLite } from '../attendance/location';
type Shift = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    location_type: string;
};
type BreakData = {
    id: number;
    type: string;
    paid: boolean;
    started_at: string;
    ended_at: string | null;
    duration_seconds: number;
};
type RecordData = {
    id: number;
    clock_in_at: string | null;
    clock_out_at: string | null;
    break_seconds: number;
    worked_seconds: number;
    late_minutes: number;
    overtime_minutes: number;
    status: string;
    flag_type?: string | null;
    note: string | null;
    breaks?: BreakData[];
};
type Holiday = {
    id: number;
    name: string;
    date: string;
    type: string;
    paid: boolean;
    status: string;
};
type Row = {
    member_id: number;
    name: string;
    department: string | null;
    shift: Shift | null;
    work_mode: string | null;
    record: RecordData | null;
    active_break: BreakData | null;
    display_status: string;
};
type Payload = {
    date: string;
    rows: Row[];
    holiday: Holiday | null;
    current_member_id: number;
    can_manage: boolean;
    policy: AttendancePolicyLite;
};
/** Handles the add days operation for the WorkIntel client. */ const addDays = (date: string, days: number) => { const d = new Date(`${date}T00:00:00`); d.setDate(d.getDate() + days); return d.toISOString().slice(0, 10); };
/** Formats fmt data for display. */ const fmt = (seconds: number) => { if (!seconds)
    return '—'; const h = Math.floor(seconds / 3600); const m = Math.floor((seconds % 3600) / 60); return h ? `${h}h ${m}m` : `${m}m`; };
/** Handles the time operation for the WorkIntel client. */ const time = (iso: string | null) => iso ? new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—';
const tone: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = { present: 'success', late: 'warning', absent: 'danger', missing_clock_out: 'danger', leave: 'info', wfh: 'info', partial: 'warning', holiday: 'info', scheduled: 'neutral', unscheduled: 'neutral' };
/** Handles the attendance operation for the WorkIntel client. */ export default function Attendance() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const workspace = session?.user.workspaces.find(item => item.id === workspaceId);
    const [data, setData] = useState<Payload | null>(null);
    const [date, setDate] = useState('');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [holidays, setHolidays] = useState<Holiday[]>([]);
    const [holidayModal, setHolidayModal] = useState(false);
    const [holidayForm, setHolidayForm] = useState({ name: '', date: '', type: 'public', paid: true });
    /** Loads load data required by the current view. */ const load = async (nextDate?: string) => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        const query = nextDate ?? date;
        const payload = await apiRequest<Payload>(`/api/v1/attendance${query ? `?date=${query}` : ''}`, { workspaceId });
        setData(payload);
        setDate(payload.date);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load attendance.');
    }
    finally {
        setLoading(false);
    } };
    /** Loads load holidays data required by the current view. */ const loadHolidays = async (year: number) => { if (!workspaceId)
        return; try {
        const payload = await apiRequest<{
            data: Holiday[];
        }>(`/api/v1/holidays?year=${year}`, { workspaceId, silent: true });
        setHolidays(payload.data);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load holidays.');
    } };
    useEffect(() => { void load(''); }, [workspaceId]);
    useEffect(() => { if (data?.can_manage)
        void loadHolidays(new Date(`${data.date}T00:00:00`).getFullYear()); }, [data?.date, data?.can_manage]);
    /** Handles the action location operation for the WorkIntel client. */ const actionLocation = async (memberId: number) => memberId === data?.current_member_id ? attendanceActionLocation(data?.policy) : Promise.resolve({ source: 'web' as const });
    /** Handles the clock operation for the WorkIntel client. */ const clock = async (memberId: number, direction: 'in' | 'out') => { if (!workspaceId)
        return; setSaving(true); setError(''); try {
        const location = await actionLocation(memberId);
        await apiRequest(`/api/v1/attendance/clock-${direction}`, { method: 'POST', workspaceId, body: JSON.stringify({ member_id: memberId, ...location }) });
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : `Could not clock ${direction}.`);
    }
    finally {
        setSaving(false);
    } };
    /** Handles the start break operation for the WorkIntel client. */ const startBreak = async (memberId: number) => { if (!workspaceId)
        return; setSaving(true); setError(''); try {
        const location = await actionLocation(memberId);
        await apiRequest('/api/v1/attendance/breaks/start', { method: 'POST', workspaceId, body: JSON.stringify({ member_id: memberId, type: 'break', paid: false, ...location }) });
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not start break.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the end break operation for the WorkIntel client. */ const endBreak = async (activeBreak: BreakData) => { if (!workspaceId)
        return; setSaving(true); setError(''); try {
        const memberId = data?.rows.find(row => row.active_break?.id === activeBreak.id)?.member_id ?? 0;
        const location = await actionLocation(memberId);
        await apiRequest(`/api/v1/attendance/breaks/${activeBreak.id}/end`, { method: 'POST', workspaceId, body: JSON.stringify(location) });
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not end break.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the save holiday operation for the WorkIntel client. */ const saveHoliday = async (e: FormEvent) => { e.preventDefault(); if (!workspaceId)
        return; setSaving(true); setError(''); try {
        await apiRequest('/api/v1/holidays', { method: 'POST', workspaceId, body: JSON.stringify({ ...holidayForm, status: 'active' }) });
        setHolidayModal(false);
        setHolidayForm({ name: '', date: '', type: 'public', paid: true });
        await Promise.all([load(date), loadHolidays(new Date(`${date}T00:00:00`).getFullYear())]);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not create holiday.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the remove holiday operation for the WorkIntel client. */ const removeHoliday = async (holiday: Holiday) => { if (!workspaceId || !await confirmAction({ title: 'Delete holiday?', description: `Delete ${holiday.name}?`, confirmLabel: 'Delete', danger: true }))
        return; setSaving(true); try {
        await apiRequest(`/api/v1/holidays/${holiday.id}`, { method: 'DELETE', workspaceId });
        await loadHolidays(new Date(`${date}T00:00:00`).getFullYear());
        if (holiday.date.slice(0, 10) === date)
            await load(date);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not delete holiday.');
    }
    finally {
        setSaving(false);
    } };
    const stats = useMemo(() => { const rows = data?.rows ?? []; return { present: rows.filter(r => r.record && ['present', 'late', 'wfh', 'partial'].includes(r.record.status)).length, late: rows.filter(r => r.record?.late_minutes).length, absent: rows.filter(r => !data?.holiday && (r.record?.status === 'absent' || (!r.record && r.shift))).length, overtime: rows.reduce((sum, r) => sum + (r.record?.overtime_minutes ?? 0), 0) }; }, [data]);
    if (loading && !data)
        return <PageLoadingState />;
    if (!data)
        return <Page><ErrorState title="Attendance unavailable" text={error || 'Attendance data could not be loaded.'} retry={() => load()}/></Page>;
    const today = new Date().toISOString().slice(0, 10);
    const ownOnly = workspace?.role === 'employee';
    const ownRow = data.rows.find(row => row.member_id === data.current_member_id);
    const attendanceColumns: DataGridColumn<Row>[] = [
        { id: 'employee', header: 'Employee', sortValue: row => row.name, searchValue: row => `${row.name} ${row.department ?? ''} ${row.work_mode ?? ''}`, cell: row => <Inline align="center" gap={8}><Avatar name={row.name} size="sm"/><Stack gap={2}><Text weight={550}>{row.name}</Text><Text size={10.5} color="var(--text-3)">{row.department || 'No department'}{row.work_mode ? ` · ${row.work_mode}` : ''}</Text></Stack></Inline> },
        { id: 'shift', header: 'Shift', sortValue: row => row.shift?.start_time ?? '', searchValue: row => row.shift?.name ?? '', cell: row => row.shift ? <Stack gap={2}><Text weight={550} size={12}>{row.shift.name}</Text><Text size={10.5} color="var(--text-3)">{row.shift.start_time?.slice(0, 5)} – {row.shift.end_time?.slice(0, 5)}</Text></Stack> : <Text color="var(--text-3)">No shift</Text> },
        { id: 'clock_in', header: 'Clock in', sortValue: row => row.record?.clock_in_at ?? '', cell: row => <Text className="stat-num">{time(row.record?.clock_in_at ?? null)}</Text> },
        { id: 'break', header: 'Break', sortValue: row => row.record?.break_seconds ?? 0, cell: row => <Stack gap={3}><Text className="stat-num">{fmt(row.record?.break_seconds ?? 0)}</Text>{row.active_break && <Badge tone="warning" dot>{row.active_break.type} active</Badge>}</Stack> },
        { id: 'clock_out', header: 'Clock out', sortValue: row => row.record?.clock_out_at ?? '', cell: row => <Text className="stat-num">{time(row.record?.clock_out_at ?? null)}</Text> },
        { id: 'worked', header: 'Worked', sortValue: row => row.record?.worked_seconds ?? 0, cell: row => <Text className="stat-num" weight={650}>{fmt(row.record?.worked_seconds ?? 0)}</Text> },
        { id: 'late', header: 'Late', sortValue: row => row.record?.late_minutes ?? 0, cell: row => row.record?.late_minutes ? <Text className="stat-num" color="var(--warning)">+{row.record.late_minutes}m</Text> : '—' },
        { id: 'overtime', header: 'Overtime', sortValue: row => row.record?.overtime_minutes ?? 0, cell: row => row.record?.overtime_minutes ? <Text className="stat-num" color="var(--info)">+{row.record.overtime_minutes}m</Text> : '—' },
        { id: 'status', header: 'Status', sortValue: row => row.display_status, filterValue: row => row.display_status, filter: { type: 'select', label: 'Status', options: Object.keys(tone).map(value => ({ value, label: value.replaceAll('_', ' ') })) }, cell: row => <Badge tone={tone[row.display_status] ?? 'neutral'} dot>{row.display_status}</Badge> },
        { id: 'actions', header: '', hideable: false, cell: row => { const record = row.record; const canAct = data.can_manage || row.member_id === data.current_member_id; return canAct && data.date === today ? <Inline gap={4} wrap="wrap">{!record?.clock_in_at && <Button variant="ghost" size="sm" onClick={() => void clock(row.member_id, 'in')} disabled={saving}><LogIn size={13}/> In</Button>}{record?.clock_in_at && !record.clock_out_at && !row.active_break && <Button variant="ghost" size="sm" onClick={() => void startBreak(row.member_id)} disabled={saving}><Coffee size={13}/> Break</Button>}{row.active_break && <Button variant="ghost" size="sm" onClick={() => void endBreak(row.active_break!)} disabled={saving}><Coffee size={13}/> End Break</Button>}{record?.clock_in_at && !record.clock_out_at && !row.active_break && <Button variant="ghost" size="sm" onClick={() => void clock(row.member_id, 'out')} disabled={saving}><LogOut size={13}/> Out</Button>}</Inline> : null; } },
    ];
    return <Page><PageHeader title={ownOnly ? 'My Attendance' : 'Team Attendance'} description={ownOnly ? 'Clock in, take breaks, clock out and review your attendance history.' : 'Review visible team attendance, shifts, breaks, late arrivals and overtime.'} actions={<><Button variant="outline" size="sm" iconOnly aria-label="Previous day" onClick={() => void load(addDays(data.date, -1))}><ChevronLeft size={14}/></Button><Button variant="outline" size="sm" onClick={() => void load(today)}><CalendarDays size={14}/>{new Date(`${data.date}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}</Button><Button variant="outline" size="sm" iconOnly aria-label="Next day" onClick={() => void load(addDays(data.date, 1))}><ChevronRight size={14}/></Button>{data.can_manage && <Button variant="secondary" size="sm" onClick={() => { setHolidayForm({ name: '', date: data.date, type: 'public', paid: true }); setHolidayModal(true); }}><CalendarPlus size={13}/> Holiday</Button>}<Button variant="ghost" size="sm" onClick={() => void load()}><RefreshCw size={13}/> Refresh</Button></>}/>{error && <Alert tone="danger">{error}</Alert>}{data.holiday && <Alert tone="info"><strong>{data.holiday.name}</strong> · {data.holiday.type} holiday · {data.holiday.paid ? 'Paid' : 'Unpaid'}</Alert>}
 <Grid columns="repeat(auto-fit,minmax(150px,1fr))" gap={12} m="14px 0 16px">{ownOnly ? <><StatCard label="Today's status" value={ownRow?.display_status ?? 'Not started'} sub={ownRow?.record?.clock_in_at ? `Clock in ${time(ownRow.record.clock_in_at)}` : 'Use Clock In above to start your day'}/><StatCard label="Worked" value={fmt(ownRow?.record?.worked_seconds ?? 0)} sub="Today"/><StatCard label="Breaks" value={fmt(ownRow?.record?.break_seconds ?? 0)} sub={ownRow?.active_break ? 'Break active' : 'Today'}/><StatCard label="Overtime" value={`${ownRow?.record?.overtime_minutes ?? 0}m`} sub="Today"/></> : <><StatCard label="Present" value={String(stats.present)} sub={`${data.rows.length} visible team members`}/><StatCard label="Late" value={String(stats.late)} sub="After shift grace period"/><StatCard label="Absent / Missing" value={String(stats.absent)} sub={data.holiday ? 'Holiday excluded' : 'Scheduled without attendance'}/><StatCard label="Overtime" value={`${stats.overtime}m`} sub="Visible team total"/></>}</Grid>
 <AttendancePolicyPanel workspaceId={workspaceId} currentDate={data.date}/>
 <DataGrid rows={data.rows} columns={attendanceColumns} rowKey={row => row.member_id} persistKey={ownOnly ? 'attendance.mine' : 'attendance.team'} searchPlaceholder={ownOnly ? 'Search attendance…' : 'Search employee, department or shift…'} defaultSort={{ id: 'employee', direction: 'asc' }} onRefresh={() => load()} empty={<Text color="var(--text-3)">No attendance rows are available for this date.</Text>} mobileCard={row => <Stack gap={6}><Inline justify="space-between" align="center"><strong>{row.name}</strong><Badge tone={tone[row.display_status] ?? 'neutral'}>{row.display_status}</Badge></Inline><Text size={11} color="var(--text-2)">{row.shift ? `${row.shift.name} · ${row.shift.start_time.slice(0, 5)}–${row.shift.end_time.slice(0, 5)}` : 'No shift'}</Text><Inline gap={10} wrap="wrap"><Text size={11}>In {time(row.record?.clock_in_at ?? null)}</Text><Text size={11}>Worked {fmt(row.record?.worked_seconds ?? 0)}</Text><Text size={11}>OT {row.record?.overtime_minutes ?? 0}m</Text></Inline></Stack>}/>

 {data.can_manage && <Card mt={14}><CardHeader title="Holiday Calendar" description={`${new Date(`${data.date}T00:00:00`).getFullYear()} workspace holidays`} action={<Button variant="outline" size="sm" onClick={() => { setHolidayForm({ name: '', date: data.date, type: 'public', paid: true }); setHolidayModal(true); }}><Plus size={13}/> Add Holiday</Button>}/><CardBody>{holidays.length ? <Grid columns="repeat(auto-fill,minmax(220px,1fr))" gap={8}>{holidays.map(holiday => <Box key={holiday.id} p={10} border="1px solid var(--border)" radius={8} display="flex" gap={9} align="center"><CalendarDays size={15} color="var(--accent)"/><Box flex={1}><Box size={12} weight={600}>{holiday.name}</Box><div className="ui-card-description">{holiday.date.slice(0, 10)} · {holiday.type} · {holiday.paid ? 'Paid' : 'Unpaid'}</div></Box><Button variant="ghost" size="sm" iconOnly aria-label="Delete holiday" onClick={() => void removeHoliday(holiday)}><Trash2 size={13}/></Button></Box>)}</Grid> : <div className="ui-card-description">No holidays configured for this year.</div>}</CardBody></Card>}
 <FormDialog open={holidayModal} onClose={() => setHolidayModal(false)} title="Add holiday" description="Holidays can be excluded automatically from leave-day calculations." formId="holiday-form" onSubmit={saveHoliday} submitLabel="Save Holiday" loading={saving}><Field label="Holiday name"><Input value={holidayForm.name} onChange={e => setHolidayForm({ ...holidayForm, name: e.target.value })} required/></Field><Grid columns="1fr 1fr" gap={10}><Field label="Date"><Input type="date" value={holidayForm.date} onChange={e => setHolidayForm({ ...holidayForm, date: e.target.value })} required/></Field><Field label="Type"><Select value={holidayForm.type} onChange={e => setHolidayForm({ ...holidayForm, type: e.target.value })}><Option value="public">Public</Option><Option value="company">Company</Option><Option value="optional">Optional</Option></Select></Field></Grid><BooleanField label="Paid holiday" description="Used later by payroll and attendance rules." checked={holidayForm.paid} onChange={paid => setHolidayForm({ ...holidayForm, paid })}/></FormDialog>
 </Page>;
}
