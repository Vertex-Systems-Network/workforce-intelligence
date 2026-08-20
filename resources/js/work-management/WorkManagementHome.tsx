import { useEffect, useMemo, useState } from 'react'
import { AlertTriangle, CheckSquare2, Clock3, FolderKanban, Inbox, RefreshCw } from 'lucide-react'
import { canAccessPage } from '../access'
import { apiRequest } from '../api/client'
import type { AuthWorkspace } from '../auth/types'
import type { Page } from '../components/Sidebar'
import { Alert, Button, Card, CardBody, CardHeader, EmptyState, Grid, Inline, Stack, StatCard, Text } from '../design-system'

type ProjectSummary={id:number;name:string;status:string;due_date:string|null;priority:string;tasks_count?:number}
type TaskSummary={id:number;title:string;due_at:string|null;priority:string;completed_at:string|null;workflow_status?:{is_completed?:boolean}|null}
type ApprovalSummary={counts?:{inbox?:number;mine_pending?:number}}
type AutomationSummary={workflows?:Array<{id:number;status:string}>;runs?:Array<{id:number;status:string}>}

type Snapshot={projects:ProjectSummary[];tasks:TaskSummary[];approvalInbox:number;myPending:number;activeAutomations:number;failedAutomationRuns:number}
const emptySnapshot:Snapshot={projects:[],tasks:[],approvalInbox:0,myPending:0,activeAutomations:0,failedAutomationRuns:0}

/** Return true when a date-only or ISO timestamp is already past. */
function isPast(value:string|null|undefined){if(!value)return false;const time=new Date(value).getTime();return Number.isFinite(time)&&time<Date.now()}
/** Return true when a date lands within the next seven days. */
function dueSoon(value:string|null|undefined){if(!value)return false;const time=new Date(value).getTime();const delta=time-Date.now();return Number.isFinite(time)&&delta>=0&&delta<=7*86400000}
/** Treat completed workflow statuses and explicit completion timestamps as closed work. */
function taskOpen(task:TaskSummary){return !task.completed_at&&!task.workflow_status?.is_completed}

/** Render the Work Management module's role-aware operational snapshot. */
export default function WorkManagementHome({workspace,onNavigate}:{workspace:AuthWorkspace;onNavigate:(page:Page)=>void}){
  const [snapshot,setSnapshot]=useState<Snapshot>(emptySnapshot)
  const [loading,setLoading]=useState(true)
  const [warning,setWarning]=useState('')
  const access=useMemo(()=>({projects:canAccessPage(workspace,'projects'),tasks:canAccessPage(workspace,'tasks'),approvals:canAccessPage(workspace,'approvals'),automations:canAccessPage(workspace,'automations')}),[workspace])
  /** Load only summary datasets the current member is already authorized to open. */
  const load=async()=>{
    setLoading(true);setWarning('')
    const requests:Array<Promise<{kind:'projects'|'tasks'|'approvals'|'automations';value:unknown}>>=[]
    if(access.projects)requests.push(apiRequest<{data:ProjectSummary[]}>('/api/v1/projects',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'projects' as const,value})))
    if(access.tasks)requests.push(apiRequest<{data:TaskSummary[]}>('/api/v1/tasks',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'tasks' as const,value})))
    if(access.approvals)requests.push(apiRequest<ApprovalSummary>('/api/v1/approvals',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'approvals' as const,value})))
    if(access.automations)requests.push(apiRequest<AutomationSummary>('/api/v1/automations/overview',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'automations' as const,value})))
    const results=await Promise.allSettled(requests)
    const next:Snapshot={...emptySnapshot};let failed=0
    for(const result of results){if(result.status==='rejected'){failed++;continue}const {kind,value}=result.value;if(kind==='projects')next.projects=(value as {data:ProjectSummary[]}).data??[];else if(kind==='tasks')next.tasks=(value as {data:TaskSummary[]}).data??[];else if(kind==='approvals'){const counts=(value as ApprovalSummary).counts;next.approvalInbox=counts?.inbox??0;next.myPending=counts?.mine_pending??0}else{const automation=value as AutomationSummary;next.activeAutomations=(automation.workflows??[]).filter(row=>row.status==='active').length;next.failedAutomationRuns=(automation.runs??[]).filter(row=>row.status==='failed').length}}
    setSnapshot(next);if(failed)setWarning('Some work summary data could not be loaded. Your module pages are still available below.');setLoading(false)
  }
  useEffect(()=>{void load()},[workspace.id,access.projects,access.tasks,access.approvals,access.automations])
  const openTasks=snapshot.tasks.filter(taskOpen)
  const overdueTasks=openTasks.filter(task=>isPast(task.due_at))
  const urgentTasks=openTasks.filter(task=>task.priority==='critical'||task.priority==='high')
  const activeProjects=snapshot.projects.filter(project=>project.status==='active')
  const overdueProjects=snapshot.projects.filter(project=>!['completed','archived'].includes(project.status)&&isPast(project.due_date))
  const dueSoonProjects=snapshot.projects.filter(project=>!['completed','archived'].includes(project.status)&&dueSoon(project.due_date))
  const hasData=access.projects||access.tasks||access.approvals||access.automations
  return <Stack gap={12}>
    <Card><CardHeader title="Work management snapshot" description="Live, permission-aware signals from projects, tasks and approvals. Use this to decide what needs attention first." action={<Button size="sm" variant="ghost" loading={loading} onClick={()=>void load()}><RefreshCw size={13}/> Refresh</Button>}/><CardBody>
      {warning&&<Alert tone="warning">{warning}</Alert>}
      {!hasData?<EmptyState title="No work areas are available for this role." text="Your workspace administrator controls access to Projects, Tasks and Approvals." contextualHelp/>:<Grid columns="repeat(auto-fit,minmax(150px,1fr))" gap={9}>
        {access.projects&&<><StatCard label="Active projects" value={loading?'…':String(activeProjects.length)} sub={`${overdueProjects.length} overdue · ${dueSoonProjects.length} due soon`}/></>}
        {access.tasks&&<><StatCard label="Open tasks" value={loading?'…':String(openTasks.length)} sub={`${overdueTasks.length} overdue`}/><StatCard label="High priority" value={loading?'…':String(urgentTasks.length)} sub="High + critical open work"/></>}
        {access.approvals&&<StatCard label="Approval inbox" value={loading?'…':String(snapshot.approvalInbox)} sub={`${snapshot.myPending} of your requests pending`}/>}
        {access.automations&&<StatCard label="Active automations" value={loading?'…':String(snapshot.activeAutomations)} sub={`${snapshot.failedAutomationRuns} failed recent run(s)`}/>} 
      </Grid>}
    </CardBody></Card>
    <Grid columns="repeat(auto-fit,minmax(240px,1fr))" gap={9}>
      {access.projects&&<Card><CardHeader title="Projects" description="Portfolio, ownership, dates and delivery context."/><CardBody><Stack gap={8}><Inline gap={8} align="center"><FolderKanban size={15}/><Text color="var(--text-2)">{overdueProjects.length?`${overdueProjects.length} project(s) need date attention.`:'No overdue project dates in your visible scope.'}</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('projects')}>Open Projects</Button></Stack></CardBody></Card>}
      {access.tasks&&<Card><CardHeader title="Tasks" description="Priorities, workflow status, assignments and dependencies."/><CardBody><Stack gap={8}><Inline gap={8} align="center"><CheckSquare2 size={15}/><Text color="var(--text-2)">{overdueTasks.length?`${overdueTasks.length} overdue task(s) need attention.`:'No overdue open tasks in your visible scope.'}</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('tasks')}>Open Tasks</Button></Stack></CardBody></Card>}
      {access.approvals&&<Card><CardHeader title="Approvals" description="Requests waiting for you plus your own pending requests."/><CardBody><Stack gap={8}><Inline gap={8} align="center">{snapshot.approvalInbox?<AlertTriangle size={15}/>:<Inbox size={15}/>}<Text color="var(--text-2)">{snapshot.approvalInbox?`${snapshot.approvalInbox} request(s) are waiting in your inbox.`:'Your approval inbox is clear.'}</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('approvals')}>Open Approvals</Button></Stack></CardBody></Card>}
      {access.automations&&<Card><CardHeader title="Automations" description="Reduce repetitive work with controlled trigger/action workflows."/><CardBody><Stack gap={8}><Inline gap={8} align="center"><Clock3 size={15}/><Text color="var(--text-2)">{snapshot.failedAutomationRuns?`${snapshot.failedAutomationRuns} recent automation run(s) failed and need review.`:`${snapshot.activeAutomations} active workflow(s) are reducing repetitive work.`}</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('automations')}>Open Automations</Button></Stack></CardBody></Card>}
    </Grid>
  </Stack>
}
