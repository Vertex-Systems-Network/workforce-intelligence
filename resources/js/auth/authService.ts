import { apiRequest } from '../api/client'
import type { AuthResult, AuthSession, AuthUser, LoginInput, RegisterInput, WorkspaceRole } from './types'

const LOCAL_LOGOUT_KEY = 'workintel-auth-local-logout'
const AUTH_MODE = 'laravel' as const

/** Returns get csrf cookie data required by the current workflow. */ async function getCsrfCookie() {
  await apiRequest<void>('/sanctum/csrf-cookie')
}

export const authService = {
  /** Laravel sessions are restored from the server, never from a client-side demo identity. */ restore(): AuthSession | null {
    return null
  },

  /** Handles the restore from api operation for the WorkIntel client. */ async restoreFromApi(): Promise<AuthSession | null> {
    if (window.localStorage.getItem(LOCAL_LOGOUT_KEY) === '1') return null

    try {
      const payload = await apiRequest<{ user: ApiAuthUser }>('/api/v1/auth/me', { silent: true })
      return { user: mapApiUser(payload.user), issuedAt: new Date().toISOString() }
    } catch {
      return null
    }
  },

  /** Handles the login operation for the WorkIntel client. */ async login(input: LoginInput): Promise<AuthResult> {
    await getCsrfCookie()
    const payload = await apiRequest<{ user: ApiAuthUser }>('/api/v1/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email: input.email, password: input.password, remember: input.remember, mfa_code: input.mfaCode || undefined }),
    })
    window.localStorage.removeItem(LOCAL_LOGOUT_KEY)
    return { session: { user: mapApiUser(payload.user), issuedAt: new Date().toISOString() } }
  },

  /** Handles the register operation for the WorkIntel client. */ async register(input: RegisterInput): Promise<AuthResult> {
    if (!input.agreeToTerms) {
      throw new Error('Please accept the terms to create your workspace.')
    }

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
    await apiRequest('/api/v1/auth/logout', { method: 'POST', keepalive: true, silent: true }).catch(() => undefined)
  },

  mode: AUTH_MODE,
}
