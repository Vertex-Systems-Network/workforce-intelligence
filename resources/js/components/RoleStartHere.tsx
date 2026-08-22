import { ArrowRight, Check, Circle, Sparkles } from 'lucide-react'
import type { AuthWorkspace } from '../auth/types'
import type { Page } from './Sidebar'
import type { WorkspaceModuleId } from '../moduleCatalog'
import { workspaceModuleForPage } from '../moduleCatalog'
import { Alert, Badge, Button, Card, CardBody, CardHeader, Inline, Progress, Stack, Text } from '../design-system'
import { useRoleExperience } from '../help/roleExperience'
import { useLocalization } from '../i18n/LocalizationContext'

/** Render a compact permission-aware Start Here checklist; full guidance remains in Help Center. */
export default function RoleStartHere({workspace,onNavigate,moduleId,compact=true}:{workspace:AuthWorkspace;onNavigate:(page:Page)=>void;moduleId?:WorkspaceModuleId;compact?:boolean}){
 const experience=useRoleExperience(workspace),guide=experience.guide,{t,text}=useLocalization()
 if(!guide)return null
 const scopedTasks=moduleId?guide.tasks.filter(task=>workspaceModuleForPage(task.page)?.id===moduleId):guide.tasks
 const tasks=scopedTasks.slice(0,compact?3:6)
 if(!tasks.length)return null
 const done=tasks.filter(task=>experience.state.completed.includes(task.id)).length,percent=Math.round(done/tasks.length*100),next=tasks.find(task=>!experience.state.completed.includes(task.id))
 return <Card className="ui-role-start-here"><CardHeader title={moduleId?t('help.start_module'):t('help.start_here')} description={moduleId?t('help.module_guidance',{role:text(guide.label)}):text(guide.summary)} action={<Inline gap={6}><Badge tone={percent===100?'success':'accent'}>{done}/{tasks.length}</Badge>{next&&<Button size="sm" variant="outline" onClick={()=>onNavigate(next.page)}>{t('help.next')} <ArrowRight className="ui-help-directional-icon" size={13}/></Button>}</Inline>}/><CardBody><Stack gap={10}>{experience.error&&<Alert tone="warning">{experience.error}</Alert>}<Inline gap={8} align="center"><Sparkles size={15}/><Text className="ui-role-start-here__summary" color="var(--text-2)">{text(guide.title)}</Text></Inline><Progress value={percent} tone={percent===100?'success':'accent'} label={t('help.progress',{percent})}/><div className="ui-role-start-here__tasks">{tasks.map(task=>{const complete=experience.state.completed.includes(task.id);return <div key={task.id} className={`ui-role-start-here__task${complete?' is-complete':''}`}><Button variant="ghost" size="sm" iconOnly aria-label={complete?t('help.mark_incomplete',{item:text(task.title)}):t('help.mark_complete',{item:text(task.title)})} aria-pressed={complete} onClick={()=>void experience.toggleTask(task.id)}>{complete?<Check size={15}/>:<Circle size={15}/>}</Button><div><strong>{text(task.title)}</strong><small>{text(task.description)}</small></div><Button size="sm" variant="ghost" onClick={()=>onNavigate(task.page)}>{t('help.open')} <ArrowRight className="ui-help-directional-icon" size={13}/></Button></div>})}</div>{!compact&&<Text className="ui-role-start-here__note" color="var(--text-3)">{t('help.personal_checklist')}</Text>}</Stack></CardBody></Card>
}
