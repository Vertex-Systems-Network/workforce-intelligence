import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import { Bell, Building2, Camera, HardDrive, Link2, Moon, Palette, ScrollText, Shield, Sun, UserRound, Webhook } from 'lucide-react';
import { Alert, Button, Card, CardBody, CardHeader, Field, Input, Select, Switch, Pressable, Box, Grid, Inline, Text, Form, Option, LoadingState } from '../design-system';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { hasPermission } from '../access';
import { useTheme } from '../theme';
import { NotificationSettingsM13, IntegrationsSettingsM13, ApiSettingsM13, AuditSettingsM13, SecuritySettingsM13 } from './settings/M13Settings';
import { useLocalization } from '../i18n/LocalizationContext';
import { localeOptions, coreLocales } from '../i18n/catalog';
import ScreenshotStorageSettings from './settings/ScreenshotStorageSettings';
import { MediaFileField } from '../media/MediaFileField';
type Section = {
    id: string;
    label: string;
    icon: LucideIcon;
};
type SettingsData = {
    workspace_name: string;
    timezone: string;
    currency: string;
    country?: string | null;
    week_starts_on: number;
    company_name?: string | null;
    legal_name?: string | null;
    website_url?: string | null;
    support_email?: string | null;
    support_phone?: string | null;
    address_line_1?: string | null;
    address_line_2?: string | null;
    city?: string | null;
    state_region?: string | null;
    postal_code?: string | null;
    default_language: string;
    date_format: string;
    time_format: string;
    fiscal_year_start_month: number;
    number_format: string;
    decimal_separator: string;
    thousands_separator: string;
    app_title?: string | null;
    accent_color: string;
    secondary_color?: string | null;
    default_theme: 'system' | 'light' | 'dark';
    sidebar_density: 'comfortable' | 'compact';
    login_title?: string | null;
    login_subtitle?: string | null;
    logo_url?: string | null;
    favicon_url?: string | null;
};
type SettingsResponse = {
    data: SettingsData;
    options: {
        languages: Array<{
            code: string;
            label: string;
            direction: string;
            intl: string;
            core: boolean;
        }>;
        date_formats: string[];
        time_formats: string[];
        themes: string[];
        sidebar_densities: string[];
        week_starts: number[];
    };
    can_manage: boolean;
};
type Profile = {
    first_name: string;
    last_name: string;
    email: string;
    phone?: string | null;
    avatar_url?: string | null;
    timezone?: string | null;
    locale?: string | null;
    use_workspace_locale?: boolean;
};
const languageLabels: Record<string, string> = Object.fromEntries(localeOptions.map(item => [item.code, coreLocales.includes(item.code) ? item.label : `${item.label} (English fallback)`]));
const currencies = ['USD', 'EUR', 'GBP', 'AED', 'SAR', 'PKR', 'TRY', 'INR', 'CAD', 'AUD', 'CHF', 'JPY', 'CNY', 'RUB'];
const countries = ['AE', 'SA', 'PK', 'TR', 'US', 'GB', 'DE', 'FR', 'RU', 'IN', 'CA', 'AU', 'CH', 'CN', 'JP'];
const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const timezoneOptions = (() => {
    try {
        return ((Intl as any).supportedValuesOf?.('timeZone') as string[]) ?? ['UTC'];
    }
    catch {
        return ['UTC'];
    }
})();
/** Handles the heading operation for the WorkIntel client. */ function Heading({ title, text }: {
    title: string;
    text: string;
}) { return <Box mb={20}><Box as="h2" m={0} size={17}>{title}</Box><Text className="ui-card-description" as="p" mt={4}>{text}</Text></Box>; }
/** Handles the row operation for the WorkIntel client. */ function Row({ label, desc, children }: {
    label: string;
    desc?: string;
    children: ReactNode;
}) { return <Box display="flex" align="center" justify="space-between" gap={18} p="14px 0" borderBottom="1px solid var(--border-muted)"><div><Box size={13} weight={550}>{label}</Box>{desc && <Box className="ui-card-description" mt={2}>{desc}</Box>}</div>{children}</Box>; }
/** Handles the general settings operation for the WorkIntel client. */ function GeneralSettings({ data, setData, canManage, onSaved }: {
    data: SettingsData;
    setData: (v: SettingsData) => void;
    canManage: boolean;
    onSaved: () => Promise<void>;
}) {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [busy, setBusy] = useState(false), [message, setMessage] = useState('');
    /** Handles the save operation for the WorkIntel client. */ const save = async (e: FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setMessage('');
        try {
            const r = await apiRequest<{
                data: SettingsData;
                message: string;
            }>('/api/v1/settings/workspace/general', { method: 'PUT', workspaceId, body: JSON.stringify(data) });
            setData(r.data);
            setMessage(r.message);
            await onSaved();
        }
        catch (err) {
            setMessage(err instanceof Error ? err.message : 'Could not save settings.');
        }
        finally {
            setBusy(false);
        }
    };
    return <><Heading title="General workspace settings" text="Company identity, locale, time, currency and fiscal defaults used across the workspace."/>{message && <Alert tone={message.includes('saved') ? 'success' : 'danger'}>{message}</Alert>}<Form onSubmit={save} gap={12}><Card><CardHeader title="Company & workspace"/><CardBody><Grid columns="1fr 1fr" gap={10}><Field label="Workspace name"><Input value={data.workspace_name} disabled={!canManage} onChange={e => setData({ ...data, workspace_name: e.target.value })}/></Field><Field label="Company name"><Input value={data.company_name ?? ''} disabled={!canManage} onChange={e => setData({ ...data, company_name: e.target.value })}/></Field><Field label="Legal name"><Input value={data.legal_name ?? ''} disabled={!canManage} onChange={e => setData({ ...data, legal_name: e.target.value })}/></Field><Field label="Website"><Input value={data.website_url ?? ''} disabled={!canManage} placeholder="https://example.com" onChange={e => setData({ ...data, website_url: e.target.value })}/></Field><Field label="Support email"><Input type="email" value={data.support_email ?? ''} disabled={!canManage} onChange={e => setData({ ...data, support_email: e.target.value })}/></Field><Field label="Support phone"><Input value={data.support_phone ?? ''} disabled={!canManage} onChange={e => setData({ ...data, support_phone: e.target.value })}/></Field></Grid></CardBody></Card><Card><CardHeader title="Address"/><CardBody><Grid columns="1fr 1fr" gap={10}><Field label="Address line 1"><Input value={data.address_line_1 ?? ''} disabled={!canManage} onChange={e => setData({ ...data, address_line_1: e.target.value })}/></Field><Field label="Address line 2"><Input value={data.address_line_2 ?? ''} disabled={!canManage} onChange={e => setData({ ...data, address_line_2: e.target.value })}/></Field><Field label="City"><Input value={data.city ?? ''} disabled={!canManage} onChange={e => setData({ ...data, city: e.target.value })}/></Field><Field label="State / region"><Input value={data.state_region ?? ''} disabled={!canManage} onChange={e => setData({ ...data, state_region: e.target.value })}/></Field><Field label="Postal code"><Input value={data.postal_code ?? ''} disabled={!canManage} onChange={e => setData({ ...data, postal_code: e.target.value })}/></Field><Field label="Country"><Select value={data.country ?? ''} disabled={!canManage} onChange={e => setData({ ...data, country: e.target.value })}><Option value="">Not set</Option>{countries.map(v => <Option key={v} value={v}>{v}</Option>)}</Select></Field></Grid></CardBody></Card><Card><CardHeader title="Localization & fiscal defaults"/><CardBody><Grid columns="1fr 1fr" gap={10}><Field label="Timezone"><Select value={data.timezone} disabled={!canManage} onChange={e => setData({ ...data, timezone: e.target.value })}>{timezoneOptions.map(v => <Option key={v} value={v}>{v}</Option>)}</Select></Field><Field label="Currency"><Select value={data.currency} disabled={!canManage} onChange={e => setData({ ...data, currency: e.target.value })}>{currencies.map(v => <Option key={v}>{v}</Option>)}</Select></Field><Field label="Default language"><Select value={data.default_language} disabled={!canManage} onChange={e => setData({ ...data, default_language: e.target.value })}>{Object.entries(languageLabels).map(([v, l]) => <Option key={v} value={v}>{l}</Option>)}</Select></Field><Field label="Week starts"><Select value={String(data.week_starts_on)} disabled={!canManage} onChange={e => setData({ ...data, week_starts_on: Number(e.target.value) })}><Option value="1">Monday</Option><Option value="0">Sunday</Option><Option value="6">Saturday</Option></Select></Field><Field label="Date format"><Select value={data.date_format} disabled={!canManage} onChange={e => setData({ ...data, date_format: e.target.value })}>{['YYYY-MM-DD', 'DD/MM/YYYY', 'MM/DD/YYYY', 'DD.MM.YYYY'].map(v => <Option key={v}>{v}</Option>)}</Select></Field><Field label="Time format"><Select value={data.time_format} disabled={!canManage} onChange={e => setData({ ...data, time_format: e.target.value })}><Option value="24h">24-hour</Option><Option value="12h">12-hour</Option></Select></Field><Field label="Fiscal year starts"><Select value={String(data.fiscal_year_start_month)} disabled={!canManage} onChange={e => setData({ ...data, fiscal_year_start_month: Number(e.target.value) })}>{months.map((m, i) => <Option key={m} value={i + 1}>{m}</Option>)}</Select></Field><Field label="Number format"><Select value={data.number_format} disabled={!canManage} onChange={e => { const v = e.target.value; setData({ ...data, number_format: v, decimal_separator: v === '1.234,56' ? ',' : '.', thousands_separator: v === '1.234,56' ? '.' : v === '1234.56' ? ' ' : ',' }); }}><Option value="1,234.56">1,234.56</Option><Option value="1.234,56">1.234,56</Option><Option value="1234.56">1234.56</Option></Select></Field></Grid></CardBody></Card>{canManage && <Inline justify="flex-end"><Button type="submit" loading={busy}>Save General Settings</Button></Inline>}</Form></>;
}
/** Handles the appearance settings operation for the WorkIntel client. */ function AppearanceSettings({ data, setData, canManage, onSaved }: {
    data: SettingsData;
    setData: (v: SettingsData) => void;
    canManage: boolean;
    onSaved: () => Promise<void>;
}) {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const { theme, setTheme, clearThemePreference, hasExplicitPreference } = useTheme();
    const [busy, setBusy] = useState(false), [message, setMessage] = useState(''), [logoFile, setLogoFile] = useState<File | null>(null), [faviconFile, setFaviconFile] = useState<File | null>(null);
    /** Handles the save operation for the WorkIntel client. */ const save = async (e: FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setMessage('');
        try {
            const fd = new FormData();
            ['app_title', 'accent_color', 'secondary_color', 'default_theme', 'sidebar_density', 'login_title', 'login_subtitle'].forEach(k => fd.append(k, String((data as any)[k] ?? '')));
            if (logoFile)
                fd.append('logo', logoFile);
            if (faviconFile)
                fd.append('favicon', faviconFile);
            const r = await apiRequest<{
                data: SettingsData;
                message: string;
            }>('/api/v1/settings/workspace/appearance', { method: 'POST', workspaceId, body: fd });
            setData(r.data);
            setMessage(r.message);
            await onSaved();
        }
        catch (err) {
            setMessage(err instanceof Error ? err.message : 'Could not save appearance.');
        }
        finally {
            setBusy(false);
        }
    };
    return <><Heading title="Appearance & workspace identity" text="Workspace-level title, logo, colors and defaults. Public custom-domain white-label controls remain in Platform."/>{message && <Alert tone={message.includes('saved') ? 'success' : 'danger'}>{message}</Alert>}<Form onSubmit={save}><Card><CardBody><Grid columns="1fr 1fr" gap={10}><Field label="App title"><Input value={data.app_title ?? ''} disabled={!canManage} onChange={e => setData({ ...data, app_title: e.target.value })}/></Field><Field label="Default theme"><Select value={data.default_theme} disabled={!canManage} onChange={e => setData({ ...data, default_theme: e.target.value as any })}><Option value="system">System</Option><Option value="light">Light</Option><Option value="dark">Dark</Option></Select></Field><Field label="Accent color"><Inline gap={8}><Input type="color" value={data.accent_color} disabled={!canManage} onChange={e => setData({ ...data, accent_color: e.target.value })} width={56} p={2}/><Input value={data.accent_color} disabled={!canManage} onChange={e => setData({ ...data, accent_color: e.target.value })}/></Inline></Field><Field label="Secondary color"><Input value={data.secondary_color ?? ''} disabled={!canManage} placeholder="#22C55E" onChange={e => setData({ ...data, secondary_color: e.target.value })}/></Field><MediaFileField workspaceId={workspaceId} label="Logo" accept="image/png,image/jpeg,image/webp" imagesOnly disabled={!canManage} onFiles={files => setLogoFile(files[0] ?? null)}/><MediaFileField workspaceId={workspaceId} label="Favicon" accept="image/png,image/jpeg,image/webp,image/x-icon" imagesOnly disabled={!canManage} onFiles={files => setFaviconFile(files[0] ?? null)}/><Field label="Sidebar density"><Select value={data.sidebar_density} disabled={!canManage} onChange={e => setData({ ...data, sidebar_density: e.target.value as any })}><Option value="comfortable">Comfortable</Option><Option value="compact">Compact</Option></Select></Field><Field label="Login title"><Input value={data.login_title ?? ''} disabled={!canManage} onChange={e => setData({ ...data, login_title: e.target.value })}/></Field></Grid><Field label="Login subtitle"><Input value={data.login_subtitle ?? ''} disabled={!canManage} onChange={e => setData({ ...data, login_subtitle: e.target.value })}/></Field><Inline justify="space-between" align="center" mt={14}><div className="ui-card-description">Your browser theme: <strong>{theme}</strong>{hasExplicitPreference ? ' (personal override)' : ' (workspace/system default)'}</div><Inline gap={8}><Button type="button" variant="outline" onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}>{theme === 'dark' ? <Sun size={14}/> : <Moon size={14}/>} Toggle my theme</Button>{hasExplicitPreference && <Button type="button" variant="ghost" onClick={() => { clearThemePreference(); window.dispatchEvent(new CustomEvent('workintel:workspace-theme', { detail: { theme: data.default_theme } })); }}>Use workspace default</Button>}{canManage && <Button type="submit" loading={busy}>Save Appearance</Button>}</Inline></Inline></CardBody></Card></Form></>;
}
/** Handles the personal settings operation for the WorkIntel client. */ function PersonalSettings() {
    const { session, refreshSession } = useAuth();
    const { t } = useLocalization();
    const [profile, setProfile] = useState<Profile | null>(null), [busy, setBusy] = useState(false), [message, setMessage] = useState('');
    useEffect(() => {
        apiRequest<{
            data: Profile;
        }>('/api/v1/auth/profile', { silent: true }).then(r => setProfile(r.data)).catch(() => { });
    }, []);
    if (!profile)
        return <Alert tone="info">{t('common.loading')}</Alert>; /** Handles the save operation for the WorkIntel client. */ /** Handles the save operation for the WorkIntel client. */
    const save = async (e: FormEvent) => {
        e.preventDefault();
        setBusy(true);
        try {
            const r = await apiRequest<{
                data: Profile;
            }>('/api/v1/auth/profile', { method: 'PUT', body: JSON.stringify(profile) });
            setProfile(r.data);
            setMessage(t('settings.saved'));
            await refreshSession();
        }
        catch (err) {
            setMessage(err instanceof Error ? err.message : 'Could not save preferences.');
        }
        finally {
            setBusy(false);
        }
    };
    const languageValue = profile.use_workspace_locale ? 'workspace' : (profile.locale ?? 'en');
    return <><Heading title={t('settings.personal_title')} text={t('settings.personal_text')}/>{message && <Alert tone={message === t('settings.saved') ? 'success' : 'danger'}>{message}</Alert>}<Card><CardBody><Form onSubmit={save}><Grid columns="1fr 1fr" gap={10}><Field label={t('common.language')} hint={t('settings.language_help')}><Select value={languageValue} onChange={e => { const value = e.target.value; setProfile({ ...profile, use_workspace_locale: value === 'workspace', locale: value === 'workspace' ? (profile.locale ?? 'en') : value }); }}><Option value="workspace">{t('common.workspace_default')}</Option>{Object.entries(languageLabels).map(([v, l]) => <Option key={v} value={v}>{l}</Option>)}</Select></Field><Field label={t('common.timezone')}><Select value={profile.timezone ?? session?.user.timezone ?? 'UTC'} onChange={e => setProfile({ ...profile, timezone: e.target.value })}>{timezoneOptions.map(v => <Option key={v}>{v}</Option>)}</Select></Field></Grid><Inline justify="flex-end" mt={14}><Button type="submit" loading={busy}>{t('settings.save_personal')}</Button></Inline></Form></CardBody></Card></>;
}
/** Handles the screenshots operation for the WorkIntel client. */ function Screenshots() {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [state, setState] = useState({ enabled: false, interval_minutes: 10, randomize_minutes: 2, capture_all_monitors: false, blur_by_default: false, quality: 'medium', allow_employee_delete: false, retention_days: 90, max_upload_kb: 4096, capture_notification_mode: 'always', notify_on_upload_failure: true });
    const [limits, setLimits] = useState({ interval_min: 1, interval_max: 60, randomize_max: 15, retention_days_max: 365, max_upload_kb_min: 256, max_upload_kb_max: 20480 });
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');
    useEffect(() => {
        if (!workspaceId)
            return;
        apiRequest<any>('/api/v1/screenshots?date=1970-01-01', { workspaceId, silent: true }).then(r => {
            setState(current => ({ ...current, ...r.settings }));
            if (r.limits)
                setLimits(current => ({ ...current, ...r.limits }));
        }).catch(() => { });
    }, [workspaceId]);
    /** Handles the save operation for the WorkIntel client. */ const save = async () => {
        setSaving(true);
        setMessage('');
        try {
            const payload = { ...state, interval_minutes: Math.max(1, Math.min(60, Number(state.interval_minutes) || 1)), randomize_minutes: Math.max(0, Math.min(15, Number(state.randomize_minutes) || 0)), retention_days: Math.max(1, Math.min(limits.retention_days_max, Number(state.retention_days) || 1)), max_upload_kb: Math.max(limits.max_upload_kb_min, Math.min(limits.max_upload_kb_max, Number(state.max_upload_kb) || 4096)) };
            const r = await apiRequest<any>('/api/v1/screenshots/settings', { method: 'PUT', workspaceId, body: JSON.stringify(payload) });
            setState(current => ({ ...current, ...r.settings }));
            setMessage('Screenshot policy saved.');
        }
        catch (err) {
            setMessage(err instanceof Error ? err.message : 'Could not save screenshot policy.');
        }
        finally {
            setSaving(false);
        }
    };
    return <><Heading title="Screenshot Policy" text="Capture cadence starts at 1 minute. Every value below is enforced by the screenshot API and native agent."/>{message && <Alert tone={message.includes('saved') ? 'success' : 'danger'}>{message}</Alert>}<Card><CardBody><Row label="Enable screenshots" desc="Allow enrolled agents to upload screenshots"><Switch checked={state.enabled} onChange={enabled => setState({ ...state, enabled })}/></Row><Row label="Capture interval" desc="Minutes between captures. Minimum 1 minute."><Inline align="center" gap={7}><Input type="number" min={limits.interval_min} max={limits.interval_max} step="1" value={state.interval_minutes} onChange={e => setState({ ...state, interval_minutes: Number(e.target.value) })} width={96}/><span className="ui-card-description">minute(s)</span></Inline></Row><Row label="Randomize interval" desc="Optional jitter around the interval; 0 gives an exact cadence."><Inline align="center" gap={7}><Input type="number" min="0" max={limits.randomize_max} step="1" value={state.randomize_minutes} onChange={e => setState({ ...state, randomize_minutes: Number(e.target.value) })} width={96}/><span className="ui-card-description">± minutes</span></Inline></Row><Row label="Require blur before upload"><Switch checked={state.blur_by_default} onChange={blur_by_default => setState({ ...state, blur_by_default })}/></Row><Row label="Capture all monitors"><Switch checked={state.capture_all_monitors} onChange={capture_all_monitors => setState({ ...state, capture_all_monitors })}/></Row><Row label="System capture notification" desc="Native desktop notification shown after a successful screenshot upload."><Select value={state.capture_notification_mode} onChange={e => setState({ ...state, capture_notification_mode: e.target.value })}><Option value="always">Every capture</Option><Option value="first_session">First capture of agent session</Option><Option value="silent">Silent</Option></Select></Row><Row label="Notify on upload failure" desc="Show a native warning if the screenshot upload fails."><Switch checked={state.notify_on_upload_failure} onChange={notify_on_upload_failure => setState({ ...state, notify_on_upload_failure })}/></Row><Row label="Employee can delete own"><Switch checked={state.allow_employee_delete} onChange={allow_employee_delete => setState({ ...state, allow_employee_delete })}/></Row><Row label="Retention" desc={`Your plan allows up to ${limits.retention_days_max} day(s).`}><Input type="number" min="1" max={limits.retention_days_max} value={state.retention_days} onChange={e => setState({ ...state, retention_days: Number(e.target.value) })} width={96}/></Row><Inline justify="flex-end" pt={14}><Button loading={saving} onClick={() => void save()}>Save Policy</Button></Inline></CardBody></Card></>;
}
/** Handles the settings operation for the WorkIntel client. */ export default function Settings() {
    const { session, refreshSession } = useAuth();
    const { t } = useLocalization();
    const workspace = session?.user.workspaces.find(w => w.id === session.user.activeWorkspaceId);
    const workspaceId = workspace?.id ?? 0;
    const canManage = hasPermission(workspace, 'settings.manage');
    const [active, setActive] = useState('general'), [settings, setSettings] = useState<SettingsData | null>(null), [loadError, setLoadError] = useState('');
    const sections = useMemo<Section[]>(() => {
        const list: Section[] = [{ id: 'general', label: t('settings.general'), icon: Building2 }, { id: 'appearance', label: t('settings.appearance'), icon: Palette }, { id: 'personal', label: t('settings.personal'), icon: UserRound }];
        if (hasPermission(workspace, 'screenshots.settings_manage'))
            list.push({ id: 'screenshots', label: 'Screenshots', icon: Camera });
        if (hasPermission(workspace, 'screenshots.storage_manage'))
            list.push({ id: 'screenshot-storage', label: 'Screenshot Storage', icon: HardDrive });
        if (hasPermission(workspace, 'notifications.manage'))
            list.push({ id: 'notifications', label: 'Notifications', icon: Bell });
        if (hasPermission(workspace, 'integrations.view') || hasPermission(workspace, 'integrations.manage'))
            list.push({ id: 'integrations', label: 'Integrations', icon: Link2 });
        if (hasPermission(workspace, 'api.manage'))
            list.push({ id: 'api', label: 'API & Webhooks', icon: Webhook });
        if (hasPermission(workspace, 'security.manage') || hasPermission(workspace, 'enterprise.security.manage'))
            list.push({ id: 'security', label: 'Security', icon: Shield });
        if (hasPermission(workspace, 'security.audit.view'))
            list.push({ id: 'audit', label: 'Audit Logs', icon: ScrollText });
        return list;
    }, [workspace]);
    /** Loads load data required by the current view. */ const load = async () => {
        if (!workspaceId)
            return;
        try {
            const r = await apiRequest<SettingsResponse>('/api/v1/settings/workspace', { workspaceId, silent: true });
            setSettings(r.data);
            setLoadError('');
        }
        catch (err) {
            setLoadError(err instanceof Error ? err.message : 'Could not load settings.');
        }
    };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Handles the refresh operation for the WorkIntel client. */ const refresh = async () => { await refreshSession(); await load(); window.dispatchEvent(new Event('workintel:permissions-changed')); };
    if (loadError)
        return <Box p={28}><Alert tone="danger">{loadError}</Alert></Box>;
    if (!settings)
        return <Box p={28}><LoadingState title="Loading Settings Center…" text="Preparing workspace configuration and administration modules."/></Box>;
    const content = active === 'general' ? <GeneralSettings data={settings} setData={setSettings} canManage={canManage} onSaved={refresh}/> : active === 'appearance' ? <AppearanceSettings data={settings} setData={setSettings} canManage={canManage} onSaved={refresh}/> : active === 'personal' ? <PersonalSettings /> : active === 'screenshots' ? <Screenshots /> : active === 'screenshot-storage' ? <ScreenshotStorageSettings /> : active === 'notifications' ? <NotificationSettingsM13 /> : active === 'integrations' ? <IntegrationsSettingsM13 /> : active === 'api' ? <ApiSettingsM13 /> : active === 'security' ? <SecuritySettingsM13 /> : <AuditSettingsM13 />;
    return <Box className="settings-center-layout" display="flex" height="100%"><Box as="aside" className="settings-center-nav" aria-label={t('settings.center')} width={240} shrink={0} overflowY="auto" p="18px 8px" bg="var(--surface)" borderInlineEnd="1px solid var(--border)"><div className="ui-sidebar__section-label">{t('settings.center')}</div>{sections.map(section => { const Icon = section.icon; return <Pressable key={section.id} type="button" className={`ui-nav-item${active === section.id ? ' is-active' : ''}`} onClick={() => setActive(section.id)}><span className="ui-nav-item__icon"><Icon size={15}/></span><span className="ui-nav-item__label">{section.label}</span></Pressable>; })}<Box p={12} mt={12}><Alert tone={canManage ? 'info' : 'warning'}>{canManage ? 'Workspace defaults apply across modules. Personal preferences can override language/timezone per user.' : 'You have read-only access to workspace settings.'}</Alert></Box></Box><Box className="settings-center-content" flex={1} minWidth={0} overflowY="auto" p="28px 32px">{content}</Box></Box>;
}
