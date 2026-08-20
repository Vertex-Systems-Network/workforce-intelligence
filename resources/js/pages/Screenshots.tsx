import { FormEvent, useEffect, useMemo, useState } from 'react';
import { CalendarDays, Flag, Image as ImageIcon, RefreshCw, ShieldCheck, StickyNote, Trash2, ZoomIn } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { PageLoadingState } from '../components/LoadingStates';
import { useConfirmAction, Alert, Badge, Button, Card, CardBody, Drawer, EmptyState, Field, Input, Modal, Page, PageHeader, Select, Textarea, Pressable, Image, Box, Grid, Inline, Stack, Text, Form, Option } from '../design-system';
type ScreenshotItem = {
    id: number;
    uuid: string;
    member_id: number;
    employee: string;
    device: string | null;
    project: string | null;
    task: string | null;
    app_name: string | null;
    activity_percent: number | null;
    monitor_index: number;
    blurred: boolean;
    flagged: boolean;
    flag_reason: string | null;
    note: string | null;
    captured_at: string;
    image_url: string;
    size_bytes: number;
    width: number | null;
    height: number | null;
    storage_status?: string;
    storage_provider?: string | null;
    storage_verified_at?: string | null;
    storage_error?: string | null;
};
type ScreenshotResponse = {
    date: string;
    settings: {
        enabled: boolean;
        interval_minutes: number;
        retention_days: number;
        blur_by_default: boolean;
    };
    can_manage: boolean;
    screenshots: ScreenshotItem[];
    members: Array<{
        id: number;
        name: string;
    }>;
    projects: Array<{
        id: number;
        name: string;
    }>;
};
/** Handles the local date operation for the WorkIntel client. */ function localDate() {
    const now = new Date();
    const offset = now.getTimezoneOffset();
    return new Date(now.getTime() - offset * 60000).toISOString().slice(0, 10);
}
/** Handles the time label operation for the WorkIntel client. */ function timeLabel(value: string) { return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
/** Handles the hour bucket operation for the WorkIntel client. */ function hourBucket(value: string) { const d = new Date(value); const h = d.getHours(); return `${String(h).padStart(2, '0')}:00–${String((h + 1) % 24).padStart(2, '0')}:00`; }
/** Handles the screenshots operation for the WorkIntel client. */ export default function Screenshots() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [data, setData] = useState<ScreenshotResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [date, setDate] = useState(localDate());
    const [memberId, setMemberId] = useState('');
    const [projectId, setProjectId] = useState('');
    const [selected, setSelected] = useState<ScreenshotItem | null>(null);
    const [editOpen, setEditOpen] = useState(false);
    const [note, setNote] = useState('');
    const [flagReason, setFlagReason] = useState('');
    const [saving, setSaving] = useState(false);
    /** Loads load data required by the current view. */ const load = async (silent = false) => {
        if (!workspaceId)
            return;
        if (!silent)
            setLoading(true);
        setError('');
        try {
            const q = new URLSearchParams({ date });
            if (memberId)
                q.set('member_id', memberId);
            if (projectId)
                q.set('project_id', projectId);
            setData(await apiRequest<ScreenshotResponse>(`/api/v1/screenshots?${q}`, { workspaceId, silent }));
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not load screenshots.');
        }
        finally {
            if (!silent)
                setLoading(false);
        }
    };
    useEffect(() => { void load(); }, [workspaceId, date, memberId, projectId]);
    const groups = useMemo(() => {
        const map = new Map<string, ScreenshotItem[]>();
        for (const item of data?.screenshots ?? []) {
            const key = hourBucket(item.captured_at);
            map.set(key, [...(map.get(key) ?? []), item]);
        }
        return [...map.entries()];
    }, [data]);
    /** Handles the open edit operation for the WorkIntel client. */ const openEdit = (item: ScreenshotItem) => { setSelected(item); setNote(item.note ?? ''); setFlagReason(item.flag_reason ?? ''); setEditOpen(true); };
    /** Handles the save meta operation for the WorkIntel client. */ const saveMeta = async (event: FormEvent) => {
        event.preventDefault();
        if (!workspaceId || !selected)
            return;
        setSaving(true);
        try {
            await apiRequest(`/api/v1/screenshots/${selected.id}`, { method: 'PUT', workspaceId, body: JSON.stringify({ flagged: Boolean(flagReason.trim()) || selected.flagged, flag_reason: flagReason.trim() || null, note: note.trim() || null }) });
            setEditOpen(false);
            await load(true);
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not update screenshot.');
        }
        finally {
            setSaving(false);
        }
    };
    /** Handles the toggle flag operation for the WorkIntel client. */ const toggleFlag = async (item: ScreenshotItem) => {
        if (!workspaceId)
            return;
        try {
            await apiRequest(`/api/v1/screenshots/${item.id}`, { method: 'PUT', workspaceId, body: JSON.stringify({ flagged: !item.flagged, flag_reason: !item.flagged ? (item.flag_reason || 'Flagged for review') : null }) });
            await load(true);
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not update flag.');
        }
    };
    /** Handles the remove operation for the WorkIntel client. */ const remove = async (item: ScreenshotItem) => {
        if (!workspaceId || !await confirmAction({ title: 'Delete screenshot?', description: 'Delete this screenshot file? The image cannot be restored.', confirmLabel: 'Delete', danger: true }))
            return;
        try {
            await apiRequest(`/api/v1/screenshots/${item.id}`, { method: 'DELETE', workspaceId });
            if (selected?.id === item.id)
                setSelected(null);
            await load(true);
        }
        catch (e) {
            setError(e instanceof Error ? e.message : 'Could not delete screenshot.');
        }
    };
    if (loading && !data)
        return <PageLoadingState />;
    return <Page>
    <PageHeader title="Screenshots" description="Consent-aware desktop captures with private storage, review controls and retention policies" actions={<Button variant="outline" size="sm" loading={loading} onClick={() => void load()}><RefreshCw size={14}/> Refresh</Button>}/>
    {error && <Alert tone="danger">{error}</Alert>}
    <Box display="flex" align="center" gap={8} p="10px 14px" bg="var(--surface)" border="1px solid var(--border)" radius="var(--radius-lg)" m="14px 0" size={12} color="var(--text-3)"><ShieldCheck size={14}/>Screenshot capture is controlled by workspace policy. Files are private and only permission-scoped users can view them.<Text ml="auto"><Badge tone={data?.settings.enabled ? 'success' : 'neutral'}>{data?.settings.enabled ? `Every ${data.settings.interval_minutes} min` : 'Capture disabled'}</Badge></Text></Box>
    <Card mb={14}><CardBody><Grid columns="minmax(180px,1fr) minmax(180px,1fr) minmax(180px,1fr) auto" gap={10} align="end">
      <Field label="Date"><Input type="date" value={date} onChange={e => setDate(e.target.value)}/></Field>
      <Field label="Employee"><Select value={memberId} onChange={e => setMemberId(e.target.value)}><Option value="">All employees</Option>{data?.members.map(m => <Option key={m.id} value={m.id}>{m.name}</Option>)}</Select></Field>
      <Field label="Project"><Select value={projectId} onChange={e => setProjectId(e.target.value)}><Option value="">All projects</Option>{data?.projects.map(p => <Option key={p.id} value={p.id}>{p.name}</Option>)}</Select></Field>
      <Button variant="ghost" onClick={() => { setDate(localDate()); setMemberId(''); setProjectId(''); }}><CalendarDays size={14}/> Today</Button>
    </Grid></CardBody></Card>

    {!data?.screenshots.length ? <Card><CardBody><EmptyState icon={<ImageIcon size={24}/>} title="No screenshots for this filter" text={data?.settings.enabled ? 'Screenshots will appear after enrolled desktop agents upload captures.' : 'Enable screenshot capture in Settings → Screenshots to begin collecting captures.'}/></CardBody></Card> :
            groups.map(([hour, items]) => <Box key={hour} mb={22}><Box size={12} weight={600} color="var(--text-3)" mb={9} display="flex" gap={8} align="center"><span className="stat-num">{hour}</span><Box height={1} bg="var(--border-muted)" flex={1}/><Text weight={400}>{items.length} capture{items.length === 1 ? '' : 's'}</Text></Box><Grid columns="repeat(auto-fill,minmax(240px,1fr))" gap={12}>{items.map(item => <Card key={item.id} interactive overflow="hidden">
        <Pressable type="button" onClick={() => setSelected(item)} display="block" p={0} border={0} width="100%" bg="var(--bg)" cursor="zoom-in"><Image src={item.image_url} alt={`${item.employee} screenshot at ${timeLabel(item.captured_at)}`} width="100%" aspectRatio="16/9" objectFit="cover" display="block" filter={item.blurred ? 'blur(7px)' : 'none'}/></Pressable>
        <CardBody p={11}><Inline align="center" gap={7}><Box weight={600} size={12} flex={1} overflow="hidden" textOverflow="ellipsis" whiteSpace="nowrap">{item.employee}</Box><span className="stat-num ui-card-description">{timeLabel(item.captured_at)}</span>{item.flagged && <Flag size={13} color="var(--warning)" fill="currentColor"/>}</Inline><Box className="ui-card-description" mt={5} whiteSpace="nowrap" overflow="hidden" textOverflow="ellipsis">{item.project || 'No project'}{item.task ? ` · ${item.task}` : ''}</Box><Box display="flex" justify="space-between" mt={7} size={11}><span>{item.app_name || item.device || 'Desktop'}</span><Box as="span" color={(item.activity_percent ?? 0) >= 70 ? 'var(--success)' : 'var(--warning)'} weight={650}>{item.activity_percent === null ? '—' : `${item.activity_percent}%`}</Box></Box><Inline gap={5} mt={6} align="center"><Badge tone={item.storage_status === 'failed' ? 'danger' : item.storage_status === 'remote' ? 'success' : item.storage_status === 'queued' || item.storage_status === 'syncing' ? 'warning' : 'neutral'}>{item.storage_status ?? 'local'}</Badge>{item.storage_provider && <span className="ui-card-description">{item.storage_provider}</span>}</Inline><Inline gap={5} mt={8}><Button variant="ghost" size="sm" onClick={() => setSelected(item)}><ZoomIn size={13}/> View</Button>{data.can_manage && <><Button variant="ghost" size="sm" onClick={() => void toggleFlag(item)}><Flag size={13}/> {item.flagged ? 'Unflag' : 'Flag'}</Button><Button variant="ghost" size="sm" onClick={() => openEdit(item)}><StickyNote size={13}/> Note</Button><Button variant="ghost" size="sm" onClick={() => void remove(item)}><Trash2 size={13}/></Button></>}</Inline></CardBody>
      </Card>)}</Grid></Box>)}

    <Drawer open={Boolean(selected)} onClose={() => setSelected(null)} title={selected?.employee || 'Screenshot'} description={selected ? new Date(selected.captured_at).toLocaleString() : undefined} footer={selected && data?.can_manage ? <Inline gap={8}><Button variant="outline" onClick={() => openEdit(selected)}><StickyNote size={14}/> Note</Button><Button variant="outline" onClick={() => void toggleFlag(selected)}><Flag size={14}/> {selected.flagged ? 'Unflag' : 'Flag'}</Button><Button variant="danger" onClick={() => void remove(selected)}><Trash2 size={14}/> Delete</Button></Inline> : undefined}>
      {selected && <Stack gap={13}><Image src={selected.image_url} alt="Screenshot preview" width="100%" radius={8} border="1px solid var(--border)" filter={selected.blurred ? 'blur(8px)' : 'none'}/><Grid columns="1fr 1fr" gap={10}>{[['Project', selected.project || '—'], ['Task', selected.task || '—'], ['Application', selected.app_name || '—'], ['Monitor', String(selected.monitor_index)], ['Activity', selected.activity_percent === null ? '—' : `${selected.activity_percent}%`], ['Device', selected.device || '—'], ['Storage', `${selected.storage_status ?? 'local'}${selected.storage_provider ? ` · ${selected.storage_provider}` : ''}`]].map(([k, v]) => <Box key={k} p={10} border="1px solid var(--border-muted)" radius={7}><div className="ui-card-description">{k}</div><Box mt={3} weight={550}>{v}</Box></Box>)}</Grid>{selected.storage_error && <Alert tone="warning"><strong>Storage:</strong> {selected.storage_error}</Alert>}{selected.note && <Alert tone="info"><strong>Note:</strong> {selected.note}</Alert>}{selected.flagged && <Alert tone="warning"><strong>Flagged:</strong> {selected.flag_reason || 'Review required'}</Alert>}</Stack>}
    </Drawer>

    <Modal open={editOpen} onClose={() => setEditOpen(false)} title="Screenshot review note" description="Add a private manager note or flag reason." footer={<><Button variant="outline" onClick={() => setEditOpen(false)}>Cancel</Button><Button variant="primary" loading={saving} onClick={() => document.getElementById('screenshot-note-form')?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))}>Save</Button></>}>
      <Form id="screenshot-note-form" onSubmit={saveMeta} gap={12}><Field label="Flag reason"><Input value={flagReason} onChange={e => setFlagReason(e.target.value)} placeholder="Optional review reason"/></Field><Field label="Private note"><Textarea rows={5} value={note} onChange={e => setNote(e.target.value)} placeholder="Visible to authorized managers only"/></Field></Form>
    </Modal>
  </Page>;
}
