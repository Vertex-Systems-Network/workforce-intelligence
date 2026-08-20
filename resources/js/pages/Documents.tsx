import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { DndContext, KeyboardSensor, PointerSensor, closestCenter, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Archive, BadgeCheck, Barcode, BookOpenCheck, Box, Braces, Check, ChevronDown, ClipboardCheck, Columns3, Copy, Download, FileCheck2, FileClock, FileSignature, FileText, GalleryHorizontalEnd, GripVertical, History, Image as ImageIcon, Link2, ListTree, LockKeyhole, MessageSquareText, MoreHorizontal, PanelLeftClose, PanelRightClose, PenLine, Plus, QrCode, Redo2, RefreshCw, RotateCcw, Save, Send, Settings2, Share2, ShieldCheck, Stamp, Star, Table2, Trash2, Type, Undo2, Variable, X, ZoomIn, ZoomOut, Palette, LayoutTemplate, Ruler, ListChecks, Clock3, } from 'lucide-react';
import { apiDownload, apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import RichTextEditor from '../components/RichTextEditor';
import { MediaPicker } from '../media/MediaPicker';
import type { MediaAsset } from '../media/types';
import { useConfirmAction, Alert, Badge, Button, Card, CardBody, DataGrid, Drawer, Dropdown, EmptyState, LoadingState, Field, FormActions, FormGrid, FormSection, IconButton, Input, Modal, Page, PageHeader, RefreshButton, Select, Stack, Switch, Tabs, Textarea, Tooltip, type DataGridColumn, Pressable, Option } from '../design-system';
import { useLocalization } from '../i18n/LocalizationContext';
import type { DocumentBatchJob, DocumentBlock, DocumentBrandKit, DocumentComment, DocumentComponent, DocumentOverview, DocumentPageMaster, DocumentTemplate, DocumentTemplateDraft, DocumentPreflight, DocumentV6Resources, DocumentVersion, GeneratedDocument, } from '../documents/types';
import { BLOCK_ICONS, BlockInspector, CommentPanel, PageInspector, SortableBlock, blockId, documentPreflight, fileSize, findBlock, generatedPolicy, humanize, makeBlock, normalizeSettings, normalizeV6Schema, pageId, rekeyBlock, replaceBlock, type DesignerRailTab, type InspectorTab, type StudioTab, type WorkflowModal } from '../documents/studio/DocumentStudioSupport';
import './document-studio-v4.css';
/** Render the complete Document Studio V6 designer, workflow and generated-document experience. */
export default function Documents() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const { t, formatDate } = useLocalization();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [overview, setOverview] = useState<DocumentOverview | null>(null), [components, setComponents] = useState<DocumentComponent[]>([]), [tab, setTab] = useState<StudioTab>('designer');
    const [v6Resources, setV6Resources] = useState<DocumentV6Resources>({ brand_kits: [], page_masters: [], batch_jobs: [] });
    const [selectedId, setSelectedId] = useState<number | null>(null), [editor, setEditor] = useState<DocumentTemplate | null>(null), [selectedPageId, setSelectedPageId] = useState<string | null>(null), [selectedBlockId, setSelectedBlockId] = useState<string | null>(null), [inspectorTab, setInspectorTab] = useState<InspectorTab>('block'), [railTab, setRailTab] = useState<DesignerRailTab>('pages');
    const [previewHtml, setPreviewHtml] = useState(''), [previewError, setPreviewError] = useState(''), [previewBusy, setPreviewBusy] = useState(false), [sourceId, setSourceId] = useState('');
    const [busy, setBusy] = useState(''), [notice, setNotice] = useState(''), [creating, setCreating] = useState(false), [modal, setModal] = useState<WorkflowModal>(null);
    const [newName, setNewName] = useState(''), [newType, setNewType] = useState('invoice'), [newLanguage, setNewLanguage] = useState('en');
    const [comments, setComments] = useState<DocumentComment[]>([]), [selectedGenerated, setSelectedGenerated] = useState<GeneratedDocument | null>(null), [generatedDrawer, setGeneratedDrawer] = useState(false);
    const [mediaBlock, setMediaBlock] = useState<DocumentBlock | null>(null), [toolboxSearch, setToolboxSearch] = useState('');
    const [documentZoom, setDocumentZoom] = useState(100), [focusMode, setFocusMode] = useState(false), [editorUndo, setEditorUndo] = useState<DocumentTemplate[]>([]), [editorRedo, setEditorRedo] = useState<DocumentTemplate[]>([]);
    const [modalNote, setModalNote] = useState(''), [modalEmail, setModalEmail] = useState(''), [modalName, setModalName] = useState(''), [modalRole, setModalRole] = useState(''), [modalDays, setModalDays] = useState('14'), [modalAccess, setModalAccess] = useState('view'), [modalViews, setModalViews] = useState('');
    const [compareLeft, setCompareLeft] = useState(''), [compareRight, setCompareRight] = useState(''), [compareData, setCompareData] = useState<any>(null);
    const [componentName, setComponentName] = useState(''), [componentCategory, setComponentCategory] = useState('General');
    const [dirty, setDirty] = useState(false), [autosaveState, setAutosaveState] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle'), [draftRevision, setDraftRevision] = useState<number | null>(null), [serverPreflight, setServerPreflight] = useState<DocumentPreflight | null>(null), [batchSourceIds, setBatchSourceIds] = useState('');
    const [showRulers, setShowRulers] = useState(true), [showGuides, setShowGuides] = useState(true);
    const [brandName, setBrandName] = useState(''), [brandPrimary, setBrandPrimary] = useState('#111827'), [brandSecondary, setBrandSecondary] = useState('#6B7280'), [brandAccent, setBrandAccent] = useState('#2563EB'), [brandFont, setBrandFont] = useState('Arial');
    const [brandLogoId, setBrandLogoId] = useState<number | null>(null), [brandLogoPicker, setBrandLogoPicker] = useState(false);
    const [masterName, setMasterName] = useState('');
    const previewTimer = useRef<number | null>(null), autosaveTimer = useRef<number | null>(null);
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));
    const editable = Boolean(overview?.permissions.templates_manage && editor?.status !== 'archived');
    const pages = useMemo(() => editor ? normalizeV6Schema(editor.content_schema) : [], [editor]);
    const selectedPage = pages.find(page => page.id === selectedPageId) ?? pages[0] ?? null;
    const currentBlocks = Array.isArray(selectedPage?.children) ? selectedPage.children : [];
    const selectedBlock = findBlock(currentBlocks, selectedBlockId);
    /** Load the Document Studio catalog, templates, generated history, components and renderer capabilities. */
    const load = async () => {
        if (!workspaceId)
            return;
        try {
            const [main, reusable, advanced] = await Promise.all([apiRequest<DocumentOverview>('/api/v1/documents/overview', { workspaceId, silent: true }), apiRequest<{
                    data: DocumentComponent[];
                }>('/api/v1/documents/components', { workspaceId, silent: true }), apiRequest<{
                    data: DocumentV6Resources;
                }>('/api/v1/documents/v6/resources', { workspaceId, silent: true })]);
            setOverview(main);
            setComponents(reusable.data);
            setV6Resources(advanced.data);
            if (selectedId && !main.templates.some(template => template.id === selectedId)) {
                setSelectedId(null);
                setEditor(null);
            }
        }
        catch (reason) {
            setNotice(reason instanceof Error ? reason.message : 'Could not load Document Studio.');
        }
    };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Open a template with immutable version history and collaboration comments. */
    const openTemplate = async (id: number) => {
        setBusy('open');
        try {
            const [templateResponse, commentResponse] = await Promise.all([apiRequest<{
                    data: DocumentTemplate;
                    draft: DocumentTemplateDraft | null;
                }>(`/api/v1/documents/templates/${id}`, { workspaceId, silent: true }), apiRequest<{
                    data: DocumentComment[];
                }>(`/api/v1/documents/templates/${id}/comments`, { workspaceId, silent: true })]);
            const persisted = templateResponse.data, draft = templateResponse.draft;
            const schema = normalizeV6Schema(draft?.content_schema ?? persisted.content_schema);
            const metadata = draft?.metadata ?? {};
            const next = { ...persisted, name: String(metadata.name ?? persisted.name), language: String(metadata.language ?? persisted.language), paper_size: (metadata.paper_size ?? persisted.paper_size) as DocumentTemplate['paper_size'], orientation: (metadata.orientation ?? persisted.orientation) as DocumentTemplate['orientation'], primary_color: String(metadata.primary_color ?? persisted.primary_color), secondary_color: String(metadata.secondary_color ?? persisted.secondary_color), font_family: String(metadata.font_family ?? persisted.font_family ?? 'Arial'), content_schema: schema, settings: normalizeSettings(draft?.settings ?? persisted.settings) };
            const firstPage = schema[0];
            const firstBlocks = Array.isArray(firstPage?.children) ? firstPage.children : [];
            setSelectedId(id);
            setEditor(next);
            setEditorUndo([]);
            setEditorRedo([]);
            setDocumentZoom(100);
            setSelectedPageId(firstPage?.id ?? null);
            setSelectedBlockId(firstBlocks[0]?.id ?? null);
            setComments(commentResponse.data);
            setTab('designer');
            setInspectorTab('block');
            setRailTab('pages');
            setPreviewHtml('');
            setPreviewError('');
            setDirty(Boolean(draft));
            setDraftRevision(draft?.revision ?? null);
            setAutosaveState(draft ? 'saved' : 'idle');
            setServerPreflight(null);
            if (draft)
                setNotice('Recovered the latest autosaved Document Studio draft.');
        }
        catch (reason) {
            setNotice(reason instanceof Error ? reason.message : 'Could not open template.');
        }
        finally {
            setBusy('');
        }
    };
    /** Create a new document template and open it directly in the V6 designer. */
    const createTemplate = async () => {
        if (!newName.trim())
            return;
        setBusy('create');
        try {
            const response = await apiRequest<{
                data: DocumentTemplate;
            }>('/api/v1/documents/templates', { method: 'POST', workspaceId, body: JSON.stringify({ name: newName.trim(), document_type: newType, language: newLanguage }) });
            setCreating(false);
            setNewName('');
            await load();
            await openTemplate(response.data.id);
            setNotice('Template created.');
        }
        catch (reason) {
            setNotice(reason instanceof Error ? reason.message : 'Could not create template.');
        }
        finally {
            setBusy('');
        }
    };
    /** Persist current designer state as a new immutable template version. */
    const saveTemplate = async () => {
        if (!editor)
            return;
        setBusy('save');
        try {
            const response = await apiRequest<{
                data: DocumentTemplate;
            }>(`/api/v1/documents/templates/${editor.id}`, { method: 'PUT', workspaceId, body: JSON.stringify({ name: editor.name, legal_entity_id: editor.legal_entity_id, language: editor.language, status: editor.status, paper_size: editor.paper_size, orientation: editor.orientation, primary_color: editor.primary_color, secondary_color: editor.secondary_color, font_family: editor.font_family ?? 'Arial', content_schema: editor.content_schema, settings: normalizeSettings(editor.settings), change_note: 'Saved from Document Studio V6' }) });
            const saved = { ...response.data, content_schema: normalizeV6Schema(response.data.content_schema), settings: normalizeSettings(response.data.settings) };
            setEditor(saved);
            setDirty(false);
            setDraftRevision(null);
            setAutosaveState('idle');
            await load();
            setNotice('Template saved as a new immutable version.');
        }
        catch (reason) {
            setNotice(reason instanceof Error ? reason.message : 'Could not save template.');
        }
        finally {
            setBusy('');
        }
    };
    /** Render unsaved designer state through the exact backend V6 HTML renderer. */
    const renderPreview = async (silent = true) => {
        if (!editor)
            return;
        setPreviewBusy(true);
        setPreviewError('');
        try {
            const response = await apiRequest<{
                html: string;
            }>(`/api/v1/documents/templates/${editor.id}/live-preview`, { method: 'POST', workspaceId, silent, body: JSON.stringify({ language: editor.language, paper_size: editor.paper_size, orientation: editor.orientation, primary_color: editor.primary_color, secondary_color: editor.secondary_color, font_family: editor.font_family ?? 'Arial', content_schema: editor.content_schema, settings: normalizeSettings(editor.settings), ...(sourceId ? { source_id: Number(sourceId) } : {}) }) });
            setPreviewHtml(response.html);
        }
        catch (reason) {
            setPreviewError(reason instanceof Error ? reason.message : 'Preview could not be rendered.');
        }
        finally {
            setPreviewBusy(false);
        }
    };
    useEffect(() => {
        if (!editor)
            return;
        if (previewTimer.current)
            window.clearTimeout(previewTimer.current);
        previewTimer.current = window.setTimeout(() => void renderPreview(true), 360);
        return () => {
            if (previewTimer.current)
                window.clearTimeout(previewTimer.current);
        };
    }, [editor, sourceId, workspaceId]);
    /** Autosave mutable V6 designer state without creating an immutable template version. */
    const autosaveDraft = async () => {
        if (!editor || !editable || !dirty)
            return;
        setAutosaveState('saving');
        try {
            const response = await apiRequest<{
                data: DocumentTemplateDraft;
            }>(`/api/v1/documents/templates/${editor.id}/draft`, { method: 'PUT', workspaceId, silent: true, body: JSON.stringify({ content_schema: editor.content_schema, settings: normalizeSettings(editor.settings), metadata: { name: editor.name, language: editor.language, paper_size: editor.paper_size, orientation: editor.orientation, primary_color: editor.primary_color, secondary_color: editor.secondary_color, font_family: editor.font_family ?? 'Arial' } }) });
            setDraftRevision(response.data.revision);
            setAutosaveState('saved');
        }
        catch {
            setAutosaveState('error');
        }
    };
    useEffect(() => {
        if (!editor || !editable || !dirty)
            return;
        if (autosaveTimer.current)
            window.clearTimeout(autosaveTimer.current);
        autosaveTimer.current = window.setTimeout(() => void autosaveDraft(), 900);
        return () => {
            if (autosaveTimer.current)
                window.clearTimeout(autosaveTimer.current);
        };
    }, [editor, dirty, editable, workspaceId]);
    /** Discard only the mutable autosave and reload the latest immutable template version. */
    const discardDraft = async () => {
        if (!editor || !draftRevision || !await confirmAction({ title: 'Discard autosaved draft?', description: 'Unsaved changes will be removed. Immutable template versions are preserved.', confirmLabel: 'Discard', danger: true }))
            return;
        await apiRequest(`/api/v1/documents/templates/${editor.id}/draft`, { method: 'DELETE', workspaceId });
        await openTemplate(editor.id);
        setNotice('Autosaved draft discarded.');
    };
    /** Run authoritative server PDF preflight against the current unsaved V6 state. */
    const runServerPreflight = async () => {
        if (!editor)
            return;
        setBusy('preflight');
        try {
            const response = await apiRequest<{
                data: DocumentPreflight;
            }>(`/api/v1/documents/templates/${editor.id}/preflight`, { method: 'POST', workspaceId, silent: true, body: JSON.stringify({ content_schema: editor.content_schema, settings: normalizeSettings(editor.settings) }) });
            setServerPreflight(response.data);
            setInspectorTab('preflight');
        }
        catch (reason) {
            setNotice(reason instanceof Error ? reason.message : 'Document preflight failed.');
        }
        finally {
            setBusy('');
        }
    };
    /** Generate small batches immediately and persist larger batches for scheduler-backed processing. */
    const batchGenerate = async () => {
        if (!editor)
            return;
        const ids = [...new Set(batchSourceIds.split(/[^0-9]+/).map(value => Number(value)).filter(value => Number.isInteger(value) && value > 0))];
        if (!ids.length || ids.length > 500)
            return;
        setBusy('batch');
        try {
            if (ids.length <= 50) {
                const response = await apiRequest<{
                    data: {
                        generated: Array<{
                            source_id: number;
                            document_id: number;
                        }>;
                        failed: Array<{
                            source_id: number;
                        }>;
                        requested: number;
                    };
                }>(`/api/v1/documents/templates/${editor.id}/batch-generate`, { method: 'POST', workspaceId, body: JSON.stringify({ source_ids: ids }) });
                setNotice(`Batch complete: ${response.data.generated.length} generated${response.data.failed.length ? `, ${response.data.failed.length} failed` : ''}.`);
            }
            else {
                const clientRequestId = globalThis.crypto?.randomUUID?.() ?? `batch-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
                const response = await apiRequest<{
                    data: DocumentBatchJob;
                }>(`/api/v1/documents/templates/${editor.id}/batch-jobs`, { method: 'POST', workspaceId, body: JSON.stringify({ source_ids: ids, client_request_id: clientRequestId }) });
                setV6Resources(current => ({ ...current, batch_jobs: [response.data, ...current.batch_jobs] }));
                setNotice(`Large batch queued: ${response.data.requested_count} sources. Scheduler will process it in bounded chunks.`);
            }
            setModal(null);
            setBatchSourceIds('');
            await load();
            setTab('generated');
        }
        catch (reason) {
            setNotice(reason instanceof Error ? reason.message : 'Batch generation failed.');
        }
        finally {
            setBusy('');
        }
    };
    /** Commit one in-memory template edit and retain bounded undo history until the next server load/save. */
    const commitEditor = (next: DocumentTemplate) => {
        if (!editor)
            return;
        setEditorUndo(stack => [...stack, structuredClone(editor)].slice(-50));
        setEditorRedo([]);
        setEditor(next);
        setDirty(true);
        setAutosaveState('idle');
        setServerPreflight(null);
    };
    /** Restore the previous in-memory Document Studio edit. */
    const undoEditor = () => {
        const previous = editorUndo[editorUndo.length - 1];
        if (!previous || !editor)
            return;
        setEditorRedo(stack => [structuredClone(editor), ...stack].slice(0, 50));
        setEditorUndo(stack => stack.slice(0, -1));
        setEditor(structuredClone(previous));
        setDirty(true);
    };
    /** Re-apply the next in-memory Document Studio edit after undo. */
    const redoEditor = () => {
        const next = editorRedo[0];
        if (!next || !editor)
            return;
        setEditorUndo(stack => [...stack, structuredClone(editor)].slice(-50));
        setEditorRedo(stack => stack.slice(1));
        setEditor(structuredClone(next));
        setDirty(true);
    };
    /** Replace the active V6 page children while preserving every other page. */
    const updatePageBlocks = (children: DocumentBlock[]) => {
        if (!editor || !selectedPage)
            return;
        commitEditor({ ...editor, content_schema: pages.map(page => page.id === selectedPage.id ? { ...page, children } : page) });
    };
    /** Append a toolbox block to the active V6 page and select it for immediate configuration. */
    const addBlock = (type: string) => {
        if (!editor || !editable || !selectedPage || type === 'page')
            return;
        const block = makeBlock(type);
        updatePageBlocks([...currentBlocks, block]);
        setSelectedBlockId(block.id);
        setInspectorTab('block');
        setRailTab('layers');
    };
    /** Update one selected block inside the active logical page. */
    const updateBlock = (block: DocumentBlock) => {
        if (editor && selectedPage)
            updatePageBlocks(replaceBlock(currentBlocks, block));
    };
    /** Remove one selected page block after confirmation and select the nearest remaining block. */
    const removeBlock = async (id: string) => {
        if (!editor || !editable || !selectedPage || !await confirmAction({ title: 'Remove template block?', description: 'Remove this block from the current page?', confirmLabel: 'Remove', danger: true }))
            return;
        const next = currentBlocks.filter(block => block.id !== id);
        updatePageBlocks(next);
        setSelectedBlockId(next[0]?.id ?? null);
    };
    /** Duplicate one page block with fresh nested IDs so server uniqueness validation remains valid. */
    const duplicateBlock = (block: DocumentBlock) => {
        if (!editor || !editable || !selectedPage)
            return;
        const clone = rekeyBlock(block);
        const index = currentBlocks.findIndex(row => row.id === block.id);
        const next = [...currentBlocks];
        next.splice(index + 1, 0, clone);
        updatePageBlocks(next);
        setSelectedBlockId(clone.id);
    };
    /** Persist drag-and-drop reordering inside the active V6 page. */
    const dragEnd = (event: DragEndEvent) => {
        if (!editor || !editable || !selectedPage || !event.over || event.active.id === event.over.id)
            return;
        const oldIndex = currentBlocks.findIndex(block => block.id === event.active.id);
        const newIndex = currentBlocks.findIndex(block => block.id === event.over?.id);
        if (oldIndex >= 0 && newIndex >= 0)
            updatePageBlocks(arrayMove(currentBlocks, oldIndex, newIndex));
    };
    /** Add an empty logical page without inserting legacy page-break blocks. */
    const addPage = () => {
        if (!editor || !editable || pages.length >= 50)
            return;
        const page: DocumentBlock = { id: pageId(), type: 'page', label: `Page ${pages.length + 1}`, children: [] };
        commitEditor({ ...editor, content_schema: [...pages, page] });
        setSelectedPageId(page.id);
        setSelectedBlockId(null);
        setRailTab('pages');
    };
    /** Duplicate the selected page and recursively re-key all nested block IDs. */
    const duplicatePage = () => {
        if (!editor || !editable || !selectedPage || pages.length >= 50)
            return;
        const clone: DocumentBlock = { ...rekeyBlock(selectedPage), id: pageId(), type: 'page', label: `${String(selectedPage.label ?? 'Page')} Copy` };
        const index = pages.findIndex(page => page.id === selectedPage.id);
        const next = [...pages];
        next.splice(index + 1, 0, clone);
        commitEditor({ ...editor, content_schema: next });
        setSelectedPageId(clone.id);
        setSelectedBlockId(Array.isArray(clone.children) ? clone.children[0]?.id ?? null : null);
    };
    /** Delete one logical page only when at least one other page will remain. */
    const deletePage = async () => {
        if (!editor || !editable || !selectedPage || pages.length <= 1 || !await confirmAction({ title: 'Delete page?', description: 'All blocks on this page will be removed from the current draft.', confirmLabel: 'Delete page', danger: true }))
            return;
        const index = pages.findIndex(page => page.id === selectedPage.id), next = pages.filter(page => page.id !== selectedPage.id), fallback = next[Math.max(0, index - 1)] ?? next[0];
        commitEditor({ ...editor, content_schema: next });
        setSelectedPageId(fallback?.id ?? null);
        setSelectedBlockId(Array.isArray(fallback?.children) ? fallback.children[0]?.id ?? null : null);
    };
    /** Move one logical page earlier or later without altering its blocks. */
    const movePage = (direction: -1 | 1) => {
        if (!editor || !editable || !selectedPage)
            return;
        const index = pages.findIndex(page => page.id === selectedPage.id), target = index + direction;
        if (index < 0 || target < 0 || target >= pages.length)
            return;
        commitEditor({ ...editor, content_schema: arrayMove(pages, index, target) });
    };
    /** Update authored metadata on the active logical page without touching its child blocks. */
    const updatePageMeta = (patch: Partial<DocumentBlock>) => {
        if (!editor || !selectedPage)
            return;
        commitEditor({ ...editor, content_schema: pages.map(page => page.id === selectedPage.id ? { ...page, ...patch } : page) });
    };
    /** Apply selected Media Library image to the block that opened the shared picker. */
    const mediaSelected = (asset: MediaAsset) => {
        if (!editor || !mediaBlock)
            return;
        updateBlock({ ...mediaBlock, media_asset_id: asset.id, alt: mediaBlock.alt || asset.alt_text || asset.name });
        setMediaBlock(null);
    };
    /** Insert an image block on the active page and immediately open the shared Media DAM picker. */
    const addImageFromLibrary = () => {
        if (!editor || !editable || !selectedPage)
            return;
        const block = makeBlock('image');
        updatePageBlocks([...currentBlocks, block]);
        setSelectedBlockId(block.id);
        setMediaBlock(block);
        setRailTab('assets');
    };
    /** Insert one reusable component reference into the active page without copying its schema. */
    const insertReusable = (component: DocumentComponent) => {
        if (!editor || !editable || !selectedPage)
            return;
        const block = { ...makeBlock('reusable'), component_id: component.id };
        updatePageBlocks([...currentBlocks, block]);
        setSelectedBlockId(block.id);
        setInspectorTab('block');
        setTab('designer');
    };
    /** Generate a private PDF from the active template and current source context. */
    const generate = async () => {
        if (!editor)
            return;
        setBusy('generate');
        try {
            await apiRequest(`/api/v1/documents/templates/${editor.id}/generate`, { method: 'POST', workspaceId, body: JSON.stringify(sourceId ? { source_id: Number(sourceId) } : {}) });
            await load();
            setTab('generated');
            setNotice('PDF generated and added to governed document history.');
        }
        catch (reason) {
            setNotice(reason instanceof Error ? reason.message : 'Could not generate document.');
        }
        finally {
            setBusy('');
        }
    };
    /** Set the selected template as the default for its document type/language/legal entity scope. */
    const setDefault = async () => {
        if (!editor)
            return;
        setBusy('default');
        try {
            await apiRequest(`/api/v1/documents/templates/${editor.id}/default`, { method: 'POST', workspaceId });
            setEditor({ ...editor, is_default: true });
            await load();
        }
        finally {
            setBusy('');
        }
    };
    /** Archive the selected template while preserving generated documents and immutable versions. */
    const archiveTemplate = async () => {
        if (!editor || !await confirmAction({ title: 'Archive template?', description: 'Existing generated documents remain available.', confirmLabel: 'Archive', danger: true }))
            return;
        setBusy('archive');
        try {
            await apiRequest(`/api/v1/documents/templates/${editor.id}/archive`, { method: 'POST', workspaceId });
            setEditor({ ...editor, status: 'archived', is_default: false });
            await load();
        }
        finally {
            setBusy('');
        }
    };
    /** Restore one historical template version as a new version rather than overwriting history. */
    const restoreVersion = async (version: DocumentVersion) => {
        if (!editor || !await confirmAction({ title: 'Restore document version?', description: `Restore version ${version.version} as a new version?`, confirmLabel: 'Restore' }))
            return;
        setBusy('restore');
        try {
            const response = await apiRequest<{
                data: DocumentTemplate;
            }>(`/api/v1/documents/templates/${editor.id}/versions/${version.id}/restore`, { method: 'POST', workspaceId });
            const restored = { ...response.data, content_schema: normalizeV6Schema(response.data.content_schema), settings: normalizeSettings(response.data.settings) };
            setEditor(restored);
            setDirty(false);
            setDraftRevision(null);
            setSelectedPageId(restored.content_schema[0]?.id ?? null);
            setSelectedBlockId(Array.isArray(restored.content_schema[0]?.children) ? restored.content_schema[0].children[0]?.id ?? null : null);
            await load();
        }
        finally {
            setBusy('');
        }
    };
    /** Add a collaboration comment to either the template or currently selected block. */
    const addComment = async (body: string) => {
        if (!editor)
            return;
        const response = await apiRequest<{
            data: DocumentComment;
        }>('/api/v1/documents/comments', { method: 'POST', workspaceId, body: JSON.stringify({ document_template_id: editor.id, block_id: selectedBlockId, body }) });
        setComments(current => [response.data, ...current]);
    };
    /** Resolve or reopen a collaboration comment through the auditable backend workflow. */
    const resolveComment = async (comment: DocumentComment, resolved: boolean) => {
        const response = await apiRequest<{
            data: DocumentComment;
        }>(`/api/v1/documents/comments/${comment.id}/resolve`, { method: 'PUT', workspaceId, body: JSON.stringify({ resolved }) });
        setComments(current => current.map(row => row.id === comment.id ? response.data : row));
    };
    /** Fetch full generated-document workflow details and open the governance drawer. */
    const openGenerated = async (document: GeneratedDocument) => {
        setBusy(`generated-${document.id}`);
        try {
            const response = await apiRequest<{
                data: GeneratedDocument;
            }>(`/api/v1/documents/generated/${document.id}`, { workspaceId, silent: true });
            setSelectedGenerated(response.data);
            setGeneratedDrawer(true);
        }
        finally {
            setBusy('');
        }
    };
    /** Refresh the currently opened generated-document workflow details. */
    const refreshGenerated = async () => {
        if (!selectedGenerated)
            return;
        const response = await apiRequest<{
            data: GeneratedDocument;
        }>(`/api/v1/documents/generated/${selectedGenerated.id}`, { workspaceId, silent: true });
        setSelectedGenerated(response.data);
        await load();
    };
    /** Execute generated-document review/approval/locking transitions with an optional note. */
    const workflowAction = async (kind: 'review' | 'approve' | 'reject' | 'lock') => {
        const document = modal?.document ?? selectedGenerated;
        if (!document)
            return;
        setBusy(kind);
        try {
            await apiRequest(`/api/v1/documents/generated/${document.id}/${kind}`, { method: 'POST', workspaceId, body: JSON.stringify(kind === 'reject' || kind === 'review' || kind === 'approve' ? { note: modalNote || undefined } : {}) });
            setModal(null);
            setModalNote('');
            if (selectedGenerated?.id === document.id)
                await refreshGenerated();
            else
                await load();
        }
        finally {
            setBusy('');
        }
    };
    /** Create a revocable public share URL and copy the one-time raw token URL to clipboard. */
    const createShare = async () => {
        const document = modal?.document ?? selectedGenerated;
        if (!document)
            return;
        setBusy('share');
        try {
            const response = await apiRequest<{
                data: {
                    url: string;
                };
            }>(`/api/v1/documents/generated/${document.id}/share`, { method: 'POST', workspaceId, body: JSON.stringify({ access_mode: modalAccess, expires_in_days: modalDays ? Number(modalDays) : undefined, max_views: modalViews ? Number(modalViews) : undefined }) });
            await copyUrl(response.data.url);
            setNotice('Secure share link created and copied. The raw token is shown only once.');
            setModal(null);
            if (selectedGenerated?.id === document.id)
                await refreshGenerated();
            else
                await load();
        }
        finally {
            setBusy('');
        }
    };
    /** Create an external signature request and copy its hash-token-backed web signing URL. */
    const createSignature = async () => {
        const document = modal?.document ?? selectedGenerated;
        if (!document)
            return;
        setBusy('signature');
        try {
            const response = await apiRequest<{
                data: {
                    url: string;
                };
            }>(`/api/v1/documents/generated/${document.id}/signature-requests`, { method: 'POST', workspaceId, body: JSON.stringify({ signer_name: modalName.trim(), signer_email: modalEmail.trim() || undefined, role_label: modalRole.trim() || undefined, expires_in_days: modalDays ? Number(modalDays) : 14 }) });
            await copyUrl(response.data.url);
            setNotice('Signature request created and signing URL copied.');
            setModal(null);
            if (selectedGenerated?.id === document.id)
                await refreshGenerated();
            else
                await load();
        }
        finally {
            setBusy('');
        }
    };
    /** Revoke one public share URL without deleting the generated document. */
    const revokeShare = async (id: number) => { await apiRequest(`/api/v1/documents/shares/${id}/revoke`, { method: 'POST', workspaceId }); await refreshGenerated(); };
    /** Create a reusable component from the selected block or current template schema. */
    const createComponent = async () => {
        if (!componentName.trim() || !editor)
            return;
        setBusy('component');
        try {
            const schema = selectedBlock ? [structuredClone(selectedBlock)] : structuredClone(editor.content_schema);
            const response = await apiRequest<{
                data: DocumentComponent;
            }>('/api/v1/documents/components', { method: 'POST', workspaceId, body: JSON.stringify({ name: componentName.trim(), category: componentCategory.trim() || 'General', content_schema: schema, settings: { studio_version: 6 } }) });
            setComponents(current => [response.data, ...current]);
            setModal(null);
            setComponentName('');
            setNotice('Reusable component created.');
        }
        finally {
            setBusy('');
        }
    };
    /** Delete an unused reusable component after a deliberate confirmation. */
    const deleteComponent = async (component: DocumentComponent) => {
        if (!await confirmAction({ title: 'Delete reusable component?', description: `Delete reusable component “${component.name}”?`, confirmLabel: 'Delete', danger: true }))
            return;
        await apiRequest(`/api/v1/documents/components/${component.id}`, { method: 'DELETE', workspaceId });
        setComponents(current => current.filter(row => row.id !== component.id));
    };
    /** Push the selected local block into an existing reusable source; linked instances update on the next render. */
    const updateComponentFromSelection = async (component: DocumentComponent) => {
        if (!selectedBlock || selectedBlock.type === 'reusable' || !overview?.permissions.components_manage)
            return;
        if (!await confirmAction({ title: 'Update reusable component?', description: `Replace “${component.name}” with the selected local block? Linked instances will render the new source version.`, confirmLabel: 'Update component' }))
            return;
        const response = await apiRequest<{
            data: DocumentComponent;
        }>(`/api/v1/documents/components/${component.id}`, { method: 'PUT', workspaceId, body: JSON.stringify({ content_schema: [structuredClone(selectedBlock)] }) });
        setComponents(current => current.map(row => row.id === component.id ? response.data : row));
        setNotice(`Reusable component updated to version ${response.data.version ?? 'next'}. Linked instances render the latest source.`);
    };
    /** Detach one linked reusable block into local editable blocks while leaving the shared source unchanged. */
    const detachReusable = () => {
        if (!selectedBlock || selectedBlock.type !== 'reusable' || !selectedPage)
            return;
        const component = components.find(row => row.id === Number(selectedBlock.component_id));
        if (!component)
            return;
        const local = component.content_schema.map(rekeyBlock);
        const index = currentBlocks.findIndex(row => row.id === selectedBlock.id);
        const next = [...currentBlocks];
        next.splice(index, 1, ...local);
        updatePageBlocks(next);
        setSelectedBlockId(local[0]?.id ?? null);
        setNotice(`Detached ${component.name}. This page now has a local copy and will no longer receive shared-source updates.`);
    };
    /** Apply a reusable brand kit to the current mutable document state. */
    const applyBrandKit = (kit: DocumentBrandKit) => {
        if (!editor || !editable)
            return;
        const settings = normalizeSettings(editor.settings);
        commitEditor({ ...editor, primary_color: kit.primary_color, secondary_color: kit.secondary_color, font_family: kit.font_family, settings: { ...settings, brand_kit_id: kit.id, brand_accent_color: kit.accent_color, heading_font_family: kit.heading_font_family } });
        setNotice(`Applied brand kit “${kit.name}”.`);
    };
    /** Create a brand kit from the modal and make it available without leaving the designer. */
    const createBrandKit = async () => {
        if (!brandName.trim())
            return;
        setBusy('brand');
        try {
            const response = await apiRequest<{
                data: DocumentBrandKit;
            }>('/api/v1/documents/brand-kits', { method: 'POST', workspaceId, body: JSON.stringify({ name: brandName.trim(), primary_color: brandPrimary, secondary_color: brandSecondary, accent_color: brandAccent, font_family: brandFont, heading_font_family: brandFont, logo_media_asset_id: brandLogoId ?? undefined }) });
            setV6Resources(current => ({ ...current, brand_kits: [response.data, ...current.brand_kits] }));
            setModal(null);
            setBrandName('');
            if (editor)
                applyBrandKit(response.data);
            setNotice('Brand kit created and applied.');
        }
        finally {
            setBusy('brand');
        }
    };
    /** Apply a reusable page master to page, header, footer and watermark settings. */
    const applyPageMaster = (master: DocumentPageMaster) => {
        if (!editor || !editable)
            return;
        const settings = normalizeSettings(editor.settings);
        commitEditor({ ...editor, settings: { ...settings, page: { ...settings.page, ...master.page_settings }, header: { ...settings.header, ...(master.header_settings ?? {}) }, footer: { ...settings.footer, ...(master.footer_settings ?? {}) }, watermark: { ...settings.watermark, ...(master.watermark_settings ?? {}) }, page_master_id: master.id } });
        setNotice(`Applied page master “${master.name}”.`);
    };
    /** Save the current normalized page regions as a reusable page master. */
    const createPageMaster = async () => {
        if (!editor || !masterName.trim())
            return;
        setBusy('master');
        try {
            const settings = normalizeSettings(editor.settings);
            const response = await apiRequest<{
                data: DocumentPageMaster;
            }>('/api/v1/documents/page-masters', { method: 'POST', workspaceId, body: JSON.stringify({ name: masterName.trim(), page_settings: settings.page, header_settings: settings.header, footer_settings: settings.footer, watermark_settings: settings.watermark }) });
            setV6Resources(current => ({ ...current, page_masters: [response.data, ...current.page_masters] }));
            setModal(null);
            setMasterName('');
            applyPageMaster(response.data);
            setNotice('Page master created and applied.');
        }
        finally {
            setBusy('master');
        }
    };
    /** Compare two immutable template versions and retain block-level diff results for the modal. */
    const compareVersions = async () => {
        if (!editor || !compareLeft || !compareRight)
            return;
        setBusy('compare');
        try {
            const response = await apiRequest<{
                data: any;
            }>(`/api/v1/documents/templates/${editor.id}/versions/${compareLeft}/compare/${compareRight}`, { workspaceId, silent: true });
            setCompareData(response.data);
        }
        finally {
            setBusy('');
        }
    };
    /** Clone or language-variant a template using proper modal fields rather than browser prompts. */
    const cloneOrVariant = async () => {
        if (!editor || !modal)
            return;
        setBusy(modal.kind);
        try {
            if (modal.kind === 'clone') {
                const response = await apiRequest<{
                    data: DocumentTemplate;
                }>(`/api/v1/documents/templates/${editor.id}/clone`, { method: 'POST', workspaceId, body: JSON.stringify({ name: modalName.trim() || `${editor.name} Copy` }) });
                setModal(null);
                await load();
                await openTemplate(response.data.id);
            }
            else if (modal.kind === 'variant') {
                const response = await apiRequest<{
                    data: DocumentTemplate;
                }>(`/api/v1/documents/templates/${editor.id}/language-variant`, { method: 'POST', workspaceId, body: JSON.stringify({ language: modalAccess, name: modalName.trim() || undefined }) });
                setModal(null);
                await load();
                await openTemplate(response.data.id);
            }
        }
        finally {
            setBusy('');
        }
    };
    const toolbox = useMemo(() => overview ? Object.entries(overview.catalog.blocks).filter(([type, label]) => !['page', 'page_break'].includes(type) && `${type} ${label}`.toLowerCase().includes(toolboxSearch.toLowerCase())) : [], [overview, toolboxSearch]);
    const preflightIssues = useMemo(() => documentPreflight(editor), [editor]);
    const preflightCount = serverPreflight ? serverPreflight.errors + serverPreflight.warnings : preflightIssues.length;
    useEffect(() => {
        /** Handle common editor shortcuts without intercepting typing fields. */
        const keydown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            if (target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
                return;
            if (!(event.ctrlKey || event.metaKey))
                return;
            if (event.key.toLowerCase() === 'z') {
                event.preventDefault();
                if (event.shiftKey)
                    redoEditor();
                else
                    undoEditor();
            }
            else if (event.key.toLowerCase() === 'y') {
                event.preventDefault();
                redoEditor();
            }
            else if (event.key.toLowerCase() === 's' && editable) {
                event.preventDefault();
                void saveTemplate();
            }
        };
        window.addEventListener('keydown', keydown);
        return () => window.removeEventListener('keydown', keydown);
    }, [editor, editorUndo, editorRedo, editable]);
    const generatedColumns = useMemo<DataGridColumn<GeneratedDocument>[]>(() => [
        { id: 'filename', header: 'Document', sortable: true, searchValue: row => row.filename, cell: row => <div className="document-v4-file-cell"><FileText size={14}/><div><strong>{row.filename}</strong><small>{row.template?.name ?? humanize(row.document_type)}</small></div></div> },
        { id: 'workflow', header: 'Workflow', sortable: true, value: row => row.workflow_status ?? 'generated', filter: { type: 'select', label: 'Workflow', options: ['generated', 'in_review', 'approved', 'rejected', 'signed'].map(value => ({ value, label: humanize(value) })) }, cell: row => <Badge tone={row.workflow_status === 'signed' || row.workflow_status === 'approved' ? 'success' : row.workflow_status === 'rejected' ? 'danger' : row.workflow_status === 'in_review' ? 'warning' : 'neutral'}>{humanize(row.workflow_status ?? 'generated')}</Badge> },
        { id: 'render_driver', header: 'Renderer', sortable: true, value: row => row.render_driver ?? '', cell: row => <span>{row.render_driver ?? '—'}</span> },
        { id: 'generated_at', header: 'Generated', sortable: true, value: row => row.generated_at, filter: { type: 'dateRange', label: 'Generated date' }, cell: row => <span>{formatDate(row.generated_at)}</span> },
        { id: 'size', header: 'Size', sortable: true, value: row => row.size_bytes, cell: row => <span>{fileSize(row.size_bytes)}</span> },
        { id: 'governance', header: 'Governance', sortable: false, hideable: false, cell: row => <div className="document-v4-governance-counts"><Tooltip content="Share links"><span><Share2 size={11}/>{row.share_links_count ?? 0}</span></Tooltip><Tooltip content="Signature requests"><span><FileSignature size={11}/>{row.signature_requests_count ?? 0}</span></Tooltip></div> },
        { id: 'actions', header: '', sortable: false, hideable: false, className: 'col-actions', cell: row => <Dropdown align="right" trigger={<IconButton variant="ghost" size="sm" aria-label={`Actions for ${row.filename}`}><MoreHorizontal size={14}/></IconButton>} items={[
                    { label: 'Open governance', icon: <BookOpenCheck size={13}/>, onClick: () => void openGenerated(row) },
                    { label: 'Download', icon: <Download size={13}/>, onClick: () => void downloadGenerated(row.id, workspaceId) },
                    ...(overview?.permissions.manage ? [{ label: 'Request review', icon: <Send size={13}/>, onClick: () => { setModal({ kind: 'review', document: row }); setModalNote(''); } }] : []),
                    ...(overview?.permissions.approve ? [{ label: 'Approve', icon: <BadgeCheck size={13}/>, disabled: generatedPolicy(row).review_required && row.workflow_status !== 'in_review', onClick: () => { setModal({ kind: 'approve', document: row }); setModalNote(''); } }, { label: 'Reject', icon: <X size={13}/>, danger: true, onClick: () => { setModal({ kind: 'reject', document: row }); setModalNote(''); } }] : []),
                    ...(overview?.permissions.share ? [{ label: 'Create share link', icon: <Share2 size={13}/>, onClick: () => { setModal({ kind: 'share', document: row }); setModalDays('14'); setModalAccess('view'); setModalViews(''); } }] : []),
                    ...(overview?.permissions.sign ? [{ label: 'Request signature', icon: <FileSignature size={13}/>, disabled: generatedPolicy(row).approval_required && !row.approved_at, onClick: () => { setModal({ kind: 'signature', document: row }); setModalName(''); setModalEmail(''); setModalRole(generatedPolicy(row).signer_role); setModalDays('14'); } }] : []),
                ]}/> },
    ], [overview, workspaceId, formatDate]);
    const batchColumns = useMemo<DataGridColumn<DocumentBatchJob>[]>(() => [
        { id: 'template', header: 'Template', searchValue: row => row.template?.name ?? '', cell: row => <Stack gap={2}><strong>{row.template?.name ?? `Template ${row.document_template_id}`}</strong><small>{row.uuid}</small></Stack> },
        { id: 'status', header: 'Status', filterValue: row => row.status, filter: { type: 'select', label: 'Status', options: ['queued', 'running', 'completed', 'partial', 'failed'].map(value => ({ value, label: humanize(value) })) }, cell: row => <Badge tone={row.status === 'completed' ? 'success' : row.status === 'failed' ? 'danger' : row.status === 'partial' ? 'warning' : 'neutral'}>{humanize(row.status)}</Badge> },
        { id: 'progress', header: 'Progress', sortValue: row => row.processed_count, cell: row => <span>{row.processed_count} / {row.requested_count}</span> },
        { id: 'generated', header: 'Generated', sortValue: row => row.generated_count, cell: row => <span>{row.generated_count}</span> },
        { id: 'failed', header: 'Failed', sortValue: row => row.failed_count, cell: row => <span>{row.failed_count}</span> },
        { id: 'created', header: 'Queued', sortValue: row => row.created_at ?? '', filterValue: row => row.created_at ?? '', filter: { type: 'dateRange', label: 'Queued date' }, cell: row => <span>{row.created_at ? formatDate(row.created_at) : '—'}</span> },
    ], [formatDate]);
    if (!overview)
        return <Page><PageHeader title="Document Studio V6" description="Loading multi-page document workspace…"/><LoadingState title="Loading Document Studio…" text="Preparing templates, generated documents and studio resources."/></Page>;
    return <Page className="document-v4-page">
    <PageHeader title="Document Studio V6" description="Design multi-page, data-bound, governed documents with autosave, PDF preflight, review, signatures and batch generation." actions={<><Badge tone={overview.rendering.chromium_available ? 'success' : 'warning'}>{overview.rendering.chromium_available ? 'Unicode PDF ready' : 'Legacy PDF fallback'}</Badge><Badge tone={overview.rendering.code_adapter_available ? 'success' : 'neutral'}>{overview.rendering.code_adapter_available ? 'QR / Barcode ready' : 'QR adapter optional'}</Badge><RefreshButton onRefresh={load}/><Button onClick={() => setCreating(true)} disabled={!overview.permissions.templates_manage}><Plus size={14}/> New template</Button></>}/>
    {notice && <Alert tone="info" autoHideMs={5000} onClick={() => setNotice('')}>{notice}</Alert>}
    <Tabs value={tab} onChange={value => setTab(value as StudioTab)} tabs={[{ value: 'designer', label: 'Studio' }, { value: 'generated', label: `Generated (${overview.generated.length})` }, { value: 'components', label: `Components (${components.length})` }, { value: 'variables', label: 'Data Catalog' }]}/>

    {tab === 'designer' && <div className={`document-v4-workspace document-v6-workspace${focusMode ? ' is-focus-mode' : ''}`}>
      <aside className="document-v4-sidebar document-v4-sidebar--left document-v6-left-rail">
        <div className="document-v4-sidebar__section"><div className="document-v4-sidebar__title"><strong>Templates</strong><Badge>{overview.templates.length}</Badge></div><div className="document-v4-template-list">{overview.templates.map(template => <Pressable key={template.id} type="button" className={selectedId === template.id ? 'is-active' : ''} onClick={() => void openTemplate(template.id)}><span><FileText size={14}/></span><div><strong>{template.name}</strong><small>{overview.catalog.types[template.document_type] ?? template.document_type} · {template.language.toUpperCase()} · v{template.current_version}</small></div>{template.is_default && <Star size={11} fill="currentColor"/>}</Pressable>)}</div></div>
        <Tabs value={railTab} onChange={value => setRailTab(value as DesignerRailTab)} tabs={[{ value: 'pages', label: 'Pages' }, { value: 'layers', label: 'Layers' }, { value: 'blocks', label: 'Blocks' }, { value: 'assets', label: 'Assets' }]}/>
        <div className="document-v6-rail-body">
          {railTab === 'pages' && <Stack gap={7}><div className="document-v4-sidebar__title"><strong>Pages</strong><span>{pages.length}/50</span></div><div className="document-v6-page-list">{pages.map((page, index) => <Pressable key={page.id} type="button" className={selectedPage?.id === page.id ? 'is-active' : ''} onClick={() => { setSelectedPageId(page.id); setSelectedBlockId(Array.isArray(page.children) ? page.children[0]?.id ?? null : null); }}><span>{index + 1}</span><div><strong>{String(page.label ?? `Page ${index + 1}`)}</strong><small>{Array.isArray(page.children) ? page.children.length : 0} layers</small></div></Pressable>)}</div><Button size="sm" variant="outline" disabled={!editable || pages.length >= 50} onClick={addPage}><Plus size={12}/> Add page</Button></Stack>}
          {railTab === 'layers' && <Stack gap={7}><div className="document-v4-sidebar__title"><strong>{String(selectedPage?.label ?? 'Page')} layers</strong><span>{currentBlocks.length}</span></div><div className="document-v6-layer-list">{currentBlocks.map((block, index) => <Pressable key={block.id} type="button" className={selectedBlockId === block.id ? 'is-active' : ''} onClick={() => { setSelectedBlockId(block.id); setInspectorTab('block'); }}><span>{BLOCK_ICONS[block.type] ?? <Box size={13}/>}</span><div><strong>{humanize(block.type)}</strong><small>{String(block.text ?? block.label ?? block.source ?? `Layer ${index + 1}`)}</small></div></Pressable>)}</div>{!currentBlocks.length && <EmptyState title="No layers on this page" text="Add a block from the Blocks tab."/>}</Stack>}
          {railTab === 'blocks' && <div className="document-v4-toolbox"><div className="document-v4-sidebar__title"><strong>Blocks</strong><span>Page content</span></div><Input value={toolboxSearch} onChange={event => setToolboxSearch(event.target.value)} placeholder="Find block…"/><div className="document-v4-toolbox__grid">{toolbox.map(([type, label]) => <Pressable key={type} type="button" disabled={!editor || !editable || !selectedPage} onClick={() => addBlock(type)}>{BLOCK_ICONS[type] ?? <Box size={14}/>}<span>{label}</span></Pressable>)}</div></div>}
          {railTab === 'assets' && <Stack gap={9}><div className="document-v4-sidebar__title"><strong>Assets</strong><span>Media + components</span></div><Button size="sm" variant="outline" disabled={!editable || !selectedPage} onClick={addImageFromLibrary}><ImageIcon size={12}/> Media Library</Button><div className="document-v6-assets-list">{components.slice(0, 12).map(component => <div key={component.id}><div><Box size={13}/><span><strong>{component.name}</strong><small>{component.category || 'General'}</small></span></div><Button size="sm" variant="ghost" disabled={!editable || !selectedPage} onClick={() => insertReusable(component)}>Insert</Button></div>)}</div>{!components.length && <EmptyState title="No reusable components" text="Create one from a selected block."/>}</Stack>}
        </div>
      </aside>

      <main className="document-v4-main">{editor ? <><div className="document-v4-editor-toolbar"><div className="document-v4-editor-toolbar__identity"><div><Input value={editor.name} disabled={!editable} onChange={event => commitEditor({ ...editor, name: event.target.value })}/><span>{overview.catalog.types[editor.document_type] ?? editor.document_type} · {editor.language.toUpperCase()} · v{editor.current_version} · {pages.length} page{pages.length === 1 ? '' : 's'}</span></div>{editor.status === 'archived' && <Badge tone="warning">Archived</Badge>}{editor.is_default && <Badge tone="success">Default</Badge>}<Badge tone={autosaveState === 'error' ? 'danger' : autosaveState === 'saving' ? 'warning' : draftRevision ? 'neutral' : 'success'}>{autosaveState === 'saving' ? 'Autosaving…' : autosaveState === 'error' ? 'Autosave failed' : draftRevision ? `Draft r${draftRevision}` : dirty ? 'Unsaved' : 'Version saved'}</Badge><Badge tone={preflightCount ? 'warning' : 'success'}><ShieldCheck size={11}/> {preflightCount ? `${preflightCount} preflight` : 'Ready'}</Badge></div><div className="document-v4-editor-toolbar__actions"><div className="document-v4-history-controls"><IconButton variant="ghost" size="sm" aria-label="Undo edit" disabled={!editorUndo.length} onClick={undoEditor}><Undo2 size={13}/></IconButton><IconButton variant="ghost" size="sm" aria-label="Redo edit" disabled={!editorRedo.length} onClick={redoEditor}><Redo2 size={13}/></IconButton></div><Field label="Source ID"><Input value={sourceId} onChange={event => setSourceId(event.target.value.replace(/\D/g, ''))} placeholder="Sample"/></Field><Tooltip content="Toggle canvas rulers"><IconButton variant={showRulers ? 'outline' : 'ghost'} aria-label="Toggle rulers" onClick={() => setShowRulers(value => !value)}><Ruler size={13}/></IconButton></Tooltip><Tooltip content="Toggle printable margin guides"><IconButton variant={showGuides ? 'outline' : 'ghost'} aria-label="Toggle guides" onClick={() => setShowGuides(value => !value)}><ListChecks size={13}/></IconButton></Tooltip><Button variant="ghost" onClick={() => setFocusMode(value => !value)}><PanelRightClose size={13}/> {focusMode ? 'Show panels' : 'Focus'}</Button><Button variant="outline" onClick={() => void runServerPreflight()} loading={busy === 'preflight'}><ShieldCheck size={13}/> Preflight</Button><Button variant="outline" onClick={() => void renderPreview(false)} loading={previewBusy}><RefreshCw size={13}/> Preview</Button><Button variant="outline" onClick={() => void generate()} loading={busy === 'generate'} disabled={!overview.permissions.generate || editor.status === 'archived'}><FileCheck2 size={13}/> Generate</Button><Button onClick={() => void saveTemplate()} loading={busy === 'save'} disabled={!editable}><Save size={13}/> Save version</Button><Dropdown trigger={<IconButton variant="outline" aria-label="More template actions"><MoreHorizontal size={14}/></IconButton>} items={[
                    { label: 'Batch generate', icon: <GalleryHorizontalEnd size={13}/>, disabled: !overview.permissions.generate, onClick: () => { setBatchSourceIds(''); setModal({ kind: 'batch' }); } },
                    { label: 'Discard autosave', icon: <RotateCcw size={13}/>, disabled: !draftRevision, onClick: () => void discardDraft() },
                    { label: 'Set as default', icon: <Star size={13}/>, disabled: !editable || editor.is_default, onClick: () => void setDefault() },
                    { label: 'Clone template', icon: <Copy size={13}/>, disabled: !overview.permissions.templates_manage, onClick: () => { setModal({ kind: 'clone' }); setModalName(`${editor.name} Copy`); } },
                    { label: 'Create language variant', icon: <Variable size={13}/>, disabled: !overview.permissions.templates_manage, onClick: () => { setModal({ kind: 'variant' }); setModalName(''); setModalAccess(overview.catalog.locales.find(locale => locale.code !== editor.language)?.code ?? 'tr'); } },
                    { label: 'Compare versions', icon: <History size={13}/>, disabled: (editor.versions?.length ?? 0) < 2, onClick: () => { const versions = editor.versions ?? []; setCompareLeft(String(versions[1]?.id ?? '')); setCompareRight(String(versions[0]?.id ?? '')); setCompareData(null); setModal({ kind: 'compare' }); } },
                    { label: 'Save as reusable component', icon: <Box size={13}/>, disabled: !overview.permissions.components_manage, onClick: () => { setComponentName(selectedBlock ? `${humanize(selectedBlock.type)} component` : `${editor.name} component`); setComponentCategory('General'); setModal({ kind: 'component' }); } },
                    { label: 'Archive template', icon: <Archive size={13}/>, danger: true, disabled: !editable, onClick: () => void archiveTemplate() },
                ]}/></div></div>
        <div className={`document-v6-canvas-shell${showGuides ? ' has-guides' : ''}`}>{showRulers && <><div className="document-v6-ruler document-v6-ruler--horizontal" aria-hidden="true"/><div className="document-v6-ruler document-v6-ruler--vertical" aria-hidden="true"/></>}{showGuides && <div className="document-v6-guide-overlay" aria-hidden="true"><span className="guide-top"/><span className="guide-right"/><span className="guide-bottom"/><span className="guide-left"/></div>}<section className="document-v4-preview document-v6-preview"><header><div><strong>Multi-page live canvas</strong><span>{selectedPage ? String(selectedPage.label ?? 'Page') : 'No page'} · {previewBusy ? 'Rendering…' : previewError ? 'Preview needs attention' : 'Same server renderer as generated PDF'}</span></div><div className="document-v4-preview-controls"><div className="document-v4-zoom-controls"><IconButton size="sm" variant="ghost" aria-label="Zoom out" disabled={documentZoom <= 50} onClick={() => setDocumentZoom(value => Math.max(50, value - 10))}><ZoomOut size={12}/></IconButton><Pressable type="button" onClick={() => setDocumentZoom(100)}>{documentZoom}%</Pressable><IconButton size="sm" variant="ghost" aria-label="Zoom in" disabled={documentZoom >= 140} onClick={() => setDocumentZoom(value => Math.min(140, value + 10))}><ZoomIn size={12}/></IconButton></div><Tooltip content="Preview uses the same V6 server renderer as PDF generation"><Badge tone={previewError ? 'danger' : previewBusy ? 'warning' : 'success'}>{previewError ? 'Error' : previewBusy ? 'Rendering' : 'Synced'}</Badge></Tooltip></div></header>{previewError && <Alert tone="danger">{previewError}</Alert>}{previewHtml ? <div className="document-v4-preview-scroll"><Box className="document-v4-preview-canvas" width={`${10000 / documentZoom}%`} transform={`scale(${documentZoom / 100})`}><iframe title="Live multi-page document preview" sandbox="allow-same-origin" srcDoc={previewHtml}/></Box></div> : <EmptyState icon={<FileText size={28}/>} title="Preview is preparing" text="The V6 server-rendered pages will appear here."/>}</section></div></> : <EmptyState icon={<FileText size={30}/>} title="Choose a template" text="Open an existing template or create a new one to start the V6 studio." action={<Button disabled={!overview.permissions.templates_manage} onClick={() => setCreating(true)}><Plus size={13}/> New template</Button>}/>}</main>

      <aside className="document-v4-sidebar document-v4-sidebar--right document-v6-inspector">{editor ? <><Tabs value={inspectorTab} onChange={value => setInspectorTab(value as InspectorTab)} tabs={[{ value: 'block', label: 'Block' }, { value: 'page', label: 'Page' }, { value: 'data', label: 'Data' }, { value: 'brand', label: 'Brand' }, { value: 'comments', label: `Review ${comments.length ? `(${comments.length})` : ''}` }, { value: 'preflight', label: 'Preflight' }]}/><div className="document-v4-inspector">{inspectorTab === 'block' && <BlockInspector block={selectedBlock} onChange={updateBlock} editable={editable} workspaceId={workspaceId} components={components} onPickMedia={setMediaBlock} onDetachReusable={detachReusable}/>} {inspectorTab === 'page' && <Stack gap={12}>{selectedPage && <FormSection title="Logical page"><Field label="Page label"><Input disabled={!editable} value={String(selectedPage.label ?? '')} onChange={event => updatePageMeta({ label: event.target.value })}/></Field><Field label="Page master override" hint="Optional: use a different master only on this logical page."><Select disabled={!editable} value={String(selectedPage.page_master_id ?? '')} onChange={event => updatePageMeta({ page_master_id: event.target.value ? Number(event.target.value) : undefined })}><Option value="">Inherit document master</Option>{v6Resources.page_masters.map(master => <Option key={master.id} value={master.id}>{master.name}</Option>)}</Select></Field><Switch checked={Boolean(selectedPage.page_settings)} disabled={!editable} onChange={checked => updatePageMeta({ page_settings: checked ? { margin_top: 18, margin_right: 18, margin_bottom: 20, margin_left: 18, background: '#FFFFFF' } : undefined })} label="Override margins/background for this page"/>{selectedPage.page_settings && <FormGrid columns={2}>{(['margin_top', 'margin_right', 'margin_bottom', 'margin_left'] as const).map(key => <Field key={key} label={humanize(key.replace('margin_', ''))}><Input disabled={!editable} type="number" min="5" max="45" value={Number(selectedPage.page_settings?.[key] ?? 18)} onChange={event => updatePageMeta({ page_settings: { ...(selectedPage.page_settings ?? {}), [key]: Number(event.target.value) } })}/></Field>)}<Field label="Background"><Input disabled={!editable} type="color" value={String(selectedPage.page_settings?.background ?? '#FFFFFF')} onChange={event => updatePageMeta({ page_settings: { ...(selectedPage.page_settings ?? {}), background: event.target.value } })}/></Field></FormGrid>}<FormActions><Button size="sm" variant="outline" disabled={!editable || pages.indexOf(selectedPage) <= 0} onClick={() => movePage(-1)}>Move up</Button><Button size="sm" variant="outline" disabled={!editable || pages.indexOf(selectedPage) >= pages.length - 1} onClick={() => movePage(1)}>Move down</Button><Button size="sm" variant="outline" disabled={!editable || pages.length >= 50} onClick={duplicatePage}>Duplicate</Button><Button size="sm" variant="danger" disabled={!editable || pages.length <= 1} onClick={() => void deletePage()}>Delete</Button></FormActions></FormSection>}<PageInspector editor={editor} onChange={commitEditor} editable={editable}/></Stack>} {inspectorTab === 'data' && <Stack gap={10}><FormSection title="Source context"><Field label="Preview source ID" hint="Use a real source ID to preview domain data safely through the server context resolver."><Input value={sourceId} onChange={event => setSourceId(event.target.value.replace(/\D/g, ''))} placeholder="Sample data"/></Field></FormSection><FormSection title="Available merge fields"><div className="document-v6-token-list">{(overview.catalog.variables[editor.document_type] ?? []).map(variable => <Pressable key={variable} type="button" onClick={() => void navigator.clipboard.writeText(`{{${variable}}}`)}><Copy size={10}/><code>{`{{${variable}}}`}</code></Pressable>)}</div></FormSection><Alert tone="info">Tables, formulas, conditions and repeat blocks resolve through the same allowlisted server context used by PDF generation.</Alert></Stack>} {inspectorTab === 'brand' && <Stack gap={10}><FormSection title="Brand kit"><Field label="Workspace brand kit"><Select disabled={!editable} value={String((editor.settings as any)?.brand_kit_id ?? '')} onChange={event => {
                        const kit = v6Resources.brand_kits.find(row => row.id === Number(event.target.value));
                        if (kit)
                            applyBrandKit(kit);
                    }}><Option value="">No linked brand kit</Option>{v6Resources.brand_kits.map(kit => <Option key={kit.id} value={kit.id}>{kit.name}{kit.is_default ? ' · Default' : ''}</Option>)}</Select></Field><Button size="sm" variant="outline" disabled={!editable} onClick={() => { setBrandName(`${editor.name} Brand`); setBrandPrimary(editor.primary_color); setBrandSecondary(editor.secondary_color); setBrandAccent(String((editor.settings as any)?.brand_accent_color ?? '#2563EB')); setBrandFont(editor.font_family ?? 'Arial'); setBrandLogoId(null); setModal({ kind: 'brand' }); }}><Palette size={12}/> Save current brand kit</Button></FormSection><FormSection title="Page master"><Field label="Reusable master"><Select disabled={!editable} value={String((editor.settings as any)?.page_master_id ?? '')} onChange={event => {
                        const master = v6Resources.page_masters.find(row => row.id === Number(event.target.value));
                        if (master)
                            applyPageMaster(master);
                    }}><Option value="">No linked page master</Option>{v6Resources.page_masters.map(master => <Option key={master.id} value={master.id}>{master.name}{master.is_default ? ' · Default' : ''}</Option>)}</Select></Field><Button size="sm" variant="outline" disabled={!editable} onClick={() => { setMasterName(`${editor.name} Master`); setModal({ kind: 'master' }); }}><LayoutTemplate size={12}/> Save current page master</Button></FormSection><Alert tone="info">Brand kits centralize color/font tokens. Page masters centralize margins, header, footer and watermark defaults.</Alert></Stack>} {inspectorTab === 'comments' && <Stack gap={10}><FormSection title="Workflow defaults"><Switch checked={Boolean((editor.settings as any)?.workflow?.review_required)} disabled={!editable} onChange={checked => commitEditor({ ...editor, settings: { ...normalizeSettings(editor.settings), workflow: { ...((editor.settings as any)?.workflow ?? {}), review_required: checked } } })} label="Require review before approval"/><Switch checked={Boolean((editor.settings as any)?.workflow?.approval_required)} disabled={!editable} onChange={checked => commitEditor({ ...editor, settings: { ...normalizeSettings(editor.settings), workflow: { ...((editor.settings as any)?.workflow ?? {}), approval_required: checked } } })} label="Require approval before final lock"/><Switch checked={Boolean((editor.settings as any)?.workflow?.signature_required)} disabled={!editable} onChange={checked => commitEditor({ ...editor, settings: { ...normalizeSettings(editor.settings), workflow: { ...((editor.settings as any)?.workflow ?? {}), signature_required: checked } } })} label="Require signature"/><Field label="Default signer role"><Input disabled={!editable} value={String((editor.settings as any)?.workflow?.signer_role ?? '')} onChange={event => commitEditor({ ...editor, settings: { ...normalizeSettings(editor.settings), workflow: { ...((editor.settings as any)?.workflow ?? {}), signer_role: event.target.value } } })} placeholder="Authorized Signer"/></Field></FormSection><CommentPanel comments={comments} selectedBlock={selectedBlockId} canManage={overview.permissions.manage} onAdd={addComment} onResolve={resolveComment}/></Stack>} {inspectorTab === 'preflight' && <Stack gap={10}><Button variant="outline" loading={busy === 'preflight'} onClick={() => void runServerPreflight()}><ShieldCheck size={13}/> Run server preflight</Button>{serverPreflight ? <><div className="document-v6-preflight-stats"><Badge tone={serverPreflight.errors ? 'danger' : 'success'}>{serverPreflight.errors} errors</Badge><Badge tone={serverPreflight.warnings ? 'warning' : 'neutral'}>{serverPreflight.warnings} warnings</Badge><Badge>{serverPreflight.stats.page_count} pages</Badge><Badge>{serverPreflight.stats.block_count} blocks</Badge></div>{serverPreflight.issues.length ? <div className="document-v6-preflight-list">{serverPreflight.issues.map((issue, index) => <div key={`${issue.code}-${index}`} className={`is-${issue.severity}`}><strong>{humanize(issue.code)}</strong><span>{issue.message}</span>{issue.pageId && <small>{issue.pageId}{issue.blockId ? ` · ${issue.blockId}` : ''}</small>}</div>)}</div> : <EmptyState title="Server preflight passed" text="No blocking PDF or document-structure issues were found."/>}</> : <div className="document-v6-preflight-list">{preflightIssues.map(issue => <div key={issue} className="is-warning"><span>{issue}</span></div>)}</div>}</Stack>}</div></> : <EmptyState title="Inspector" text="Open a template to edit block, page, data and preflight settings."/>}</aside>
    </div>}

    {tab === 'generated' && <Stack gap={12}><Card><CardBody><DataGrid rows={overview.generated} columns={generatedColumns} rowKey={row => row.id} persistKey="documents.generated.v6" defaultSort={{ id: 'generated_at', direction: 'desc' }} onRefresh={load} searchPlaceholder="Search generated documents…" empty={<EmptyState icon={<FileCheck2 size={26}/>} title="No generated documents" text="Generate a PDF from a template to start the governed document history."/>} mobileCard={row => <div className="document-v4-mobile-doc"><strong>{row.filename}</strong><span>{humanize(row.workflow_status ?? 'generated')} · {formatDate(row.generated_at)}</span><Button size="sm" variant="ghost" onClick={() => void openGenerated(row)}>Open</Button></div>}/></CardBody></Card>{v6Resources.batch_jobs.length > 0 && <Card><CardBody><DataGrid rows={v6Resources.batch_jobs} columns={batchColumns} rowKey={row => row.id} persistKey="documents.batch-jobs.v6" defaultSort={{ id: 'created', direction: 'desc' }} onRefresh={load} searchPlaceholder="Search document batch jobs…" empty={<EmptyState title="No large batch jobs" text="Batches above 50 sources are processed here by the scheduler."/>}/></CardBody></Card>}</Stack>}

    {tab === 'components' && <div className="document-v4-components"><PageHeader title="Reusable components" description="Build once and reuse approved block collections across workspace templates." actions={<Button disabled={!overview.permissions.components_manage || !editor} onClick={() => { setComponentName(editor && selectedBlock ? `${humanize(selectedBlock.type)} component` : 'New component'); setComponentCategory('General'); setModal({ kind: 'component' }); }}><Plus size={13}/> New from designer</Button>}/>{components.length ? <div className="document-v4-component-grid">{components.map(component => <Card key={component.id}><CardBody><div className="document-v4-component-card"><div><Box size={18}/><strong>{component.name}</strong><span>{component.category || 'General'} · {component.content_schema.length} blocks{component.version ? ` · v${component.version}` : ''}</span></div><div><Button size="sm" variant="outline" disabled={!editor || !editable} onClick={() => insertReusable(component)}>Insert linked</Button><Button size="sm" variant="ghost" disabled={!selectedBlock || selectedBlock.type === 'reusable' || !overview.permissions.components_manage} onClick={() => void updateComponentFromSelection(component)}>Update source</Button><IconButton size="sm" variant="ghost" disabled={!overview.permissions.components_manage} aria-label={`Delete ${component.name}`} onClick={() => void deleteComponent(component)}><Trash2 size={12}/></IconButton></div></div></CardBody></Card>)}</div> : <EmptyState icon={<Box size={28}/>} title="No reusable components" text="Open a template, select a block or use the whole template, then save it as a reusable component."/>}</div>}

    {tab === 'variables' && <div className="document-v4-variable-layout"><Card><CardBody><div className="document-v4-variable-help"><Variable size={22}/><div><h3>Dynamic data bindings</h3><p>Insert variables using double braces. Values are resolved by the server context and escaped before HTML rendering.</p><code>{'{{client.name}}'}</code><code>{'{{invoice.total}}'}</code><code>{'{{employee.salary}}'}</code></div></div></CardBody></Card>{Object.entries(overview.catalog.variables).map(([type, variables]) => <Card key={type}><div className="document-v4-variable-card"><strong>{overview.catalog.types[type] ?? humanize(type)}</strong><div>{variables.map(variable => <Pressable key={variable} type="button" onClick={() => void navigator.clipboard.writeText(`{{${variable}}}`)}><Copy size={10}/><code>{`{{${variable}}}`}</code></Pressable>)}</div></div></Card>)}</div>}

    <Modal open={creating} onClose={() => !busy && setCreating(false)} title="Create document template" description="Start from a safe V6 schema for the selected document type." size="lg" footer={<><Button variant="outline" onClick={() => setCreating(false)}>Cancel</Button><Button loading={busy === 'create'} onClick={() => void createTemplate()}>Create template</Button></>}><FormGrid columns={2}><Field label="Name"><Input value={newName} onChange={event => setNewName(event.target.value)} placeholder="UAE Client Invoice"/></Field><Field label="Document type"><Select value={newType} onChange={event => setNewType(event.target.value)}>{Object.entries(overview.catalog.types).map(([key, label]) => <Option key={key} value={key}>{label}</Option>)}</Select></Field><Field label="Language"><Select value={newLanguage} onChange={event => setNewLanguage(event.target.value)}>{overview.catalog.locales.map(locale => <Option key={locale.code} value={locale.code}>{locale.label}</Option>)}</Select></Field></FormGrid></Modal>

    <Modal open={modal?.kind === 'clone' || modal?.kind === 'variant'} onClose={() => !busy && setModal(null)} title={modal?.kind === 'clone' ? 'Clone template' : 'Create language variant'} footer={<><Button variant="outline" onClick={() => setModal(null)}>Cancel</Button><Button loading={Boolean(busy)} onClick={() => void cloneOrVariant()}>{modal?.kind === 'clone' ? 'Clone' : 'Create variant'}</Button></>}><Stack>{modal?.kind === 'clone' ? <Field label="New template name"><Input value={modalName} onChange={event => setModalName(event.target.value)}/></Field> : <><Field label="Language"><Select value={modalAccess} onChange={event => setModalAccess(event.target.value)}>{overview.catalog.locales.filter(locale => locale.code !== editor?.language).map(locale => <Option key={locale.code} value={locale.code}>{locale.label}</Option>)}</Select></Field><Field label="Optional name"><Input value={modalName} onChange={event => setModalName(event.target.value)} placeholder="Leave blank for automatic name"/></Field></>}</Stack></Modal>

    <Modal open={modal?.kind === 'compare'} onClose={() => setModal(null)} title="Compare template versions" description="Immutable block-level comparison without overwriting either version." size="lg" footer={<Button variant="outline" onClick={() => setModal(null)}>Close</Button>}><Stack><FormGrid columns={2}><Field label="Older / left version"><Select value={compareLeft} onChange={event => setCompareLeft(event.target.value)}>{(editor?.versions ?? []).map(version => <Option key={version.id} value={version.id}>Version {version.version}</Option>)}</Select></Field><Field label="Newer / right version"><Select value={compareRight} onChange={event => setCompareRight(event.target.value)}>{(editor?.versions ?? []).map(version => <Option key={version.id} value={version.id}>Version {version.version}</Option>)}</Select></Field></FormGrid><Button loading={busy === 'compare'} disabled={!compareLeft || !compareRight || compareLeft === compareRight} onClick={() => void compareVersions()}><History size={13}/> Compare</Button>{compareData && <div className="document-v4-diff"><div><strong>Added</strong>{(compareData.added ?? []).map((id: string) => <code key={id}>+ {id}</code>)}</div><div><strong>Removed</strong>{(compareData.removed ?? []).map((id: string) => <code key={id}>− {id}</code>)}</div><div><strong>Changed</strong>{(compareData.changed ?? []).map((id: string) => <code key={id}>~ {id}</code>)}</div></div>}</Stack></Modal>

    <Modal open={modal?.kind === 'component'} onClose={() => setModal(null)} title="Create reusable component" description={selectedBlock ? 'The selected block will become a reusable component.' : 'The full current schema will be reusable.'} footer={<><Button variant="outline" onClick={() => setModal(null)}>Cancel</Button><Button loading={busy === 'component'} disabled={!componentName.trim()} onClick={() => void createComponent()}>Create component</Button></>}><FormGrid columns={2}><Field label="Name"><Input value={componentName} onChange={event => setComponentName(event.target.value)}/></Field><Field label="Category"><Input value={componentCategory} onChange={event => setComponentCategory(event.target.value)}/></Field></FormGrid></Modal>

    <Modal open={Boolean(modal && ['review', 'approve', 'reject'].includes(modal.kind))} onClose={() => setModal(null)} title={modal ? humanize(modal.kind) : 'Workflow action'} description={modal?.document?.filename} footer={<><Button variant="outline" onClick={() => setModal(null)}>Cancel</Button><Button variant={modal?.kind === 'reject' ? 'danger' : 'primary'} loading={Boolean(busy)} disabled={modal?.kind === 'reject' && !modalNote.trim()} onClick={() => void workflowAction(modal!.kind as 'review' | 'approve' | 'reject')}>{humanize(modal?.kind ?? 'Continue')}</Button></>}><Field label={modal?.kind === 'reject' ? 'Reason (required)' : 'Workflow note (optional)'}><Textarea rows={4} value={modalNote} onChange={event => setModalNote(event.target.value)}/></Field></Modal>

    <Modal open={modal?.kind === 'brand'} onClose={() => !busy && setModal(null)} title="Save Brand Kit" description="Create reusable visual tokens and an optional Media Library logo from the current document." footer={<><Button variant="outline" onClick={() => setModal(null)}>Cancel</Button><Button loading={busy === 'brand'} disabled={!brandName.trim()} onClick={() => void createBrandKit()}><Palette size={13}/> Save brand kit</Button></>}><Stack gap={10}><Field label="Name"><Input value={brandName} onChange={event => setBrandName(event.target.value)}/></Field><FormGrid columns={2}><Field label="Primary"><Input type="color" value={brandPrimary} onChange={event => setBrandPrimary(event.target.value)}/></Field><Field label="Secondary"><Input type="color" value={brandSecondary} onChange={event => setBrandSecondary(event.target.value)}/></Field><Field label="Accent"><Input type="color" value={brandAccent} onChange={event => setBrandAccent(event.target.value)}/></Field><Field label="Font"><Select value={brandFont} onChange={event => setBrandFont(event.target.value)}><Option value="Arial">Arial</Option><Option value="Helvetica">Helvetica</Option><Option value="Georgia">Georgia</Option><Option value="Times New Roman">Times New Roman</Option><Option value="Courier New">Courier New</Option><Option value="Noto Sans">Noto Sans</Option><Option value="Noto Sans Arabic">Noto Sans Arabic</Option></Select></Field></FormGrid><Field label="Brand logo" hint="A logo block without its own asset automatically uses this linked Media DAM image."><FormActions><Button type="button" variant="outline" onClick={() => setBrandLogoPicker(true)}><ImageIcon size={13}/> {brandLogoId ? 'Change logo' : 'Select from Media Library'}</Button>{brandLogoId && <><Badge tone="accent">Media #{brandLogoId}</Badge><Button type="button" variant="ghost" onClick={() => setBrandLogoId(null)}>Clear</Button></>}</FormActions></Field></Stack></Modal>

    <Modal open={modal?.kind === 'master'} onClose={() => !busy && setModal(null)} title="Save Page Master" description="Save current margins, header, footer and watermark as reusable page defaults." footer={<><Button variant="outline" onClick={() => setModal(null)}>Cancel</Button><Button loading={busy === 'master'} disabled={!masterName.trim()} onClick={() => void createPageMaster()}><LayoutTemplate size={13}/> Save page master</Button></>}><Field label="Name"><Input value={masterName} onChange={event => setMasterName(event.target.value)} placeholder="Corporate A4 Master"/></Field></Modal>

    <Modal open={modal?.kind === 'batch'} onClose={() => !busy && setModal(null)} title="Batch generate governed PDFs" description="Enter up to 500 source IDs. Batches of 1–50 run immediately; 51–500 are queued and processed by the scheduler in bounded chunks." footer={<><Button variant="outline" onClick={() => setModal(null)}>Cancel</Button><Button loading={busy === 'batch'} disabled={!batchSourceIds.trim()} onClick={() => void batchGenerate()}><GalleryHorizontalEnd size={13}/> Generate / queue batch</Button></>}><Stack gap={10}><Field label="Source IDs" hint="Example: 101, 102, 103"><Textarea rows={6} value={batchSourceIds} onChange={event => setBatchSourceIds(event.target.value)} placeholder="101, 102, 103"/></Field><Alert tone="info"><Clock3 size={13}/> Large batches persist as queue jobs and can continue after this browser request ends.</Alert></Stack></Modal>

    <Modal open={modal?.kind === 'share'} onClose={() => setModal(null)} title="Create secure share link" description="Raw access token is returned once, stored only as SHA-256, and can be revoked." footer={<><Button variant="outline" onClick={() => setModal(null)}>Cancel</Button><Button loading={busy === 'share'} onClick={() => void createShare()}><Share2 size={13}/> Create & copy</Button></>}><FormGrid columns={2}><Field label="Access"><Select value={modalAccess} onChange={event => setModalAccess(event.target.value)}><Option value="view">View inline</Option><Option value="download">Download</Option></Select></Field><Field label="Expires in days"><Input type="number" min="1" max="365" value={modalDays} onChange={event => setModalDays(event.target.value)}/></Field><Field label="Maximum views"><Input type="number" min="1" max="100000" value={modalViews} onChange={event => setModalViews(event.target.value)} placeholder="Unlimited"/></Field></FormGrid></Modal>

    <Modal open={modal?.kind === 'signature'} onClose={() => setModal(null)} title="Request electronic signature" description="External signer receives a hash-token URL. The token itself is never stored in plaintext." footer={<><Button variant="outline" onClick={() => setModal(null)}>Cancel</Button><Button loading={busy === 'signature'} disabled={!modalName.trim()} onClick={() => void createSignature()}><FileSignature size={13}/> Create & copy URL</Button></>}><FormGrid columns={2}><Field label="Signer name"><Input value={modalName} onChange={event => setModalName(event.target.value)}/></Field><Field label="Signer email"><Input type="email" value={modalEmail} onChange={event => setModalEmail(event.target.value)}/></Field><Field label="Role label"><Input value={modalRole} onChange={event => setModalRole(event.target.value)} placeholder="Director, Employee, Client…"/></Field><Field label="Expires in days"><Input type="number" min="1" max="90" value={modalDays} onChange={event => setModalDays(event.target.value)}/></Field></FormGrid></Modal>

    <Drawer open={generatedDrawer} onClose={() => setGeneratedDrawer(false)} title={selectedGenerated?.filename ?? 'Document governance'} description={selectedGenerated ? `${humanize(selectedGenerated.workflow_status ?? 'generated')} · ${selectedGenerated.render_driver ?? 'renderer unknown'}` : undefined}>{selectedGenerated && <Stack gap={14}><div className="document-v4-governance-actions"><Button variant="outline" onClick={() => void downloadGenerated(selectedGenerated.id, workspaceId)}><Download size={13}/> Download</Button>{overview.permissions.manage && <Button variant="outline" disabled={Boolean(selectedGenerated.locked_at)} onClick={() => { setModal({ kind: 'review', document: selectedGenerated }); setModalNote(''); }}><Send size={13}/> Review</Button>}{overview.permissions.approve && <><Button variant="outline" disabled={Boolean(selectedGenerated.locked_at) || (generatedPolicy(selectedGenerated).review_required && selectedGenerated.workflow_status !== 'in_review')} onClick={() => { setModal({ kind: 'approve', document: selectedGenerated }); setModalNote(''); }}><BadgeCheck size={13}/> Approve</Button><Button variant="outline" disabled={!['approved', 'signed'].includes(selectedGenerated.workflow_status ?? '') || (generatedPolicy(selectedGenerated).approval_required && !selectedGenerated.approved_at) || (generatedPolicy(selectedGenerated).signature_required && !selectedGenerated.signed_at)} onClick={() => void workflowAction('lock')}><LockKeyhole size={13}/> Lock</Button></>}{overview.permissions.share && <Button variant="outline" onClick={() => { setModal({ kind: 'share', document: selectedGenerated }); setModalAccess('view'); setModalDays('14'); setModalViews(''); }}><Share2 size={13}/> Share</Button>}{overview.permissions.sign && <Button variant="outline" disabled={generatedPolicy(selectedGenerated).approval_required && !selectedGenerated.approved_at} onClick={() => { setModal({ kind: 'signature', document: selectedGenerated }); setModalName(''); setModalEmail(''); setModalRole(generatedPolicy(selectedGenerated).signer_role); setModalDays('14'); }}><FileSignature size={13}/> Signature</Button>}</div>
      <FormSection title="Workflow status"><div className="document-v4-status-strip"><Badge tone={selectedGenerated.workflow_status === 'signed' || selectedGenerated.workflow_status === 'approved' ? 'success' : selectedGenerated.workflow_status === 'rejected' ? 'danger' : 'neutral'}>{humanize(selectedGenerated.workflow_status ?? 'generated')}</Badge>{selectedGenerated.approved_at && <span>Approved {formatDate(selectedGenerated.approved_at)}</span>}{selectedGenerated.signed_at && <span>Signed {formatDate(selectedGenerated.signed_at)}</span>}{selectedGenerated.locked_at && <span><LockKeyhole size={11}/> Locked</span>}</div></FormSection><FormSection title="Policy snapshot"><div className="document-v4-status-strip">{Boolean((selectedGenerated.render_metadata as any)?.workflow_policy?.review_required) && <Badge>Review required</Badge>}{Boolean((selectedGenerated.render_metadata as any)?.workflow_policy?.approval_required) && <Badge>Approval required</Badge>}{Boolean((selectedGenerated.render_metadata as any)?.workflow_policy?.signature_required) && <Badge>Signature required</Badge>}{(selectedGenerated.render_metadata as any)?.workflow_policy?.signer_role && <span>Signer role: {String((selectedGenerated.render_metadata as any).workflow_policy.signer_role)}</span>}</div></FormSection>
      <FormSection title={`Share links (${selectedGenerated.share_links?.length ?? 0})`}>{selectedGenerated.share_links?.length ? <div className="document-v4-governance-list">{selectedGenerated.share_links.map(link => <div key={link.id}><div><strong>{humanize(link.access_mode)}</strong><small>{link.revoked_at ? 'Revoked' : link.expires_at ? `Expires ${new Date(link.expires_at).toLocaleString()}` : 'No expiry'} · {link.view_count}{link.max_views ? ` / ${link.max_views}` : ''} views</small></div>{!link.revoked_at && overview.permissions.share && <Button size="sm" variant="ghost" onClick={() => void revokeShare(link.id)}>Revoke</Button>}</div>)}</div> : <EmptyState title="No active share history"/>}</FormSection>
      <FormSection title={`Signature requests (${selectedGenerated.signature_requests?.length ?? 0})`}>{selectedGenerated.signature_requests?.length ? <div className="document-v4-governance-list">{selectedGenerated.signature_requests.map(request => <div key={request.id}><div><strong>{request.signer_name ?? request.signer_email ?? 'Signer'}</strong><small>{request.role_label ?? 'Signer'} · {humanize(request.status)}{request.expires_at ? ` · expires ${new Date(request.expires_at).toLocaleDateString()}` : ''}</small></div><Badge tone={request.status === 'signed' ? 'success' : request.status === 'declined' ? 'danger' : 'neutral'}>{humanize(request.status)}</Badge></div>)}</div> : <EmptyState title="No signature requests"/>}</FormSection>
      <FormSection title={`Review timeline (${selectedGenerated.review_events?.length ?? 0})`}>{selectedGenerated.review_events?.length ? <div className="document-v4-timeline">{selectedGenerated.review_events.map(event => <div key={event.id}><span /><div><strong>{humanize(event.event)}</strong><small>{new Date(event.created_at).toLocaleString()}</small>{event.note && <p>{event.note}</p>}</div></div>)}</div> : <EmptyState title="No workflow events"/>}</FormSection>
      <FormSection title={`Comments (${selectedGenerated.comments?.length ?? 0})`}>{selectedGenerated.comments?.length ? <div className="document-v4-governance-list">{selectedGenerated.comments.map(comment => <div key={comment.id}><div><strong>{[comment.author?.user?.first_name, comment.author?.user?.last_name].filter(Boolean).join(' ') || 'Member'}</strong><small>{new Date(comment.created_at).toLocaleString()}</small><p>{comment.body}</p></div></div>)}</div> : <EmptyState title="No document comments"/>}</FormSection>
    </Stack>}</Drawer>

    <MediaPicker open={Boolean(mediaBlock)} workspaceId={workspaceId} imagesOnly title="Choose document image" onClose={() => setMediaBlock(null)} onSelect={mediaSelected}/>
    <MediaPicker open={brandLogoPicker} workspaceId={workspaceId} imagesOnly title="Choose Brand Kit logo" onClose={() => setBrandLogoPicker(false)} onSelect={asset => { setBrandLogoId(asset.id); setBrandLogoPicker(false); }}/>
  </Page>;
}
