import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { DndContext, PointerSensor, closestCenter, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, arrayMove, verticalListSortingStrategy, useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Archive, ArrowUpRight, Blocks, BookOpen, CheckCircle2, ChevronRight, Copy, Eye, FilePlus2, FileText, FormInput, Globe2, GripVertical, Image as ImageIcon, Languages, LayoutTemplate, Link2, ListPlus, Menu, MessageSquare, Monitor, MoreHorizontal, PanelRight, Plus, Redo2, RotateCcw, Save, Search, Send, Settings2, Smartphone, Tablet, Trash2, Undo2, Users, WandSparkles, ZoomIn, ZoomOut } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import RichTextEditor from '../components/RichTextEditor';
import { MediaPicker } from '../media/MediaPicker';
import type { MediaAsset } from '../media/types';
import { Alert, Badge, Button, Card, CardBody, CardHeader, DataGrid, type DataGridColumn, type DataGridQuery, dataGridQueryParams, Dropdown, EmptyState, Field, FormActions, FormDialog, FormGrid, FormSection, Grid, IconButton, Inline, Input, Modal, Page, PageHeader, RefreshButton, SearchInput, Segmented, Select, SettingRow, Stack, Switch, Tabs, Text, Textarea, Tooltip, Pressable, Option, Box } from '../design-system';
import { useLocalization } from '../i18n/LocalizationContext';
import WebsiteRenderer from '../website/WebsiteRenderer';
import type { PublicWebsitePayload, WebsitePublicForm, WebsiteSchema, WebsiteSection } from '../website/types';
import { FormEditorModal, PageSeoInspector, ReviewInspector, SectionInspector, SortableSection, autosaveMetadata, bindPreviewTokens, previewPayload, schemaMediaIds, sectionDefaults, websitePageAudit, type AutosaveDraft, type Domain, type InspectorPanel, type LeadRow, type LeftPanel, type OverviewPayload, type PageRow, type PreflightResult, type PreviewTokenRow, type ReusableSection, type ReviewComment, type SharePreview, type Site, type VersionRow, type WebsiteForm } from '../website/studio/WebsiteStudioSupport';
import './website-studio.css';
/** Render the complete Website Studio shell while focused editor concerns live in support modules. */
export default function WebsiteStudio() {
    const { session } = useAuth(), { t } = useLocalization(), workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [data, setData] = useState<OverviewPayload | null>(null), [loading, setLoading] = useState(true), [error, setError] = useState(''), [tab, setTab] = useState<'pages' | 'site' | 'forms' | 'leads' | 'components'>('pages');
    const [selectedPageId, setSelectedPageId] = useState<number | null>(null), [page, setPage] = useState<PageRow | null>(null), [schema, setSchema] = useState<WebsiteSchema>({ schema_version: 1, sections: [] }), [versions, setVersions] = useState<VersionRow[]>([]), [selectedSectionId, setSelectedSectionId] = useState<string | null>(null), [saving, setSaving] = useState(false), [viewport, setViewport] = useState<'desktop' | 'tablet' | 'mobile'>('desktop');
    const [createPageOpen, setCreatePageOpen] = useState(false), [pageDraft, setPageDraft] = useState({ title: '', page_type: 'custom', slug: '', language: 'en', is_home: false }), [siteDraft, setSiteDraft] = useState<Site | null>(null);
    const [mediaPicker, setMediaPicker] = useState<{
        open: boolean;
        mode: 'single' | 'gallery';
        sectionId?: string;
        field?: 'media_id' | 'og_media_id';
    }>({ open: false, mode: 'single' });
    const [formOpen, setFormOpen] = useState(false), [formDraft, setFormDraft] = useState<Partial<WebsiteForm>>({ name: '', slug: '', status: 'active', fields: [{ id: 'name', type: 'text', label: 'Name', required: true }, { id: 'email', type: 'email', label: 'Email', required: true }, { id: 'message', type: 'textarea', label: 'Message', required: true }], settings: { require_consent: false }, notification_emails: [] });
    const [reusableOpen, setReusableOpen] = useState(false), [reusableDraft, setReusableDraft] = useState({ name: '', is_global: false });
    const [leadRows, setLeadRows] = useState<LeadRow[]>([]), [leadTotal, setLeadTotal] = useState(0), [leadQuery, setLeadQuery] = useState<DataGridQuery>({ page: 1, pageSize: 25, search: '', sorting: [{ id: 'submitted_at', desc: true }], filters: [] });
    const [zoom, setZoom] = useState(100), [sectionSearch, setSectionSearch] = useState(''), [focusMode, setFocusMode] = useState(false), [undoStack, setUndoStack] = useState<WebsiteSchema[]>([]), [redoStack, setRedoStack] = useState<WebsiteSchema[]>([]);
    const [leftPanel, setLeftPanel] = useState<LeftPanel>('pages'), [inspectorPanel, setInspectorPanel] = useState<InspectorPanel>('content'), [dirty, setDirty] = useState(false), [autosaveState, setAutosaveState] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle'), [lastAutosavedAt, setLastAutosavedAt] = useState<string | null>(null), [preflight, setPreflight] = useState<PreflightResult | null>(null);
    const [comments, setComments] = useState<ReviewComment[]>([]), [previewTokens, setPreviewTokens] = useState<PreviewTokenRow[]>([]), [commentDraft, setCommentDraft] = useState(''), [sharePreview, setSharePreview] = useState<SharePreview | null>(null), [shareOpen, setShareOpen] = useState(false), [shareHours, setShareHours] = useState(72);
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));
    /** Reloads the authenticated Website Studio overview and preserves current page selection when possible. */
    const load = async () => {
        if (!workspaceId)
            return;
        setLoading(true);
        setError('');
        try {
            const response = await apiRequest<OverviewPayload>('/api/v1/website/overview', { workspaceId });
            setData(response);
            setSiteDraft(response.site);
            const nextId = selectedPageId && response.pages.some(item => item.id === selectedPageId) ? selectedPageId : response.pages[0]?.id ?? null;
            setSelectedPageId(nextId);
            if (nextId)
                await loadPage(nextId);
        }
        catch (err) {
            setError(err instanceof Error ? err.message : 'Could not load Website Studio.');
        }
        finally {
            setLoading(false);
        }
    };
    /** Loads one page schema and immutable revision list into the designer. */
    const loadPage = async (id: number) => {
        const response = await apiRequest<{
            page: PageRow;
            schema: WebsiteSchema;
            draft?: AutosaveDraft | null;
            comments?: ReviewComment[];
            preview_tokens?: PreviewTokenRow[];
            versions: VersionRow[];
        }>(`/api/v1/website/pages/${id}`, { workspaceId, silent: true });
        const draft = response.draft ?? null, nextSchema = draft?.schema || response.schema || { schema_version: 1, sections: [] }, nextPage = draft ? { ...response.page, ...draft.metadata } : response.page;
        setPage(nextPage);
        setSchema(nextSchema);
        setUndoStack([]);
        setRedoStack([]);
        setVersions(response.versions || []);
        setComments(response.comments || []);
        setPreviewTokens(response.preview_tokens || []);
        setSelectedSectionId(nextSchema.sections?.[0]?.id ?? null);
        setDirty(Boolean(draft));
        setAutosaveState(draft ? 'saved' : 'idle');
        setLastAutosavedAt(draft?.updated_at ?? null);
        setPreflight(null);
    };
    useEffect(() => { void load(); }, [workspaceId]);
    useEffect(() => {
        if (selectedPageId && page?.id !== selectedPageId)
            void loadPage(selectedPageId);
    }, [selectedPageId]);
    /** Saves the current page as a new immutable version. */
    const savePage = async () => {
        if (!page)
            return;
        setSaving(true);
        try {
            const response = await apiRequest<{
                data: PageRow;
            }>(`/api/v1/website/pages/${page.id}`, { method: 'PUT', workspaceId, body: JSON.stringify({ ...page, schema, change_note: 'Manual save from Website Studio V3' }) });
            setPage(response.data);
            setDirty(false);
            setAutosaveState('idle');
            setLastAutosavedAt(null);
            await loadPage(page.id);
            await refreshOverview();
        }
        finally {
            setSaving(false);
        }
    };
    /** Runs server-side preflight against the exact in-memory page before publish. */
    const runPreflight = async () => {
        if (!page)
            return null;
        const response = await apiRequest<{
            data: PreflightResult;
        }>(`/api/v1/website/pages/${page.id}/preflight`, { method: 'POST', workspaceId, body: JSON.stringify({ schema, metadata: autosaveMetadata(page) }) });
        setPreflight(response.data);
        return response.data;
    };
    /** Converts the exact editor state into the immutable version reviewers and publishers will see. */
    const stageForReview = async () => {
        if (!page)
            return null;
        const result = await runPreflight();
        if (!result?.ready) {
            setInspectorPanel('seo');
            return null;
        }
        setSaving(true);
        try {
            const response = await apiRequest<{
                data: PageRow;
            }>(`/api/v1/website/pages/${page.id}/stage`, { method: 'POST', workspaceId, body: JSON.stringify({ schema, metadata: autosaveMetadata(page) }) });
            await loadPage(page.id);
            await refreshOverview();
            return response.data;
        }
        finally {
            setSaving(false);
        }
    };
    /** Creates a revocable share link for the immutable staging version only. */
    const createSharePreview = async () => {
        if (!page)
            return;
        if (!page.staged_version) {
            const staged = await stageForReview();
            if (!staged)
                return;
        }
        const response = await apiRequest<{
            data: {
                url: string;
                version: number;
                expires_at: string;
            };
        }>(`/api/v1/website/pages/${page.id}/preview-tokens`, { method: 'POST', workspaceId, body: JSON.stringify({ expires_hours: shareHours }) });
        setSharePreview({ ...response.data, url: new URL(response.data.url, window.location.origin).toString() });
        setShareOpen(true);
        await loadPage(page.id);
    };
    /** Publishes the current immutable staging version; later editor changes remain drafts. */
    const publish = async () => {
        if (!page?.staged_version) {
            setInspectorPanel('review');
            return;
        }
        setSaving(true);
        try {
            await apiRequest(`/api/v1/website/pages/${page.id}/publish`, { method: 'POST', workspaceId });
            await loadPage(page.id);
            await refreshOverview();
        }
        finally {
            setSaving(false);
        }
    };
    /** Adds a review comment to the selected layer or whole page. */
    const addComment = async () => {
        if (!page || !commentDraft.trim())
            return;
        await apiRequest(`/api/v1/website/pages/${page.id}/comments`, { method: 'POST', workspaceId, body: JSON.stringify({ message: commentDraft.trim(), section_id: selectedSectionId || null }) });
        setCommentDraft('');
        await loadPage(page.id);
        setInspectorPanel('review');
    };
    /** Resolves or reopens one review comment while preserving the discussion record. */
    const setCommentStatus = async (comment: ReviewComment, status: 'open' | 'resolved') => {
        await apiRequest(`/api/v1/website/comments/${comment.id}`, { method: 'PATCH', workspaceId, body: JSON.stringify({ status }) });
        if (page)
            await loadPage(page.id);
    };
    /** Revokes one previously issued staging preview token. */
    const revokePreview = async (token: PreviewTokenRow) => {
        await apiRequest(`/api/v1/website/preview-tokens/${token.id}`, { method: 'DELETE', workspaceId });
        if (page)
            await loadPage(page.id);
    };
    /** Refreshes overview-only data without replacing the active editor schema. */
    const refreshOverview = async () => { const response = await apiRequest<OverviewPayload>('/api/v1/website/overview', { workspaceId, silent: true }); setData(response); setSiteDraft(response.site); };
    /** Creates one page from the current modal form. */
    const createPage = async () => {
        const response = await apiRequest<{
            data: PageRow;
        }>('/api/v1/website/pages', { method: 'POST', workspaceId, body: JSON.stringify(pageDraft) });
        setCreatePageOpen(false);
        setPageDraft({ title: '', page_type: 'custom', slug: '', language: data?.site.default_language || 'en', is_home: false });
        await refreshOverview();
        setSelectedPageId(response.data.id);
    };
    /** Applies one editor schema change and records bounded undo history. */
    const applySchema = (updater: (current: WebsiteSchema) => WebsiteSchema) => setSchema(current => {
        const next = updater(current);
        if (next === current)
            return current;
        setUndoStack(stack => [...stack, structuredClone(current)].slice(-50));
        setRedoStack([]);
        setDirty(true);
        setPreflight(null);
        return next;
    });
    /** Updates editable page metadata and marks the immutable version as dirty. */
    const updatePageMeta = (patch: Partial<PageRow>) => { setPage(current => current ? { ...current, ...patch } : current); setDirty(true); setPreflight(null); };
    /** Restores the previous in-memory page schema without creating a server version. */
    const undoSchema = () => {
        const previous = undoStack[undoStack.length - 1];
        if (!previous)
            return;
        setRedoStack(stack => [structuredClone(schema), ...stack].slice(0, 50));
        setUndoStack(stack => stack.slice(0, -1));
        setSchema(structuredClone(previous));
        setDirty(true);
        setPreflight(null);
    };
    /** Re-applies the next in-memory page schema after an undo. */
    const redoSchema = () => {
        const next = redoStack[0];
        if (!next)
            return;
        setUndoStack(stack => [...stack, structuredClone(schema)].slice(-50));
        setRedoStack(stack => stack.slice(1));
        setSchema(structuredClone(next));
        setDirty(true);
        setPreflight(null);
    };
    /** Adds one section after the current selection or at the end of the page. */
    const addSection = (type: string) => { const section = sectionDefaults(type); applySchema(current => ({ ...current, sections: [...current.sections, section] })); setSelectedSectionId(section.id); };
    /** Updates one section settings object without mutating sibling sections. */
    const updateSection = (patch: Record<string, any>) => applySchema(current => ({ ...current, sections: current.sections.map(section => section.id === selectedSectionId ? { ...section, settings: { ...section.settings, ...patch } } : section) }));
    /** Applies a dnd-kit section reorder operation using stable section IDs. */
    const reorder = (event: DragEndEvent) => {
        if (!event.over || event.active.id === event.over.id)
            return;
        applySchema(current => { const from = current.sections.findIndex(item => item.id === event.active.id), to = current.sections.findIndex(item => item.id === event.over?.id); return { ...current, sections: arrayMove(current.sections, from, to) }; });
    };
    /** Duplicates one section and assigns a fresh schema ID. */
    const duplicateSection = (id: string) => applySchema(current => {
        const source = current.sections.find(item => item.id === id);
        if (!source)
            return current;
        const copy = structuredClone(source);
        copy.id = `section_${crypto.randomUUID().replaceAll('-', '').slice(0, 10)}`;
        const index = current.sections.findIndex(item => item.id === id);
        const sections = [...current.sections];
        sections.splice(index + 1, 0, copy);
        setSelectedSectionId(copy.id);
        return { ...current, sections };
    });
    /** Removes one section and selects its nearest remaining neighbor. */
    const removeSection = (id: string) => applySchema(current => {
        const index = current.sections.findIndex(item => item.id === id), sections = current.sections.filter(item => item.id !== id);
        if (selectedSectionId === id)
            setSelectedSectionId(sections[Math.min(index, sections.length - 1)]?.id ?? null);
        return { ...current, sections };
    });
    /** Assigns a Media Library item without persisting private content URLs to the backend schema. */
    const selectMedia = (asset: MediaAsset) => {
        if (mediaPicker.field === 'og_media_id' && page) {
            updatePageMeta({ og_media_id: asset.id });
            return;
        }
        if (!mediaPicker.sectionId)
            return;
        setSelectedSectionId(mediaPicker.sectionId);
        if (mediaPicker.mode === 'gallery') {
            const section = schema.sections.find(item => item.id === mediaPicker.sectionId);
            const ids = Array.from(new Set([...(section?.settings.media_ids || []), asset.id]));
            applySchema(current => ({ ...current, sections: current.sections.map(item => item.id === mediaPicker.sectionId ? { ...item, settings: { ...item.settings, media_ids: ids, _preview_media_items: [...(item.settings._preview_media_items || []), { id: asset.id, uuid: asset.uuid, url: asset.content_url, alt_text: asset.alt_text, name: asset.name }] } } : item) }));
            return;
        }
        applySchema(current => ({ ...current, sections: current.sections.map(item => item.id === mediaPicker.sectionId ? { ...item, settings: { ...item.settings, media_id: asset.id, _preview_media: { id: asset.id, uuid: asset.uuid, url: asset.content_url, alt_text: asset.alt_text, name: asset.name } } } : item) }));
    };
    /** Saves global Website Studio settings. */
    const saveSite = async () => {
        if (!siteDraft)
            return;
        setSaving(true);
        try {
            await apiRequest('/api/v1/website/site', { method: 'PUT', workspaceId, body: JSON.stringify(siteDraft) });
            await refreshOverview();
        }
        finally {
            setSaving(false);
        }
    };
    /** Creates or updates a lead form using the modal field builder. */
    const saveForm = async () => {
        if (!data)
            return;
        const existing = formDraft.id;
        await apiRequest(existing ? `/api/v1/website/forms/${existing}` : '/api/v1/website/forms', { method: existing ? 'PUT' : 'POST', workspaceId, body: JSON.stringify(formDraft) });
        setFormOpen(false);
        setFormDraft({ name: '', slug: '', status: 'active', fields: [{ id: 'name', type: 'text', label: 'Name', required: true }, { id: 'email', type: 'email', label: 'Email', required: true }, { id: 'message', type: 'textarea', label: 'Message', required: true }], settings: { require_consent: false }, notification_emails: [] });
        await refreshOverview();
    };
    /** Saves the selected section into the reusable section library without mutating the page schema. */
    const saveReusable = async () => {
        if (!selectedSection || !reusableDraft.name.trim())
            return;
        await apiRequest('/api/v1/website/reusable-sections', { method: 'POST', workspaceId, body: JSON.stringify({ name: reusableDraft.name.trim(), is_global: reusableDraft.is_global, schema: selectedSection }) });
        setReusableOpen(false);
        setReusableDraft({ name: '', is_global: false });
        await refreshOverview();
    };
    /** Pushes edits from a linked instance back to its global source, then reloads propagated drafts. */
    const updateLinkedSource = async () => {
        if (!selectedSection || !page)
            return;
        const uuid = String(selectedSection.settings?.linked_reusable_uuid || ''), source = data?.reusable_sections.find(item => item.uuid === uuid);
        if (!source)
            return;
        await apiRequest(`/api/v1/website/reusable-sections/${source.id}`, { method: 'PUT', workspaceId, body: JSON.stringify({ name: source.name, is_global: true, schema: selectedSection }) });
        await refreshOverview();
        await loadPage(page.id);
    };
    /** Inserts a reusable component either as a detached copy or a global linked instance. */
    const insertReusable = (section: ReusableSection, linked = false) => { const copy = structuredClone(section.schema); copy.id = `section_${crypto.randomUUID().replaceAll('-', '').slice(0, 10)}`; copy.settings = { ...copy.settings, ...(linked && section.is_global ? { linked_reusable_uuid: section.uuid } : {}) }; applySchema(current => ({ ...current, sections: [...current.sections, copy] })); setSelectedSectionId(copy.id); setLeftPanel('layers'); };
    /** Archives or restores the selected page while preserving every immutable version. */
    const togglePageArchive = async () => {
        if (!page)
            return;
        const restoring = page.status === 'archived';
        await apiRequest(`/api/v1/website/pages/${page.id}${restoring ? '/restore' : ''}`, { method: restoring ? 'POST' : 'DELETE', workspaceId });
        await refreshOverview();
        await loadPage(page.id);
    };
    /** Loads server-side website lead rows from the shared DataGrid query contract. */
    const loadLeads = async (query = leadQuery) => {
        if (!data?.permissions.submissions_view)
            return;
        const params = dataGridQueryParams(query);
        const response = await apiRequest<{
            data: LeadRow[];
            meta: {
                total: number;
            };
        }>(`/api/v1/website/submissions?${params}`, { workspaceId, silent: true });
        setLeadRows(response.data);
        setLeadTotal(response.meta.total);
    };
    useEffect(() => {
        if (tab === 'leads' && data?.permissions.submissions_view)
            void loadLeads(leadQuery);
    }, [tab, data?.permissions.submissions_view, leadQuery.page, leadQuery.pageSize, leadQuery.search, JSON.stringify(leadQuery.sorting), JSON.stringify(leadQuery.filters)]);
    /** Autosaves mutable editor state after a quiet period without creating immutable page versions. */
    useEffect(() => {
        if (!dirty || !page || !data?.permissions.manage)
            return;
        const pageId = page.id;
        setAutosaveState('saving');
        const timer = window.setTimeout(() => {
            void apiRequest<{
                data: AutosaveDraft;
            }>(`/api/v1/website/pages/${pageId}/draft`, { method: 'PUT', workspaceId, silent: true, body: JSON.stringify({ schema, metadata: autosaveMetadata(page) }) }).then(response => { setAutosaveState('saved'); setLastAutosavedAt(response.data.updated_at); }).catch(() => setAutosaveState('error'));
        }, 1200);
        return () => window.clearTimeout(timer);
    }, [dirty, page?.id, page?.title, page?.slug, page?.language, page?.is_home, page?.navigation_visible, page?.navigation_label, page?.seo_title, page?.seo_description, page?.og_media_id, schema, data?.permissions.manage, workspaceId]);
    /** Discards the server autosave and reloads the last immutable version. */
    const discardAutosave = async () => {
        if (!page)
            return;
        await apiRequest(`/api/v1/website/pages/${page.id}/draft`, { method: 'DELETE', workspaceId });
        await loadPage(page.id);
    };
    /** Provides editor keyboard shortcuts while preserving native text editing behavior. */
    useEffect(() => {
        /** Handle editor-level keyboard shortcuts for save, undo and redo commands. */
        const handler = (event: KeyboardEvent) => {
            const command = event.ctrlKey || event.metaKey;
            if (!command)
                return;
            const key = event.key.toLowerCase();
            if (key === 's') {
                event.preventDefault();
                if (data?.permissions.manage)
                    void savePage();
                return;
            }
            if (key === 'z' && !event.shiftKey) {
                event.preventDefault();
                undoSchema();
                return;
            }
            if ((key === 'z' && event.shiftKey) || key === 'y') {
                event.preventDefault();
                redoSchema();
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [data?.permissions.manage, page, schema, undoStack, redoStack]);
    const selectedSection = schema.sections.find(item => item.id === selectedSectionId) || null;
    const referencedMediaIds = useMemo(() => schemaMediaIds(schema), [schema]);
    const preview = page && siteDraft ? previewPayload(siteDraft, page, { ...schema, sections: schema.sections.map(section => ({ ...section, settings: { ...section.settings, media: section.settings._preview_media, media_items: section.settings._preview_media_items } })) }, data?.forms || [], data?.pages || []) : null;
    const publicUrl = data && page ? `/site/${session?.user.workspaces.find(w => w.id === workspaceId)?.slug || ''}${page.is_home ? '' : `/${page.slug}`}` : '';
    const pageQualityIssues = useMemo(() => websitePageAudit(page, schema), [page, schema]);
    const leadColumns: DataGridColumn<LeadRow>[] = [
        { id: 'submitted_at', header: 'Submitted', sortable: true, value: row => row.submitted_at, filter: { type: 'dateRange', label: 'Submitted' }, cell: row => new Date(row.submitted_at).toLocaleString() },
        { id: 'form', header: 'Form', sortable: true, value: row => row.form, cell: row => <strong>{row.form}</strong> },
        { id: 'status', header: 'Status', sortable: true, value: row => row.status, filter: { type: 'select', label: 'Status', options: ['new', 'contacted', 'qualified', 'closed', 'spam', 'archived'].map(value => ({ value, label: value })) }, cell: row => <Badge tone={row.status === 'new' ? 'accent' : row.status === 'spam' ? 'danger' : 'neutral'}>{row.status}</Badge> },
        { id: 'payload', header: 'Lead', value: row => Object.values(row.payload).join(' '), cell: row => <div className="website-lead-summary">{Object.entries(row.payload).slice(0, 3).map(([key, value]) => <span key={key}><b>{key.replaceAll('_', ' ')}:</b> {String(value ?? '')}</span>)}</div> },
        { id: 'actions', header: '', hideable: false, sortable: false, cell: row => <Dropdown trigger={<IconButton variant="ghost" size="sm" aria-label="Lead actions"><MoreHorizontal size={14}/></IconButton>} items={['contacted', 'qualified', 'closed', 'spam', 'archived'].map(status => ({ label: `Mark ${status}`, onClick: async () => { await apiRequest(`/api/v1/website/submissions/${row.id}`, { method: 'PATCH', workspaceId, body: JSON.stringify({ status }) }); await loadLeads(); } }))}/> }
    ];
    if (loading && !data)
        return <Page><PageHeader title={t('page.website.title')} description={t('common.loading')}/><Stack><Card><CardBody>{t('common.loading')}</CardBody></Card></Stack></Page>;
    if (error && !data)
        return <Page><PageHeader title={t('page.website.title')}/><Alert tone="danger">{error}</Alert><Button onClick={() => void load()}>{t('common.retry')}</Button></Page>;
    if (!data || !siteDraft)
        return null;
    return <Page className="website-studio-page"><PageHeader title={t('page.website.title')} description={t('page.website.desc')} actions={<Inline><Button variant="outline" onClick={() => window.open(`/site/${session?.user.workspaces.find(w => w.id === workspaceId)?.slug || ''}`, '_blank')}><ArrowUpRight size={14}/> Open website</Button><RefreshButton onRefresh={load}/></Inline>}/>
  <Tabs value={tab} onChange={setTab} tabs={[{ value: 'pages', label: <><LayoutTemplate size={13}/> Pages</> }, { value: 'site', label: <><Settings2 size={13}/> Site</> }, { value: 'forms', label: <><FormInput size={13}/> Forms</> }, { value: 'leads', label: <><Users size={13}/> Leads</> }, { value: 'components', label: <><Blocks size={13}/> Reusable</> }]}/>

  {tab === 'pages' && <div className={`website-builder-shell website-builder-shell--v3${focusMode ? ' is-focus-mode' : ''}`}>
   <aside className="website-builder-left"><div className="website-rail-tabs" role="tablist" aria-label="Website Studio navigator"><Tooltip content="Pages"><IconButton size="sm" variant={leftPanel === 'pages' ? 'primary' : 'ghost'} aria-label="Pages" aria-pressed={leftPanel === 'pages'} onClick={() => setLeftPanel('pages')}><FileText size={14}/></IconButton></Tooltip><Tooltip content="Layers"><IconButton size="sm" variant={leftPanel === 'layers' ? 'primary' : 'ghost'} aria-label="Layers" aria-pressed={leftPanel === 'layers'} onClick={() => setLeftPanel('layers')}><Menu size={14}/></IconButton></Tooltip><Tooltip content="Blocks"><IconButton size="sm" variant={leftPanel === 'blocks' ? 'primary' : 'ghost'} aria-label="Blocks" aria-pressed={leftPanel === 'blocks'} onClick={() => setLeftPanel('blocks')}><Blocks size={14}/></IconButton></Tooltip><Tooltip content="Assets"><IconButton size="sm" variant={leftPanel === 'assets' ? 'primary' : 'ghost'} aria-label="Assets" aria-pressed={leftPanel === 'assets'} onClick={() => setLeftPanel('assets')}><ImageIcon size={14}/></IconButton></Tooltip></div>
    {leftPanel === 'pages' && <><div className="website-builder-panel-title"><span>Pages</span>{data.permissions.manage && <IconButton size="sm" variant="ghost" aria-label="Create page" onClick={() => setCreatePageOpen(true)}><Plus size={14}/></IconButton>}</div><div className="website-page-list">{data.pages.map(item => <Pressable type="button" key={item.id} className={selectedPageId === item.id ? 'is-active' : ''} onClick={() => setSelectedPageId(item.id)}><span><strong>{item.title}</strong><small>{item.language.toUpperCase()} · /{item.is_home ? '' : item.slug}</small></span><Badge tone={item.status === 'published' ? 'success' : item.status === 'archived' ? 'neutral' : 'warning'}>{item.status}</Badge></Pressable>)}</div></>}
    {leftPanel === 'layers' && <><div className="website-builder-panel-title"><span>Layers</span><Badge>{schema.sections.length}</Badge></div><DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={reorder}><SortableContext items={schema.sections.map(item => item.id)} strategy={verticalListSortingStrategy}><div className="website-section-list">{schema.sections.map(section => <SortableSection key={section.id} section={section} selected={section.id === selectedSectionId} onSelect={() => { setSelectedSectionId(section.id); setInspectorPanel('content'); }} onDuplicate={() => duplicateSection(section.id)} onRemove={() => removeSection(section.id)}/>)}</div></SortableContext></DndContext>{!schema.sections.length && <EmptyState title="No layers" text="Add a block to start building this page."/>}</>}
    {leftPanel === 'blocks' && <><div className="website-builder-panel-title"><span>Blocks</span><Badge>{data.catalog.section_types.length}</Badge></div><div className="website-toolbox-search"><SearchInput value={sectionSearch} onChange={event => setSectionSearch(event.target.value)} placeholder="Find block…"/></div><div className="website-toolbox">{data.catalog.section_types.filter(type => type.replaceAll('_', ' ').includes(sectionSearch.trim().toLowerCase())).map(type => <Button key={type} size="sm" variant="ghost" onClick={() => { addSection(type); setLeftPanel('layers'); setInspectorPanel('content'); }}><Plus size={12}/>{type.replaceAll('_', ' ')}</Button>)}</div>{data.reusable_sections.length > 0 && <><div className="website-builder-panel-title"><span>Reusable</span><Badge>{data.reusable_sections.length}</Badge></div><div className="website-reusable-list">{data.reusable_sections.map(section => <Inline key={section.id} gap={4}><Button size="sm" variant="ghost" onClick={() => insertReusable(section)}><Blocks size={12}/><span>{section.name}</span>{section.is_global && <Badge tone="accent">Global</Badge>}</Button>{section.is_global && <Tooltip content="Insert linked global component"><IconButton size="sm" variant="ghost" aria-label={`Insert linked ${section.name}`} onClick={() => insertReusable(section, true)}><Link2 size={12}/></IconButton></Tooltip>}</Inline>)}</div></>}</>}
    {leftPanel === 'assets' && <><div className="website-builder-panel-title"><span>Assets</span><Badge>{referencedMediaIds.length}</Badge></div><Stack p={10} gap={9}><Text size={11} color="var(--text-3)">This page references {referencedMediaIds.length} Media DAM asset{referencedMediaIds.length === 1 ? '' : 's'}. Selection always uses the shared Media Library.</Text>{selectedSection ? <><Button variant="outline" size="sm" onClick={() => setMediaPicker({ open: true, mode: selectedSection.type === 'gallery' ? 'gallery' : 'single', sectionId: selectedSection.id })}><ImageIcon size={13}/> {selectedSection.type === 'gallery' ? 'Add gallery asset' : 'Choose section asset'}</Button><Text size={10} color="var(--text-3)">Selected layer: {selectedSection.type.replaceAll('_', ' ')}</Text></> : <Alert tone="info">Select a layer before assigning section media.</Alert>}<Button size="sm" variant="ghost" onClick={() => setTab('components')}><Blocks size={13}/> Reusable components</Button></Stack></>}
   </aside>
   <section className="website-builder-center">{page ? <><div className="website-builder-toolbar"><div><strong>{page.title}</strong><span>{publicUrl}</span></div><div className="website-preview-controls"><Segmented value={viewport} onChange={setViewport} options={[{ value: 'desktop', label: <Monitor size={14}/> }, { value: 'tablet', label: <Tablet size={14}/> }, { value: 'mobile', label: <Smartphone size={14}/> }]}/><div className="website-history-controls"><IconButton size="sm" variant="ghost" aria-label="Undo" disabled={!undoStack.length} onClick={undoSchema}><Undo2 size={13}/></IconButton><IconButton size="sm" variant="ghost" aria-label="Redo" disabled={!redoStack.length} onClick={redoSchema}><Redo2 size={13}/></IconButton></div><div className="website-zoom-controls"><IconButton size="sm" variant="ghost" aria-label="Zoom out" disabled={zoom <= 50} onClick={() => setZoom(value => Math.max(50, value - 10))}><ZoomOut size={13}/></IconButton><Pressable type="button" onClick={() => setZoom(100)}>{zoom}%</Pressable><IconButton size="sm" variant="ghost" aria-label="Zoom in" disabled={zoom >= 140} onClick={() => setZoom(value => Math.min(140, value + 10))}><ZoomIn size={13}/></IconButton></div></div><Inline gap={6} wrap="wrap"><Badge tone={preflight?.ready === false ? 'danger' : pageQualityIssues.length ? 'warning' : 'success'}>{preflight ? `${preflight.summary.errors} errors · ${preflight.summary.warnings} warnings` : pageQualityIssues.length ? `${pageQualityIssues.length} local issue${pageQualityIssues.length === 1 ? '' : 's'}` : 'Quality ready'}</Badge>{dirty && <Badge tone="warning">Unsaved version</Badge>}<Badge tone={autosaveState === 'error' ? 'danger' : autosaveState === 'saved' ? 'success' : 'neutral'}>{autosaveState === 'saving' ? 'Autosaving…' : autosaveState === 'saved' ? `Autosaved${lastAutosavedAt ? ` · ${new Date(lastAutosavedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : ''}` : autosaveState === 'error' ? 'Autosave failed' : 'Saved version'}</Badge><Button variant="ghost" size="sm" onClick={() => setFocusMode(value => !value)}><PanelRight size={13}/> {focusMode ? 'Show panels' : 'Focus'}</Button>{page.staged_version && <Badge tone={dirty ? 'warning' : 'accent'}>Staging v{page.staged_version}{dirty ? ' · editor ahead' : ''}</Badge>}{data.permissions.manage && <Button variant="outline" loading={saving} disabled={!dirty && autosaveState !== 'saved'} onClick={() => void savePage()}><Save size={14}/> Save version</Button>}{data.permissions.manage && page.status !== 'archived' && <Button variant="outline" loading={saving} onClick={() => void stageForReview()}><CheckCircle2 size={14}/> Stage</Button>}{data.permissions.manage && page.staged_version && <Button variant="ghost" onClick={() => void createSharePreview()}><Link2 size={14}/> Share</Button>}{data.permissions.publish && page.status !== 'archived' && <Button variant="primary" loading={saving} disabled={!page.staged_version} onClick={() => void publish()}><Send size={14}/> Publish staging</Button>}{data.permissions.manage && <Dropdown trigger={<IconButton variant="ghost" aria-label="Page actions"><MoreHorizontal size={14}/></IconButton>} items={[page.status === 'archived' ? { label: 'Restore page', icon: <RotateCcw size={13}/>, onClick: () => void togglePageArchive() } : { label: 'Archive page', icon: <Archive size={13}/>, danger: true, onClick: () => void togglePageArchive() }]}/>}</Inline></div><div className={`website-preview-frame is-${viewport}`}><Box className="website-preview-canvas" width={`${10000 / zoom}%`} transform={`scale(${zoom / 100})`}>{preview && <WebsiteRenderer payload={preview} workspaceSlug={session?.user.workspaces.find(w => w.id === workspaceId)?.slug || ''} basePath="#" preview viewport={viewport}/>}</Box></div></> : <EmptyState icon={<FileText size={28}/>} title="Select a page" text="Choose a page from the Pages rail or create a new page."/>}</section>
   <aside className="website-builder-right">{page ? <><div className="website-inspector-tabs" role="tablist" aria-label="Website Studio inspector">{([{ id: 'content', label: 'Content', icon: <BookOpen size={12}/> }, { id: 'design', label: 'Design', icon: <WandSparkles size={12}/> }, { id: 'settings', label: 'Settings', icon: <Settings2 size={12}/> }, { id: 'effects', label: 'Effects', icon: <WandSparkles size={12}/> }, { id: 'seo', label: 'SEO', icon: <Search size={12}/> }, { id: 'review', label: 'Review', icon: <MessageSquare size={12}/> }] as Array<{
                    id: InspectorPanel;
                    label: string;
                    icon: ReactNode;
                }>).map(item => <Button key={item.id} size="sm" variant={inspectorPanel === item.id ? 'primary' : 'ghost'} onClick={() => setInspectorPanel(item.id)}>{item.icon}{item.label}</Button>)}</div>{inspectorPanel === 'seo' ? <PageSeoInspector page={page} updatePage={updatePageMeta} localIssues={pageQualityIssues} preflight={preflight} onPreflight={runPreflight} onMedia={() => setMediaPicker({ open: true, mode: 'single', field: 'og_media_id' })}/> : inspectorPanel === 'review' ? <ReviewInspector page={page} selectedSectionId={selectedSectionId} comments={comments} commentDraft={commentDraft} setCommentDraft={setCommentDraft} onAdd={() => void addComment()} onStatus={(comment, status) => void setCommentStatus(comment, status)} previews={previewTokens} onCreatePreview={() => void createSharePreview()} onRevoke={(token) => void revokePreview(token)}/> : <SectionInspector panel={inspectorPanel} section={selectedSection} update={updateSection} forms={data.forms} onMedia={(mode) => selectedSection && setMediaPicker({ open: true, mode, sectionId: selectedSection.id })} onSaveReusable={() => {
                        if (!selectedSection)
                            return;
                        setReusableDraft({ name: `${page.title} · ${selectedSection.type.replaceAll('_', ' ')}`, is_global: false });
                        setReusableOpen(true);
                    }} onUpdateLinkedSource={() => void updateLinkedSource()}/>}{inspectorPanel === 'settings' && <FormSection title="Versions & recovery" description={`Current v${page.current_version} · Staged ${page.staged_version ? `v${page.staged_version}` : 'none'} · Published ${page.published_version ? `v${page.published_version}` : 'none'}`}><Stack>{dirty && data.permissions.manage && <Button size="sm" variant="outline" onClick={() => void discardAutosave()}><RotateCcw size={12}/> Discard autosave & reload version</Button>}{versions.slice(0, 12).map(version => <div className="website-version-row" key={version.id}><span><strong>v{version.version}</strong><small>{version.change_note || 'No note'} · {new Date(version.created_at).toLocaleString()}</small></span>{data.permissions.manage && version.version !== page.current_version && <Button size="sm" variant="ghost" onClick={async () => { await apiRequest(`/api/v1/website/pages/${page.id}/versions/${version.id}/restore`, { method: 'POST', workspaceId }); await loadPage(page.id); }}><RotateCcw size={12}/> Restore</Button>}</div>)}</Stack></FormSection>}</> : <EmptyState title="No page selected"/>}</aside>
  </div>}

  {tab === 'site' && <div className="website-settings-grid"><FormSection title="Site identity" description="Global public website identity and publishing state."><FormGrid><Field label="Website name"><Input value={siteDraft.name} onChange={e => setSiteDraft({ ...siteDraft, name: e.target.value })}/></Field><Field label="Status"><Select value={siteDraft.status} onChange={e => setSiteDraft({ ...siteDraft, status: e.target.value })}><Option value="draft">Draft</Option><Option value="published">Published</Option><Option value="offline">Offline</Option></Select></Field><Field label="Default language"><Select value={siteDraft.default_language} onChange={e => setSiteDraft({ ...siteDraft, default_language: e.target.value })}>{localeOptions.map(value => <Option key={value}>{value}</Option>)}</Select></Field><Field label="Custom domain"><Select value={String(siteDraft.custom_domain_id || '')} onChange={e => setSiteDraft({ ...siteDraft, custom_domain_id: e.target.value ? Number(e.target.value) : null })}><Option value="">Use /site workspace URL</Option>{data.domains.map(domain => <Option key={domain.id} value={domain.id}>{domain.hostname} · {domain.status}</Option>)}</Select></Field></FormGrid></FormSection><FormSection title="Theme"><FormGrid columns={3}>{(['background', 'surface', 'text', 'muted', 'primary'] as const).map(key => <Field key={key} label={key}><Input type="color" value={String(siteDraft.theme?.[key] || '#ffffff')} onChange={e => setSiteDraft({ ...siteDraft, theme: { ...siteDraft.theme, [key]: e.target.value } })}/></Field>)}<Field label="Radius"><Input type="number" min={0} max={40} value={Number(siteDraft.theme?.radius || 14)} onChange={e => setSiteDraft({ ...siteDraft, theme: { ...siteDraft.theme, radius: Number(e.target.value) } })}/></Field><Field label="Content width"><Input type="number" min={720} max={1600} value={Number(siteDraft.theme?.content_width || 1180)} onChange={e => setSiteDraft({ ...siteDraft, theme: { ...siteDraft.theme, content_width: Number(e.target.value) } })}/></Field><Field label="Body font"><Select value={String(siteDraft.theme?.font_body || 'Inter')} onChange={e => setSiteDraft({ ...siteDraft, theme: { ...siteDraft.theme, font_body: e.target.value } })}>{['Inter', 'Arial', 'Helvetica', 'Georgia', 'Times New Roman', 'Noto Sans', 'Noto Sans Arabic'].map(font => <Option key={font}>{font}</Option>)}</Select></Field><Field label="Heading font"><Select value={String(siteDraft.theme?.font_heading || siteDraft.theme?.font_body || 'Inter')} onChange={e => setSiteDraft({ ...siteDraft, theme: { ...siteDraft.theme, font_heading: e.target.value } })}>{['Inter', 'Arial', 'Helvetica', 'Georgia', 'Times New Roman', 'Noto Sans', 'Noto Sans Arabic'].map(font => <Option key={font}>{font}</Option>)}</Select></Field><Field label="Base font size"><Input type="number" min={12} max={22} value={Number(siteDraft.theme?.body_size || siteDraft.theme?.font_size || 16)} onChange={e => setSiteDraft({ ...siteDraft, theme: { ...siteDraft.theme, body_size: Number(e.target.value) } })}/></Field><Field label="Heading scale"><Input type="number" min={0.8} max={1.4} step={0.05} value={Number(siteDraft.theme?.heading_scale || 1)} onChange={e => setSiteDraft({ ...siteDraft, theme: { ...siteDraft.theme, heading_scale: Number(e.target.value) } })}/></Field><Field label="Section spacing"><Input type="number" min={32} max={140} value={Number(siteDraft.theme?.section_spacing || 72)} onChange={e => setSiteDraft({ ...siteDraft, theme: { ...siteDraft.theme, section_spacing: Number(e.target.value) } })}/></Field><Field label="Button radius"><Input type="number" min={0} max={40} value={Number(siteDraft.theme?.button_radius || 10)} onChange={e => setSiteDraft({ ...siteDraft, theme: { ...siteDraft.theme, button_radius: Number(e.target.value) } })}/></Field></FormGrid></FormSection><FormSection title="Header"><FormGrid><Switch checked={siteDraft.header_config?.sticky !== false} onChange={checked => setSiteDraft({ ...siteDraft, header_config: { ...siteDraft.header_config, sticky: checked } })} label="Sticky header"/><Switch checked={siteDraft.header_config?.show_navigation !== false} onChange={checked => setSiteDraft({ ...siteDraft, header_config: { ...siteDraft.header_config, show_navigation: checked } })} label="Show navigation"/><Field label="CTA label"><Input value={String(siteDraft.header_config?.cta_label || '')} onChange={e => setSiteDraft({ ...siteDraft, header_config: { ...siteDraft.header_config, cta_label: e.target.value } })}/></Field><Field label="CTA URL"><Input value={String(siteDraft.header_config?.cta_url || '')} onChange={e => setSiteDraft({ ...siteDraft, header_config: { ...siteDraft.header_config, cta_url: e.target.value } })}/></Field></FormGrid></FormSection><FormSection title="Footer & SEO"><FormGrid><Field label="Copyright"><Input value={String(siteDraft.footer_config?.copyright || '')} onChange={e => setSiteDraft({ ...siteDraft, footer_config: { ...siteDraft.footer_config, copyright: e.target.value } })}/></Field><Field label="Default SEO description"><Textarea value={String(siteDraft.seo_defaults?.description || '')} onChange={e => setSiteDraft({ ...siteDraft, seo_defaults: { ...siteDraft.seo_defaults, description: e.target.value } })}/></Field></FormGrid></FormSection><FormActions><Button variant="primary" loading={saving} onClick={() => void saveSite()}><Save size={14}/> Save website settings</Button></FormActions></div>}

  {tab === 'forms' && <Card><CardHeader title="Lead forms" description="Reusable public forms rendered inside website sections." action={data.permissions.forms_manage ? <Button onClick={() => setFormOpen(true)}><Plus size={13}/> New form</Button> : undefined}/><CardBody>{data.forms.length ? <div className="website-form-cards">{data.forms.map(form => <div key={form.id} className="website-form-card"><div><strong>{form.name}</strong><span>/{form.slug} · {form.fields.length} fields · {form.submissions_count || 0} submissions</span></div><Badge tone={form.status === 'active' ? 'success' : 'neutral'}>{form.status}</Badge>{data.permissions.forms_manage && <Button size="sm" variant="outline" onClick={() => { setFormDraft(form); setFormOpen(true); }}>Edit</Button>}</div>)}</div> : <EmptyState icon={<FormInput size={28}/>} title="No website forms yet" text="Create a reusable lead form and place it on any page." action={data.permissions.forms_manage ? <Button onClick={() => setFormOpen(true)}>Create form</Button> : undefined}/>}</CardBody></Card>}

  {tab === 'leads' && <Card><CardHeader title="Website leads" description="Encrypted public form submissions with status workflow."/><CardBody>{data.permissions.submissions_view ? <DataGrid rows={leadRows} columns={leadColumns} rowKey={row => row.id} server totalRows={leadTotal} onQueryChange={setLeadQuery} persistKey="website.leads" searchable searchPlaceholder="Search leads…" onRefresh={() => loadLeads()} defaultSort={{ id: 'submitted_at', direction: 'desc' }} empty={<EmptyState title="No leads yet" text="Website form submissions will appear here."/>}/> : <Alert tone="warning">You do not have permission to view website leads.</Alert>}</CardBody></Card>}

  {tab === 'components' && <Card><CardHeader title="Reusable sections" description="Save approved section patterns and reuse them across pages."/><CardBody>{data.reusable_sections.length ? <div className="website-reusable-grid">{data.reusable_sections.map(section => <Pressable key={section.id} type="button" onClick={() => { setTab('pages'); const copy = structuredClone(section.schema); copy.id = `section_${crypto.randomUUID().replaceAll('-', '').slice(0, 10)}`; applySchema(current => ({ ...current, sections: [...current.sections, copy] })); setSelectedSectionId(copy.id); }}><Blocks size={18}/><strong>{section.name}</strong><span>{section.section_type}</span>{section.is_global && <Badge tone="accent">Global</Badge>}</Pressable>)}</div> : <EmptyState icon={<Blocks size={28}/>} title="No reusable sections" text="Select a page section and save it as a reusable component."/>}</CardBody></Card>}

  <FormDialog open={createPageOpen} onClose={() => setCreatePageOpen(false)} title="Create website page" description="Choose a page type, language and public path." formId="website-page-create" onSubmit={event => { event.preventDefault(); void createPage(); }} submitLabel="Create page" disabled={!pageDraft.title.trim()}><FormGrid><Field label="Title"><Input value={pageDraft.title} onChange={e => setPageDraft({ ...pageDraft, title: e.target.value })}/></Field><Field label="Type"><Select value={pageDraft.page_type} onChange={e => setPageDraft({ ...pageDraft, page_type: e.target.value })}>{data.catalog.page_types.map(type => <Option key={type} value={type}>{type}</Option>)}</Select></Field><Field label="Slug"><Input value={pageDraft.slug} onChange={e => setPageDraft({ ...pageDraft, slug: e.target.value })}/></Field><Field label="Language"><Select value={pageDraft.language} onChange={e => setPageDraft({ ...pageDraft, language: e.target.value })}>{localeOptions.map(value => <Option key={value} value={value}>{value.toUpperCase()}</Option>)}</Select></Field><Switch checked={pageDraft.is_home} onChange={checked => setPageDraft({ ...pageDraft, is_home: checked })} label="Home page"/></FormGrid></FormDialog>

  <FormDialog open={reusableOpen} onClose={() => setReusableOpen(false)} title="Save reusable component" description="Save this block for detached reuse. Global components can also be inserted as linked instances from the Blocks rail." formId="website-reusable-save" onSubmit={event => { event.preventDefault(); void saveReusable(); }} submitLabel="Save component" disabled={!reusableDraft.name.trim()}><Stack><Field label="Component name"><Input value={reusableDraft.name} onChange={e => setReusableDraft({ ...reusableDraft, name: e.target.value })}/></Field><Switch checked={reusableDraft.is_global} onChange={checked => setReusableDraft({ ...reusableDraft, is_global: checked })} label="Mark as global component"/></Stack></FormDialog>
  <FormEditorModal open={formOpen} draft={formDraft} setDraft={setFormDraft} onClose={() => setFormOpen(false)} onSave={saveForm}/>
  <Modal open={shareOpen} onClose={() => setShareOpen(false)} title="Share staging preview" description="This revocable link renders the immutable staged version and never exposes later autosaves."><Stack gap={10}><Field label="Link expiry"><Select value={String(shareHours)} onChange={event => setShareHours(Number(event.target.value))}><Option value="24">24 hours</Option><Option value="72">3 days</Option><Option value="168">7 days</Option></Select></Field>{sharePreview && <><Field label={`Staging v${sharePreview.version}`}><Input readOnly value={sharePreview.url}/></Field><Inline gap={8}><Button variant="primary" onClick={() => void navigator.clipboard?.writeText(sharePreview.url)}><Copy size={13}/> Copy link</Button><Button variant="outline" onClick={() => window.open(sharePreview.url, '_blank')}><ArrowUpRight size={13}/> Open preview</Button></Inline><Text size={10.5} color="var(--text-3)">Expires {new Date(sharePreview.expires_at).toLocaleString()}.</Text></>}</Stack></Modal>
  <MediaPicker open={mediaPicker.open} workspaceId={workspaceId} onClose={() => setMediaPicker(current => ({ ...current, open: false }))} onSelect={selectMedia} imagesOnly title="Choose website media"/>
 </Page>;
}
