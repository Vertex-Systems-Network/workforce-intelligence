import { Coffee, LogIn, LogOut, RefreshCw } from 'lucide-react'
import { useEffect, useState } from 'react'
import { apiRequest } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { hasPermission } from '../access'
import { attendanceActionLocation, type AttendancePolicyLite } from '../attendance/location'
import { Badge, Button, Tooltip, Inline } from '../design-system'

type BreakData={id:number;type:string;ended_at:string|null}
type RecordData={clock_in_at:string|null;clock_out_at:string|null;status:string;breaks?:BreakData[]}
type Row={member_id:number;record:RecordData|null;active_break:BreakData|null}
type Payload={rows:Row[];current_member_id:number;policy:AttendancePolicyLite}

/** Handles the attendance quick action operation for the WorkIntel client. */ export default function AttendanceQuickAction(){
  const {session}=useAuth(); const workspace=session?.user.workspaces.find(item=>item.id===session.user.activeWorkspaceId)
  const [row,setRow]=useState<Row|null>(null); const [policy,setPolicy]=useState<AttendancePolicyLite|null>(null); const [loading,setLoading]=useState(false); const [error,setError]=useState('')
  const canUse=hasPermission(workspace,'attendance.view_own')||hasPermission(workspace,'attendance.manage')
  /** Loads load data required by the current view. */ const load=async(silent=true)=>{if(!workspace||!canUse)return;try{const data=await apiRequest<Payload>('/api/v1/attendance',{workspaceId:workspace.id,silent});setRow(data.rows.find(item=>item.member_id===data.current_member_id)??null);setPolicy(data.policy);setError('')}catch(err){setError(err instanceof Error?err.message:'Attendance unavailable.')}}
  useEffect(()=>{void load(true);const id=window.setInterval(()=>void load(true),30000);return()=>window.clearInterval(id)},[workspace?.id,canUse])
  if(!workspace||!canUse)return null
  const record=row?.record
  /** Handles the action operation for the WorkIntel client. */ const action=async(type:'in'|'out'|'break'|'end-break')=>{setLoading(true);setError('');try{
    const location=await attendanceActionLocation(policy)
    if(type==='in'||type==='out')await apiRequest(`/api/v1/attendance/clock-${type}`,{method:'POST',workspaceId:workspace.id,body:JSON.stringify(location)})
    if(type==='break')await apiRequest('/api/v1/attendance/breaks/start',{method:'POST',workspaceId:workspace.id,body:JSON.stringify({...location,type:'break',paid:false})})
    if(type==='end-break'&&row?.active_break)await apiRequest(`/api/v1/attendance/breaks/${row.active_break.id}/end`,{method:'POST',workspaceId:workspace.id,body:JSON.stringify(location)})
    await load(false); window.dispatchEvent(new CustomEvent('workintel:attendance-changed'))
  }catch(err){setError(err instanceof Error?err.message:'Attendance action failed.')}finally{setLoading(false)}}
  const button=!record?.clock_in_at
    ? <Button variant="primary" size="sm" loading={loading} onClick={()=>void action('in')}><LogIn size={13}/> Clock In</Button>
    : record.clock_out_at
      ? <Badge tone="success">Day Complete</Badge>
      : row?.active_break
        ? <Button variant="secondary" size="sm" loading={loading} onClick={()=>void action('end-break')}><Coffee size={13}/> End Break</Button>
        : <Inline gap={6}><Button variant="outline" size="sm" loading={loading} onClick={()=>void action('break')}><Coffee size={13}/> Break</Button><Button variant="danger" size="sm" loading={loading} onClick={()=>void action('out')}><LogOut size={13}/> Clock Out</Button></Inline>
  return error?<Tooltip content={error}><Button variant="outline" size="sm" onClick={()=>void load(false)}><RefreshCw size={13}/> Attendance</Button></Tooltip>:button
}
