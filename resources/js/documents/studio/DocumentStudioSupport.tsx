import { useState, type ReactNode } from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Barcode, Box as BoxIcon, Braces, ChevronDown, Columns3, Copy, FileText, GalleryHorizontalEnd, GripVertical, Image as ImageIcon, Link2, ListTree, MessageSquareText, PanelLeftClose, PanelRightClose, PenLine, Plus, QrCode, RefreshCw, Settings2, Stamp, Table2, Trash2, Type, Variable, X } from 'lucide-react';
import RichTextEditor from '../../components/RichTextEditor';
import { apiDownload } from '../../api/client';
import { Alert, Box, Button, Dropdown, EmptyState, Field, FormGrid, FormSection, IconButton, Input, Option, Pressable, Select, Stack, Switch, Textarea } from '../../design-system';
import type { DocumentBlock, DocumentComment, DocumentComponent, DocumentTemplate, GeneratedDocument } from '../types';

export type StudioTab = 'designer' | 'generated' | 'components' | 'variables';
export type InspectorTab = 'block' | 'page' | 'data' | 'brand' | 'comments' | 'preflight';
export type DesignerRailTab = 'pages' | 'layers' | 'blocks' | 'assets';
export type WorkflowModal = {
    kind: 'share' | 'signature' | 'review' | 'approve' | 'reject' | 'clone' | 'variant' | 'compare' | 'component' | 'comment' | 'batch' | 'brand' | 'master';
    document?: GeneratedDocument | null;
} | null;
export type BlockIconMap = Record<string, ReactNode>;
export const BLOCK_ICONS: BlockIconMap = { logo: <ImageIcon size={14}/>, heading: <Type size={14}/>, text: <Type size={14}/>, rich_text: <Type size={14}/>, field: <Variable size={14}/>, image: <ImageIcon size={14}/>, key_value: <ListTree size={14}/>, table: <Table2 size={14}/>, totals: <Table2 size={14}/>, formula: <Braces size={14}/>, conditional: <Braces size={14}/>, repeat: <RefreshCw size={14}/>, columns: <Columns3 size={14}/>, callout: <MessageSquareText size={14}/>, stamp: <Stamp size={14}/>, qr: <QrCode size={14}/>, barcode: <Barcode size={14}/>, reusable: <BoxIcon size={14}/>, divider: <PanelLeftClose size={14}/>, spacer: <GalleryHorizontalEnd size={14}/>, signature: <PenLine size={14}/>, page_number: <FileText size={14}/>, page_break: <FileText size={14}/>, footer: <PanelRightClose size={14}/> };
/** Return a collision-resistant block identifier suitable for nested schema validation. */
export function blockId() { return `b_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`; }
/** Return a collision-resistant logical page identifier for the V6 page model. */
export function pageId() { return `p_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`; }
/** Re-key one nested block tree when duplicating pages or reusable content. */
export function rekeyBlock(block: DocumentBlock): DocumentBlock {
    const next = { ...structuredClone(block), id: blockId() };
    if (next.children)
        next.children = next.children.map(rekeyBlock);
    if (next.type === 'columns' && Array.isArray(next.columns))
        next.columns = next.columns.map(column => ({ ...column, children: Array.isArray(column.children) ? column.children.map(rekeyBlock) : column.children }));
    return next;
}
/** Upgrade legacy flat/page-break schemas into explicit V6 page containers without dropping authored content. */
export function normalizeV6Schema(schema: DocumentBlock[] | null | undefined): DocumentBlock[] {
    const blocks = structuredClone(schema ?? []);
    if (blocks.some(block => block.type === 'page'))
        return blocks;
    const pages: DocumentBlock[] = [];
    let children: DocumentBlock[] = []; /** Flush the current legacy block group into one explicit V6 logical page. */ /** Flush the current legacy block group into one explicit V6 logical page. */
    const flush = () => { pages.push({ id: `page_${pages.length + 1}`, type: 'page', label: `Page ${pages.length + 1}`, children }); children = []; };
    for (const block of blocks) {
        if (block.type === 'page_break') {
            flush();
            continue;
        }
        children.push(block);
    }
    if (children.length || !pages.length)
        flush();
    return pages;
}
/** Return a human-readable label for technical document block or workflow values. */
export function humanize(value: string) { return value.replaceAll('_', ' ').replace(/\b\w/g, (letter: string) => letter.toUpperCase()); }
/** Return a conservative default V4 block schema for the selected toolbox block type. */
export function makeBlock(type: string): DocumentBlock {
    const id = blockId();
    if (type === 'logo')
        return { id, type, label: '{{workspace.company_name}}', align: 'left', width: 34, margin_y: 8 };
    if (type === 'heading')
        return { id, type, text: 'New heading', level: 2, align: 'left', margin_y: 8 };
    if (type === 'text')
        return { id, type, text: 'Write text or insert {{workspace.company_name}} variables.', align: 'left', margin_y: 8 };
    if (type === 'rich_text')
        return { id, type, html: '<p>Write rich content with <strong>formatting</strong> and variables.</p>', align: 'left', margin_y: 8 };
    if (type === 'field')
        return { id, type, value: '{{workspace.company_name}}', prefix: '', suffix: '', align: 'left', margin_y: 8 };
    if (type === 'image')
        return { id, type, media_asset_id: 0, alt: 'Document image', caption: '', width: 100, align: 'center', margin_y: 8 };
    if (type === 'key_value' || type === 'totals')
        return { id, type, items: [{ label: 'Label', value: '{{workspace.name}}' }], margin_y: 8 };
    if (type === 'table')
        return { id, type, source: 'invoice.lines', show_header: true, max_rows: 250, columns: [{ label: 'Description', key: 'description', align: 'left' }, { label: 'Amount', key: 'amount', align: 'right' }], margin_y: 8 };
    if (type === 'formula')
        return { id, type, label: 'Calculated value', expression: 'invoice.subtotal + invoice.tax_total', decimals: 2, margin_y: 8 };
    if (type === 'conditional')
        return { id, type, condition: { path: 'invoice.discount_total', operator: 'gt', value: 0 }, children: [makeBlock('text')], margin_y: 8 };
    if (type === 'repeat')
        return { id, type, source: 'invoice.lines', alias: 'item', max_items: 100, children: [{ ...makeBlock('field'), value: '{{item.description}}' }], margin_y: 8 };
    if (type === 'columns')
        return { id, type, columns: [{ width: 50, children: [makeBlock('text')] }, { width: 50, children: [makeBlock('text')] }], margin_y: 8 };
    if (type === 'callout')
        return { id, type, tone: 'info', html: '<p>Important information.</p>', margin_y: 8 };
    if (type === 'stamp')
        return { id, type, text: 'APPROVED', color: '#166534', margin_y: 8 };
    if (type === 'qr')
        return { id, type, value: '{{workspace.website}}', margin_y: 8 };
    if (type === 'barcode')
        return { id, type, value: '{{document.id}}', margin_y: 8 };
    if (type === 'reusable')
        return { id, type, component_id: 0, margin_y: 8 };
    if (type === 'divider' || type === 'page_break')
        return { id, type, margin_y: 8 };
    if (type === 'spacer')
        return { id, type, height: 20, margin_y: 0 };
    if (type === 'signature')
        return { id, type, label: 'Authorized Signature', role: 'Authorized Signer', margin_y: 8 };
    if (type === 'page_number')
        return { id, type, label: 'Page', margin_y: 8 };
    if (type === 'footer')
        return { id, type, text: '{{workspace.support_email}}', margin_y: 8 };
    return { id, type, text: '', margin_y: 8 };
}
/** Return normalized V4 page settings without mutating persisted template state. */
export function normalizeSettings(settings: Record<string, any> | null | undefined) {
    const input = settings ?? {};
    return { ...input, studio_version: 6, page: { margin_top: 18, margin_right: 18, margin_bottom: 20, margin_left: 18, background: '#FFFFFF', ...(input.page ?? {}) }, header: { enabled: false, text: '', divider: true, ...(input.header ?? {}) }, footer: { enabled: false, text: '', divider: true, ...(input.footer ?? {}) }, watermark: { enabled: false, text: 'DRAFT', opacity: .08, ...(input.watermark ?? {}) } };
}
/** Return the selected top-level document block by its immutable block ID. */
export function findBlock(blocks: DocumentBlock[], id: string | null) { return id ? blocks.find(block => block.id === id) ?? null : null; }
/** Replace one top-level block immutably while preserving original block order. */
export function replaceBlock(blocks: DocumentBlock[], next: DocumentBlock) { return blocks.map(block => block.id === next.id ? next : block); }
/** Format file bytes into a compact human-readable size label. */
export function fileSize(bytes: number) {
    if (bytes < 1024)
        return `${bytes} B`;
    if (bytes < 1024 * 1024)
        return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
/** Return the immutable generated-document workflow policy captured at generation time. */
export function generatedPolicy(document: GeneratedDocument) { const policy = (document.render_metadata?.workflow_policy ?? {}) as Record<string, unknown>; return { review_required: Boolean(policy.review_required), approval_required: Boolean(policy.approval_required), signature_required: Boolean(policy.signature_required), signer_role: String(policy.signer_role ?? '') }; }
/** Return actionable Document Studio preflight issues without executing template expressions. */
export function documentPreflight(template: DocumentTemplate | null): string[] {
    if (!template)
        return [];
    const issues: string[] = [];
    const seen = new Set<string>();
    /** Inspect one block tree recursively for missing required designer configuration. */
    const inspect = (blocks: DocumentBlock[], prefix = 'Block') => blocks.forEach((block, index) => {
        const label = `${prefix} ${index + 1} (${humanize(block.type)})`;
        if (!block.id)
            issues.push(`${label}: missing stable ID.`);
        else if (seen.has(block.id))
            issues.push(`${label}: duplicate block ID.`);
        else
            seen.add(block.id);
        if ((block.type === 'image' || block.type === 'logo') && !block.media_asset_id)
            issues.push(`${label}: choose media or upload a file.`);
        if ((block.type === 'table' || block.type === 'repeat') && !String(block.source ?? '').trim())
            issues.push(`${label}: data source is required.`);
        if (block.type === 'formula' && !String(block.expression ?? '').trim())
            issues.push(`${label}: formula expression is empty.`);
        if (block.type === 'reusable' && !block.component_id)
            issues.push(`${label}: reusable component is not selected.`);
        if (block.children?.length)
            inspect(block.children, `${label} child`);
        if (block.type === 'columns')
            for (const column of block.columns ?? [])
                if ('children' in column && Array.isArray(column.children))
                    inspect(column.children, `${label} column`);
    });
    const pages = normalizeV6Schema(template.content_schema ?? []);
    if (!pages.length)
        issues.push('Add at least one page before generating a document.');
    pages.forEach((page, index) => {
        const children = Array.isArray(page.children) ? page.children : [];
        if (!children.length)
            issues.push(`${String(page.label ?? `Page ${index + 1}`)}: add at least one block.`);
        inspect(children, String(page.label ?? `Page ${index + 1}`));
    });
    return issues;
}
/** Trigger an authenticated generated-document download while preserving the server filename. */
export async function downloadGenerated(id: number, workspaceId: number) { const result = await apiDownload(`/api/v1/documents/generated/${id}/download`, workspaceId); const url = URL.createObjectURL(result.blob); const anchor = document.createElement('a'); anchor.href = url; anchor.download = result.filename; anchor.click(); window.setTimeout(() => URL.revokeObjectURL(url), 1000); }
/** Copy a secure one-time URL to the clipboard when browser permissions allow it. */
export async function copyUrl(url: string) { await navigator.clipboard.writeText(new URL(url, window.location.origin).toString()); }
/** Render one sortable top-level designer block with selection and accessible actions. */
export function SortableBlock({ block, selected, editable, onSelect, onDelete, onDuplicate }: {
    block: DocumentBlock;
    selected: boolean;
    editable: boolean;
    onSelect: () => void;
    onDelete: () => void;
    onDuplicate: () => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition } = useSortable({ id: block.id, disabled: !editable });
    const summary = String(block.text ?? block.label ?? block.value ?? block.source ?? block.expression ?? '');
    return <Box ref={setNodeRef} className={`document-v4-block${selected ? ' is-selected' : ''}`} transform={CSS.Transform.toString(transform)} transition={transition}>
    <Pressable type="button" className="document-v4-block__handle" {...(editable ? attributes : {})} {...(editable ? listeners : {})} disabled={!editable} aria-label="Reorder block"><GripVertical size={14}/></Pressable>
    <Pressable type="button" className="document-v4-block__main" onClick={onSelect}><span>{BLOCK_ICONS[block.type] ?? <BoxIcon size={14}/>}</span><div><strong>{humanize(block.type)}</strong><small>{summary || 'Configure this block in the inspector'}</small></div></Pressable>
    {editable && <div className="document-v4-block__actions"><IconButton variant="ghost" size="sm" aria-label="Duplicate block" onClick={onDuplicate}><Copy size={12}/></IconButton><IconButton variant="ghost" size="sm" aria-label="Delete block" onClick={onDelete}><Trash2 size={12}/></IconButton></div>}
  </Box>;
}
/** Render structured key/value editor rows without exposing raw JSON configuration. */
export function ItemRows({ items, onChange, disabled }: {
    items: Array<{
        label: string;
        value: string;
    }>;
    onChange: (items: Array<{
        label: string;
        value: string;
    }>) => void;
    disabled: boolean;
}) {
    /** Update one key/value row field immutably. */
    const update = (index: number, key: 'label' | 'value', value: string) => onChange(items.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row));
    return <div className="document-v4-repeat-editor">{items.map((row, index) => <div key={index} className="document-v4-repeat-editor__row"><Input disabled={disabled} value={row.label} onChange={event => update(index, 'label', event.target.value)} placeholder="Label"/><Input disabled={disabled} value={row.value} onChange={event => update(index, 'value', event.target.value)} placeholder="{{variable.path}}"/><IconButton disabled={disabled} variant="ghost" size="sm" aria-label="Remove row" onClick={() => onChange(items.filter((_, rowIndex) => rowIndex !== index))}><X size={12}/></IconButton></div>)}<Button type="button" size="sm" variant="ghost" disabled={disabled} onClick={() => onChange([...items, { label: 'Label', value: '{{workspace.name}}' }])}><Plus size={12}/> Add row</Button></div>;
}
/** Render structured repeating-table columns with alignment controls and no raw JSON textarea. */
type TableColumnDefinition = Pick<NonNullable<DocumentBlock['columns']>[number], 'label' | 'key' | 'align' | 'width' | 'format'>;
export function TableColumns({ columns, onChange, disabled }: {
    columns: TableColumnDefinition[];
    onChange: (columns: TableColumnDefinition[]) => void;
    disabled: boolean;
}) {
    /** Update one repeating-table column definition immutably. */
    const update = (index: number, patch: Record<string, unknown>) => onChange(columns.map((column, columnIndex) => columnIndex === index ? { ...column, ...patch } : column));
    return <div className="document-v4-repeat-editor">{columns.map((column, index) => <div key={index} className="document-v4-repeat-editor__row document-v4-repeat-editor__row--table document-v6-table-column"><Input disabled={disabled} value={column.label ?? ''} onChange={event => update(index, { label: event.target.value })} placeholder="Column label"/><Input disabled={disabled} value={column.key ?? ''} onChange={event => update(index, { key: event.target.value })} placeholder="data_key"/><Select disabled={disabled} value={column.align ?? 'left'} onChange={event => update(index, { align: event.target.value })}><Option value="left">Left</Option><Option value="center">Center</Option><Option value="right">Right</Option></Select><Select disabled={disabled} value={column.format ?? 'text'} onChange={event => update(index, { format: event.target.value })}><Option value="text">Text</Option><Option value="number">Number</Option><Option value="currency">Currency</Option><Option value="date">Date</Option><Option value="percent">Percent</Option></Select><Input disabled={disabled} type="number" min="5" max="100" value={Number(column.width ?? 0) || ''} onChange={event => update(index, { width: event.target.value ? Number(event.target.value) : undefined })} placeholder="Width %"/><IconButton disabled={disabled} variant="ghost" size="sm" aria-label="Remove column" onClick={() => onChange(columns.filter((_, columnIndex) => columnIndex !== index))}><X size={12}/></IconButton></div>)}<Button type="button" size="sm" variant="ghost" disabled={disabled || columns.length >= 20} onClick={() => onChange([...columns, { label: 'Column', key: 'value', align: 'left', format: 'text' }])}><Plus size={12}/> Add column</Button></div>;
}
/** Render a compact child-block editor for conditional, repeat, and layout column blocks. */
export function ChildBlocks({ blocks, onChange, disabled }: {
    blocks: DocumentBlock[];
    onChange: (blocks: DocumentBlock[]) => void;
    disabled: boolean;
}) {
    return <div className="document-v4-child-blocks">{blocks.map(block => <div key={block.id}><span>{BLOCK_ICONS[block.type] ?? <BoxIcon size={12}/>} {humanize(block.type)}</span><Input disabled={disabled} value={String(block.text ?? block.value ?? '')} onChange={event => onChange(blocks.map(row => row.id === block.id ? { ...row, ...(row.type === 'field' ? { value: event.target.value } : { text: event.target.value }) } : row))}/><IconButton disabled={disabled} size="sm" variant="ghost" aria-label="Remove nested block" onClick={() => onChange(blocks.filter(row => row.id !== block.id))}><Trash2 size={11}/></IconButton></div>)}<Dropdown trigger={<Button type="button" size="sm" variant="ghost" disabled={disabled}><Plus size={12}/> Add nested block <ChevronDown size={11}/></Button>} items={['heading', 'text', 'field', 'rich_text', 'divider', 'signature'].map(type => ({ label: humanize(type), icon: BLOCK_ICONS[type], onClick: () => onChange([...blocks, makeBlock(type)]) }))}/></div>;
}
/** Render the selected V4 block's safe structured configuration controls. */
export function BlockInspector({ block, onChange, editable, workspaceId, components, onPickMedia, onDetachReusable }: {
    block: DocumentBlock | null;
    onChange: (block: DocumentBlock) => void;
    editable: boolean;
    workspaceId: number;
    components: DocumentComponent[];
    onPickMedia: (block: DocumentBlock) => void;
    onDetachReusable: () => void;
}) {
    if (!block)
        return <EmptyState icon={<Settings2 size={23}/>} title="Select a block" text="Choose a block from the outline to configure its content, data source and appearance."/>;
    /** Update selected block fields without mutating the source schema. */
    const patch = (next: Partial<DocumentBlock>) => onChange({ ...block, ...next });
    const disabled = !editable;
    return <Stack gap={12}>
    <div className="document-v4-inspector-heading"><span>{BLOCK_ICONS[block.type] ?? <BoxIcon size={15}/>}</span><div><strong>{humanize(block.type)}</strong><small>{block.id}</small></div></div>
    {['heading', 'text', 'stamp', 'footer'].includes(block.type) && <Field label="Text"><Textarea disabled={disabled} rows={block.type === 'text' ? 5 : 3} value={String(block.text ?? '')} onChange={event => patch({ text: event.target.value })}/></Field>}
    {block.type === 'rich_text' && <Field label="Rich content"><RichTextEditor disabled={disabled} value={String(block.html ?? '')} onChange={html => patch({ html })} placeholder="Write formatted document content…"/></Field>}
    {block.type === 'callout' && <><Field label="Tone"><Select disabled={disabled} value={block.tone ?? 'info'} onChange={event => patch({ tone: event.target.value as DocumentBlock['tone'] })}><Option value="neutral">Neutral</Option><Option value="info">Info</Option><Option value="success">Success</Option><Option value="warning">Warning</Option><Option value="danger">Danger</Option></Select></Field><Field label="Content"><RichTextEditor disabled={disabled} value={String(block.html ?? '')} onChange={html => patch({ html })}/></Field></>}
    {block.type === 'field' && <FormGrid columns={2}><Field label="Value"><Input disabled={disabled} value={String(block.value ?? '')} onChange={event => patch({ value: event.target.value })}/></Field><Field label="Alignment"><Select disabled={disabled} value={block.align ?? 'left'} onChange={event => patch({ align: event.target.value as DocumentBlock['align'] })}><Option value="left">Left</Option><Option value="center">Center</Option><Option value="right">Right</Option></Select></Field><Field label="Prefix"><Input disabled={disabled} value={String(block.prefix ?? '')} onChange={event => patch({ prefix: event.target.value })}/></Field><Field label="Suffix"><Input disabled={disabled} value={String(block.suffix ?? '')} onChange={event => patch({ suffix: event.target.value })}/></Field></FormGrid>}
    {block.type === 'heading' && <FormGrid columns={2}><Field label="Heading level"><Select disabled={disabled} value={String(block.level ?? 2)} onChange={event => patch({ level: Number(event.target.value) })}><Option value="1">H1</Option><Option value="2">H2</Option><Option value="3">H3</Option></Select></Field><Field label="Alignment"><Select disabled={disabled} value={block.align ?? 'left'} onChange={event => patch({ align: event.target.value as DocumentBlock['align'] })}><Option value="left">Left</Option><Option value="center">Center</Option><Option value="right">Right</Option></Select></Field></FormGrid>}
    {block.type === 'logo' && <><Field label="Fallback company label"><Input disabled={disabled} value={String(block.label ?? '')} onChange={event => patch({ label: event.target.value })}/></Field><FormGrid columns={2}><Field label="Width %"><Input disabled={disabled} type="number" min="10" max="100" value={Number(block.width ?? 34)} onChange={event => patch({ width: Number(event.target.value) })}/></Field><Field label="Alignment"><Select disabled={disabled} value={block.align ?? 'left'} onChange={event => patch({ align: event.target.value as DocumentBlock['align'] })}><Option value="left">Left</Option><Option value="center">Center</Option><Option value="right">Right</Option></Select></Field></FormGrid><Button type="button" variant="outline" disabled={disabled} onClick={() => onPickMedia(block)}><ImageIcon size={13}/> {block.media_asset_id ? 'Replace logo media' : 'Choose logo from Media Library'}</Button></>}
    {block.type === 'image' && <><Button type="button" variant="outline" disabled={disabled} onClick={() => onPickMedia(block)}><ImageIcon size={13}/> {block.media_asset_id ? 'Replace image' : 'Choose or upload image'}</Button><FormGrid columns={2}><Field label="Media asset ID"><Input disabled value={block.media_asset_id ? String(block.media_asset_id) : ''} placeholder="Choose media"/></Field><Field label="Width %"><Input disabled={disabled} type="number" min="10" max="100" value={Number(block.width ?? 100)} onChange={event => patch({ width: Number(event.target.value) })}/></Field></FormGrid><Field label="Alt text"><Input disabled={disabled} value={String(block.alt ?? '')} onChange={event => patch({ alt: event.target.value })}/></Field><Field label="Caption"><Input disabled={disabled} value={String(block.caption ?? '')} onChange={event => patch({ caption: event.target.value })}/></Field></>}
    {(block.type === 'key_value' || block.type === 'totals') && <Field label={block.type === 'totals' ? 'Totals rows' : 'Key/value rows'}><ItemRows disabled={disabled} items={block.items ?? []} onChange={items => patch({ items })}/></Field>}
    {block.type === 'table' && <><FormGrid columns={2}><Field label="Data source"><Input disabled={disabled} value={String(block.source ?? '')} onChange={event => patch({ source: event.target.value })}/></Field><Field label="Max rows"><Input disabled={disabled} type="number" min="1" max="1000" value={Number(block.max_rows ?? 250)} onChange={event => patch({ max_rows: Number(event.target.value) })}/></Field></FormGrid><Switch checked={block.show_header !== false} disabled={disabled} onChange={checked => patch({ show_header: checked })} label="Show table header"/><Field label="Columns"><TableColumns disabled={disabled} columns={(block.columns ?? []) as TableColumnDefinition[]} onChange={columns => patch({ columns })}/></Field></>}
    {block.type === 'formula' && <><Field label="Expression" hint="Use numeric variables with + − × ÷ and parentheses. No executable code is evaluated."><Input disabled={disabled} value={String(block.expression ?? '0')} onChange={event => patch({ expression: event.target.value })}/></Field><FormGrid columns={2}><Field label="Label"><Input disabled={disabled} value={String(block.label ?? '')} onChange={event => patch({ label: event.target.value })}/></Field><Field label="Decimals"><Input disabled={disabled} type="number" min="0" max="6" value={Number(block.decimals ?? 2)} onChange={event => patch({ decimals: Number(event.target.value) })}/></Field></FormGrid></>}
    {block.type === 'conditional' && <><FormGrid columns={2}><Field label="Variable path"><Input disabled={disabled} value={String(block.condition?.path ?? '')} onChange={event => patch({ condition: { ...block.condition, path: event.target.value } })}/></Field><Field label="Operator"><Select disabled={disabled} value={String(block.condition?.operator ?? 'truthy')} onChange={event => patch({ condition: { ...block.condition, operator: event.target.value } })}>{['truthy', 'falsy', 'eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'empty', 'not_empty'].map(option => <Option key={option} value={option}>{humanize(option)}</Option>)}</Select></Field></FormGrid><Field label="Comparison value"><Input disabled={disabled} value={String(block.condition?.value ?? '')} onChange={event => patch({ condition: { ...block.condition, value: event.target.value } })}/></Field><Field label="Rendered when condition matches"><ChildBlocks disabled={disabled} blocks={block.children ?? []} onChange={children => patch({ children })}/></Field></>}
    {block.type === 'repeat' && <><FormGrid columns={3}><Field label="Array source"><Input disabled={disabled} value={String(block.source ?? '')} onChange={event => patch({ source: event.target.value })}/></Field><Field label="Alias"><Input disabled={disabled} value={String(block.alias ?? 'item')} onChange={event => patch({ alias: event.target.value })}/></Field><Field label="Max items"><Input disabled={disabled} type="number" min="1" max="250" value={Number(block.max_items ?? 100)} onChange={event => patch({ max_items: Number(event.target.value) })}/></Field></FormGrid><Field label="Repeated child blocks"><ChildBlocks disabled={disabled} blocks={block.children ?? []} onChange={children => patch({ children })}/></Field></>}
    {block.type === 'columns' && <div className="document-v4-layout-columns">{(block.columns ?? []).map((column, index) => <FormSection key={index} title={`Column ${index + 1}`} actions={<IconButton disabled={disabled || (block.columns?.length ?? 0) <= 2} size="sm" variant="ghost" aria-label="Remove layout column" onClick={() => patch({ columns: block.columns?.filter((_, columnIndex) => columnIndex !== index) })}><Trash2 size={12}/></IconButton>}><Field label="Width %"><Input disabled={disabled} type="number" min="10" max="100" value={Number(column.width ?? Math.round(100 / (block.columns?.length || 2)))} onChange={event => patch({ columns: block.columns?.map((row, columnIndex) => columnIndex === index ? { ...row, width: Number(event.target.value) } : row) })}/></Field><ChildBlocks disabled={disabled} blocks={column.children ?? []} onChange={children => patch({ columns: block.columns?.map((row, columnIndex) => columnIndex === index ? { ...row, children } : row) })}/></FormSection>)}<Button type="button" size="sm" variant="ghost" disabled={disabled || (block.columns?.length ?? 0) >= 4} onClick={() => patch({ columns: [...(block.columns ?? []), { width: 25, children: [makeBlock('text')] }] })}><Plus size={12}/> Add column</Button></div>}
    {block.type === 'stamp' && <Field label="Stamp color"><Input disabled={disabled} type="color" value={String(block.color ?? '#166534')} onChange={event => patch({ color: event.target.value })}/></Field>}
    {(block.type === 'qr' || block.type === 'barcode') && <Field label="Encoded value"><Input disabled={disabled} value={String(block.value ?? '')} onChange={event => patch({ value: event.target.value })}/></Field>}
    {block.type === 'signature' && <FormGrid columns={2}><Field label="Display label"><Input disabled={disabled} value={String(block.label ?? '')} onChange={event => patch({ label: event.target.value })}/></Field><Field label="Signature role"><Input disabled={disabled} value={String(block.role ?? '')} onChange={event => patch({ role: event.target.value })}/></Field></FormGrid>}
    {block.type === 'reusable' && <Stack gap={8}><Field label="Reusable component"><Select disabled={disabled} value={String(block.component_id ?? '')} onChange={event => patch({ component_id: Number(event.target.value) })}><Option value="">Choose component</Option>{components.map(component => <Option key={component.id} value={component.id}>{component.name}{component.version ? ` · v${component.version}` : ''}</Option>)}</Select></Field><Alert tone="info">Linked reusable blocks render the latest shared component source. Detach only when this page needs an independent local copy.</Alert><Button type="button" size="sm" variant="outline" disabled={disabled || !block.component_id} onClick={onDetachReusable}><Link2 size={12}/> Detach to local copy</Button></Stack>}
    {block.type === 'spacer' && <Field label="Height"><Input disabled={disabled} type="number" min="4" max="120" value={Number(block.height ?? 16)} onChange={event => patch({ height: Number(event.target.value) })}/></Field>}
    {!['divider', 'page_break', 'spacer', 'key_value', 'totals', 'table', 'formula', 'conditional', 'repeat', 'columns', 'stamp', 'qr', 'barcode', 'signature', 'reusable', 'image', 'logo', 'field', 'heading', 'rich_text', 'callout', 'text', 'footer'].includes(block.type) && <Alert tone="info">This block has no additional properties.</Alert>}
    {!['page_break', 'spacer'].includes(block.type) && <Field label="Vertical spacing"><Input disabled={disabled} type="range" min="0" max="48" value={Number(block.margin_y ?? 8)} onChange={event => patch({ margin_y: Number(event.target.value) })}/></Field>}
    <small className="document-v4-muted">Workspace {workspaceId} · Block settings are validated again on the server.</small>
  </Stack>;
}
/** Render page, header, footer, watermark, paper and typography settings for V4 templates. */
export function PageInspector({ editor, onChange, editable }: {
    editor: DocumentTemplate;
    onChange: (template: DocumentTemplate) => void;
    editable: boolean;
}) {
    const settings = normalizeSettings(editor.settings);
    /** Patch normalized V4 document settings at one nested section. */
    const patchSettings = (section: 'page' | 'header' | 'footer' | 'watermark', patch: Record<string, unknown>) => onChange({ ...editor, settings: { ...settings, [section]: { ...settings[section], ...patch } } });
    return <Stack gap={12}>
    <FormSection title="Paper & typography" description="These settings drive both preview and PDF output."><FormGrid columns={2}><Field label="Paper"><Select disabled={!editable} value={editor.paper_size} onChange={event => onChange({ ...editor, paper_size: event.target.value as DocumentTemplate['paper_size'] })}><Option value="A4">A4</Option><Option value="Letter">Letter</Option></Select></Field><Field label="Orientation"><Select disabled={!editable} value={editor.orientation} onChange={event => onChange({ ...editor, orientation: event.target.value as DocumentTemplate['orientation'] })}><Option value="portrait">Portrait</Option><Option value="landscape">Landscape</Option></Select></Field><Field label="Font"><Select disabled={!editable} value={editor.font_family ?? 'Arial'} onChange={event => onChange({ ...editor, font_family: event.target.value })}>{['Arial', 'Helvetica', 'Georgia', 'Times New Roman', 'Courier New', 'Noto Sans', 'Noto Sans Arabic'].map(font => <Option key={font}>{font}</Option>)}</Select></Field><Field label="Page background"><Input disabled={!editable} type="color" value={settings.page.background} onChange={event => patchSettings('page', { background: event.target.value })}/></Field></FormGrid><FormGrid columns={2}><Field label="Primary color"><Input disabled={!editable} type="color" value={editor.primary_color} onChange={event => onChange({ ...editor, primary_color: event.target.value })}/></Field><Field label="Secondary color"><Input disabled={!editable} type="color" value={editor.secondary_color} onChange={event => onChange({ ...editor, secondary_color: event.target.value })}/></Field></FormGrid></FormSection>
    <FormSection title="Page margins" description="Millimeters; validated between 5 and 45."><div className="document-v4-margin-grid">{(['margin_top', 'margin_right', 'margin_bottom', 'margin_left'] as const).map(key => <Field key={key} label={humanize(key.replace('margin_', ''))}><Input disabled={!editable} type="number" min="5" max="45" value={Number(settings.page[key])} onChange={event => patchSettings('page', { [key]: Number(event.target.value) })}/></Field>)}</div></FormSection>
    <FormSection title="Repeating header"><Switch checked={Boolean(settings.header.enabled)} disabled={!editable} onChange={enabled => patchSettings('header', { enabled })} label="Enable header"/><Field label="Header text"><Textarea disabled={!editable || !settings.header.enabled} rows={3} value={String(settings.header.text ?? '')} onChange={event => patchSettings('header', { text: event.target.value })}/></Field><Switch checked={Boolean(settings.header.divider)} disabled={!editable || !settings.header.enabled} onChange={divider => patchSettings('header', { divider })} label="Show divider"/></FormSection>
    <FormSection title="Repeating footer"><Switch checked={Boolean(settings.footer.enabled)} disabled={!editable} onChange={enabled => patchSettings('footer', { enabled })} label="Enable footer"/><Field label="Footer text"><Textarea disabled={!editable || !settings.footer.enabled} rows={3} value={String(settings.footer.text ?? '')} onChange={event => patchSettings('footer', { text: event.target.value })}/></Field><Switch checked={Boolean(settings.footer.divider)} disabled={!editable || !settings.footer.enabled} onChange={divider => patchSettings('footer', { divider })} label="Show divider"/></FormSection>
    <FormSection title="Watermark"><Switch checked={Boolean(settings.watermark.enabled)} disabled={!editable} onChange={enabled => patchSettings('watermark', { enabled })} label="Enable watermark"/><Field label="Watermark text"><Input disabled={!editable || !settings.watermark.enabled} value={String(settings.watermark.text ?? 'DRAFT')} onChange={event => patchSettings('watermark', { text: event.target.value })}/></Field><Field label="Opacity"><Input disabled={!editable || !settings.watermark.enabled} type="range" min="0.02" max="0.25" step="0.01" value={Number(settings.watermark.opacity ?? .08)} onChange={event => patchSettings('watermark', { opacity: Number(event.target.value) })}/></Field></FormSection>
  </Stack>;
}
/** Render collaboration comments for the selected template with block-level resolution controls. */
export function CommentPanel({ comments, selectedBlock, canManage, onAdd, onResolve }: {
    comments: DocumentComment[];
    selectedBlock: string | null;
    canManage: boolean;
    onAdd: (body: string) => Promise<void>;
    onResolve: (comment: DocumentComment, resolved: boolean) => Promise<void>;
}) {
    const [body, setBody] = useState('');
    /** Submit one new block-scoped or template-scoped comment. */
    const submit = async () => {
        if (!body.trim())
            return;
        await onAdd(body.trim());
        setBody('');
    };
    return <Stack gap={10}><div className="document-v4-comment-compose"><Textarea rows={3} value={body} onChange={event => setBody(event.target.value)} placeholder={selectedBlock ? 'Comment on selected block…' : 'Comment on this template…'}/><Button size="sm" disabled={!body.trim()} onClick={() => void submit()}><MessageSquareText size={12}/> Add comment</Button></div>{comments.length ? comments.map(comment => <div key={comment.id} className={`document-v4-comment${comment.resolved_at ? ' is-resolved' : ''}`}><div><strong>{[comment.author?.user?.first_name, comment.author?.user?.last_name].filter(Boolean).join(' ') || 'Workspace member'}</strong><small>{new Date(comment.created_at).toLocaleString()}</small></div><p>{comment.body}</p>{comment.block_id && <code>{comment.block_id}</code>}{canManage && <Button size="sm" variant="ghost" onClick={() => void onResolve(comment, !comment.resolved_at)}>{comment.resolved_at ? 'Reopen' : 'Resolve'}</Button>}</div>) : <EmptyState icon={<MessageSquareText size={22}/>} title="No comments yet" text="Collaborate on this template or the currently selected block."/>}</Stack>;
}
