import { useEffect, useState, type FormEvent } from 'react';
import { Activity, ArchiveRestore, BellRing, CheckCircle2, DatabaseBackup, Download, Gauge, HardDriveDownload, Percent, RefreshCw, Settings2, ShieldCheck, TicketPercent, TriangleAlert } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { EmptyState, ErrorState, LoadingState, Alert, Badge, Button, Card, CardBody, CardHeader, Field, Input, Modal, Page, PageHeader, Select, Switch, DataGrid, Tabs, Textarea, type DataGridColumn, Box, Grid, Inline, Stack, Form, Option } from '../design-system';
type Summary = {
    customers: number;
    paid_subscriptions: number;
    mrr: number;
    arr: number;
    month_collected: number;
    month_refunded: number;
    churn_percent: number;
    open_invoices: number;
    pending_checkouts: number;
};
type Provider = {
    provider: string;
    display_name: string;
    enabled: boolean;
    is_default: boolean;
    test_mode: boolean;
    settings: Record<string, unknown> | null;
    has_credentials: boolean;
    health_status: string;
    health_message: string | null;
};
type PlanEntitlement = {
    key: string;
    value_type: 'boolean' | 'integer' | 'string';
    value: {
        value: boolean | number | string;
    };
    label: string;
};
type Plan = {
    id: number;
    slug: string;
    name: string;
    description: string;
    monthly_price_per_seat: number;
    annual_price_per_seat: number;
    trial_days: number;
    is_active: boolean;
    is_public: boolean;
    is_popular?: boolean;
    subscriptions_count: number;
    entitlements: PlanEntitlement[];
};
type Coupon = {
    id: number;
    code: string;
    name: string;
    discount_type: string;
    discount_value: number;
    redeemed_count: number;
    max_redemptions: number | null;
    active: boolean;
};
type Tax = {
    id: number;
    name: string;
    country: string | null;
    state_region: string | null;
    rate_percent: number;
    active: boolean;
    priority: number;
};
type Checkout = {
    id: number;
    uuid: string;
    provider: string;
    status: string;
    total: number;
    currency: string;
    workspace?: {
        name: string;
    };
    plan?: {
        name: string;
    };
};
type Addon = {
    id: number;
    name: string;
    slug: string;
    description: string;
    status: string;
    pricing_mode: string;
    monthly_price: number;
    unit_price: number;
    subscriptions_count: number;
};
type Customer = {
    id: number;
    name: string;
    slug: string;
    status: string;
    active_members_count: number;
    subscription?: {
        status: string;
        plan?: {
            name: string;
        };
    };
};
type Transaction = {
    id: number;
    provider: string;
    type: string;
    status: string;
    currency: string;
    amount: number;
    provider_transaction_id: string | null;
    workspace_id: number;
};
type Dunning = {
    id: number;
    attempt_number: number;
    status: string;
    next_attempt_at: string | null;
    failure_message: string | null;
    subscription?: {
        plan?: {
            name: string;
        };
    };
};
type Capability = {
    key: string;
    label: string;
    value_type: 'boolean' | 'integer' | 'string';
    category: string;
};
type Payload = {
    summary: Summary;
    providers: Provider[];
    provider_catalog: {
        key: string;
        name: string;
    }[];
    plans: Plan[];
    capability_catalog: Capability[];
    addons: Addon[];
    coupons: Coupon[];
    tax_rules: Tax[];
    recent_checkouts: Checkout[];
    recent_refunds: any[];
    recent_transactions: Transaction[];
    dunning_attempts: Dunning[];
};
type BackupPolicy = {
    enabled: boolean;
    frequency: 'daily' | 'weekly';
    run_time: string;
    retention_days: number;
    minimum_verified_copies: number;
    include_private_storage: boolean;
    disk: string;
    included_paths: string[] | null;
    excluded_paths: string[] | null;
};
type BackupRun = {
    id: number;
    uuid: string;
    backup_type: string;
    status: string;
    size_bytes: number;
    file_count: number;
    created_at: string;
    verified_at: string | null;
    failure_message: string | null;
};
type OpsHealth = {
    scheduler: {
        ok: boolean;
        last_seen_at: string | null;
    };
    backup: {
        ok: boolean;
        latest: any;
        latest_verified_at: string | null;
    };
    queue: {
        connection: string;
        failed_jobs_table: boolean;
    };
    maintenance: {
        active: boolean;
    };
    storage: {
        disk: string;
        writable: boolean;
    };
};
type ObsEvent = {
    id: number;
    category: string;
    severity: string;
    event_type: string;
    source: string | null;
    message: string;
    duration_ms: string | null;
    occurrence_count: number;
    last_seen_at: string;
    workspace?: {
        name: string;
    } | null;
};
type ObsAlert = {
    id: number;
    uuid: string;
    status: string;
    severity: string;
    title: string;
    message: string;
    metric_value: string | null;
    threshold: string | null;
    triggered_at: string;
    rule?: {
        name: string;
        metric_key: string;
    };
};
type ObsRule = {
    id: number;
    key: string;
    name: string;
    metric_key: string;
    operator: string;
    threshold: string;
    window_minutes: number;
    severity: string;
    enabled: boolean;
    cooldown_minutes: number;
    channels: string[] | null;
};
type ObsHeartbeat = {
    id: number;
    key: string;
    status: string;
    expected_interval_seconds: number;
    last_seen_at: string;
};
type ObsPayload = {
    summary: {
        open_alerts: number;
        critical_alerts: number;
        errors_15m: number;
        slow_requests_15m: number;
        failed_jobs_60m: number;
        failed_webhooks_60m: number;
        payment_failures_60m: number;
        storage_failures_60m: number;
    };
    metrics: Record<string, number>;
    heartbeats: ObsHeartbeat[];
    events: ObsEvent[];
    alerts: ObsAlert[];
    rules: ObsRule[];
    failed_jobs: Array<{
        id: number;
        uuid: string;
        connection: string;
        queue: string;
        failed_at: string;
    }>;
};
type SecurityCheck = {
    key: string;
    label: string;
    ok: boolean;
    recommendation: string | null;
};
type SecurityPayload = {
    score: number;
    checks: SecurityCheck[];
    api_keys: {
        active: number;
        stale: number;
    };
    security_events: {
        open: number;
        high_open: number;
    };
    workspace_policies: {
        total: number;
        mfa_required: number;
        sso_required: number;
    };
    mfa_methods: number;
    rate_limits: Record<string, number>;
    upload_security: {
        driver: string;
        required: boolean;
        quarantine_on_detection: boolean;
    };
};
type OpsPayload = {
    policy: BackupPolicy;
    health: OpsHealth;
    backups: BackupRun[];
    restore_requests: Array<{
        id: number;
        uuid: string;
        status: string;
        restore_scope: string;
        expires_at: string;
        backup?: {
            uuid: string;
            status: string;
        };
    }>;
    events: Array<{
        id: number;
        event_type: string;
        severity: string;
        message: string;
        occurred_at: string;
    }>;
};
/** Handles the money operation for the WorkIntel client. */ const money = (v: number) => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(v);
/** Handles the seller console operation for the WorkIntel client. */ export default function SellerConsole() {
    const { session } = useAuth();
    const [data, setData] = useState<Payload | null>(null), [tab, setTab] = useState('overview'), [error, setError] = useState(''), [message, setMessage] = useState(''), [busy, setBusy] = useState(false), [initialLoading, setInitialLoading] = useState(true);
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [providerKey, setProviderKey] = useState('manual'), [providerOpen, setProviderOpen] = useState(false), [couponOpen, setCouponOpen] = useState(false), [taxOpen, setTaxOpen] = useState(false), [planOpen, setPlanOpen] = useState(false), [editingPlan, setEditingPlan] = useState<Plan | null>(null), [planValues, setPlanValues] = useState<Record<string, boolean | number | string>>({}), [planForm, setPlanForm] = useState({ monthly: '0', annual: '0', trial: '0', active: true, public: true, popular: false });
    const [providerForm, setProviderForm] = useState({ display_name: 'Manual Settlement', enabled: true, is_default: true, test_mode: true, credentials: '{}', settings: '{}' });
    const [couponForm, setCouponForm] = useState({ code: '', name: '', discount_type: 'percent', discount_value: '10', eligible_plans: 'gold,platinum', max_redemptions: '' });
    const [taxForm, setTaxForm] = useState({ name: '', country: '', state_region: '', rate_percent: '0', priority: '100' });
    const [settleOpen, setSettleOpen] = useState(false), [settlingCheckout, setSettlingCheckout] = useState<Checkout | null>(null), [settleReference, setSettleReference] = useState('');
    const [refundOpen, setRefundOpen] = useState(false), [refundingTransaction, setRefundingTransaction] = useState<Transaction | null>(null), [refundAmount, setRefundAmount] = useState(''), [refundReason, setRefundReason] = useState('Seller Console refund');
    const [addonOpen, setAddonOpen] = useState(false), [editingAddon, setEditingAddon] = useState<Addon | null>(null), [addonMonthlyPrice, setAddonMonthlyPrice] = useState('0');
    const [observability, setObservability] = useState<ObsPayload | null>(null), [obsBusy, setObsBusy] = useState(false), [ruleOpen, setRuleOpen] = useState(false), [editingRule, setEditingRule] = useState<ObsRule | null>(null), [ruleForm, setRuleForm] = useState({ operator: '>=', threshold: '0', window_minutes: '15', severity: 'warning', enabled: true, cooldown_minutes: '30', email: false });
    const [securityPosture, setSecurityPosture] = useState<SecurityPayload | null>(null);
    const [ops, setOps] = useState<OpsPayload | null>(null), [opsPolicy, setOpsPolicy] = useState<BackupPolicy | null>(null), [opsBusy, setOpsBusy] = useState(false), [restoreCommand, setRestoreCommand] = useState(''), [opsConfirm, setOpsConfirm] = useState('');
    /** Loads global seller data and exposes a shared loading/error state before the console is usable. */ const load = async () => { setError(''); if (!data) setInitialLoading(true); try {
        setData(await apiRequest<Payload>('/api/v1/seller'));
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load Seller Console.');
    } finally {
        setInitialLoading(false);
    } };
    useEffect(() => { void load(); }, []);
    /** Load production operations and disaster-recovery state for the platform operator. */ const loadOperations = async () => { const value = await apiRequest<OpsPayload>('/api/v1/seller/operations'); setOps(value); setOpsPolicy(value.policy); };
    useEffect(() => { if (tab === 'operations')
        void loadOperations().catch(e => setError(e instanceof Error ? e.message : 'Could not load production operations.')); }, [tab]);
    /** Load platform security posture without returning secret values. */ const loadSecurityPosture = async () => setSecurityPosture(await apiRequest<SecurityPayload>('/api/v1/seller/security-posture'));
    useEffect(() => { if (tab === 'security')
        void loadSecurityPosture().catch(e => setError(e instanceof Error ? e.message : 'Could not load security posture.')); }, [tab]);
    /** Load platform observability health, alerts, event ledger and failed-job summary. */ const loadObservability = async () => setObservability(await apiRequest<ObsPayload>('/api/v1/seller/observability'));
    useEffect(() => { if (tab === 'observability')
        void loadObservability().catch(e => setError(e instanceof Error ? e.message : 'Could not load observability data.')); }, [tab]);
    /** Evaluate alert thresholds immediately and refresh the observability dashboard. */ const evaluateObservability = async () => { setObsBusy(true); try {
        await apiRequest('/api/v1/seller/observability/evaluate', { method: 'POST' });
        await loadObservability();
        setMessage('Observability rules evaluated.');
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not evaluate observability rules.');
    }
    finally {
        setObsBusy(false);
    } };
    /** Acknowledge one active observability incident without marking it recovered. */ const acknowledgeAlert = async (id: number) => { setObsBusy(true); try {
        await apiRequest(`/api/v1/seller/observability/alerts/${id}/acknowledge`, { method: 'POST' });
        await loadObservability();
        setMessage('Alert acknowledged.');
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not acknowledge alert.');
    }
    finally {
        setObsBusy(false);
    } };
    /** Resolve one observability incident after the operator verifies recovery. */ const resolveAlert = async (id: number) => { setObsBusy(true); try {
        await apiRequest(`/api/v1/seller/observability/alerts/${id}/resolve`, { method: 'POST' });
        await loadObservability();
        setMessage('Alert resolved.');
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not resolve alert.');
    }
    finally {
        setObsBusy(false);
    } };
    /** Open the rule editor with a copy of the persisted threshold configuration. */ const openRuleEditor = (rule: ObsRule) => { setEditingRule(rule); setRuleForm({ operator: rule.operator, threshold: String(rule.threshold), window_minutes: String(rule.window_minutes), severity: rule.severity, enabled: rule.enabled, cooldown_minutes: String(rule.cooldown_minutes), email: (rule.channels ?? []).includes('email') }); setRuleOpen(true); };
    /** Persist one alert rule using only the backend allowlisted metric and operator contract. */ const saveObservabilityRule = async (e: FormEvent) => { e.preventDefault(); if (!editingRule)
        return; setObsBusy(true); try {
        await apiRequest(`/api/v1/seller/observability/rules/${editingRule.id}`, { method: 'PUT', body: JSON.stringify({ operator: ruleForm.operator, threshold: Number(ruleForm.threshold), window_minutes: Number(ruleForm.window_minutes), severity: ruleForm.severity, enabled: ruleForm.enabled, cooldown_minutes: Number(ruleForm.cooldown_minutes), channels: ['dashboard', ...(ruleForm.email ? ['email'] : [])] }) });
        setRuleOpen(false);
        await loadObservability();
        setMessage('Observability rule saved.');
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not save observability rule.');
    }
    finally {
        setObsBusy(false);
    } };
    /** Download a server-generated, secret-redacted production diagnostics bundle. */ const downloadDiagnostics = () => { window.location.href = '/api/v1/seller/observability/diagnostics'; };
    useEffect(() => { if (tab === 'customers' && !customers.length)
        void apiRequest<{
            data: {
                data: Customer[];
            };
        }>('/api/v1/seller/customers').then(r => setCustomers(r.data.data)).catch(e => setError(e instanceof Error ? e.message : 'Could not load customers.')); }, [tab]);
    /** Persist backup frequency, retention and storage-snapshot policy. */ const saveOpsPolicy = async (e: FormEvent) => { e.preventDefault(); if (!opsPolicy)
        return; setOpsBusy(true); try {
        await apiRequest('/api/v1/seller/operations/policy', { method: 'PUT', body: JSON.stringify(opsPolicy) });
        await loadOperations();
        setMessage('Backup policy saved.');
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not save backup policy.');
    }
    finally {
        setOpsBusy(false);
    } };
    /** Execute a manually confirmed production backup. */ const runOpsBackup = async (type: 'database' | 'full') => { if (opsConfirm !== 'BACKUP NOW') {
        setError('Type BACKUP NOW before starting a manual backup.');
        return;
    } setOpsBusy(true); try {
        await apiRequest('/api/v1/seller/operations/backups', { method: 'POST', body: JSON.stringify({ backup_type: type, confirmation: 'BACKUP NOW' }) });
        setOpsConfirm('');
        await loadOperations();
        setMessage('Backup completed and verified.');
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Backup failed.');
    }
    finally {
        setOpsBusy(false);
    } };
    /** Verify a selected backup by re-reading all stored checksums. */ const verifyOpsBackup = async (id: number) => { setOpsBusy(true); try {
        await apiRequest(`/api/v1/seller/operations/backups/${id}/verify`, { method: 'POST' });
        await loadOperations();
        setMessage('Backup verification passed.');
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Backup verification failed.');
    }
    finally {
        setOpsBusy(false);
    } };
    /** Prepare a short-lived CLI restore authorization for one verified restore point. */ const prepareOpsRestore = async (id: number) => { setOpsBusy(true); try {
        const result = await apiRequest<{
            data: {
                command: string;
            };
        }>(`/api/v1/seller/operations/backups/${id}/restore-requests`, { method: 'POST', body: JSON.stringify({ scope: 'full', confirmation: 'PREPARE RESTORE' }) });
        setRestoreCommand(result.data.command);
        await loadOperations();
        setMessage('Restore command prepared for 30 minutes.');
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not prepare restore.');
    }
    finally {
        setOpsBusy(false);
    } };
    /** Apply backup retention after explicit operator confirmation. */ const pruneOps = async () => { if (opsConfirm !== 'PRUNE BACKUPS') {
        setError('Type PRUNE BACKUPS before applying retention.');
        return;
    } setOpsBusy(true); try {
        await apiRequest('/api/v1/seller/operations/backups/prune', { method: 'POST', body: JSON.stringify({ confirmation: 'PRUNE BACKUPS' }) });
        setOpsConfirm('');
        await loadOperations();
        setMessage('Backup retention completed.');
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Retention failed.');
    }
    finally {
        setOpsBusy(false);
    } };
    /** Define event ledger columns with category, severity, occurrence and latency context. */ const observabilityEventColumns: DataGridColumn<ObsEvent>[] = [{ id: 'time', header: 'Last seen', cell: r => new Date(r.last_seen_at).toLocaleString(), sortValue: r => new Date(r.last_seen_at).getTime() }, { id: 'workspace', header: 'Workspace', cell: r => r.workspace?.name ?? 'Platform', searchValue: r => r.workspace?.name ?? 'Platform' }, { id: 'category', header: 'Category', cell: r => <Badge tone="neutral">{r.category}</Badge>, filter: { type: 'select', options: ['runtime', 'request', 'query', 'queue', 'webhook', 'mail', 'storage', 'payment'].map(value => ({ value, label: value })) }, filterValue: r => r.category }, { id: 'severity', header: 'Severity', cell: r => <Badge tone={r.severity === 'critical' || r.severity === 'error' ? 'danger' : r.severity === 'warning' ? 'warning' : 'neutral'}>{r.severity}</Badge>, filter: { type: 'select', options: ['info', 'warning', 'error', 'critical'].map(value => ({ value, label: value })) }, filterValue: r => r.severity }, { id: 'event', header: 'Event', cell: r => <div><strong>{r.message}</strong><small>{r.event_type}{r.source ? ` · ${r.source}` : ''}</small></div>, searchValue: r => `${r.message} ${r.event_type} ${r.source ?? ''}` }, { id: 'count', header: 'Occurrences', cell: r => r.occurrence_count, sortValue: r => r.occurrence_count }, { id: 'duration', header: 'Latency', cell: r => r.duration_ms ? `${Number(r.duration_ms).toFixed(0)} ms` : '—', sortValue: r => Number(r.duration_ms ?? 0) }];
    /** Define active and historical alert incident columns with operator actions. */ const observabilityAlertColumns: DataGridColumn<ObsAlert>[] = [{ id: 'time', header: 'Triggered', cell: r => new Date(r.triggered_at).toLocaleString(), sortValue: r => new Date(r.triggered_at).getTime() }, { id: 'severity', header: 'Severity', cell: r => <Badge tone={r.severity === 'critical' || r.severity === 'error' ? 'danger' : r.severity === 'warning' ? 'warning' : 'neutral'}>{r.severity}</Badge>, filter: { type: 'select', options: ['warning', 'error', 'critical'].map(value => ({ value, label: value })) }, filterValue: r => r.severity }, { id: 'alert', header: 'Alert', cell: r => <div><strong>{r.title}</strong><small>{r.message}</small></div>, searchValue: r => `${r.title} ${r.message}` }, { id: 'status', header: 'Status', cell: r => <Badge tone={r.status === 'resolved' ? 'success' : r.status === 'acknowledged' ? 'warning' : 'danger'}>{r.status}</Badge>, filter: { type: 'select', options: ['open', 'acknowledged', 'resolved'].map(value => ({ value, label: value })) }, filterValue: r => r.status }, { id: 'actions', header: 'Actions', cell: r => <Inline gap={6}>{r.status === 'open' && <Button size="sm" variant="outline" onClick={() => void acknowledgeAlert(r.id)}>Acknowledge</Button>}{r.status !== 'resolved' && <Button size="sm" variant="outline" onClick={() => void resolveAlert(r.id)}>Resolve</Button>}</Inline> }];
    /** Define editable alert-rule columns while keeping metric identifiers server-owned. */ const observabilityRuleColumns: DataGridColumn<ObsRule>[] = [{ id: 'name', header: 'Rule', cell: r => <div><strong>{r.name}</strong><small>{r.metric_key}</small></div>, searchValue: r => `${r.name} ${r.metric_key}` }, { id: 'threshold', header: 'Threshold', cell: r => `${r.metric_key} ${r.operator} ${r.threshold}`, sortValue: r => Number(r.threshold) }, { id: 'window', header: 'Window', cell: r => `${r.window_minutes} min`, sortValue: r => r.window_minutes }, { id: 'severity', header: 'Severity', cell: r => <Badge tone={r.severity === 'critical' || r.severity === 'error' ? 'danger' : 'warning'}>{r.severity}</Badge> }, { id: 'status', header: 'Status', cell: r => <Badge tone={r.enabled ? 'success' : 'neutral'}>{r.enabled ? 'enabled' : 'disabled'}</Badge> }, { id: 'actions', header: 'Actions', cell: r => <Button size="sm" variant="outline" onClick={() => openRuleEditor(r)}><Settings2 size={13}/> Edit</Button> }];
    /** Define bounded failed-job metadata columns without exposing serialized payloads or exception bodies. */ const observabilityFailedJobColumns: DataGridColumn<ObsPayload['failed_jobs'][number]>[] = [{ id: 'failed_at', header: 'Failed at', cell: r => new Date(r.failed_at).toLocaleString(), sortValue: r => new Date(r.failed_at).getTime() }, { id: 'queue', header: 'Queue', cell: r => <strong>{r.queue}</strong>, searchValue: r => r.queue }, { id: 'connection', header: 'Connection', cell: r => r.connection, searchValue: r => r.connection }, { id: 'uuid', header: 'Job UUID', cell: r => <code>{r.uuid}</code>, searchValue: r => r.uuid }];
    if (!session?.user.platformOperator)
        return <Page><Alert tone="danger">Platform operator access is required.</Alert></Page>;
    if (initialLoading && !data)
        return <Page><PageHeader title="Seller Console" description="Global SaaS customers, subscriptions, payments, providers, pricing, coupons, taxes and revenue."/><LoadingState title="Loading Seller Console…" text="Preparing global commerce and subscription data."/></Page>;
    if (!data)
        return <Page><PageHeader title="Seller Console" description="Global SaaS customers, subscriptions, payments, providers, pricing, coupons, taxes and revenue."/><ErrorState title="Seller Console is unavailable" text={error || 'Global commerce data could not be loaded.'} retry={load}/></Page>;
    /** Save a seller payment provider and surface its automatic remote activation test result. */ const saveProvider = async (e: FormEvent) => { e.preventDefault(); setBusy(true); setError(''); setMessage(''); try {
        const response = await apiRequest<{
            data: Provider;
            activation_test?: {
                ok: boolean;
                message: string;
            } | null;
        }>(`/api/v1/seller/providers/${providerKey}`, { method: 'PUT', body: JSON.stringify({ ...providerForm, credentials: JSON.parse(providerForm.credentials || '{}'), settings: JSON.parse(providerForm.settings || '{}') }) });
        setProviderOpen(false);
        if (response.activation_test && !response.activation_test.ok) {
            setMessage('Payment provider settings saved.');
            setError(`Provider remains disabled: ${response.activation_test.message}`);
        }
        else if (response.activation_test?.ok) {
            setMessage('Payment provider saved, tested and enabled.');
        }
        else {
            setMessage('Payment provider saved.');
        }
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not save provider.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the test provider operation for the WorkIntel client. */ const testProvider = async (key: string) => { setBusy(true); try {
        await apiRequest(`/api/v1/seller/providers/${key}/test`, { method: 'POST' });
        setMessage(`${key} connection tested.`);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Provider test failed.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the create coupon operation for the WorkIntel client. */ const createCoupon = async (e: FormEvent) => { e.preventDefault(); setBusy(true); try {
        await apiRequest('/api/v1/seller/coupons', { method: 'POST', body: JSON.stringify({ code: couponForm.code, name: couponForm.name, discount_type: couponForm.discount_type, discount_value: Number(couponForm.discount_value), eligible_plans: couponForm.eligible_plans.split(',').map(x => x.trim()).filter(Boolean), max_redemptions: couponForm.max_redemptions ? Number(couponForm.max_redemptions) : null, active: true }) });
        setCouponOpen(false);
        setMessage('Coupon created.');
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create coupon.');
    }
    finally {
        setBusy(false);
    } };
    /** Open the seller settlement form for a manual or bank-transfer checkout. */ const openSettlement = (c: Checkout) => { setSettlingCheckout(c); setSettleReference(`BANK-${c.uuid.slice(0, 8).toUpperCase()}`); setSettleOpen(true); };
    /** Settle a seller checkout after an operator enters the verified payment reference. */ const settleCheckout = async (e: FormEvent) => { e.preventDefault(); if (!settlingCheckout || !settleReference.trim())
        return; setBusy(true); try {
        await apiRequest(`/api/v1/seller/checkouts/${settlingCheckout.id}/settle`, { method: 'POST', body: JSON.stringify({ reference: settleReference.trim() }) });
        setSettleOpen(false);
        setSettlingCheckout(null);
        setSettleReference('');
        setMessage('Checkout settled and subscription activated.');
        await load();
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not settle checkout.');
    }
    finally {
        setBusy(false);
    } };
    /** Open the refund form with the original payment amount prefilled. */ const openRefund = (tx: Transaction) => { setRefundingTransaction(tx); setRefundAmount(String(Math.abs(tx.amount))); setRefundReason('Seller Console refund'); setRefundOpen(true); };
    /** Submit a validated refund amount through the existing server-side refund ledger. */ const refundTransaction = async (e: FormEvent) => { e.preventDefault(); if (!refundingTransaction)
        return; const amount = Number(refundAmount); if (!Number.isFinite(amount) || amount <= 0) {
        setError('Enter a valid refund amount.');
        return;
    } setBusy(true); try {
        await apiRequest(`/api/v1/seller/transactions/${refundingTransaction.id}/refund`, { method: 'POST', body: JSON.stringify({ amount, reason: refundReason.trim() || 'Seller Console refund' }) });
        setRefundOpen(false);
        setRefundingTransaction(null);
        setMessage('Refund submitted.');
        await load();
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Refund failed.');
    }
    finally {
        setBusy(false);
    } };
    /** Open the full plan capability editor without mutating the live plan until save. */ const editPlan = (p: Plan) => { setEditingPlan(p); setPlanForm({ monthly: String(p.monthly_price_per_seat), annual: String(p.annual_price_per_seat), trial: String(p.trial_days), active: p.is_active, public: p.is_public, popular: Boolean(p.is_popular) }); setPlanValues(Object.fromEntries((p.entitlements ?? []).map(item => [item.key, item.value?.value]))); setPlanOpen(true); };
    /** Persist plan pricing, visibility and the seller-controlled entitlement matrix. */ const savePlan = async (e: FormEvent) => { e.preventDefault(); if (!editingPlan)
        return; setBusy(true); try {
        await apiRequest(`/api/v1/seller/plans/${editingPlan.id}`, { method: 'PATCH', body: JSON.stringify({ monthly_price_per_seat: Number(planForm.monthly), annual_price_per_seat: Number(planForm.annual), trial_days: Number(planForm.trial), is_active: planForm.active, is_public: planForm.public, is_popular: planForm.popular }) });
        await apiRequest(`/api/v1/seller/plans/${editingPlan.id}/entitlements`, { method: 'PUT', body: JSON.stringify({ entitlements: planValues }) });
        setPlanOpen(false);
        setMessage(`${editingPlan.name} plan capabilities updated.`);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not update plan capabilities.');
    }
    finally {
        setBusy(false);
    } };
    /** Open the add-on pricing editor without mutating live pricing until save. */ const openAddonEditor = (a: Addon) => { setEditingAddon(a); setAddonMonthlyPrice(String(a.monthly_price)); setAddonOpen(true); };
    /** Persist add-on monthly pricing from the dedicated seller editor. */ const editAddon = async (e: FormEvent) => { e.preventDefault(); if (!editingAddon)
        return; const monthly = Number(addonMonthlyPrice); if (!Number.isFinite(monthly) || monthly < 0) {
        setError('Enter a valid monthly add-on price.');
        return;
    } setBusy(true); try {
        await apiRequest(`/api/v1/seller/addons/${editingAddon.id}`, { method: 'PATCH', body: JSON.stringify({ monthly_price: monthly }) });
        setAddonOpen(false);
        setMessage(`${editingAddon.name} pricing updated.`);
        await load();
    }
    catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Could not update add-on.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the create tax operation for the WorkIntel client. */ const createTax = async (e: FormEvent) => { e.preventDefault(); setBusy(true); try {
        await apiRequest('/api/v1/seller/tax-rules', { method: 'POST', body: JSON.stringify({ name: taxForm.name, country: taxForm.country || null, state_region: taxForm.state_region || null, rate_percent: Number(taxForm.rate_percent), active: true, priority: Number(taxForm.priority) }) });
        setTaxOpen(false);
        setMessage('Tax rule created.');
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create tax rule.');
    }
    finally {
        setBusy(false);
    } };
    /** Define searchable, sortable seller customer columns using the shared DataGrid V2 engine. */ const customerColumns: DataGridColumn<Customer>[] = [{ id: 'workspace', header: 'Workspace', cell: r => <strong>{r.name}</strong>, searchValue: r => r.name, sortValue: r => r.name }, { id: 'slug', header: 'Slug', cell: r => r.slug, searchValue: r => r.slug }, { id: 'members', header: 'Members', cell: r => r.active_members_count, sortValue: r => r.active_members_count }, { id: 'plan', header: 'Plan', cell: r => r.subscription?.plan?.name ?? 'Free', searchValue: r => r.subscription?.plan?.name ?? 'Free' }, { id: 'subscription', header: 'Subscription', cell: r => <Badge tone={r.subscription?.status === 'active' ? 'success' : 'neutral'}>{r.subscription?.status ?? 'none'}</Badge>, filter: { type: 'select', options: [{ value: 'active', label: 'Active' }, { value: 'trialing', label: 'Trialing' }, { value: 'past_due', label: 'Past due' }, { value: 'canceled', label: 'Canceled' }, { value: 'none', label: 'None' }] }, filterValue: r => r.subscription?.status ?? 'none' }];
    /** Define seller coupon columns with common filtering and pagination controls. */ const couponColumns: DataGridColumn<Coupon>[] = [{ id: 'code', header: 'Code', cell: r => <strong>{r.code}</strong>, searchValue: r => r.code, sortValue: r => r.code }, { id: 'name', header: 'Name', cell: r => r.name, searchValue: r => r.name }, { id: 'discount', header: 'Discount', cell: r => r.discount_type === 'percent' ? `${r.discount_value}%` : money(r.discount_value), sortValue: r => r.discount_value }, { id: 'redemptions', header: 'Redemptions', cell: r => `${r.redeemed_count} / ${r.max_redemptions ?? '∞'}`, sortValue: r => r.redeemed_count }, { id: 'status', header: 'Status', cell: r => <Badge tone={r.active ? 'success' : 'neutral'}>{r.active ? 'active' : 'inactive'}</Badge>, filter: { type: 'select', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }] }, filterValue: r => r.active ? 'active' : 'inactive' }];
    /** Define seller tax-rule columns with status and region filtering. */ const taxColumns: DataGridColumn<Tax>[] = [{ id: 'name', header: 'Name', cell: r => <strong>{r.name}</strong>, searchValue: r => r.name }, { id: 'region', header: 'Country / Region', cell: r => r.country ? `${r.country}${r.state_region ? ` / ${r.state_region}` : ''}` : 'Global', searchValue: r => r.country ? `${r.country} ${r.state_region ?? ''}` : 'Global' }, { id: 'rate', header: 'Rate', cell: r => `${r.rate_percent}%`, sortValue: r => r.rate_percent }, { id: 'priority', header: 'Priority', cell: r => r.priority, sortValue: r => r.priority }, { id: 'status', header: 'Status', cell: r => <Badge tone={r.active ? 'success' : 'neutral'}>{r.active ? 'active' : 'inactive'}</Badge>, filter: { type: 'select', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }] }, filterValue: r => r.active ? 'active' : 'inactive' }];
    /** Define seller checkout columns including manual settlement actions. */ const checkoutColumns: DataGridColumn<Checkout>[] = [{ id: 'workspace', header: 'Workspace', cell: r => r.workspace?.name ?? '—', searchValue: r => r.workspace?.name }, { id: 'plan', header: 'Plan', cell: r => r.plan?.name ?? '—', searchValue: r => r.plan?.name }, { id: 'provider', header: 'Provider', cell: r => r.provider, filterValue: r => r.provider, filter: { type: 'select', options: [{ value: 'manual', label: 'Manual' }, { value: 'bank_transfer', label: 'Bank transfer' }, { value: 'stripe', label: 'Stripe' }, { value: 'paypal', label: 'PayPal' }, { value: 'paddle', label: 'Paddle' }, { value: 'custom_http', label: 'Custom HTTP' }] } }, { id: 'total', header: 'Total', cell: r => new Intl.NumberFormat(undefined, { style: 'currency', currency: r.currency }).format(r.total), sortValue: r => r.total }, { id: 'status', header: 'Status', cell: r => <Badge tone={r.status === 'completed' ? 'success' : r.status === 'canceled' ? 'neutral' : 'warning'}>{r.status}</Badge>, filterValue: r => r.status, filter: { type: 'select', options: [{ value: 'pending', label: 'Pending' }, { value: 'redirect', label: 'Redirect' }, { value: 'completed', label: 'Completed' }, { value: 'expired', label: 'Expired' }, { value: 'failed', label: 'Failed' }, { value: 'canceled', label: 'Canceled' }] } }, { id: 'actions', header: '', sortable: false, hideable: false, cell: r => ['manual', 'bank_transfer'].includes(r.provider) && ['pending', 'redirect', 'expired'].includes(r.status) ? <Button size="sm" variant="outline" loading={busy} onClick={() => openSettlement(r)}>Settle</Button> : null }];
    /** Define seller payment and refund ledger columns with guarded refund actions. */ const transactionColumns: DataGridColumn<Transaction>[] = [{ id: 'type', header: 'Type', cell: r => r.type, filterValue: r => r.type, filter: { type: 'select', options: [{ value: 'payment', label: 'Payment' }, { value: 'refund', label: 'Refund' }] } }, { id: 'provider', header: 'Provider', cell: r => r.provider, filterValue: r => r.provider, filter: { type: 'select', options: [{ value: 'manual', label: 'Manual' }, { value: 'bank_transfer', label: 'Bank transfer' }, { value: 'stripe', label: 'Stripe' }, { value: 'paypal', label: 'PayPal' }, { value: 'paddle', label: 'Paddle' }, { value: 'custom_http', label: 'Custom HTTP' }] } }, { id: 'amount', header: 'Amount', cell: r => new Intl.NumberFormat(undefined, { style: 'currency', currency: r.currency }).format(r.amount), sortValue: r => r.amount }, { id: 'status', header: 'Status', cell: r => <Badge tone={r.status === 'succeeded' ? 'success' : r.status === 'failed' ? 'danger' : 'warning'}>{r.status}</Badge>, filterValue: r => r.status, filter: { type: 'select', options: [{ value: 'pending', label: 'Pending' }, { value: 'succeeded', label: 'Succeeded' }, { value: 'failed', label: 'Failed' }] } }, { id: 'reference', header: 'Reference', cell: r => r.provider_transaction_id ?? '—', searchValue: r => r.provider_transaction_id }, { id: 'actions', header: '', sortable: false, hideable: false, cell: r => r.type === 'payment' && r.status === 'succeeded' && r.amount > 0 ? <Button size="sm" variant="outline" loading={busy} onClick={() => openRefund(r)}>Refund</Button> : null }];
    /** Define failed-payment dunning columns for operator search and review. */ const dunningColumns: DataGridColumn<Dunning>[] = [{ id: 'plan', header: 'Plan', cell: r => r.subscription?.plan?.name ?? '—', searchValue: r => r.subscription?.plan?.name }, { id: 'attempt', header: 'Attempt', cell: r => `#${r.attempt_number}`, sortValue: r => r.attempt_number }, { id: 'status', header: 'Status', cell: r => <Badge tone={r.status === 'scheduled' ? 'warning' : 'neutral'}>{r.status}</Badge>, filterValue: r => r.status, filter: { type: 'select', options: [{ value: 'scheduled', label: 'Scheduled' }, { value: 'processing', label: 'Processing' }, { value: 'resolved', label: 'Resolved' }, { value: 'failed', label: 'Failed' }] } }, { id: 'next', header: 'Next attempt', cell: r => r.next_attempt_at ? new Date(r.next_attempt_at).toLocaleString() : '—', sortValue: r => r.next_attempt_at ?? '' }, { id: 'failure', header: 'Failure', cell: r => r.failure_message ?? '—', searchValue: r => r.failure_message }];
    /** Define the immutable restore-point DataGrid used by the Operations Center. */ const backupColumns: DataGridColumn<BackupRun>[] = [
        { id: 'created_at', header: 'Created', cell: r => new Date(r.created_at).toLocaleString(), sortValue: r => r.created_at, searchValue: r => r.created_at },
        { id: 'backup_type', header: 'Type', cell: r => r.backup_type, sortValue: r => r.backup_type, searchValue: r => r.backup_type },
        { id: 'status', header: 'Status', filterValue: r => r.status, searchValue: r => r.status, cell: r => <Badge tone={r.status === 'verified' ? 'success' : r.status === 'failed' ? 'danger' : 'neutral'}>{r.status}</Badge> },
        { id: 'size_bytes', header: 'Size', cell: r => `${(r.size_bytes / 1024 / 1024).toFixed(1)} MB`, sortValue: r => r.size_bytes },
        { id: 'file_count', header: 'Files', cell: r => r.file_count, sortValue: r => r.file_count },
        { id: 'actions', header: 'Actions', cell: r => <Inline gap={5}><Button size="sm" variant="outline" onClick={() => void verifyOpsBackup(r.id)} disabled={opsBusy}>Verify</Button>{r.status === 'verified' && <Button size="sm" variant="ghost" onClick={() => void prepareOpsRestore(r.id)} disabled={opsBusy}><ArchiveRestore size={12}/> Restore</Button>}</Inline> },
    ];
    return <Page><PageHeader title="Seller Console" description="Global SaaS customers, subscriptions, payments, providers, pricing, coupons, taxes and revenue." actions={<Button variant="outline" size="sm" onClick={() => void load()}><RefreshCw size={13}/> Refresh</Button>}/>{error && <Alert tone="danger" mb={10}>{error}</Alert>}{message && <Alert tone="success" mb={10}>{message}</Alert>}
 <Tabs value={tab} onChange={setTab} tabs={[{ value: 'overview', label: 'Overview' }, { value: 'customers', label: 'Customers' }, { value: 'providers', label: 'Payment Providers' }, { value: 'plans', label: 'Plans' }, { value: 'addons', label: 'Add-ons' }, { value: 'coupons', label: 'Coupons' }, { value: 'taxes', label: 'Taxes' }, { value: 'checkouts', label: 'Checkouts' }, { value: 'transactions', label: 'Payments & Refunds' }, { value: 'dunning', label: 'Failed Payments' }, { value: 'operations', label: 'Operations & Recovery' }, { value: 'observability', label: 'Observability' }, { value: 'security', label: 'Security' }]}/>
 {tab === 'overview' && data && <><Grid columns="repeat(auto-fit,minmax(170px,1fr))" gap={10} mt={12}>{[['Customers', data.summary.customers], ['Paid subscriptions', data.summary.paid_subscriptions], ['MRR', money(data.summary.mrr)], ['ARR', money(data.summary.arr)], ['Collected this month', money(data.summary.month_collected)], ['Churn', `${data.summary.churn_percent}%`], ['Open invoices', data.summary.open_invoices], ['Pending checkouts', data.summary.pending_checkouts]].map(([k, v]) => <Card key={String(k)}><CardBody><div className="ui-card-description">{k}</div><Box className="stat-num" size={22} weight={750} mt={5}>{v}</Box></CardBody></Card>)}</Grid><Card mt={12}><CardHeader title="Commerce boundary" description="Seller Console is global platform-operator access; workspace Owners cannot enumerate other customers."/><CardBody><Inline gap={8} align="center"><ShieldCheck size={17}/><span className="ui-card-description">Operator: {session.user.email}</span></Inline></CardBody></Card></>}
 {tab === 'customers' && <Box mt={12}><DataGrid rows={customers} columns={customerColumns} rowKey={r => r.id} persistKey="seller-customers" searchable searchPlaceholder="Search customers…" pageSizeOptions={[10, 25, 50, 100]}/></Box>}
 {tab === 'providers' && data && <Box mt={12}><Inline justify="flex-end" mb={10}><Button onClick={() => setProviderOpen(true)}><Settings2 size={13}/> Configure Provider</Button></Inline>{data.providers.length ? <Grid columns="repeat(auto-fit,minmax(270px,1fr))" gap={10}>{data.providers.map(p => <Card key={p.provider}><CardHeader title={p.display_name} description={p.provider} action={p.is_default ? <Badge tone="accent">Default</Badge> : undefined}/><CardBody><Inline gap={6} mb={9}><Badge tone={p.enabled ? 'success' : 'neutral'}>{p.enabled ? 'enabled' : 'disabled'}</Badge><Badge tone={p.health_status === 'healthy' ? 'success' : p.health_status === 'failed' ? 'danger' : 'neutral'}>{p.health_status}</Badge><Badge>{p.test_mode ? 'test' : 'live'}</Badge></Inline><Button size="sm" variant="outline" loading={busy} onClick={() => void testProvider(p.provider)}>Test Connection</Button></CardBody></Card>)}</Grid> : <EmptyState title="No payment providers configured" text="Configure a provider to make subscription checkout available."/>}</Box>}
 {tab === 'plans' && data && <Box mt={12}>{data.plans.length ? <Grid columns="repeat(auto-fit,minmax(240px,1fr))" gap={10}>{data.plans.map(p => <Card key={p.id}><CardHeader title={p.name} description={p.description}/><CardBody><div className="stat-num">{money(p.monthly_price_per_seat)} / seat / mo</div><Box className="ui-card-description" mt={5}>{money(p.annual_price_per_seat)} annual · {p.trial_days} trial days · {p.subscriptions_count} subscriptions</Box><Inline gap={6} mt={9} align="center"><Badge tone={p.is_active ? 'success' : 'neutral'}>{p.is_active ? 'active' : 'inactive'}</Badge><Badge>{p.is_public ? 'public' : 'private'}</Badge><Button size="sm" variant="outline" loading={busy} onClick={() => editPlan(p)}>Edit plan</Button></Inline></CardBody></Card>)}</Grid> : <EmptyState title="No subscription plans" text="Create or seed a plan before customers can subscribe."/>}</Box>}
 {tab === 'addons' && data && <Box mt={12}>{data.addons.length ? <Grid columns="repeat(auto-fit,minmax(240px,1fr))" gap={10}>{data.addons.map(a => <Card key={a.id}><CardHeader title={a.name} description={a.description}/><CardBody><div className="stat-num">{money(a.monthly_price)} / month</div><Box className="ui-card-description" mt={5}>{a.pricing_mode} · {a.subscriptions_count} subscriptions</Box><Box mt={9}><Button size="sm" variant="outline" loading={busy} onClick={() => openAddonEditor(a)}>Edit price</Button></Box></CardBody></Card>)}</Grid> : <EmptyState title="No add-ons available" text="Optional paid capabilities will appear here after they are configured."/>}</Box>}
 {tab === 'coupons' && data && <Box mt={12}><Inline justify="flex-end" mb={10}><Button onClick={() => setCouponOpen(true)}><TicketPercent size={13}/> New Coupon</Button></Inline><DataGrid rows={data.coupons} columns={couponColumns} rowKey={r => r.id} persistKey="seller-coupons" searchable searchPlaceholder="Search coupons…"/></Box>}
 {tab === 'taxes' && data && <Box mt={12}><Inline justify="flex-end" mb={10}><Button onClick={() => setTaxOpen(true)}><Percent size={13}/> New Tax Rule</Button></Inline><DataGrid rows={data.tax_rules} columns={taxColumns} rowKey={r => r.id} persistKey="seller-taxes" searchable searchPlaceholder="Search tax rules…"/></Box>}
 {tab === 'checkouts' && data && <Box mt={12}><DataGrid rows={data.recent_checkouts} columns={checkoutColumns} rowKey={r => r.id} persistKey="seller-checkouts" searchable searchPlaceholder="Search checkouts…"/></Box>}
 {tab === 'transactions' && data && <Box mt={12}><DataGrid rows={data.recent_transactions} columns={transactionColumns} rowKey={r => r.id} persistKey="seller-transactions" searchable searchPlaceholder="Search payments and refunds…"/></Box>}
 {tab === 'dunning' && data && <Box mt={12}><DataGrid rows={data.dunning_attempts} columns={dunningColumns} rowKey={r => r.id} persistKey="seller-dunning" searchable searchPlaceholder="Search failed payments…"/></Box>}

 {tab === 'operations' && <Stack mt={12} gap={12}>
  {!ops && <LoadingState title="Loading production operations…" text="Reading backup, scheduler, queue and storage health."/>}
  {ops && <>
   <Grid columns="repeat(auto-fit,minmax(180px,1fr))" gap={10}>
    <Card><CardBody><div className="ui-card-description">Scheduler</div><Box mt={6}><Badge tone={ops.health.scheduler.ok ? 'success' : 'danger'}>{ops.health.scheduler.ok ? 'Healthy' : 'Stale'}</Badge></Box><small>{ops.health.scheduler.last_seen_at ? new Date(ops.health.scheduler.last_seen_at).toLocaleString() : 'No heartbeat'}</small></CardBody></Card>
    <Card><CardBody><div className="ui-card-description">Verified backup</div><Box mt={6}><Badge tone={ops.health.backup.ok ? 'success' : 'warning'}>{ops.health.backup.ok ? 'Fresh' : 'Needs attention'}</Badge></Box><small>{ops.health.backup.latest_verified_at ? new Date(ops.health.backup.latest_verified_at).toLocaleString() : 'No verified restore point'}</small></CardBody></Card>
    <Card><CardBody><div className="ui-card-description">Backup storage</div><Box mt={6}><Badge tone={ops.health.storage.writable ? 'success' : 'danger'}>{ops.health.storage.writable ? 'Writable' : 'Unavailable'}</Badge></Box><small>{ops.health.storage.disk}</small></CardBody></Card>
    <Card><CardBody><div className="ui-card-description">Queue</div><Box mt={6}><Badge tone={ops.health.queue.failed_jobs_table ? 'success' : 'warning'}>{ops.health.queue.connection}</Badge></Box><small>{ops.health.queue.failed_jobs_table ? 'Failed-job ledger available' : 'Failed-job ledger missing'}</small></CardBody></Card>
   </Grid>
   <Card><CardHeader title="Backup policy" description="Database backups are always included. Full backups also snapshot configured private application storage."/><CardBody><Form onSubmit={saveOpsPolicy} className="ui-form-stack"><div className="ui-form-grid-2"><Field label="Frequency"><Select value={opsPolicy?.frequency ?? 'daily'} onChange={e => setOpsPolicy(v => v ? { ...v, frequency: e.target.value as 'daily' | 'weekly' } : v)}><Option value="daily">Daily</Option><Option value="weekly">Weekly (Monday)</Option></Select></Field><Field label="Run time"><Input type="time" value={opsPolicy?.run_time ?? '02:00'} onChange={e => setOpsPolicy(v => v ? { ...v, run_time: e.target.value } : v)}/></Field><Field label="Retention days"><Input type="number" min={2} max={3650} value={opsPolicy?.retention_days ?? 14} onChange={e => setOpsPolicy(v => v ? { ...v, retention_days: Number(e.target.value) } : v)}/></Field><Field label="Minimum verified copies"><Input type="number" min={1} max={30} value={opsPolicy?.minimum_verified_copies ?? 2} onChange={e => setOpsPolicy(v => v ? { ...v, minimum_verified_copies: Number(e.target.value) } : v)}/></Field><Field label="Backup disk"><Input value={opsPolicy?.disk ?? 'local'} onChange={e => setOpsPolicy(v => v ? { ...v, disk: e.target.value } : v)}/></Field><Field label="Private storage"><Switch checked={Boolean(opsPolicy?.include_private_storage)} onChange={checked => setOpsPolicy(v => v ? { ...v, include_private_storage: checked } : v)} label="Include private files"/></Field></div><Button type="submit" loading={opsBusy}><Settings2 size={13}/> Save policy</Button></Form></CardBody></Card>
   <Card><CardHeader title="Manual recovery operations" description="Destructive actions require explicit typed confirmation. Restore execution is CLI-only and time-limited."/><CardBody><div className="ui-form-stack"><Field label="Confirmation"><Input value={opsConfirm} onChange={e => setOpsConfirm(e.target.value)} placeholder="BACKUP NOW or PRUNE BACKUPS"/></Field><Inline gap={7} wrap="wrap"><Button onClick={() => void runOpsBackup('full')} loading={opsBusy}><DatabaseBackup size={13}/> Full backup</Button><Button variant="outline" onClick={() => void runOpsBackup('database')} loading={opsBusy}><HardDriveDownload size={13}/> Database only</Button><Button variant="outline" onClick={() => void pruneOps()} loading={opsBusy}><TriangleAlert size={13}/> Apply retention</Button></Inline>{restoreCommand && <Alert tone="warning"><strong>One-time restore command</strong><Box mt={6}><Box as="code" wordBreak="break-all">{restoreCommand}</Box></Box></Alert>}</div></CardBody></Card>
   <Card><CardHeader title="Verified restore points" description="Checksums are verified from the configured backup disk before a restore authorization can be created."/><CardBody><DataGrid rows={ops.backups} columns={backupColumns} rowKey={r => r.id} persistKey="seller-operations-backups" searchable searchPlaceholder="Search backups…"/></CardBody></Card>
   <Card><CardHeader title="Operations audit" description="Backup, verification, retention and restore preparation events are immutable operational history."/><CardBody><Stack gap={6}>{ops.events.slice(0, 25).map(event => <div className="schedule-list-row" key={event.id}><div><strong>{event.message}</strong><small>{event.event_type} · {new Date(event.occurred_at).toLocaleString()}</small></div><Badge tone={event.severity === 'critical' ? 'danger' : event.severity === 'warning' ? 'warning' : 'neutral'}>{event.severity}</Badge></div>)}{!ops.events.length && <EmptyState title="No operations events yet."/>}</Stack></CardBody></Card>
  </>}
 </Stack>}



 {tab === 'observability' && !observability && <Box mt={12}><LoadingState title="Loading observability…" text="Reading platform health, alerts and recent runtime events."/></Box>}
 {tab === 'observability' && observability && <Stack mt={12} gap={12}>
  <Inline justify="space-between" gap={8} wrap="wrap"><div><strong>Observability & Audit Operations</strong><div className="ui-card-description">Centralized runtime, queue, request, query, webhook, storage and payment health without storing request bodies or credentials.</div></div><Inline gap={7} wrap="wrap"><Button variant="outline" onClick={() => void loadObservability()}><RefreshCw size={13}/> Refresh</Button><Button variant="outline" onClick={downloadDiagnostics}><Download size={13}/> Diagnostics bundle</Button><Button onClick={() => void evaluateObservability()} loading={obsBusy}><Gauge size={13}/> Evaluate alerts</Button></Inline></Inline>
  <>
   <div className="seller-observability-summary">{[{ label: 'Open alerts', value: observability.summary.open_alerts, icon: <BellRing size={15}/> }, { label: 'Critical', value: observability.summary.critical_alerts, icon: <TriangleAlert size={15}/> }, { label: 'Errors · 15m', value: observability.summary.errors_15m, icon: <Activity size={15}/> }, { label: 'Slow requests · 15m', value: observability.summary.slow_requests_15m, icon: <Gauge size={15}/> }, { label: 'Failed jobs · 60m', value: observability.summary.failed_jobs_60m, icon: <TriangleAlert size={15}/> }, { label: 'Failed webhooks · 60m', value: observability.summary.failed_webhooks_60m, icon: <Activity size={15}/> }, { label: 'Payment failures · 60m', value: observability.summary.payment_failures_60m, icon: <TriangleAlert size={15}/> }, { label: 'Storage failures · 60m', value: observability.summary.storage_failures_60m, icon: <HardDriveDownload size={15}/> }].map(item => <Card key={item.label}><CardBody><div className="seller-observability-stat">{item.icon}<span>{item.label}</span></div><strong className="seller-observability-number">{String(item.value)}</strong></CardBody></Card>)}</div>
   <Card><CardHeader title="Subsystem heartbeats" description="Scheduler and queue freshness are persisted independently from application request traffic."/><CardBody><div className="seller-heartbeat-grid">{observability.heartbeats.map(item => { const age = Math.max(0, Math.floor((Date.now() - new Date(item.last_seen_at).getTime()) / 1000)); const healthy = age <= item.expected_interval_seconds * 3; return <div className="seller-heartbeat" key={item.key}><span className={healthy ? 'is-healthy' : 'is-stale'}>{healthy ? <CheckCircle2 size={14}/> : <TriangleAlert size={14}/>}</span><div><strong>{item.key}</strong><small>{age}s ago · expected every {item.expected_interval_seconds}s</small></div></div>; })}{!observability.heartbeats.length && <EmptyState title="No subsystem heartbeat has been recorded yet."/>}</div></CardBody></Card>
   <Card><CardHeader title="Alert incidents" description="Acknowledge an incident when owned; resolve it only after the underlying condition has recovered."/><CardBody><DataGrid rows={observability.alerts} columns={observabilityAlertColumns} rowKey={r => r.id} persistKey="seller-observability-alerts" searchable searchPlaceholder="Search incidents…"/></CardBody></Card>
   <Card><CardHeader title="Event ledger" description="Repeated fingerprints are aggregated to reduce alert noise while retaining occurrence counts and maximum latency."/><CardBody><DataGrid rows={observability.events} columns={observabilityEventColumns} rowKey={r => r.id} persistKey="seller-observability-events" searchable searchPlaceholder="Search runtime events…" pageSizeOptions={[25, 50, 100]}/></CardBody></Card>
   <Card><CardHeader title="Failed queue jobs" description="Only safe job identifiers, queue, connection and failure time are shown; serialized payloads and exception bodies are deliberately excluded."/><CardBody><DataGrid rows={observability.failed_jobs} columns={observabilityFailedJobColumns} rowKey={r => r.id} persistKey="seller-observability-failed-jobs" searchable searchPlaceholder="Search failed jobs…"/></CardBody></Card>
   <Card><CardHeader title="Alert rules" description="Metric identifiers are fixed by the platform; operators can tune thresholds, severity, cooldown and optional email delivery."/><CardBody><DataGrid rows={observability.rules} columns={observabilityRuleColumns} rowKey={r => r.id} persistKey="seller-observability-rules" searchable searchPlaceholder="Search alert rules…"/></CardBody></Card>
  </>
 </Stack>}


 {tab === 'security' && !securityPosture && <Box mt={12}><LoadingState title="Loading security posture…" text="Checking platform controls without exposing secret values."/></Box>}
 {tab === 'security' && securityPosture && <Stack mt={12} gap={12}>
  <Inline justify="space-between" gap={8} wrap="wrap"><div><strong>Security Production Hardening</strong><div className="ui-card-description">CSP, session, MFA, API-key, rate-limit and upload security posture. Secret values are never returned by this endpoint.</div></div><Button variant="outline" onClick={() => void loadSecurityPosture()}><RefreshCw size={13}/> Refresh</Button></Inline>
  <>
   <Grid columns="repeat(auto-fit,minmax(170px,1fr))" gap={10}><Card><CardBody><div className="ui-card-description">Security score</div><strong className="seller-observability-number">{securityPosture.score}/100</strong></CardBody></Card><Card><CardBody><div className="ui-card-description">Active API keys</div><strong className="seller-observability-number">{securityPosture.api_keys.active}</strong><small>{securityPosture.api_keys.stale} rotation warning(s)</small></CardBody></Card><Card><CardBody><div className="ui-card-description">Open security events</div><strong className="seller-observability-number">{securityPosture.security_events.open}</strong><small>{securityPosture.security_events.high_open} high/critical</small></CardBody></Card><Card><CardBody><div className="ui-card-description">MFA enrolled</div><strong className="seller-observability-number">{securityPosture.mfa_methods}</strong><small>{securityPosture.workspace_policies.mfa_required} workspace policies require MFA</small></CardBody></Card></Grid>
   <Card><CardHeader title="Production security checks" description="Warnings identify configuration that should be resolved before public production exposure."/><CardBody><Stack gap={7}>{securityPosture.checks.map(check => <div className="schedule-list-row" key={check.key}><div><strong>{check.label}</strong><small>{check.ok ? 'Configured' : check.recommendation}</small></div><Badge tone={check.ok ? 'success' : 'warning'}>{check.ok ? 'PASS' : 'ACTION'}</Badge></div>)}</Stack></CardBody></Card>
   <Card><CardHeader title="Upload & API protection" description="All uploads use server-side byte MIME inspection; detected malware is quarantined and cannot be served or downloaded."/><CardBody><div className="ui-form-grid-2"><div><strong>Malware scanner</strong><div className="ui-card-description">{securityPosture.upload_security.driver} · {securityPosture.upload_security.required ? 'required' : 'best effort'}</div></div><div><strong>API key rotation</strong><div className="ui-card-description">Rotation issues a successor secret and atomically revokes the previous credential.</div></div></div></CardBody></Card>
   <Card><CardHeader title="Rate-limit matrix" description="Sensitive public operations use named server-side throttles keyed by account/IP context."/><CardBody><Grid columns="repeat(auto-fit,minmax(210px,1fr))" gap={8}>{Object.entries(securityPosture.rate_limits).map(([key, value]) => <div className="schedule-list-row" key={key}><span>{key.replaceAll('_', ' ')}</span><Badge>{value}/min</Badge></div>)}</Grid></CardBody></Card>
  </>
 </Stack>}

 <Modal open={ruleOpen} onClose={() => !obsBusy && setRuleOpen(false)} title={editingRule ? `Edit ${editingRule.name}` : 'Edit observability rule'} description={editingRule ? `Metric: ${editingRule.metric_key}` : 'Tune the platform-owned threshold.'} footer={<><Button variant="outline" onClick={() => setRuleOpen(false)}>Cancel</Button><Button form="observability-rule-form" type="submit" loading={obsBusy}>Save rule</Button></>}><Form id="observability-rule-form" onSubmit={saveObservabilityRule} className="ui-form-stack"><div className="ui-form-grid-2"><Field label="Operator"><Select value={ruleForm.operator} onChange={e => setRuleForm({ ...ruleForm, operator: e.target.value })}><Option value=">=">≥</Option><Option value=">">&gt;</Option><Option value="<=">≤</Option><Option value="<">&lt;</Option><Option value="==">=</Option></Select></Field><Field label="Threshold"><Input type="number" min="0" step="0.001" value={ruleForm.threshold} onChange={e => setRuleForm({ ...ruleForm, threshold: e.target.value })}/></Field><Field label="Window minutes"><Input type="number" min="1" max="10080" value={ruleForm.window_minutes} onChange={e => setRuleForm({ ...ruleForm, window_minutes: e.target.value })}/></Field><Field label="Cooldown minutes"><Input type="number" min="1" max="10080" value={ruleForm.cooldown_minutes} onChange={e => setRuleForm({ ...ruleForm, cooldown_minutes: e.target.value })}/></Field><Field label="Severity"><Select value={ruleForm.severity} onChange={e => setRuleForm({ ...ruleForm, severity: e.target.value })}><Option value="warning">Warning</Option><Option value="error">Error</Option><Option value="critical">Critical</Option></Select></Field><Field label="Delivery"><Stack gap={8}><Switch checked={ruleForm.enabled} onChange={enabled => setRuleForm({ ...ruleForm, enabled })} label="Rule enabled"/><Switch checked={ruleForm.email} onChange={email => setRuleForm({ ...ruleForm, email })} label="Email configured operators"/></Stack></Field></div></Form></Modal>

 <Modal open={settleOpen} onClose={() => !busy && setSettleOpen(false)} title="Settle subscription checkout" description="Record the verified bank or manual settlement reference before activating the subscription." footer={<><Button variant="outline" onClick={() => setSettleOpen(false)}>Cancel</Button><Button form="seller-settlement-form" type="submit" loading={busy}>Confirm settlement</Button></>}><Form id="seller-settlement-form" onSubmit={settleCheckout} className="ui-form-stack"><Field label="Workspace"><Input value={settlingCheckout?.workspace?.name ?? '—'} readOnly/></Field><Field label="Plan"><Input value={settlingCheckout?.plan?.name ?? '—'} readOnly/></Field><Field label="Payment reference"><Input value={settleReference} onChange={e => setSettleReference(e.target.value)} required autoFocus/></Field></Form></Modal>
 <Modal open={refundOpen} onClose={() => !busy && setRefundOpen(false)} title="Refund transaction" description="Refunds are written to the commerce ledger and remain auditable." footer={<><Button variant="outline" onClick={() => setRefundOpen(false)}>Cancel</Button><Button form="seller-refund-form" type="submit" loading={busy}>Submit refund</Button></>}><Form id="seller-refund-form" onSubmit={refundTransaction} className="ui-form-stack"><Field label={`Refund amount${refundingTransaction ? ` (${refundingTransaction.currency})` : ''}`}><Input type="number" min="0.01" step="0.01" value={refundAmount} onChange={e => setRefundAmount(e.target.value)} required/></Field><Field label="Reason"><Textarea rows={3} value={refundReason} onChange={e => setRefundReason(e.target.value)} required/></Field></Form></Modal>
 <Modal open={addonOpen} onClose={() => !busy && setAddonOpen(false)} title={editingAddon ? `Edit ${editingAddon.name}` : 'Edit add-on'} description="Update add-on pricing without using browser prompts." footer={<><Button variant="outline" onClick={() => setAddonOpen(false)}>Cancel</Button><Button form="seller-addon-form" type="submit" loading={busy}>Save pricing</Button></>}><Form id="seller-addon-form" onSubmit={editAddon} className="ui-form-stack"><Field label="Monthly add-on price"><Input type="number" min="0" step="0.01" value={addonMonthlyPrice} onChange={e => setAddonMonthlyPrice(e.target.value)} required/></Field></Form></Modal>
 <Modal open={planOpen} onClose={() => !busy && setPlanOpen(false)} title={editingPlan ? `Edit ${editingPlan.name} plan` : 'Edit plan'} description="Pricing and capability changes apply to plan entitlements without being reset by routine seed runs." size="lg" footer={<><Button variant="outline" onClick={() => setPlanOpen(false)}>Cancel</Button><Button form="seller-plan-form" type="submit" loading={busy}>Save plan</Button></>}><Form id="seller-plan-form" onSubmit={savePlan} className="ui-form-stack"><div className="ui-form-grid-2"><Field label="Monthly price / seat"><Input type="number" min="0" step="0.01" value={planForm.monthly} onChange={e => setPlanForm({ ...planForm, monthly: e.target.value })}/></Field><Field label="Annual price / seat"><Input type="number" min="0" step="0.01" value={planForm.annual} onChange={e => setPlanForm({ ...planForm, annual: e.target.value })}/></Field><Field label="Trial days"><Input type="number" min="0" max="365" value={planForm.trial} onChange={e => setPlanForm({ ...planForm, trial: e.target.value })}/></Field><Field label="Availability"><div className="seller-plan-toggles"><Switch checked={planForm.active} onChange={v => setPlanForm({ ...planForm, active: v })} label="Active"/><Switch checked={planForm.public} onChange={v => setPlanForm({ ...planForm, public: v })} label="Public"/><Switch checked={planForm.popular} onChange={v => setPlanForm({ ...planForm, popular: v })} label="Popular"/></div></Field></div><div className="seller-capability-matrix">{['features', 'limits'].map(group => <section key={group}><h3>{group === 'features' ? 'Features' : 'Limits & quotas'}</h3>{(data?.capability_catalog ?? []).filter(c => c.category === group).map(c => <div key={c.key} className="seller-capability-row"><div><strong>{c.label}</strong><small>{c.key}</small></div>{c.value_type === 'boolean' ? <Switch checked={Boolean(planValues[c.key])} onChange={v => setPlanValues(current => ({ ...current, [c.key]: v }))} label={Boolean(planValues[c.key]) ? 'Enabled' : 'Disabled'}/> : <Input type="number" value={String(planValues[c.key] ?? 0)} onChange={e => setPlanValues(current => ({ ...current, [c.key]: Number(e.target.value) }))}/>}</div>)}</section>)}</div></Form></Modal>
 <Modal open={providerOpen} onClose={() => setProviderOpen(false)} title="Configure payment provider" footer={<><Button variant="outline" onClick={() => setProviderOpen(false)}>Cancel</Button><Button form="provider-form" type="submit" loading={busy}>Save</Button></>}><Form id="provider-form" onSubmit={saveProvider} gap={9}><Field label="Provider"><Select value={providerKey} onChange={e => { const key = e.target.value; setProviderKey(key); setProviderForm({ ...providerForm, display_name: data?.provider_catalog.find(p => p.key === key)?.name ?? key }); }}>{data?.provider_catalog.map(p => <Option key={p.key} value={p.key}>{p.name}</Option>)}</Select></Field><Field label="Display name"><Input value={providerForm.display_name} onChange={e => setProviderForm({ ...providerForm, display_name: e.target.value })}/></Field><Grid columns="1fr 1fr" gap={8}><Field label="Enabled"><Select value={providerForm.enabled ? 'yes' : 'no'} onChange={e => setProviderForm({ ...providerForm, enabled: e.target.value === 'yes' })}><Option value="yes">Yes</Option><Option value="no">No</Option></Select></Field><Field label="Mode"><Select value={providerForm.test_mode ? 'test' : 'live'} onChange={e => setProviderForm({ ...providerForm, test_mode: e.target.value === 'test' })}><Option value="test">Test / Sandbox</Option><Option value="live">Live</Option></Select></Field></Grid><Field label="Default provider"><Select value={providerForm.is_default ? 'yes' : 'no'} onChange={e => setProviderForm({ ...providerForm, is_default: e.target.value === 'yes' })}><Option value="yes">Yes</Option><Option value="no">No</Option></Select></Field><Field label="Credentials JSON"><Textarea rows={5} value={providerForm.credentials} onChange={e => setProviderForm({ ...providerForm, credentials: e.target.value })}/></Field><Field label="Settings JSON"><Textarea rows={7} value={providerForm.settings} onChange={e => setProviderForm({ ...providerForm, settings: e.target.value })}/></Field></Form></Modal>
 <Modal open={couponOpen} onClose={() => setCouponOpen(false)} title="Create coupon" footer={<><Button variant="outline" onClick={() => setCouponOpen(false)}>Cancel</Button><Button form="coupon-form" type="submit" loading={busy}>Create</Button></>}><Form id="coupon-form" onSubmit={createCoupon} gap={9}><Field label="Code"><Input value={couponForm.code} onChange={e => setCouponForm({ ...couponForm, code: e.target.value.toUpperCase() })}/></Field><Field label="Name"><Input value={couponForm.name} onChange={e => setCouponForm({ ...couponForm, name: e.target.value })}/></Field><Grid columns="1fr 1fr" gap={8}><Field label="Type"><Select value={couponForm.discount_type} onChange={e => setCouponForm({ ...couponForm, discount_type: e.target.value })}><Option value="percent">Percent</Option><Option value="fixed">Fixed</Option></Select></Field><Field label="Value"><Input type="number" min="0.01" step="0.01" value={couponForm.discount_value} onChange={e => setCouponForm({ ...couponForm, discount_value: e.target.value })}/></Field></Grid><Field label="Eligible plan slugs"><Input value={couponForm.eligible_plans} onChange={e => setCouponForm({ ...couponForm, eligible_plans: e.target.value })}/></Field><Field label="Max redemptions"><Input type="number" min="1" value={couponForm.max_redemptions} onChange={e => setCouponForm({ ...couponForm, max_redemptions: e.target.value })}/></Field></Form></Modal>
 <Modal open={taxOpen} onClose={() => setTaxOpen(false)} title="Create tax rule" footer={<><Button variant="outline" onClick={() => setTaxOpen(false)}>Cancel</Button><Button form="tax-form" type="submit" loading={busy}>Create</Button></>}><Form id="tax-form" onSubmit={createTax} gap={9}><Field label="Name"><Input value={taxForm.name} onChange={e => setTaxForm({ ...taxForm, name: e.target.value })}/></Field><Field label="Country code"><Input maxLength={2} placeholder="AE or blank for global" value={taxForm.country} onChange={e => setTaxForm({ ...taxForm, country: e.target.value.toUpperCase() })}/></Field><Field label="State / region"><Input placeholder="Dubai, California, Ontario…" value={taxForm.state_region} onChange={e => setTaxForm({ ...taxForm, state_region: e.target.value })}/></Field><Field label="Rate %"><Input type="number" min="0" max="100" step="0.0001" value={taxForm.rate_percent} onChange={e => setTaxForm({ ...taxForm, rate_percent: e.target.value })}/></Field><Field label="Priority"><Input type="number" min="0" value={taxForm.priority} onChange={e => setTaxForm({ ...taxForm, priority: e.target.value })}/></Field></Form></Modal>
 </Page>;
}
