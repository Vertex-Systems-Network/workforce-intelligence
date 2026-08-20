import { useEffect, useMemo, useState } from 'react'
import { CalendarCheck2, CalendarClock, Clock3, RefreshCw, Umbrella } from 'lucide-react'
import { canAccessPage } from '../access'
import { apiRequest } from '../api/client'
import type { AuthWorkspace } from '../auth/types'
import type { Page } from '../components/Sidebar'
import { Alert, Button, Card, CardBody, CardHeader, EmptyState, Grid, Inline, Stack, StatCard, Text } from '../design-system'

type AttendanceSummary={current_member_id?:number;rows?:Array<{member_id:number;display_status:string;record?:{late_minutes?:number;overtime_minutes?:number}|null}>}
type TimesheetSummary={summary?:{tracked_seconds?:number;pending_count?:number;approved_count?:number};rows?:Array<{member_id:number;period_status:string}>;current_member_id?:number}
type LeaveSummary={requests?:Array<{member?:{id?:number};status:string}>;balances?:Array<{remaining:number}>;current_member_id?:number;can_manage?:boolean}
type ScheduleSummary={assignments?:Array<{member_id:number;status:string}>;open_shifts?:Array<{status:string;slots:number;claimed_slots:number}>;analysis?:{warnings?:Array<unknown>};current_member_id?:number;can_manage?:boolean}
type Snapshot={attendance:AttendanceSummary|null;timesheets:TimesheetSummary|null;leave:LeaveSummary|null;schedule:ScheduleSummary|null}
const emptySnapshot:Snapshot={attendance:null,timesheets:null,leave:null,schedule:null}

/** Format tracked seconds into a compact hours/minutes label for module summaries. */
function formatDuration(seconds:number){if(!seconds)return '0m';const hours=Math.floor(seconds/3600);const minutes=Math.floor((seconds%3600)/60);return hours?`${hours}h${minutes?` ${minutes}m`:''}`:`${minutes}m`}

/** Render a permission-aware Time & Attendance operational snapshot. */
export default function TimeAttendanceHome({workspace,onNavigate}:{workspace:AuthWorkspace;onNavigate:(page:Page)=>void}){
  const [snapshot,setSnapshot]=useState<Snapshot>(emptySnapshot)
  const [loading,setLoading]=useState(true)
  const [warning,setWarning]=useState('')
  const access=useMemo(()=>({attendance:canAccessPage(workspace,'attendance'),time:canAccessPage(workspace,'time'),leave:canAccessPage(workspace,'leave'),schedule:canAccessPage(workspace,'schedule')}),[workspace])

  /** Load summary datasets independently so one unavailable workflow does not blank the whole module home. */
  const load=async()=>{
    setLoading(true);setWarning('')
    const requests:Array<Promise<{kind:keyof Snapshot;value:unknown}>>=[]
    if(access.attendance)requests.push(apiRequest<AttendanceSummary>('/api/v1/attendance',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'attendance' as const,value})))
    if(access.time)requests.push(apiRequest<TimesheetSummary>('/api/v1/timesheets/week',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'timesheets' as const,value})))
    if(access.leave)requests.push(apiRequest<LeaveSummary>('/api/v1/leave',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'leave' as const,value})))
    if(access.schedule)requests.push(apiRequest<ScheduleSummary>('/api/v1/scheduling/week',{workspaceId:workspace.id,silent:true}).then(value=>({kind:'schedule' as const,value})))
    const results=await Promise.allSettled(requests)
    const next:Snapshot={...emptySnapshot};let failed=0
    for(const result of results){if(result.status==='rejected'){failed++;continue}next[result.value.kind]=result.value.value as never}
    setSnapshot(next);if(failed)setWarning('Some time and attendance summary data could not be loaded. The available workflows below are still safe to use.');setLoading(false)
  }
  useEffect(()=>{void load()},[workspace.id,access.attendance,access.time,access.leave,access.schedule])

  const attendanceRows=snapshot.attendance?.rows??[]
  const ownAttendance=attendanceRows.find(row=>row.member_id===snapshot.attendance?.current_member_id)
  const present=attendanceRows.filter(row=>['present','late','wfh','partial'].includes(row.display_status)).length
  const late=attendanceRows.filter(row=>(row.record?.late_minutes??0)>0).length
  const pendingTime=snapshot.timesheets?.summary?.pending_count??0
  const tracked=snapshot.timesheets?.summary?.tracked_seconds??0
  const leaveRequests=snapshot.leave?.requests??[]
  const pendingLeave=leaveRequests.filter(row=>row.status==='pending').length
  const ownLeaveRemaining=(snapshot.leave?.balances??[]).reduce((sum,row)=>sum+Number(row.remaining||0),0)
  const scheduleAssignments=snapshot.schedule?.assignments??[]
  const ownAssignments=scheduleAssignments.filter(row=>row.member_id===snapshot.schedule?.current_member_id).length
  const openSlots=(snapshot.schedule?.open_shifts??[]).filter(row=>row.status==='open').reduce((sum,row)=>sum+Math.max(0,Number(row.slots||0)-Number(row.claimed_slots||0)),0)
  const warnings=snapshot.schedule?.analysis?.warnings?.length??0
  const hasData=access.attendance||access.time||access.leave||access.schedule

  return <Stack gap={12}>
    <Card><CardHeader title="Time & attendance snapshot" description="Live, permission-aware signals for attendance, submitted time, leave and scheduling. Use this area to understand what needs action today." action={<Button size="sm" variant="ghost" loading={loading} onClick={()=>void load()}><RefreshCw size={13}/> Refresh</Button>}/><CardBody>
      {warning&&<Alert tone="warning">{warning}</Alert>}
      {!hasData?<EmptyState title="No time workflows are available for this role." text="Your workspace administrator controls attendance, timesheet, leave and scheduling access." contextualHelp/>:<Grid columns="repeat(auto-fit,minmax(150px,1fr))" gap={9}>
        {access.attendance&&<StatCard label={workspace.role==='employee'?"Today's attendance":"Present today"} value={loading?'…':workspace.role==='employee'?(ownAttendance?.display_status??'Not started'):String(present)} sub={workspace.role==='employee'?'Your current attendance state':`${late} late in visible scope`}/>} 
        {access.time&&<StatCard label="Tracked this week" value={loading?'…':formatDuration(tracked)} sub={`${pendingTime} entr${pendingTime===1?'y':'ies'} pending approval`}/>} 
        {access.leave&&<StatCard label={snapshot.leave?.can_manage?'Pending leave':'Leave remaining'} value={loading?'…':snapshot.leave?.can_manage?String(pendingLeave):`${ownLeaveRemaining.toFixed(1)}d`} sub={snapshot.leave?.can_manage?'Requests awaiting review':'Across your visible balances'}/>} 
        {access.schedule&&<StatCard label={snapshot.schedule?.can_manage?'Roster coverage':'My scheduled shifts'} value={loading?'…':snapshot.schedule?.can_manage?String(scheduleAssignments.length):String(ownAssignments)} sub={snapshot.schedule?.can_manage?`${openSlots} open slot(s) · ${warnings} warning(s)`:'Current week'}/>} 
      </Grid>}
    </CardBody></Card>
    <Grid columns="repeat(auto-fit,minmax(240px,1fr))" gap={9}>
      {access.attendance&&<Card><CardHeader title="Attendance" description="Clock activity, breaks, lateness, overtime and attendance policy."/><CardBody><Stack gap={8}><Inline gap={8} align="center"><CalendarCheck2 size={15}/><Text color="var(--text-2)">{workspace.role==='employee'?(ownAttendance?.display_status?`Today's status is ${ownAttendance.display_status}.`:'Clock in when you begin work.'):`${present} visible member(s) are present today.`}</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('attendance')}>Open Attendance</Button></Stack></CardBody></Card>}
      {access.time&&<Card><CardHeader title="Timesheets" description="Weekly totals, manual time, submission, approvals and audit history."/><CardBody><Stack gap={8}><Inline gap={8} align="center"><Clock3 size={15}/><Text color="var(--text-2)">{pendingTime?`${pendingTime} visible time entr${pendingTime===1?'y':'ies'} still need approval.`:'No pending time-entry approvals in your visible scope.'}</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('time')}>Open Timesheets</Button></Stack></CardBody></Card>}
      {access.leave&&<Card><CardHeader title="Leave" description="Requests, balances, policies, approvals and the team leave calendar."/><CardBody><Stack gap={8}><Inline gap={8} align="center"><Umbrella size={15}/><Text color="var(--text-2)">{snapshot.leave?.can_manage?`${pendingLeave} request(s) are awaiting review.`:`${ownLeaveRemaining.toFixed(1)} day(s) remain across your visible balances.`}</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('leave')}>Open Leave</Button></Stack></CardBody></Card>}
      {access.schedule&&<Card><CardHeader title="Scheduling" description="Roster board, reusable shift templates, availability and shift changes."/><CardBody><Stack gap={8}><Inline gap={8} align="center"><CalendarClock size={15}/><Text color="var(--text-2)">{warnings?`${warnings} schedule warning(s) need review.`:openSlots?`${openSlots} open shift slot(s) are available.`:'No schedule warnings in the current week.'}</Text></Inline><Button size="sm" variant="outline" onClick={()=>onNavigate('schedule')}>Open Scheduling</Button></Stack></CardBody></Card>}
    </Grid>
  </Stack>
}
