import type { AuthWorkspace, WorkspaceRole } from './auth/types'
import type { Page } from './components/Sidebar'

const PAGE_PERMISSIONS:Partial<Record<Page,string[]>>={
  live:['activity.view_team','activity.view_all','activity.manage'],
  people:['people.view_team','people.view_all','people.manage','people.view'], organization:['organization.view','organization.manage'],
  hris:['hris.view_own','hris.view_team','hris.view_all','hris.manage'], performance:['performance.view_own','performance.view_team','performance.view_all','performance.manage'], finance:['expenses.view_own','expenses.view_team','expenses.manage','procurement.request','procurement.view','procurement.manage','job_costing.view','job_costing.manage'],
  'payroll-compliance':['payroll.compliance.view','payroll.compliance.manage','payroll.exports.manage','payroll.contractors.manage'], field:['field.view_own','field.view_team','field.manage','field.forms.manage','field.incidents.manage'], enterprise:['enterprise.identity.manage','enterprise.scim.manage','enterprise.security.manage','enterprise.governance.manage'], automations:['automations.view','automations.manage','automations.runs.view'],
  projects:['projects.view_assigned','projects.view_all','projects.manage','projects.view'], tasks:['tasks.view_own','tasks.view_team','tasks.view_all','tasks.manage_team','tasks.manage','tasks.view'], clients:['clients.view','clients.manage'], 'client-commerce':['client_payments.manage','client_invoices.recurring_manage'],
  time:['time.view_own','time.view_team','time.view_all','time.manage'], activity:['activity.view_own','activity.view_team','activity.view_all','activity.manage'],
  apps:['activity.view_own','activity.view_team','activity.view_all','activity.manage'], screenshots:['screenshots.view_own','screenshots.view_team','screenshots.view_all','screenshots.manage'],
  attendance:['attendance.view_own','attendance.view_team','attendance.manage'], schedule:['scheduling.view_own','scheduling.view_team','scheduling.manage'], shifts:['attendance.view_team','attendance.manage'], leave:['attendance.view_own','attendance.view_team','attendance.manage'],
  payroll:['payroll.view_own','payroll.view_all','payroll.manage'], reports:['reports.view','reports.manage'], documents:['documents.view','documents.generate','documents.manage','documents.templates_manage'], website:['website.view','website.manage','website.publish','website.forms_manage','website.submissions_view'], media:['media.view','media.manage'], trash:['trash.view','trash.restore','trash.purge'], chat:['chat.view','chat.create','chat.manage'], insights:['intelligence.view_own','intelligence.view_team','intelligence.view_all','intelligence.manage'],
  devices:['devices.view','devices.manage'], billing:['billing.manage'], settings:['settings.view','settings.manage'],
  access:['access.view','access.manage','settings.manage'], modules:['modules.view','modules.manage','settings.manage'], approvals:['approvals.view_own','approvals.review','approvals.workflow_manage'],
}

const PAGE_MODULES:Partial<Record<Page,string>>={people:'people',organization:'organization',projects:'projects',tasks:'tasks',time:'time',attendance:'attendance',leave:'attendance',schedule:'scheduling',shifts:'scheduling',approvals:'approvals',activity:'activity',apps:'activity',screenshots:'screenshots',devices:'devices',clients:'clients','client-commerce':'clients',hris:'hris',performance:'performance',payroll:'payroll','payroll-compliance':'payroll-compliance',finance:'finance',reports:'reports',documents:'documents',website:'website',chat:'chat',insights:'intelligence',field:'field',automations:'automations',enterprise:'enterprise',live:'activity'}

/** Handles the module key for page operation for the WorkIntel client. */ export function moduleKeyForPage(page:Page){return PAGE_MODULES[page]}
/** Handles the is module enabled operation for the WorkIntel client. */ export function isModuleEnabled(workspace:AuthWorkspace|undefined,moduleKey:string|undefined){if(!workspace||!moduleKey)return true;const state=workspace.modules?.[moduleKey];return state?state.enabled:true}
/** Handles the is page visible in navigation operation for the WorkIntel client. */ export function isPageVisibleInNavigation(workspace:AuthWorkspace|undefined,page:Page){const key=moduleKeyForPage(page);if(!key)return true;const state=workspace?.modules?.[key];return state?state.enabled&&state.navigationVisible:true}
const MULTI_PAGE_MODULE_LABELS=new Set(['attendance','activity','clients','scheduling'])
/** Return a configurable module label only when one module maps to a single navigation destination. */ export function moduleLabelForPage(workspace:AuthWorkspace|undefined,page:Page,fallback:string){const key=moduleKeyForPage(page);if(!key||MULTI_PAGE_MODULE_LABELS.has(key))return fallback;return workspace?.modules?.[key]?.label??fallback}

/** Handles the has permission operation for the WorkIntel client. */ export function hasPermission(workspace:AuthWorkspace|undefined,permission:string){
  if(!workspace)return false
  return workspace.roles.includes('owner')||workspace.roles.includes('admin')||workspace.permissions.includes('*')||workspace.permissions.includes(permission)
}
/** Handles the has any permission operation for the WorkIntel client. */ export function hasAnyPermission(workspace:AuthWorkspace|undefined,permissions:string[]){return permissions.some(permission=>hasPermission(workspace,permission))}
/** Handles the can access page operation for the WorkIntel client. */ export function canAccessPage(workspace:AuthWorkspace|undefined,page:Page){
  if(!workspace)return false
  if(page==='automations'&&!['Gold','Platinum'].includes(workspace.plan))return false
  if(page==='insights'&&!['Gold','Platinum'].includes(workspace.plan))return false
  if(page==='website'&&workspace.entitlements&&workspace.entitlements['feature.website_builder']!==true)return false
  if(page==='client-commerce'&&workspace.entitlements&&workspace.entitlements['feature.client_payments']!==true&&workspace.entitlements['feature.recurring_client_invoices']!==true)return false
  if(page==='overview'||page==='account'||page==='downloads')return true
  if(!isModuleEnabled(workspace,moduleKeyForPage(page)))return false
  const permissions=PAGE_PERMISSIONS[page]
  return permissions?hasAnyPermission(workspace,permissions):false
}
/** Handles the default page for role operation for the WorkIntel client. */ export function defaultPageForRole(role:WorkspaceRole):Page{
  if(role==='employee')return 'overview'
  if(role==='payroll-manager')return 'payroll'
  if(role==='hr')return 'people'
  return 'overview'
}
/** Handles the role label operation for the WorkIntel client. */ export function roleLabel(role:WorkspaceRole){return ({owner:'Owner',admin:'Administrator',hr:'HR',manager:'Manager','team-lead':'Team Lead','payroll-manager':'Payroll Manager',employee:'Employee',client:'Legacy Client'} as Record<string,string>)[role]??role.split('-').map(v=>v.charAt(0).toUpperCase()+v.slice(1)).join(' ')}
