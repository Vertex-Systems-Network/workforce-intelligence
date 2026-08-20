import { createContext, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { RotateCcw, SlidersHorizontal } from 'lucide-react'
import { apiRequest } from '../api/client'
import { emitToast } from './toast'
import { Button, Drawer, Field, Select, Switch } from './index'

export type PageCustomizationSettings = {
  density: 'comfortable' | 'compact'
  content_width: 'full' | 'balanced' | 'narrow'
  motion: 'full' | 'reduced' | 'off'
  table_density: 'comfortable' | 'compact'
  sticky_header: boolean
  show_descriptions: boolean
  visible_widgets?: string[]
  widget_layout?: Array<{ id: string; x?: number; y?: number; w?: number; h?: number }>
}

type ContextValue = {
  page: string
  loading: boolean
  saving: boolean
  settings: PageCustomizationSettings
  updateSettings: (patch: Partial<PageCustomizationSettings>) => void
  resetSettings: () => Promise<void>
}

const tablePages = new Set(['people','projects','tasks','clients','time','activity','screenshots','attendance','schedule','shifts','leave','payroll','reports','documents','devices','approvals','access','organization','hris','performance','finance','payroll-compliance','field','enterprise','automations'])
const PageCustomizationContext = createContext<ContextValue | null>(null)

/** Return stable product defaults for a page before a user-specific preference exists. */
function defaultsFor(page: string): PageCustomizationSettings {
  return {
    density: 'comfortable',
    content_width: ['chat','tasks','schedule'].includes(page) ? 'full' : 'balanced',
    motion: 'full',
    table_density: tablePages.has(page) ? 'compact' : 'comfortable',
    sticky_header: false,
    show_descriptions: true,
  }
}

/** Provide persistent per-user page customization and apply its visual attributes to the current page. */
export function PageCustomizationShell({ page, workspaceId, children }: { page: string; workspaceId: number; children: ReactNode }) {
  const defaults = useMemo(() => defaultsFor(page), [page])
  const [settings, setSettings] = useState<PageCustomizationSettings>(defaults)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState(false)
  const [open, setOpen] = useState(false)
  const saveTimer = useRef<number | null>(null)

  useEffect(() => {
    let active = true
    if (saveTimer.current) window.clearTimeout(saveTimer.current)
    saveTimer.current = null
    setSaveError(false)
    setLoading(true)
    setSettings(defaults)
    apiRequest<{ data: Partial<PageCustomizationSettings> }>(`/api/v1/ui/preferences/${encodeURIComponent(page)}`, { workspaceId, silent: true })
      .then(payload => { if (active) setSettings({ ...defaults, ...(payload.data ?? {}) }) })
      .catch(() => { if (active) setSettings(defaults) })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [page, workspaceId, defaults])

  useEffect(() => {
    /** Open the page-specific customization panel from the application top bar. */
    const onOpen = () => setOpen(true)
    window.addEventListener('workintel:customize-page', onOpen)
    return () => window.removeEventListener('workintel:customize-page', onOpen)
  }, [])

  useEffect(() => () => { if (saveTimer.current) window.clearTimeout(saveTimer.current) }, [])

  /** Merge a customization patch locally and persist it with a short debounce. */
  const updateSettings = (patch: Partial<PageCustomizationSettings>) => {
    setSettings(current => {
      const next = { ...current, ...patch }
      if (saveTimer.current) window.clearTimeout(saveTimer.current)
      saveTimer.current = window.setTimeout(async () => {
        setSaving(true)
        try {
          await apiRequest(`/api/v1/ui/preferences/${encodeURIComponent(page)}`, {
            method: 'PUT', workspaceId, silent: true, body: JSON.stringify({ settings: next }),
          })
          setSaveError(false)
        } catch (error) {
          setSaveError(true)
          emitToast({ tone: 'danger', title: 'Customization was not saved', message: error instanceof Error ? error.message : 'Please retry after checking your connection.' })
        } finally { setSaving(false) }
      }, 450)
      return next
    })
  }

  /** Delete the user's page override and restore the product defaults. */
  const resetSettings = async () => {
    if (saveTimer.current) window.clearTimeout(saveTimer.current)
    setSaving(true)
    try {
      await apiRequest(`/api/v1/ui/preferences/${encodeURIComponent(page)}`, { method: 'DELETE', workspaceId, silent: true })
      setSettings(defaults)
      setSaveError(false)
      emitToast({ tone: 'success', title: 'Page customization reset' })
    } catch (error) {
      setSaveError(true)
      emitToast({ tone: 'danger', title: 'Could not reset customization', message: error instanceof Error ? error.message : 'Please retry.' })
    } finally { setSaving(false) }
  }

  const value: ContextValue = { page, loading, saving, settings, updateSettings, resetSettings }
  return <PageCustomizationContext.Provider value={value}>
    <div
      className="wi-page-customization-shell"
      data-page={page}
      data-density={settings.density}
      data-content-width={settings.content_width}
      data-motion={settings.motion}
      data-table-density={settings.table_density}
      data-sticky-header={settings.sticky_header ? 'true' : 'false'}
      data-show-descriptions={settings.show_descriptions ? 'true' : 'false'}
    >{children}</div>
    <Drawer open={open} onClose={() => setOpen(false)} title={<span className="ui-customize-title"><SlidersHorizontal size={15}/> Customize this page</span>} description="Saved only for your account in this workspace." footer={<div className="ui-customize-footer"><span>{loading ? 'Loading…' : saving ? 'Saving…' : saveError ? 'Save failed — retry a change' : 'Saved automatically'}</span><Button size="sm" variant="outline" onClick={() => void resetSettings()} disabled={saving}><RotateCcw size={13}/> Reset</Button></div>}>
      <div className="ui-customize-form">
        <Field label="Content width" hint="Controls how wide this page can expand on large displays."><Select value={settings.content_width} onChange={event => updateSettings({ content_width: event.target.value as PageCustomizationSettings['content_width'] })}><option value="full">Full width</option><option value="balanced">Balanced</option><option value="narrow">Focused / narrow</option></Select></Field>
        <Field label="Interface density"><Select value={settings.density} onChange={event => updateSettings({ density: event.target.value as PageCustomizationSettings['density'] })}><option value="comfortable">Comfortable</option><option value="compact">Compact</option></Select></Field>
        {tablePages.has(page) && <Field label="Table density"><Select value={settings.table_density} onChange={event => updateSettings({ table_density: event.target.value as PageCustomizationSettings['table_density'] })}><option value="comfortable">Comfortable rows</option><option value="compact">Compact rows</option></Select></Field>}
        <Field label="Motion"><Select value={settings.motion} onChange={event => updateSettings({ motion: event.target.value as PageCustomizationSettings['motion'] })}><option value="full">Smooth</option><option value="reduced">Reduced</option><option value="off">Off</option></Select></Field>
        <div className="ui-customize-toggle"><div><strong>Sticky page header</strong><small>Keep page title/actions visible while scrolling.</small></div><Switch checked={settings.sticky_header} onChange={checked => updateSettings({ sticky_header: checked })} label="Sticky page header"/></div>
        <div className="ui-customize-toggle"><div><strong>Descriptions</strong><small>Show explanatory text below page and card titles.</small></div><Switch checked={settings.show_descriptions} onChange={checked => updateSettings({ show_descriptions: checked })} label="Show descriptions"/></div>
        {page === 'overview' && <div className="ui-customize-note">Dashboard widget visibility and layout are managed from the <strong>Manage widgets</strong> control on the dashboard itself. They use this same user preference record.</div>}
      </div>
    </Drawer>
  </PageCustomizationContext.Provider>
}

/** Return the current page customization state to dashboard and other page-specific components. */
export function usePageCustomization(): ContextValue {
  const value = useContext(PageCustomizationContext)
  if (!value) throw new Error('usePageCustomization must be used inside PageCustomizationShell.')
  return value
}
