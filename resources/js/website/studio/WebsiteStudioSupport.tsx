import { type ReactNode } from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Blocks, CheckCircle2, Copy, Eye, Globe2, GripVertical, Image as ImageIcon, Link2, ListPlus, MessageSquare, MoreHorizontal, Plus, Trash2 } from 'lucide-react';
import RichTextEditor from '../../components/RichTextEditor';
import { Alert, Badge, Box, Button, Dropdown, EmptyState, Field, FormDialog, FormGrid, FormSection, IconButton, Inline, Input, Option, Pressable, Select, SettingRow, Stack, Switch, Text, Textarea } from '../../design-system';
import type { PublicWebsitePayload, WebsitePublicForm, WebsiteSchema, WebsiteSection } from '../types';

export type Site = {
    id: number;
    uuid: string;
    name: string;
    status: string;
    default_language: string;
    supported_languages: string[];
    theme: Record<string, any>;
    header_config: Record<string, any>;
    footer_config: Record<string, any>;
    seo_defaults: Record<string, any>;
    custom_domain_id?: number | null;
};
export type PageRow = {
    id: number;
    uuid: string;
    page_type: string;
    language: string;
    title: string;
    slug: string;
    status: string;
    is_home: boolean;
    navigation_visible: boolean;
    navigation_label?: string | null;
    sort_order: number;
    current_version: number;
    published_version?: number | null;
    staged_version?: number | null;
    staged_at?: string | null;
    seo_title?: string | null;
    seo_description?: string | null;
    og_media_id?: number | null;
    published_at?: string | null;
};
export type VersionRow = {
    id: number;
    version: number;
    change_note?: string | null;
    published_at?: string | null;
    created_at: string;
};
export type ReusableSection = {
    id: number;
    uuid: string;
    name: string;
    section_type: string;
    schema: WebsiteSection;
    is_global: boolean;
};
export type WebsiteForm = {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    status: string;
    fields: Array<{
        id: string;
        type: string;
        label: string;
        required: boolean;
        options?: string[];
    }>;
    settings?: Record<string, any>;
    success_message?: string | null;
    notification_emails?: string[];
    submissions_count?: number;
};
export type Domain = {
    id: number;
    hostname: string;
    status: string;
    purpose: string;
    certificate_status: string;
};
export type OverviewPayload = {
    site: Site;
    pages: PageRow[];
    forms: WebsiteForm[];
    reusable_sections: ReusableSection[];
    domains: Domain[];
    submission_summary: Record<string, number>;
    catalog: {
        page_types: string[];
        section_types: string[];
    };
    permissions: {
        manage: boolean;
        publish: boolean;
        forms_manage: boolean;
        submissions_view: boolean;
    };
};
export type LeadRow = {
    id: number;
    uuid: string;
    form: string;
    form_id: number;
    status: string;
    payload: Record<string, unknown>;
    consent: boolean;
    source_url?: string | null;
    internal_note?: string | null;
    submitted_at: string;
};
export type AutosaveDraft = {
    uuid: string;
    revision: number;
    schema: WebsiteSchema;
    metadata: Partial<PageRow>;
    updated_at: string;
    updated_by_member_id?: number | null;
};
export type PreflightIssue = {
    severity: 'error' | 'warning';
    code: string;
    message: string;
    sectionId?: string | null;
};
export type PreflightResult = {
    ready: boolean;
    issues: PreflightIssue[];
    summary: {
        errors: number;
        warnings: number;
        sections: number;
        media_assets: number;
    };
};
export type ReviewComment = {
    id: number;
    uuid: string;
    section_id?: string | null;
    message: string;
    status: 'open' | 'resolved';
    created_by?: string | null;
    created_at?: string | null;
    resolved_at?: string | null;
};
export type PreviewTokenRow = {
    id: number;
    uuid: string;
    source: string;
    version: number;
    expires_at: string;
    revoked_at?: string | null;
    last_viewed_at?: string | null;
    created_at: string;
};
export type SharePreview = {
    url: string;
    version: number;
    expires_at: string;
};
export type LeftPanel = 'pages' | 'layers' | 'blocks' | 'assets';
export type InspectorPanel = 'content' | 'design' | 'settings' | 'effects' | 'seo' | 'review';
export const localeOptions = ['en', 'tr', 'ru', 'ur', 'ar', 'de', 'fr', 'es', 'it', 'pt'];
export const itemSectionTypes = new Set(['features', 'stats', 'services', 'team', 'portfolio', 'testimonials', 'pricing', 'faq', 'columns']);
/** Returns a complete starter section for one Website Studio section type. */
export function sectionDefaults(type: string): WebsiteSection {
    const id = `section_${crypto.randomUUID().replaceAll('-', '').slice(0, 10)}`;
    const base = { id, type, settings: { title: 'Section title', body: 'Add useful content for your visitors.' } } as WebsiteSection;
    if (type === 'hero')
        base.settings = { eyebrow: '', title: 'Build something meaningful.', body: 'Introduce your company with a clear value proposition.', primary_label: 'Get started', primary_url: '#', secondary_label: 'Learn more', secondary_url: '#', media_id: null };
    if (type === 'rich_text' || type === 'custom')
        base.settings = { title: '', html: '<p>Write your content here.</p>' };
    if (type === 'image')
        base.settings = { title: '', media_id: null, caption: '', alt: '' };
    if (type === 'gallery')
        base.settings = { title: 'Gallery', media_ids: [], columns: 3 };
    if (itemSectionTypes.has(type))
        base.settings = { title: type === 'faq' ? 'Frequently asked questions' : 'Section title', body: '', items: type === 'faq' ? [{ question: 'Question', answer: 'Answer' }] : [{ title: 'Item title', text: 'Describe this item.' }] };
    if (type === 'form')
        base.settings = { title: 'Contact us', body: 'Send us a message and our team will respond.', form_uuid: '' };
    if (type === 'cta')
        base.settings = { title: 'Ready to get started?', body: 'Make the next action clear.', button_label: 'Contact us', button_url: '/contact' };
    if (type === 'divider')
        base.settings = {};
    if (type === 'spacer')
        base.settings = { height: 48 };
    return base;
}
/** Renders one sortable editor section row with stable drag handles and selection state. */
export function SortableSection({ section, selected, onSelect, onDuplicate, onRemove }: {
    section: WebsiteSection;
    selected: boolean;
    onSelect: () => void;
    onDuplicate: () => void;
    onRemove: () => void;
}) {
    const sortable = useSortable({ id: section.id }), style = { transform: CSS.Transform.toString(sortable.transform), transition: sortable.transition };
    return <div ref={sortable.setNodeRef} style={style} className={`website-section-row${selected ? ' is-selected' : ''}`} onClick={onSelect}><Pressable type="button" className="website-section-row__handle" {...sortable.attributes} {...sortable.listeners} aria-label="Reorder section"><GripVertical size={14}/></Pressable><div><strong>{section.type.replaceAll('_', ' ')}</strong><span>{String(section.settings.title || section.settings.eyebrow || 'Section')}</span></div><Dropdown trigger={<IconButton variant="ghost" size="sm" aria-label="Section actions"><MoreHorizontal size={14}/></IconButton>} items={[{ label: 'Duplicate', icon: <Copy size={13}/>, onClick: onDuplicate }, { label: 'Remove', icon: <Trash2 size={13}/>, danger: true, onClick: onRemove }]}/></div>;
}
/** Resolves the allowlisted Website Studio dynamic tokens for in-editor preview parity. */
export function bindPreviewTokens(value: any, context: Record<string, string>): any {
    if (Array.isArray(value))
        return value.map(item => bindPreviewTokens(item, context));
    if (value && typeof value === 'object')
        return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, bindPreviewTokens(item, context)]));
    if (typeof value !== 'string' || !value.includes('{{'))
        return value;
    return value.replace(/\{\{\s*([a-z0-9_.-]+)\s*\}\}/gi, (match, key) => Object.prototype.hasOwnProperty.call(context, key) ? context[key] : match);
}
/** Converts the current editor state into the same public renderer contract used by published websites. */
export function previewPayload(site: Site, page: PageRow, schema: WebsiteSchema, forms: WebsiteForm[], pages: PageRow[] = []): PublicWebsitePayload {
    const publicForms = Object.fromEntries(forms.filter(form => form.status === 'active').map(form => [form.uuid, { uuid: form.uuid, name: form.name, fields: form.fields, settings: form.settings || {}, success_message: form.success_message } satisfies WebsitePublicForm]));
    const navigation = (pages.length ? pages : [page]).filter(item => item.navigation_visible && item.status !== 'archived' && item.language === page.language).sort((a, b) => a.sort_order - b.sort_order).map(item => ({ label: item.navigation_label || item.title, path: item.is_home ? '/' : `/${item.slug}` }));
    const boundSchema = bindPreviewTokens(schema, { 'site.name': site.name, 'page.title': page.title, 'page.slug': page.slug, 'page.language': page.language, 'year': String(new Date().getFullYear()) }) as WebsiteSchema;
    return { site: { uuid: site.uuid, name: site.name, default_language: site.default_language, supported_languages: site.supported_languages || [site.default_language], theme: site.theme || {}, header_config: site.header_config || {}, footer_config: site.footer_config || {}, seo_defaults: site.seo_defaults || {} }, page: { uuid: page.uuid, type: page.page_type, language: page.language, title: page.title, slug: page.slug, seo_title: page.seo_title, seo_description: page.seo_description, schema: boundSchema }, navigation, forms: publicForms };
}
/** Return actionable quality issues for SEO, accessibility and responsive Website Studio structure. */
export function websitePageAudit(page: PageRow | null, schema: WebsiteSchema): string[] {
    if (!page)
        return [];
    const issues: string[] = [];
    const seoTitle = (page.seo_title || page.title || '').trim(), seoDescription = (page.seo_description || '').trim();
    if (!schema.sections.length)
        issues.push('Add at least one section before publishing.');
    if (seoTitle.length < 20)
        issues.push('SEO title is very short; make the search result more descriptive.');
    if (seoTitle.length > 65)
        issues.push('SEO title is longer than 65 characters and may truncate.');
    if (seoDescription.length < 70)
        issues.push('Add a useful SEO description of roughly 70–160 characters.');
    if (seoDescription.length > 170)
        issues.push('SEO description is longer than 170 characters and may truncate.');
    const anchors = schema.sections.map(section => String((section.settings?.design as any)?.anchor || '').trim()).filter(Boolean);
    if (new Set(anchors).size !== anchors.length)
        issues.push('Section anchor IDs must be unique.');
    for (const section of schema.sections) {
        const settings = section.settings || {}, design = (settings.design || {}) as Record<string, any>;
        if (section.type === 'image' && settings.media_id && !String(settings.alt || '').trim())
            issues.push(`Image section “${String(settings.title || section.id)}” needs alt text.`);
        if (section.type === 'form' && !settings.form_uuid)
            issues.push(`Form section “${String(settings.title || section.id)}” is not connected to a lead form.`);
        if (design.hide_desktop && design.hide_tablet && design.hide_mobile)
            issues.push(`Section “${String(settings.title || section.type)}” is hidden on every device.`);
    }
    return Array.from(new Set(issues));
}
/** Extract unique Media Library IDs referenced by the current in-memory website schema. */
export function schemaMediaIds(schema: WebsiteSchema): number[] {
    const ids: number[] = []; /** Walk nested schema values looking only for stable media ID keys. */ /** Walk nested schema values looking only for stable media ID keys. */
    const walk = (value: any, key = '') => {
        if (key === 'media_ids' && Array.isArray(value)) {
            for (const id of value)
                if (Number.isFinite(Number(id)))
                    ids.push(Number(id));
            return;
        }
        if (['media_id', 'image_id', 'og_media_id'].includes(key) && Number.isFinite(Number(value)))
            ids.push(Number(value));
        else if (value && typeof value === 'object')
            for (const [childKey, child] of Object.entries(value))
                walk(child, childKey);
    };
    walk(schema);
    return Array.from(new Set(ids));
}
/** Build the metadata payload persisted alongside a mutable autosave draft. */
export function autosaveMetadata(page: PageRow) { return { title: page.title, slug: page.slug, language: page.language, is_home: page.is_home, navigation_visible: page.navigation_visible, navigation_label: page.navigation_label ?? null, seo_title: page.seo_title ?? null, seo_description: page.seo_description ?? null, og_media_id: page.og_media_id ?? null }; }
/** Handles the Website Studio V4-style page/section/form/publishing workspace. */
/** Renders one focused Website Studio inspector tab for the selected layer. */
export function SectionInspector({ panel, section, update, forms, onMedia, onSaveReusable, onUpdateLinkedSource }: {
    panel: Exclude<InspectorPanel, 'seo' | 'review'>;
    section: WebsiteSection | null;
    update: (patch: Record<string, any>) => void;
    forms: WebsiteForm[];
    onMedia: (mode: 'single' | 'gallery') => void;
    onSaveReusable: () => void;
    onUpdateLinkedSource: () => void;
}) {
    if (!section)
        return <EmptyState title="Select a layer" text="Choose a layer from the Layers rail to edit its properties."/>;
    const s = section.settings || {}, design = (s.design || {}) as Record<string, any>, effects = (s.effects || {}) as Record<string, any>;
    /** Updates one section-level design token without replacing content settings. */
    const updateDesign = (patch: Record<string, any>) => update({ design: { ...design, ...patch } });
    /** Updates one section-level motion/effect token. */
    const updateEffects = (patch: Record<string, any>) => update({ effects: { ...effects, ...patch } });
    /** Updates one repeated item without replacing the entire section. */
    const updateItem = (index: number, patch: Record<string, any>) => update({ items: (s.items || []).map((item: any, i: number) => i === index ? { ...item, ...patch } : item) });
    /** Adds one generic item appropriate for repeated-card sections. */
    const addItem = () => update({ items: [...(s.items || []), section.type === 'faq' ? { question: 'Question', answer: 'Answer' } : { title: 'Item title', text: 'Describe this item.' }] });
    if (panel === 'content')
        return <FormSection title={`${section.type.replaceAll('_', ' ')} content`} description="Visitor-facing copy, media and repeated items."><Stack>{['hero', 'features', 'stats', 'services', 'team', 'portfolio', 'testimonials', 'pricing', 'faq', 'form', 'cta', 'gallery'].includes(section.type) && <Field label="Title"><Input value={String(s.title || '')} onChange={e => update({ title: e.target.value })}/></Field>}{['hero', 'features', 'stats', 'services', 'team', 'portfolio', 'testimonials', 'pricing', 'form', 'cta'].includes(section.type) && <Field label="Body"><Textarea value={String(s.body || '')} onChange={e => update({ body: e.target.value })}/></Field>}{(section.type === 'rich_text' || section.type === 'custom') && <RichTextEditor value={String(s.html || '')} onChange={html => update({ html })} placeholder="Write page content…"/>}{['hero', 'image'].includes(section.type) && <Button variant="outline" onClick={() => onMedia('single')}><ImageIcon size={13}/> Choose image from DAM</Button>}{section.type === 'image' && <><Field label="Caption"><Input value={String(s.caption || '')} onChange={e => update({ caption: e.target.value })}/></Field><Field label="Fallback alt text"><Input value={String(s.alt || '')} onChange={e => update({ alt: e.target.value })} placeholder="Prefer Media DAM alt text"/></Field></>}{section.type === 'gallery' && <><Button variant="outline" onClick={() => onMedia('gallery')}><ImageIcon size={13}/> Add gallery image</Button><Field label="Columns"><Input type="number" min={1} max={6} value={Number(s.columns || 3)} onChange={e => update({ columns: Number(e.target.value) })}/></Field></>}{section.type === 'hero' && <><Field label="Eyebrow"><Input value={String(s.eyebrow || '')} onChange={e => update({ eyebrow: e.target.value })}/></Field><FormGrid><Field label="Primary button"><Input value={String(s.primary_label || '')} onChange={e => update({ primary_label: e.target.value })}/></Field><Field label="Primary URL"><Input value={String(s.primary_url || '')} onChange={e => update({ primary_url: e.target.value })}/></Field><Field label="Secondary button"><Input value={String(s.secondary_label || '')} onChange={e => update({ secondary_label: e.target.value })}/></Field><Field label="Secondary URL"><Input value={String(s.secondary_url || '')} onChange={e => update({ secondary_url: e.target.value })}/></Field></FormGrid></>}{section.type === 'cta' && <FormGrid><Field label="Button label"><Input value={String(s.button_label || '')} onChange={e => update({ button_label: e.target.value })}/></Field><Field label="Button URL"><Input value={String(s.button_url || '')} onChange={e => update({ button_url: e.target.value })}/></Field></FormGrid>}{section.type === 'form' && <Field label="Lead form"><Select value={String(s.form_uuid || '')} onChange={e => update({ form_uuid: e.target.value })}><Option value="">Select form</Option>{forms.filter(form => form.status === 'active').map(form => <Option key={form.uuid} value={form.uuid}>{form.name}</Option>)}</Select></Field>}{itemSectionTypes.has(section.type) && <div className="website-item-editor"><Inline justify="space-between"><strong>Items</strong><Button size="sm" variant="ghost" onClick={addItem}><ListPlus size={12}/> Add item</Button></Inline>{(s.items || []).map((item: any, index: number) => <div key={index} className="website-item-editor__row"><Input value={String(item.title || item.question || '')} onChange={e => updateItem(index, section.type === 'faq' ? { question: e.target.value } : { title: e.target.value })}/><Textarea value={String(item.text || item.answer || '')} onChange={e => updateItem(index, section.type === 'faq' ? { answer: e.target.value } : { text: e.target.value })}/><IconButton size="sm" variant="ghost" aria-label="Remove item" onClick={() => update({ items: (s.items || []).filter((_: any, i: number) => i !== index) })}><Trash2 size={12}/></IconButton></div>)}</div>}{section.type === 'spacer' && <Field label="Height"><Input type="number" min={8} max={240} value={Number(s.height || 48)} onChange={e => update({ height: Number(e.target.value) })}/></Field>}</Stack></FormSection>;
    if (panel === 'design')
        return <FormSection title="Design" description="Section-scoped visual tokens that render identically in preview and public delivery."><Stack><FormGrid columns={2}><Field label="Background"><Input type="color" value={String(design.background || '#ffffff')} onChange={e => updateDesign({ background: e.target.value })}/></Field><Field label="Text color"><Input type="color" value={String(design.text_color || '#111827')} onChange={e => updateDesign({ text_color: e.target.value })}/></Field><Field label="Vertical padding"><Input type="number" min={0} max={220} value={Number(design.padding_y ?? 72)} onChange={e => updateDesign({ padding_y: Number(e.target.value) })}/></Field><Field label="Content width"><Input type="number" min={520} max={1600} value={Number(design.content_width ?? 1180)} onChange={e => updateDesign({ content_width: Number(e.target.value) })}/></Field><Field label="Alignment"><Select value={String(design.align || 'left')} onChange={e => updateDesign({ align: e.target.value })}><Option value="left">Left</Option><Option value="center">Center</Option><Option value="right">Right</Option></Select></Field><Field label="Surface radius"><Input type="number" min={0} max={48} value={Number(design.radius ?? 0)} onChange={e => updateDesign({ radius: Number(e.target.value) })}/></Field></FormGrid><SettingRow title="Full bleed background" description="Allow the section background to span the viewport while content remains bounded." control={<Switch checked={Boolean(design.full_bleed)} onChange={checked => updateDesign({ full_bleed: checked })} label="Full bleed"/>}/><FormSection title="Responsive overrides" description="Override spacing and content width only where this section needs it; desktop values remain the base."><Stack><FormGrid columns={2}><Field label="Tablet padding"><Input type="number" min={0} max={220} value={Number(design.breakpoints?.tablet?.padding_y ?? design.padding_y ?? 64)} onChange={e => updateDesign({ breakpoints: { ...design.breakpoints, tablet: { ...design.breakpoints?.tablet, padding_y: Number(e.target.value) } } })}/></Field><Field label="Tablet width"><Input type="number" min={320} max={1200} value={Number(design.breakpoints?.tablet?.content_width ?? Math.min(Number(design.content_width ?? 1180), 900))} onChange={e => updateDesign({ breakpoints: { ...design.breakpoints, tablet: { ...design.breakpoints?.tablet, content_width: Number(e.target.value) } } })}/></Field><Field label="Mobile padding"><Input type="number" min={0} max={180} value={Number(design.breakpoints?.mobile?.padding_y ?? Math.min(Number(design.padding_y ?? 48), 72))} onChange={e => updateDesign({ breakpoints: { ...design.breakpoints, mobile: { ...design.breakpoints?.mobile, padding_y: Number(e.target.value) } } })}/></Field><Field label="Mobile width"><Input type="number" min={280} max={760} value={Number(design.breakpoints?.mobile?.content_width ?? 560)} onChange={e => updateDesign({ breakpoints: { ...design.breakpoints, mobile: { ...design.breakpoints?.mobile, content_width: Number(e.target.value) } } })}/></Field></FormGrid></Stack></FormSection></Stack></FormSection>;
    if (panel === 'effects')
        return <FormSection title="Effects" description="Motion is progressive enhancement and automatically disabled for reduced-motion visitors."><Stack><Field label="Entrance effect"><Select value={String(effects.animation || 'none')} onChange={e => updateEffects({ animation: e.target.value })}><Option value="none">None</Option><Option value="fade">Fade</Option><Option value="fade-up">Fade up</Option><Option value="scale">Scale in</Option></Select></Field><FormGrid columns={2}><Field label="Duration (ms)"><Input type="number" min={100} max={2000} step={50} value={Number(effects.duration ?? 500)} onChange={e => updateEffects({ duration: Number(e.target.value) })}/></Field><Field label="Delay (ms)"><Input type="number" min={0} max={2000} step={50} value={Number(effects.delay ?? 0)} onChange={e => updateEffects({ delay: Number(e.target.value) })}/></Field><Field label="Easing"><Select value={String(effects.easing || 'ease-out')} onChange={e => updateEffects({ easing: e.target.value })}><Option value="ease-out">Ease out</Option><Option value="ease-in-out">Ease in/out</Option><Option value="linear">Linear</Option></Select></Field></FormGrid><Alert tone="info">Effects never block content visibility and are disabled when the visitor prefers reduced motion.</Alert></Stack></FormSection>;
    return <FormSection title="Layer settings" description="Identity, responsive visibility, data bindings and reusable component actions."><Stack>{s.linked_reusable_uuid && <Stack gap={7}><Alert tone="info">Linked global component · source updates propagate into this page's mutable draft.</Alert><Button size="sm" variant="outline" onClick={onUpdateLinkedSource}><Globe2 size={12}/> Push instance edits to global source</Button></Stack>}<Field label="Anchor ID"><Input value={String(design.anchor || '')} onChange={e => updateDesign({ anchor: e.target.value })} placeholder="about-us"/></Field><Alert tone="info">Dynamic content tokens: <code>{'{{site.name}}'}</code>, <code>{'{{page.title}}'}</code>, <code>{'{{page.slug}}'}</code>, <code>{'{{page.language}}'}</code>, <code>{'{{year}}'}</code>.</Alert><SettingRow title="Desktop visibility" description="Hide this layer on wide desktop viewports." control={<Switch checked={!Boolean(design.hide_desktop)} onChange={checked => updateDesign({ hide_desktop: !checked })} label="Desktop visible"/>}/><SettingRow title="Tablet visibility" description="Hide this layer between mobile and desktop breakpoints." control={<Switch checked={!Boolean(design.hide_tablet)} onChange={checked => updateDesign({ hide_tablet: !checked })} label="Tablet visible"/>}/><SettingRow title="Mobile visibility" description="Hide this layer on narrow mobile viewports." control={<Switch checked={!Boolean(design.hide_mobile)} onChange={checked => updateDesign({ hide_mobile: !checked })} label="Mobile visible"/>}/><Button variant="outline" onClick={onSaveReusable}><Blocks size={13}/> Save as reusable component</Button></Stack></FormSection>;
}
/** Renders page/section review comments and staging-link governance in one inspector panel. */
export function ReviewInspector({ page, selectedSectionId, comments, commentDraft, setCommentDraft, onAdd, onStatus, previews, onCreatePreview, onRevoke }: {
    page: PageRow;
    selectedSectionId: string | null;
    comments: ReviewComment[];
    commentDraft: string;
    setCommentDraft: (value: string) => void;
    onAdd: () => void;
    onStatus: (comment: ReviewComment, status: 'open' | 'resolved') => void;
    previews: PreviewTokenRow[];
    onCreatePreview: () => void;
    onRevoke: (token: PreviewTokenRow) => void;
}) {
    const open = comments.filter(comment => comment.status === 'open'), resolved = comments.filter(comment => comment.status === 'resolved'), activePreviews = previews.filter(token => !token.revoked_at && new Date(token.expires_at).getTime() > Date.now());
    return <Stack gap={12}><FormSection title="Review" description="Discuss the whole page or anchor feedback to the currently selected layer."><Stack><Inline gap={6}><Badge tone={open.length ? 'warning' : 'success'}>{open.length} open</Badge>{selectedSectionId && <Badge tone="accent">Layer · {selectedSectionId}</Badge>}</Inline><Field label={selectedSectionId ? 'Comment on selected layer' : 'Page comment'}><Textarea rows={4} value={commentDraft} onChange={event => setCommentDraft(event.target.value)} placeholder="Describe the change, question or approval note…"/></Field><Button variant="primary" size="sm" disabled={!commentDraft.trim()} onClick={onAdd}><MessageSquare size={12}/> Add review comment</Button><div className="website-review-list">{open.map(comment => <div className="website-review-item" key={comment.id}><div><Inline gap={6}><Badge tone="warning">Open</Badge>{comment.section_id && <Badge>{comment.section_id}</Badge>}</Inline><p>{comment.message}</p><small>{comment.created_by || 'Workspace member'} · {comment.created_at ? new Date(comment.created_at).toLocaleString() : 'recently'}</small></div><Button size="sm" variant="ghost" onClick={() => onStatus(comment, 'resolved')}><CheckCircle2 size={12}/> Resolve</Button></div>)}{!open.length && <EmptyState title="No open review comments" text="This page has no unresolved feedback."/>}</div>{resolved.length > 0 && <details className="website-review-resolved"><summary>{resolved.length} resolved comment{resolved.length === 1 ? '' : 's'}</summary>{resolved.slice(0, 20).map(comment => <div className="website-review-item is-resolved" key={comment.id}><div><p>{comment.message}</p><small>{comment.created_by || 'Workspace member'}{comment.section_id ? ` · ${comment.section_id}` : ''}</small></div><Button size="sm" variant="ghost" onClick={() => onStatus(comment, 'open')}>Reopen</Button></div>)}</details>}</Stack></FormSection><FormSection title="Staging previews" description={`Staged ${page.staged_version ? `v${page.staged_version}` : 'none'} · Published ${page.published_version ? `v${page.published_version}` : 'none'}`}><Stack><Button size="sm" variant="outline" onClick={onCreatePreview}><Link2 size={12}/> {page.staged_version ? 'Create share link' : 'Stage & create share link'}</Button>{activePreviews.map(token => <div className="website-preview-token" key={token.id}><span><strong>Staging v{token.version}</strong><small>Expires {new Date(token.expires_at).toLocaleString()}{token.last_viewed_at ? ` · viewed ${new Date(token.last_viewed_at).toLocaleString()}` : ''}</small></span><Button size="sm" variant="ghost" onClick={() => onRevoke(token)}>Revoke</Button></div>)}{!activePreviews.length && <Text size={10.5} color="var(--text-3)">No active share links. Token URLs are shown only once when created.</Text>}</Stack></FormSection></Stack>;
}
/** Renders page-level SEO, navigation and publish-preflight controls. */
export function PageSeoInspector({ page, updatePage, localIssues, preflight, onPreflight, onMedia }: {
    page: PageRow;
    updatePage: (patch: Partial<PageRow>) => void;
    localIssues: string[];
    preflight: PreflightResult | null;
    onPreflight: () => Promise<PreflightResult | null>;
    onMedia: () => void;
}) {
    const issues = preflight?.issues ?? localIssues.map(message => ({ severity: 'warning' as const, code: 'local', message }));
    return <><FormSection title="Page & SEO" description="Metadata, navigation and social sharing for this localized page."><Stack><Field label="Title"><Input value={page.title} onChange={e => updatePage({ title: e.target.value })}/></Field><Field label="Slug"><Input value={page.slug} disabled={page.is_home} onChange={e => updatePage({ slug: e.target.value })}/></Field><Field label="Language"><Select value={page.language} onChange={e => updatePage({ language: e.target.value })}>{localeOptions.map(value => <Option key={value} value={value}>{value.toUpperCase()}</Option>)}</Select></Field><Field label="SEO title"><Input value={page.seo_title || ''} onChange={e => updatePage({ seo_title: e.target.value })}/></Field><Text size={10} color="var(--text-3)">{(page.seo_title || page.title).length}/65 recommended characters</Text><Field label="SEO description"><Textarea value={page.seo_description || ''} onChange={e => updatePage({ seo_description: e.target.value })}/></Field><Text size={10} color="var(--text-3)">{(page.seo_description || '').length}/160 recommended characters</Text><SettingRow title="Home page" description="Use this page as the root path for its language." control={<Switch checked={page.is_home} onChange={checked => updatePage({ is_home: checked })} label="Home page"/>}/><SettingRow title="Navigation" description="Include this page in the generated public navigation." control={<Switch checked={page.navigation_visible} onChange={checked => updatePage({ navigation_visible: checked })} label="Navigation visible"/>}/>{page.navigation_visible && <Field label="Navigation label"><Input value={page.navigation_label || ''} onChange={e => updatePage({ navigation_label: e.target.value })} placeholder={page.title}/></Field>}<Button variant="outline" onClick={onMedia}><ImageIcon size={13}/> Select OpenGraph image</Button></Stack></FormSection><FormSection title="Publish preflight" description="Server validation covers SEO, referenced forms, Media DAM availability/rights and unsafe links."><Stack><Inline justify="space-between" align="center"><Badge tone={preflight?.ready === false ? 'danger' : preflight?.ready ? 'success' : 'neutral'}>{preflight ? `${preflight.summary.errors} errors · ${preflight.summary.warnings} warnings` : `${localIssues.length} local checks`}</Badge><Button size="sm" variant="outline" onClick={() => void onPreflight()}><Eye size={12}/> Run preflight</Button></Inline>{issues.length ? <div className="website-preflight-list">{issues.slice(0, 12).map((issue, index) => <div key={`${issue.code}-${index}`} className={`is-${issue.severity}`}><Badge tone={issue.severity === 'error' ? 'danger' : 'warning'}>{issue.severity}</Badge><span>{issue.message}</span></div>)}</div> : <Alert tone="success">No current preflight issues.</Alert>}</Stack></FormSection></>;
}
/** Renders the no-prompt lead-form editor used by Website Studio. */
export function FormEditorModal({ open, draft, setDraft, onClose, onSave }: {
    open: boolean;
    draft: Partial<WebsiteForm>;
    setDraft: (value: Partial<WebsiteForm>) => void;
    onClose: () => void;
    onSave: () => Promise<void>;
}) {
    const fields = draft.fields || [];
    /** Updates one form field definition by array index. */
    const updateField = (index: number, patch: Record<string, any>) => setDraft({ ...draft, fields: fields.map((field, i) => i === index ? { ...field, ...patch } : field) });
    return <FormDialog open={open} onClose={onClose} title={draft.id ? 'Edit website form' : 'Create website form'} description="Define public fields, consent and delivery settings without exposing submissions." size="lg" formId="website-form-editor" onSubmit={event => { event.preventDefault(); void onSave(); }} submitLabel="Save form" disabled={!draft.name?.trim()}><Stack><FormGrid><Field label="Name"><Input value={draft.name || ''} onChange={e => setDraft({ ...draft, name: e.target.value })}/></Field><Field label="Slug"><Input value={draft.slug || ''} onChange={e => setDraft({ ...draft, slug: e.target.value })}/></Field><Field label="Status"><Select value={draft.status || 'active'} onChange={e => setDraft({ ...draft, status: e.target.value })}><Option value="active">Active</Option><Option value="inactive">Inactive</Option><Option value="archived">Archived</Option></Select></Field><Field label="Notification emails"><Input value={(draft.notification_emails || []).join(', ')} onChange={e => setDraft({ ...draft, notification_emails: e.target.value.split(',').map(v => v.trim()).filter(Boolean) })}/></Field></FormGrid><Switch checked={Boolean(draft.settings?.require_consent)} onChange={checked => setDraft({ ...draft, settings: { ...draft.settings, require_consent: checked } })} label="Require consent"/><Field label="Success message"><Textarea value={draft.success_message || ''} onChange={e => setDraft({ ...draft, success_message: e.target.value })}/></Field><div className="website-form-fields"><Inline><strong>Fields</strong><Button size="sm" variant="ghost" onClick={() => setDraft({ ...draft, fields: [...fields, { id: `field_${fields.length + 1}`, type: 'text', label: 'New field', required: false }] })}><Plus size={12}/> Add field</Button></Inline>{fields.map((field, index) => <div key={`${field.id}-${index}`} className="website-form-field-row"><Input value={field.label} onChange={e => updateField(index, { label: e.target.value, id: e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '') || field.id })}/><Select value={field.type} onChange={e => updateField(index, { type: e.target.value })}>{['text', 'email', 'phone', 'textarea', 'select', 'checkbox', 'number', 'date'].map(type => <Option key={type} value={type}>{type}</Option>)}</Select><Switch checked={Boolean(field.required)} onChange={checked => updateField(index, { required: checked })} label="Required"/><IconButton size="sm" variant="ghost" aria-label="Remove form field" onClick={() => setDraft({ ...draft, fields: fields.filter((_, i) => i !== index) })}><Trash2 size={12}/></IconButton></div>)}</div></Stack></FormDialog>;
}
