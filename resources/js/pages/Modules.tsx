import { useEffect, useMemo, useState } from 'react';
import { Boxes, History, LockKeyhole, RotateCcw, Search, Settings2, ShieldCheck } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { useConfirmAction, Alert, Badge, Button, Card, CardBody, CardHeader, DataGrid, EmptyState, LoadingState, Field, Input, Modal, Page, PageHeader, SearchInput, Switch, SettingRow, Tabs, Box, Grid, Inline, Stack, type DataGridColumn } from '../design-system';
type ModuleRow = {
    key: string;
    label: string;
    description: string;
    category: string;
    dependencies: string[];
    dependents: string[];
    entitlement: string | null;
    plan_available: boolean;
    workspace_enabled: boolean;
    enabled: boolean;
    navigation_visible: boolean;
    background_processing: boolean;
    label_override: string | null;
    settings: Record<string, unknown>;
    page: string | null;
};
type EventRow = {
    id: number;
    module_key: string;
    action: string;
    before_state: Record<string, unknown> | null;
    after_state: Record<string, unknown> | null;
    created_at: string;
    actor?: {
        user?: {
            first_name: string;
            last_name: string;
            email: string;
        };
    };
};
/** Handles the modules operation for the WorkIntel client. */ export default function Modules() {
    const confirmAction = useConfirmAction();
    const { session, refreshSession } = useAuth();
    const workspace = session?.user.workspaces.find(w => w.id === session.user.activeWorkspaceId) ?? session?.user.workspaces[0];
    const workspaceId = workspace?.id;
    const [rows, setRows] = useState<ModuleRow[]>([]);
    const [history, setHistory] = useState<EventRow[]>([]);
    const [canManage, setCanManage] = useState(false);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState('');
    const [message, setMessage] = useState('');
    const [query, setQuery] = useState('');
    const [tab, setTab] = useState<'modules' | 'history'>('modules');
    const [editing, setEditing] = useState<ModuleRow | null>(null);
    /** Loads load data required by the current view. */ const load = async () => {
        if (!workspaceId)
            return;
        setLoading(true);
        setMessage('');
        try {
            const payload = await apiRequest<{
                data: ModuleRow[];
                can_manage: boolean;
            }>('/api/v1/modules', { workspaceId });
            setRows(payload.data);
            setCanManage(payload.can_manage);
            if (tab === 'history')
                await loadHistory();
        }
        catch (err) {
            setMessage(err instanceof Error ? err.message : 'Could not load modules.');
        }
        finally {
            setLoading(false);
        }
    };
    /** Loads load history data required by the current view. */ const loadHistory = async () => {
        if (!workspaceId)
            return;
        const p = await apiRequest<{
            data: EventRow[];
        }>('/api/v1/modules/history', { workspaceId });
        setHistory(p.data);
    };
    useEffect(() => { void load(); }, [workspaceId]);
    useEffect(() => {
        if (tab === 'history')
            void loadHistory();
    }, [tab, workspaceId]);
    const filtered = useMemo(() => rows.filter(row => `${row.label} ${row.description} ${row.category}`.toLowerCase().includes(query.toLowerCase())), [rows, query]);
    const groups = useMemo(() => {
        const m = new Map<string, ModuleRow[]>();
        for (const row of filtered) {
            const list = m.get(row.category) ?? [];
            list.push(row);
            m.set(row.category, list);
        }
        return [...m.entries()];
    }, [filtered]);
    /** Handles the name operation for the WorkIntel client. */ const name = (key: string) => rows.find(r => r.key === key)?.label ?? key;
    const historyColumns = useMemo<DataGridColumn<EventRow>[]>(() => [
        { id: 'time', header: 'Time', cell: row => new Date(row.created_at).toLocaleString(), sortValue: row => new Date(row.created_at), filterValue: row => row.created_at, filter: { type: 'dateRange', label: 'Changed' } },
        { id: 'module', header: 'Module', cell: row => name(row.module_key), searchValue: row => name(row.module_key), sortable: true },
        { id: 'action', header: 'Action', cell: row => <Badge>{row.action.replaceAll('_', ' ')}</Badge>, searchValue: row => row.action, sortable: true },
        { id: 'actor', header: 'Actor', cell: row => row.actor?.user ? `${row.actor.user.first_name} ${row.actor.user.last_name}` : 'System', searchValue: row => row.actor?.user ? `${row.actor.user.first_name} ${row.actor.user.last_name} ${row.actor.user.email}` : 'System', sortable: true },
    ], [rows]);
    /** Updates update state for the current workflow. */ const update = async (row: ModuleRow, patch: Record<string, unknown>, cascade = false) => {
        if (!workspaceId)
            return;
        setBusy(row.key);
        setMessage('');
        try {
            const p = await apiRequest<{
                message: string;
                modules: ModuleRow[];
            }>(`/api/v1/modules/${row.key}`, { method: 'PATCH', workspaceId, body: JSON.stringify({ ...patch, cascade_dependents: cascade, enable_dependencies: true }) });
            setRows(p.modules);
            setMessage(p.message);
            setEditing(null);
            await refreshSession();
            window.dispatchEvent(new CustomEvent('workintel:modules-changed'));
        }
        catch (err) {
            setMessage(err instanceof Error ? err.message : 'Could not update module.');
        }
        finally {
            setBusy('');
        }
    };
    /** Handles the toggle operation for the WorkIntel client. */ const toggle = async (row: ModuleRow, next: boolean) => {
        if (!canManage)
            return;
        if (next && !row.plan_available) {
            setMessage(`${row.label} is not included in the current plan.`);
            return;
        }
        if (!next && row.dependents.some(key => rows.find(r => r.key === key)?.workspace_enabled)) {
            const deps = row.dependents.filter(key => rows.find(r => r.key === key)?.workspace_enabled).map(name);
            if (!await confirmAction({ title: `Disable ${row.label}?`, description: `This also requires disabling: ${deps.join(', ')}.`, confirmLabel: 'Disable', danger: true }))
                return;
            await update(row, { is_enabled: false }, true);
            return;
        }
        await update(row, { is_enabled: next });
    };
    /** Handles the reset operation for the WorkIntel client. */ const reset = async () => {
        if (!workspaceId || !canManage || !await confirmAction({ title: 'Reset module settings?', description: 'Reset all module switches, labels and runtime settings to product defaults. Workspace data will not be deleted.', confirmLabel: 'Reset' }))
            return;
        setBusy('reset');
        setMessage('');
        try {
            const p = await apiRequest<{
                message: string;
                data: ModuleRow[];
            }>('/api/v1/modules/reset-defaults', { method: 'POST', workspaceId, body: JSON.stringify({ confirm: 'RESET_MODULES' }) });
            setRows(p.data);
            setMessage(p.message);
            await refreshSession();
            window.dispatchEvent(new CustomEvent('workintel:modules-changed'));
        }
        catch (err) {
            setMessage(err instanceof Error ? err.message : 'Could not reset modules.');
        }
        finally {
            setBusy('');
        }
    };
    if (!workspace)
        return null;
    return <Page>
    <PageHeader title="Apps / Modules" description="Owner-level workspace module control. Disabling a module hides navigation, blocks module APIs and pauses module background processing without deleting its data." actions={<Inline gap={8}>{canManage && <Button variant="outline" loading={busy === 'reset'} onClick={() => void reset()}><RotateCcw size={14}/> Reset defaults</Button>}</Inline>}/>
    {message && <Alert tone={message.toLowerCase().includes('saved') || message.toLowerCase().includes('reset') ? 'success' : 'warning'}>{message}</Alert>}
    <Inline justify="space-between" align="center" gap={12} m="14px 0">
      <Tabs value={tab} tabs={[{ value: 'modules', label: 'Modules' }, { value: 'history', label: 'Audit history' }]} onChange={setTab}/>
      {tab === 'modules' && <Box width={280}><SearchInput icon={<Search size={14}/>} value={query} onChange={e => setQuery(e.target.value)} placeholder="Search modules…"/></Box>}
    </Inline>

    {tab === 'modules' ? <>
      <Alert tone="info"><strong>Data-safe switching:</strong> OFF means unavailable, not deleted. Re-enable later and the module continues with its existing records. Dependencies are validated before a module can be enabled or disabled.</Alert>
      {!canManage && <Alert tone="warning" mt={10}><ShieldCheck size={14}/> You can review module state, but only the workspace Owner can enable or disable modules.</Alert>}
      {loading ? <LoadingState title="Loading workspace modules…" text="Checking plan availability, dependencies and workspace overrides."/> : <Stack gap={22} mt={18}>{groups.map(([category, items]) => <section key={category}><Box className="ui-sidebar__section-label" mb={9}>{category}</Box><Grid columns="repeat(auto-fill,minmax(310px,1fr))" gap={10}>{items.map(row => <Card key={row.key} opacity={row.plan_available ? 1 : .72}><CardHeader title={<Inline align="center" gap={8}><span>{row.label_override || row.label}</span>{!row.plan_available && <Badge tone="warning"><LockKeyhole size={11}/> Plan locked</Badge>}{row.workspace_enabled && row.plan_available ? <Badge tone="success">ON</Badge> : <Badge>OFF</Badge>}</Inline>} description={row.description} action={<Switch checked={row.workspace_enabled} disabled={!canManage || busy === row.key || (!row.plan_available && !row.workspace_enabled)} onChange={next => void toggle(row, next)} label={`Toggle ${row.label}`}/>}/><CardBody><Box display="grid" gap={10} size={12}><Inline justify="space-between"><span className="ui-card-description">Navigation</span><Badge tone={row.navigation_visible ? 'info' : 'neutral'}>{row.navigation_visible ? 'Visible' : 'Hidden'}</Badge></Inline><Inline justify="space-between"><span className="ui-card-description">Background processing</span><Badge tone={row.background_processing ? 'info' : 'neutral'}>{row.background_processing ? 'Enabled' : 'Paused'}</Badge></Inline>{row.dependencies.length > 0 && <div><Box className="ui-card-description" mb={5}>Depends on</Box><Inline gap={5} wrap="wrap">{row.dependencies.map(key => <Badge key={key}>{name(key)}</Badge>)}</Inline></div>}{row.entitlement && <div className="ui-card-description">Plan feature: <code>{row.entitlement}</code></div>}<Button size="sm" variant="ghost" disabled={!canManage || busy === row.key} onClick={() => setEditing(row)}><Settings2 size={13}/> Runtime & navigation settings</Button></Box></CardBody></Card>)}</Grid></section>)}</Stack>}
    </> : <Card><CardHeader title="Module audit history" description="Enable, disable, dependency and settings changes are retained for workspace auditability."/><CardBody p={0}><DataGrid rows={history} columns={historyColumns} rowKey={row => row.id} persistKey="module-audit-history" defaultSort={{ id: 'time', direction: 'desc' }} searchPlaceholder="Search module changes…" empty={<EmptyState title="No module changes recorded yet" text="Enable, disable, reset or reconfigure a module and its audit entry will appear here."/>} mobileCard={row => <Stack gap={5}><Inline justify="space-between"><strong>{name(row.module_key)}</strong><Badge>{row.action.replaceAll('_', ' ')}</Badge></Inline><span className="ui-card-description">{new Date(row.created_at).toLocaleString()} · {row.actor?.user ? `${row.actor.user.first_name} ${row.actor.user.last_name}` : 'System'}</span></Stack>}/></CardBody></Card>}

    <Modal open={Boolean(editing)} onClose={() => !busy && setEditing(null)} title={editing ? `${editing.label} settings` : 'Module settings'} description="These settings control module navigation/runtime behavior. Domain-specific configuration remains inside the module itself." footer={editing ? <><Button variant="outline" onClick={() => setEditing(null)}>Cancel</Button><Button loading={busy === editing.key} onClick={() => void update(editing, { navigation_visible: editing.navigation_visible, background_processing: editing.background_processing, label_override: editing.label_override })}>Save settings</Button></> : undefined}>
      {editing && <Stack gap={14}><Field label="Custom navigation label" hint="Leave blank to use the product module name."><Input value={editing.label_override ?? ''} onChange={e => setEditing({ ...editing, label_override: e.target.value || null })} maxLength={80}/></Field><SettingRow title="Show in navigation" description="The module remains accessible by permission/API while enabled." control={<Switch checked={editing.navigation_visible} onChange={value => setEditing({ ...editing, navigation_visible: value })} label="Show in navigation"/>}/><SettingRow title="Background processing" description="Pause scheduled processing without turning the whole module off." control={<Switch checked={editing.background_processing} onChange={value => setEditing({ ...editing, background_processing: value })} label="Background processing"/>}/></Stack>}
    </Modal>
  </Page>;
}
