import { createContext, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'

export type ThemeMode = 'light' | 'dark'
type WorkspaceTheme = 'system' | ThemeMode

type ThemeContextValue = {
  theme: ThemeMode
  setTheme: (theme: ThemeMode) => void
  toggleTheme: () => void
  clearThemePreference: () => void
  hasExplicitPreference: boolean
}

const ThemeContext = createContext<ThemeContextValue | null>(null)
const STORAGE_KEY = 'workintel-theme'

/** Handles the system theme operation for the WorkIntel client. */ function systemTheme(): ThemeMode {
  if (typeof window === 'undefined') return 'dark'
  return window.matchMedia?.('(prefers-color-scheme: light)').matches ? 'light' : 'dark'
}

/** Handles the stored theme operation for the WorkIntel client. */ function storedTheme(): ThemeMode | null {
  if (typeof window === 'undefined') return null
  const stored = window.localStorage.getItem(STORAGE_KEY)
  return stored === 'light' || stored === 'dark' ? stored : null
}

/** Handles the theme provider operation for the WorkIntel client. */ export function ThemeProvider({ children }: { children: ReactNode }) {
  const initial = storedTheme()
  const [theme, setThemeState] = useState<ThemeMode>(initial ?? systemTheme())
  const [hasExplicitPreference, setExplicit] = useState(Boolean(initial))
  const explicitRef = useRef(Boolean(initial))

  useEffect(() => { explicitRef.current = hasExplicitPreference }, [hasExplicitPreference])

  useEffect(() => {
    document.documentElement.dataset.theme = theme
    document.documentElement.style.colorScheme = theme
  }, [theme])

  useEffect(() => {
    /** Handles the apply workspace default operation for the WorkIntel client. */ const applyWorkspaceDefault = (event: Event) => {
      if (explicitRef.current) return
      const value = (event as CustomEvent<{ theme?: WorkspaceTheme }>).detail?.theme
      if (value === 'light' || value === 'dark') setThemeState(value)
      else if (value === 'system') setThemeState(systemTheme())
    }
    window.addEventListener('workintel:workspace-theme', applyWorkspaceDefault)
    return () => window.removeEventListener('workintel:workspace-theme', applyWorkspaceDefault)
  }, [])

  const value = useMemo<ThemeContextValue>(() => ({
    theme,
    hasExplicitPreference,
    /** Updates set theme state for the current workflow. */ setTheme(next) {
      setThemeState(next)
      setExplicit(true)
      window.localStorage.setItem(STORAGE_KEY, next)
    },
    /** Handles the toggle theme operation for the WorkIntel client. */ toggleTheme() {
      const next = theme === 'dark' ? 'light' : 'dark'
      setThemeState(next)
      setExplicit(true)
      window.localStorage.setItem(STORAGE_KEY, next)
    },
    /** Handles the clear theme preference operation for the WorkIntel client. */ clearThemePreference() {
      window.localStorage.removeItem(STORAGE_KEY)
      setExplicit(false)
    },
  }), [theme, hasExplicitPreference])

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
}

/** Handles the use theme operation for the WorkIntel client. */ export function useTheme() {
  const value = useContext(ThemeContext)
  if (!value) throw new Error('useTheme must be used inside ThemeProvider')
  return value
}
