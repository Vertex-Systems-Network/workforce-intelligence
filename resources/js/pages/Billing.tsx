import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { BadgeDollarSign, Check, CircleDollarSign, Clock3, CreditCard, FileText, RefreshCw, ShieldCheck, Sparkles } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { PageLoadingState } from '../components/LoadingStates';
import { useConfirmAction, ErrorState, Alert, Badge, Button, Card, CardBody, CardHeader, Field, Input, Page, PageHeader, Progress, Segmented, Select, DataGrid, FormDialog, Box, Grid, Inline, Stack, Text, Option, type DataGridColumn } from '../design-system';
type Entitlements = Record<string, boolean | number | string>;
type Plan = {
    id: number;
    name: string;
    slug: string;
    description: string;
    currency: string;
    monthly_price_per_seat: number;
    annual_price_per_seat: number;
    trial_days: number;
    is_popular: boolean;
    entitlements: Entitlements;
};
type Subscription = {
    id: number;
    uuid: string;
    status: string;
    billing_interval: 'monthly' | 'annual';
    provider: string;
    seat_quantity: number;
    trial_ends_at: string | null;
    current_period_start: string | null;
    current_period_end: string | null;
    cancel_at_period_end: boolean;
    grace_ends_at: string | null;
    grandfathered: boolean;
    plan: Plan;
};
type UsageMetric = {
    used: number;
    limit: number | null;
    percent: number | null;
};
type InvoiceLine = {
    id: number;
    description: string;
    quantity: number;
    unit_amount: number;
    amount: number;
};
type Invoice = {
    id: number;
    uuid: string;
    number: string;
    status: string;
    currency: string;
    subtotal: number;
    tax_total: number;
    discount_total: number;
    total: number;
    amount_paid: number;
    amount_due: number;
    issued_at: string | null;
    due_at: string | null;
    paid_at: string | null;
    provider: string;
    provider_hosted_url: string | null;
    lines: InvoiceLine[];
};
type Transaction = {
    id: number;
    uuid: string;
    provider: string;
    type: string;
    status: string;
    currency: string;
    amount: number;
    processed_at: string | null;
    provider_transaction_id: string | null;
};
type BillingProvider = {
    provider: string;
    display_name: string;
    is_default: boolean;
    health_status: string;
};
type BillingPayload = {
    subscription: Subscription;
    plans: Plan[];
    entitlements: Entitlements;
    usage: Record<string, UsageMetric>;
    invoices: Invoice[];
    transactions: Transaction[];
    billing_provider: string;
    commerce_providers: BillingProvider[];
    can_mark_manual_paid: boolean;
    currency_note: string;
};
/** Handles the money operation for the WorkIntel client. */ const money = (value: number, currency = 'USD') => new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: 2 }).format(value);
/** Handles the date operation for the WorkIntel client. */ const date = (value: string | null) => value ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(value)) : '—';
const labels: Record<string, string> = { members: 'Active members', projects: 'Projects', clients: 'Clients', devices: 'Desktop devices', saved_reports: 'Saved reports', scheduled_reports: 'Scheduled reports' };
const featureLabels: Record<string, string> = {
    'feature.desktop_agent': 'Desktop agent', 'feature.activity_tracking': 'Application tracking', 'feature.browser_tracking': 'Browser tracking', 'feature.screenshots': 'Screenshots',
    'feature.payroll': 'Payroll', 'feature.advanced_reports': 'Advanced reports', 'feature.scheduled_reports': 'Scheduled reports', 'feature.client_portal': 'Client portal', 'feature.client_invoicing': 'Client invoicing', 'feature.api_access': 'API access', 'feature.priority_support': 'Priority support',
};
/** Handles the billing operation for the WorkIntel client. */ export default function Billing() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [data, setData] = useState<BillingPayload | null>(null);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [interval, setInterval] = useState<'monthly' | 'annual'>('monthly');
    const [provider, setProvider] = useState('');
    const [coupon, setCoupon] = useState('');
    const [manualInvoice, setManualInvoice] = useState<Invoice | null>(null);
    const [manualReference, setManualReference] = useState('');
    /** Loads load data required by the current view. */ const load = async (silent = false) => {
        if (!workspaceId)
            return;
        if (!silent)
            setLoading(true);
        setError('');
        try {
            const payload = await apiRequest<BillingPayload>('/api/v1/billing', { workspaceId, silent });
            setData(payload);
            setInterval(payload.subscription.billing_interval);
            setProvider(payload.commerce_providers.find(p => p.is_default)?.provider ?? payload.commerce_providers[0]?.provider ?? 'manual');
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not load billing.');
        }
        finally {
            if (!silent)
                setLoading(false);
        }
    };
    useEffect(() => { void load(); }, [workspaceId]);
    const current = data?.subscription;
    /** Handles the switch plan operation for the WorkIntel client. */ const switchPlan = async (plan: Plan) => {
        if (!data || plan.slug === current?.plan.slug && interval === current.billing_interval)
            return;
        const price = interval === 'annual' ? plan.annual_price_per_seat : plan.monthly_price_per_seat;
        if (!await confirmAction({ title: plan.slug === 'free' ? 'Switch subscription plan?' : 'Start checkout?', description: `${plan.name} (${interval}) · ${price > 0 ? `${money(price, plan.currency)} per active seat` : 'No charge'}.`, confirmLabel: plan.slug === 'free' ? 'Switch plan' : 'Continue' }))
            return;
        setBusy(true);
        setError('');
        setMessage('');
        try {
            if (plan.slug === 'free') {
                await apiRequest('/api/v1/billing/subscription/change', { method: 'POST', workspaceId, body: JSON.stringify({ plan_slug: plan.slug, billing_interval: interval, use_trial: false }) });
                setMessage(`Workspace switched to ${plan.name}.`);
                window.dispatchEvent(new CustomEvent('workintel:subscription-changed'));
                await load(true);
                return;
            }
            const payload = await apiRequest<{
                checkout: {
                    status: string;
                    checkout_url: string | null;
                    instructions?: string | null;
                    total: number;
                    currency: string;
                };
            }>('/api/v1/commerce/checkout', { method: 'POST', workspaceId, body: JSON.stringify({ plan_slug: plan.slug, billing_interval: interval, provider: provider || undefined, coupon_code: coupon.trim() || undefined }) });
            if (payload.checkout.checkout_url) {
                window.location.assign(payload.checkout.checkout_url);
                return;
            }
            setMessage(payload.checkout.instructions || `Checkout created for ${money(payload.checkout.total, payload.checkout.currency)}. Complete payment with the selected provider.`);
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not start checkout.');
        }
        finally {
            setBusy(false);
        }
    };
    /** Handles the cancel operation for the WorkIntel client. */ const cancel = async () => {
        if (!current || !await confirmAction({ title: 'Schedule cancellation?', description: 'The subscription will downgrade to Free at the end of the current period.', confirmLabel: 'Schedule cancellation', danger: true }))
            return;
        setBusy(true);
        try {
            await apiRequest('/api/v1/billing/subscription/cancel', { method: 'POST', workspaceId });
            setMessage('Cancellation scheduled.');
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not schedule cancellation.');
        }
        finally {
            setBusy(false);
        }
    };
    /** Handles the resume operation for the WorkIntel client. */ const resume = async () => {
        setBusy(true);
        try {
            await apiRequest('/api/v1/billing/subscription/resume', { method: 'POST', workspaceId });
            setMessage('Cancellation removed.');
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not resume subscription.');
        }
        finally {
            setBusy(false);
        }
    };
    /** Handles the activate billing operation for the WorkIntel client. */ const activateBilling = async () => {
        if (!current)
            return;
        setBusy(true);
        setError('');
        try {
            await apiRequest('/api/v1/billing/subscription/change', { method: 'POST', workspaceId, body: JSON.stringify({ plan_slug: current.plan.slug, billing_interval: interval, use_trial: false }) });
            setMessage('Billing activated for the current plan.');
            window.dispatchEvent(new CustomEvent('workintel:subscription-changed'));
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not activate billing.');
        }
        finally {
            setBusy(false);
        }
    };
    /** Open a WorkIntel-owned payment-reference dialog for manual settlement. */ const openManualPayment = (invoice: Invoice) => { setManualInvoice(invoice); setManualReference(`MANUAL-${invoice.number}`); };
    /** Record one verified manual workspace-billing settlement. */ const payManual = async (event: FormEvent) => {
        event.preventDefault();
        if (!manualInvoice)
            return;
        setBusy(true);
        try {
            await apiRequest(`/api/v1/billing/invoices/${manualInvoice.id}/mark-paid`, { method: 'POST', workspaceId, body: JSON.stringify({ reference: manualReference.trim() || null }) });
            setMessage(`${manualInvoice.number} marked paid.`);
            setManualInvoice(null);
            setManualReference('');
            await load(true);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not mark invoice paid.');
        }
        finally {
            setBusy(false);
        }
    };
    if (loading && !data)
        return <PageLoadingState title="Loading billing" description="Checking subscription, entitlements and usage."/>;
    if (!data || !current)
        return <Page><ErrorState title="Billing unavailable" text={error || 'Billing information could not be loaded.'} retry={() => load()}/></Page>;
    const perSeat = interval === 'annual' ? current.plan.annual_price_per_seat : current.plan.monthly_price_per_seat;
    const periodLabel = current.status === 'trialing' ? `Trial ends ${date(current.trial_ends_at)}` : current.current_period_end ? `Current period ends ${date(current.current_period_end)}` : 'No billing period';
    const invoiceColumns: DataGridColumn<Invoice>[] = [
        { id: 'invoice', header: 'Invoice', searchValue: row => row.number, sortValue: row => row.number, cell: row => <Inline align="center" gap={7}><FileText size={14}/><Text weight={650}>{row.number}</Text></Inline> },
        { id: 'issued', header: 'Issued', sortValue: row => row.issued_at ?? '', filterValue: row => row.issued_at ?? '', filter: { type: 'dateRange', label: 'Issued' }, cell: row => date(row.issued_at) },
        { id: 'due', header: 'Due', sortValue: row => row.due_at ?? '', filterValue: row => row.due_at ?? '', filter: { type: 'dateRange', label: 'Due' }, cell: row => date(row.due_at) },
        { id: 'total', header: 'Total', sortValue: row => row.total, cell: row => <Text className="stat-num">{money(row.total, row.currency)}</Text> },
        { id: 'status', header: 'Status', filterValue: row => row.status, filter: { type: 'select', label: 'Status', options: ['open', 'paid', 'uncollectible', 'void'].map(value => ({ value, label: value })) }, cell: row => <Badge tone={row.status === 'paid' ? 'success' : row.status === 'open' ? 'warning' : row.status === 'uncollectible' ? 'danger' : 'neutral'}>{row.status}</Badge> },
        { id: 'actions', header: '', hideable: false, sortable: false, cell: row => data.can_mark_manual_paid && row.provider === 'manual' && ['open', 'uncollectible'].includes(row.status) ? <Button size="sm" variant="outline" loading={busy} onClick={() => openManualPayment(row)}><CircleDollarSign size={13}/> Mark Paid</Button> : null },
    ];
    const transactionColumns: DataGridColumn<Transaction>[] = [
        { id: 'type', header: 'Type', searchValue: row => row.type, filterValue: row => row.type, cell: row => row.type },
        { id: 'provider', header: 'Provider', searchValue: row => row.provider, filterValue: row => row.provider, cell: row => row.provider },
        { id: 'amount', header: 'Amount', sortValue: row => row.amount, cell: row => <Text className="stat-num">{money(row.amount, row.currency)}</Text> },
        { id: 'status', header: 'Status', filterValue: row => row.status, filter: { type: 'select', label: 'Status', options: ['pending', 'succeeded', 'failed', 'refunded'].map(value => ({ value, label: value })) }, cell: row => <Badge tone={row.status === 'succeeded' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'}>{row.status}</Badge> },
        { id: 'processed', header: 'Processed', sortValue: row => row.processed_at ?? '', filterValue: row => row.processed_at ?? '', filter: { type: 'dateRange', label: 'Processed' }, cell: row => date(row.processed_at) },
        { id: 'reference', header: 'Reference', searchValue: row => row.provider_transaction_id ?? '', cell: row => <Text size={10.5} color="var(--text-3)">{row.provider_transaction_id ?? '—'}</Text> },
    ];
    return <Page>
    <PageHeader title="Billing & Plans" description="Manage workspace subscription, limits, invoices and feature entitlements" actions={<Button variant="outline" size="sm" loading={loading} onClick={() => void load()}><RefreshCw size={14}/> Refresh</Button>}/>
    {error && <Alert tone="danger" mb={12}>{error}</Alert>}{message && <Alert tone="success" mb={12}>{message}</Alert>}
    <Grid columns="minmax(0,1.2fr) minmax(0,1fr)" gap={12} mb={18}>
      <Card borderColor="var(--accent)"><CardHeader title={`${current.plan.name} Plan`} description={`${current.seat_quantity} active seat${current.seat_quantity === 1 ? '' : 's'} · ${current.billing_interval}`} action={<Badge tone={current.status === 'active' ? 'success' : current.status === 'trialing' ? 'accent' : current.status === 'past_due' ? 'danger' : 'neutral'} dot>{current.status}</Badge>}/><CardBody>
        <Inline align="baseline" gap={7} mb={7}><Text className="stat-num" size={30} weight={750} color="var(--accent)">{perSeat === 0 ? 'Free' : money(perSeat, current.plan.currency)}</Text>{perSeat > 0 && <span className="ui-card-description">/ seat / {current.billing_interval === 'annual' ? 'year' : 'month'}</span>}</Inline>
        <Box className="ui-card-description" mb={14}>{periodLabel}{current.cancel_at_period_end ? ' · Cancellation scheduled' : ''}</Box>
        <Inline gap={7} wrap="wrap">{current.grandfathered && current.plan.slug !== 'free' && <Button variant="primary" loading={busy} onClick={() => void activateBilling()}>Activate Billing</Button>}{current.cancel_at_period_end ? <Button variant="primary" loading={busy} onClick={() => void resume()}>Resume Subscription</Button> : current.plan.slug !== 'free' && !current.grandfathered && <Button variant="outline" loading={busy} onClick={() => void cancel()}>Cancel at Period End</Button>}<Badge tone="neutral">Provider: {data.billing_provider}</Badge>{current.grandfathered && <Badge tone="info">Grandfathered</Badge>}</Inline>
      </CardBody></Card>
      <Card><CardHeader title="Usage & Limits" description="Hard limits are enforced by the API"/><CardBody><Stack gap={12}>{Object.entries(data.usage).filter(([key]) => key !== 'screenshot_storage_bytes').map(([key, item]) => { const unlimited = item.limit === -1; return <div key={key}><Inline justify="space-between" gap={8} mb={5}><Text size={12} color="var(--text-2)">{labels[key] ?? key}</Text><span className="stat-num ui-card-description">{item.used} / {unlimited ? 'Unlimited' : item.limit}</span></Inline>{!unlimited && <Progress value={item.percent ?? 0} tone={(item.percent ?? 0) >= 90 ? 'danger' : (item.percent ?? 0) >= 75 ? 'warning' : 'accent'}/>}</div>; })}<div className="ui-card-description">Screenshot storage: {(data.usage.screenshot_storage_bytes.used / 1024 / 1024).toFixed(1)} MB</div></Stack></CardBody></Card>
    </Grid>

    <Inline align="center" justify="space-between" gap={12} mb={10} wrap="wrap"><div><h3 className="ui-card-title">Plans</h3><div className="ui-card-description">Paid plans use the P11 checkout lifecycle; activation occurs after provider confirmation.</div></div><Segmented value={interval} onChange={setInterval} options={[{ value: 'monthly', label: 'Monthly' }, { value: 'annual', label: 'Annual' }]}/></Inline>
    <Card mb={12}><CardBody><Grid columns="minmax(180px,.7fr) minmax(180px,.7fr) 1fr" gap={10} align="end"><Field label="Payment provider"><Select value={provider} onChange={e => setProvider(e.target.value)}>{data.commerce_providers.length ? data.commerce_providers.map(p => <Option key={p.provider} value={p.provider}>{p.display_name}{p.is_default ? ' · default' : ''}</Option>) : <Option value="manual">Manual settlement</Option>}</Select></Field><Field label="Coupon code"><Input value={coupon} onChange={e => setCoupon(e.target.value.toUpperCase())} placeholder="Optional"/></Field><div className="ui-card-description">Provider credentials stay seller-side. Workspace buyers only see enabled provider names.</div></Grid></CardBody></Card>
    <Box display="grid" gridColumns="repeat(4,minmax(220px,1fr))" gap={10} overflowX="auto" pb={6} mb={20}>{data.plans.map(plan => { const currentPlan = plan.slug === current.plan.slug; const price = interval === 'annual' ? plan.annual_price_per_seat : plan.monthly_price_per_seat; const features = Object.entries(plan.entitlements).filter(([key, value]) => key.startsWith('feature.') && value === true).slice(0, 10); const memberLimit = Number(plan.entitlements['limit.members'] ?? 0); return <Card key={plan.slug} minWidth={220} borderColor={currentPlan ? 'var(--accent)' : 'var(--border)'}><CardBody><Inline justify="space-between" align="center" gap={6} mb={8}><Box size={15} weight={750}>{plan.name}</Box>{currentPlan ? <Badge tone="accent">Current</Badge> : plan.is_popular && <Badge tone="success">Popular</Badge>}</Inline><Box className="ui-card-description" minHeight={34}>{plan.description}</Box><Box m="12px 0"><Text className="stat-num" size={23} weight={750}>{price === 0 ? '$0' : money(price, plan.currency)}</Text>{price > 0 && <span className="ui-card-description"> / seat / {interval === 'annual' ? 'yr' : 'mo'}</span>}</Box><Box display="grid" gap={6} minHeight={158}><Box size={11} color="var(--text-2)" display="flex" gap={6}><Check size={12} color="var(--success)"/> {memberLimit < 0 ? 'Unlimited' : memberLimit} members</Box>{features.map(([key]) => <Box key={key} size={11} color="var(--text-2)" display="flex" gap={6}><Check size={12} color="var(--success)"/> {featureLabels[key] ?? key.replace('feature.', '').replaceAll('_', ' ')}</Box>)}</Box><Button variant={currentPlan ? 'secondary' : plan.is_popular ? 'primary' : 'outline'} disabled={busy || currentPlan && interval === current.billing_interval} onClick={() => void switchPlan(plan)} width="100%" mt={13}>{currentPlan && interval === current.billing_interval ? 'Current Plan' : plan.slug === 'free' ? 'Switch to Free' : plan.trial_days > 0 ? `Choose ${plan.name}` : `Switch Plan`}</Button>{plan.trial_days > 0 && plan.slug !== 'free' && <Box className="ui-card-description" textAlign="center" mt={5}>Up to {plan.trial_days}-day trial when eligible</Box>}</CardBody></Card>; })}</Box>

    <Grid columns="minmax(0,1.5fr) minmax(300px,.8fr)" gap={14}>
      <DataGrid rows={data.invoices} columns={invoiceColumns} rowKey={row => row.id} persistKey="billing.invoices" onRefresh={() => load(true)} defaultSort={{ id: 'issued', direction: 'desc' }} searchPlaceholder="Search billing invoices…" empty={<Text color="var(--text-3)">No invoices yet.</Text>}/>
      <Card><CardHeader title="Plan Entitlements" description="Effective features for this workspace"/><CardBody><Stack gap={8}>{Object.entries(data.entitlements).filter(([key]) => key.startsWith('feature.')).map(([key, value]) => <Inline key={key} justify="space-between" gap={9} align="center"><Text size={12} color="var(--text-2)">{featureLabels[key] ?? key}</Text><Badge tone={value ? 'success' : 'neutral'}>{value ? 'Included' : 'Locked'}</Badge></Inline>)}</Stack></CardBody></Card>
    </Grid>

    {data.transactions.length > 0 && <Box mt={14}><DataGrid rows={data.transactions} columns={transactionColumns} rowKey={row => row.id} persistKey="billing.transactions" defaultSort={{ id: 'processed', direction: 'desc' }} searchPlaceholder="Search billing transactions…"/></Box>}
    <Alert tone="info" mt={14}>{data.currency_note} Payment-provider adapters are intentionally separate from the entitlement engine.</Alert>
    <FormDialog open={!!manualInvoice} onClose={() => setManualInvoice(null)} title="Mark invoice paid" description={manualInvoice ? `Record a verified manual payment for ${manualInvoice.number}.` : undefined} formId="manual-billing-payment" onSubmit={payManual} submitLabel="Mark paid" loading={busy}><Field label="Payment reference" hint="Use the bank, reconciliation or receipt reference used by your finance team."><Input value={manualReference} onChange={event => setManualReference(event.target.value)} autoFocus/></Field></FormDialog>
  </Page>;
}
