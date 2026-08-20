import { useEffect, useMemo, useState } from 'react'
import { Building2, CreditCard, RefreshCw, ReceiptText } from 'lucide-react'
import { canAccessPage } from '../access'
import { apiRequest } from '../api/client'
import type { AuthWorkspace } from '../auth/types'
import type { Page } from '../components/Sidebar'
import { Alert, Button, Card, CardBody, CardHeader, EmptyState, Grid, Inline, Stack, StatCard, Text } from '../design-system'

type ClientSummary={id:number;status:string;projects_count?:number;active_projects_count?:number;portal_accounts_count?:number;invoices_count?:number}
type CommerceSummary={gateways?:Array<{enabled:boolean;health_status:string}>;schedules?:Array<{status:string}>;recent_checkouts?:Array<{status:string}>}
type Snapshot={clients:ClientSummary[];commerce:CommerceSummary|null}
const emptySnapshot:Snapshot={clients:[],commerce:null}

/** Render live Clients & Commerce signals without exposing data outside the member's existing page permissions. */
export default function ClientsCommerceHome({workspace,onNavigate}:{workspace:AuthWorkspace;onNavigate:(page:Page)=>void}){
  const [snapshot,setSnapshot]=useState<Snapshot>(emptySnapshot);const [loading,setLoading]=useState(true);const [warning,setWarning]=useState('')
  const access=useMemo(()=>({clients:canAccessPage(workspace,'clients'),commerce:canAccessPage(workspace,'client-commerce')}),[workspace])
  /** Load each visible area independently so one commerce integration failure never hides the client directory. */
  const load=async()=>{setLoading(true);setWarning('');const requests:Array<Promise<{kind:keyof Snapshot;value:unknown}>>=[];if(access.clients)requests.push(apiRequest<{data:ClientSummary[]}>('/api/v1/clients',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'clients' as const,value:value.data})));if(access.commerce)requests.push(apiRequest<CommerceSummary>('/api/v1/client-commerce',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'commerce' as const,value})));const results=await Promise.allSettled(requests);const next:Snapshot={...emptySnapshot};let failed=0;for(const result of results){if(result.status==='rejected'){failed++;continue}if(result.value.kind==='clients')next.clients=result.value.value as ClientSummary[];else next.commerce=result.value.value as CommerceSummary}setSnapshot(next);if(failed)setWarning('Some client-commerce summary data could not be loaded. Available client and payment workflows remain accessible below.');setLoading(false)}
  useEffect(()=>{void load()},[workspace.id,access.clients,access.commerce])
  const activeClients=snapshot.clients.filter(row=>row.status==='active').length
  const activeProjects=snapshot.clients.reduce((sum,row)=>sum+Number(row.active_projects_count??0),0)
  const portalAccounts=snapshot.clients.reduce((sum,row)=>sum+Number(row.portal_accounts_count??0),0)
  const enabledGateways=(snapshot.commerce?.gateways??[]).filter(row=>row.enabled).length
  const unhealthyGateways=(snapshot.commerce?.gateways??[]).filter(row=>row.enabled&&row.health_status==='failed').length
  const activeSchedules=(snapshot.commerce?.schedules??[]).filter(row=>row.status==='active').length
  const pendingCheckouts=(snapshot.commerce?.recent_checkouts??[]).filter(row=>['pending','processing'].includes(row.status)).length
  const hasData=access.clients||access.commerce
  return <Stack gap={12}><Card><CardHeader title="Clients & commerce snapshot" description="Client relationships, portal adoption, recurring invoices and workspace-owned payment readiness in one place." action={<Button size="sm" variant="ghost" loading={loading} onClick={()=>void load()}><RefreshCw size={13}/> Refresh</Button>}/><CardBody>{warning&&<Alert tone="warning">{warning}</Alert>}{!hasData?<EmptyState title="No client-commerce areas are available for this role." text="Your role and module permissions control client records and payment administration." contextualHelp/>:<Grid columns="repeat(auto-fit,minmax(150px,1fr))" gap={9}>{access.clients&&<><StatCard label="Active clients" value={loading?'…':String(activeClients)} sub={`${activeProjects} active project(s)`}/><StatCard label="Portal accounts" value={loading?'…':String(portalAccounts)} sub="Activated external client access"/></>}{access.commerce&&<><StatCard label="Payment gateways" value={loading?'…':String(enabledGateways)} sub={unhealthyGateways?`${unhealthyGateways} enabled gateway(s) need attention`:'Enabled and workspace-owned'}/><StatCard label="Recurring billing" value={loading?'…':String(activeSchedules)} sub={`${pendingCheckouts} pending checkout(s)`}/></>}</Grid>}</CardBody></Card><Grid columns="repeat(auto-fit,minmax(250px,1fr))" gap={9}>{access.clients&&<Card><CardHeader title="Clients" description="Client records, portal access, invoices, payments and client-facing reports."/><CardBody><Stack gap={8}><Inline gap={8} align="center"><Building2 size={15}/><Text color="var(--text-2)">{activeClients} active client(s) are visible in your scope.</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('clients')}>Open Clients</Button></Stack></CardBody></Card>}{access.commerce&&<Card><CardHeader title="Client Payments" description="Workspace gateways, Pay Now settlement and recurring invoice automation."/><CardBody><Stack gap={8}><Inline gap={8} align="center">{unhealthyGateways?<CreditCard size={15}/>:<ReceiptText size={15}/>}<Text color="var(--text-2)">{unhealthyGateways?`${unhealthyGateways} gateway(s) need connection review.`:`${activeSchedules} recurring schedule(s) are active.`}</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('client-commerce')}>Open Client Payments</Button></Stack></CardBody></Card>}</Grid></Stack>
}
