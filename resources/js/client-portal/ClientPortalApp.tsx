import { FormEvent, useEffect, useMemo, useState } from 'react';
import { ArrowRight, Banknote, BriefcaseBusiness, CheckCircle2, CircleDollarSign, CreditCard, Download, ExternalLink, FileChartColumnIncreasing, Landmark, LogOut, RefreshCw, ShieldCheck, } from 'lucide-react';
import { Alert, Badge, Button, Card, CardBody, EmptyState, Field, IconButton, Input, LoadingState, Modal, PageHeader, Progress, Segmented, TableWrap, Pressable, Box, Inline, Form, Link } from '../design-system';
type PortalIdentity = {
    id: number;
    name: string;
    email: string;
    workspace: {
        id: number;
        name: string;
        slug: string;
    };
    client: {
        id: number;
        name: string;
        company_name: string | null;
        currency: string;
    };
};
type PortalProject = {
    id: number;
    name: string;
    code: string | null;
    status: string;
    priority: string;
    start_date: string | null;
    due_date: string | null;
    tasks_total: number;
    tasks_done: number;
    progress_percent: number;
    description?: string | null;
    tracked_hours?: number;
    billable_hours?: number;
    tasks?: Array<{
        id: number;
        title: string;
        status: string;
        priority: string;
        due_at: string | null;
        completed_at: string | null;
    }>;
};
type PortalInvoice = {
    id: number;
    uuid: string;
    number: string;
    status: string;
    currency: string;
    issue_date: string;
    due_date: string;
    subtotal: number;
    discount_total: number;
    tax_percent: number;
    tax_total: number;
    total: number;
    amount_paid: number;
    amount_due: number;
    notes: string | null;
    terms: string | null;
    lines: Array<{
        id: number;
        description: string;
        quantity: number;
        unit_price: number;
        amount: number;
        project: string | null;
        source_type: string;
    }>;
    payments: Array<{
        id: number;
        amount: number;
        method: string;
        reference: string | null;
        paid_on: string;
    }>;
};
type PortalReport = {
    id: number;
    uuid: string;
    name: string;
    report_type: string;
    project: string | null;
    period_start: string | null;
    period_end: string | null;
    snapshot: Record<string, any>;
    note: string | null;
    published_at: string;
};
type PaymentGatewayOption = {
    id: number;
    provider: string;
    display_name: string;
    is_default: boolean;
    hosted: boolean;
    instructions: string | null;
};
type PaymentCheckout = {
    id: number;
    uuid: string;
    invoice_id: number;
    invoice_number: string | null;
    provider: string;
    status: string;
    currency: string;
    amount: number;
    checkout_url: string | null;
    expires_at: string | null;
    completed_at: string | null;
    instructions: string | null;
    bank_details: string | null;
};
type PaymentOptions = {
    invoice: {
        id: number;
        number: string;
        status: string;
        currency: string;
        amount_due: number;
    };
    gateways: PaymentGatewayOption[];
};
type Dashboard = {
    client: PortalIdentity['client'];
    stats: {
        active_projects: number;
        reports: number;
        outstanding: number;
        paid: number;
        currency: string;
    };
    projects: PortalProject[];
    invoices: PortalInvoice[];
    reports: PortalReport[];
};
const pathParts = window.location.pathname.split('/').filter(Boolean);
const workspaceSlug = pathParts[1] ?? '';
const activationMode = pathParts[2] === 'activate';
const storageKey = `workintel-client-portal:${workspaceSlug}`;
/** Formats money for the current browser locale without changing invoice currency. */
const money = (value: number, currency: string) => new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: 2 }).format(value);
/** Formats a date-only portal value without introducing timezone day shifts. */
const date = (value: string | null) => value ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(`${value}T00:00:00`)) : '—';
/** Performs one authenticated or public client-portal JSON request. */
async function request<T>(path: string, options: RequestInit = {}, token?: string | null): Promise<T> {
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Locale', localStorage.getItem('workintel-language') || navigator.language || 'en');
    if (options.body && !headers.has('Content-Type'))
        headers.set('Content-Type', 'application/json');
    if (token)
        headers.set('Authorization', `Bearer ${token}`);
    const response = await fetch(path, { ...options, headers });
    const payload = response.status === 204 ? null : await response.json().catch(() => null);
    if (!response.ok) {
        const errors = payload?.errors;
        if (errors && typeof errors === 'object') {
            for (const value of Object.values(errors)) {
                if (Array.isArray(value) && typeof value[0] === 'string')
                    throw new Error(value[0]);
            }
        }
        throw new Error(payload?.message || 'The request could not be completed.');
    }
    return payload as T;
}
/** Downloads a private client invoice PDF through the portal bearer token. */
async function downloadInvoice(id: number, number: string, token: string) {
    const response = await fetch(`/api/v1/client-portal/invoices/${id}/pdf`, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/pdf', 'X-Locale': localStorage.getItem('workintel-language') || navigator.language || 'en' } });
    if (!response.ok)
        throw new Error('Could not download invoice.');
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${number}.pdf`;
    anchor.click();
    URL.revokeObjectURL(url);
}
/** Downloads a published report PDF through the scoped client portal token. */
async function downloadReport(id: number, name: string, token: string) {
    const response = await fetch(`/api/v1/client-portal/reports/${id}/pdf`, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/pdf', 'X-Locale': localStorage.getItem('workintel-language') || navigator.language || 'en' } });
    if (!response.ok)
        throw new Error('Could not download report.');
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${name.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'client-report'}.pdf`;
    anchor.click();
    URL.revokeObjectURL(url);
}
/** Renders the isolated client portal and its invoice-payment checkout workflow. */
export default function ClientPortalApp() {
    const [token, setToken] = useState<string | null>(() => sessionStorage.getItem(storageKey));
    const [portal, setPortal] = useState<PortalIdentity | null>(null);
    const [ready, setReady] = useState(false);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [email, setEmail] = useState('client@techcorp.test');
    const [password, setPassword] = useState('');
    const [activationPassword, setActivationPassword] = useState('');
    const [tab, setTab] = useState<'overview' | 'projects' | 'invoices' | 'reports'>('overview');
    const [dashboard, setDashboard] = useState<Dashboard | null>(null);
    const [projects, setProjects] = useState<PortalProject[]>([]);
    const [invoices, setInvoices] = useState<PortalInvoice[]>([]);
    const [reports, setReports] = useState<PortalReport[]>([]);
    const [selectedProject, setSelectedProject] = useState<PortalProject | null>(null);
    const [selectedInvoice, setSelectedInvoice] = useState<PortalInvoice | null>(null);
    const [selectedReport, setSelectedReport] = useState<PortalReport | null>(null);
    const [paymentOptions, setPaymentOptions] = useState<PaymentOptions | null>(null);
    const [paymentCheckout, setPaymentCheckout] = useState<PaymentCheckout | null>(null);
    const [paymentBusy, setPaymentBusy] = useState(false);
    const [paymentMessage, setPaymentMessage] = useState('');
    /** Restores an authenticated client portal identity from the session token. */
    useEffect(() => {
        if (!token) {
            setReady(true);
            return;
        }
        request<{
            portal: PortalIdentity;
        }>('/api/v1/client-portal/me', {}, token)
            .then(response => setPortal(response.portal))
            .catch(() => {
            sessionStorage.removeItem(storageKey);
            setToken(null);
        })
            .finally(() => setReady(true));
    }, [token]);
    /** Loads the initial dashboard once a valid portal identity is known. */
    useEffect(() => {
        if (portal && token)
            void loadTab('overview');
    }, [portal, token]);
    /** Reconciles a hosted checkout after Stripe, PayPal or custom checkout redirects back to the portal. */
    useEffect(() => {
        if (!portal || !token)
            return;
        const params = new URLSearchParams(window.location.search);
        const checkoutId = Number(params.get('payment_checkout') || 0);
        const cancelled = params.get('payment_cancelled') === '1';
        if (cancelled) {
            setPaymentMessage('Payment was cancelled. No payment was recorded.');
            history.replaceState({}, '', `/portal/${workspaceSlug}`);
            return;
        }
        if (!Number.isInteger(checkoutId) || checkoutId <= 0)
            return;
        setPaymentBusy(true);
        request<{
            data: PaymentCheckout;
        }>(`/api/v1/client-portal/payment-checkouts/${checkoutId}`, {}, token)
            .then(async (response) => {
            setPaymentCheckout(response.data);
            setPaymentMessage(response.data.status === 'completed' ? 'Payment completed successfully.' : 'Payment is still being confirmed. You can check its status again.');
            if (response.data.invoice_id)
                await openInvoiceById(response.data.invoice_id);
            await loadTab('invoices');
        })
            .catch(reason => setError(reason instanceof Error ? reason.message : 'Could not confirm payment status.'))
            .finally(() => {
            history.replaceState({}, '', `/portal/${workspaceSlug}`);
            setPaymentBusy(false);
        });
    }, [portal, token]);
    /** Loads one portal tab while keeping client-scoped API boundaries intact. */
    const loadTab = async (next: typeof tab) => {
        if (!token)
            return;
        setLoading(true);
        setError('');
        try {
            if (next === 'overview')
                setDashboard(await request<Dashboard>('/api/v1/client-portal/dashboard', {}, token));
            if (next === 'projects')
                setProjects((await request<{
                    data: PortalProject[];
                }>('/api/v1/client-portal/projects', {}, token)).data);
            if (next === 'invoices')
                setInvoices((await request<{
                    data: PortalInvoice[];
                }>('/api/v1/client-portal/invoices', {}, token)).data);
            if (next === 'reports')
                setReports((await request<{
                    data: PortalReport[];
                }>('/api/v1/client-portal/reports', {}, token)).data);
            setTab(next);
        }
        catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Could not load portal data.');
        }
        finally {
            setLoading(false);
        }
    };
    /** Authenticates an existing client portal account. */
    const login = async (event: FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setError('');
        try {
            const response = await request<{
                token: string;
                portal: PortalIdentity;
            }>('/api/v1/client-portal/login', { method: 'POST', body: JSON.stringify({ workspace_slug: workspaceSlug, email, password }) });
            sessionStorage.setItem(storageKey, response.token);
            setToken(response.token);
            setPortal(response.portal);
        }
        catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Could not sign in.');
        }
        finally {
            setLoading(false);
        }
    };
    /** Activates a one-time portal invitation and establishes a client session. */
    const activate = async (event: FormEvent) => {
        event.preventDefault();
        const inviteToken = new URLSearchParams(location.hash.replace(/^#/, '')).get('token');
        if (!inviteToken) {
            setError('Activation token is missing.');
            return;
        }
        setLoading(true);
        setError('');
        try {
            const response = await request<{
                token: string;
                portal: PortalIdentity;
            }>('/api/v1/client-portal/activate', { method: 'POST', body: JSON.stringify({ workspace_slug: workspaceSlug, token: inviteToken, password: activationPassword }) });
            sessionStorage.setItem(storageKey, response.token);
            setToken(response.token);
            setPortal(response.portal);
            history.replaceState({}, '', `/portal/${workspaceSlug}`);
        }
        catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Could not activate portal access.');
        }
        finally {
            setLoading(false);
        }
    };
    /** Ends the client portal session without affecting internal workforce sessions. */
    const logout = async () => {
        if (token)
            await request('/api/v1/client-portal/logout', { method: 'POST' }, token).catch(() => undefined);
        sessionStorage.removeItem(storageKey);
        setToken(null);
        setPortal(null);
        setDashboard(null);
        setPaymentOptions(null);
        setPaymentCheckout(null);
    };
    /** Loads one project detail visible to the authenticated client. */
    const openProject = async (project: PortalProject) => {
        if (!token)
            return;
        setLoading(true);
        try {
            setSelectedProject((await request<{
                data: PortalProject;
            }>(`/api/v1/client-portal/projects/${project.id}`, {}, token)).data);
        }
        catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Could not load project.');
        }
        finally {
            setLoading(false);
        }
    };
    /** Loads invoice details and the workspace-approved Pay Now methods. */
    const openInvoiceById = async (invoiceId: number) => {
        if (!token)
            return;
        const invoice = (await request<{
            data: PortalInvoice;
        }>(`/api/v1/client-portal/invoices/${invoiceId}`, {}, token)).data;
        setSelectedInvoice(invoice);
        setPaymentCheckout(current => current?.invoice_id === invoice.id ? current : null);
        setPaymentMessage('');
        if (invoice.amount_due > 0 && ['sent', 'partial', 'overdue'].includes(invoice.status)) {
            setPaymentOptions(await request<PaymentOptions>(`/api/v1/client-portal/invoices/${invoice.id}/payment-options`, {}, token));
        }
        else {
            setPaymentOptions(null);
        }
    };
    /** Opens an invoice selected from the portal tables. */
    const openInvoice = async (invoice: PortalInvoice) => {
        setLoading(true);
        setError('');
        try {
            await openInvoiceById(invoice.id);
        }
        catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Could not load invoice.');
        }
        finally {
            setLoading(false);
        }
    };
    /** Opens one published report visible to the current client. */
    const openReport = async (report: PortalReport) => {
        if (!token)
            return;
        setLoading(true);
        try {
            setSelectedReport((await request<{
                data: PortalReport;
            }>(`/api/v1/client-portal/reports/${report.id}`, {}, token)).data);
        }
        catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Could not load report.');
        }
        finally {
            setLoading(false);
        }
    };
    /** Starts a workspace-owned payment checkout for the selected invoice. */
    const startPayment = async (gateway: PaymentGatewayOption) => {
        if (!token || !selectedInvoice)
            return;
        setPaymentBusy(true);
        setError('');
        setPaymentMessage('');
        try {
            const response = await request<{
                data: PaymentCheckout;
            }>(`/api/v1/client-portal/invoices/${selectedInvoice.id}/checkout`, { method: 'POST', body: JSON.stringify({ gateway_id: gateway.id }) }, token);
            setPaymentCheckout(response.data);
            if (response.data.checkout_url) {
                window.location.assign(response.data.checkout_url);
                return;
            }
            setPaymentMessage('Payment instructions are ready. Your workspace billing team will confirm the payment after verification.');
        }
        catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Could not start payment.');
        }
        finally {
            setPaymentBusy(false);
        }
    };
    /** Rechecks a pending hosted checkout and refreshes invoice balances if it completes. */
    const refreshPayment = async () => {
        if (!token || !paymentCheckout)
            return;
        setPaymentBusy(true);
        try {
            const response = await request<{
                data: PaymentCheckout;
            }>(`/api/v1/client-portal/payment-checkouts/${paymentCheckout.id}`, {}, token);
            setPaymentCheckout(response.data);
            setPaymentMessage(response.data.status === 'completed' ? 'Payment completed successfully.' : 'Payment is still pending confirmation.');
            if (response.data.status === 'completed') {
                await openInvoiceById(response.data.invoice_id);
                await loadTab('invoices');
            }
        }
        catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Could not refresh payment status.');
        }
        finally {
            setPaymentBusy(false);
        }
    };
    if (!ready)
        return <div className="client-portal-shell client-portal-shell--state"><LoadingState title="Loading client portal…" text="Checking your secure client session."/></div>;
    if (!portal) {
        return <div className="client-portal-shell"><div className="client-portal-auth">
      <div className="client-portal-brand"><div className="client-portal-logo">W</div><div><strong>Client Portal</strong><span>{workspaceSlug || 'Workforce Intelligence'}</span></div></div>
      <PageHeader title={activationMode ? 'Activate access' : 'Welcome back'} description={activationMode ? 'Choose a password to activate your secure client portal.' : 'Sign in to view projects, reports and invoices shared with your organization.'}/>
      {error && <Alert tone="danger">{error}</Alert>}
      {activationMode ? <Form onSubmit={activate}><Field label="New password"><Input type="password" minLength={8} value={activationPassword} onChange={event => setActivationPassword(event.target.value)} required/></Field><Button variant="primary" type="submit" loading={loading} width="100%" mt={14}>Activate Portal <ArrowRight size={14}/></Button></Form> : <Form onSubmit={login}><Field label="Email"><Input type="email" value={email} onChange={event => setEmail(event.target.value)} required/></Field><Field label="Password"><Input type="password" value={password} onChange={event => setPassword(event.target.value)} required/></Field><Button variant="primary" type="submit" loading={loading} width="100%" mt={14}>Sign In <ArrowRight size={14}/></Button></Form>}
      <div className="client-portal-secure"><ShieldCheck size={14}/> Dedicated client access · Internal workforce data is not exposed</div>
    </div></div>;
    }
    return <div className="client-portal-shell">
    <Link className="ui-skip-link" href="#client-portal-main">Skip to main content</Link>
    <header className="client-portal-header">
      <div className="client-portal-brand"><div className="client-portal-logo">W</div><div><strong>{portal.workspace.name}</strong><span>{portal.client.company_name || portal.client.name}</span></div></div>
      <div className="client-portal-header__actions"><span className="client-portal-user">{portal.name}</span><Button variant="ghost" size="sm" onClick={() => void logout()}><LogOut size={14}/> Sign out</Button></div>
    </header>
    <main id="client-portal-main" tabIndex={-1} className="client-portal-main">
      <PageHeader title="Client Portal" description="Projects, published reports and billing shared with your organization." actions={<Button variant="outline" size="sm" loading={loading} onClick={() => void loadTab(tab)}><RefreshCw size={14}/> Refresh</Button>}/>
      {error && <Alert tone="danger" mb={12}>{error}</Alert>}
      {paymentMessage && <Alert tone={paymentCheckout?.status === 'completed' ? 'success' : 'info'} mb={12}>{paymentMessage}</Alert>}
      <Segmented value={tab} onChange={value => void loadTab(value)} options={[{ value: 'overview', label: 'Overview' }, { value: 'projects', label: 'Projects' }, { value: 'invoices', label: 'Invoices' }, { value: 'reports', label: 'Reports' }]}/>
      <Box mt={18}>
        {loading && <Box mb={12}><LoadingState compact title="Updating client portal…" text="Refreshing the selected client view."/></Box>}
        {tab === 'overview' && dashboard && <Overview dashboard={dashboard} onProject={openProject} onInvoice={openInvoice} onReport={openReport}/>}
        {tab === 'projects' && <Projects rows={projects} onOpen={openProject}/>}
        {tab === 'invoices' && <Invoices rows={invoices} onOpen={openInvoice} token={token}/>}
        {tab === 'reports' && <Reports rows={reports} onOpen={openReport}/>}
      </Box>
    </main>

    <Modal open={Boolean(selectedProject)} onClose={() => setSelectedProject(null)} title={selectedProject?.name ?? 'Project'} description={selectedProject?.description || selectedProject?.code || undefined} size="lg">
      {selectedProject && <><div className="client-portal-kpis"><Metric label="Progress" value={`${selectedProject.progress_percent}%`}/><Metric label="Tracked" value={`${selectedProject.tracked_hours ?? 0}h`}/><Metric label="Billable" value={`${selectedProject.billable_hours ?? 0}h`}/></div><Progress value={selectedProject.progress_percent}/><Box as="h3" mt={18} mb={6}>Tasks</Box>{selectedProject.tasks?.length ? selectedProject.tasks.map(task => <div className="client-portal-list-row" key={task.id}><span>{task.title}</span><Badge tone={task.status === 'done' ? 'success' : task.status === 'blocked' ? 'danger' : 'neutral'}>{task.status}</Badge></div>) : <EmptyState title="No client-visible tasks" text="Tasks will appear here when they are shared with this project."/>}</>}
    </Modal>

    <Modal open={Boolean(selectedInvoice)} onClose={() => { setSelectedInvoice(null); setPaymentOptions(null); setPaymentCheckout(null); }} title={selectedInvoice?.number ?? 'Invoice'} description={selectedInvoice ? `Issued ${date(selectedInvoice.issue_date)} · Due ${date(selectedInvoice.due_date)}` : undefined} size="lg">
      {selectedInvoice && <><Inline justify="flex-end" mb={10}><Badge tone={selectedInvoice.status === 'paid' ? 'success' : selectedInvoice.status === 'overdue' ? 'danger' : 'warning'}>{selectedInvoice.status}</Badge></Inline><TableWrap label={`Invoice ${selectedInvoice.number} line items`}><thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead><tbody>{selectedInvoice.lines.map(line => <tr key={line.id}><td>{line.description}</td><td>{line.quantity}</td><td>{money(line.unit_price, selectedInvoice.currency)}</td><td>{money(line.amount, selectedInvoice.currency)}</td></tr>)}</tbody></TableWrap><div className="client-portal-invoice-total"><span>Amount due</span><strong>{money(selectedInvoice.amount_due, selectedInvoice.currency)}</strong></div>{selectedInvoice.amount_due > 0 && <PaymentPanel options={paymentOptions} checkout={paymentCheckout} busy={paymentBusy} onPay={startPayment} onRefresh={refreshPayment}/>}<div className="client-portal-modal-actions"><Button variant="outline" onClick={() => void downloadInvoice(selectedInvoice.id, selectedInvoice.number, token!)}><Download size={14}/> Download PDF</Button></div></>}
    </Modal>

    <Modal open={Boolean(selectedReport)} onClose={() => setSelectedReport(null)} title={selectedReport?.name ?? 'Report'} description={selectedReport ? `${selectedReport.project || selectedReport.report_type.replaceAll('_', ' ')} · ${date(selectedReport.period_start)} – ${date(selectedReport.period_end)}` : undefined} size="lg">
      {selectedReport && <><ReportSnapshot report={selectedReport}/><Button variant="primary" onClick={() => token && void downloadReport(selectedReport.id, selectedReport.name, token)} mt={14}><Download size={14}/> Download PDF</Button></>}
    </Modal>
  </div>;
}
/** Renders Pay Now methods and the current client checkout status. */
function PaymentPanel({ options, checkout, busy, onPay, onRefresh }: {
    options: PaymentOptions | null;
    checkout: PaymentCheckout | null;
    busy: boolean;
    onPay: (gateway: PaymentGatewayOption) => void;
    onRefresh: () => void;
}) {
    if (!options)
        return <div className="client-payment-panel"><div><strong>Pay now</strong><small>No online or offline payment method is currently enabled for this invoice.</small></div></div>;
    return <section className="client-payment-panel">
    <div className="client-payment-panel__header"><div><strong>Pay now</strong><small>Choose a payment method enabled by your billing team.</small></div><ShieldCheck size={16}/></div>
    {checkout && <div className={`client-payment-status is-${checkout.status}`}><div>{checkout.status === 'completed' ? <CheckCircle2 size={17}/> : <CircleDollarSign size={17}/>}<span><strong>{checkout.status === 'completed' ? 'Payment confirmed' : 'Payment checkout'}</strong><small>{money(checkout.amount, checkout.currency)} · {checkout.provider.replaceAll('_', ' ')}</small></span></div><Badge tone={checkout.status === 'completed' ? 'success' : checkout.status === 'failed' ? 'danger' : 'warning'}>{checkout.status}</Badge>{checkout.status === 'pending' && !['manual', 'bank_transfer'].includes(checkout.provider) && <Button size="sm" variant="outline" loading={busy} onClick={onRefresh}><RefreshCw size={12}/> Check status</Button>}</div>}
    {checkout?.instructions && <div className="client-payment-instructions"><strong>Payment instructions</strong><p>{checkout.instructions}</p>{checkout.bank_details && <pre>{checkout.bank_details}</pre>}</div>}
    {checkout?.status !== 'completed' && <div className="client-payment-methods">{options.gateways.map(gateway => <Pressable key={gateway.id} type="button" className="client-payment-method" disabled={busy} onClick={() => onPay(gateway)}><span>{gateway.provider === 'bank_transfer' ? <Landmark size={17}/> : gateway.hosted ? <CreditCard size={17}/> : <Banknote size={17}/>}</span><div><strong>{gateway.display_name}</strong><small>{gateway.hosted ? 'Secure hosted checkout' : gateway.provider === 'bank_transfer' ? 'Pay by bank transfer' : 'Offline payment instructions'}</small></div>{gateway.is_default && <Badge tone="accent">Recommended</Badge>}{gateway.hosted && <ExternalLink size={13}/>}</Pressable>)}</div>}
  </section>;
}
/** Renders one client-portal KPI card. */
function Metric({ label, value }: {
    label: string;
    value: string;
}) {
    return <Card><CardBody><div className="ui-card-description">{label}</div><Box className="stat-num" size={22} weight={700} mt={5}>{value}</Box></CardBody></Card>;
}
/** Renders the client portal overview dashboard. */
function Overview({ dashboard, onProject, onInvoice, onReport }: {
    dashboard: Dashboard;
    onProject: (project: PortalProject) => void;
    onInvoice: (invoice: PortalInvoice) => void;
    onReport: (report: PortalReport) => void;
}) {
    return <><div className="client-portal-kpis"><Metric label="Active Projects" value={String(dashboard.stats.active_projects)}/><Metric label="Published Reports" value={String(dashboard.stats.reports)}/><Metric label="Outstanding" value={money(dashboard.stats.outstanding, dashboard.stats.currency)}/><Metric label="Paid" value={money(dashboard.stats.paid, dashboard.stats.currency)}/></div><div className="client-portal-grid"><Card><CardBody><h3>Recent Projects</h3>{dashboard.projects.length ? dashboard.projects.map(project => <Pressable className="client-portal-list-row button" key={project.id} onClick={() => onProject(project)}><span><strong>{project.name}</strong><small>{project.tasks_done}/{project.tasks_total} tasks · {project.progress_percent}%</small></span><ArrowRight size={14}/></Pressable>) : <EmptyState title="No recent projects" text="Shared projects will appear here."/>}</CardBody></Card><Card><CardBody><h3>Recent Invoices</h3>{dashboard.invoices.length ? dashboard.invoices.map(invoice => <Pressable className="client-portal-list-row button" key={invoice.id} onClick={() => onInvoice(invoice)}><span><strong>{invoice.number}</strong><small>{money(invoice.total, invoice.currency)} · Due {date(invoice.due_date)}</small></span><Badge tone={invoice.status === 'paid' ? 'success' : invoice.status === 'overdue' ? 'danger' : 'warning'}>{invoice.status}</Badge></Pressable>) : <EmptyState title="No recent invoices" text="Published invoices will appear here."/>}</CardBody></Card></div><Card mt={14}><CardBody><h3>Published Reports</h3>{dashboard.reports.length ? dashboard.reports.map(report => <Pressable className="client-portal-list-row button" key={report.id} onClick={() => onReport(report)}><span><strong>{report.name}</strong><small>{report.report_type.replaceAll('_', ' ')}</small></span><FileChartColumnIncreasing size={15}/></Pressable>) : <EmptyState title="No published reports" text="Reports will appear here after your workspace team publishes them."/>}</CardBody></Card></>;
}
/** Renders client-visible projects. */
function Projects({ rows, onOpen }: {
    rows: PortalProject[];
    onOpen: (project: PortalProject) => void;
}) {
    if (!rows.length) return <EmptyState icon={<BriefcaseBusiness size={20}/>} title="No projects shared yet" text="Projects will appear here when your workspace team shares them with this client account."/>;
    return <div className="client-portal-card-grid">{rows.map(project => <Card key={project.id}><CardBody><Inline justify="space-between"><BriefcaseBusiness size={18}/><Badge>{project.status}</Badge></Inline><h3>{project.name}</h3><p className="ui-card-description">{project.code || 'Client project'} · Due {date(project.due_date)}</p><Progress value={project.progress_percent}/><div className="client-portal-list-row"><span>{project.tasks_done}/{project.tasks_total} tasks</span><strong>{project.progress_percent}%</strong></div><Button variant="outline" size="sm" onClick={() => onOpen(project)}>View project</Button></CardBody></Card>)}</div>;
}
/** Renders client-visible invoices and their outstanding balances. */
function Invoices({ rows, onOpen, token }: {
    rows: PortalInvoice[];
    onOpen: (invoice: PortalInvoice) => void;
    token: string | null;
}) {
    if (!rows.length) return <EmptyState icon={<CircleDollarSign size={20}/>} title="No invoices shared yet" text="Client-visible invoices and payment status will appear here when billing publishes them."/>;
    return <Card><CardBody><TableWrap label="Client invoices"><thead><tr><th>Invoice</th><th>Issue</th><th>Due</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th><th></th></tr></thead><tbody>{rows.map(invoice => <tr key={invoice.id}><td><strong>{invoice.number}</strong></td><td>{date(invoice.issue_date)}</td><td>{date(invoice.due_date)}</td><td>{money(invoice.total, invoice.currency)}</td><td>{money(invoice.amount_paid, invoice.currency)}</td><td>{money(invoice.amount_due, invoice.currency)}</td><td><Badge tone={invoice.status === 'paid' ? 'success' : invoice.status === 'overdue' ? 'danger' : 'warning'}>{invoice.status}</Badge></td><td><Inline gap={6}><Button size="sm" variant={invoice.amount_due > 0 ? 'primary' : 'ghost'} onClick={() => onOpen(invoice)}>{invoice.amount_due > 0 ? <><CircleDollarSign size={13}/> Pay / View</> : 'View'}</Button><IconButton aria-label={`Download ${invoice.number}`} onClick={() => token && void downloadInvoice(invoice.id, invoice.number, token)}><Download size={13}/></IconButton></Inline></td></tr>)}</tbody></TableWrap></CardBody></Card>;
}
/** Renders client-visible published reports. */
function Reports({ rows, onOpen }: {
    rows: PortalReport[];
    onOpen: (report: PortalReport) => void;
}) {
    if (!rows.length) return <EmptyState icon={<FileChartColumnIncreasing size={20}/>} title="No published reports" text="Reports will appear here after your workspace team publishes them for this client."/>;
    return <div className="client-portal-card-grid">{rows.map(report => <Card key={report.id}><CardBody><FileChartColumnIncreasing size={19}/><h3>{report.name}</h3><p className="ui-card-description">{report.project || report.report_type.replaceAll('_', ' ')}</p><p className="ui-card-description">{date(report.period_start)} – {date(report.period_end)}</p><Button variant="outline" size="sm" onClick={() => onOpen(report)}>Open report</Button></CardBody></Card>)}</div>;
}
/** Renders a report-type-specific snapshot for the client. */
function ReportSnapshot({ report }: {
    report: PortalReport;
}) {
    const snapshot = report.snapshot;
    if (report.report_type === 'project_progress')
        return <div>{(snapshot.projects ?? []).map((project: any) => <Box key={project.id} mb={14}><div className="client-portal-list-row"><strong>{project.name}</strong><span>{project.progress_percent}%</span></div><Progress value={project.progress_percent}/><p className="ui-card-description">{project.tasks_done}/{project.tasks_total} tasks completed · Status {project.status}</p></Box>)}</div>;
    if (report.report_type === 'time_summary')
        return <><div className="client-portal-kpis"><Metric label="Tracked Hours" value={`${snapshot.tracked_hours ?? 0}h`}/><Metric label="Billable Hours" value={`${snapshot.billable_hours ?? 0}h`}/></div>{(snapshot.projects ?? []).map((project: any) => <div className="client-portal-list-row" key={project.id}><span>{project.name}</span><strong>{project.billable_hours}h billable</strong></div>)}</>;
    return <div className="client-portal-kpis"><Metric label="Invoiced" value={money(snapshot.invoiced ?? 0, snapshot.currency ?? 'USD')}/><Metric label="Paid" value={money(snapshot.paid ?? 0, snapshot.currency ?? 'USD')}/><Metric label="Outstanding" value={money(snapshot.outstanding ?? 0, snapshot.currency ?? 'USD')}/><Metric label="Invoices" value={String(snapshot.invoice_count ?? 0)}/></div>;
}
