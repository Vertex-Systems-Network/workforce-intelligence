import { apiRequest } from '../api/client'
import { DEMO_ACCOUNTS } from './demoData'
import type { AuthResult, AuthSession, AuthUser, LoginInput, RegisterInput, WorkspaceRole } from './types'

const SESSION_KEY = 'workintel-demo-session'
const LOCAL_LOGOUT_KEY = 'workintel-auth-local-logout'
const AUTH_MODE = import.meta.env.VITE_AUTH_MODE === 'demo' ? 'demo' : 'laravel'

/** Handles the normalize email operation for the WorkIntel client. */ function normalizeEmail(email: string) {
  return email.trim().toLowerCase()
}

/** Handles the save demo session operation for the WorkIntel client. */ function saveDemoSession(session: AuthSession, remember = true) {
  const storage = remember ? window.localStorage : window.sessionStorage
  storage.setItem(SESSION_KEY, JSON.stringify(session))
}

/** Handles the clear demo session operation for the WorkIntel client. */ function clearDemoSession() {
  window.localStorage.removeItem(SESSION_KEY)
  window.sessionStorage.removeItem(SESSION_KEY)
}

/** Returns read demo session data required by the current workflow. */ function readDemoSession(): AuthSession | null {
  const stored = window.localStorage.getItem(SESSION_KEY) ?? window.sessionStorage.getItem(SESSION_KEY)
  if (!stored) return null

  try {
    return JSON.parse(stored) as AuthSession
  } catch {
    clearDemoSession()
    return null
  }
}

/** Handles the slugify operation for the WorkIntel client. */ function slugify(value: string) {
  return value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
}

type ApiAuthUser = {
  id: number
  first_name: string
  last_name: string
  email: string
  phone?: string | null
  avatar_url?: string | null
  locale?: string
  use_workspace_locale?: boolean
  timezone?: string
  email_verified?: boolean
  force_password_change?: boolean
  platform_operator?: boolean
  workspaces: Array<{
    id: number
    name: string
    slug: string
    role: string
    member_id: number
    plan?: 'Free' | 'Silver' | 'Gold' | 'Platinum'
    roles?: string[]
    permissions?: string[]
    workspace_type?: 'production' | 'sandbox' | 'template'
    branding?: { product_name?: string | null; accent_color?: string | null; hide_powered_by?: boolean } | null
    settings?: { app_title?: string | null; accent_color?: string | null; logo_url?: string | null; favicon_url?: string | null; default_theme?: 'system'|'light'|'dark'; sidebar_density?: 'comfortable'|'compact'; default_language?: string; date_format?: string; time_format?: string; number_format?: string; week_starts_on?: number; fiscal_year_start_month?: number; currency?: string; timezone?: string } | null
    modules?: Record<string,{ enabled:boolean; workspace_enabled:boolean; plan_available:boolean; navigation_visible:boolean; label:string }>
  }>
}

/** Handles the map api user operation for the WorkIntel client. */ function mapApiUser(user: ApiAuthUser): AuthUser {
  const workspaces = user.workspaces.map(workspace => ({
    id: workspace.id,
    memberId: workspace.member_id,
    name: workspace.name,
    slug: workspace.slug,
    plan: workspace.plan ?? 'Free',
    role: workspace.role as WorkspaceRole,
    roles: (workspace.roles ?? [workspace.role]) as WorkspaceRole[],
    permissions: workspace.permissions ?? [],
    workspaceType: workspace.workspace_type ?? 'production',
    branding: workspace.branding ? { productName: workspace.branding.product_name, accentColor: workspace.branding.accent_color, hidePoweredBy: workspace.branding.hide_powered_by } : null,
    settings: workspace.settings ? { appTitle:workspace.settings.app_title, accentColor:workspace.settings.accent_color, logoUrl:workspace.settings.logo_url, faviconUrl:workspace.settings.favicon_url, defaultTheme:workspace.settings.default_theme, sidebarDensity:workspace.settings.sidebar_density, defaultLanguage:workspace.settings.default_language, dateFormat:workspace.settings.date_format, timeFormat:workspace.settings.time_format, numberFormat:workspace.settings.number_format, weekStartsOn:workspace.settings.week_starts_on, fiscalYearStartMonth:workspace.settings.fiscal_year_start_month, currency:workspace.settings.currency, timezone:workspace.settings.timezone } : null,
    platformOperator: Boolean(user.platform_operator),
    modules: workspace.modules ? Object.fromEntries(Object.entries(workspace.modules).map(([key,value])=>[key,{enabled:Boolean(value.enabled),workspaceEnabled:Boolean(value.workspace_enabled),planAvailable:Boolean(value.plan_available),navigationVisible:Boolean(value.navigation_visible),label:value.label}])) : undefined,
  }))

  return {
    id: user.id,
    firstName: user.first_name,
    lastName: user.last_name,
    email: user.email,
    phone: user.phone ?? null,
    avatarUrl: user.avatar_url ?? null,
    locale: user.locale ?? 'en',
    useWorkspaceLocale: user.use_workspace_locale ?? true,
    timezone: user.timezone ?? 'UTC',
    emailVerified: Boolean(user.email_verified),
    forcePasswordChange: Boolean(user.force_password_change),
    platformOperator: Boolean(user.platform_operator),
    jobTitle: workspaces[0]?.role === 'owner' ? 'Workspace Owner' : 'Team Member',
    avatar: `${user.first_name.charAt(0)}${user.last_name.charAt(0)}`.toUpperCase(),
    workspaces,
    activeWorkspaceId: workspaces[0]?.id ?? 0,
  }
}

/** Returns get csrf cookie data required by the current workflow. */ async function getCsrfCookie() {
  await apiRequest<void>('/sanctum/csrf-cookie')
}

export const authService = {
  /** Handles the restore operation for the WorkIntel client. */ restore(): AuthSession | null {
    return AUTH_MODE === 'demo' ? readDemoSession() : null
  },

  /** Handles the restore from api operation for the WorkIntel client. */ async restoreFromApi(): Promise<AuthSession | null> {
    if (AUTH_MODE !== 'laravel') return readDemoSession()
    if (window.localStorage.getItem(LOCAL_LOGOUT_KEY) === '1') return null

    try {
      const payload = await apiRequest<{ user: ApiAuthUser }>('/api/v1/auth/me', { silent: true })
      return { user: mapApiUser(payload.user), issuedAt: new Date().toISOString() }
    } catch {
      return null
    }
  },

  /** Handles the login operation for the WorkIntel client. */ async login(input: LoginInput): Promise<AuthResult> {
    if (AUTH_MODE === 'laravel') {
      await getCsrfCookie()
      const payload = await apiRequest<{ user: ApiAuthUser }>('/api/v1/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email: input.email, password: input.password, remember: input.remember, mfa_code: input.mfaCode || undefined }),
      })
      window.localStorage.removeItem(LOCAL_LOGOUT_KEY)
      return { session: { user: mapApiUser(payload.user), issuedAt: new Date().toISOString() } }
    }

    const email = normalizeEmail(input.email)
    const account = DEMO_ACCOUNTS.find(item => item.user.email === email)

    if (!account || account.password !== input.password) {
      throw new Error('Email or password is incorrect.')
    }

    const session: AuthSession = {
      user: account.user,
      issuedAt: new Date().toISOString(),
    }

    clearDemoSession()
    saveDemoSession(session, input.remember)
    return { session }
  },

  /** Handles the register operation for the WorkIntel client. */ async register(input: RegisterInput): Promise<AuthResult> {
    if (!input.agreeToTerms) {
      throw new Error('Please accept the terms to create your workspace.')
    }

    if (AUTH_MODE === 'laravel') {
      await getCsrfCookie()
      const payload = await apiRequest<{ user: ApiAuthUser }>('/api/v1/auth/register', {
        method: 'POST',
        body: JSON.stringify({
          first_name: input.firstName,
          last_name: input.lastName,
          email: input.workEmail,
          company_name: input.companyName,
          password: input.password,
          timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        }),
      })
      window.localStorage.removeItem(LOCAL_LOGOUT_KEY)
      return { session: { user: mapApiUser(payload.user), issuedAt: new Date().toISOString() } }
    }

    const workspaceName = input.companyName.trim()
    const firstName = input.firstName.trim()
    const lastName = input.lastName.trim()
    const workspaceId = Date.now()

    const session: AuthSession = {
      issuedAt: new Date().toISOString(),
      user: {
        id: workspaceId,
        firstName,
        lastName,
        email: normalizeEmail(input.workEmail),
        jobTitle: 'Workspace Owner',
        avatar: `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase(),
        activeWorkspaceId: workspaceId,
        workspaces: [
          {
            id: workspaceId,
            name: workspaceName,
            slug: slugify(workspaceName),
            plan: 'Free',
            role: 'owner',
            roles: ['owner'],
            permissions: ['*'],
          },
        ],
      },
    }

    clearDemoSession()
    saveDemoSession(session)
    return { session }
  },

  /** Handles the forgot password operation for the WorkIntel client. */ async forgotPassword(email: string): Promise<string> {
    await getCsrfCookie()
    const payload = await apiRequest<{ message:string }>('/api/v1/auth/password/forgot', { method:'POST', body:JSON.stringify({ email }) })
    return payload.message
  },

  /** Handles the reset password operation for the WorkIntel client. */ async resetPassword(input:{token:string;email:string;password:string;passwordConfirmation:string}): Promise<string> {
    await getCsrfCookie()
    const payload = await apiRequest<{ message:string }>('/api/v1/auth/password/reset', { method:'POST', body:JSON.stringify({ token:input.token,email:input.email,password:input.password,password_confirmation:input.passwordConfirmation }) })
    return payload.message
  },

  /** Handles the change password operation for the WorkIntel client. */ async changePassword(input:{currentPassword:string;password:string;passwordConfirmation:string}): Promise<AuthSession|null> {
    await apiRequest('/api/v1/auth/password/change', { method:'POST', body:JSON.stringify({ current_password:input.currentPassword,password:input.password,password_confirmation:input.passwordConfirmation }) })
    return this.restoreFromApi()
  },

  /** Handles the verify email operation for the WorkIntel client. */ async verifyEmail(token:string): Promise<string> {
    const payload = await apiRequest<{ message:string }>('/api/v1/auth/email/verify', { method:'POST', body:JSON.stringify({ token }) })
    return payload.message
  },

  /** End authentication locally first and use keepalive so server-session invalidation survives navigation. */ async logout() {
    window.localStorage.setItem(LOCAL_LOGOUT_KEY, '1')
    if (AUTH_MODE === 'laravel') {
      await apiRequest('/api/v1/auth/logout', { method: 'POST', keepalive: true, silent: true }).catch(() => undefined)
      return
    }

    clearDemoSession()
  },

  mode: AUTH_MODE,
}
