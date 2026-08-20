import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { Eye, EyeOff, LayoutDashboard, RotateCcw, Settings2 } from 'lucide-react'
import { GridStack, type GridStackNode } from 'gridstack'
import 'gridstack/dist/gridstack.min.css'
import { Button, Drawer, Switch } from '../design-system'
import { usePageCustomization } from '../design-system/PageCustomization'

type GridItem = {
  id: string
  title: string
  description?: string
  content: ReactNode
  w?: number
  h?: number
  minW?: number
  minH?: number
  defaultVisible?: boolean
}

/** Render a user-configurable GridStack dashboard with persisted visibility and layout. */
export default function DashboardGrid({ items }: { items: GridItem[] }) {
  const ref = useRef<HTMLDivElement | null>(null)
  const gridRef = useRef<GridStack | null>(null)
  const { settings, updateSettings } = usePageCustomization()
  const [editing, setEditing] = useState(false)
  const [managerOpen, setManagerOpen] = useState(false)
  const [resetVersion, setResetVersion] = useState(0)

  const defaults = useMemo(() => items.filter(item => item.defaultVisible !== false).map(item => item.id), [items])
  const visibleIds = settings.visible_widgets !== undefined ? settings.visible_widgets : defaults
  const visibleItems = items.filter(item => visibleIds.includes(item.id))
  const savedLayout = settings.widget_layout ?? []
  const visibilitySignature = visibleItems.map(item => item.id).join('|')
  const layoutSignature = savedLayout.map(item => `${item.id}:${item.x}:${item.y}:${item.w}:${item.h}`).join('|')

  useEffect(() => {
    if (!ref.current) return
    const grid = GridStack.init({
      column: 12,
      cellHeight: 72,
      margin: 10,
      float: true,
      animate: true,
      disableDrag: !editing,
      disableResize: !editing,
      draggable: { handle: '.dashboard-widget-handle' },
    }, ref.current)
    if (!grid) return
    gridRef.current = grid

    /** Persist the current GridStack coordinates into the user's overview preference. */
    const save = () => {
      if (!editing) return
      const layout = grid.save(false, false) as GridStackNode[]
      updateSettings({ widget_layout: layout.map(({ x, y, w, h, id }) => ({ id: String(id ?? ''), x, y, w, h })).filter(item => item.id) })
    }
    grid.on('dragstop resizestop', save)
    return () => {
      grid.off('dragstop resizestop')
      grid.destroy(false)
      gridRef.current = null
    }
  }, [visibilitySignature, layoutSignature, editing, resetVersion])

  /** Toggle one dashboard widget and persist the visibility list for this user. */
  const toggleWidget = (id: string, enabled: boolean) => {
    const next = enabled ? Array.from(new Set([...visibleIds, id])) : visibleIds.filter(item => item !== id)
    updateSettings({ visible_widgets: next })
  }

  /** Restore the product's recommended widgets and default GridStack coordinates. */
  const resetDashboard = () => {
    updateSettings({ visible_widgets: defaults, widget_layout: [] })
    setEditing(false)
    setResetVersion(value => value + 1)
  }

  return <section className="dashboard-builder">
    <div className="dashboard-builder__toolbar">
      <div><strong>Dashboard widgets</strong><span>{visibleItems.length} of {items.length} visible</span></div>
      <div className="dashboard-builder__actions">
        <Button size="sm" variant="outline" onClick={() => setManagerOpen(true)}><Settings2 size={13}/> Manage widgets</Button>
        <Button size="sm" variant={editing ? 'primary' : 'outline'} onClick={() => setEditing(value => !value)}><LayoutDashboard size={13}/> {editing ? 'Done editing' : 'Edit layout'}</Button>
        <Button size="sm" variant="ghost" onClick={resetDashboard}><RotateCcw size={13}/> Reset</Button>
      </div>
    </div>

    {!visibleItems.length && <div className="dashboard-builder__empty"><LayoutDashboard size={22}/><strong>No dashboard widgets are visible.</strong><span>Use Manage widgets to enable the information you need.</span><Button size="sm" onClick={() => setManagerOpen(true)}>Manage widgets</Button></div>}

    <div ref={ref} className={`grid-stack workintel-dashboard-grid${editing ? ' is-editing' : ''}`}>
      {visibleItems.map(item => {
        const layout = savedLayout.find(row => row.id === item.id)
        return <div
          key={item.id}
          className="grid-stack-item"
          gs-id={item.id}
          gs-x={layout?.x}
          gs-y={layout?.y}
          gs-w={layout?.w ?? item.w ?? 6}
          gs-h={layout?.h ?? item.h ?? 3}
          gs-min-w={item.minW ?? 2}
          gs-min-h={item.minH ?? 2}
        ><div className="grid-stack-item-content"><div className="dashboard-widget-handle" aria-hidden={!editing}><span/><span/><span/><span/><span/><span/></div>{item.content}</div></div>
      })}
    </div>

    <Drawer open={managerOpen} onClose={() => setManagerOpen(false)} title="Manage dashboard widgets" description="Only essential widgets are enabled by default. Your choices are saved to your account.">
      <div className="dashboard-widget-manager">{items.map(item => {
        const enabled = visibleIds.includes(item.id)
        return <div key={item.id} className="dashboard-widget-manager__row"><span className={enabled ? 'is-visible' : ''}>{enabled ? <Eye size={14}/> : <EyeOff size={14}/>}</span><div><strong>{item.title}</strong><small>{item.description ?? (item.defaultVisible === false ? 'Optional dashboard widget' : 'Recommended dashboard widget')}</small></div><Switch checked={enabled} onChange={checked => toggleWidget(item.id, checked)} label={`Show ${item.title}`}/></div>
      })}</div>
    </Drawer>
  </section>
}
