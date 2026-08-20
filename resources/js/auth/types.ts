export type WorkspaceRole = string

/** Describes the auth workspace data contract used by the WorkIntel client. */ export interface AuthWorkspace {
  id: number
  memberId?: number
  name: string
  slug: string
  plan: 'Free' | 'Silver' | 'Gold' | 'Platinum'
  entitlements?: Record<string,boolean|number|string|null>
  role: WorkspaceRole
  roles: WorkspaceRole[]
  permissions: string[]
  workspaceType?: 'production' | 'sandbox' | 'template'
  branding?: { productName?: string | null; accentColor?: string | null; hidePoweredBy?: boolean } | null
  settings?: { appTitle?: string | null; accentColor?: string | null; logoUrl?: string | null; faviconUrl?: string | null; defaultTheme?: 'system'|'light'|'dark'; sidebarDensity?: 'comfortable'|'compact'; defaultLanguage?: string; dateFormat?: string; timeFormat?: string; numberFormat?: string; weekStartsOn?: number; fiscalYearStartMonth?: number; currency?: string; timezone?: string } | null
  modules?: Record<string,{ enabled:boolean; workspaceEnabled:boolean; planAvailable:boolean; navigationVisible:boolean; label:string }>
  platformOperator?: boolean
}

/** Describes the auth user data contract used by the WorkIntel client. */ export interface AuthUser {
  id: number
  firstName: string
  lastName: string
  email: string
  phone?: string | null
  avatarUrl?: string | null
  locale?: string
  useWorkspaceLocale?: boolean
  timezone?: string
  emailVerified?: boolean
  forcePasswordChange?: boolean
  platformOperator?: boolean
  jobTitle: string
  avatar: string
  workspaces: AuthWorkspace[]
  activeWorkspaceId: number
}

/** Describes the auth session data contract used by the WorkIntel client. */ export interface AuthSession { user: AuthUser; issuedAt: string }
/** Describes the login input data contract used by the WorkIntel client. */ export interface LoginInput { email:string;password:string;remember:boolean;mfaCode?:string }
/** Describes the register input data contract used by the WorkIntel client. */ export interface RegisterInput { firstName:string;lastName:string;workEmail:string;companyName:string;password:string;agreeToTerms:boolean }
/** Describes the auth result data contract used by the WorkIntel client. */ export interface AuthResult { session: AuthSession }
