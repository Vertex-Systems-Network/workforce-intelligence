import { Children, cloneElement, createContext, forwardRef, isValidElement, useCallback, useContext, useEffect, useId, useMemo, useRef, useState, type AnchorHTMLAttributes, type ButtonHTMLAttributes, type ChangeEvent, type CSSProperties, type FormHTMLAttributes, type HTMLAttributes, type LabelHTMLAttributes, type OptionHTMLAttributes, type ImgHTMLAttributes, type InputHTMLAttributes, type KeyboardEvent as ReactKeyboardEvent, type ReactElement, type ReactNode, type RefObject, type SelectHTMLAttributes, type TableHTMLAttributes, type TextareaHTMLAttributes } from 'react'
import { createPortal } from 'react-dom'
import { ArrowDown, ArrowUp, Bookmark, BookmarkPlus, Check, ChevronDown, ChevronLeft, ChevronRight, CircleAlert, CircleHelp, Columns3, FileUp, Filter, Grid2X2, List, LoaderCircle, RefreshCw, RotateCcw, Search, Trash2, X } from 'lucide-react'
import ReactDatePicker from 'react-datepicker'
import { flexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, useReactTable, type ColumnDef, type ColumnFiltersState, type PaginationState, type RowSelectionState, type SortingState, type VisibilityState } from '@tanstack/react-table'
import { apiRequest } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { focusFirstPortalControl, useFocusTrap } from './accessibility'
import { Box, ChoiceList, ChoiceRow, Grid, Inline, Stack, Text, splitVisualProps, visualStyle, type LayoutLength, type LayoutSpacingProps, type VisualProps } from './layout'
export { Box, ChoiceList, ChoiceRow, Grid, Inline, Stack, Text, type LayoutLength, type LayoutSpacingProps, type VisualProps } from './layout'
import { useLocalization, useOptionalLocalization } from '../i18n/LocalizationContext'
import 'react-datepicker/dist/react-datepicker.css'
import './toolkit.css'

/** Handles the cx operation for the WorkIntel client. */ const cx = (...values: Array<string | false | null | undefined>) => values.filter(Boolean).join(' ')
/** Translate only registered static English UI text while leaving dynamic business data untouched. */
function useLocalizedNode(){const localization=useOptionalLocalization();return(node:ReactNode):ReactNode=>Children.map(node,child=>{if(typeof child!=='string'||!localization)return child;const core=child.trim();if(!core)return child;const translated=localization.text(core);return translated===core?child:child.replace(core,translated)})}

export type ConfirmActionOptions = { title: ReactNode; description?: ReactNode; confirmLabel?: ReactNode; cancelLabel?: ReactNode; danger?: boolean }
type PendingConfirmAction = ConfirmActionOptions & { resolve: (value: boolean) => void }
const ConfirmActionContext = createContext<((options: ConfirmActionOptions) => Promise<boolean>) | null>(null)

/** Provide one promise-based, app-owned confirmation surface so feature flows never depend on browser confirm UI. */
export function ConfirmProvider({children}:{children:ReactNode}){
  const [pending,setPending]=useState<PendingConfirmAction|null>(null)
  const pendingRef=useRef<PendingConfirmAction|null>(null)
  const confirmAction=useCallback((options:ConfirmActionOptions)=>new Promise<boolean>(resolve=>{
    if(pendingRef.current)pendingRef.current.resolve(false)
    const next={...options,resolve};pendingRef.current=next;setPending(next)
  }),[])
  /** Settle the active confirmation once and release any pending promise before hiding the dialog. */
  const settle=useCallback((accepted:boolean)=>{const current=pendingRef.current;if(!current)return;pendingRef.current=null;setPending(null);current.resolve(accepted)},[])
  useEffect(()=>()=>{if(pendingRef.current){pendingRef.current.resolve(false);pendingRef.current=null}},[])
  return <ConfirmActionContext.Provider value={confirmAction}>{children}<ConfirmDialog open={Boolean(pending)} onClose={()=>settle(false)} onConfirm={()=>settle(true)} title={pending?.title??'Confirm action'} description={pending?.description} confirmLabel={pending?.confirmLabel} cancelLabel={pending?.cancelLabel} danger={pending?.danger}/></ConfirmActionContext.Provider>
}

/** Request a WorkIntel confirmation dialog and await the user's explicit decision. */
export function useConfirmAction(){const context=useContext(ConfirmActionContext);if(!context)throw new Error('useConfirmAction must be used inside ConfirmProvider.');return context}

/** Handles the page operation for the WorkIntel client. */ export function Page({ children, narrow = false, className = '', style, ...props }: HTMLAttributes<HTMLDivElement> & VisualProps & { narrow?: boolean }) {
  const [visual,rest]=splitVisualProps(props)
  return <div className={cx('ui-page', narrow && 'ui-page--narrow', className)} style={{...visualStyle(visual),...style}} {...rest}>{children}</div>
}

/** Handles the page header operation for the WorkIntel client. */ export function PageHeader({ title, description, actions, children, className = '' }: { title?: ReactNode; description?: ReactNode; actions?: ReactNode; children?: ReactNode; className?: string }) {
  const localize=useLocalizedNode()
  return <div className={cx('ui-page-header', className)}>
    <div>{children ?? <><h1 className="ui-page-title">{localize(title)}</h1>{description && <p className="ui-page-description">{localize(description)}</p>}</>}</div>
    {actions && <div className="ui-page-actions">{actions}</div>}
  </div>
}

/** Render a labeled settings row with consistent title, supporting copy and right-aligned control. */
export function SettingRow({title,description,control,className=''}:{title:ReactNode;description?:ReactNode;control:ReactNode;className?:string}){
  const localize=useLocalizedNode();return <div className={cx('ui-setting-row',className)}><div className="ui-setting-row__copy"><strong>{localize(title)}</strong>{description&&<span>{localize(description)}</span>}</div><div className="ui-setting-row__control">{control}</div></div>
}

/** Handles the divider operation for the WorkIntel client. */ export function Divider() { return <span className="ui-divider" aria-hidden="true" /> }

/** Render the shared semantic form root; optional gap/columns enable design-system-owned form composition. */
export function Form({children,className='',style,gap,columns,m,mt,mb,ml,mr,p,pt,pb,pl,pr,minWidth,maxWidth,width,...props}:FormHTMLAttributes<HTMLFormElement>&LayoutSpacingProps&{gap?:LayoutLength;columns?:string}){
  const composed:CSSProperties={...layoutStyle({m,mt,mb,ml,mr,p,pt,pb,pl,pr,minWidth,maxWidth,width}),...(gap!==undefined||columns?{display:'grid',gap,gridTemplateColumns:columns}:{}),...style}
  return <form className={cx('ui-form',className)} style={composed} {...props}>{children}</form>
}

/** Group related fields under a consistent title, description and spacing contract. */
export function FormSection({title,description,children,actions,className=''}:{title?:ReactNode;description?:ReactNode;children:ReactNode;actions?:ReactNode;className?:string}){return <section className={cx('ui-form-section',className)}>{(title||description||actions)&&<header className="ui-form-section__header"><div>{title&&<h3>{title}</h3>}{description&&<p>{description}</p>}</div>{actions}</header>}<div className="ui-form-section__body">{children}</div></section>}
/** Arrange form controls on responsive tokenized columns instead of page-specific gap values. */
export function FormGrid({children,columns=2,className=''}:{children:ReactNode;columns?:1|2|3;className?:string}){return <div className={cx('ui-form-grid',`ui-form-grid--${columns}`,className)}>{children}</div>}
/** Render consistent form footer actions with RTL-safe alignment. */
export function FormActions({children,align='end',className=''}:{children:ReactNode;align?:'start'|'end'|'between';className?:string}){return <div className={cx('ui-form-actions',`ui-form-actions--${align}`,className)}>{children}</div>}

/** Combine Modal, Form and DialogActions into one accessible CRUD form contract without DOM-click submit workarounds. */
export function FormDialog({open,onClose,title,description,formId,onSubmit,children,submitLabel='Save',cancelLabel='Cancel',loading=false,disabled=false,danger=false,size='md',gap=12}:{open:boolean;onClose:()=>void;title:ReactNode;description?:ReactNode;formId:string;onSubmit:FormHTMLAttributes<HTMLFormElement>['onSubmit'];children:ReactNode;submitLabel?:ReactNode;cancelLabel?:ReactNode;loading?:boolean;disabled?:boolean;danger?:boolean;size?:'md'|'lg'|'xl';gap?:LayoutLength}){return <Modal open={open} onClose={()=>{if(!loading)onClose()}} title={title} description={description} size={size} footer={<DialogActions onCancel={onClose} cancelLabel={cancelLabel} submitLabel={submitLabel} form={formId} loading={loading} disabled={disabled} danger={danger}/>}><Form id={formId} onSubmit={onSubmit} gap={gap}>{children}</Form></Modal>}

/** Handles the card operation for the WorkIntel client. */ export function Card({ children, elevated = false, interactive = false, className = '', style, ...props }: HTMLAttributes<HTMLDivElement> & VisualProps & { elevated?: boolean; interactive?: boolean }) {
  const [visual,rest]=splitVisualProps(props)
  return <div className={cx('ui-card', elevated && 'ui-card--elevated', interactive && 'ui-card--interactive', className)} style={{...visualStyle(visual),...style}} {...rest}>{children}</div>
}
/** Handles the card header operation for the WorkIntel client. */ export function CardHeader({ title, description, action, children }: { title?: ReactNode; description?: ReactNode; action?: ReactNode; children?: ReactNode }) {
  const localize=useLocalizedNode();return <div className="ui-card__header"><div>{children ?? <><h3 className="ui-card-title">{localize(title)}</h3>{description && <p className="ui-card-description">{localize(description)}</p>}</>}</div>{action}</div>
}
/** Handles the card body operation for the WorkIntel client. */ export function CardBody({ children, className = '', style, ...props }: HTMLAttributes<HTMLDivElement> & VisualProps) { const [visual,rest]=splitVisualProps(props); return <div className={cx('ui-card__body', className)} style={{...visualStyle(visual),...style}} {...rest}>{children}</div> }
/** Handles the card footer operation for the WorkIntel client. */ export function CardFooter({ children, className = '', ...props }: HTMLAttributes<HTMLDivElement>) { return <div className={cx('ui-card__footer', className)} {...props}>{children}</div> }

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'outline' | 'danger'

/** Render a semantic button without visual treatment for specialized design-system patterns such as command rows and media tiles. */
export function Pressable({ type = 'button', className = '', style, ...props }: ButtonHTMLAttributes<HTMLButtonElement> & VisualProps) {
  const [visual,rest]=splitVisualProps(props)
  return <button type={type} className={cx('ui-pressable', className)} style={{...visualStyle(visual),...style}} {...rest} />
}

/** Render the single shared checkbox primitive used by feature modules. */
export const Checkbox = forwardRef<HTMLInputElement, Omit<InputHTMLAttributes<HTMLInputElement>, 'type'>>(({ className = '', ...props }, ref) =>
  <input ref={ref} type="checkbox" className={cx('ui-checkbox', className)} {...props} />
)
Checkbox.displayName = 'Checkbox'

/** Render a radio control through the same choice-control contract as checkboxes. */
export const Radio = forwardRef<HTMLInputElement, Omit<InputHTMLAttributes<HTMLInputElement>, 'type'>>(({ className = '', ...props }, ref) =>
  <input ref={ref} type="radio" className={cx('ui-radio', className)} {...props} />
)
Radio.displayName = 'Radio'

/** Render a dynamically selected checkbox/radio type without exposing native controls in feature code. */
export const ChoiceInput = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(({ className = '', type = 'checkbox', ...props }, ref) =>
  <input ref={ref} type={type} className={cx(type === 'radio' ? 'ui-radio' : 'ui-checkbox', className)} {...props} />
)
ChoiceInput.displayName = 'ChoiceInput'

/** Keep native file selection semantics inside the design system while letting composed upload surfaces own the visible trigger. */
export const HiddenFileInput = forwardRef<HTMLInputElement, Omit<InputHTMLAttributes<HTMLInputElement>, 'type'>>(({ className = '', ...props }, ref) =>
  <input ref={ref} type="file" className={cx('ui-hidden-file-input', className)} {...props} />
)
HiddenFileInput.displayName = 'HiddenFileInput'

/** Render media through one design-system image primitive so loading, decoding and accessibility defaults remain consistent. */
export function Image({ loading = 'lazy', decoding = 'async', alt = '', className = '', style, ...props }: ImgHTMLAttributes<HTMLImageElement> & VisualProps) {
  const [visual,rest]=splitVisualProps(props)
  return <img loading={loading} decoding={decoding} alt={alt} className={cx('ui-image', className)} style={{...visualStyle(visual),...style}} {...rest} />
}

/** Render accessible determinate progress without page-specific width calculations. */
export function ProgressBar({ value, max = 100, label = 'Progress', className = '' }: { value: number; max?: number; label?: string; className?: string }) {
  const safeMax = Math.max(1, max)
  const safeValue = Math.min(safeMax, Math.max(0, value))
  return <progress className={cx('ui-progress-bar', className)} max={safeMax} value={safeValue} aria-label={label} />
}

/** Handles the button operation for the WorkIntel client. */ export function Button({ variant = 'outline', size = 'md', iconOnly = false, loading = false, className = '', children, disabled, style, ...props }: ButtonHTMLAttributes<HTMLButtonElement> & VisualProps & { variant?: ButtonVariant; size?: 'sm' | 'md' | 'lg'; iconOnly?: boolean; loading?: boolean }) {
  const localize=useLocalizedNode();const [visual,rest]=splitVisualProps(props);return <button className={cx('ui-button', `ui-button--${variant}`, size !== 'md' && `ui-button--${size}`, iconOnly && 'ui-button--icon', loading && 'is-loading', className)} style={{...visualStyle(visual),...style}} disabled={disabled || loading} aria-busy={loading || undefined} {...rest}>{loading && <LoaderCircle className="ui-button__spinner" size={14}/>}<span className="ui-button__content">{localize(children)}</span></button>
}
/** Render an icon-only action with an accessible label and matching visual tooltip. */
export function IconButton(props: ButtonHTMLAttributes<HTMLButtonElement> & VisualProps & { variant?: ButtonVariant; size?: 'sm' | 'md' | 'lg'; loading?: boolean; tooltip?: ReactNode }) {
  const label = props['aria-label'] ?? 'Action'
  const { tooltip, ...buttonProps } = props
  return <Tooltip content={tooltip ?? label}><Button {...buttonProps} iconOnly aria-label={label} /></Tooltip>
}

/** Standardize refresh feedback so users can see active, successful and failed refresh attempts. */
export function RefreshButton({onRefresh,label,lastUpdated,className=''}:{onRefresh:()=>Promise<void>|void;label?:string;lastUpdated?:Date|null;className?:string}){
  const {t}=useLocalization()
  const [state,setState]=useState<'idle'|'loading'|'success'|'error'>('idle')
  /** Run the supplied refresh action and keep visible feedback for the result. */
  const refresh=async()=>{if(state==='loading')return;setState('loading');try{await onRefresh();setState('success');window.setTimeout(()=>setState('idle'),1800)}catch(error){setState('error');window.setTimeout(()=>setState('idle'),3500);throw error}}
  const text=state==='loading'?t('common.refreshing'):state==='success'?t('common.updated'):state==='error'?t('common.retry'):(label??t('common.refresh'))
  return <span className={cx('ui-refresh-control',className)}><Button variant="outline" loading={state==='loading'} onClick={()=>void refresh()}>{text}</Button>{lastUpdated&&<small>{t('common.updated')} {lastUpdated.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}</small>}</span>
}

/** Render one consistent form field with optional hint, required marker and validation feedback. */ export function Field({ label, hint, error, required = false, children, className = '' }: { label?: ReactNode; hint?: ReactNode; error?: ReactNode; required?: boolean; children: ReactNode; className?: string }) {
  const localize=useLocalizedNode();return <label className={cx('ui-field', error && 'is-error', className)}>{label && <span className="ui-label">{localize(label)}{required&&<span className="ui-required" aria-hidden="true"> *</span>}</span>}{children}{error?<span className="ui-field-error" role="alert">{localize(error)}</span>:hint&&<span className="ui-hint">{localize(hint)}</span>}</label>
}
/** Handles the local date string operation for the WorkIntel client. */ function localDateString(date:Date){const y=date.getFullYear();const m=String(date.getMonth()+1).padStart(2,'0');const d=String(date.getDate()).padStart(2,'0');return `${y}-${m}-${d}`}
/** Handles the local time string operation for the WorkIntel client. */ function localTimeString(date:Date){return `${String(date.getHours()).padStart(2,'0')}:${String(date.getMinutes()).padStart(2,'0')}`}
/** Handles the parse smart date operation for the WorkIntel client. */ function parseSmartDate(value:string,mode:'date'|'time'|'datetime-local'){
  if(!value)return null
  if(mode==='date'){const [y,m,d]=value.slice(0,10).split('-').map(Number);return y&&m&&d?new Date(y,m-1,d):null}
  if(mode==='time'){const [h,m]=value.slice(0,5).split(':').map(Number);const date=new Date();date.setSeconds(0,0);date.setHours(h||0,m||0);return date}
  const parsed=new Date(value);return Number.isNaN(parsed.getTime())?null:parsed
}
/** Handles the smart date input operation for the WorkIntel client. */ function SmartDateInput({type,className='',value,onChange,min,max,disabled,placeholder,id,name,required,style}:InputHTMLAttributes<HTMLInputElement> & {type:'date'|'time'|'datetime-local'}){
  const mode=type;const selected=parseSmartDate(String(value??''),mode);const minDate=typeof min==='string'&&mode!=='time'?parseSmartDate(min,mode):undefined;const maxDate=typeof max==='string'&&mode!=='time'?parseSmartDate(max,mode):undefined
  const Picker=ReactDatePicker as any
  /** Handles the commit operation for the WorkIntel client. */ const commit=(date:Date|null)=>{const next=!date?'':mode==='date'?localDateString(date):mode==='time'?localTimeString(date):`${localDateString(date)}T${localTimeString(date)}`;if(onChange){onChange({target:{value:next},currentTarget:{value:next}} as ChangeEvent<HTMLInputElement>)}}
  return <span className="wi-picker-wrap" style={style}><Picker selected={selected} onChange={commit} className={cx('ui-input','wi-picker-input',className)} wrapperClassName="wi-picker" popperClassName="wi-picker-popper" calendarClassName="wi-picker-calendar" portalId="workintel-datepicker-portal" dateFormat={mode==='date'?'yyyy-MM-dd':mode==='time'?'HH:mm':'yyyy-MM-dd HH:mm'} showTimeSelect={mode==='datetime-local'} showTimeSelectOnly={mode==='time'} timeFormat="HH:mm" timeIntervals={15} calendarStartDay={1} minDate={minDate??undefined} maxDate={maxDate??undefined} disabled={disabled} placeholderText={placeholder} id={id} name={name} required={required} isClearable={!required} autoComplete="off" /></span>
}
/** Render a consistently styled file chooser while preserving the native input for forms and accessibility. */ function FileInput({ className = '', onChange, disabled, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  const localization=useOptionalLocalization();const chooseFile=localization?.t('common.choose_file')??'Choose file';const browse=localization?.t('common.browse')??'Browse'
  const [fileLabel, setFileLabel] = useState(chooseFile)
  /** Forward file changes and expose the selected filename in the styled control. */
  const change = (event: ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(event.target.files ?? [])
    setFileLabel(files.length > 1 ? (localization?.t('common.files_selected',{count:files.length})??`${files.length} files selected`) : files[0]?.name ?? chooseFile)
    onChange?.(event)
  }
  return <span className={cx('ui-file-input', disabled && 'is-disabled', className)}><input {...props} type="file" disabled={disabled} onChange={change}/><span className="ui-file-input__action"><FileUp size={14}/> {browse}</span><span className="ui-file-input__name" title={fileLabel}>{fileLabel}</span></span>
}
/** Handles the input operation for the WorkIntel client. */ export function Input({ className = '', type, style, ...props }: InputHTMLAttributes<HTMLInputElement> & VisualProps) { const [visual,rest]=splitVisualProps(props); const composed={...visualStyle(visual),...style}; if(type==='date'||type==='time'||type==='datetime-local')return <SmartDateInput type={type as 'date'|'time'|'datetime-local'} className={className} style={composed} {...rest}/>; if(type==='file')return <FileInput className={className} style={composed} {...rest}/>; return <input className={cx('ui-input', className)} type={type} style={composed} {...rest} /> }
/** Handles the textarea operation for the WorkIntel client. */ export function Textarea({ className = '', ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) { return <textarea className={cx('ui-textarea', className)} {...props} /> }
type SelectOptionRow = { value: string; label: ReactNode; disabled: boolean }
/** Normalize React option children into rows used by the custom single-select listbox. */
function selectOptionRows(children: ReactNode): SelectOptionRow[] {
  return Children.toArray(children).flatMap(child => {
    if (!isValidElement(child) || (child.type !== 'option' && child.type !== Option)) return []
    const props = child.props as { value?: string | number; children?: ReactNode; disabled?: boolean }
    return [{ value: String(props.value ?? ''), label: props.children, disabled: Boolean(props.disabled) }]
  })
}

/** Render one select option through the WorkIntel form component boundary. */
export function Option({className='',...props}:OptionHTMLAttributes<HTMLOptionElement>){return <option className={cx('ui-option',className)} {...props}/>} 
/** Render a form label through the WorkIntel form component boundary. */
export function Label({className='',style,...props}:LabelHTMLAttributes<HTMLLabelElement>&VisualProps){const [visual,rest]=splitVisualProps(props);return <label className={cx('ui-label',className)} style={{...visualStyle(visual),...style}} {...rest}/>} 
/** Render navigation/external anchors through one WorkIntel link primitive. */
export function Link({className='',target,rel,...props}:AnchorHTMLAttributes<HTMLAnchorElement>){return <a className={cx('ui-link',className)} target={target} rel={rel??(target==='_blank'?'noopener noreferrer':undefined)} {...props}/>} 

/** Render the shared select; multi-selects keep native multi-selection semantics while single-selects use the React listbox. */
export function Select({style,...props}: SelectHTMLAttributes<HTMLSelectElement>&VisualProps) {
  const [visual,rest]=splitVisualProps(props);const composed={...visualStyle(visual),...style}
  if (props.multiple) return <select className={cx('ui-select', 'ui-select--multiple', props.className)} style={composed} {...rest}>{props.children}</select>
  return <SingleSelect {...rest} style={composed}/>
}

/** Render a React-owned single select listbox with a hidden value for named form submissions. */
function SingleSelect({ className = '', children, value, defaultValue, onChange, disabled, required, name, id, style, form, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
  const localize=useLocalizedNode()
  const options = useMemo(() => selectOptionRows(children), [children])
  const controlled = value !== undefined
  const [internalValue, setInternalValue] = useState(String(defaultValue ?? ''))
  const selectedValue = controlled ? String(value ?? '') : internalValue
  const selected = options.find(option => option.value === selectedValue) ?? options[0]
  const [open, setOpen] = useState(false)
  const [activeIndex, setActiveIndex] = useState(0)
  const listboxId = useId()
  const triggerRef = useRef<HTMLButtonElement>(null)
  const menuRef = useRef<HTMLDivElement>(null)
  const [menuStyle, setMenuStyle] = useState<Record<string, string | number>>({})

  /** Reposition the portal listbox so it remains visible above cards, modals and drawers. */
  const positionMenu = () => {
    const trigger = triggerRef.current
    if (!trigger) return
    const rect = trigger.getBoundingClientRect()
    const below = window.innerHeight - rect.bottom
    const openUpward = below < 230 && rect.top > below
    setMenuStyle({
      position: 'fixed',
      left: Math.max(8, Math.min(rect.left, window.innerWidth - Math.max(rect.width, 180) - 8)),
      width: Math.max(rect.width, 180),
      ...(openUpward ? { bottom: window.innerHeight - rect.top + 6 } : { top: rect.bottom + 6 }),
    })
  }

  useEffect(() => {
    if (!open) return
    const selectedIndex = Math.max(0, options.findIndex(option => option.value === selectedValue && !option.disabled))
    setActiveIndex(selectedIndex)
    positionMenu()
    /** Close the select when the user clicks outside both the trigger and portal listbox. */
    const closeOutside = (event: MouseEvent) => {
      const node = event.target as Node
      if (!triggerRef.current?.contains(node) && !menuRef.current?.contains(node)) setOpen(false)
    }
    /** Keep the portal anchored while any scroll container or viewport moves. */
    const reposition = () => window.requestAnimationFrame(positionMenu)
    window.addEventListener('resize', reposition)
    window.addEventListener('scroll', reposition, true)
    document.addEventListener('mousedown', closeOutside)
    return () => {
      window.removeEventListener('resize', reposition)
      window.removeEventListener('scroll', reposition, true)
      document.removeEventListener('mousedown', closeOutside)
    }
  }, [open, options, selectedValue])

  /** Commit one custom-listbox option through the same value-centric ChangeEvent contract used by existing forms. */
  const choose = (next: string) => {
    const target = { value: next, name: name ?? '' } as HTMLSelectElement
    onChange?.({ target, currentTarget: target } as ChangeEvent<HTMLSelectElement>)
    if (!controlled) setInternalValue(target.value)
    setOpen(false)
    window.requestAnimationFrame(() => triggerRef.current?.focus())
  }

  /** Provide keyboard navigation expected from a professional single-select control. */
  const onTriggerKeyDown = (event: ReactKeyboardEvent<HTMLButtonElement>) => {
    if (disabled) return
    const enabled = options.map((option, index) => ({ option, index })).filter(row => !row.option.disabled)
    if (!enabled.length) return
    if (event.key === 'Escape') { setOpen(false); return }
    if (event.key === 'Tab') { setOpen(false); return }
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      if (!open) setOpen(true)
      else choose(options[activeIndex]?.value ?? selectedValue)
      return
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Home' || event.key === 'End') {
      event.preventDefault()
      if (!open) setOpen(true)
      const current = Math.max(0, enabled.findIndex(row => row.index === activeIndex))
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? enabled.length - 1 : event.key === 'ArrowDown' ? Math.min(enabled.length - 1, current + 1) : Math.max(0, current - 1)
      setActiveIndex(enabled[next].index)
    }
  }

  const menu = open && typeof document !== 'undefined' ? createPortal(
    <div id={listboxId} ref={menuRef} className="ui-select-menu" style={menuStyle} role="listbox" aria-label={props['aria-label'] ?? name ?? 'Select option'}>
      <div className="ui-select-menu__scroll">
        {options.map((option, index) => <button
          key={`${option.value}-${index}`}
          id={`${listboxId}-option-${index}`}
          type="button"
          role="option"
          tabIndex={-1}
          aria-selected={option.value === selectedValue}
          disabled={option.disabled}
          className={cx('ui-select-option', option.value === selectedValue && 'is-selected', index === activeIndex && 'is-active')}
          onMouseEnter={() => !option.disabled && setActiveIndex(index)}
          onClick={() => choose(option.value)}
        ><span>{localize(option.label)}</span>{option.value === selectedValue && <Check size={14}/>}</button>)}
      </div>
    </div>, document.body) : null

  return <span className={cx('ui-select-control', disabled && 'is-disabled', className)} style={style}>
    {name && <input type="hidden" name={name} value={selectedValue} form={form}/>} 
    <button ref={triggerRef} id={id} type="button" className={cx('ui-select-trigger', open && 'is-open')} disabled={disabled} role="combobox" aria-haspopup="listbox" aria-expanded={open} aria-controls={open?listboxId:undefined} aria-activedescendant={open?`${listboxId}-option-${activeIndex}`:undefined} aria-autocomplete="none" aria-required={required || undefined} aria-label={props['aria-label']} onClick={() => setOpen(current => !current)} onKeyDown={onTriggerKeyDown}>
      <span className={cx('ui-select-trigger__value', !selectedValue && 'is-placeholder')}>{localize(selected?.label ?? 'Select…')}</span><ChevronDown className="ui-select-trigger__chevron" size={15}/>
    </button>
    {menu}
  </span>
}
/** Handles the search input operation for the WorkIntel client. */ export function SearchInput({ icon, className = '', ...props }: InputHTMLAttributes<HTMLInputElement> & { icon?: ReactNode }) { return <div className={cx('ui-search', className)}><span className="ui-search__icon">{icon ?? <Search size={14} strokeWidth={1.8} />}</span><Input {...props} /></div> }

/** Handles the segmented operation for the WorkIntel client. */ export function Segmented<T extends string>({ value, options, onChange, ariaLabel }: { value: T; options: Array<T | { value: T; label: ReactNode }>; onChange: (value: T) => void; ariaLabel?: string }) {
  const localize=useLocalizedNode();const localization=useOptionalLocalization();return <div className="ui-segmented" role="group" aria-label={ariaLabel??localization?.t('common.options')??'Options'}>{options.map(option => { const item = typeof option === 'string' ? { value: option as T, label: option } : option; return <button key={item.value} type="button" aria-pressed={value===item.value} className={cx('ui-segmented__item', value === item.value && 'is-active')} onClick={() => onChange(item.value)}>{localize(item.label)}</button> })}</div>
}
/** Handles the tabs operation for the WorkIntel client. */ export function Tabs<T extends string>({ value, tabs, onChange, ariaLabel }: { value: T; tabs: Array<T | { value: T; label: ReactNode }>; onChange: (value: T) => void; ariaLabel?: string }) {
  const localize=useLocalizedNode();const localization=useOptionalLocalization();const normalized=tabs.map(tab=>typeof tab==='string'?{value:tab as T,label:tab}:tab)
  /** Move between sibling tabs with the WAI-ARIA arrow/Home/End keyboard convention. */
  const onKeyDown=(event:ReactKeyboardEvent<HTMLButtonElement>,index:number)=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;event.preventDefault();const rtl=document.documentElement.dir==='rtl';let next=index;if(event.key==='Home')next=0;else if(event.key==='End')next=normalized.length-1;else if(event.key==='ArrowRight')next=(index+(rtl?-1:1)+normalized.length)%normalized.length;else next=(index+(rtl?1:-1)+normalized.length)%normalized.length;onChange(normalized[next].value);const buttons=event.currentTarget.parentElement?.querySelectorAll<HTMLButtonElement>('[role="tab"]');window.requestAnimationFrame(()=>buttons?.[next]?.focus())}
  return <div className="ui-tabs" role="tablist" aria-label={ariaLabel??localization?.t('common.tabs')??'Tabs'}>{normalized.map((item,index)=><button key={item.value} type="button" role="tab" aria-selected={value===item.value} tabIndex={value===item.value?0:-1} className={cx('ui-tab', value === item.value && 'is-active')} onKeyDown={event=>onKeyDown(event,index)} onClick={() => onChange(item.value)}>{localize(item.label)}</button>)}</div>
}

/** Render the shared grid/table switch with identical geometry on every collection screen. */
export function ViewModeToggle({value,onChange,gridLabel='Grid',tableLabel='List',ariaLabel='View mode'}:{value:'grid'|'table';onChange:(value:'grid'|'table')=>void;gridLabel?:string;tableLabel?:string;ariaLabel?:string}){
  return <div className="ui-view-mode-toggle"><Segmented value={value} onChange={onChange} ariaLabel={ariaLabel} options={[{value:'table',label:<><List size={13}/> {tableLabel}</>},{value:'grid',label:<><Grid2X2 size={13}/> {gridLabel}</>}]} /></div>
}


/** Handles the badge operation for the WorkIntel client. */ export function Badge({ children, tone = 'neutral', dot = false, className = '', style, ...props }: HTMLAttributes<HTMLSpanElement> & VisualProps & { children: ReactNode; tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'accent'; dot?: boolean }) {
  const localize=useLocalizedNode();const [visual,rest]=splitVisualProps(props);return <span {...rest} style={{...visualStyle(visual),...style}} className={cx('ui-badge', tone !== 'neutral' && `ui-badge--${tone}`, className)}>{dot && <span className="ui-status-dot" />}{localize(children)}</span>
}
/** Handles the avatar operation for the WorkIntel client. */ export function Avatar({ name = '', src, size = 'md' }: { name?: string; src?: string; size?: 'sm' | 'md' | 'lg' }) {
  const initials = name.split(/\s+/).filter(Boolean).slice(0, 2).map(x => x[0]).join('').toUpperCase()
  return <span className={cx('ui-avatar', size !== 'md' && `ui-avatar--${size}`)}>{src ? <img src={src} alt={name} /> : initials}</span>
}
/** Handles the progress operation for the WorkIntel client. */ export function Progress({ value, tone = 'accent', className = '', label }: { value: number; tone?: 'accent' | 'success' | 'warning' | 'danger'; className?: string; label?: string }) {
  const localization=useOptionalLocalization();const normalized=Math.max(0,Math.min(100,value));return <div className={cx('ui-progress', tone !== 'accent' && `ui-progress--${tone}`, className)} role="progressbar" aria-label={label??localization?.t('common.progress')??'Progress'} aria-valuemin={0} aria-valuemax={100} aria-valuenow={normalized}><div className="ui-progress__bar" style={{ width: `${normalized}%` }} /></div>
}
/** Handles the stat card operation for the WorkIntel client. */ export function StatCard({ label, value, sub, icon }: { label: ReactNode; value: ReactNode; sub?: ReactNode; icon?: ReactNode }) {
  const localize=useLocalizedNode();return <div className="ui-stat-card"><div className="ui-stat-card__top"><span className="ui-stat-card__label">{localize(label)}</span>{icon}</div><div className="ui-stat-card__value">{value}</div>{sub && <div className="ui-stat-card__sub">{localize(sub)}</div>}</div>
}
/** Render a styled inline alert with dismiss support; transient positive/informational notices auto-expire. */ export function Alert({ children, tone = 'info', icon, className = '', dismissible = true, autoHideMs, style, ...props }: HTMLAttributes<HTMLDivElement> & VisualProps & { children: ReactNode; tone?: 'info' | 'warning' | 'danger' | 'success'; icon?: ReactNode; dismissible?: boolean; autoHideMs?: number | false }) {
  const localization=useOptionalLocalization();const [visible, setVisible] = useState(true)
  useEffect(() => {
    setVisible(true)
    const duration = autoHideMs === false ? 0 : autoHideMs ?? (tone === 'success' ? 5000 : tone === 'info' ? 6500 : 0)
    if (!duration) return
    const timer = window.setTimeout(() => setVisible(false), duration)
    return () => window.clearTimeout(timer)
  }, [children, tone, autoHideMs])
  if (!visible) return null
  const [visual,rest]=splitVisualProps(props)
  return <div className={cx('ui-alert', `ui-alert--${tone}`, className)} style={{...visualStyle(visual),...style}} role={tone === 'danger' ? 'alert' : 'status'} {...rest}>{icon && <span className="ui-alert__icon">{icon}</span>}<div className="ui-alert__content">{children}</div>{dismissible && <button type="button" className="ui-alert__close" aria-label={localization?.t('common.dismiss_alert')??'Dismiss alert'} onClick={() => setVisible(false)}><X size={13}/></button>}</div>
}

/** Handles the table wrap operation for the WorkIntel client. */ export function TableWrap({ children, compact = false, className = '', label, tableProps }: { children: ReactNode; compact?: boolean; className?: string; label?: string; tableProps?:TableHTMLAttributes<HTMLTableElement> }) { return <div className={cx('ui-table-wrap', className)} role={label?'region':undefined} aria-label={label} tabIndex={label?0:undefined}><table {...tableProps} className={cx('ui-table', compact && 'ui-table--compact',tableProps?.className)}>{label&&<caption className="ui-sr-only">{label}</caption>}{children}</table></div> }

/** Pair one boolean setting with a visible title and description while keeping switch alignment consistent across forms. */
export function BooleanField({label,description,checked,onChange,disabled=false,className=''}:{label:ReactNode;description?:ReactNode;checked:boolean;onChange:(checked:boolean)=>void;disabled?:boolean;className?:string}){const localize=useLocalizedNode();return <div className={cx('ui-boolean-field',className)}><div><strong>{localize(label)}</strong>{description&&<span>{localize(description)}</span>}</div><Switch checked={checked} onChange={onChange} disabled={disabled} label={typeof label==='string'?label:undefined}/></div>}

/** Handles the switch operation for the WorkIntel client. */ export function Switch({ checked, onChange, disabled = false, label }: { checked: boolean; onChange: (checked: boolean) => void; disabled?: boolean; label?: string }) {
  const localization=useOptionalLocalization();return <button type="button" role="switch" aria-checked={checked} aria-label={label??localization?.t('common.toggle_setting')??'Toggle setting'} disabled={disabled} onClick={() => onChange(!checked)} className={cx('ui-switch', checked && 'is-on')}><span className="ui-switch__thumb" aria-hidden="true" /></button>
}

/** Handles the modal operation for the WorkIntel client. */ export function Modal({ open, onClose, title, description, children, footer, size = 'md' }: { open: boolean; onClose: () => void; title: ReactNode; description?: ReactNode; children: ReactNode; footer?: ReactNode; size?: 'md' | 'lg' | 'xl' }) {
  const localize=useLocalizedNode();const localization=useOptionalLocalization();const dialogRef=useRef<HTMLElement>(null);const titleId=useId();const descriptionId=useId();useFocusTrap(open,dialogRef,{onEscape:onClose})
  useEffect(()=>{if(open)window.dispatchEvent(new CustomEvent('workintel:overlay-open',{detail:{kind:'modal'}}))},[open])
  if (!open) return null
  return <div className="ui-backdrop" onMouseDown={e => { if (e.currentTarget === e.target) onClose() }}><section ref={dialogRef} tabIndex={-1} className={cx('ui-modal', size === 'lg' && 'ui-modal--lg', size === 'xl' && 'ui-modal--xl')} role="dialog" aria-modal="true" aria-labelledby={titleId} aria-describedby={description?descriptionId:undefined}><div className="ui-modal__header"><div><h3 id={titleId} className="ui-card-title">{localize(title)}</h3>{description && <p id={descriptionId} className="ui-card-description">{localize(description)}</p>}</div><IconButton variant="ghost" aria-label={localization?.t('common.close_dialog')??'Close dialog'} onClick={onClose}><X size={15} /></IconButton></div><div className="ui-modal__body">{children}</div>{footer && <div className="ui-modal__footer">{footer}</div>}</section></div>
}

/** Handles the drawer operation for the WorkIntel client. */ export function Drawer({ open, onClose, title, description, children, footer }: { open: boolean; onClose: () => void; title: ReactNode; description?: ReactNode; children: ReactNode; footer?: ReactNode }) {
  const localize=useLocalizedNode();const localization=useOptionalLocalization();const drawerRef=useRef<HTMLElement>(null);const titleId=useId();const descriptionId=useId();useFocusTrap(open,drawerRef,{onEscape:onClose})
  useEffect(()=>{if(open)window.dispatchEvent(new CustomEvent('workintel:overlay-open',{detail:{kind:'drawer'}}))},[open])
  if (!open) return null
  return <><div className="ui-drawer-backdrop" onClick={onClose} aria-hidden="true"/><aside ref={drawerRef} tabIndex={-1} className="ui-drawer" role="dialog" aria-modal="true" aria-labelledby={titleId} aria-describedby={description?descriptionId:undefined}><div className="ui-drawer__header"><div><h3 id={titleId} className="ui-card-title">{localize(title)}</h3>{description && <p id={descriptionId} className="ui-card-description">{localize(description)}</p>}</div><IconButton variant="ghost" aria-label={localization?.t('common.close_panel')??'Close panel'} onClick={onClose}><X size={15} /></IconButton></div><div className="ui-drawer__body">{children}</div>{footer && <div className="ui-drawer__footer">{footer}</div>}</aside></>
}

export type MenuItem = { label?: ReactNode; icon?: ReactNode; meta?: ReactNode; danger?: boolean; separator?: boolean; header?: boolean; disabled?: boolean; onClick?: () => void }
type FloatingAlign = 'left' | 'right'
type FloatingPlacement = { top:number; left:number; width?:number; maxHeight:number; transformOrigin:string }

/** Calculate a collision-aware viewport position for portal menus and popovers. */
function floatingPlacement(anchor: HTMLElement, content: HTMLElement | null, align: FloatingAlign, minWidth = 188): FloatingPlacement {
  const rect = anchor.getBoundingClientRect()
  const viewportGap = 8
  const gap = 6
  const measuredWidth = Math.max(minWidth, content?.offsetWidth ?? 0, rect.width)
  const measuredHeight = Math.max(40, content?.offsetHeight ?? 240)
  const roomBelow = window.innerHeight - rect.bottom - viewportGap
  const roomAbove = rect.top - viewportGap
  const openUp = roomBelow < Math.min(measuredHeight, 280) && roomAbove > roomBelow
  const maxHeight = Math.max(96, Math.min(openUp ? roomAbove - gap : roomBelow - gap, 420))
  const desiredLeft = align === 'right' ? rect.right - measuredWidth : rect.left
  const left = Math.max(viewportGap, Math.min(desiredLeft, window.innerWidth - measuredWidth - viewportGap))
  const top = openUp ? Math.max(viewportGap, rect.top - Math.min(measuredHeight, maxHeight) - gap) : Math.min(window.innerHeight - viewportGap, rect.bottom + gap)
  const originX = align === 'right' ? 'right' : 'left'
  return { top, left, width: measuredWidth, maxHeight, transformOrigin: `${openUp ? 'bottom' : 'top'} ${originX}` }
}

/** Keep a portal overlay positioned on its trigger through nested scrolling and viewport changes. */
function usePortalPosition(open:boolean, anchorRef:RefObject<HTMLElement|null>, contentRef:RefObject<HTMLElement|null>, align:FloatingAlign='right', minWidth=188){
  const [style,setStyle]=useState<FloatingPlacement>({top:0,left:0,maxHeight:320,transformOrigin:'top right'})
  useEffect(()=>{
    if(!open)return
    let frame=0
    /** Recalculate the portal position after viewport or ancestor layout changes. */
    const update=()=>{cancelAnimationFrame(frame);frame=requestAnimationFrame(()=>{if(anchorRef.current)setStyle(floatingPlacement(anchorRef.current,contentRef.current,align,minWidth))})}
    update()
    window.addEventListener('resize',update)
    window.addEventListener('scroll',update,true)
    const observer=typeof ResizeObserver!=='undefined'?new ResizeObserver(update):null
    if(anchorRef.current)observer?.observe(anchorRef.current)
    if(contentRef.current)observer?.observe(contentRef.current)
    return()=>{cancelAnimationFrame(frame);window.removeEventListener('resize',update);window.removeEventListener('scroll',update,true);observer?.disconnect()}
  },[open,align,minWidth,anchorRef,contentRef])
  return style
}

/** Render a portal-backed action menu so table/card overflow never clips row actions. */
export function Dropdown({ trigger, items, align = 'right', ariaLabel }: { trigger: ReactNode; items: MenuItem[]; align?: FloatingAlign; ariaLabel?: string }) {
  const localize=useLocalizedNode();const localization=useOptionalLocalization();const [open,setOpen]=useState(false);const menuId=useId();const resolvedAriaLabel=ariaLabel??localization?.t('common.actions')??'Actions'
  const anchorRef=useRef<HTMLSpanElement>(null)
  const menuRef=useRef<HTMLDivElement>(null)
  const style=usePortalPosition(open,anchorRef,menuRef,align,188)
  /** Return focus to the trigger button after keyboard dismissal. */
  const focusTrigger=()=>window.requestAnimationFrame(()=>anchorRef.current?.querySelector<HTMLElement>('button,[href],[tabindex]:not([tabindex="-1"])')?.focus())
  /** Focus a menu item by bounded index, skipping disabled controls. */
  const focusMenuItem=(position:'first'|'last')=>window.requestAnimationFrame(()=>{const buttons=Array.from(menuRef.current?.querySelectorAll<HTMLButtonElement>('[role="menuitem"]:not(:disabled)')??[]);(position==='first'?buttons[0]:buttons.at(-1))?.focus()})
  useEffect(()=>{
    if(!open)return
    /** Close the action menu only for clicks outside both trigger and portal content. */
    const close=(event:MouseEvent)=>{const node=event.target as Node;if(!anchorRef.current?.contains(node)&&!menuRef.current?.contains(node))setOpen(false)}
    document.addEventListener('mousedown',close)
    return()=>document.removeEventListener('mousedown',close)
  },[open])
  /** Navigate the active menu using standard Arrow/Home/End/Escape keyboard behavior. */
  const onMenuKeyDown=(event:ReactKeyboardEvent<HTMLDivElement>)=>{const buttons=Array.from(menuRef.current?.querySelectorAll<HTMLButtonElement>('[role="menuitem"]:not(:disabled)')??[]);const current=Math.max(0,buttons.indexOf(document.activeElement as HTMLButtonElement));if(event.key==='Escape'){event.preventDefault();setOpen(false);focusTrigger();return}if(event.key==='Tab'){setOpen(false);return}if(!['ArrowDown','ArrowUp','Home','End'].includes(event.key)||!buttons.length)return;event.preventDefault();const next=event.key==='Home'?0:event.key==='End'?buttons.length-1:event.key==='ArrowDown'?(current+1)%buttons.length:(current-1+buttons.length)%buttons.length;buttons[next].focus()}
  const menu=open&&typeof document!=='undefined'?createPortal(<div id={menuId} ref={menuRef} className="ui-dropdown ui-dropdown--portal" style={{position:'fixed',top:style.top,left:style.left,width:style.width,maxHeight:style.maxHeight,transformOrigin:style.transformOrigin}} role="menu" aria-label={resolvedAriaLabel} onKeyDown={onMenuKeyDown}>{items.map((item,i)=>item.separator?<div key={i} className="ui-menu-separator" role="separator"/>:item.header?<div key={i} className="ui-menu-label" role="presentation">{localize(item.label)}</div>:<button key={i} type="button" role="menuitem" disabled={item.disabled} className={cx('ui-menu-item',item.danger&&'ui-menu-item--danger')} onClick={()=>{if(item.disabled)return;item.onClick?.();setOpen(false)}}>{item.icon??<span className="ui-menu-item__icon-placeholder" aria-hidden="true"/>}<span className="ui-menu-item__label">{localize(item.label)}</span><span className="ui-menu-item__meta">{item.meta}</span></button>)}</div>,document.body):null
  const triggerElement=isValidElement(trigger)?cloneElement(trigger as ReactElement<any>,{...((trigger as ReactElement<any>).props??{}),'aria-haspopup':'menu','aria-expanded':open,'aria-controls':open?menuId:undefined,onClick:(event:any)=>{(trigger as ReactElement<any>).props?.onClick?.(event);if(!event.defaultPrevented)setOpen((current:boolean)=>!current)},onKeyDown:(event:ReactKeyboardEvent<HTMLElement>)=>{(trigger as ReactElement<any>).props?.onKeyDown?.(event);if(event.defaultPrevented)return;if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();setOpen(true);focusMenuItem(event.key==='ArrowDown'?'first':'last')}else if(event.key==='Escape'&&open){event.preventDefault();setOpen(false)}}}):<button type="button" className="ui-button ui-button--outline" aria-haspopup="menu" aria-expanded={open} aria-controls={open?menuId:undefined} onClick={()=>setOpen(current=>!current)}>{trigger}</button>
  return <span className="ui-dropdown-anchor" ref={anchorRef}>{triggerElement}{menu}</span>
}

/** Render general floating content in a viewport-aware body portal. */
export function Popover({ trigger, children, align='right', ariaLabel }: { trigger: ReactNode; children: ReactNode; align?:FloatingAlign; ariaLabel?: string }) {
  const localization=useOptionalLocalization();const [open,setOpen]=useState(false);const popoverId=useId();const resolvedAriaLabel=ariaLabel??localization?.t('common.options')??'Options'
  const anchorRef=useRef<HTMLSpanElement>(null)
  const popoverRef=useRef<HTMLDivElement>(null)
  const style=usePortalPosition(open,anchorRef,popoverRef,align,220)
  /** Return keyboard focus to the popover trigger after explicit dismissal. */
  const focusTrigger=()=>window.requestAnimationFrame(()=>anchorRef.current?.querySelector<HTMLElement>('button,[href],[tabindex]:not([tabindex="-1"])')?.focus())
  useEffect(()=>{
    if(!open)return
    /** Close the popover only when the pointer lands outside trigger and portal content. */
    const close=(event:MouseEvent)=>{const node=event.target as Node;if(!anchorRef.current?.contains(node)&&!popoverRef.current?.contains(node))setOpen(false)}
    /** Let Escape close a non-modal popover without trapping normal Tab order. */
    const key=(event:KeyboardEvent)=>{if(event.key==='Escape'){event.preventDefault();setOpen(false);focusTrigger()}}
    document.addEventListener('mousedown',close);document.addEventListener('keydown',key,true)
    return()=>{document.removeEventListener('mousedown',close);document.removeEventListener('keydown',key,true)}
  },[open])
  const content=open&&typeof document!=='undefined'?createPortal(<div id={popoverId} ref={popoverRef} className="ui-popover ui-popover--portal" style={{position:'fixed',top:style.top,left:style.left,width:style.width,maxHeight:style.maxHeight,transformOrigin:style.transformOrigin}} role="dialog" aria-label={resolvedAriaLabel}>{children}</div>,document.body):null
  const triggerElement=isValidElement(trigger)?cloneElement(trigger as ReactElement<any>,{...((trigger as ReactElement<any>).props??{}),'aria-haspopup':'dialog','aria-expanded':open,'aria-controls':open?popoverId:undefined,onClick:(event:any)=>{(trigger as ReactElement<any>).props?.onClick?.(event);if(!event.defaultPrevented)setOpen((current:boolean)=>!current)},onKeyDown:(event:ReactKeyboardEvent<HTMLElement>)=>{(trigger as ReactElement<any>).props?.onKeyDown?.(event);if(event.defaultPrevented)return;if(event.key==='ArrowDown'){event.preventDefault();setOpen(true);window.requestAnimationFrame(()=>focusFirstPortalControl(popoverRef.current))}}}):<button type="button" className="ui-button ui-button--outline" aria-haspopup="dialog" aria-expanded={open} onClick={()=>setOpen(current=>!current)}>{trigger}</button>
  return <span className="ui-dropdown-anchor" ref={anchorRef}>{triggerElement}{content}</span>
}

/** Handles the tooltip operation for the WorkIntel client. */ export function Tooltip({ content, children, placement = 'top' }: { content: ReactNode; children: ReactNode; placement?: 'top' | 'right' | 'bottom' | 'left' }) {
  const [show, setShow] = useState(false)
  const [position, setPosition] = useState({ top: 0, left: 0 })
  const anchorRef = useRef<HTMLSpanElement>(null)

  /** Updates update position state for the current workflow. */ const updatePosition = () => {
    const rect = anchorRef.current?.getBoundingClientRect()
    if (!rect) return

    const gap = 9
    if (placement === 'right') setPosition({ top: rect.top + rect.height / 2, left: rect.right + gap })
    if (placement === 'left') setPosition({ top: rect.top + rect.height / 2, left: rect.left - gap })
    if (placement === 'bottom') setPosition({ top: rect.bottom + gap, left: rect.left + rect.width / 2 })
    if (placement === 'top') setPosition({ top: rect.top - gap, left: rect.left + rect.width / 2 })
  }

  useEffect(() => {
    if (!show) return
    updatePosition()
    /** Close stale tooltips before modal/drawer overlays, navigation, or pointer actions. */
    const closeTransient=()=>setShow(false)
    window.addEventListener('resize', updatePosition)
    window.addEventListener('scroll', updatePosition, true)
    window.addEventListener('workintel:overlay-open', closeTransient)
    window.addEventListener('workintel:navigate', closeTransient)
    document.addEventListener('pointerdown', closeTransient, true)
    return () => {
      window.removeEventListener('resize', updatePosition)
      window.removeEventListener('scroll', updatePosition, true)
      window.removeEventListener('workintel:overlay-open', closeTransient)
      window.removeEventListener('workintel:navigate', closeTransient)
      document.removeEventListener('pointerdown', closeTransient, true)
    }
  }, [show, placement])

  /** Handles the open operation for the WorkIntel client. */ const open = () => { updatePosition(); setShow(true) }
  /** Handles the close operation for the WorkIntel client. */ const close = () => setShow(false)

  return <span ref={anchorRef} className="ui-tooltip-anchor" onMouseEnter={open} onMouseLeave={close} onFocus={open} onBlur={close} onPointerDown={close}>
    {children}
    {show && typeof document !== 'undefined' && createPortal(
      <span role="tooltip" className={`ui-tooltip ui-tooltip--${placement}`} style={{ top: position.top, left: position.left }}>{content}</span>,
      document.body,
    )}
  </span>
}

/** Render a shared empty state with optional contextual-help recovery without coupling feature pages to shell internals. */ export function EmptyState({ icon, title, text, action, contextualHelp=false }: { icon?: ReactNode; title: ReactNode; text?: ReactNode; action?: ReactNode; contextualHelp?:boolean }) { const localize=useLocalizedNode(),localization=useOptionalLocalization();/** Ask the global shell to open permission-aware help for the current page. */ const openHelp=()=>window.dispatchEvent(new CustomEvent('workintel:open-help'));return <div className="ui-empty">{icon && <div className="ui-empty__icon">{icon}</div>}<div className="ui-empty__title">{localize(title)}</div>{text && <div className="ui-empty__text">{localize(text)}</div>}{contextualHelp&&!text&&<div className="ui-empty__text">{localization?.t('help.need_context')??'Need help getting started?'}</div>}{(action||contextualHelp)&&<div className="ui-empty__actions">{action}{contextualHelp&&<Button size="sm" variant="outline" onClick={openHelp}><CircleHelp size={13}/>{localization?.t('help.open_context')??'Open contextual help'}</Button>}</div>}</div> }

/** Render a compact, accessible loading state for panels, drawers and async subviews. */
export function LoadingState({title='Loading…',text,compact=false}:{title?:ReactNode;text?:ReactNode;compact?:boolean}){const localize=useLocalizedNode();return <div className={cx('ui-loading-state',compact&&'ui-loading-state--compact')} role="status" aria-live="polite"><LoaderCircle className="ui-loading-state__spinner" size={compact?18:24}/><div><strong>{localize(title)}</strong>{text&&<span>{localize(text)}</span>}</div></div>}

/** Render a reusable recoverable error state instead of page-specific error markup. */
export function ErrorState({title='Something went wrong',text,retry,retryLabel='Try again'}:{title?:ReactNode;text?:ReactNode;retry?:()=>Promise<void>|void;retryLabel?:ReactNode}){const localize=useLocalizedNode();return <div className="ui-error-state" role="alert"><CircleAlert size={24} aria-hidden="true"/><div><strong>{localize(title)}</strong>{text&&<span>{localize(text)}</span>}{retry&&<Button size="sm" variant="outline" onClick={()=>void retry()}><RefreshCw size={13}/>{localize(retryLabel)}</Button>}</div></div>}

/** Standardize list/filter controls into one responsive toolbar with clear semantic zones. */
export function FilterBar({primary,filters,actions,summary,className=''}:{primary?:ReactNode;filters?:ReactNode;actions?:ReactNode;summary?:ReactNode;className?:string}){return <div className={cx('ui-filter-bar',className)} role="search"><div className="ui-filter-bar__main">{primary}{filters}</div>{summary&&<div className="ui-filter-bar__summary">{summary}</div>}{actions&&<div className="ui-filter-bar__actions">{actions}</div>}</div>}

/** Render a consistent inclusive date range control with portal-backed WorkIntel date inputs. */
export function DateRangeField({from,to,onChange,label='Date range',fromLabel='From',toLabel='To',min,max,disabled=false}:{from?:string;to?:string;onChange:(value:{from?:string;to?:string})=>void;label?:ReactNode;fromLabel?:ReactNode;toLabel?:ReactNode;min?:string;max?:string;disabled?:boolean}){const localize=useLocalizedNode();const id=useId();return <fieldset className="ui-date-range" aria-labelledby={`${id}-legend`}><legend id={`${id}-legend`}>{localize(label)}</legend><div className="ui-date-range__fields"><Field label={fromLabel}><Input type="date" value={from??''} min={min} max={to||max} disabled={disabled} onChange={event=>onChange({from:event.target.value||undefined,to})}/></Field><span className="ui-date-range__separator" aria-hidden="true">→</span><Field label={toLabel}><Input type="date" value={to??''} min={from||min} max={max} disabled={disabled} onChange={event=>onChange({from,to:event.target.value||undefined})}/></Field></div></fieldset>}

/** Standardize modal/drawer footer actions including cancel, submit, loading and danger semantics. */
export function DialogActions({onCancel,cancelLabel='Cancel',submitLabel='Save',form,loading=false,disabled=false,danger=false,children}:{onCancel?:()=>void;cancelLabel?:ReactNode;submitLabel?:ReactNode;form?:string;loading?:boolean;disabled?:boolean;danger?:boolean;children?:ReactNode}){const localize=useLocalizedNode();return <div className="ui-dialog-actions">{children}{onCancel&&<Button variant="outline" disabled={loading} onClick={onCancel}>{localize(cancelLabel)}</Button>}<Button type={form?'submit':'button'} form={form} variant={danger?'danger':'primary'} loading={loading} disabled={disabled}>{localize(submitLabel)}</Button></div>}

/** Render a safe confirmation dialog for destructive or consequential actions. */
export function ConfirmDialog({open,onClose,onConfirm,title,description,confirmLabel='Confirm',cancelLabel='Cancel',danger=false,loading=false,children}:{open:boolean;onClose:()=>void;onConfirm:()=>Promise<void>|void;title:ReactNode;description?:ReactNode;confirmLabel?:ReactNode;cancelLabel?:ReactNode;danger?:boolean;loading?:boolean;children?:ReactNode}){return <Modal open={open} onClose={()=>{if(!loading)onClose()}} title={title} description={description} footer={<div className="ui-dialog-actions"><Button variant="outline" disabled={loading} onClick={onClose}>{cancelLabel}</Button><Button variant={danger?'danger':'primary'} loading={loading} onClick={()=>void onConfirm()}>{confirmLabel}</Button></div>}>{children??null}</Modal>}

/** Handles the kbd operation for the WorkIntel client. */ export function Kbd({ children }: { children: ReactNode }) { return <kbd style={{ fontFamily: 'var(--font-mono)', fontSize: 10, padding: '1px 5px', borderRadius: 4, background: 'var(--border)', border: '1px solid var(--border)', color: 'var(--text-3)' }}>{children}</kbd> }


export type DataGridSortDirection = 'asc' | 'desc'
export type DataGridFilterOption = { value:string; label:ReactNode }
export type DataGridFilterConfig = { type:'text'|'select'|'dateRange'; label?:string; options?:DataGridFilterOption[]; placeholder?:string }
export type DataGridColumn<T> = {
  id:string
  header:ReactNode
  cell:(row:T)=>ReactNode
  value?:(row:T)=>unknown
  sortValue?:(row:T)=>string|number|Date|null|undefined
  searchValue?:(row:T)=>string|number|null|undefined
  filterValue?:(row:T)=>string|number|Date|null|undefined
  filter?:DataGridFilterConfig
  sortable?:boolean
  hideable?:boolean
  defaultHidden?:boolean
  className?:string
}
export type DataGridQuery = { page:number; pageSize:number; search:string; sorting:SortingState; filters:ColumnFiltersState }

/** Serialize controlled DataGrid state into the standard WorkIntel list-endpoint query contract. */
export function dataGridQueryParams(query:DataGridQuery):URLSearchParams{
  const params=new URLSearchParams()
  params.set('page',String(Math.max(1,query.page)))
  params.set('per_page',String(Math.max(5,query.pageSize)))
  if(query.search.trim())params.set('search',query.search.trim())
  if(query.sorting.length)params.set('sort',query.sorting.slice(0,3).map(item=>`${item.desc?'-':''}${item.id}`).join(','))
  query.filters.forEach(filter=>{
    const key=filter.id.replace(/[^a-z0-9._-]/gi,'')
    if(!key)return
    const value=filter.value as any
    if(value&&typeof value==='object'&&!Array.isArray(value)){
      Object.entries(value).forEach(([part,item])=>{if(item!==undefined&&item!==null&&String(item)!=='')params.set(`filters[${key}][${part.replace(/[^a-z0-9._-]/gi,'')}]`,String(item))})
    }else if(value!==undefined&&value!==null&&String(value)!=='')params.set(`filters[${key}]`,String(value))
  })
  return params
}
export type DataGridBulkAction<T> = { label:string; icon?:ReactNode; danger?:boolean; onClick:(rows:T[])=>Promise<void>|void }
type DataGridSavedView = { id:string; name:string; state:{ search:string; sorting:SortingState; filters:ColumnFiltersState; visibility:VisibilityState; pageSize:number } }
type DataGridPersistedState = { search?:string; sorting?:SortingState; filters?:ColumnFiltersState; visibility?:VisibilityState; pageSize?:number; savedViews?:DataGridSavedView[] }

/** Return a stable string representation for generic grid filtering and searching. */
function gridPrimitive(value:unknown):string{
  if(value===null||value===undefined)return ''
  if(value instanceof Date)return value.toISOString()
  if(Array.isArray(value))return value.map(gridPrimitive).join(' ')
  if(typeof value==='object')return Object.values(value as Record<string,unknown>).map(gridPrimitive).join(' ')
  return String(value)
}

/** Test a date-like cell value against an inclusive YYYY-MM-DD range. */
function dateRangeFilter(row:any,columnId:string,value:{from?:string;to?:string}){
  const raw=row.getValue(columnId);if(!raw)return false
  const date=raw instanceof Date?raw:new Date(String(raw));if(Number.isNaN(date.getTime()))return false
  const day=`${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`
  return (!value?.from||day>=value.from)&&(!value?.to||day<=value.to)
}

/** Render WorkIntel's TanStack-powered data grid for client-side or controlled server-side datasets. */
export function DataGrid<T>({
  rows,columns,rowKey,empty,filteredEmpty,toolbar,pageSizeOptions=[10,25,50,100],defaultPageSize=25,defaultSort,persistKey,
  searchable=true,searchPlaceholder,loading=false,onRefresh,server,totalRows,onQueryChange,bulkActions=[],mobileCard,ariaLabel,
}:{
  rows:T[];columns:DataGridColumn<T>[];rowKey:(row:T)=>string|number;empty?:ReactNode;filteredEmpty?:ReactNode;toolbar?:ReactNode;
  pageSizeOptions?:number[];defaultPageSize?:number;defaultSort?:{id:string;direction:DataGridSortDirection};persistKey?:string;
  searchable?:boolean;searchPlaceholder?:string;loading?:boolean;onRefresh?:()=>Promise<void>|void;server?:boolean;totalRows?:number;
  onQueryChange?:(query:DataGridQuery)=>void;bulkActions?:DataGridBulkAction<T>[];mobileCard?:(row:T)=>ReactNode;ariaLabel?:string;
}){
  const {session}=useAuth();const {t}=useLocalization();const workspaceId=session?.user.activeWorkspaceId??0;const resolvedAriaLabel=ariaLabel??t('common.data_table')
  const preferenceKey=persistKey?`grid.${persistKey.replace(/[^a-z0-9._-]+/gi,'-').toLowerCase()}`:null
  const [sorting,setSorting]=useState<SortingState>(defaultSort?[{id:defaultSort.id,desc:defaultSort.direction==='desc'}]:[])
  const [columnFilters,setColumnFilters]=useState<ColumnFiltersState>([])
  const [globalFilter,setGlobalFilter]=useState('')
  const [columnVisibility,setColumnVisibility]=useState<VisibilityState>(()=>Object.fromEntries(columns.filter(column=>column.defaultHidden).map(column=>[column.id,false])))
  const [pagination,setPagination]=useState<PaginationState>({pageIndex:0,pageSize:defaultPageSize})
  const [rowSelection,setRowSelection]=useState<RowSelectionState>({})
  const [savedViews,setSavedViews]=useState<DataGridSavedView[]>([])
  const [viewName,setViewName]=useState('')
  const [preferencesReady,setPreferencesReady]=useState(!preferenceKey)
  const saveTimer=useRef<number|null>(null)

  /** Load the current user's saved grid state and named views from backend preferences. */
  useEffect(()=>{
    if(!preferenceKey||!workspaceId){setPreferencesReady(true);return}
    let active=true
    apiRequest<{data:{data_grid?:DataGridPersistedState}}>(`/api/v1/ui/preferences/${encodeURIComponent(preferenceKey)}`,{workspaceId,silent:true})
      .then(response=>{if(!active)return;const state=response.data?.data_grid??{};if(typeof state.search==='string')setGlobalFilter(state.search);if(Array.isArray(state.sorting))setSorting(state.sorting);if(Array.isArray(state.filters))setColumnFilters(state.filters);if(state.visibility&&typeof state.visibility==='object')setColumnVisibility(state.visibility);if(Number.isFinite(state.pageSize))setPagination(current=>({...current,pageSize:Number(state.pageSize)}));if(Array.isArray(state.savedViews))setSavedViews(state.savedViews);setPreferencesReady(true)})
      .catch(()=>{if(active)setPreferencesReady(true)})
    return()=>{active=false}
  },[preferenceKey,workspaceId])

  /** Persist grid preferences after a short debounce so rapid sorting/filtering does not spam the API. */
  useEffect(()=>{
    if(!preferenceKey||!workspaceId||!preferencesReady)return
    if(saveTimer.current)window.clearTimeout(saveTimer.current)
    saveTimer.current=window.setTimeout(()=>{void apiRequest(`/api/v1/ui/preferences/${encodeURIComponent(preferenceKey)}`,{method:'PUT',workspaceId,silent:true,body:JSON.stringify({settings:{data_grid:{search:globalFilter,sorting,filters:columnFilters,visibility:columnVisibility,pageSize:pagination.pageSize,savedViews}}})})},450)
    return()=>{if(saveTimer.current)window.clearTimeout(saveTimer.current)}
  },[preferenceKey,workspaceId,preferencesReady,globalFilter,sorting,columnFilters,columnVisibility,pagination.pageSize,savedViews])

  /** Update sorting and always return paginated datasets to their first page. */
  const changeSorting=(updater:any)=>{setSorting(current=>typeof updater==='function'?updater(current):updater);setPagination(current=>({...current,pageIndex:0}))}
  /** Update column filters and return paginated datasets to their first page. */
  const changeColumnFilters=(updater:any)=>{setColumnFilters(current=>typeof updater==='function'?updater(current):updater);setPagination(current=>({...current,pageIndex:0}))}

  const tanstackColumns=useMemo<ColumnDef<T>[]>(()=>columns.map(column=>({
    id:column.id,
    header:()=>column.header,
    accessorFn:(row:T)=>column.filterValue?.(row)??column.searchValue?.(row)??column.sortValue?.(row)??column.value?.(row)??'',
    cell:info=>column.cell(info.row.original),
    enableSorting:Boolean(column.sortable!==false&&(column.sortValue||column.value||column.searchValue||column.filterValue)),
    enableHiding:column.hideable!==false,
    enableColumnFilter:Boolean(column.filter),
    filterFn:column.filter?.type==='dateRange'?dateRangeFilter:column.filter?.type==='select'?'equalsString':'includesString',
    meta:{className:column.className,filter:column.filter},
  })),[columns])

  const table=useReactTable({
    data:rows,columns:tanstackColumns,getRowId:row=>String(rowKey(row)),
    state:{sorting,columnFilters,globalFilter,columnVisibility,pagination,rowSelection},
    onSortingChange:changeSorting,onColumnFiltersChange:changeColumnFilters,onGlobalFilterChange:setGlobalFilter,onColumnVisibilityChange:setColumnVisibility,onPaginationChange:setPagination,onRowSelectionChange:setRowSelection,
    getCoreRowModel:getCoreRowModel(),getSortedRowModel:server?undefined:getSortedRowModel(),getFilteredRowModel:server?undefined:getFilteredRowModel(),getPaginationRowModel:server?undefined:getPaginationRowModel(),
    manualSorting:Boolean(server),manualFiltering:Boolean(server),manualPagination:Boolean(server),
    rowCount:server?(totalRows??rows.length):undefined,enableRowSelection:bulkActions.length>0,
  })
  const hasFilters=Boolean(globalFilter.trim()||columnFilters.length)
  const selectedRows=table.getSelectedRowModel().rows.map(row=>row.original)
  const total=server?(totalRows??rows.length):table.getFilteredRowModel().rows.length
  const pageCount=Math.max(1,table.getPageCount())
  const currentPage=Math.min(pagination.pageIndex,pageCount-1)
  const first=total?currentPage*pagination.pageSize+1:0
  const last=Math.min(total,(currentPage+1)*pagination.pageSize)

  /** Publish the fully controlled query shape for server-side grid endpoints. */
  useEffect(()=>{if(server)onQueryChange?.({page:pagination.pageIndex+1,pageSize:pagination.pageSize,search:globalFilter,sorting,filters:columnFilters})},[server,onQueryChange,pagination.pageIndex,pagination.pageSize,globalFilter,sorting,columnFilters])
  /** Return pagination buttons centered around the current page without rendering hundreds of controls. */
  const pageButtons=useMemo(()=>{const pages=new Set<number>([0,pageCount-1,currentPage-1,currentPage,currentPage+1]);return [...pages].filter(page=>page>=0&&page<pageCount).sort((a,b)=>a-b)},[currentPage,pageCount])
  /** Remove all filtering and sorting while keeping column visibility and the user's preferred page size. */
  const resetView=()=>{setGlobalFilter('');setColumnFilters([]);setSorting(defaultSort?[{id:defaultSort.id,desc:defaultSort.direction==='desc'}]:[]);setColumnVisibility(Object.fromEntries(columns.filter(column=>column.defaultHidden).map(column=>[column.id,false])));setPagination({pageIndex:0,pageSize:defaultPageSize});setRowSelection({})}
  /** Save the current table state as a reusable named view. */
  const saveView=()=>{const name=viewName.trim();if(!name)return;const id=`view-${Date.now()}`;setSavedViews(current=>[...current.filter(view=>view.name.toLocaleLowerCase()!==name.toLocaleLowerCase()),{id,name,state:{search:globalFilter,sorting,filters:columnFilters,visibility:columnVisibility,pageSize:pagination.pageSize}}].slice(-20));setViewName('')}
  /** Apply a named user view and return to the first page. */
  const applyView=(view:DataGridSavedView)=>{setGlobalFilter(view.state.search);setSorting(view.state.sorting);setColumnFilters(view.state.filters);setColumnVisibility(view.state.visibility);setPagination({pageIndex:0,pageSize:view.state.pageSize});setRowSelection({})}

  const filterableColumns=table.getAllLeafColumns().filter(column=>column.getCanFilter())
  const showToolbar=searchable||toolbar||filterableColumns.length||persistKey||onRefresh||bulkActions.length>0

  return <div className="ui-data-grid-v2 ui-data-grid-v3" data-grid-version="3" aria-busy={loading||undefined} role="region" aria-label={resolvedAriaLabel}>
    {showToolbar&&<div className="ui-data-grid-v2__toolbar">
      <div className="ui-data-grid-v2__toolbar-main">{searchable&&<SearchInput value={globalFilter} onChange={event=>{setGlobalFilter(event.target.value);setPagination(current=>({...current,pageIndex:0}))}} placeholder={searchPlaceholder??t('common.search')} aria-label={t('common.search_table')}/>}{toolbar}</div>
      <div className="ui-data-grid-v2__toolbar-actions">
        {filterableColumns.length>0&&<Popover align="right" trigger={<Button variant={columnFilters.length?'secondary':'outline'} size="sm"><Filter size={13}/> {t('common.filters')}{columnFilters.length?` (${columnFilters.length})`:''}</Button>}><div className="ui-data-grid-v2__filters"><strong>{t('common.filters')}</strong>{filterableColumns.map(column=>{const config=(column.columnDef.meta as any)?.filter as DataGridFilterConfig|undefined;if(!config)return null;const value=column.getFilterValue() as any;return <Field key={column.id} label={config.label??String(column.columnDef.header??column.id)}>{config.type==='select'?<Select value={String(value??'')} onChange={event=>{column.setFilterValue(event.target.value||undefined);setPagination(current=>({...current,pageIndex:0}))}}><option value="">{t('common.all')}</option>{config.options?.map(option=><option key={option.value} value={option.value}>{option.label}</option>)}</Select>:config.type==='dateRange'?<DateRangeField label={config.label??String(column.columnDef.header??column.id)} from={value?.from} to={value?.to} onChange={next=>{column.setFilterValue(next.from||next.to?next:undefined);setPagination(current=>({...current,pageIndex:0}))}}/>:<Input value={String(value??'')} placeholder={config.placeholder??t('common.filters')} onChange={event=>column.setFilterValue(event.target.value||undefined)}/>}</Field>})}<Button size="sm" variant="ghost" onClick={()=>setColumnFilters([])} disabled={!columnFilters.length}><RotateCcw size={13}/> {t('common.clear_filters')}</Button></div></Popover>}
        {persistKey&&<Popover align="right" trigger={<Button variant="outline" size="sm"><Bookmark size={13}/> {t('common.views')}</Button>}><div className="ui-data-grid-v2__views"><strong>{t('common.saved_views')}</strong>{savedViews.map(view=><div key={view.id} className="ui-data-grid-v2__saved-view"><button type="button" onClick={()=>applyView(view)}>{view.name}</button><IconButton size="sm" variant="ghost" aria-label={`Delete ${view.name} view`} onClick={()=>setSavedViews(current=>current.filter(item=>item.id!==view.id))}><Trash2 size={12}/></IconButton></div>)}{!savedViews.length&&<span className="ui-card-description">{t('common.no_saved_views')}</span>}<div className="ui-data-grid-v2__save-view"><Input value={viewName} onChange={event=>setViewName(event.target.value)} placeholder={t('common.view_name')}/><Button size="sm" onClick={saveView} disabled={!viewName.trim()}><BookmarkPlus size={13}/> {t('common.save')}</Button></div></div></Popover>}
        <Popover align="right" trigger={<Button variant="outline" size="sm"><Columns3 size={13}/> {t('common.columns')}</Button>}><div className="ui-data-grid__columns"><strong>Visible columns</strong>{table.getAllLeafColumns().filter(column=>column.getCanHide()).map(column=>{const source=columns.find(item=>item.id===column.id);return <label key={column.id}><input type="checkbox" checked={column.getIsVisible()} onChange={column.getToggleVisibilityHandler()}/><span>{source?.header??column.id}</span></label>})}</div></Popover>
        {(hasFilters||sorting.length>0)&&<IconButton size="sm" variant="ghost" aria-label={t('common.reset_table')} onClick={resetView}><RotateCcw size={13}/></IconButton>}
        {onRefresh&&<RefreshButton onRefresh={onRefresh} label={t('common.refresh')}/>}
      </div>
    </div>}

    {selectedRows.length>0&&<div className="ui-data-grid-v2__bulk"><strong>{t('common.selected',{count:selectedRows.length})}</strong><div>{bulkActions.map(action=><Button key={action.label} size="sm" variant={action.danger?'danger':'outline'} onClick={()=>void action.onClick(selectedRows)}>{action.icon}{action.label}</Button>)}<Button size="sm" variant="ghost" onClick={()=>setRowSelection({})}>{t('common.clear')}</Button></div></div>}

    {columnFilters.length>0&&<div className="ui-data-grid-v2__chips">{columnFilters.map(filter=>{const column=columns.find(item=>item.id===filter.id);const label=column?.filter?.label??String(column?.header??filter.id);const value=typeof filter.value==='object'&&filter.value?`${(filter.value as any).from??'…'} → ${(filter.value as any).to??'…'}`:String(filter.value);return <button key={filter.id} type="button" onClick={()=>table.getColumn(filter.id)?.setFilterValue(undefined)}>{label}: {value}<X size={11}/></button>})}</div>}

    <div className="ui-data-grid-v2__desktop"><TableWrap label={resolvedAriaLabel} tableProps={{'aria-rowcount':total+1,'aria-colcount':table.getVisibleLeafColumns().length+(bulkActions.length>0?1:0)}}><thead>{table.getHeaderGroups().map(group=><tr key={group.id}>{bulkActions.length>0&&<th scope="col" className="ui-data-grid-v2__select"><input type="checkbox" aria-label="Select all visible rows" checked={table.getIsAllPageRowsSelected()} ref={node=>{if(node)node.indeterminate=table.getIsSomePageRowsSelected()}} onChange={table.getToggleAllPageRowsSelectedHandler()}/></th>}{group.headers.map(header=><th key={header.id} scope="col" aria-sort={header.column.getIsSorted()==='asc'?'ascending':header.column.getIsSorted()==='desc'?'descending':header.column.getCanSort()?'none':undefined} className={(header.column.columnDef.meta as any)?.className}>{header.isPlaceholder?null:header.column.getCanSort()?<button type="button" className="ui-data-grid__sort" aria-label={`Sort ${String(header.column.columnDef.header??header.id)}${header.column.getIsSorted()?` ${header.column.getIsSorted()}`:''}`} onClick={header.column.getToggleSortingHandler()}>{flexRender(header.column.columnDef.header,header.getContext())}{header.column.getIsSorted()==='asc'?<ArrowUp size={12} aria-hidden="true"/>:header.column.getIsSorted()==='desc'?<ArrowDown size={12} aria-hidden="true"/>:<span className="ui-data-grid__sort-placeholder" aria-hidden="true"/>}</button>:flexRender(header.column.columnDef.header,header.getContext())}</th>)}</tr>)}</thead><tbody>{loading?Array.from({length:Math.min(8,pagination.pageSize)}).map((_,index)=><tr key={`skeleton-${index}`} className="ui-data-grid-v2__skeleton">{bulkActions.length>0&&<td/>}{table.getVisibleLeafColumns().map(column=><td key={column.id}><span/></td>)}</tr>):table.getRowModel().rows.map(row=><tr key={row.id}>{bulkActions.length>0&&<td className="ui-data-grid-v2__select"><input type="checkbox" aria-label={`Select row ${row.id}`} checked={row.getIsSelected()} onChange={row.getToggleSelectedHandler()}/></td>}{row.getVisibleCells().map(cell=><td key={cell.id} className={(cell.column.columnDef.meta as any)?.className}>{flexRender(cell.column.columnDef.cell,cell.getContext())}</td>)}</tr>)}</tbody></TableWrap></div>
    {mobileCard&&<div className="ui-data-grid-v2__mobile">{loading?Array.from({length:4}).map((_,index)=><div key={index} className="ui-data-grid-v2__mobile-skeleton"/>):table.getRowModel().rows.map(row=><div key={row.id} className="ui-data-grid-v2__mobile-row">{bulkActions.length>0&&<input type="checkbox" aria-label={`Select row ${row.id}`} checked={row.getIsSelected()} onChange={row.getToggleSelectedHandler()}/>}<div>{mobileCard(row.original)}</div></div>)}</div>}
    {!loading&&!table.getRowModel().rows.length&&<div className="ui-data-grid__empty">{hasFilters?(filteredEmpty??<EmptyState title={t('common.no_matching_records')} text={t('common.no_matching_help')} action={<Button size="sm" variant="outline" onClick={resetView}>{t('common.clear_filters')}</Button>}/>):(empty??<EmptyState title={t('common.no_records')} text={t('common.no_records_help')}/>)}</div>}

    <div className="ui-data-grid-v2__footer"><span role="status" aria-live="polite">{t('common.showing',{first,last,total})}</span><div className="ui-data-grid-v2__pagination"><Select aria-label={t('common.rows_per_page')} value={String(pagination.pageSize)} onChange={event=>setPagination({pageIndex:0,pageSize:Number(event.target.value)})}>{pageSizeOptions.map(size=><option key={size} value={size}>{size} / page</option>)}</Select><IconButton size="sm" variant="outline" aria-label={t('common.previous_page')} disabled={!table.getCanPreviousPage()} onClick={()=>table.previousPage()}><ChevronLeft size={13}/></IconButton>{pageButtons.map((page,index)=><span key={page} className="ui-data-grid-v2__page-wrap">{index>0&&page-pageButtons[index-1]>1&&<i>…</i>}<button type="button" aria-label={`Page ${page+1}`} aria-current={page===currentPage?'page':undefined} className={page===currentPage?'is-active':''} onClick={()=>table.setPageIndex(page)}>{page+1}</button></span>)}<IconButton size="sm" variant="outline" aria-label={t('common.next_page')} disabled={!table.getCanNextPage()} onClick={()=>table.nextPage()}><ChevronRight size={13}/></IconButton></div></div>
  </div>
}
