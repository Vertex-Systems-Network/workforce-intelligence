import { useEffect, useMemo, useState } from 'react'
import { BarChart3, Network, RefreshCw, UserRoundCog, Users } from 'lucide-react'
import { canAccessPage } from '../access'
import { apiRequest } from '../api/client'
import type { AuthWorkspace } from '../auth/types'
import type { Page } from '../components/Sidebar'
import { Alert, Button, Card, CardBody, CardHeader, EmptyState, Grid, Inline, Stack, StatCard, Text } from '../design-system'

type PeopleSummary={data?:Array<{id:number;status:string;department?:string|null}>}
type HrisSummary={data?:Array<{id:number;employment_stage:string;status:string;probation_end_date?:string|null}>}
type OrganizationSummary={departments?:Array<unknown>;job_titles?:Array<unknown>;teams?:Array<unknown>;people?:Array<unknown>}
type PerformanceSummary={goals?:Array<{status:string}>;reviews?:Array<{status:string}>;member_skills?:Array<unknown>;enrollments?:Array<{status:string}>}
type Snapshot={people:PeopleSummary|null;hris:HrisSummary|null;organization:OrganizationSummary|null;performance:PerformanceSummary|null}
const emptySnapshot:Snapshot={people:null,hris:null,organization:null,performance:null}

/** Render a permission-aware People & HR operational snapshot. */
export default function PeopleHrHome({workspace,onNavigate}:{workspace:AuthWorkspace;onNavigate:(page:Page)=>void}){
  const [snapshot,setSnapshot]=useState<Snapshot>(emptySnapshot)
  const [loading,setLoading]=useState(true)
  const [warning,setWarning]=useState('')
  const access=useMemo(()=>({people:canAccessPage(workspace,'people'),hris:canAccessPage(workspace,'hris'),organization:canAccessPage(workspace,'organization'),performance:canAccessPage(workspace,'performance')}),[workspace])

  /** Load each People & HR summary independently so sensitive/disabled areas do not block permitted ones. */
  const load=async()=>{
    setLoading(true);setWarning('')
    const requests:Array<Promise<{kind:keyof Snapshot;value:unknown}>>=[]
    if(access.people)requests.push(apiRequest<PeopleSummary>('/api/v1/people',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'people' as const,value})))
    if(access.hris)requests.push(apiRequest<HrisSummary>('/api/v1/hris/members',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'hris' as const,value})))
    if(access.organization)requests.push(apiRequest<OrganizationSummary>('/api/v1/organization',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'organization' as const,value})))
    if(access.performance)requests.push(apiRequest<PerformanceSummary>('/api/v1/performance/overview',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'performance' as const,value})))
    const results=await Promise.allSettled(requests)
    const next:Snapshot={...emptySnapshot};let failed=0
    for(const result of results){if(result.status==='rejected'){failed++;continue}next[result.value.kind]=result.value.value as never}
    setSnapshot(next);if(failed)setWarning('Some People & HR summary data could not be loaded. Access to the available workflows below is unchanged.');setLoading(false)
  }
  useEffect(()=>{void load()},[workspace.id,access.people,access.hris,access.organization,access.performance])

  const people=snapshot.people?.data??[]
  const activePeople=people.filter(row=>row.status==='active').length
  const hris=snapshot.hris?.data??[]
  const lifecycleAttention=hris.filter(row=>['onboarding','probation','offboarding'].includes(row.employment_stage)).length
  const departments=snapshot.organization?.departments?.length??0
  const teams=snapshot.organization?.teams?.length??0
  const goals=snapshot.performance?.goals??[]
  const activeGoals=goals.filter(row=>row.status==='active'||row.status==='at_risk').length
  const pendingReviews=(snapshot.performance?.reviews??[]).filter(row=>!['completed','approved'].includes(row.status)).length
  const hasData=access.people||access.hris||access.organization||access.performance

  return <Stack gap={12}>
    <Card><CardHeader title="People & HR snapshot" description="Role-aware workforce identity, lifecycle, organization and development signals. Sensitive HR details stay inside their permissioned pages." action={<Button size="sm" variant="ghost" loading={loading} onClick={()=>void load()}><RefreshCw size={13}/> Refresh</Button>}/><CardBody>
      {warning&&<Alert tone="warning">{warning}</Alert>}
      {!hasData?<EmptyState title="No People & HR areas are available for this role." text="Your role and module permissions determine which employee workflows you can open." contextualHelp/>:<Grid columns="repeat(auto-fit,minmax(150px,1fr))" gap={9}>
        {access.people&&<StatCard label="Visible people" value={loading?'…':String(people.length)} sub={`${activePeople} active`}/>} 
        {access.hris&&<StatCard label="HR lifecycle" value={loading?'…':String(hris.length)} sub={`${lifecycleAttention} onboarding/probation/offboarding`}/>} 
        {access.organization&&<StatCard label="Organization" value={loading?'…':String(departments)} sub={`${teams} teams`}/>} 
        {access.performance&&<StatCard label="Active goals" value={loading?'…':String(activeGoals)} sub={`${pendingReviews} review(s) in progress`}/>} 
      </Grid>}
    </CardBody></Card>
    <Grid columns="repeat(auto-fit,minmax(240px,1fr))" gap={9}>
      {access.people&&<Card><CardHeader title="People" description="Directory, workforce identity, role assignment and account security."/><CardBody><Stack gap={8}><Inline gap={8} align="center"><Users size={15}/><Text color="var(--text-2)">{people.length?`${people.length} people are visible in your current scope.`:'No people are currently visible in your scope.'}</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('people')}>Open People</Button></Stack></CardBody></Card>}
      {access.hris&&<Card><CardHeader title="HRIS" description="Employment profile, lifecycle, contracts, documents, assets and policy acknowledgements."/><CardBody><Stack gap={8}><Inline gap={8} align="center"><UserRoundCog size={15}/><Text color="var(--text-2)">{lifecycleAttention?`${lifecycleAttention} employee record(s) are in an active lifecycle transition.`:'No lifecycle transitions need attention in the visible records.'}</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('hris')}>Open HRIS</Button></Stack></CardBody></Card>}
      {access.organization&&<Card><CardHeader title="Organization" description="Departments, job titles, teams and reporting structure shared across WorkIntel."/><CardBody><Stack gap={8}><Inline gap={8} align="center"><Network size={15}/><Text color="var(--text-2)">{departments} department(s) and {teams} team(s) are configured.</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('organization')}>Open Organization</Button></Stack></CardBody></Card>}
      {access.performance&&<Card><CardHeader title="Performance & Growth" description="Goals, reviews, skills, learning, recognition and employee development."/><CardBody><Stack gap={8}><Inline gap={8} align="center"><BarChart3 size={15}/><Text color="var(--text-2)">{activeGoals} active goal(s) · {pendingReviews} review(s) in progress.</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('performance')}>Open Performance</Button></Stack></CardBody></Card>}
    </Grid>
  </Stack>
}
