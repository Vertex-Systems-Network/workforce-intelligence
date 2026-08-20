import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Archive, ArrowUpRight, CircleDollarSign, Download, Ellipsis, FileChartColumnIncreasing, KeyRound, MailPlus, Pencil, Plus, Search, Send, Trash2, UserRoundCog } from 'lucide-react';
import { apiDownload, apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { hasPermission } from '../access';
import { PageLoadingState } from '../components/LoadingStates';
import { useConfirmAction, EmptyState, Alert, Badge, Button, Card, CardBody, DataGrid, Drawer, Dropdown, Field, Input, Modal, FormDialog, SettingRow, Page, PageHeader, Segmented, Select, Textarea, type DataGridColumn, Pressable, Checkbox, Box, Grid, Inline, Stack, Text, Form, Label, Option } from '../design-system';
import { useShellEntitySearch } from '../shellEntityFocus';
import { type Client, type ClientForm, type ClientReport, type Invoice, type PortalAccount, type PortalInvite, type Project, dt, emptyClient, money } from './clients/support';
/** Handles the clients operation for the WorkIntel client. */ export default function Clients() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const workspace = session?.user.workspaces.find(item => item.id === workspaceId);
    const canManage = hasPermission(workspace, 'clients.manage');
    const [tab, setTab] = useState<'clients' | 'invoices' | 'reports'>('clients');
    const [clients, setClients] = useState<Client[]>([]);
    const [projects, setProjects] = useState<Project[]>([]);
    const [invoices, setInvoices] = useState<Invoice[]>([]);
    const [reports, setReports] = useState<ClientReport[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [search, setSearch] = useState('');
    useShellEntitySearch('clients', setSearch, () => setTab('clients'));
    const [clientModal, setClientModal] = useState(false);
    const [editing, setEditing] = useState<Client | null>(null);
    const [clientForm, setClientForm] = useState<ClientForm>(emptyClient);
    const [portalClient, setPortalClient] = useState<Client | null>(null);
    const [portalAccounts, setPortalAccounts] = useState<PortalAccount[]>([]);
    const [portalInvites, setPortalInvites] = useState<PortalInvite[]>([]);
    const [inviteForm, setInviteForm] = useState({ name: '', email: '', expires_hours: '72' });
    const [activationUrl, setActivationUrl] = useState('');
    const [invoiceModal, setInvoiceModal] = useState(false);
    const [invoiceForm, setInvoiceForm] = useState({ client_id: '', issue_date: new Date().toISOString().slice(0, 10), due_date: '', period_start: new Date(new Date().setDate(1)).toISOString().slice(0, 10), period_end: new Date().toISOString().slice(0, 10), tax_percent: '0', discount_total: '0', include_unbilled_time: true, project_id: '', description: '', quantity: '1', unit_price: '0', notes: '', terms: 'Payment due within 14 days.' });
    const [selectedInvoice, setSelectedInvoice] = useState<Invoice | null>(null);
    const [paymentForm, setPaymentForm] = useState({ amount: '', method: 'bank_transfer', reference: '', paid_on: new Date().toISOString().slice(0, 10), note: '' });
    const [paymentModal, setPaymentModal] = useState(false);
    const [reportModal, setReportModal] = useState(false);
    const [reportForm, setReportForm] = useState({ client_id: '', project_id: '', name: 'Client Progress Report', report_type: 'project_progress', period_start: new Date(new Date().setDate(1)).toISOString().slice(0, 10), period_end: new Date().toISOString().slice(0, 10), note: '', publish: true });
    /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        const [c, p] = await Promise.all([apiRequest<{
                data: Client[];
            }>('/api/v1/clients', { workspaceId }), apiRequest<{
                data: Project[];
            }>('/api/v1/projects', { workspaceId })]);
        setClients(c.data);
        setProjects(p.data);
        if (canManage)
            await Promise.all([loadInvoices(true), loadReports(true)]);
        else {
            setInvoices([]);
            setReports([]);
            setTab('clients');
        }
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load client workspace.');
    }
    finally {
        setLoading(false);
    } };
    /** Loads load invoices data required by the current view. */ const loadInvoices = async (silent = false) => { try {
        setInvoices((await apiRequest<{
            data: Invoice[];
        }>('/api/v1/client-invoices', { workspaceId, silent })).data);
    }
    catch (err) {
        if (!silent)
            setError(err instanceof Error ? err.message : 'Could not load invoices.');
    } };
    /** Loads load reports data required by the current view. */ const loadReports = async (silent = false) => { try {
        setReports((await apiRequest<{
            data: ClientReport[];
        }>('/api/v1/client-reports', { workspaceId, silent })).data);
    }
    catch (err) {
        if (!silent)
            setError(err instanceof Error ? err.message : 'Could not load reports.');
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Handles the client projects operation for the WorkIntel client. */ const clientProjects = (clientId: string | number) => projects.filter(p => p.client_id === Number(clientId));
    /** Handles the open create operation for the WorkIntel client. */ const openCreate = () => { setEditing(null); setClientForm(emptyClient); setClientModal(true); };
    /** Handles the open edit operation for the WorkIntel client. */ const openEdit = (c: Client) => { setEditing(c); setClientForm({ name: c.name, company_name: c.company_name ?? '', email: c.email ?? '', billing_email: c.billing_email ?? '', phone: c.phone ?? '', billing_address: c.billing_address ?? '', tax_id: c.tax_id ?? '', currency: c.currency, billing_rate: c.billing_rate ?? '', status: c.status }); setClientModal(true); };
    /** Handles the save client operation for the WorkIntel client. */ const saveClient = async (e: FormEvent) => { e.preventDefault(); setSaving(true); setError(''); try {
        await apiRequest(editing ? `/api/v1/clients/${editing.id}` : '/api/v1/clients', { method: editing ? 'PUT' : 'POST', workspaceId, body: JSON.stringify({ ...clientForm, billing_rate: clientForm.billing_rate ? Number(clientForm.billing_rate) : null }) });
        setClientModal(false);
        await load();
        setMessage(editing ? 'Client updated.' : 'Client created.');
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not save client.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the archive operation for the WorkIntel client. */ const archive = async (c: Client) => { if (!await confirmAction({ title: 'Archive client?', description: `Archive ${c.name}?`, confirmLabel: 'Archive', danger: true }))
        return; try {
        await apiRequest(`/api/v1/clients/${c.id}`, { method: 'DELETE', workspaceId });
        await load();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not archive client.');
    } };
    /** Moves a dependency-free client into the centralized recoverable Trash Center. */ const trashClient = async (c: Client) => { if (!await confirmAction({ title: 'Move client to Trash?', description: `Move ${c.name} to Trash? You can restore it later.`, confirmLabel: 'Move to Trash', danger: true }))
        return; try {
        await apiRequest(`/api/v1/lifecycle/client/${c.id}/trash`, { method: 'POST', workspaceId });
        await load();
        setMessage('Client moved to Trash.');
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not move client to Trash.');
    } };
    /** Handles the open portal operation for the WorkIntel client. */ const openPortal = async (c: Client) => { setPortalClient(c); setActivationUrl(''); setInviteForm({ name: c.name, email: c.email ?? c.billing_email ?? '', expires_hours: '72' }); setSaving(true); try {
        const r = await apiRequest<{
            accounts: PortalAccount[];
            invites: PortalInvite[];
        }>(`/api/v1/clients/${c.id}/portal`, { workspaceId });
        setPortalAccounts(r.accounts);
        setPortalInvites(r.invites);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load portal access.');
        setPortalClient(null);
    }
    finally {
        setSaving(false);
    } };
    /** Handles the invite operation for the WorkIntel client. */ const invite = async (e: FormEvent) => { e.preventDefault(); if (!portalClient)
        return; setSaving(true); try {
        const r = await apiRequest<{
            invite: {
                activation_url: string;
            };
        }>(`/api/v1/clients/${portalClient.id}/portal/invites`, { method: 'POST', workspaceId, body: JSON.stringify({ ...inviteForm, expires_hours: Number(inviteForm.expires_hours) }) });
        setActivationUrl(r.invite.activation_url);
        await openPortal(portalClient);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not create invite.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the toggle portal account operation for the WorkIntel client. */ const togglePortalAccount = async (account: PortalAccount) => { try {
        await apiRequest(`/api/v1/client-portal/accounts/${account.id}`, { method: 'PUT', workspaceId, body: JSON.stringify({ status: account.status === 'active' ? 'suspended' : 'active' }) });
        if (portalClient)
            await openPortal(portalClient);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not update portal account.');
    } };
    /** Handles the open invoice create operation for the WorkIntel client. */ const openInvoiceCreate = () => { const first = clients[0]; setInvoiceForm({ ...invoiceForm, client_id: first ? String(first.id) : '', due_date: new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10), project_id: '' }); setInvoiceModal(true); };
    /** Handles the create invoice operation for the WorkIntel client. */ const createInvoice = async (e: FormEvent) => { e.preventDefault(); setSaving(true); setError(''); try {
        const lines = invoiceForm.description.trim() ? [{ project_id: invoiceForm.project_id ? Number(invoiceForm.project_id) : null, description: invoiceForm.description, quantity: Number(invoiceForm.quantity), unit_price: Number(invoiceForm.unit_price) }] : [];
        await apiRequest('/api/v1/client-invoices', { method: 'POST', workspaceId, body: JSON.stringify({ client_id: Number(invoiceForm.client_id), issue_date: invoiceForm.issue_date, due_date: invoiceForm.due_date, period_start: invoiceForm.period_start || null, period_end: invoiceForm.period_end || null, tax_percent: Number(invoiceForm.tax_percent), discount_total: Number(invoiceForm.discount_total), include_unbilled_time: invoiceForm.include_unbilled_time, project_ids: invoiceForm.project_id ? [Number(invoiceForm.project_id)] : [], lines, notes: invoiceForm.notes, terms: invoiceForm.terms }) });
        setInvoiceModal(false);
        await Promise.all([loadInvoices(), load()]);
        setTab('invoices');
        setMessage('Draft client invoice created.');
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not create invoice.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the open invoice operation for the WorkIntel client. */ const openInvoice = async (i: Invoice) => { setSaving(true); try {
        setSelectedInvoice((await apiRequest<{
            data: Invoice;
        }>(`/api/v1/client-invoices/${i.id}`, { workspaceId })).data);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load invoice.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the send invoice operation for the WorkIntel client. */ const sendInvoice = async (i: Invoice) => { setSaving(true); try {
        await apiRequest(`/api/v1/client-invoices/${i.id}/send`, { method: 'POST', workspaceId });
        await loadInvoices();
        await openInvoice(i);
        setMessage('Invoice is now visible in the client portal.');
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not send invoice.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the record payment operation for the WorkIntel client. */ const recordPayment = async (e: FormEvent) => { e.preventDefault(); if (!selectedInvoice)
        return; setSaving(true); try {
        await apiRequest(`/api/v1/client-invoices/${selectedInvoice.id}/payments`, { method: 'POST', workspaceId, body: JSON.stringify({ ...paymentForm, amount: Number(paymentForm.amount), currency: selectedInvoice.currency }) });
        setPaymentModal(false);
        await loadInvoices();
        await openInvoice(selectedInvoice);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not record payment.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the void invoice operation for the WorkIntel client. */ const voidInvoice = async (i: Invoice) => { if (!await confirmAction({ title: 'Void invoice?', description: `Void ${i.number}?`, confirmLabel: 'Void invoice', danger: true }))
        return; setSaving(true); try {
        await apiRequest(`/api/v1/client-invoices/${i.id}/void`, { method: 'POST', workspaceId });
        setSelectedInvoice(null);
        await loadInvoices();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not void invoice.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the download invoice operation for the WorkIntel client. */ const downloadInvoice = async (i: Invoice) => { try {
        const file = await apiDownload(`/api/v1/client-invoices/${i.id}/pdf`, workspaceId);
        const url = URL.createObjectURL(file.blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = file.filename;
        a.click();
        URL.revokeObjectURL(url);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not download invoice.');
    } };
    /** Handles the download report operation for the WorkIntel client. */ const downloadReport = async (r: ClientReport) => { try {
        const file = await apiDownload(`/api/v1/client-reports/${r.id}/pdf`, workspaceId);
        const url = URL.createObjectURL(file.blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = file.filename;
        a.click();
        URL.revokeObjectURL(url);
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not download client report.');
    } };
    /** Handles the open report create operation for the WorkIntel client. */ const openReportCreate = () => { const first = clients[0]; setReportForm({ ...reportForm, client_id: first ? String(first.id) : '', project_id: '' }); setReportModal(true); };
    /** Handles the create report operation for the WorkIntel client. */ const createReport = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest('/api/v1/client-reports', { method: 'POST', workspaceId, body: JSON.stringify({ ...reportForm, client_id: Number(reportForm.client_id), project_id: reportForm.project_id ? Number(reportForm.project_id) : null }) });
        setReportModal(false);
        await loadReports();
        setTab('reports');
        setMessage(reportForm.publish ? 'Report generated and published.' : 'Report generated as draft.');
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not generate client report.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the publish operation for the WorkIntel client. */ const publish = async (r: ClientReport) => { try {
        await apiRequest(`/api/v1/client-reports/${r.id}/${r.published_at ? 'unpublish' : 'publish'}`, { method: 'POST', workspaceId });
        await loadReports();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not update report visibility.');
    } };
    /** Handles the delete report operation for the WorkIntel client. */ const deleteReport = async (r: ClientReport) => { if (!await confirmAction({ title: 'Delete client report?', description: `Delete ${r.name}?`, confirmLabel: 'Delete', danger: true }))
        return; try {
        await apiRequest(`/api/v1/client-reports/${r.id}`, { method: 'DELETE', workspaceId });
        await loadReports();
    }
    catch (err) {
        setError(err instanceof Error ? err.message : 'Could not delete report.');
    } };
    /** Define the client directory columns for the shared DataGrid V2 surface. */
    const clientColumns: DataGridColumn<Client>[] = [
        { id: 'client', header: 'Client', searchValue: c => `${c.name} ${c.company_name ?? ''}`, sortValue: c => c.name, cell: c => <><strong>{c.name}</strong><div className="ui-card-description">{c.company_name || 'Independent client'}</div></> },
        { id: 'contact', header: 'Contact', searchValue: c => `${c.billing_email ?? c.email ?? ''} ${c.phone ?? ''}`, sortValue: c => c.billing_email ?? c.email ?? '', cell: c => <><div>{c.billing_email || c.email || '—'}</div><div className="ui-card-description">{c.phone || 'No phone'}</div></> },
        { id: 'projects', header: 'Projects', sortValue: c => c.projects_count ?? 0, cell: c => <>{c.projects_count ?? 0} <span className="ui-card-description">({c.active_projects_count ?? 0} active)</span></> },
        { id: 'portal', header: 'Portal', sortValue: c => c.portal_accounts_count ?? 0, cell: c => <Badge tone={(c.portal_accounts_count ?? 0) > 0 ? 'success' : 'neutral'}>{c.portal_accounts_count ?? 0} account(s)</Badge> },
        { id: 'invoices', header: 'Invoices', sortValue: c => c.invoices_count ?? 0, cell: c => c.invoices_count ?? 0 },
        { id: 'reports', header: 'Reports', sortValue: c => c.reports_count ?? 0, defaultHidden: true, cell: c => c.reports_count ?? 0 },
        { id: 'billing', header: 'Billing', sortValue: c => Number(c.billing_rate ?? 0), cell: c => c.billing_rate ? `${money(c.billing_rate, c.currency)}/h` : '—' },
        { id: 'status', header: 'Status', filterValue: c => c.status, filter: { type: 'select', label: 'Status', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }, { value: 'archived', label: 'Archived' }] }, cell: c => <Badge tone={c.status === 'active' ? 'success' : c.status === 'archived' ? 'neutral' : 'warning'}>{c.status}</Badge> },
        { id: 'actions', header: '', hideable: false, cell: c => canManage ? <Dropdown trigger={<Button variant="ghost" size="sm" iconOnly aria-label={`Actions for ${c.name}`}><Ellipsis size={15}/></Button>} items={[{ label: 'Portal access', icon: <KeyRound size={14}/>, onClick: () => void openPortal(c) }, { label: 'Edit client', icon: <Pencil size={14}/>, onClick: () => openEdit(c) }, { separator: true }, { label: 'Archive client', icon: <Archive size={14}/>, onClick: () => void archive(c) }, { label: 'Move to Trash', icon: <Trash2 size={14}/>, danger: true, onClick: () => void trashClient(c) }]}/> : null },
    ];
    /** Define invoice columns with date and status filters. */
    const invoiceColumns: DataGridColumn<Invoice>[] = [
        { id: 'invoice', header: 'Invoice', searchValue: i => i.number, sortValue: i => i.number, cell: i => <Pressable className="wi-link-button" onClick={() => void openInvoice(i)}><strong>{i.number}</strong></Pressable> },
        { id: 'client', header: 'Client', searchValue: i => i.client?.company_name ?? i.client?.name ?? '', sortValue: i => i.client?.company_name ?? i.client?.name ?? '', cell: i => i.client?.company_name || i.client?.name || '—' },
        { id: 'issue', header: 'Issue', sortValue: i => i.issue_date, filterValue: i => i.issue_date, filter: { type: 'dateRange', label: 'Issue date' }, cell: i => dt(i.issue_date) },
        { id: 'due', header: 'Due', sortValue: i => i.due_date, filterValue: i => i.due_date, filter: { type: 'dateRange', label: 'Due date' }, cell: i => dt(i.due_date) },
        { id: 'total', header: 'Total', sortValue: i => Number(i.total), cell: i => money(i.total, i.currency) },
        { id: 'paid', header: 'Paid', sortValue: i => Number(i.amount_paid), defaultHidden: true, cell: i => money(i.amount_paid, i.currency) },
        { id: 'outstanding', header: 'Outstanding', sortValue: i => Number(i.amount_due), cell: i => money(i.amount_due, i.currency) },
        { id: 'status', header: 'Status', filterValue: i => i.status, filter: { type: 'select', label: 'Status', options: ['draft', 'sent', 'partial', 'overdue', 'paid', 'void'].map(value => ({ value, label: value[0].toUpperCase() + value.slice(1) })) }, cell: i => <Badge tone={i.status === 'paid' ? 'success' : i.status === 'overdue' ? 'danger' : i.status === 'draft' ? 'neutral' : 'warning'}>{i.status}</Badge> },
        { id: 'actions', header: '', hideable: false, cell: i => <Dropdown trigger={<Button variant="ghost" size="sm" iconOnly aria-label={`Actions for ${i.number}`}><Ellipsis size={15}/></Button>} items={[{ label: 'Open invoice', icon: <ArrowUpRight size={14}/>, onClick: () => void openInvoice(i) }, { label: 'Download PDF', icon: <Download size={14}/>, onClick: () => void downloadInvoice(i) }, ...(i.status === 'draft' ? [{ label: 'Send to client', icon: <Send size={14}/>, onClick: () => void sendInvoice(i) }] : [])]}/> },
    ];
    /** Define client report columns with period and publication filters. */
    const reportColumns: DataGridColumn<ClientReport>[] = [
        { id: 'report', header: 'Report', searchValue: r => `${r.name} ${r.note ?? ''}`, sortValue: r => r.name, cell: r => <><strong>{r.name}</strong><div className="ui-card-description">{r.note || 'Client report snapshot'}</div></> },
        { id: 'client', header: 'Client', searchValue: r => r.client?.company_name ?? r.client?.name ?? '', sortValue: r => r.client?.company_name ?? r.client?.name ?? '', cell: r => r.client?.company_name || r.client?.name || '—' },
        { id: 'project', header: 'Project', searchValue: r => r.project?.name ?? '', sortValue: r => r.project?.name ?? '', cell: r => r.project?.name || 'All client projects' },
        { id: 'type', header: 'Type', filterValue: r => r.report_type, filter: { type: 'select', label: 'Report type', options: Array.from(new Set(reports.map(row => row.report_type))).map(value => ({ value, label: value.replaceAll('_', ' ') })) }, cell: r => r.report_type.replaceAll('_', ' ') },
        { id: 'period', header: 'Period', filterValue: r => r.period_start ?? '', filter: { type: 'dateRange', label: 'Period start' }, sortValue: r => r.period_start ?? '', cell: r => <>{dt(r.period_start)} – {dt(r.period_end)}</> },
        { id: 'visibility', header: 'Visibility', filterValue: r => r.published_at ? 'published' : 'draft', filter: { type: 'select', label: 'Visibility', options: [{ value: 'published', label: 'Published' }, { value: 'draft', label: 'Draft' }] }, cell: r => <Badge tone={r.published_at ? 'success' : 'neutral'}>{r.published_at ? 'Published' : 'Draft'}</Badge> },
        { id: 'actions', header: '', hideable: false, cell: r => <Dropdown trigger={<Button variant="ghost" size="sm" iconOnly aria-label={`Actions for ${r.name}`}><Ellipsis size={15}/></Button>} items={[{ label: 'Download PDF', icon: <Download size={14}/>, onClick: () => void downloadReport(r) }, { label: r.published_at ? 'Unpublish' : 'Publish', icon: <Send size={14}/>, onClick: () => void publish(r) }, { separator: true }, { label: 'Delete report', icon: <Trash2 size={14}/>, danger: true, onClick: () => void deleteReport(r) }]}/> },
    ];
    const invoiceLineColumns: DataGridColumn<InvoiceLine>[] = [
        { id: 'description', header: 'Description', searchValue: row => `${row.description} ${row.project?.name ?? ''} ${row.source_type}`, cell: row => <Stack gap={2}><Text weight={650}>{row.description}</Text><Text size={10.5} color="var(--text-3)">{row.project?.name || row.source_type}</Text></Stack> },
        { id: 'qty', header: 'Qty', sortValue: row => Number(row.quantity), cell: row => Number(row.quantity).toFixed(2) },
        { id: 'rate', header: 'Rate', sortValue: row => Number(row.unit_price), cell: row => selectedInvoice ? money(row.unit_price, selectedInvoice.currency) : String(row.unit_price) },
        { id: 'amount', header: 'Amount', sortValue: row => Number(row.amount), cell: row => selectedInvoice ? money(row.amount, selectedInvoice.currency) : String(row.amount) },
    ];
    if (loading && !clients.length)
        return <PageLoadingState title="Loading clients" description="Preparing client directory, invoices and portal reports."/>;
    return <Page><PageHeader title="Clients" description="Client directory, portal access, customer invoices and published reports" actions={canManage ? <Segmented value={tab} onChange={setTab} options={[{ value: 'clients', label: 'Directory' }, { value: 'invoices', label: 'Invoices' }, { value: 'reports', label: 'Client Reports' }]}/> : <Badge>Read only</Badge>}/>
    {error && <Alert tone="danger" mb={12}>{error}</Alert>}{message && <Alert tone="success" mb={12}>{message}</Alert>}
    {tab === 'clients' && <DataGrid rows={clients} columns={clientColumns} rowKey={row => row.id} persistKey="clients.directory" onRefresh={load} searchPlaceholder="Search clients, companies, email or phone…" empty={<EmptyState title="No clients yet." text="Create the first client to organize projects, invoices and portal access." action={canManage ? <Button size="sm" onClick={openCreate}><Plus size={13}/> Add client</Button> : undefined}/>} toolbar={canManage ? <Button variant="primary" size="sm" onClick={openCreate}><Plus size={14}/> Add Client</Button> : undefined} mobileCard={c => <div><Inline justify="space-between" gap={8}><strong>{c.name}</strong><Badge tone={c.status === 'active' ? 'success' : 'neutral'}>{c.status}</Badge></Inline><div className="ui-card-description">{c.company_name || 'Independent client'} · {c.billing_email || c.email || 'No email'}</div><Inline justify="space-between" mt={8}><span>{c.projects_count ?? 0} projects</span>{canManage && <Dropdown trigger={<Button variant="ghost" size="sm" iconOnly aria-label={`Actions for ${c.name}`}><Ellipsis size={14}/></Button>} items={[{ label: 'Portal access', icon: <KeyRound size={14}/>, onClick: () => void openPortal(c) }, { label: 'Edit client', icon: <Pencil size={14}/>, onClick: () => openEdit(c) }]}/>}</Inline></div>}/>}
    {tab === 'invoices' && <DataGrid rows={invoices} columns={invoiceColumns} rowKey={row => row.id} persistKey="clients.invoices" onRefresh={() => loadInvoices()} searchPlaceholder="Search invoice number or client…" toolbar={<><div className="ui-card-description">Client billing · separate from WorkIntel subscription billing</div><Button variant="primary" size="sm" onClick={openInvoiceCreate}><Plus size={14}/> New Invoice</Button></>} empty={<EmptyState title="No client invoices yet." text="Create an invoice when a client is ready to be billed."/>} mobileCard={i => <div><Inline justify="space-between"><strong>{i.number}</strong><Badge tone={i.status === 'paid' ? 'success' : i.status === 'overdue' ? 'danger' : 'warning'}>{i.status}</Badge></Inline><div className="ui-card-description">{i.client?.company_name || i.client?.name} · due {dt(i.due_date)}</div><strong>{money(i.amount_due, i.currency)} outstanding</strong></div>}/>}
    {tab === 'reports' && <DataGrid rows={reports} columns={reportColumns} rowKey={row => row.id} persistKey="clients.reports" onRefresh={() => loadReports()} searchPlaceholder="Search reports, clients or projects…" toolbar={<><div className="ui-card-description">Client-safe report snapshots only.</div><Button variant="primary" size="sm" onClick={openReportCreate}><Plus size={14}/> Generate Client Report</Button></>} empty={<EmptyState title="No client reports yet." text="Generate a report snapshot when you want to share progress with a client."/>}/>}

    <FormDialog open={clientModal} onClose={() => setClientModal(false)} title={editing ? 'Edit client' : 'Add client'} description="Contact and billing identity used in the client portal and invoices." formId="client-form" onSubmit={saveClient} submitLabel="Save Client" loading={saving}><Field label="Client name"><Input value={clientForm.name} onChange={e => setClientForm({ ...clientForm, name: e.target.value })} required/></Field><Field label="Company"><Input value={clientForm.company_name} onChange={e => setClientForm({ ...clientForm, company_name: e.target.value })}/></Field><Grid columns="1fr 1fr" gap={9}><Field label="Primary email"><Input type="email" value={clientForm.email} onChange={e => setClientForm({ ...clientForm, email: e.target.value })}/></Field><Field label="Billing email"><Input type="email" value={clientForm.billing_email} onChange={e => setClientForm({ ...clientForm, billing_email: e.target.value })}/></Field></Grid><Field label="Phone"><Input value={clientForm.phone} onChange={e => setClientForm({ ...clientForm, phone: e.target.value })}/></Field><Field label="Billing address"><Textarea value={clientForm.billing_address} onChange={e => setClientForm({ ...clientForm, billing_address: e.target.value })}/></Field><Grid columns="1fr 1fr" gap={9}><Field label="Tax ID"><Input value={clientForm.tax_id} onChange={e => setClientForm({ ...clientForm, tax_id: e.target.value })}/></Field><Field label="Currency"><Input value={clientForm.currency} maxLength={3} onChange={e => setClientForm({ ...clientForm, currency: e.target.value.toUpperCase() })}/></Field></Grid><Grid columns="1fr 1fr" gap={9}><Field label="Default hourly billing rate"><Input type="number" min="0" step="0.01" value={clientForm.billing_rate} onChange={e => setClientForm({ ...clientForm, billing_rate: e.target.value })}/></Field><Field label="Status"><Select value={clientForm.status} onChange={e => setClientForm({ ...clientForm, status: e.target.value })}><Option value="active">Active</Option><Option value="inactive">Inactive</Option><Option value="archived">Archived</Option></Select></Field></Grid></FormDialog>

    <Drawer open={!!portalClient} onClose={() => setPortalClient(null)} title={portalClient ? `${portalClient.name} · Portal Access` : 'Portal Access'} description="Dedicated external accounts; they do not receive internal workspace membership.">{portalClient && <Stack gap={16}>{activationUrl && <Alert tone="success"><div>Activation URL generated. Copy it now; the raw token is not stored.</div><Input readOnly value={activationUrl} onFocus={e => e.currentTarget.select()} mt={8}/></Alert>}<Card><CardBody><Box as="h3" mt={0}>Create invitation</Box><Form onSubmit={invite} gap={9}><Field label="Contact name"><Input value={inviteForm.name} onChange={e => setInviteForm({ ...inviteForm, name: e.target.value })} required/></Field><Field label="Email"><Input type="email" value={inviteForm.email} onChange={e => setInviteForm({ ...inviteForm, email: e.target.value })} required/></Field><Field label="Expires"><Select value={inviteForm.expires_hours} onChange={e => setInviteForm({ ...inviteForm, expires_hours: e.target.value })}><Option value="24">24 hours</Option><Option value="72">72 hours</Option><Option value="168">7 days</Option></Select></Field><Button variant="primary" type="submit" loading={saving}><MailPlus size={14}/> Generate Activation Link</Button></Form></CardBody></Card><Card><CardBody><Box as="h3" mt={0}>Portal accounts</Box>{portalAccounts.length ? portalAccounts.map(a => <div className="ui-menu-item" key={a.id}><UserRoundCog size={14}/><Box flex={1}><Text as="strong" size={12}>{a.name}</Text><div className="ui-card-description">{a.email} · Last login {dt(a.last_login_at)}</div></Box><Badge tone={a.status === 'active' ? 'success' : 'neutral'}>{a.status}</Badge><Button size="sm" variant="ghost" onClick={() => void togglePortalAccount(a)}>{a.status === 'active' ? 'Suspend' : 'Activate'}</Button></div>) : <div className="ui-card-description">No activated portal accounts yet.</div>}</CardBody></Card></Stack>}</Drawer>

    <FormDialog open={invoiceModal} onClose={() => setInvoiceModal(false)} title="New Client Invoice" description="Generate from approved unbilled time and/or add a manual line." size="lg" formId="invoice-create" onSubmit={createInvoice} submitLabel="Create Draft" loading={saving}><Field label="Client"><Select value={invoiceForm.client_id} onChange={e => setInvoiceForm({ ...invoiceForm, client_id: e.target.value, project_id: '' })} required>{clients.map(c => <Option key={c.id} value={c.id}>{c.company_name || c.name}</Option>)}</Select></Field><Grid columns="1fr 1fr" gap={9}><Field label="Issue date"><Input type="date" value={invoiceForm.issue_date} onChange={e => setInvoiceForm({ ...invoiceForm, issue_date: e.target.value })}/></Field><Field label="Due date"><Input type="date" value={invoiceForm.due_date} onChange={e => setInvoiceForm({ ...invoiceForm, due_date: e.target.value })}/></Field></Grid><Grid columns="1fr 1fr" gap={9}><Field label="Period start"><Input type="date" value={invoiceForm.period_start} onChange={e => setInvoiceForm({ ...invoiceForm, period_start: e.target.value })}/></Field><Field label="Period end"><Input type="date" value={invoiceForm.period_end} onChange={e => setInvoiceForm({ ...invoiceForm, period_end: e.target.value })}/></Field></Grid><Field label="Project (optional)"><Select value={invoiceForm.project_id} onChange={e => setInvoiceForm({ ...invoiceForm, project_id: e.target.value })}><Option value="">All client projects</Option>{clientProjects(invoiceForm.client_id).map(p => <Option key={p.id} value={p.id}>{p.name}</Option>)}</Select></Field><SettingRow title="Include approved unbilled time" description="Pull approved, not-yet-billed time from the selected client/project period." control={<Checkbox checked={invoiceForm.include_unbilled_time} onChange={e => setInvoiceForm({ ...invoiceForm, include_unbilled_time: e.target.checked })}/>}/><Box borderTop="1px solid var(--border)" pt={10}><div className="ui-label">Optional manual line</div><Field label="Description"><Input value={invoiceForm.description} onChange={e => setInvoiceForm({ ...invoiceForm, description: e.target.value })} placeholder="Design retainer, consulting fee…"/></Field><Grid columns="1fr 1fr" gap={9}><Field label="Quantity"><Input type="number" min="0" step="0.01" value={invoiceForm.quantity} onChange={e => setInvoiceForm({ ...invoiceForm, quantity: e.target.value })}/></Field><Field label="Unit price"><Input type="number" min="0" step="0.01" value={invoiceForm.unit_price} onChange={e => setInvoiceForm({ ...invoiceForm, unit_price: e.target.value })}/></Field></Grid></Box><Grid columns="1fr 1fr" gap={9}><Field label="Tax %"><Input type="number" min="0" max="100" step="0.01" value={invoiceForm.tax_percent} onChange={e => setInvoiceForm({ ...invoiceForm, tax_percent: e.target.value })}/></Field><Field label="Discount"><Input type="number" min="0" step="0.01" value={invoiceForm.discount_total} onChange={e => setInvoiceForm({ ...invoiceForm, discount_total: e.target.value })}/></Field></Grid><Field label="Notes"><Textarea value={invoiceForm.notes} onChange={e => setInvoiceForm({ ...invoiceForm, notes: e.target.value })}/></Field><Field label="Terms"><Textarea value={invoiceForm.terms} onChange={e => setInvoiceForm({ ...invoiceForm, terms: e.target.value })}/></Field></FormDialog>

    <Drawer open={!!selectedInvoice} onClose={() => setSelectedInvoice(null)} title={selectedInvoice?.number || 'Invoice'} description={selectedInvoice ? `${selectedInvoice.client?.company_name || selectedInvoice.client?.name || ''} · ${dt(selectedInvoice.issue_date)} → ${dt(selectedInvoice.due_date)}` : undefined} footer={selectedInvoice ? <Inline gap={7} wrap="wrap"><Button size="sm" variant="outline" onClick={() => void downloadInvoice(selectedInvoice)}><Download size={13}/> PDF</Button>{selectedInvoice.status === 'draft' && <Button size="sm" variant="primary" loading={saving} onClick={() => void sendInvoice(selectedInvoice)}><Send size={13}/> Send</Button>}{['sent', 'partial', 'overdue'].includes(selectedInvoice.status) && <Button size="sm" variant="primary" onClick={() => { setPaymentForm({ ...paymentForm, amount: String(selectedInvoice.amount_due) }); setPaymentModal(true); }}><CircleDollarSign size={13}/> Record Payment</Button>}{selectedInvoice.status !== 'paid' && selectedInvoice.status !== 'void' && <Button size="sm" variant="ghost" onClick={() => void voidInvoice(selectedInvoice)}>Void</Button>}</Inline> : undefined}>{selectedInvoice && <Stack gap={14}><Grid columns="repeat(3,1fr)" gap={8}><Card><CardBody><div className="ui-card-description">Total</div><strong>{money(selectedInvoice.total, selectedInvoice.currency)}</strong></CardBody></Card><Card><CardBody><div className="ui-card-description">Paid</div><strong>{money(selectedInvoice.amount_paid, selectedInvoice.currency)}</strong></CardBody></Card><Card><CardBody><div className="ui-card-description">Outstanding</div><strong>{money(selectedInvoice.amount_due, selectedInvoice.currency)}</strong></CardBody></Card></Grid><Badge tone={selectedInvoice.status === 'paid' ? 'success' : selectedInvoice.status === 'overdue' ? 'danger' : 'warning'}>{selectedInvoice.status}</Badge><DataGrid rows={selectedInvoice.lines ?? []} columns={invoiceLineColumns} rowKey={row => row.id} persistKey={`clients.invoice-lines.${selectedInvoice.id}`} searchable={false} defaultPageSize={10} empty={<Text color="var(--text-3)">No invoice lines.</Text>}/>{selectedInvoice.payments?.length ? <Card><CardBody><Box as="h3" mt={0}>Payments</Box>{selectedInvoice.payments.map(p => <div className="ui-menu-item" key={p.id}><CircleDollarSign size={14}/><Box flex={1}><strong>{money(p.amount, selectedInvoice.currency)}</strong><div className="ui-card-description">{p.method.replaceAll('_', ' ')} · {dt(p.paid_on)} {p.reference ? `· ${p.reference}` : ''}</div></Box></div>)}</CardBody></Card> : null}</Stack>}</Drawer>

    <FormDialog open={paymentModal} onClose={() => setPaymentModal(false)} title="Record Client Payment" formId="payment-form" onSubmit={recordPayment} submitLabel="Record Payment" loading={saving}><Field label="Amount"><Input type="number" min="0.01" step="0.01" value={paymentForm.amount} onChange={e => setPaymentForm({ ...paymentForm, amount: e.target.value })} required/></Field><Field label="Method"><Select value={paymentForm.method} onChange={e => setPaymentForm({ ...paymentForm, method: e.target.value })}><Option value="bank_transfer">Bank transfer</Option><Option value="card">Card</Option><Option value="cash">Cash</Option><Option value="check">Check</Option><Option value="manual">Manual</Option><Option value="other">Other</Option></Select></Field><Field label="Paid on"><Input type="date" value={paymentForm.paid_on} onChange={e => setPaymentForm({ ...paymentForm, paid_on: e.target.value })}/></Field><Field label="Reference"><Input value={paymentForm.reference} onChange={e => setPaymentForm({ ...paymentForm, reference: e.target.value })}/></Field><Field label="Note"><Textarea value={paymentForm.note} onChange={e => setPaymentForm({ ...paymentForm, note: e.target.value })}/></Field></FormDialog>

    <FormDialog open={reportModal} onClose={() => setReportModal(false)} title="Generate Client Report" description="Every report is hard-scoped to the selected client before its snapshot is created." formId="client-report-form" onSubmit={createReport} submitLabel="Generate" loading={saving}><Field label="Client"><Select value={reportForm.client_id} onChange={e => setReportForm({ ...reportForm, client_id: e.target.value, project_id: '' })}>{clients.map(c => <Option key={c.id} value={c.id}>{c.company_name || c.name}</Option>)}</Select></Field><Field label="Report name"><Input value={reportForm.name} onChange={e => setReportForm({ ...reportForm, name: e.target.value })}/></Field><Field label="Type"><Select value={reportForm.report_type} onChange={e => setReportForm({ ...reportForm, report_type: e.target.value, project_id: e.target.value === 'financial_summary' ? '' : reportForm.project_id })}><Option value="project_progress">Project Progress</Option><Option value="time_summary">Time Summary</Option><Option value="financial_summary">Financial Summary</Option></Select></Field>{reportForm.report_type !== 'financial_summary' && <Field label="Project"><Select value={reportForm.project_id} onChange={e => setReportForm({ ...reportForm, project_id: e.target.value })}><Option value="">All client projects</Option>{clientProjects(reportForm.client_id).map(p => <Option key={p.id} value={p.id}>{p.name}</Option>)}</Select></Field>}<Grid columns="1fr 1fr" gap={9}><Field label="From"><Input type="date" value={reportForm.period_start} onChange={e => setReportForm({ ...reportForm, period_start: e.target.value })}/></Field><Field label="To"><Input type="date" value={reportForm.period_end} onChange={e => setReportForm({ ...reportForm, period_end: e.target.value })}/></Field></Grid><Field label="Note"><Textarea value={reportForm.note} onChange={e => setReportForm({ ...reportForm, note: e.target.value })}/></Field><SettingRow title="Publish to client portal" description="Make the generated snapshot visible to the client immediately." control={<Checkbox checked={reportForm.publish} onChange={e => setReportForm({ ...reportForm, publish: e.target.checked })}/>}/></FormDialog>
  </Page>;
}
