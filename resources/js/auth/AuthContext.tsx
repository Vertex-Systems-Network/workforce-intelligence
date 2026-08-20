import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { authService } from './authService'
import type { AuthSession, LoginInput, RegisterInput } from './types'

type AuthContextValue = {
  session: AuthSession | null
  isAuthenticated: boolean
  isReady: boolean
  login: (input: LoginInput) => Promise<void>
  register: (input: RegisterInput) => Promise<void>
  changePassword: (input:{currentPassword:string;password:string;passwordConfirmation:string}) => Promise<void>
  refreshSession: () => Promise<void>
  logout: () => Promise<void>
  switchWorkspace: (workspaceId: number) => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

/** Handles the auth provider operation for the WorkIntel client. */ export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<AuthSession | null>(() => authService.restore())
  const [isReady, setIsReady] = useState(authService.mode === 'demo')

  useEffect(() => {
    if (authService.mode !== 'laravel') return

    let active = true
    let validationId = 0

    /** Revalidate the server-backed session before revealing a restored private browser snapshot. */
    const validateSession = async (hideUntilValidated = false) => {
      const currentValidation = ++validationId
      if (hideUntilValidated) {
        setSession(null)
        setIsReady(false)
      }
      const restored = await authService.restoreFromApi()
      if (!active || currentValidation !== validationId) return
      setSession(restored)
      setIsReady(true)
      document.documentElement.removeAttribute('data-workintel-private-snapshot')
    }

    void validateSession()

    /** Force a server auth check when the browser restores this document from back/forward cache. */
    const handlePageShow = (event: PageTransitionEvent) => {
      const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming | undefined
      if (event.persisted || navigation?.type === 'back_forward') void validateSession(true)
      else document.documentElement.removeAttribute('data-workintel-private-snapshot')
    }
    /** Drop sensitive client state as soon as any API request reports an invalid server session. */
    const handleInvalidated = () => {
      validationId += 1
      setSession(null)
      setIsReady(true)
      document.documentElement.removeAttribute('data-workintel-private-snapshot')
    }
    /** Synchronize logout across tabs sharing the same Laravel session cookie. */
    const handleStorage = (event: StorageEvent) => {
      if (event.key === 'workintel-auth-invalidated-at') handleInvalidated()
    }

    window.addEventListener('pageshow', handlePageShow)
    window.addEventListener('workintel:auth-invalidated', handleInvalidated)
    window.addEventListener('storage', handleStorage)
    return () => {
      active = false
      validationId += 1
      window.removeEventListener('pageshow', handlePageShow)
      window.removeEventListener('workintel:auth-invalidated', handleInvalidated)
      window.removeEventListener('storage', handleStorage)
    }
  }, [])


  useEffect(() => {
    if (authService.mode !== 'laravel') return
    /** Handles the refresh operation for the WorkIntel client. */ const refresh = () => { void authService.restoreFromApi().then(restored => { if (restored) setSession(restored) }) }
    window.addEventListener('workintel:subscription-changed', refresh)
    window.addEventListener('workintel:permissions-changed', refresh)
    window.addEventListener('workintel:modules-changed', refresh)
    return () => {
      window.removeEventListener('workintel:subscription-changed', refresh)
      window.removeEventListener('workintel:permissions-changed', refresh)
      window.removeEventListener('workintel:modules-changed', refresh)
    }
  }, [])


  useEffect(() => {
    const workspace = session?.user.workspaces.find(item => item.id === session.user.activeWorkspaceId) ?? session?.user.workspaces[0]
    const accent = workspace?.settings?.accentColor
    if (accent && /^#[0-9A-Fa-f]{6}$/.test(accent)) {
      document.documentElement.style.setProperty('--accent', accent)
      const r = parseInt(accent.slice(1,3),16), g = parseInt(accent.slice(3,5),16), b = parseInt(accent.slice(5,7),16)
      document.documentElement.style.setProperty('--accent-dim', `rgba(${r}, ${g}, ${b}, 0.12)`)
      document.documentElement.style.setProperty('--accent-glow', `rgba(${r}, ${g}, ${b}, 0.25)`)
    }
    document.documentElement.dataset.sidebarDensity = workspace?.settings?.sidebarDensity ?? 'comfortable'
    if (workspace?.settings?.defaultTheme) window.dispatchEvent(new CustomEvent('workintel:workspace-theme',{detail:{theme:workspace.settings.defaultTheme}}))
    if (workspace?.settings?.faviconUrl) {
      let favicon = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
      if (!favicon) { favicon = document.createElement('link'); favicon.rel='icon'; document.head.appendChild(favicon) }
      favicon.href = workspace.settings.faviconUrl
    }
    document.title = workspace?.settings?.appTitle || workspace?.name || 'WorkIntel'
  }, [session])

  const value = useMemo<AuthContextValue>(() => ({
    session,
    isAuthenticated: Boolean(session),
    isReady,
    /** Handles the login operation for the WorkIntel client. */ async login(input) {
      const result = await authService.login(input)
      setSession(result.session)
    },
    /** Handles the register operation for the WorkIntel client. */ async register(input) {
      const result = await authService.register(input)
      setSession(result.session)
    },
    /** Handles the change password operation for the WorkIntel client. */ async changePassword(input) {
      const restored = await authService.changePassword(input)
      setSession(restored)
    },
    /** Handles the refresh session operation for the WorkIntel client. */ async refreshSession() {
      const restored = await authService.restoreFromApi()
      setSession(restored)
    },
    /** End the server session and immediately remove protected client state from the current tab. */ async logout() {
      setSession(null)
      setIsReady(true)
      window.localStorage.setItem('workintel-auth-invalidated-at', String(Date.now()))
      window.dispatchEvent(new CustomEvent('workintel:auth-invalidated'))
      await authService.logout()
      window.history.replaceState({ ...(window.history.state ?? {}), workintelLoggedOut: true }, '', window.location.pathname + window.location.search)
    },
    /** Handles the switch workspace operation for the WorkIntel client. */ switchWorkspace(workspaceId) {
      setSession(current => {
        if (!current || !current.user.workspaces.some(workspace => workspace.id === workspaceId)) return current
        const updated = {
          ...current,
          user: { ...current.user, activeWorkspaceId: workspaceId },
        }
        if (authService.mode === 'demo') {
          window.localStorage.setItem('workintel-demo-session', JSON.stringify(updated))
        }
        return updated
      })
    },
  }), [session, isReady])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

/** Handles the use auth operation for the WorkIntel client. */ export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used inside AuthProvider')
  return context
}
