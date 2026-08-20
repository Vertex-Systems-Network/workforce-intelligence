import { FormEvent, useEffect, useState } from 'react';
import { Check, LocateFixed, MapPin, Plus, RotateCcw, Settings2, X } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { hasPermission } from '../access';
import { useConfirmAction, Alert, Badge, Button, Card, CardBody, CardHeader, DataGrid, Field, FormDialog, Input, Select, Switch, SettingRow, Textarea, Box, Grid, Inline, Stack, Text, Option, type DataGridColumn } from '../design-system';
type Policy = {
    id: number;
    allow_web: boolean;
    allow_mobile: boolean;
    require_geolocation: boolean;
    require_geofence: boolean;
    max_accuracy_meters: number;
    correction_window_days: number;
    missed_clock_out_hours: number;
    auto_flag_missed_clock_out: boolean;
    allow_employee_corrections: boolean;
};
type Location = {
    id: number;
    uuid: string;
    name: string;
    latitude: number;
    longitude: number;
    radius_meters: number;
    status: 'active' | 'inactive';
};
type Correction = {
    id: number;
    uuid: string;
    date: string;
    requested_clock_in_at: string | null;
    requested_clock_out_at: string | null;
    reason: string;
    status: string;
    review_note: string | null;
    member?: {
        id: number;
        user?: {
            first_name: string;
            last_name: string;
        };
    };
};
type SettingsPayload = {
    policy: Policy;
    locations: Location[];
    can_manage_policy: boolean;
};
/** Handles the to local input operation for the WorkIntel client. */ const toLocalInput = (date: string, time = '09:00') => `${date}T${time}`;
/** Handles the display date time operation for the WorkIntel client. */ const displayDateTime = (value: string | null) => value ? new Date(value).toLocaleString() : '—';
/** Handles the attendance phase15 panel operation for the WorkIntel client. */ export default function AttendancePolicyPanel({ workspaceId, currentDate }: {
    workspaceId: number;
    currentDate: string;
}) {
    const { session } = useAuth();
    const workspace = session?.user.workspaces.find(item => item.id === workspaceId);
    const confirmAction = useConfirmAction();
    const canManageAttendance = hasPermission(workspace, 'attendance.manage');
    const canManagePolicy = hasPermission(workspace, 'attendance.policy_manage');
    const [settings, setSettings] = useState<SettingsPayload | null>(null), [corrections, setCorrections] = useState<Correction[]>([]);
    const [error, setError] = useState(''), [saving, setSaving] = useState(false), [correctionOpen, setCorrectionOpen] = useState(false), [locationOpen, setLocationOpen] = useState(false);
    const [correctionForm, setCorrectionForm] = useState({ date: currentDate, clock_in: toLocalInput(currentDate, '09:00'), clock_out: toLocalInput(currentDate, '18:00'), reason: '' });
    const [locationForm, setLocationForm] = useState({ name: 'Office', latitude: '', longitude: '', radius_meters: '150', status: 'active' });
    /** Loads load data required by the current view. */ const load = async () => { try {
        const [s, c] = await Promise.all([apiRequest<SettingsPayload>('/api/v1/attendance/settings', { workspaceId, silent: true }), apiRequest<{
                data: Correction[];
            }>('/api/v1/attendance/corrections', { workspaceId, silent: true })]);
        setSettings(s);
        setCorrections(c.data);
        setError('');
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load Attendance 2.0 settings.');
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    useEffect(() => { setCorrectionForm(f => ({ ...f, date: currentDate, clock_in: toLocalInput(currentDate, '09:00'), clock_out: toLocalInput(currentDate, '18:00') })); }, [currentDate]);
    /** Handles the save policy operation for the WorkIntel client. */ const savePolicy = async () => { if (!settings)
        return; setSaving(true); try {
        const r = await apiRequest<{
            data: Policy;
        }>('/api/v1/attendance/settings', { method: 'PUT', workspaceId, body: JSON.stringify(settings.policy) });
        setSettings({ ...settings, policy: r.data });
        setError('');
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not save attendance policy.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the submit correction operation for the WorkIntel client. */ const submitCorrection = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest('/api/v1/attendance/corrections', { method: 'POST', workspaceId, body: JSON.stringify({ date: correctionForm.date, requested_clock_in_at: correctionForm.clock_in || null, requested_clock_out_at: correctionForm.clock_out || null, reason: correctionForm.reason }) });
        setCorrectionOpen(false);
        setCorrectionForm({ ...correctionForm, reason: '' });
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not request attendance correction.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the review operation for the WorkIntel client. */ const review = async (row: Correction, status: 'approved' | 'rejected') => { setSaving(true); try {
        await apiRequest(`/api/v1/attendance/corrections/${row.id}/review`, { method: 'PATCH', workspaceId, body: JSON.stringify({ status, review_note: status === 'approved' ? 'Approved from Attendance 2.0' : 'Rejected from Attendance 2.0' }) });
        await load();
        window.dispatchEvent(new CustomEvent('workintel:attendance-changed'));
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not review correction.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the detect location operation for the WorkIntel client. */ const detectLocation = () => { if (!navigator.geolocation) {
        setError('This browser does not provide geolocation.');
        return;
    } navigator.geolocation.getCurrentPosition(p => setLocationForm(f => ({ ...f, latitude: String(p.coords.latitude), longitude: String(p.coords.longitude) })), e => setError(e.message), { enableHighAccuracy: true, timeout: 12000 }); };
    /** Handles the save location operation for the WorkIntel client. */ const saveLocation = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest('/api/v1/attendance/locations', { method: 'POST', workspaceId, body: JSON.stringify({ ...locationForm, latitude: Number(locationForm.latitude), longitude: Number(locationForm.longitude), radius_meters: Number(locationForm.radius_meters) }) });
        setLocationOpen(false);
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not save work location.');
    }
    finally {
        setSaving(false);
    } };
    /** Removes an approved work location after an app-owned confirmation. */ const removeLocation = async (row: Location) => { if (!await confirmAction({ title: 'Delete approved work location?', description: `${row.name} will no longer be accepted for geofenced attendance.`, confirmLabel: 'Delete Location', danger: true }))
        return; setSaving(true); try {
        await apiRequest(`/api/v1/attendance/locations/${row.id}`, { method: 'DELETE', workspaceId });
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not delete work location.');
    }
    finally {
        setSaving(false);
    } };
    if (!settings)
        return error ? <Alert tone="danger">{error}</Alert> : null;
    const p = settings.policy;
    /** Defines the correction-review grid for employee and manager scopes. */ const correctionColumns: DataGridColumn<Correction>[] = [
        ...(canManageAttendance ? [{ id: 'employee', header: 'Employee', value: row => `${row.member?.user?.first_name ?? ''} ${row.member?.user?.last_name ?? ''}`.trim() || 'Employee', sortable: true } as DataGridColumn<Correction>] : []),
        { id: 'date', header: 'Date', value: row => row.date.slice(0, 10), sortable: true, filter: { type: 'dateRange' } },
        { id: 'clock_in', header: 'Requested In', value: row => row.requested_clock_in_at || '', cell: row => displayDateTime(row.requested_clock_in_at), sortable: true },
        { id: 'clock_out', header: 'Requested Out', value: row => row.requested_clock_out_at || '', cell: row => displayDateTime(row.requested_clock_out_at), sortable: true },
        { id: 'reason', header: 'Reason', value: row => row.reason, cell: row => <Text size={11.5}>{row.reason}</Text> },
        { id: 'status', header: 'Status', value: row => row.status, cell: row => <Badge tone={row.status === 'approved' ? 'success' : row.status === 'rejected' ? 'danger' : row.status === 'pending' ? 'warning' : 'neutral'}>{row.status}</Badge>, sortable: true, filter: { type: 'select', options: [{ label: 'Pending', value: 'pending' }, { label: 'Approved', value: 'approved' }, { label: 'Rejected', value: 'rejected' }] } },
        ...(canManageAttendance ? [{ id: 'actions', header: 'Action', hideable: false, cell: row => row.status === 'pending' ? <Inline gap={5}><Button variant="ghost" size="sm" loading={saving} onClick={() => void review(row, 'approved')}><Check size={13}/> Approve</Button><Button variant="ghost" size="sm" onClick={() => void review(row, 'rejected')}><X size={13}/> Reject</Button></Inline> : '—' } as DataGridColumn<Correction>] : []),
    ];
    return <Stack gap={14} m="0 0 16px">
    {error && <Alert tone="danger">{error}</Alert>}
    <Card><CardHeader title="Attendance 2.0" description={canManagePolicy ? 'Attendance rules, location proof and correction workflow.' : 'Your clocking rules and attendance correction workflow.'} action={<Inline gap={7}><Button variant="outline" size="sm" onClick={() => setCorrectionOpen(true)} disabled={!p.allow_employee_corrections && !canManageAttendance}><RotateCcw size={13}/> Request Correction</Button>{canManagePolicy && <Button variant="primary" size="sm" loading={saving} onClick={() => void savePolicy()}><Settings2 size={13}/> Save Policy</Button>}</Inline>}/><CardBody>
      <Grid columns="repeat(auto-fit,minmax(175px,1fr))" gap={9}>
        <Rule label="Location proof" value={p.require_geolocation ? 'Required' : 'Optional'} tone={p.require_geolocation ? 'warning' : 'neutral'}/>
        <Rule label="Geofence" value={p.require_geofence ? 'Required' : 'Not required'} tone={p.require_geofence ? 'warning' : 'neutral'}/>
        <Rule label="Correction window" value={`${p.correction_window_days} days`}/>
        <Rule label="Missed clock-out" value={`${p.missed_clock_out_hours}h flag`}/>
      </Grid>
      {canManagePolicy && <Grid columns="repeat(auto-fit,minmax(220px,1fr))" gap={10} mt={14}>
        <SettingRow title="Require device location" description="Clock actions include browser/device location evidence." control={<Switch checked={p.require_geolocation} onChange={v => setSettings({ ...settings, policy: { ...p, require_geolocation: v } })}/>}/>
        <SettingRow title="Require approved geofence" description="Clock actions must be inside an approved work-location radius." control={<Switch checked={p.require_geofence} onChange={v => setSettings({ ...settings, policy: { ...p, require_geofence: v, require_geolocation: v ? true : p.require_geolocation } })}/>}/>
        <SettingRow title="Employee corrections" description="Employees can request corrections inside the configured time window." control={<Switch checked={p.allow_employee_corrections} onChange={v => setSettings({ ...settings, policy: { ...p, allow_employee_corrections: v } })}/>}/>
        <SettingRow title="Auto-flag missed clock-out" description="Long-running attendance records are flagged for review automatically." control={<Switch checked={p.auto_flag_missed_clock_out} onChange={v => setSettings({ ...settings, policy: { ...p, auto_flag_missed_clock_out: v } })}/>}/>
        <Field label="Max GPS accuracy (m)"><Input type="number" min={10} max={5000} value={p.max_accuracy_meters} onChange={e => setSettings({ ...settings, policy: { ...p, max_accuracy_meters: Number(e.target.value) } })}/></Field>
        <Field label="Correction window (days)"><Input type="number" min={1} max={90} value={p.correction_window_days} onChange={e => setSettings({ ...settings, policy: { ...p, correction_window_days: Number(e.target.value) } })}/></Field>
        <Field label="Missed clock-out after (hours)"><Input type="number" min={4} max={48} value={p.missed_clock_out_hours} onChange={e => setSettings({ ...settings, policy: { ...p, missed_clock_out_hours: Number(e.target.value) } })}/></Field>
      </Grid>}
    </CardBody></Card>

    {canManagePolicy && <Card><CardHeader title="Approved Work Locations" description="When geofence is required, clock actions must fall inside one of these radiuses." action={<Button variant="outline" size="sm" onClick={() => setLocationOpen(true)}><Plus size={13}/> Location</Button>}/><CardBody>{settings.locations.length ? <Grid columns="repeat(auto-fit,minmax(220px,1fr))" gap={8}>{settings.locations.map(row => <Box key={row.id} border="1px solid var(--border)" radius={8} p={11} display="flex" gap={9} align="center"><MapPin size={16}/><Box flex={1}><Box weight={600} size={12}>{row.name}</Box><div className="ui-card-description">{row.radius_meters}m radius · {row.status}</div></Box><Button variant="ghost" size="sm" iconOnly aria-label="Delete location" onClick={() => void removeLocation(row)}><X size={13}/></Button></Box>)}</Grid> : <div className="ui-card-description">No approved work locations. Add one before enabling required geofence.</div>}</CardBody></Card>}

    <Card><CardHeader title={canManageAttendance ? 'Attendance Corrections' : 'My Corrections'} description={canManageAttendance ? 'Pending employee corrections and review history.' : 'Request a correction if you forgot to clock in/out or the recorded time is wrong.'}/><CardBody><DataGrid rows={corrections} columns={correctionColumns} rowKey={row => row.id} persistKey={canManageAttendance ? 'attendance.corrections.team' : 'attendance.corrections.mine'} searchable searchPlaceholder="Search corrections" defaultSort={{ id: 'date', direction: 'desc' }} empty="No attendance correction requests." ariaLabel={canManageAttendance ? 'Team attendance corrections' : 'My attendance corrections'}/></CardBody></Card>

    <FormDialog open={correctionOpen} onClose={() => !saving && setCorrectionOpen(false)} title="Request attendance correction" description={`Corrections can be requested for the last ${p.correction_window_days} days.`} formId="attendance-correction-form" onSubmit={submitCorrection} submitLabel="Submit Request" loading={saving} gap={11}><Field label="Attendance date"><Input type="date" value={correctionForm.date} onChange={e => setCorrectionForm({ ...correctionForm, date: e.target.value, clock_in: toLocalInput(e.target.value, '09:00'), clock_out: toLocalInput(e.target.value, '18:00') })} max={new Date().toISOString().slice(0, 10)} required/></Field><Grid columns="1fr 1fr" gap={9}><Field label="Correct clock in"><Input type="datetime-local" value={correctionForm.clock_in} onChange={e => setCorrectionForm({ ...correctionForm, clock_in: e.target.value })}/></Field><Field label="Correct clock out"><Input type="datetime-local" value={correctionForm.clock_out} onChange={e => setCorrectionForm({ ...correctionForm, clock_out: e.target.value })}/></Field></Grid><Field label="Reason"><Textarea value={correctionForm.reason} onChange={e => setCorrectionForm({ ...correctionForm, reason: e.target.value })} placeholder="Example: I forgot to clock out before leaving the site." required/></Field></FormDialog>

    <FormDialog open={locationOpen} onClose={() => !saving && setLocationOpen(false)} title="Add approved work location" description="Use the current device location or enter coordinates for the office/site." formId="attendance-location-form" onSubmit={saveLocation} submitLabel="Save Location" loading={saving} gap={11}><Field label="Location name"><Input value={locationForm.name} onChange={e => setLocationForm({ ...locationForm, name: e.target.value })} required/></Field><Button type="button" variant="outline" onClick={detectLocation}><LocateFixed size={13}/> Use Current Device Location</Button><Grid columns="1fr 1fr" gap={9}><Field label="Latitude"><Input value={locationForm.latitude} onChange={e => setLocationForm({ ...locationForm, latitude: e.target.value })} required/></Field><Field label="Longitude"><Input value={locationForm.longitude} onChange={e => setLocationForm({ ...locationForm, longitude: e.target.value })} required/></Field></Grid><Field label="Allowed radius (meters)"><Input type="number" min={20} max={10000} value={locationForm.radius_meters} onChange={e => setLocationForm({ ...locationForm, radius_meters: e.target.value })}/></Field><Field label="Status"><Select value={locationForm.status} onChange={e => setLocationForm({ ...locationForm, status: e.target.value })}><Option value="active">Active</Option><Option value="inactive">Inactive</Option></Select></Field></FormDialog>
  </Stack>;
}
/** Handles the rule operation for the WorkIntel client. */ function Rule({ label, value, tone = 'neutral' }: {
    label: string;
    value: string;
    tone?: 'neutral' | 'warning';
}) { return <Box p={10} border="1px solid var(--border)" radius={8}><div className="ui-card-description">{label}</div><Box mt={4} size={13} weight={650}>{value} {tone === 'warning' && <Badge tone="warning">Policy</Badge>}</Box></Box>; }
