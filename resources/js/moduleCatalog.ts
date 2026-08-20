import type { LucideIcon } from 'lucide-react'
import {
  Activity, Banknote, BarChart3, BriefcaseBusiness, Building2, CalendarCheck2, CalendarClock, ClipboardCheck,
  Clock3, Download, FileText, FolderKanban, Gauge, Globe2, HardDrive, Images, Inbox, Landmark, LayoutDashboard,
  LockKeyhole, MapPinned, MessageCircle, Network, ReceiptText, Settings, ShieldCheck, ShoppingCart, Trash2,
  UserRound, Users, Zap, Boxes,
} from 'lucide-react'
import type { Page } from './components/Sidebar'
import type { TranslationKey } from './i18n/catalog'

/** Stable user-facing module IDs used by the M3 application shell. */
export type WorkspaceModuleId=
  | 'home' | 'work-management' | 'collaboration' | 'time-attendance' | 'people-hr'
  | 'workforce-operations' | 'clients-commerce' | 'content-studio' | 'finance-payroll'
  | 'intelligence' | 'administration'
export type ProductSurfaceId='account-support'|'platform-console'
export type ShellAreaId=WorkspaceModuleId|ProductSurfaceId

/** One module presented to users as a coherent business area rather than a feature bucket. */
export interface WorkspaceModuleDefinition{
  id:WorkspaceModuleId
  label:string
  description:string
  icon:LucideIcon
  pages:Page[]
}

/** One page description and search contract shared by navigation, breadcrumbs and command search. */
export interface PageShellDefinition{
  page:Page
  area:ShellAreaId
  description:string
  aliases:string[]
  icon:LucideIcon
  descriptionKey?:TranslationKey
}

export const workspaceModules:WorkspaceModuleDefinition[]=[
  {id:'home',label:'Home & Command Center',description:'Your workspace start point for live status, notifications, recent work and global actions.',icon:LayoutDashboard,pages:['overview','live']},
  {id:'work-management',label:'Work Management',description:'Plan, assign, approve and automate work across projects and teams.',icon:FolderKanban,pages:['projects','tasks','approvals','automations']},
  {id:'collaboration',label:'Collaboration',description:'Conversations, channels and contextual teamwork around people and work.',icon:MessageCircle,pages:['chat']},
  {id:'time-attendance',label:'Time & Attendance',description:'Attendance, timesheets, leave and scheduling in one workforce time area.',icon:CalendarCheck2,pages:['attendance','time','leave','schedule']},
  {id:'people-hr',label:'People & HR',description:'People records, organization structure, HR lifecycle and performance development.',icon:Users,pages:['people','hris','organization','performance']},
  {id:'workforce-operations',label:'Workforce Operations',description:'Operational evidence from activity, apps, screenshots, devices and field work.',icon:Activity,pages:['activity','apps','screenshots','devices','field']},
  {id:'clients-commerce',label:'Clients & Commerce',description:'Client records, portal relationships, invoices, recurring billing and payments.',icon:Building2,pages:['clients','client-commerce']},
  {id:'content-studio',label:'Content Studio',description:'Create and reuse media, websites and business documents from one content family.',icon:Images,pages:['media','website','documents']},
  {id:'finance-payroll',label:'Finance & Payroll',description:'Expenses, procurement, payroll, compliance and workspace billing operations.',icon:Banknote,pages:['finance','payroll','payroll-compliance','billing']},
  {id:'intelligence',label:'Intelligence & Reports',description:'Explain workforce signals and turn operational data into saved reports and exports.',icon:BarChart3,pages:['insights','reports']},
  {id:'administration',label:'Administration',description:'Configure modules, roles, security, enterprise controls, settings and recovery.',icon:Settings,pages:['modules','access','enterprise','settings','trash']},
]

/** Central page metadata eliminates duplicated icon maps and gives every shell destination explanatory copy. */
export const pageShell:Record<Page,PageShellDefinition>={
  overview:{page:'overview',area:'home',description:'Workspace command center with the most relevant work, time and workforce signals for your role.',aliases:['dashboard','home','command center'],icon:LayoutDashboard},
  live:{page:'live',area:'home',description:'See current workforce presence, work state and live operational status.',aliases:['live team','presence','working now'],icon:Zap,descriptionKey:'page.live.desc'},
  projects:{page:'projects',area:'work-management',description:'Manage project portfolios, delivery context, membership and work progress.',aliases:['delivery','portfolio'],icon:FolderKanban},
  tasks:{page:'tasks',area:'work-management',description:'Create, prioritize, assign and complete personal or team work.',aliases:['my tasks','team tasks','kanban'],icon:ClipboardCheck,descriptionKey:'page.tasks.desc'},
  approvals:{page:'approvals',area:'work-management',description:'Review and act on leave, time, expense, payroll and other workflow requests.',aliases:['requests','inbox','reviews'],icon:Inbox,descriptionKey:'page.approvals.desc'},
  automations:{page:'automations',area:'work-management',description:'Build trigger, condition and action workflows that automate repetitive operations.',aliases:['workflow','rules','automation studio'],icon:Zap,descriptionKey:'page.automations.desc'},
  chat:{page:'chat',area:'collaboration',description:'Work with direct messages, channels, threads and private team attachments.',aliases:['messages','channels','dm','conversation'],icon:MessageCircle,descriptionKey:'page.chat.desc'},
  attendance:{page:'attendance',area:'time-attendance',description:'Clock, review and correct attendance while applying workspace attendance policies.',aliases:['clock in','clock out','team attendance'],icon:CalendarCheck2},
  time:{page:'time',area:'time-attendance',description:'Review tracked time, submit timesheets and manage approvals for visible scopes.',aliases:['timesheets','timer','time entries'],icon:Clock3},
  leave:{page:'leave',area:'time-attendance',description:'Request, review and manage leave balances, policies and approval flows.',aliases:['vacation','absence','pto'],icon:BriefcaseBusiness,descriptionKey:'page.leave.desc'},
  schedule:{page:'schedule',area:'time-attendance',description:'Plan schedules, reusable shifts, availability, open shifts and swap requests.',aliases:['scheduling','shifts','roster'],icon:CalendarClock},
  shifts:{page:'shifts',area:'time-attendance',description:'Legacy shift destination retained for compatibility; Scheduling is the canonical home.',aliases:['shift templates'],icon:CalendarClock},
  people:{page:'people',area:'people-hr',description:'Find people, manage workforce identity and work with your permitted people scope.',aliases:['employees','members','directory'],icon:Users},
  hris:{page:'hris',area:'people-hr',description:'Manage employee lifecycle records, documents, assets and policy acknowledgements.',aliases:['employee records','hr records'],icon:Users},
  organization:{page:'organization',area:'people-hr',description:'Manage departments, teams, job titles and reporting structures used across WorkIntel.',aliases:['departments','teams','org chart'],icon:Network,descriptionKey:'page.organization.desc'},
  performance:{page:'performance',area:'people-hr',description:'Manage goals, reviews, one-to-ones, learning, skills and employee development.',aliases:['reviews','goals','growth'],icon:BarChart3,descriptionKey:'page.performance.desc'},
  activity:{page:'activity',area:'workforce-operations',description:'Review privacy-aware desktop and browser activity evidence and classification rules.',aliases:['tracking','activity analytics'],icon:Activity},
  apps:{page:'apps',area:'workforce-operations',description:'Understand application and website activity with privacy exclusions and categories.',aliases:['applications','websites','domains'],icon:Globe2,descriptionKey:'page.apps.desc'},
  screenshots:{page:'screenshots',area:'workforce-operations',description:'Review consent-aware screenshots, retention and secure storage operations.',aliases:['captures','screen captures'],icon:Images,descriptionKey:'page.screenshots.desc'},
  devices:{page:'devices',area:'workforce-operations',description:'Enroll and manage desktop/browser agents, device status and offline synchronization.',aliases:['agents','desktop agent','browser extension'],icon:HardDrive,descriptionKey:'page.devices.desc'},
  field:{page:'field',area:'workforce-operations',description:'Run field work orders, sites, checkpoints, safety forms and incident workflows.',aliases:['field workforce','work orders','sites'],icon:MapPinned,descriptionKey:'page.field.desc'},
  clients:{page:'clients',area:'clients-commerce',description:'Manage client records, contacts, portal access and customer relationships.',aliases:['customers','accounts'],icon:Building2,descriptionKey:'page.clients.desc'},
  'client-commerce':{page:'client-commerce',area:'clients-commerce',description:'Manage client billing, recurring invoices, payment actions and settlement workflows.',aliases:['client payments','client billing','invoices'],icon:ShoppingCart},
  media:{page:'media',area:'content-studio',description:'Store, organize and reuse workspace media across studios, chat, HR and work.',aliases:['assets','files','images','library'],icon:Images,descriptionKey:'page.media.desc'},
  website:{page:'website',area:'content-studio',description:'Build, preview, version and publish workspace websites from the visual studio.',aliases:['website builder','web studio','site'],icon:Globe2,descriptionKey:'page.website.desc'},
  documents:{page:'documents',area:'content-studio',description:'Design templates, generate documents, review versions and manage signing workflows.',aliases:['document studio','pdf','templates'],icon:FileText},
  finance:{page:'finance',area:'finance-payroll',description:'Submit and control expenses, receipts, procurement and project job costs.',aliases:['expenses','procurement','job costing'],icon:ReceiptText,descriptionKey:'page.finance.desc'},
  payroll:{page:'payroll',area:'finance-payroll',description:'Calculate, approve, pay and review payroll according to your role and scope.',aliases:['pay','salary','payroll runs'],icon:Banknote},
  'payroll-compliance':{page:'payroll-compliance',area:'finance-payroll',description:'Manage statutory compliance, benefits, contractors, exports and payroll snapshots.',aliases:['compliance','statutory','contractors'],icon:Landmark,descriptionKey:'page.payroll_compliance.desc'},
  billing:{page:'billing',area:'finance-payroll',description:'Manage the workspace subscription, plan, entitlements, usage and billing history.',aliases:['plans','subscription','workspace billing'],icon:ReceiptText,descriptionKey:'page.billing.desc'},
  insights:{page:'insights',area:'intelligence',description:'Explore explainable workforce signals calculated from approved operational data.',aliases:['workforce intelligence','analytics','signals'],icon:Gauge,descriptionKey:'page.insights.desc'},
  reports:{page:'reports',area:'intelligence',description:'Build, save, schedule and export permission-aware workforce reports.',aliases:['reporting','exports','scheduled reports'],icon:BarChart3,descriptionKey:'page.reports.desc'},
  modules:{page:'modules',area:'administration',description:'Enable or disable workspace capabilities while preserving their data and dependencies.',aliases:['apps','features','module manager'],icon:Boxes,descriptionKey:'page.modules.desc'},
  access:{page:'access',area:'administration',description:'Manage roles, permissions, scope rules and explicit access boundaries.',aliases:['roles','permissions','access control'],icon:ShieldCheck,descriptionKey:'page.access.desc'},
  enterprise:{page:'enterprise',area:'administration',description:'Manage enterprise identity, SCIM, sessions, ABAC, legal entities and governance.',aliases:['sso','scim','governance','security'],icon:LockKeyhole,descriptionKey:'page.enterprise.desc'},
  settings:{page:'settings',area:'administration',description:'Configure workspace identity, localization, preferences, integrations and defaults.',aliases:['configuration','preferences'],icon:Settings},
  trash:{page:'trash',area:'administration',description:'Restore recoverable workspace records or permanently purge eligible deleted data.',aliases:['deleted','recovery','trash center'],icon:Trash2,descriptionKey:'page.trash.desc'},
  downloads:{page:'downloads',area:'account-support',description:'Install desktop/browser components, create enrollment codes and troubleshoot setup.',aliases:['installation center','installer','agent download'],icon:Download},
  account:{page:'account',area:'account-support',description:'Review your role, permissions, personal security and account access.',aliases:['my access','profile','security'],icon:UserRound,descriptionKey:'page.account.desc'},
}

/** Look up a workspace module by stable ID. */
export function workspaceModule(id:WorkspaceModuleId){return workspaceModules.find(module=>module.id===id)}
/** Return the user-facing area for a page. */
export function shellAreaForPage(page:Page){return pageShell[page].area}
/** Return the workspace module for a page, excluding special app surfaces. */
export function workspaceModuleForPage(page:Page){const id=shellAreaForPage(page);return workspaceModules.find(module=>module.id===id)}
/** Return a localized-or-fallback description for one page. */
export function localizedPageDescription(page:Page,t:(key:TranslationKey)=>string,text:(value:string)=>string){const meta=pageShell[page];return meta.descriptionKey?t(meta.descriptionKey):text(meta.description)}
