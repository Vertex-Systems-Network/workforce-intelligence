import { useEffect, useMemo, useState } from 'react';
import type { LucideIcon } from 'lucide-react';
import { AppWindow, BookOpen, Check, CheckCircle2, Clipboard, Download as DownloadIcon, FileArchive, Globe2, Laptop, Monitor, RefreshCw, ShieldCheck, Terminal, Wrench } from 'lucide-react';
import { apiDownload, apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { useLocalization } from '../i18n/LocalizationContext';
import { Alert, Badge, Button, Card, CardBody, CardHeader, EmptyState, Page, PageHeader, Tabs, Pressable, Box, Grid, Inline, Stack, Text } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
type Release = {
    slug: string;
    platform: string;
    channel: string;
    version: string;
    released_at: string;
    requirements: string;
    filename: string;
    size_bytes: number;
    sha256: string;
    mime_type: string;
    notes: string;
    download_url: string;
    kind: 'agent' | 'extension';
};
type Step = {
    id: string;
    title: string;
    text: string;
    command?: string;
};
type Guide = {
    key: string;
    title: string;
    platform: string;
    audience: string;
    summary: string;
    requirements: string[];
    release: Release | null;
    steps: Step[];
    progress: {
        completed_steps: string[];
        current_step: string | null;
        completed_at: string | null;
    };
};
type InstallStatus = {
    desktop: {
        enrolled: boolean;
        online: boolean;
        device_name: string | null;
        agent_version: string | null;
        last_heartbeat_at: string | null;
    };
    browser: {
        enrolled: boolean;
        browser_name: string | null;
        last_seen_at: string | null;
    };
    activity: {
        detected: boolean;
        last_at: string | null;
    };
    screenshot: {
        detected: boolean;
        last_at: string | null;
        storage_status: string | null;
    };
};
type Center = {
    guides: Guide[];
    status: InstallStatus;
};
/** Handles the icon for operation for the WorkIntel client. */ const iconFor = (release: Release): LucideIcon => release.platform.toLowerCase().includes('windows') ? AppWindow : release.platform.toLowerCase().includes('mac') ? Laptop : release.platform.toLowerCase().includes('linux') ? Terminal : release.kind === 'extension' ? Globe2 : Monitor;
/** Handles the size operation for the WorkIntel client. */ const size = (bytes: number) => bytes < 1024 * 1024 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
/** Handles the downloads operation for the WorkIntel client. */ export default function Downloads() {
    const { session } = useAuth(), { t, formatDate, formatTime } = useLocalization();
    const workspaceId = session?.user.activeWorkspaceId ?? session?.user.workspaces[0]?.id ?? 0;
    const [releases, setReleases] = useState<Release[]>([]), [center, setCenter] = useState<Center | null>(null), [loading, setLoading] = useState(true), [error, setError] = useState(''), [downloading, setDownloading] = useState(''), [tab, setTab] = useState<'releases' | 'install' | 'troubleshoot'>('releases'), [selectedKey, setSelectedKey] = useState('windows-agent'), [enrollment, setEnrollment] = useState<{
        enrollment_code: string;
        expires_at: string;
    } | null>(null), [busy, setBusy] = useState(false);
    const groups = useMemo(() => ({ agents: releases.filter(r => r.kind === 'agent'), extensions: releases.filter(r => r.kind === 'extension') }), [releases]);
    const selected = center?.guides.find(g => g.key === selectedKey) ?? null;
    /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
        return; setLoading(true); try {
        const [r, c] = await Promise.all([apiRequest<{
                data: Release[];
            }>('/api/v1/releases', { workspaceId }), apiRequest<Center>('/api/v1/installation-center', { workspaceId })]);
        setReleases(r.data);
        setCenter(c);
        setError('');
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load installation center.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Handles the download operation for the WorkIntel client. */ const download = async (release: Release) => { setDownloading(release.slug); try {
        const result = await apiDownload(`/api/v1/releases/${release.slug}/download`, workspaceId);
        const url = URL.createObjectURL(result.blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = result.filename || release.filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Download failed.');
    }
    finally {
        setDownloading('');
    } };
    /** Handles the create enrollment operation for the WorkIntel client. */ const createEnrollment = async () => { setBusy(true); setError(''); try {
        setEnrollment(await apiRequest<{
            enrollment_code: string;
            expires_at: string;
        }>('/api/v1/installation-center/enrollment', { method: 'POST', workspaceId, body: JSON.stringify({ expires_minutes: 15 }) }));
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create enrollment code.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the test install operation for the WorkIntel client. */ const testInstall = async () => { setBusy(true); try {
        const r = await apiRequest<{
            data: InstallStatus;
        }>('/api/v1/installation-center/status', { workspaceId });
        setCenter(c => c ? { ...c, status: r.data } : c);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not test installation.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the toggle step operation for the WorkIntel client. */ const toggleStep = async (id: string) => { if (!selected)
        return; const done = selected.progress.completed_steps.includes(id); const completed = done ? selected.progress.completed_steps.filter(x => x !== id) : [...selected.progress.completed_steps, id]; try {
        const r = await apiRequest<{
            data: Guide['progress'];
        }>(`/api/v1/installation-center/guides/${selected.key}/progress`, { method: 'PUT', workspaceId, body: JSON.stringify({ completed_steps: completed, current_step: id }) });
        setCenter(c => c ? { ...c, guides: c.guides.map(g => g.key === selected.key ? { ...g, progress: r.data } : g) } : c);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not save guide progress.');
    } };
    /** Handles the copy operation for the WorkIntel client. */ const copy = async (text: string) => { await navigator.clipboard.writeText(text); };
    /** Handles the command operation for the WorkIntel client. */ const command = (value: string) => enrollment?.enrollment_code ? value.replaceAll('WI-XXXX-XXXX-XXXX', enrollment.enrollment_code) : value;
    if (loading)
        return <PageLoadingState title="Loading installation center" description="Checking releases, enrollment and installation status."/>;
    return <Page><PageHeader title={t('downloads.title')} description={t('downloads.subtitle')} actions={<Button variant="outline" size="sm" onClick={() => void load()}><RefreshCw size={13}/> Refresh</Button>}/>{error && <Alert tone="danger" mb={12}>{error}</Alert>}
 <Tabs value={tab} onChange={setTab} tabs={[{ value: 'releases', label: t('downloads.releases') }, { value: 'install', label: t('downloads.install') }, { value: 'troubleshoot', label: t('downloads.troubleshoot') }]}/>
 {tab === 'releases' && <Box mt={14}><ReleaseSection title="Desktop Agents" releases={groups.agents} onDownload={download} downloading={downloading} empty={t('downloads.no_release')} formatDate={formatDate}/><ReleaseSection title="Browser Extensions" releases={groups.extensions} onDownload={download} downloading={downloading} empty={t('downloads.no_release')} formatDate={formatDate}/></Box>}
 {tab === 'install' && center && <Grid columns="minmax(240px,.55fr) minmax(0,1.45fr)" gap={12} mt={14}><Card><CardHeader title={t('downloads.guides')}/><CardBody><Stack gap={7}>{center.guides.filter(g => g.key !== 'repair-uninstall').map(g => <Pressable key={g.key} type="button" onClick={() => { setSelectedKey(g.key); setEnrollment(null); }} textAlign="left" p={10} radius={8} border="1px solid var(--border)" bg={selectedKey === g.key ? 'var(--elevated)' : 'var(--bg)'} color="var(--text)" cursor="pointer"><Text as="strong" display="block" size={12}>{g.title}</Text><span className="ui-card-description">{g.audience}</span>{g.progress.completed_at && <Badge tone="success" mt={6}>{t('downloads.completed')}</Badge>}</Pressable>)}</Stack></CardBody></Card>{selected && <GuidePanel guide={selected} enrollment={enrollment} status={center.status} busy={busy} t={t} formatTime={formatTime} onEnroll={createEnrollment} onTest={testInstall} onToggle={toggleStep} onCopy={copy} command={command} onDownload={download} downloading={downloading} workspaceId={workspaceId}/>}</Grid>}
 {tab === 'troubleshoot' && center && <Box mt={14}><Card><CardHeader title="Repair, uninstall & recovery" description="Use the guided checks before removing a device or reinstalling the agent."/><CardBody>{center.guides.filter(g => g.key === 'repair-uninstall' || g.key === 'admin-production').map(g => <Box key={g.key} p="12px 0" borderBottom="1px solid var(--border-muted)"><Inline justify="space-between" gap={10}><div><strong>{g.title}</strong><div className="ui-card-description">{g.summary}</div></div><Button variant="outline" size="sm" onClick={() => { setSelectedKey(g.key); setTab('install'); }}><Wrench size={13}/> Open guide</Button></Inline></Box>)}</CardBody></Card></Box>}
 </Page>;
}
/** Handles the guide panel operation for the WorkIntel client. */ function GuidePanel({ guide, enrollment, status, busy, t, formatTime, onEnroll, onTest, onToggle, onCopy, command, onDownload, downloading, workspaceId }: {
    guide: Guide;
    enrollment: {
        enrollment_code: string;
        expires_at: string;
    } | null;
    status: InstallStatus;
    busy: boolean;
    t: any;
    formatTime: (v: string | Date | number, o?: Intl.DateTimeFormatOptions) => string;
    onEnroll: () => void;
    onTest: () => void;
    onToggle: (id: string) => void;
    onCopy: (s: string) => void;
    command: (s: string) => string;
    onDownload: (r: Release) => void;
    downloading: string;
    workspaceId: number;
}) {
    const agentGuide = ['windows-agent', 'macos-agent', 'linux-agent', 'chrome-edge-extension', 'firefox-extension'].includes(guide.key); /** Handles the pdf operation for the WorkIntel client. */
    const pdf = async () => { const r = await apiDownload(`/api/v1/installation-center/guides/${guide.key}/pdf`, workspaceId); const u = URL.createObjectURL(r.blob); const a = document.createElement('a'); a.href = u; a.download = r.filename; a.click(); URL.revokeObjectURL(u); };
    return <Stack gap={12}><Card><CardHeader title={guide.title} description={guide.summary} action={<Button variant="outline" size="sm" onClick={() => void pdf()}><BookOpen size={13}/>{t('downloads.download_pdf')}</Button>}/><CardBody><Inline gap={6} wrap="wrap" mb={12}>{guide.requirements.map(r => <Badge key={r} tone="neutral">{r}</Badge>)}</Inline>{guide.release ? <Box display="flex" align="center" justify="space-between" gap={10} p={10} border="1px solid var(--border)" radius={8} mb={12}><div><Text as="strong" size={12}>{guide.release.filename}</Text><div className="ui-card-description">SHA-256 <code>{guide.release.sha256.slice(0, 18)}…</code></div></div><Button size="sm" loading={downloading === guide.release.slug} onClick={() => onDownload(guide.release!)}><DownloadIcon size={13}/>{t('common.download')}</Button></Box> : null}{agentGuide && <Box p={12} radius={8} bg="var(--elevated)" mb={12}><Inline justify="space-between" gap={10} align="center"><div><Text as="strong" size={12}>{t('downloads.enrollment_code')}</Text><div className="ui-card-description">One-time code for your own workspace membership.</div></div><Button size="sm" loading={busy} onClick={onEnroll}>{t('downloads.create_enrollment')}</Button></Inline>{enrollment && <Inline gap={8} align="center" mt={10}><Box as="code" size={16} weight={800} letterSpacing={1}>{enrollment.enrollment_code}</Box><Button variant="ghost" size="sm" onClick={() => void onCopy(enrollment.enrollment_code)}><Clipboard size={13}/>{t('downloads.copy')}</Button><Text as="small" color="var(--text-3)">expires {formatTime(enrollment.expires_at)}</Text></Inline>}</Box>}<Stack gap={9}>{guide.steps.map((s, i) => { const done = guide.progress.completed_steps.includes(s.id); return <Box key={s.id} border="1px solid var(--border)" radius={8} p={11}><Inline justify="space-between" gap={10}><Inline gap={9}><Box width={24} height={24} radius={12} display="grid" placeItems="center" bg={done ? 'var(--success-dim)' : 'var(--elevated)'} color={done ? 'var(--success)' : 'var(--text-3)'} size={11}>{done ? <Check size={13}/> : i + 1}</Box><div><Text as="strong" size={12}>{s.title}</Text><Box className="ui-card-description" mt={3} lineHeight={1.55}>{s.text}</Box></div></Inline><Button variant="ghost" size="sm" onClick={() => void onToggle(s.id)}>{done ? <CheckCircle2 size={13}/> : null}{done ? t('downloads.completed') : t('downloads.mark_complete')}</Button></Inline>{s.command && <Inline gap={7} mt={9} align="stretch"><Box as="code" flex={1} display="block" p={9} whiteSpace="pre-wrap" overflowWrap="anywhere" bg="var(--bg)" radius={6}>{command(s.command)}</Box><Button variant="outline" size="sm" onClick={() => void onCopy(command(s.command!))}><Clipboard size={13}/>{t('downloads.copy')}</Button></Inline>}</Box>; })}</Stack></CardBody></Card><StatusCard status={status} busy={busy} onTest={onTest} t={t}/></Stack>;
}
/** Handles the status card operation for the WorkIntel client. */ function StatusCard({ status, busy, onTest, t }: {
    status: InstallStatus;
    busy: boolean;
    onTest: () => void;
    t: any;
}) { const rows = [['Desktop enrolled', status.desktop.enrolled], ['Desktop online', status.desktop.online], ['Browser enrolled', status.browser.enrolled], ['Activity detected', status.activity.detected], ['Screenshot detected', status.screenshot.detected]] as const; return <Card><CardHeader title={t('downloads.install_status')} action={<Button variant="outline" size="sm" loading={busy} onClick={onTest}><RefreshCw size={13}/>{t('downloads.test_installation')}</Button>}/><CardBody><Grid columns="repeat(auto-fit,minmax(150px,1fr))" gap={8}>{rows.map(([label, ok]) => <Box key={label} p={9} border="1px solid var(--border)" radius={7} display="flex" gap={7} align="center">{ok ? <CheckCircle2 size={15} color="var(--success)"/> : <Monitor size={15} color="var(--text-3)"/>}<Text size={11}>{label}</Text></Box>)}</Grid></CardBody></Card>; }
/** Handles the release section operation for the WorkIntel client. */ function ReleaseSection({ title, releases, onDownload, downloading, empty, formatDate }: {
    title: string;
    releases: Release[];
    onDownload: (release: Release) => void;
    downloading: string;
    empty: string;
    formatDate: (v: string | Date | number, o?: Intl.DateTimeFormatOptions) => string;
}) { return <Box as="section" mb={18}><Box as="h2" size={14}>{title}</Box>{!releases.length ? <Card><CardBody><EmptyState icon={<FileArchive size={26}/>} title={empty} text="Generate production release artifacts to populate this section."/></CardBody></Card> : <Grid columns="repeat(auto-fill,minmax(290px,1fr))" gap={12}>{releases.map(release => { const Icon = iconFor(release); return <Card key={release.slug}><CardBody><Inline gap={11}><Box width={40} height={40} radius={10} display="grid" placeItems="center" bg="var(--elevated)"><Icon size={19}/></Box><Box flex={1}><strong>{release.platform}</strong> <Badge tone="success">v{release.version}</Badge><div className="ui-card-description">{release.channel} · {formatDate(release.released_at)}</div></Box></Inline><Box m="12px 0" size={11} lineHeight={1.7} color="var(--text-3)">{release.requirements}<br />{size(release.size_bytes)} · SHA-256 <code>{release.sha256.slice(0, 12)}…</code></Box><Button variant="primary" loading={downloading === release.slug} onClick={() => onDownload(release)} width="100%"><DownloadIcon size={14}/> Download</Button></CardBody></Card>; })}</Grid>}</Box>; }
