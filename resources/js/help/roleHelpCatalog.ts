import type { AuthWorkspace } from '../auth/types'
import { canAccessPage, isPageVisibleInNavigation, roleLabel } from '../access'
import type { Page } from '../components/Sidebar'
import { pageShell, workspaceModuleForPage, type WorkspaceModuleId } from '../moduleCatalog'

export type RoleGuideTask={id:string;page:Page;title:string;description:string;why:string}
export type RoleGuide={key:string;label:string;title:string;summary:string;outcomes:string[];tasks:RoleGuideTask[]}
export type PageHelp={summary:string;firstSteps:string[];watchFor:string[];relatedPages:Page[];moduleId:WorkspaceModuleId|null;keywords:string[]}

const common:RoleGuideTask[]=[
 {id:'profile',page:'account',title:'Review your access',description:'Confirm your role, account details and effective workspace access.',why:'Knowing your effective access prevents confusion when a page or action is intentionally unavailable.'},
 {id:'chat',page:'chat',title:'Open Collaboration',description:'Review direct messages, channels and the Activity inbox.',why:'WorkIntel keeps operational conversations close to the work they reference.'},
]

const guides:Record<string,Omit<RoleGuide,'key'|'label'>>={
 owner:{title:'Set up your workspace for reliable operations',summary:'Start with workspace identity and access, then configure the operating modules your team will use every day.',outcomes:['Workspace settings reflect your organization.','Members have intentional roles and scopes.','Core work, time and reporting modules are ready for use.'],tasks:[
  {id:'workspace-settings',page:'settings',title:'Configure workspace settings',description:'Review identity, timezone, locale, currency and workspace defaults.',why:'These defaults flow into dates, payroll, reports and generated business records.'},
  {id:'modules',page:'modules',title:'Review enabled modules',description:'Keep only the business capabilities your workspace actually uses.',why:'A focused navigation is easier to learn and reduces accidental access surface.'},
  {id:'roles',page:'access',title:'Review roles and access',description:'Confirm role permissions, explicit denies, scopes and module access.',why:'Permissions should reflect operational responsibility, not job-title assumptions.'},
  {id:'people',page:'people',title:'Review people and memberships',description:'Confirm the people visible to your administrative scope.',why:'People records are the foundation for time, payroll, projects and policy workflows.'},
  {id:'projects',page:'projects',title:'Create or review active projects',description:'Verify project ownership, membership and delivery context.',why:'Projects provide the shared context for tasks, time and client work.'},
  {id:'reports',page:'reports',title:'Verify reporting',description:'Open reports and confirm the data your leadership team needs is available.',why:'A configured workspace should produce understandable operational evidence.'},
  ...common,
 ]},
 admin:{title:'Keep workspace configuration understandable and safe',summary:'Validate access, modules and settings before supporting daily operations.',outcomes:['Permissions are intentional.','Workspace configuration is supportable.','Operational modules expose the right data.'],tasks:[
  {id:'roles',page:'access',title:'Review roles and access',description:'Check custom roles, scopes and module restrictions.',why:'Access changes should be deliberate and auditable.'},
  {id:'modules',page:'modules',title:'Review enabled modules',description:'Confirm enabled capabilities and navigation visibility.',why:'Disabled capabilities should not create confusing dead ends.'},
  {id:'settings',page:'settings',title:'Review workspace settings',description:'Check shared identity, localization and operational defaults.',why:'Central settings affect many downstream modules.'},
  {id:'enterprise',page:'enterprise',title:'Review enterprise controls',description:'Check sessions, identity, network and governance controls where enabled.',why:'Administrative access should be governed as carefully as business data.'},
  ...common,
 ]},
 manager:{title:'Run the team from work, approvals and time signals',summary:'Use the manager path to keep delivery, team workload and exceptions visible without browsing administrative areas.',outcomes:['Work is assigned and prioritized.','Approvals and attendance exceptions are visible.','Team communication stays linked to work.'],tasks:[
  {id:'projects',page:'projects',title:'Review team projects',description:'Check active delivery, members and upcoming deadlines.',why:'Projects establish the context for team work.'},
  {id:'tasks',page:'tasks',title:'Review team tasks',description:'Check priorities, ownership and overdue work.',why:'Task health is the most immediate view of execution risk.'},
  {id:'approvals',page:'approvals',title:'Clear pending approvals',description:'Review workflow requests that require a decision.',why:'Approval delays can block time, leave, finance and payroll flows.'},
  {id:'attendance',page:'attendance',title:'Review today’s attendance',description:'Check present staff and exceptions in your visible scope.',why:'Attendance exceptions are easier to resolve while context is current.'},
  {id:'reports',page:'reports',title:'Open a team report',description:'Use saved or ad-hoc reports for a repeatable view of team operations.',why:'Reports turn individual signals into a stable management routine.'},
  ...common,
 ]},
 hr:{title:'Establish clean people and lifecycle operations',summary:'Start with people records and organization structure, then validate attendance, leave and performance workflows.',outcomes:['People records are complete within your scope.','Organization relationships are understandable.','HR exceptions and development workflows are visible.'],tasks:[
  {id:'people',page:'people',title:'Review people records',description:'Check active people, contact details and employment context.',why:'Accurate people records support every downstream HR workflow.'},
  {id:'hris',page:'hris',title:'Review HR lifecycle data',description:'Check employment, lifecycle and HRIS information available to your role.',why:'Lifecycle accuracy prevents downstream policy and reporting gaps.'},
  {id:'organization',page:'organization',title:'Review organization structure',description:'Confirm reporting and organizational relationships.',why:'Managers, scopes and approvals often depend on organization structure.'},
  {id:'attendance',page:'attendance',title:'Review attendance exceptions',description:'Check late, absent and missing-clock-out signals.',why:'Attendance exceptions frequently require timely HR context.'},
  {id:'leave',page:'leave',title:'Review leave workflows',description:'Check leave requests and policy-sensitive absences.',why:'Leave decisions affect coverage, attendance and payroll.'},
  {id:'performance',page:'performance',title:'Review performance workflows',description:'Check cycles, goals and development activity.',why:'Performance work should be discoverable as part of the employee lifecycle.'},
  ...common,
 ]},
 'payroll-manager':{title:'Prepare payroll from approved workforce evidence',summary:'Validate attendance and approved time before operating payroll, compliance and finance workflows.',outcomes:['Time inputs are reviewable.','Payroll runs use approved evidence.','Compliance and finance outputs are discoverable.'],tasks:[
  {id:'attendance',page:'attendance',title:'Review attendance inputs',description:'Check attendance exceptions before payroll processing.',why:'Unresolved attendance can materially affect calculated pay.'},
  {id:'time',page:'time',title:'Review approved time',description:'Confirm timesheets and approved tracked work.',why:'Approved time should be stable before compensation is finalized.'},
  {id:'payroll',page:'payroll',title:'Review payroll runs',description:'Open payroll and verify calculation/review state.',why:'Payroll should move through explicit review and approval states.'},
  {id:'compliance',page:'payroll-compliance',title:'Review payroll compliance',description:'Check compliance packs, contractors and export readiness.',why:'Compliance output is part of payroll completion, not an afterthought.'},
  {id:'finance',page:'finance',title:'Review finance adjustments',description:'Check reimbursements, expenses and relevant finance inputs.',why:'Approved financial adjustments can affect employee or client-facing totals.'},
  {id:'reports',page:'reports',title:'Verify payroll reporting',description:'Open saved payroll reports and exports.',why:'Repeatable reporting helps reconcile payroll decisions.'},
  ...common,
 ]},
 employee:{title:'Know what to do, when to do it, and where to find your records',summary:'Your Start Here path focuses on assigned work, attendance, schedule, time, requests and collaboration.',outcomes:['You can find assigned work.','You know where attendance and time records live.','Requests, pay and conversations are easy to return to.'],tasks:[
  {id:'tasks',page:'tasks',title:'Review your tasks',description:'Check assigned work and current priorities.',why:'Tasks are the clearest starting point for what needs your attention.'},
  {id:'schedule',page:'schedule',title:'Review your schedule',description:'Check upcoming shifts or scheduled working time.',why:'Knowing your schedule reduces attendance and availability surprises.'},
  {id:'attendance',page:'attendance',title:'Review your attendance',description:'Confirm today’s clock state and recent attendance history.',why:'Attendance is your source of truth for presence records.'},
  {id:'time',page:'time',title:'Review your timesheet',description:'Check tracked time and submit required entries.',why:'Timesheets connect work records to approvals, payroll and reporting.'},
  {id:'approvals',page:'approvals',title:'Review your requests',description:'Check leave, time or other workflow requests you submitted.',why:'Requests can require follow-up even after submission.'},
  {id:'payroll',page:'payroll',title:'Know where your pay records live',description:'Review approved pay history when available.',why:'Your pay history should be easy to locate without administrative access.'},
  ...common,
 ]},
}

/** Normalize known roles into stable guide families; unknown custom roles are classified later from effective permissions. */
export function roleGuideKey(role:string){if(role==='team-lead')return'manager';if(guides[role])return role;return''}

/** Infer a useful guide family for custom roles from effective permissions without granting any access. */
function inferredGuideKey(workspace:AuthWorkspace){if(workspace.roles.includes('owner'))return'owner';if(workspace.roles.includes('admin'))return'admin';const permissions=new Set(workspace.permissions);if(permissions.has('*')||permissions.has('settings.manage')||permissions.has('access.manage'))return'admin';if(permissions.has('payroll.manage')||permissions.has('payroll.view_all'))return'payroll-manager';if(permissions.has('hris.manage')||permissions.has('people.manage')||permissions.has('performance.manage'))return'hr';if(permissions.has('tasks.manage_team')||permissions.has('attendance.view_team')||permissions.has('projects.manage'))return'manager';return'employee'}

/** Return a permission-aware role guide; inaccessible or hidden destinations are never suggested. */
export function roleGuideForWorkspace(workspace:AuthWorkspace):RoleGuide{
 const key=roleGuideKey(workspace.role)||inferredGuideKey(workspace),base=guides[key]??guides.employee
 const seen=new Set<string>()
 const tasks=base.tasks.filter(task=>{if(seen.has(task.id))return false;seen.add(task.id);return canAccessPage(workspace,task.page)&&isPageVisibleInNavigation(workspace,task.page)})
 return{key,label:roleLabel(workspace.role),...base,tasks}
}

const firstSteps:Partial<Record<Page,string[]>>={
 overview:['Scan the cards that are relevant to your role.','Use Workspace Areas to enter a module by purpose.','Use Start Here for the next recommended setup or operating step.'],
 projects:['Open an active project and verify ownership, members and due dates.','Use project context before creating disconnected tasks or time records.','Review financial/project signals only when your role exposes them.'],
 tasks:['Start with overdue and high-priority work.','Open a task to confirm ownership, status and project context.','Use linked discussion or approvals instead of duplicating work in chat.'],
 approvals:['Review what is waiting for your role to decide.','Open the request context before approving or rejecting.','Use audit/history views when a decision needs traceability.'],
 chat:['Start in Activity for unread mentions, followed threads and direct messages.','Use channels for shared context and DMs for private coordination.','Attach WorkIntel projects, tasks, documents or Media Library files rather than pasting disconnected references.'],
 attendance:['Check current attendance state before correcting historical records.','Review exceptions such as late, absent or missing clock-out.','Use your own/team scope rather than assuming every employee is visible.'],
 time:['Review the current period first.','Resolve missing or unapproved time before downstream payroll/reporting.','Use project/task context for time when available.'],
 leave:['Review balances/policy context before submitting or deciding a request.','Use request history to understand current workflow state.','Check team coverage where your role exposes it.'],
 people:['Search before creating or editing a person.','Open the person record to understand employment and access context.','Use role/scoped visibility as the authoritative boundary.'],
 hris:['Start from the employee lifecycle record.','Update employment data only within your HR scope.','Use HRIS history rather than overwriting context silently.'],
 organization:['Confirm reporting relationships and organizational assignments.','Use organization structure to explain manager/scoping behavior.','Review downstream access implications before major changes.'],
 performance:['Check the active cycle and participant scope.','Keep goals/reviews attached to the correct employee and cycle.','Use historical cycles for context rather than editing completed evidence.'],
 payroll:['Confirm attendance/time inputs are stable before calculating.','Move runs through calculation, review and approval deliberately.','Treat approved payroll as a locked historical snapshot.'],
 'payroll-compliance':['Review the relevant compliance pack and assignment scope.','Validate export readiness before final delivery.','Keep contractor/benefit context aligned with payroll state.'],
 finance:['Start with pending claims, procurement or adjustments that need action.','Confirm project/client allocation before approval.','Use audit context for financial changes.'],
 reports:['Choose the business question before selecting a dataset.','Use filters and saved views for repeatable reporting.','Export only data your current role is authorized to see.'],
 media:['Search existing assets before uploading duplicates.','Use folders for storage organization and collections for reusable grouping.','Review rights/usage before replacing or deleting an asset.'],
 website:['Use Pages/Layers/Blocks/Assets to locate what you are editing.','Run preflight before staging or publishing.','Use Media Library assets and staged review links for governed collaboration.'],
 documents:['Use Pages/Layers/Blocks/Assets for authored structure.','Run PDF preflight before generation or staging.','Use Brand Kits, Page Masters and reusable components instead of duplicating formatting.'],
 settings:['Change workspace-wide defaults only when they should affect everyone.','Keep personal UI preferences separate from workspace configuration.','Review timezone, locale and currency carefully because they affect many modules.'],
 access:['Start from the role purpose, then review permissions, scopes and explicit denies.','Prefer least access required for the operational responsibility.','Re-check affected members after changing a shared role.'],
 modules:['Enable modules only when the workspace uses them.','Review dependencies and plan availability before enabling.','Keep navigation visibility aligned with actual operational use.'],
 enterprise:['Review identity/session controls before governance exports.','Use least-privilege network and session policy.','Treat legal hold and audit controls as governed evidence.'],
 downloads:['Choose the correct platform guide before installing an agent.','Follow enrollment/security steps exactly.','Use guide progress to resume an interrupted setup.'],
 account:['Review your current role and effective access.','Update personal profile/localization without changing workspace-wide defaults.','Use access details to understand why a destination may not be available.'],
}

const watchFor:Partial<Record<Page,string[]>>={
 access:['A role label is not authorization by itself; effective permissions, denies, scopes and modules all matter.'],
 payroll:['Do not change payroll calculation rules merely to make a run match an expected total; resolve source evidence first.'],
 media:['Deleting or replacing an asset can affect websites/documents that reference it; inspect Usage first.'],
 website:['Publishing is a governed action; staged preview links are intentionally separate from the published site.'],
 documents:['Generated documents and approved/signature workflows are historical evidence; avoid rewriting finalized state.'],
 chat:['Pins are shared, bookmarks/notes are private, and linked resources are re-authorized when viewed.'],
 settings:['Workspace settings affect other users; personal page preferences do not.'],
}

/** Build contextual guidance for any registered page without maintaining a second navigation catalog. */
export function contextualHelpForPage(page:Page,workspace:AuthWorkspace):PageHelp{
 const meta=pageShell[page],module=workspaceModuleForPage(page)
 const siblings=(module?.pages??[]).filter(candidate=>candidate!==page&&canAccessPage(workspace,candidate)&&isPageVisibleInNavigation(workspace,candidate)).slice(0,5)
 return{
  summary:meta.description,
  firstSteps:firstSteps[page]??[`Start with the page purpose: ${meta.description}`,`Use search, filters and page actions before changing workspace data.`,`Open Help again if an action is unavailable; your effective permissions and enabled modules remain authoritative.`],
  watchFor:watchFor[page]??['Only actions allowed by your current workspace permissions and enabled modules should be available.'],
  relatedPages:siblings,
  moduleId:(module?.id??null) as WorkspaceModuleId|null,
  keywords:[...meta.aliases,page,module?.label??''].filter(Boolean),
 }
}
