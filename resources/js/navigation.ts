import type { WorkspaceRole } from './auth/types'
import type { Page } from './components/Sidebar'
import type { TranslationKey } from './i18n/catalog'
import manifest from './navigation.manifest.json'

/** Describes one stable navigation item without storing mutable translated labels. */
export type NavigationItemDefinition={id:Page;labelKey?:TranslationKey}
/** Describes one stable navigation group used to rebuild the sidebar for any locale. */
export type NavigationGroupDefinition={id:string;labelKey:TranslationKey;items:NavigationItemDefinition[]}

/** Translation key used by page titles and default navigation labels. */
export const pageTranslationKey:Record<Page,TranslationKey>={
 overview:'nav.home',live:'nav.live',people:'nav.people',hris:'nav.hris',performance:'nav.performance',finance:'nav.finance','payroll-compliance':'nav.payroll_compliance',field:'nav.field',enterprise:'nav.enterprise',automations:'nav.automations',organization:'nav.organization',projects:'nav.projects',tasks:'nav.tasks',clients:'nav.clients','client-commerce':'nav.client_payments',time:'nav.timesheets',activity:'nav.activity',apps:'nav.apps',screenshots:'nav.screenshots',attendance:'nav.attendance',schedule:'nav.schedule',shifts:'nav.shifts',leave:'nav.leave',payroll:'nav.payroll',reports:'nav.reports',documents:'nav.documents',website:'nav.website',media:'nav.media',trash:'nav.trash',chat:'nav.chat',insights:'nav.insights',approvals:'nav.approvals',downloads:'nav.downloads',devices:'nav.devices',billing:'nav.billing',settings:'nav.settings',access:'nav.access',modules:'nav.modules',account:'nav.account',
}

/** Convert the JSON navigation manifest into typed immutable definitions. */
function typedGroups(value:unknown):NavigationGroupDefinition[]{return (value as Array<{id:string;labelKey:TranslationKey;items:Array<[Page,TranslationKey?]>}>).map(group=>({id:group.id,labelKey:group.labelKey,items:group.items.map(([id,labelKey])=>({id,labelKey}))}))}
/** Return an immutable navigation definition for the current role; labels are resolved later from the active locale. */
export function navigationForRole(role:WorkspaceRole):NavigationGroupDefinition[]{
 if(role==='employee')return typedGroups(manifest.employee)
 if(role==='payroll-manager')return typedGroups(manifest['payroll-manager'])
 if(role==='hr')return typedGroups(manifest.hr)
 if(role==='manager'||role==='team-lead')return typedGroups(manifest.manager)
 return typedGroups(manifest.owner)
}
/** Return the expected count of unique navigation IDs for regression tests without depending on translations. */
export function navigationIdsForRole(role:WorkspaceRole):Page[]{return navigationForRole(role).flatMap(group=>group.items.map(item=>item.id))}
