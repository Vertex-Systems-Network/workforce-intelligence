import { useEffect, useRef, type RefObject } from 'react'

/** Selector for elements that can participate in keyboard focus order inside an accessible surface. */
export const FOCUSABLE_SELECTOR = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
  '[contenteditable="true"]',
].join(',')

/** Return visible, enabled focus targets from a dialog, drawer or other keyboard-managed surface. */
export function focusableElements(container: HTMLElement | null): HTMLElement[] {
  if (!container) return []
  return Array.from(container.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)).filter(element => {
    if (element.getAttribute('aria-hidden') === 'true') return false
    const style = window.getComputedStyle(element)
    return style.display !== 'none' && style.visibility !== 'hidden' && element.getClientRects().length > 0
  })
}

/** Keep keyboard focus inside a modal surface, close on Escape and restore focus to the opener. */
export function useFocusTrap(
  open: boolean,
  containerRef: RefObject<HTMLElement | null>,
  options: { onEscape?: () => void; initialFocusRef?: RefObject<HTMLElement | null> } = {},
): void {
  const returnFocusRef = useRef<HTMLElement | null>(null)
  const escapeRef = useRef(options.onEscape)
  const initialFocusRef = useRef(options.initialFocusRef)
  escapeRef.current = options.onEscape
  initialFocusRef.current = options.initialFocusRef

  useEffect(() => {
    if (!open || typeof document === 'undefined') return
    returnFocusRef.current = document.activeElement instanceof HTMLElement ? document.activeElement : null
    const frame = window.requestAnimationFrame(() => {
      const container = containerRef.current
      const requested = initialFocusRef.current?.current
      const target = requested ?? focusableElements(container)[0] ?? container
      target?.focus({ preventScroll: true })
    })

    /** Trap Tab navigation, close with Escape, and never allow focus to escape behind a modal surface. */
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && escapeRef.current) {
        event.preventDefault()
        event.stopPropagation()
        escapeRef.current()
        return
      }
      if (event.key !== 'Tab') return
      const container = containerRef.current
      const targets = focusableElements(container)
      if (!container || !targets.length) {
        event.preventDefault()
        container?.focus({ preventScroll: true })
        return
      }
      const first = targets[0]
      const last = targets[targets.length - 1]
      const active = document.activeElement
      if (event.shiftKey && (active === first || !container.contains(active))) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && (active === last || !container.contains(active))) {
        event.preventDefault()
        first.focus()
      }
    }

    document.addEventListener('keydown', onKeyDown, true)
    return () => {
      window.cancelAnimationFrame(frame)
      document.removeEventListener('keydown', onKeyDown, true)
      const returnTarget = returnFocusRef.current
      window.requestAnimationFrame(() => {
        if (returnTarget?.isConnected) returnTarget.focus({ preventScroll: true })
      })
    }
  }, [open, containerRef])
}

/** Move focus to the first usable control inside a newly opened non-modal portal. */
export function focusFirstPortalControl(container: HTMLElement | null): void {
  const target = focusableElements(container)[0]
  target?.focus({ preventScroll: true })
}
