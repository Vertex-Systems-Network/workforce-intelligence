import { FormEvent, useEffect, useMemo, useState } from 'react';
import { AppWindow, Ban, CheckCircle2, Clock3, Copy, Ellipsis, ExternalLink, Globe2, Link2, Plus, RefreshCw, Search, Settings2, ShieldCheck, SlidersHorizontal, Tag, Trash2, Users, } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { PageLoadingState } from '../components/LoadingStates';
import { useConfirmAction, Alert, Badge, Button, Card, CardBody, CardHeader, Drawer, Dropdown, EmptyState, Field, Input, Modal, Page, PageHeader, SearchInput, Select, Segmented, StatCard, Switch, DataGrid, Tabs, Pressable, Box, Grid, Inline, Stack, Form, Option, type DataGridColumn } from '../design-system';
type Classification = 'productive' | 'neutral' | 'unproductive' | 'unclassified';
type UsageRow = {
    type: 'app' | 'domain';
    name: string;
    target: string;
    category: string;
    seconds: number;
    active_seconds: number;
    share: number;
    people: number;
    classification: Classification;
};
type Rule = {
    id: number;
    scope_type: 'workspace' | 'department' | 'team' | 'member' | 'project';
    scope_id: number | null;
    target_type: 'app' | 'domain';
    target: string;
    classification: Classification;
    category: string | null;
    active: boolean;
};
type Exclusion = {
    id: number;
    scope_type: 'workspace' | 'department' | 'team' | 'member';
    scope_id: number | null;
    target_type: 'app' | 'domain';
    pattern: string;
    reason: string | null;
    active: boolean;
};
type Settings = {
    application_tracking_enabled: boolean;
    website_tracking_enabled: boolean;
    capture_window_titles: boolean;
    capture_page_titles: boolean;
    store_full_urls: boolean;
    minimum_session_seconds: number;
    idle_threshold_seconds: number;
};
type BrowserConnection = {
    id: number;
    uuid: string;
    employee: string;
    browser_name: string;
    browser_version: string | null;
    extension_version: string;
    status: string;
    last_seen_at: string | null;
    last_sync_at: string | null;
    revoked_at: string | null;
};
type Member = {
    id: number;
    name: string;
};
type ScopeOption = {
    id: number;
    name: string;
};
type Payload = {
    range: {
        from: string;
        to: string;
    };
    stats: {
        tracked_seconds: number;
        productive_percent: number;
        applications: number;
        domains: number;
    };
    usage: UsageRow[];
    rules: Rule[];
    exclusions: Exclusion[];
    settings: Settings;
    browser_connections: BrowserConnection[];
    members: Member[];
    scope_options: {
        departments: ScopeOption[];
        teams: ScopeOption[];
        projects: ScopeOption[];
    };
};
type Enrollment = {
    enrollment_code: string;
    expires_at: string;
    member_id: number;
    enrollment_endpoint: string;
};
type SessionDetail = {
    id: number;
    type: 'app' | 'domain';
    name: string;
    employee: string;
    device?: string | null;
    browser?: string | null;
    project?: string | null;
    task?: string | null;
    started_at: string | null;
    ended_at: string | null;
    duration_seconds: number;
    active_seconds: number;
    idle_seconds: number;
    source: string;
    window_title?: string | null;
    page_title?: string | null;
};
type Section = 'usage' | 'rules' | 'exclusions' | 'browsers' | 'settings';
type RuleForm = {
    scope_type: Rule['scope_type'];
    scope_id: string;
    target_type: Rule['target_type'];
    target: string;
    classification: Classification;
    category: string;
};
type ExclusionForm = {
    scope_type: Exclusion['scope_type'];
    scope_id: string;
    target_type: Exclusion['target_type'];
    pattern: string;
    reason: string;
};
const emptyRule: RuleForm = { scope_type: 'workspace', scope_id: '', target_type: 'app', target: '', classification: 'productive', category: '' };
const emptyExclusion: ExclusionForm = { scope_type: 'workspace', scope_id: '', target_type: 'domain', pattern: '', reason: '' };
/** Formats format duration data for display. */ function formatDuration(seconds: number) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0)
        return `${hours}h ${minutes}m`;
    if (minutes > 0)
        return `${minutes}m`;
    return `${seconds}s`;
}
/** Handles the relative time operation for the WorkIntel client. */ function relativeTime(value: string | null) {
    if (!value)
        return 'Never';
    const ms = Date.now() - new Date(value).getTime();
    if (Number.isNaN(ms))
        return 'Unknown';
    const minutes = Math.max(0, Math.floor(ms / 60000));
    if (minutes < 1)
        return 'Now';
    if (minutes < 60)
        return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24)
        return `${hours}h ago`;
    return `${Math.floor(hours / 24)}d ago`;
}
/** Handles the classification tone operation for the WorkIntel client. */ function classificationTone(value: Classification): 'success' | 'warning' | 'danger' | 'neutral' {
    if (value === 'productive')
        return 'success';
    if (value === 'neutral')
        return 'warning';
    if (value === 'unproductive')
        return 'danger';
    return 'neutral';
}
/** Handles the scope label operation for the WorkIntel client. */ function scopeLabel(rule: {
    scope_type: string;
    scope_id: number | null;
}, members: Member[], options?: Payload['scope_options']) {
    if (rule.scope_type === 'workspace')
        return 'Workspace';
    if (rule.scope_type === 'member')
        return members.find(member => member.id === rule.scope_id)?.name ?? `Member #${rule.scope_id}`;
    const list = rule.scope_type === 'department' ? options?.departments : rule.scope_type === 'team' ? options?.teams : rule.scope_type === 'project' ? options?.projects : undefined;
    return list?.find(item => item.id === rule.scope_id)?.name ?? `${rule.scope_type.charAt(0).toUpperCase()}${rule.scope_type.slice(1)} #${rule.scope_id}`;
}
/** Handles the apps sites operation for the WorkIntel client. */ export default function AppsSites() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const activeWorkspace = session?.user.workspaces.find(workspace => workspace.id === workspaceId);
    const canManage = activeWorkspace?.role === 'owner' || activeWorkspace?.role === 'admin';
    const [data, setData] = useState<Payload | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [section, setSection] = useState<Section>('usage');
    const [view, setView] = useState<'all' | 'apps' | 'websites'>('all');
    const [query, setQuery] = useState('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [ruleOpen, setRuleOpen] = useState(false);
    const [editingRule, setEditingRule] = useState<Rule | null>(null);
    const [ruleForm, setRuleForm] = useState<RuleForm>(emptyRule);
    const [ruleSaving, setRuleSaving] = useState(false);
    const [exclusionOpen, setExclusionOpen] = useState(false);
    const [editingExclusion, setEditingExclusion] = useState<Exclusion | null>(null);
    const [exclusionForm, setExclusionForm] = useState<ExclusionForm>(emptyExclusion);
    const [exclusionSaving, setExclusionSaving] = useState(false);
    const [browserOpen, setBrowserOpen] = useState(false);
    const [browserMemberId, setBrowserMemberId] = useState('');
    const [browserExpires, setBrowserExpires] = useState('10');
    const [browserEnrollment, setBrowserEnrollment] = useState<Enrollment | null>(null);
    const [browserSaving, setBrowserSaving] = useState(false);
    const [actionId, setActionId] = useState<number | null>(null);
    const [settingsSaving, setSettingsSaving] = useState(false);
    const [settingsDraft, setSettingsDraft] = useState<Settings | null>(null);
    const [detailRow, setDetailRow] = useState<UsageRow | null>(null);
    const [detailSessions, setDetailSessions] = useState<SessionDetail[]>([]);
    const [detailLoading, setDetailLoading] = useState(false);
    /** Loads load data required by the current view. */ const load = async (silent = false, nextFrom = from, nextTo = to) => {
        if (!workspaceId)
            return;
        if (!silent)
            setLoading(true);
        setError('');
        try {
            const params = new URLSearchParams();
            if (nextFrom)
                params.set('from', nextFrom);
            if (nextTo)
                params.set('to', nextTo);
            const payload = await apiRequest<Payload>(`/api/v1/activity-tracking${params.size ? `?${params}` : ''}`, { workspaceId, silent });
            setData(payload);
            setSettingsDraft(payload.settings);
            if (!nextFrom)
                setFrom(payload.range.from);
            if (!nextTo)
                setTo(payload.range.to);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not load application and website activity.');
        }
        finally {
            if (!silent)
                setLoading(false);
        }
    };
    useEffect(() => { void load(false, '', ''); }, [workspaceId]);
    const usageRows = useMemo(() => {
        const normalized = query.trim().toLowerCase();
        return (data?.usage ?? []).filter(row => {
            const viewMatch = view === 'all' || (view === 'apps' && row.type === 'app') || (view === 'websites' && row.type === 'domain');
            const searchMatch = !normalized || row.name.toLowerCase().includes(normalized) || row.category.toLowerCase().includes(normalized);
            return viewMatch && searchMatch;
        });
    }, [data, query, view]);
    /** Handles the open new rule operation for the WorkIntel client. */ const openNewRule = (row?: UsageRow) => {
        setEditingRule(null);
        setRuleForm(row ? {
            ...emptyRule,
            target_type: row.type,
            target: row.target,
            classification: row.classification === 'unclassified' ? 'productive' : row.classification,
            category: row.category === 'Application' || row.category === 'Web' ? '' : row.category,
        } : emptyRule);
        setRuleOpen(true);
    };
    /** Handles the open edit rule operation for the WorkIntel client. */ const openEditRule = (rule: Rule) => {
        setEditingRule(rule);
        setRuleForm({
            scope_type: rule.scope_type,
            scope_id: rule.scope_id ? String(rule.scope_id) : '',
            target_type: rule.target_type,
            target: rule.target,
            classification: rule.classification,
            category: rule.category ?? '',
        });
        setRuleOpen(true);
    };
    /** Handles the save rule operation for the WorkIntel client. */ const saveRule = async (event: FormEvent) => {
        event.preventDefault();
        if (!workspaceId)
            return;
        setRuleSaving(true);
        setError('');
        try {
            const body = {
                ...ruleForm,
                scope_id: ruleForm.scope_type === 'workspace' ? null : Number(ruleForm.scope_id),
                category: ruleForm.category.trim() || null,
                active: true,
            };
            await apiRequest(editingRule ? `/api/v1/activity-tracking/rules/${editingRule.id}` : '/api/v1/activity-tracking/rules', {
                method: editingRule ? 'PUT' : 'POST', workspaceId, body: JSON.stringify(body),
            });
            setRuleOpen(false);
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not save classification rule.');
        }
        finally {
            setRuleSaving(false);
        }
    };
    /** Handles the delete rule operation for the WorkIntel client. */ const deleteRule = async (rule: Rule) => {
        if (!workspaceId || !await confirmAction({ title: 'Delete classification rule?', description: `Delete classification rule for ${rule.target}?`, confirmLabel: 'Delete', danger: true }))
            return;
        setActionId(rule.id);
        try {
            await apiRequest(`/api/v1/activity-tracking/rules/${rule.id}`, { method: 'DELETE', workspaceId });
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not delete rule.');
        }
        finally {
            setActionId(null);
        }
    };
    /** Handles the open new exclusion operation for the WorkIntel client. */ const openNewExclusion = (row?: UsageRow) => {
        setEditingExclusion(null);
        setExclusionForm(row ? { ...emptyExclusion, target_type: row.type, pattern: row.target } : emptyExclusion);
        setExclusionOpen(true);
    };
    /** Handles the open edit exclusion operation for the WorkIntel client. */ const openEditExclusion = (exclusion: Exclusion) => {
        setEditingExclusion(exclusion);
        setExclusionForm({
            scope_type: exclusion.scope_type,
            scope_id: exclusion.scope_id ? String(exclusion.scope_id) : '',
            target_type: exclusion.target_type,
            pattern: exclusion.pattern,
            reason: exclusion.reason ?? '',
        });
        setExclusionOpen(true);
    };
    /** Handles the save exclusion operation for the WorkIntel client. */ const saveExclusion = async (event: FormEvent) => {
        event.preventDefault();
        if (!workspaceId)
            return;
        setExclusionSaving(true);
        setError('');
        try {
            const body = {
                ...exclusionForm,
                scope_id: exclusionForm.scope_type === 'workspace' ? null : Number(exclusionForm.scope_id),
                reason: exclusionForm.reason.trim() || null,
                active: true,
            };
            await apiRequest(editingExclusion ? `/api/v1/activity-tracking/exclusions/${editingExclusion.id}` : '/api/v1/activity-tracking/exclusions', {
                method: editingExclusion ? 'PUT' : 'POST', workspaceId, body: JSON.stringify(body),
            });
            setExclusionOpen(false);
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not save exclusion.');
        }
        finally {
            setExclusionSaving(false);
        }
    };
    /** Handles the delete exclusion operation for the WorkIntel client. */ const deleteExclusion = async (exclusion: Exclusion) => {
        if (!workspaceId || !await confirmAction({ title: 'Delete tracking exclusion?', description: `Delete exclusion for ${exclusion.pattern}?`, confirmLabel: 'Delete', danger: true }))
            return;
        setActionId(exclusion.id);
        try {
            await apiRequest(`/api/v1/activity-tracking/exclusions/${exclusion.id}`, { method: 'DELETE', workspaceId });
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not delete exclusion.');
        }
        finally {
            setActionId(null);
        }
    };
    /** Handles the create browser enrollment operation for the WorkIntel client. */ const createBrowserEnrollment = async (event: FormEvent) => {
        event.preventDefault();
        if (!workspaceId || !browserMemberId)
            return;
        setBrowserSaving(true);
        try {
            const payload = await apiRequest<Enrollment>('/api/v1/activity-tracking/browser-enrollments', {
                method: 'POST', workspaceId,
                body: JSON.stringify({ member_id: Number(browserMemberId), expires_minutes: Number(browserExpires) }),
            });
            setBrowserEnrollment(payload);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not create browser enrollment code.');
        }
        finally {
            setBrowserSaving(false);
        }
    };
    /** Handles the revoke browser operation for the WorkIntel client. */ const revokeBrowser = async (connection: BrowserConnection) => {
        if (!workspaceId || !await confirmAction({ title: 'Revoke browser connection?', description: `Revoke ${connection.browser_name} for ${connection.employee}?`, confirmLabel: 'Revoke', danger: true }))
            return;
        setActionId(connection.id);
        try {
            await apiRequest(`/api/v1/activity-tracking/browser-connections/${connection.id}/revoke`, { method: 'POST', workspaceId });
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not revoke browser connection.');
        }
        finally {
            setActionId(null);
        }
    };
    /** Handles the save settings operation for the WorkIntel client. */ const saveSettings = async () => {
        if (!workspaceId || !settingsDraft)
            return;
        setSettingsSaving(true);
        try {
            await apiRequest('/api/v1/activity-tracking/settings', {
                method: 'PUT', workspaceId,
                body: JSON.stringify({
                    application_tracking_enabled: settingsDraft.application_tracking_enabled,
                    website_tracking_enabled: settingsDraft.website_tracking_enabled,
                    capture_window_titles: settingsDraft.capture_window_titles,
                    capture_page_titles: settingsDraft.capture_page_titles,
                    minimum_session_seconds: Number(settingsDraft.minimum_session_seconds),
                    idle_threshold_seconds: Number(settingsDraft.idle_threshold_seconds),
                }),
            });
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not save tracking settings.');
        }
        finally {
            setSettingsSaving(false);
        }
    };
    /** Handles the open usage details operation for the WorkIntel client. */ const openUsageDetails = async (row: UsageRow) => {
        if (!workspaceId)
            return;
        setDetailRow(row);
        setDetailSessions([]);
        setDetailLoading(true);
        try {
            const params = new URLSearchParams({ type: row.type, target: row.target });
            if (from)
                params.set('from', from);
            if (to)
                params.set('to', to);
            const payload = await apiRequest<{
                data: SessionDetail[];
            }>(`/api/v1/activity-tracking/sessions?${params}`, { workspaceId, silent: true });
            setDetailSessions(payload.data);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not load session details.');
        }
        finally {
            setDetailLoading(false);
        }
    };
    /** Shared DataGrid columns for aggregated application/domain usage. */
    const usageColumns: DataGridColumn<UsageRow>[] = [{ id: 'name', header: 'Name', searchValue: r => `${r.name} ${r.target}`, cell: r => <Inline align="center" gap={9}><Box className="ui-icon-tile">{r.type === 'app' ? <AppWindow size={15}/> : <Globe2 size={15}/>}</Box><div><strong>{r.name}</strong><div className="ui-card-description">{r.target}</div></div></Inline> }, { id: 'type', header: 'Type', value: r => r.type, cell: r => <Badge tone={r.type === 'app' ? 'accent' : 'info'}>{r.type === 'app' ? 'App' : 'Website'}</Badge> }, { id: 'category', header: 'Category', value: r => r.category, cell: r => <Inline gap={5}><Tag size={12}/>{r.category}</Inline> }, { id: 'time', header: 'Time', sortValue: r => r.seconds, cell: r => <span className="stat-num">{formatDuration(r.seconds)}</span> }, { id: 'active', header: 'Active', sortValue: r => r.active_seconds, cell: r => <span className="stat-num">{formatDuration(r.active_seconds)}</span> }, { id: 'share', header: 'Share', sortValue: r => r.share, cell: r => `${r.share}%` }, { id: 'people', header: 'People', sortValue: r => r.people, cell: r => <Inline gap={5}><Users size={12}/>{r.people}</Inline> }, { id: 'classification', header: 'Classification', value: r => r.classification, cell: r => <Badge tone={classificationTone(r.classification)}>{r.classification}</Badge> }, { id: 'actions', header: '', sortable: false, hideable: false, cell: r => <Dropdown trigger={<Button iconOnly size="sm" variant="ghost" aria-label={`Actions for ${r.name}`}><Ellipsis size={15}/></Button>} items={[{ label: 'Open session details', icon: <ExternalLink size={14}/>, onClick: () => void openUsageDetails(r) }, ...(canManage ? [{ label: 'Create classification rule', icon: <SlidersHorizontal size={14}/>, onClick: () => openNewRule(r) }, { label: 'Exclude from tracking', icon: <Ban size={14}/>, onClick: () => openNewExclusion(r) }] : [])]}/> }];
    /** Shared DataGrid columns for classification rules. */ const ruleColumns: DataGridColumn<Rule>[] = [{ id: 'target', header: 'Target', value: r => r.target, cell: r => <strong>{r.target}</strong> }, { id: 'type', header: 'Type', value: r => r.target_type, cell: r => <Badge>{r.target_type}</Badge> }, { id: 'scope', header: 'Scope', searchValue: r => data ? scopeLabel(r, data.members, data.scope_options) : '', cell: r => data ? scopeLabel(r, data.members, data.scope_options) : '—' }, { id: 'category', header: 'Category', value: r => r.category ?? '', cell: r => r.category ?? '—' }, { id: 'classification', header: 'Classification', value: r => r.classification, cell: r => <Badge tone={classificationTone(r.classification)}>{r.classification}</Badge> }, { id: 'actions', header: '', sortable: false, hideable: false, cell: r => canManage ? <Dropdown trigger={<Button iconOnly size="sm" variant="ghost" aria-label={`Actions for ${r.target}`}><Ellipsis size={15}/></Button>} items={[{ label: 'Edit rule', icon: <SlidersHorizontal size={14}/>, onClick: () => openEditRule(r) }, { label: 'Delete rule', icon: <Trash2 size={14}/>, danger: true, onClick: () => void deleteRule(r) }]}/> : null }];
    /** Shared DataGrid columns for privacy exclusions. */ const exclusionColumns: DataGridColumn<Exclusion>[] = [{ id: 'pattern', header: 'Pattern', value: r => r.pattern, cell: r => <strong>{r.pattern}</strong> }, { id: 'type', header: 'Type', value: r => r.target_type, cell: r => <Badge>{r.target_type}</Badge> }, { id: 'scope', header: 'Scope', searchValue: r => data ? scopeLabel(r, data.members, data.scope_options) : '', cell: r => data ? scopeLabel(r, data.members, data.scope_options) : '—' }, { id: 'reason', header: 'Reason', value: r => r.reason ?? '', cell: r => r.reason ?? '—' }, { id: 'status', header: 'Status', value: r => r.active ? 'active' : 'disabled', cell: r => <Badge tone={r.active ? 'success' : 'neutral'}>{r.active ? 'Active' : 'Disabled'}</Badge> }, { id: 'actions', header: '', sortable: false, hideable: false, cell: r => canManage ? <Dropdown trigger={<Button iconOnly size="sm" variant="ghost" aria-label={`Actions for ${r.pattern}`}><Ellipsis size={15}/></Button>} items={[{ label: 'Edit exclusion', icon: <ShieldCheck size={14}/>, onClick: () => openEditExclusion(r) }, { label: 'Delete exclusion', icon: <Trash2 size={14}/>, danger: true, onClick: () => void deleteExclusion(r) }]}/> : null }];
    /** Shared DataGrid columns for browser-extension connections. */ const browserColumns: DataGridColumn<BrowserConnection>[] = [{ id: 'employee', header: 'Employee', value: r => r.employee, cell: r => <strong>{r.employee}</strong> }, { id: 'browser', header: 'Browser', searchValue: r => `${r.browser_name} ${r.browser_version ?? ''}`, cell: r => `${r.browser_name} ${r.browser_version ?? ''}`.trim() }, { id: 'extension', header: 'Extension', value: r => r.extension_version, cell: r => `v${r.extension_version}` }, { id: 'seen', header: 'Last Seen', sortValue: r => r.last_seen_at ? new Date(r.last_seen_at).getTime() : 0, cell: r => relativeTime(r.last_seen_at) }, { id: 'sync', header: 'Last Sync', sortValue: r => r.last_sync_at ? new Date(r.last_sync_at).getTime() : 0, cell: r => relativeTime(r.last_sync_at) }, { id: 'status', header: 'Status', value: r => r.status, cell: r => <Badge tone={r.status === 'active' ? 'success' : 'neutral'}>{r.status}</Badge> }, { id: 'actions', header: '', sortable: false, hideable: false, cell: r => canManage && r.status === 'active' ? <Button size="sm" variant="ghost" loading={actionId === r.id} onClick={() => void revokeBrowser(r)}><Ban size={14}/> Revoke</Button> : null }];
    if (loading && !data)
        return <PageLoadingState />;
    return <Page>
    <PageHeader title="Apps & Websites" description="Application and domain-level browser tracking with privacy exclusions and scope-aware classification" actions={<>
        <Button variant="outline" size="sm" loading={loading} onClick={() => void load()}><RefreshCw size={14}/> Refresh</Button>
        {canManage && <Button variant="primary" size="sm" onClick={() => openNewRule()}><Plus size={14}/> Add Rule</Button>}
      </>}/>

    {error && <Alert tone="danger">{error}</Alert>}
    <Alert tone="info" icon={<ShieldCheck size={15}/>}>Website tracking stores <strong>domain only</strong> by default. Full URLs, query strings, form values, typed text and page content are not accepted by the tracking API.</Alert>

    <Grid columns="repeat(auto-fit,minmax(190px,1fr))" gap={12} m="14px 0 16px">
      <StatCard label="Tracked Usage" value={formatDuration(data?.stats.tracked_seconds ?? 0)} sub="Selected date range" icon={<Clock3 size={16}/>}/>
      <StatCard label="Productive" value={`${data?.stats.productive_percent ?? 0}%`} sub="Of classified usage" icon={<CheckCircle2 size={16}/>}/>
      <StatCard label="Applications" value={String(data?.stats.applications ?? 0)} sub="Observed application keys" icon={<AppWindow size={16}/>}/>
      <StatCard label="Web Domains" value={String(data?.stats.domains ?? 0)} sub="Domain-only browser sessions" icon={<Globe2 size={16}/>}/>
    </Grid>

    <Tabs value={section} onChange={setSection} tabs={[
            { value: 'usage', label: 'Usage' }, { value: 'rules', label: 'Classification Rules' }, { value: 'exclusions', label: 'Privacy Exclusions' },
            { value: 'browsers', label: 'Browser Connections' }, { value: 'settings', label: 'Tracking Settings' },
        ]}/>

    <Box mt={14}>
      {section === 'usage' && <Card>
        <CardHeader title="Usage Directory" description="Aggregated from normalized desktop-agent and browser-extension sessions" action={<Inline gap={8} align="center" wrap="wrap">
          <Input type="date" value={from} onChange={event => setFrom(event.target.value)} width={142}/>
          <Input type="date" value={to} onChange={event => setTo(event.target.value)} width={142}/>
          <Button size="sm" variant="outline" onClick={() => void load(false, from, to)}>Apply</Button>
          <Segmented value={view} onChange={setView} options={[{ value: 'all', label: 'All' }, { value: 'apps', label: <><AppWindow size={13}/> Apps</> }, { value: 'websites', label: <><Globe2 size={13}/> Websites</> }]}/>
        </Inline>}/>
        <CardBody p={0}>
          <Box p="12px 14px" borderBottom="1px solid var(--border-muted)"><Box maxWidth={390}><SearchInput value={query} onChange={event => setQuery(event.target.value)} placeholder="Search applications, domains or categories…"/></Box></Box>
          {usageRows.length === 0 ? <EmptyState icon={<Search size={21}/>} title="No matching usage" text="Tracked sessions will appear here after an enrolled desktop agent or browser extension syncs data."/> : <DataGrid rows={usageRows} columns={usageColumns} rowKey={row => `${row.type}:${row.target}`} persistKey="apps-sites.usage" searchable={false} defaultSort={{ id: 'time', direction: 'desc' }} ariaLabel="Application and website usage"/>}
        </CardBody>
      </Card>}

      {section === 'rules' && <Card>
        <CardHeader title="Classification Rules" description="More-specific project/member/team/department rules override workspace defaults" action={canManage ? <Button size="sm" variant="primary" onClick={() => openNewRule()}><Plus size={14}/> Add Rule</Button> : undefined}/>
        <CardBody p={0}>{(data?.rules.length ?? 0) === 0 ? <EmptyState icon={<SlidersHorizontal size={22}/>} title="No classification rules" text="Unclassified activity remains visible but is not counted as productive or unproductive."/> : <DataGrid rows={data?.rules ?? []} columns={ruleColumns} rowKey={row => row.id} persistKey="apps-sites.rules" searchPlaceholder="Search classification rules…" ariaLabel="Classification rules"/>}</CardBody>
      </Card>}

      {section === 'exclusions' && <Card>
        <CardHeader title="Privacy Exclusions" description="Matching apps/domains are discarded during ingestion and never become activity sessions" action={canManage ? <Button size="sm" variant="primary" onClick={() => openNewExclusion()}><Plus size={14}/> Add Exclusion</Button> : undefined}/>
        <CardBody p={0}>{(data?.exclusions.length ?? 0) === 0 ? <EmptyState icon={<ShieldCheck size={22}/>} title="No custom exclusions" text="Add banking, password manager, private or non-work applications/domains that should never be tracked."/> : <DataGrid rows={data?.exclusions ?? []} columns={exclusionColumns} rowKey={row => row.id} persistKey="apps-sites.exclusions" searchPlaceholder="Search privacy exclusions…" ariaLabel="Privacy exclusions"/>}</CardBody>
      </Card>}

      {section === 'browsers' && <Card>
        <CardHeader title="Browser Connections" description="Chrome/Edge extension connections use their own revocable token" action={canManage ? <Button size="sm" variant="primary" onClick={() => { setBrowserEnrollment(null); setBrowserMemberId(data?.members[0] ? String(data.members[0].id) : ''); setBrowserExpires('10'); setBrowserOpen(true); }}><Link2 size={14}/> Connect Browser</Button> : undefined}/>
        <CardBody p={0}>{(data?.browser_connections.length ?? 0) === 0 ? <EmptyState icon={<Globe2 size={22}/>} title="No browser extensions connected" text="Generate an enrollment code and enter it in the WorkIntel Chrome/Edge extension."/> : <DataGrid rows={data?.browser_connections ?? []} columns={browserColumns} rowKey={row => row.id} persistKey="apps-sites.browsers" searchPlaceholder="Search browser connections…" ariaLabel="Browser connections"/>}</CardBody>
      </Card>}

      {section === 'settings' && settingsDraft && <Card>
        <CardHeader title="Tracking Settings" description="Privacy-safe defaults apply across newly ingested sessions" action={canManage ? <Button size="sm" variant="primary" loading={settingsSaving} onClick={() => void saveSettings()}><Settings2 size={14}/> Save Settings</Button> : undefined}/>
        <CardBody><Stack gap={14} maxWidth={760}>
          {[
                ['Application tracking', 'Collect application/process sessions from enrolled desktop agents.', 'application_tracking_enabled'],
                ['Website tracking', 'Collect active-tab domain sessions from connected browser extensions.', 'website_tracking_enabled'],
                ['Window titles', 'Store desktop window titles. Off by default because titles can contain sensitive context.', 'capture_window_titles'],
                ['Page titles', 'Store browser page titles. Full URLs remain disabled.', 'capture_page_titles'],
            ].map(([label, hint, key]) => <Box key={key} display="flex" justify="space-between" gap={20} p="12px 0" borderBottom="1px solid var(--border-muted)"><div><Box weight={600}>{label}</Box><Box className="ui-card-description" mt={3}>{hint}</Box></div><Switch checked={Boolean(settingsDraft[key as keyof Settings])} disabled={!canManage} onChange={checked => setSettingsDraft(current => current ? { ...current, [key]: checked } : current)}/></Box>)}
          <Grid columns="repeat(2,minmax(0,1fr))" gap={12}>
            <Field label="Minimum session seconds" hint="Very short app/tab switches below this duration are ignored"><Input type="number" min={1} max={300} value={settingsDraft.minimum_session_seconds} disabled={!canManage} onChange={event => setSettingsDraft(current => current ? { ...current, minimum_session_seconds: Number(event.target.value) } : current)}/></Field>
            <Field label="Idle threshold seconds" hint="Shared threshold used by collectors when determining idle state"><Input type="number" min={60} max={3600} value={settingsDraft.idle_threshold_seconds} disabled={!canManage} onChange={event => setSettingsDraft(current => current ? { ...current, idle_threshold_seconds: Number(event.target.value) } : current)}/></Field>
          </Grid>
          <Alert tone="success" icon={<ShieldCheck size={15}/>}>Full URL storage is intentionally disabled. The server accepts domains only and rejects URL paths, query strings, form data, clipboard data and typed content.</Alert>
        </Stack></CardBody>
      </Card>}
    </Box>

    <Drawer open={Boolean(detailRow)} onClose={() => setDetailRow(null)} title={detailRow?.name ?? 'Usage details'} description={detailRow ? `${detailRow.type === 'app' ? 'Application' : 'Domain'} · ${formatDuration(detailRow.seconds)} tracked` : undefined}>
      {detailLoading ? <PageLoadingState /> : detailSessions.length === 0 ? <EmptyState icon={<Clock3 size={22}/>} title="No sessions in this range" text="Try a wider date range or wait for the collector to sync."/> : <Stack gap={8}>
        {detailSessions.map(item => <Box key={`${item.type}:${item.id}`} p={12} border="1px solid var(--border-muted)" radius={9}>
          <Inline justify="space-between" gap={10} align="flex-start"><div><Box size={12} weight={650}>{item.employee}</Box><Box className="ui-card-description" mt={2}>{item.device || item.browser || item.source}{item.project ? ` · ${item.project}` : ''}{item.task ? ` · ${item.task}` : ''}</Box></div><Badge tone="neutral">{formatDuration(item.duration_seconds)}</Badge></Inline>
          <Box display="grid" gridColumns="1fr 1fr 1fr" gap={8} mt={10} size={11}><div><span className="ui-card-description">Started</span><br />{item.started_at ? new Date(item.started_at).toLocaleString() : '—'}</div><div><span className="ui-card-description">Active</span><br />{formatDuration(item.active_seconds)}</div><div><span className="ui-card-description">Idle</span><br />{formatDuration(item.idle_seconds)}</div></Box>
          {(item.window_title || item.page_title) && <Box mt={9} pt={8} borderTop="1px solid var(--border-muted)" size={11} color="var(--text-2)">{item.window_title || item.page_title}</Box>}
        </Box>)}
      </Stack>}
    </Drawer>

    <Modal open={ruleOpen} onClose={() => !ruleSaving && setRuleOpen(false)} title={editingRule ? 'Edit classification rule' : 'Add classification rule'} footer={<><Button variant="outline" disabled={ruleSaving} onClick={() => setRuleOpen(false)}>Cancel</Button><Button variant="primary" loading={ruleSaving} form="activity-rule-submit" type="submit">Save Rule</Button></>}>
      <Form id="activity-rule-submit" onSubmit={saveRule} gap={12}>
        <Field label="Target type"><Select value={ruleForm.target_type} onChange={event => setRuleForm(current => ({ ...current, target_type: event.target.value as RuleForm['target_type'] }))}><Option value="app">Application</Option><Option value="domain">Domain</Option></Select></Field>
        <Field label={ruleForm.target_type === 'app' ? 'Application/process key' : 'Domain'}><Input required value={ruleForm.target} onChange={event => setRuleForm(current => ({ ...current, target: event.target.value }))} placeholder={ruleForm.target_type === 'app' ? 'code.exe' : 'github.com'}/></Field>
        <Grid columns="1fr 1fr" gap={10}>
          <Field label="Classification"><Select value={ruleForm.classification} onChange={event => setRuleForm(current => ({ ...current, classification: event.target.value as Classification }))}><Option value="productive">Productive</Option><Option value="neutral">Neutral</Option><Option value="unproductive">Unproductive</Option><Option value="unclassified">Unclassified</Option></Select></Field>
          <Field label="Category"><Input value={ruleForm.category} onChange={event => setRuleForm(current => ({ ...current, category: event.target.value }))} placeholder="Development"/></Field>
        </Grid>
        <Field label="Scope"><Select value={ruleForm.scope_type} onChange={event => setRuleForm(current => ({ ...current, scope_type: event.target.value as RuleForm['scope_type'], scope_id: '' }))}><Option value="workspace">Workspace</Option><Option value="member">Employee</Option><Option value="department">Department ID</Option><Option value="team">Team ID</Option><Option value="project">Project ID</Option></Select></Field>
        {ruleForm.scope_type !== 'workspace' && <Field label={ruleForm.scope_type === 'member' ? 'Employee' : ruleForm.scope_type.charAt(0).toUpperCase() + ruleForm.scope_type.slice(1)}><Select required value={ruleForm.scope_id} onChange={event => setRuleForm(current => ({ ...current, scope_id: event.target.value }))}><Option value="">Select {ruleForm.scope_type}</Option>{(ruleForm.scope_type === 'member' ? data?.members : ruleForm.scope_type === 'department' ? data?.scope_options.departments : ruleForm.scope_type === 'team' ? data?.scope_options.teams : data?.scope_options.projects)?.map(item => <Option key={item.id} value={item.id}>{item.name}</Option>)}</Select></Field>}
        
      </Form>
    </Modal>

    <Modal open={exclusionOpen} onClose={() => !exclusionSaving && setExclusionOpen(false)} title={editingExclusion ? 'Edit privacy exclusion' : 'Add privacy exclusion'} footer={<><Button variant="outline" disabled={exclusionSaving} onClick={() => setExclusionOpen(false)}>Cancel</Button><Button variant="primary" loading={exclusionSaving} form="activity-exclusion-submit" type="submit">Save Exclusion</Button></>}>
      <Form id="activity-exclusion-submit" onSubmit={saveExclusion} gap={12}>
        <Field label="Target type"><Select value={exclusionForm.target_type} onChange={event => setExclusionForm(current => ({ ...current, target_type: event.target.value as ExclusionForm['target_type'] }))}><Option value="app">Application</Option><Option value="domain">Domain</Option></Select></Field>
        <Field label="Pattern"><Input required value={exclusionForm.pattern} onChange={event => setExclusionForm(current => ({ ...current, pattern: event.target.value }))} placeholder={exclusionForm.target_type === 'app' ? '1password.exe' : 'bank.example'}/></Field>
        <Field label="Reason"><Input value={exclusionForm.reason} onChange={event => setExclusionForm(current => ({ ...current, reason: event.target.value }))} placeholder="Password manager / banking / private"/></Field>
        <Field label="Scope"><Select value={exclusionForm.scope_type} onChange={event => setExclusionForm(current => ({ ...current, scope_type: event.target.value as ExclusionForm['scope_type'], scope_id: '' }))}><Option value="workspace">Workspace</Option><Option value="member">Employee</Option><Option value="department">Department ID</Option><Option value="team">Team ID</Option></Select></Field>
        {exclusionForm.scope_type !== 'workspace' && <Field label={exclusionForm.scope_type === 'member' ? 'Employee' : exclusionForm.scope_type.charAt(0).toUpperCase() + exclusionForm.scope_type.slice(1)}><Select required value={exclusionForm.scope_id} onChange={event => setExclusionForm(current => ({ ...current, scope_id: event.target.value }))}><Option value="">Select {exclusionForm.scope_type}</Option>{(exclusionForm.scope_type === 'member' ? data?.members : exclusionForm.scope_type === 'department' ? data?.scope_options.departments : data?.scope_options.teams)?.map(item => <Option key={item.id} value={item.id}>{item.name}</Option>)}</Select></Field>}
        
      </Form>
    </Modal>

    <Modal open={browserOpen} onClose={() => !browserSaving && setBrowserOpen(false)} title="Connect browser extension" description="Generate a one-time code for an employee. The extension receives a separate revocable token." footer={<>{!browserEnrollment && <Button variant="primary" loading={browserSaving} form="browser-enroll-submit" type="submit">Generate Code</Button>}<Button variant="outline" onClick={() => setBrowserOpen(false)}>Close</Button></>}>
      {!browserEnrollment ? <Form id="browser-enroll-submit" onSubmit={createBrowserEnrollment} gap={12}>
        <Field label="Employee"><Select required value={browserMemberId} onChange={event => setBrowserMemberId(event.target.value)}><Option value="">Select employee</Option>{data?.members.map(member => <Option key={member.id} value={member.id}>{member.name}</Option>)}</Select></Field>
        <Field label="Code expires in"><Select value={browserExpires} onChange={event => setBrowserExpires(event.target.value)}><Option value="5">5 minutes</Option><Option value="10">10 minutes</Option><Option value="15">15 minutes</Option><Option value="30">30 minutes</Option><Option value="60">60 minutes</Option></Select></Field>
        
      </Form> : <Stack gap={12}><Alert tone="success">Browser enrollment code created. It is shown only once.</Alert><Box textAlign="center" p={18} border="1px solid var(--border)" radius={10} bg="var(--elevated)"><div className="ui-card-description">One-time code</div><Box className="stat-num" size={24} weight={750} letterSpacing={2} m="8px 0">{browserEnrollment.enrollment_code}</Box><Button size="sm" variant="outline" onClick={() => void navigator.clipboard.writeText(browserEnrollment.enrollment_code)}><Copy size={14}/> Copy Code</Button></Box><div className="ui-card-description">Extension enrollment endpoint: <code>{browserEnrollment.enrollment_endpoint}</code><br />Expires: {new Date(browserEnrollment.expires_at).toLocaleString()}</div></Stack>}
    </Modal>
  </Page>;
}
