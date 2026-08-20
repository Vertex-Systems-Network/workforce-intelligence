import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Activity, BadgeCheck, CircleDot, CloudDownload, Copy, Ellipsis, HardDrive, Laptop, PauseCircle, RefreshCw, RotateCw, ShieldCheck, Smartphone, TerminalSquare, Wifi, WifiOff, XCircle, } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { hasPermission } from '../access';
import { PageLoadingState } from '../components/LoadingStates';
import { useConfirmAction, Alert, Badge, Button, Card, CardBody, Drawer, Dropdown, EmptyState, Field, Input, Modal, Page, PageHeader, Select, DataGrid, Pressable, Box, Grid, Inline, Stack, Text, Form, Option, type DataGridColumn } from '../design-system';
type Device = {
    id: number;
    uuid: string;
    member_id: number;
    employee: string;
    department: string | null;
    name: string;
    platform: 'windows' | 'macos' | 'linux';
    os_name: string;
    os_version: string | null;
    architecture: string | null;
    agent_version: string | null;
    status: 'active' | 'revoked';
    connection_status: 'online' | 'offline';
    health: 'healthy' | 'update_available' | 'unsupported';
    tracking_status: 'active' | 'paused' | 'stopped';
    is_idle: boolean;
    offline_queue_size: number;
    capabilities: string[];
    last_ip: string | null;
    enrolled_at: string | null;
    last_heartbeat_at: string | null;
    last_seen_at: string | null;
    last_sync_at: string | null;
    revoked_at: string | null;
};
type Member = {
    id: number;
    name: string;
    job_title: string | null;
};
type DeviceIndex = {
    devices: Device[];
    members: Member[];
    agent: {
        latest_version: string;
        minimum_supported_version: string;
        heartbeat_interval_seconds: number;
        online_threshold_seconds: number;
    };
    stats: {
        total: number;
        online: number;
        update_required: number;
        revoked: number;
    };
};
type DeviceDetail = {
    device: Device;
    events: Array<{
        id: number;
        event_uuid: string;
        event_type: string;
        occurred_at: string;
        payload: Record<string, unknown> | null;
    }>;
    sync_batches: Array<{
        batch_uuid: string;
        event_count: number;
        accepted_count: number;
        duplicate_count: number;
        received_at: string;
    }>;
    commands: Array<{
        uuid: string;
        command_type: string;
        status: string;
        created_at: string | null;
        acknowledged_at: string | null;
    }>;
};
type Enrollment = {
    enrollment_code: string;
    expires_at: string;
    member_id: number;
    enrollment_endpoint: string;
    browser_enrollment_endpoint?: string;
    message: string;
};
/** Handles the relative time operation for the WorkIntel client. */ function relativeTime(value: string | null): string {
    if (!value)
        return 'Never';
    const timestamp = new Date(value).getTime();
    if (Number.isNaN(timestamp))
        return 'Unknown';
    const seconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
    if (seconds < 10)
        return 'Now';
    if (seconds < 60)
        return `${seconds}s ago`;
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60)
        return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24)
        return `${hours}h ago`;
    return `${Math.floor(hours / 24)}d ago`;
}
/** Handles the command label operation for the WorkIntel client. */ function commandLabel(type: string) {
    return type.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}
/** Handles the devices operation for the WorkIntel client. */ export default function Devices() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const activeWorkspace = session?.user.workspaces.find(workspace => workspace.id === workspaceId);
    const canManage = hasPermission(activeWorkspace, 'devices.manage');
    const [data, setData] = useState<DeviceIndex | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [enrollOpen, setEnrollOpen] = useState(false);
    const [enrollSaving, setEnrollSaving] = useState(false);
    const [memberId, setMemberId] = useState('');
    const [expiresMinutes, setExpiresMinutes] = useState('10');
    const [enrollment, setEnrollment] = useState<Enrollment | null>(null);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [detail, setDetail] = useState<DeviceDetail | null>(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [actionId, setActionId] = useState<number | null>(null);
    const [renameDevice, setRenameDevice] = useState<Device | null>(null);
    const [renameValue, setRenameValue] = useState('');
    /** Loads load data required by the current view. */ const load = async (silent = false) => {
        if (!workspaceId)
            return;
        if (!silent)
            setLoading(true);
        setError('');
        try {
            setData(await apiRequest<DeviceIndex>('/api/v1/devices', { workspaceId, silent }));
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not load devices.');
        }
        finally {
            if (!silent)
                setLoading(false);
        }
    };
    useEffect(() => { void load(); }, [workspaceId]);
    useEffect(() => {
        if (!workspaceId || selectedId === null) {
            setDetail(null);
            return;
        }
        let active = true;
        setDetailLoading(true);
        apiRequest<DeviceDetail>(`/api/v1/devices/${selectedId}`, { workspaceId, silent: true })
            .then(payload => { if (active)
            setDetail(payload); })
            .catch(err => { if (active)
            setError(err instanceof Error ? err.message : 'Could not load device details.'); })
            .finally(() => { if (active)
            setDetailLoading(false); });
        return () => { active = false; };
    }, [workspaceId, selectedId]);
    const sortedDevices = useMemo(() => data?.devices ?? [], [data]);
    /** Handles the open enrollment operation for the WorkIntel client. */ const openEnrollment = () => {
        setEnrollment(null);
        setMemberId(data?.members[0] ? String(data.members[0].id) : '');
        setExpiresMinutes('10');
        setEnrollOpen(true);
    };
    /** Handles the create enrollment operation for the WorkIntel client. */ const createEnrollment = async (event: FormEvent) => {
        event.preventDefault();
        if (!workspaceId || !memberId)
            return;
        setEnrollSaving(true);
        setError('');
        try {
            const payload = await apiRequest<Enrollment>('/api/v1/devices/enrollments', {
                method: 'POST', workspaceId,
                body: JSON.stringify({ member_id: Number(memberId), expires_minutes: Number(expiresMinutes) }),
            });
            setEnrollment(payload);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not create enrollment code.');
        }
        finally {
            setEnrollSaving(false);
        }
    };
    /** Handles the copy enrollment operation for the WorkIntel client. */ const copyEnrollment = async () => {
        if (!enrollment)
            return;
        await navigator.clipboard.writeText(enrollment.enrollment_code);
    };
    /** Handles the run command operation for the WorkIntel client. */ const runCommand = async (device: Device, commandType: 'update_agent' | 'restart_agent' | 'pause_tracking' | 'resume_tracking') => {
        if (!workspaceId)
            return;
        setActionId(device.id);
        setError('');
        try {
            await apiRequest(`/api/v1/devices/${device.id}/commands`, {
                method: 'POST', workspaceId, body: JSON.stringify({ command_type: commandType }),
            });
            await load(true);
            if (selectedId === device.id)
                setSelectedId(null);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not queue device command.');
        }
        finally {
            setActionId(null);
        }
    };
    /** Handles the revoke operation for the WorkIntel client. */ const revoke = async (device: Device) => {
        if (!workspaceId || !await confirmAction({ title: 'Revoke device?', description: `Revoke ${device.name}? The agent will need a new enrollment code before it can reconnect.`, confirmLabel: 'Revoke', danger: true }))
            return;
        setActionId(device.id);
        setError('');
        try {
            await apiRequest(`/api/v1/devices/${device.id}/revoke`, { method: 'POST', workspaceId });
            if (selectedId === device.id)
                setSelectedId(null);
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not revoke device.');
        }
        finally {
            setActionId(null);
        }
    };
    /** Handles the save rename operation for the WorkIntel client. */ const saveRename = async (event: FormEvent) => {
        event.preventDefault();
        if (!workspaceId || !renameDevice || !renameValue.trim())
            return;
        setActionId(renameDevice.id);
        try {
            await apiRequest(`/api/v1/devices/${renameDevice.id}`, { method: 'PUT', workspaceId, body: JSON.stringify({ name: renameValue.trim() }) });
            setRenameDevice(null);
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not rename device.');
        }
        finally {
            setActionId(null);
        }
    };
    /** Build shared DataGrid columns for enrolled device management. */
    const deviceColumns = useMemo<DataGridColumn<Device>[]>(() => [
        { id: 'employee', header: 'Employee', searchValue: row => `${row.employee} ${row.department ?? ''}`, cell: row => <div><Button size="sm" variant="ghost" onClick={() => setSelectedId(row.id)}>{row.employee}</Button><div className="ui-card-description">{row.department ?? 'No department'}</div></div> },
        { id: 'device', header: 'Device', searchValue: row => `${row.name} ${row.architecture ?? ''}`, cell: row => <Inline align="center" gap={7}>{row.platform === 'macos' ? <Smartphone size={14}/> : <Laptop size={14}/>}<div><Text weight={550}>{row.name}</Text><div className="ui-card-description">{row.architecture ?? 'Unknown architecture'}</div></div></Inline> },
        { id: 'os', header: 'OS', value: row => `${row.os_name} ${row.os_version ?? ''}`, cell: row => <Text color="var(--text-2)">{row.os_name}{row.os_version ? ` ${row.os_version}` : ''}</Text> },
        { id: 'agent', header: 'Agent', sortValue: row => row.agent_version ?? '', cell: row => <Stack gap={4}><span className="stat-num">v{row.agent_version ?? '—'}</span><Badge tone={row.health === 'healthy' ? 'success' : row.health === 'update_available' ? 'warning' : 'danger'}>{row.health === 'healthy' ? 'Current' : row.health === 'update_available' ? 'Update' : 'Unsupported'}</Badge></Stack> },
        { id: 'connection', header: 'Connection', value: row => row.status === 'revoked' ? 'revoked' : row.connection_status, cell: row => <Stack gap={3}><Badge tone={row.status === 'revoked' ? 'neutral' : row.connection_status === 'online' ? 'success' : 'neutral'} dot>{row.status === 'revoked' ? 'Revoked' : row.connection_status === 'online' ? 'Online' : 'Offline'}</Badge>{row.is_idle && row.status === 'active' && <span className="ui-card-description">Idle</span>}</Stack> },
        { id: 'last-seen', header: 'Last Seen', sortValue: row => row.last_seen_at ? new Date(row.last_seen_at).getTime() : 0, cell: row => row.connection_status === 'offline' ? <Inline gap={5} align="center"><WifiOff size={12}/><span>{relativeTime(row.last_seen_at)}</span></Inline> : relativeTime(row.last_seen_at) },
        { id: 'last-sync', header: 'Last Sync', sortValue: row => row.last_sync_at ? new Date(row.last_sync_at).getTime() : 0, cell: row => <div>{relativeTime(row.last_sync_at)}{row.offline_queue_size > 0 && <div className="ui-card-description">{row.offline_queue_size} queued</div>}</div> },
        { id: 'tracking', header: 'Tracking', value: row => row.tracking_status, cell: row => <Badge tone={row.tracking_status === 'active' ? 'accent' : row.tracking_status === 'paused' ? 'warning' : 'neutral'}>{row.tracking_status}</Badge> },
        { id: 'actions', header: '', sortable: false, hideable: false, cell: row => <Dropdown trigger={<Button variant="ghost" size="sm" iconOnly loading={actionId === row.id} aria-label={`Actions for ${row.name}`}><Ellipsis size={15}/></Button>} items={[{ label: 'View details', icon: <BadgeCheck size={14}/>, onClick: () => setSelectedId(row.id) }, ...(canManage && row.status === 'active' ? [{ label: 'Rename device', onClick: () => { setRenameDevice(row); setRenameValue(row.name); } }, { label: 'Force update', icon: <RefreshCw size={14}/>, onClick: () => void runCommand(row, 'update_agent') }, { label: 'Restart agent', icon: <RotateCw size={14}/>, onClick: () => void runCommand(row, 'restart_agent') }, { label: row.tracking_status === 'paused' ? 'Resume tracking' : 'Pause tracking', icon: <PauseCircle size={14}/>, onClick: () => void runCommand(row, row.tracking_status === 'paused' ? 'resume_tracking' : 'pause_tracking') }, { separator: true }, { label: 'Revoke device', danger: true, icon: <XCircle size={14}/>, onClick: () => void revoke(row) }] : [])]}/> },
    ], [actionId, canManage, workspaceId, selectedId]);
    if (loading && !data)
        return <PageLoadingState />;
    return <Page>
    <PageHeader title="Devices & Agents" description="Enroll desktop agents, monitor heartbeats, inspect offline sync and manage device access" actions={<>
        <Button variant="outline" size="sm" loading={loading} onClick={() => void load()}><RefreshCw size={14}/> Refresh</Button>
        {canManage && <Button variant="primary" size="sm" onClick={openEnrollment}><CloudDownload size={14}/> Enroll Device</Button>}
      </>}/>

    {error && <Alert tone="danger">{error}</Alert>}

    <Grid columns="repeat(auto-fit,minmax(190px,1fr))" gap={10} m="14px 0">
      {[
            ['Online agents', String(data?.stats.online ?? 0), 'Fresh heartbeat received', <CircleDot size={17} color="var(--success)"/>],
            ['Need update', String(data?.stats.update_required ?? 0), `Latest v${data?.agent.latest_version ?? '—'}`, <RefreshCw size={17} color="var(--warning)"/>],
            ['Managed devices', String(data?.stats.total ?? 0), 'Enrolled installations', <Laptop size={17} color="var(--accent)"/>],
            ['Revoked', String(data?.stats.revoked ?? 0), 'Access disabled', <ShieldCheck size={17} color="var(--text-3)"/>],
        ].map(([label, value, sub, icon]) => <Card key={label as string}><CardBody><Inline justify="space-between"><div><div className="ui-card-description">{label}</div><Box className="stat-num" size={23} weight={700} m="4px 0">{value}</Box><div className="ui-card-description">{sub}</div></div>{icon}</Inline></CardBody></Card>)}
    </Grid>

    {sortedDevices.length === 0 ? <Card><CardBody><EmptyState icon={<HardDrive size={22}/>} title="No desktop agents enrolled" text="Generate a one-time enrollment code for an employee, then enter it in the desktop agent." action={canManage ? <Button variant="primary" onClick={openEnrollment}><CloudDownload size={14}/> Enroll first device</Button> : undefined}/></CardBody></Card> : <DataGrid rows={sortedDevices} columns={deviceColumns} rowKey={row => row.id} persistKey="devices.agents" defaultSort={{ id: 'last-seen', direction: 'desc' }} searchPlaceholder="Search employee, device, OS or agent…" ariaLabel="Devices and agents" mobileCard={row => <Stack gap={5}><Inline justify="space-between"><Button size="sm" variant="ghost" onClick={() => setSelectedId(row.id)}>{row.employee}</Button><Badge tone={row.connection_status === 'online' ? 'success' : 'neutral'}>{row.status === 'revoked' ? 'Revoked' : row.connection_status}</Badge></Inline><span className="ui-card-description">{row.name} · {row.os_name} · v{row.agent_version ?? '—'}</span></Stack>}/>}

    <Modal open={enrollOpen} onClose={() => !enrollSaving && setEnrollOpen(false)} title="Enroll desktop agent" description="Generate one employee enrollment code. It may be used once by the desktop agent and once by the Browser Tracker before expiry." footer={<><Button variant="outline" disabled={enrollSaving} onClick={() => setEnrollOpen(false)}>Close</Button>{!enrollment && <Button variant="primary" loading={enrollSaving} form="device-enroll-submit" type="submit">Generate Code</Button>}</>}>
      {!enrollment ? <Form id="device-enroll-submit" onSubmit={createEnrollment} gap={12}>
        <Field label="Employee"><Select value={memberId} onChange={event => setMemberId(event.target.value)} required><Option value="">Select employee</Option>{data?.members.map(member => <Option key={member.id} value={member.id}>{member.name}{member.job_title ? ` · ${member.job_title}` : ''}</Option>)}</Select></Field>
        <Field label="Code expires in"><Select value={expiresMinutes} onChange={event => setExpiresMinutes(event.target.value)}><Option value="5">5 minutes</Option><Option value="10">10 minutes</Option><Option value="15">15 minutes</Option><Option value="30">30 minutes</Option><Option value="60">60 minutes</Option></Select></Field>
        <Alert tone="info">Enrollment links a physical installation to one workspace member. Re-enrollment rotates the device token.</Alert>
        
      </Form> : <Stack gap={14}>
        <Alert tone="success">Enrollment code created. It is shown only in this dialog.</Alert>
        <Box p={18} border="1px solid var(--border)" radius={10} bg="var(--elevated)" textAlign="center"><div className="ui-card-description">Unified enrollment code · Desktop Agent + Browser Tracker</div><Box className="stat-num" size={25} letterSpacing={2} weight={750} m="8px 0">{enrollment.enrollment_code}</Box><Button variant="outline" size="sm" onClick={() => void copyEnrollment()}><Copy size={14}/> Copy code</Button></Box>
        <Box size={12} color="var(--text-2)" lineHeight={1.7}><Text as="strong" color="var(--text)">Desktop Agent endpoint</Text><br /><code>{enrollment.enrollment_endpoint}</code><br />{enrollment.browser_enrollment_endpoint && <><Text as="strong" color="var(--text)">Browser Tracker endpoint</Text><br /><code>{enrollment.browser_enrollment_endpoint}</code><br /></>}<Text as="strong" color="var(--text)">Expires</Text><br />{new Date(enrollment.expires_at).toLocaleString()}</Box>
      </Stack>}
    </Modal>

    <Modal open={Boolean(renameDevice)} onClose={() => actionId === null && setRenameDevice(null)} title="Rename device" footer={<><Button variant="outline" disabled={actionId !== null} onClick={() => setRenameDevice(null)}>Cancel</Button><Button variant="primary" loading={actionId !== null} form="device-rename-submit" type="submit">Save</Button></>}>
      <Form id="device-rename-submit" onSubmit={saveRename}><Field label="Device name"><Input value={renameValue} onChange={event => setRenameValue(event.target.value)} required/></Field></Form>
    </Modal>

    <Drawer open={selectedId !== null} onClose={() => setSelectedId(null)} title={detail?.device.name ?? 'Device details'} description={detail ? `${detail.device.employee} · ${detail.device.os_name}` : 'Loading device activity…'}>
      {detailLoading || !detail ? <PageLoadingState /> : <Stack gap={16}>
        <Grid columns="repeat(2,minmax(0,1fr))" gap={8}>
          {[
                ['Connection', detail.device.connection_status], ['Tracking', detail.device.tracking_status], ['Agent', `v${detail.device.agent_version ?? '—'}`], ['Health', detail.device.health],
                ['Heartbeat', relativeTime(detail.device.last_heartbeat_at)], ['Last sync', relativeTime(detail.device.last_sync_at)], ['Offline queue', String(detail.device.offline_queue_size)], ['IP', detail.device.last_ip ?? '—'],
            ].map(([label, value]) => <Box key={label} p={10} border="1px solid var(--border-muted)" radius={8}><div className="ui-card-description">{label}</div><Box mt={4} weight={600}>{value}</Box></Box>)}
        </Grid>

        <section><Box as="h3" className="ui-card-title" mb={8}>Recent agent events</Box>{detail.events.length === 0 ? <div className="ui-card-description">No offline events synced yet.</div> : <Stack gap={6}>{detail.events.slice(0, 12).map(event => <Box key={event.id} display="flex" gap={9} align="center" p="8px 0" borderBottom="1px solid var(--border-muted)"><Activity size={13}/><Box flex={1}><Box size={12} weight={600}>{event.event_type}</Box><div className="ui-card-description">{new Date(event.occurred_at).toLocaleString()}</div></Box></Box>)}</Stack>}</section>

        <section><Box as="h3" className="ui-card-title" mb={8}>Offline sync batches</Box>{detail.sync_batches.length === 0 ? <div className="ui-card-description">No batches uploaded yet.</div> : <Stack gap={6}>{detail.sync_batches.map(batch => <Box key={batch.batch_uuid} p={10} border="1px solid var(--border-muted)" radius={8}><Inline justify="space-between" gap={8}><Text size={12} weight={600}>{batch.accepted_count}/{batch.event_count} accepted</Text><span className="ui-card-description">{relativeTime(batch.received_at)}</span></Inline>{batch.duplicate_count > 0 && <div className="ui-card-description">{batch.duplicate_count} duplicate events ignored</div>}</Box>)}</Stack>}</section>

        <section><Box as="h3" className="ui-card-title" mb={8}>Command history</Box>{detail.commands.length === 0 ? <div className="ui-card-description">No remote commands queued.</div> : <Stack gap={6}>{detail.commands.map(command => <Box key={command.uuid} display="flex" align="center" gap={8} p="8px 0" borderBottom="1px solid var(--border-muted)"><TerminalSquare size={13}/><Box flex={1}><Box size={12} weight={600}>{commandLabel(command.command_type)}</Box><div className="ui-card-description">{command.created_at ? relativeTime(command.created_at) : '—'}</div></Box><Badge tone={command.status === 'acknowledged' ? 'success' : command.status === 'failed' ? 'danger' : command.status === 'delivered' ? 'info' : 'neutral'}>{command.status}</Badge></Box>)}</Stack>}</section>
      </Stack>}
    </Drawer>
  </Page>;
}
