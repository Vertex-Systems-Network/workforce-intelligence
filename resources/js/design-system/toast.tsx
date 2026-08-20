import { useEffect, useState } from 'react'
import { AlertCircle, CheckCircle2, Info, TriangleAlert, X } from 'lucide-react'

export type ToastTone = 'success' | 'danger' | 'warning' | 'info'
export type ToastPayload = { id?: string; tone?: ToastTone; title: string; message?: string; duration?: number }

type ToastRow = Required<Pick<ToastPayload, 'id' | 'tone' | 'title' | 'duration'>> & Pick<ToastPayload, 'message'>

/** Publish a transient, dismissible application notification without coupling callers to React context. */
export function emitToast(payload: ToastPayload) {
  if (typeof window === 'undefined') return
  window.dispatchEvent(new CustomEvent<ToastPayload>('workintel:toast', { detail: payload }))
}

/** Render transient notifications with close controls and automatic expiry. */
export function ToastViewport() {
  const [rows, setRows] = useState<ToastRow[]>([])

  useEffect(() => {
    /** Add one toast and cap the visible notification stack. */
    const onToast = (event: Event) => {
      const payload = (event as CustomEvent<ToastPayload>).detail
      if (!payload?.title) return
      const row: ToastRow = {
        id: payload.id ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        tone: payload.tone ?? 'info',
        title: payload.title,
        message: payload.message,
        duration: payload.duration ?? (payload.tone === 'danger' ? 7000 : 4500),
      }
      setRows(current => [...current.filter(item => item.id !== row.id), row].slice(-5))
    }
    window.addEventListener('workintel:toast', onToast)
    return () => window.removeEventListener('workintel:toast', onToast)
  }, [])

  useEffect(() => {
    if (!rows.length) return
    const timers = rows.map(row => window.setTimeout(() => {
      setRows(current => current.filter(item => item.id !== row.id))
    }, row.duration))
    return () => timers.forEach(timer => window.clearTimeout(timer))
  }, [rows])

  const icons = { success: CheckCircle2, danger: AlertCircle, warning: TriangleAlert, info: Info }
  return <div className="ui-toast-viewport" aria-live="polite" aria-atomic="false">
    {rows.map(row => {
      const Icon = icons[row.tone]
      return <section key={row.id} className={`ui-toast ui-toast--${row.tone}`} role={row.tone === 'danger' ? 'alert' : 'status'}>
        <span className="ui-toast__icon"><Icon size={17}/></span>
        <div className="ui-toast__content"><strong>{row.title}</strong>{row.message && <p>{row.message}</p>}</div>
        <button type="button" className="ui-toast__close" aria-label="Dismiss notification" onClick={() => setRows(current => current.filter(item => item.id !== row.id))}><X size={14}/></button>
        <span className="ui-toast__timer" style={{ animationDuration: `${row.duration}ms` }}/>
      </section>
    })}
  </div>
}
