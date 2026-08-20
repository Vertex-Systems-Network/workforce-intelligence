from pathlib import Path
import re


def replace(path: str, old: str, new: str) -> None:
    p = Path(path)
    source = p.read_text()
    if old not in source:
        raise SystemExit(f"missing marker in {path}: {old[:100]}")
    p.write_text(source.replace(old, new))


# Design-system layout exports and React 19 ref-compatible Box.
replace('resources/js/design-system/layout.tsx', "import type { CSSProperties, HTMLAttributes, LabelHTMLAttributes, ReactNode } from 'react';", "import type { CSSProperties, HTMLAttributes, LabelHTMLAttributes, ReactNode, Ref } from 'react';")
replace('resources/js/design-system/layout.tsx', 'function visualStyle(', 'export function visualStyle(')
replace('resources/js/design-system/layout.tsx', 'function splitVisualProps<T extends Record<string,unknown>>', 'export function splitVisualProps<T extends Record<string,unknown>>')
replace('resources/js/design-system/layout.tsx', "type BoxTag='div'|'section'|'header'|'nav'|'main'|'aside'|'article'|'span'|'p'|'h1'|'h2'|'h3'|'h4'|'strong'|'small'|'code'|'pre'|'i'", "type BoxTag='div'|'section'|'header'|'nav'|'main'|'aside'|'article'|'span'|'p'|'h1'|'h2'|'h3'|'h4'|'strong'|'small'|'code'|'pre'|'i'|'details'|'summary'")
replace('resources/js/design-system/layout.tsx', "export function Box({as='div',children,className='',style,...props}:HTMLAttributes<HTMLElement>&VisualProps&{as?:BoxTag}){\n  const [visual,rest]=splitVisualProps(props)\n  const Component=as as any\n  return <Component className={cx('ui-box',className)} style={{...visualStyle(visual),...style}} {...rest}>{children}</Component>\n}", "export function Box({as='div',children,className='',style,ref,...props}:HTMLAttributes<HTMLElement>&VisualProps&{as?:BoxTag;ref?:Ref<HTMLElement>}){\n  const [visual,rest]=splitVisualProps(props)\n  const Component=as as any\n  return <Component ref={ref} className={cx('ui-box',className)} style={{...visualStyle(visual),...style}} {...rest}>{children}</Component>\n}")

# Shared Form/Field/Input/DataGrid typing fixes exposed by the dependency-backed CI run.
p = Path('resources/js/design-system/index.tsx')
source = p.read_text()
source = source.replace('type ReactNode, type RefObject,', 'type ReactNode, type Ref, type RefObject,')
source = source.replace('...layoutStyle({m,mt,mb,ml,mr,p,pt,pb,pl,pr,minWidth,maxWidth,width})', '...visualStyle({m,mt,mb,ml,mr,p,pt,pb,pl,pr,minWidth,maxWidth,width})')
source = source.replace("cx('ui-field', error && 'is-error', className)", "cx('ui-field', Boolean(error) && 'is-error', className)")
file_input_marker = "function FileInput({ className = '', onChange, disabled, ...props }: InputHTMLAttributes<HTMLInputElement>) {"
if file_input_marker not in source:
    raise SystemExit('FileInput marker missing')
source = source.replace(file_input_marker, "function FileInput({ className = '', onChange, disabled, inputRef, ...props }: InputHTMLAttributes<HTMLInputElement> & { inputRef?: Ref<HTMLInputElement> }) {")
source = source.replace('<input {...props} type="file" disabled={disabled} onChange={change}/>', '<input ref={inputRef} {...props} type="file" disabled={disabled} onChange={change}/>')
pattern = r"/\*\* Handles the input operation for the WorkIntel client\. \*/ export function Input\(\{ className = '', type, style, \.\.\.props \}: InputHTMLAttributes<HTMLInputElement> & VisualProps\) \{.*?\n/\*\* Handles the textarea operation"
replacement = "/** Handles the input operation for the WorkIntel client while exposing the native input ref used by command/search surfaces. */\nexport const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement> & VisualProps>(function Input({ className = '', type, style, ...props }, ref) { const [visual,rest]=splitVisualProps(props); const composed={...visualStyle(visual),...style}; if(type==='date'||type==='time'||type==='datetime-local')return <SmartDateInput type={type as 'date'|'time'|'datetime-local'} className={className} style={composed} {...rest}/>; if(type==='file')return <FileInput inputRef={ref} className={className} style={composed} {...rest}/>; return <input ref={ref} className={cx('ui-input', className)} type={type} style={composed} {...rest} /> })\n/** Handles the textarea operation"
source, count = re.subn(pattern, replacement, source, flags=re.S)
if count != 1:
    raise SystemExit(f'Input replacement count {count}')
source = source.replace('  cell:(row:T)=>ReactNode\n', '  cell?:(row:T)=>ReactNode\n')
source = source.replace('    cell:info=>column.cell(info.row.original),', "    cell:info=>column.cell?column.cell(info.row.original):String(column.value?.(info.row.original)??''),")
p.write_text(source)

replace('resources/js/components/RichTextEditor.tsx', 'size="sm" footer=', 'size="md" footer=')

# Types/helpers moved during DEV-03 decomposition must be imported by their page shells.
replace('resources/js/pages/Automations.tsx', 'type ActionForm, type DeadRow, type HookRow, type Overview, type RunRow, type Template, type WorkflowForm, type WorkflowRow, emptyAction, emptyForm, fmt, normalizeConfig, tone', 'type ActionForm, type Condition, type DeadRow, type HookRow, type Overview, type RunRow, type Template, type WorkflowForm, type WorkflowRow, emptyAction, emptyForm, fmt, maybeJson, normalizeConfig, tone')
replace('resources/js/pages/Chat.tsx', 'CollaborationInbox, Conversation, ConversationContext, ConversationFilter', 'CollaborationInbox, Conversation, ConversationContext, ConversationFilter, InboxThreadItem')
replace('resources/js/pages/Clients.tsx', 'type Client, type ClientForm, type ClientReport, type Invoice,', 'type Client, type ClientForm, type ClientReport, type Invoice, type InvoiceLine,')
replace('resources/js/pages/Payroll.tsx', 'type CompensationForm, type CompensationRow, type PayType, type PayrollItem, type PayrollRun, type RunStatus', 'type Adjustment, type CompensationForm, type CompensationProfile, type CompensationRow, type PayType, type PayrollItem, type PayrollRun, type RunStatus')
replace('resources/js/pages/Reports.tsx', 'type Catalog, type Column, type Dataset, type Preview, type ReportConfig, type ReportExport, type ReportRun, type SavedReport, type Schedule, datasetIcons, dateTime, defaultConfig', 'type Catalog, type Column, type Dataset, type FilterDef, type Preview, type ReportConfig, type ReportExport, type ReportRun, type SavedReport, type Schedule, type Visualization, datasetIcons, dateTime, daysAgo, defaultConfig')
replace('resources/js/pages/Reports.tsx', 'rowKey={(_, index) => index}', 'rowKey={row => result.rows.indexOf(row)}')
replace('resources/js/pages/WebsiteStudio.tsx', 'ReviewInspector, SectionInspector, SortableSection, autosaveMetadata,', 'ReviewInspector, SectionInspector, SortableSection, autosaveMetadata, localeOptions,')
replace('resources/js/pages/FinanceOps.tsx', "import { ErrorState, apiRequest } from '../api/client';import { useAuth } from '../auth/AuthContext';import { Alert,", "import { apiRequest } from '../api/client';import { useAuth } from '../auth/AuthContext';import { ErrorState, Alert,")

# Document Studio: distinguish the Lucide Box icon from the layout Box and restore extracted helpers.
p = Path('resources/js/documents/studio/DocumentStudioSupport.tsx')
source = p.read_text()
source = source.replace('import { Barcode, Box, Braces,', 'import { Barcode, Box as BoxIcon, Braces,')
source = source.replace("import { Alert, Button, Dropdown,", "import { apiDownload } from '../../api/client';\nimport { Alert, Box, Button, Dropdown,")
source = source.replace('reusable: <Box size={14}/>', 'reusable: <BoxIcon size={14}/>')
source = source.replace('BLOCK_ICONS[block.type] ?? <Box size={14}/>', 'BLOCK_ICONS[block.type] ?? <BoxIcon size={14}/>')
source = source.replace('BLOCK_ICONS[block.type] ?? <Box size={12}/>', 'BLOCK_ICONS[block.type] ?? <BoxIcon size={12}/>')
source = source.replace('BLOCK_ICONS[block.type] ?? <Box size={15}/>', 'BLOCK_ICONS[block.type] ?? <BoxIcon size={15}/>')
source = source.replace('async function downloadGenerated(', 'export async function downloadGenerated(')
source = source.replace('async function copyUrl(', 'export async function copyUrl(')
old = "export function TableColumns({ columns, onChange, disabled }: {\n    columns: Array<{\n        label?: string;\n        key?: string;\n        align?: 'left' | 'center' | 'right';\n        width?: number;\n        format?: string;\n    }>;\n    onChange: (columns: Array<{\n        label?: string;\n        key?: string;\n        align?: 'left' | 'center' | 'right';\n        width?: number;\n        format?: string;\n    }>) => void;"
new = "type TableColumnDefinition = Pick<NonNullable<DocumentBlock['columns']>[number], 'label' | 'key' | 'align' | 'width' | 'format'>;\nexport function TableColumns({ columns, onChange, disabled }: {\n    columns: TableColumnDefinition[];\n    onChange: (columns: TableColumnDefinition[]) => void;"
if old not in source:
    raise SystemExit('TableColumns marker missing')
source = source.replace(old, new)
old = "<Field label=\"Columns\"><TableColumns disabled={disabled} columns={(block.columns ?? []) as Array<{\n                label?: string;\n                key?: string;\n                align?: 'left' | 'center' | 'right';\n                width?: number;\n                format?: string;\n            }>} onChange={columns => patch({ columns })}/></Field>"
new = "<Field label=\"Columns\"><TableColumns disabled={disabled} columns={(block.columns ?? []) as TableColumnDefinition[]} onChange={columns => patch({ columns })}/></Field>"
if old not in source:
    raise SystemExit('table inspector marker missing')
p.write_text(source.replace(old, new))

replace('resources/js/pages/Documents.tsx', 'BLOCK_ICONS, BlockInspector, CommentPanel, PageInspector, SortableBlock, blockId, documentPreflight, fileSize,', 'BLOCK_ICONS, BlockInspector, CommentPanel, PageInspector, SortableBlock, blockId, copyUrl, documentPreflight, downloadGenerated, fileSize,')

print('DEV-08 TypeScript CI repair patch applied.')
