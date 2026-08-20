import { ArrowRight, Compass, X } from 'lucide-react'
import type { AuthWorkspace } from '../auth/types'
import { Button, Card, CardBody, Inline, Stack, Text } from '../design-system'
import { useRoleExperience } from '../help/roleExperience'
import { useLocalization } from '../i18n/LocalizationContext'

/** Show one non-blocking, permission-aware first-run invitation until the member starts or dismisses M11 guidance. */
export default function FirstRunGuide({workspace}:{workspace:AuthWorkspace}){
 const experience=useRoleExperience(workspace),{t,text}=useLocalization()
 if(experience.loading||experience.state.seen||experience.state.dismissedHelp.includes('first-run')||!experience.guide?.tasks.length)return null
 /** Start the personal guide, then ask the application shell to open the contextual Help Center. */
 const start=async()=>{await experience.markSeen();window.dispatchEvent(new CustomEvent('workintel:open-help',{detail:{tab:'start'}}))}
 /** Dismiss only this personal first-run invitation without modifying workspace or business state. */
 const dismiss=async()=>{await experience.dismissHelp('first-run')}
 return <div className="ui-first-run-wrap" role="status" aria-live="polite"><Card className="ui-first-run-guide"><CardBody><Inline justify="space-between" gap={12} align="center"><Inline gap={10} align="center"><span className="ui-first-run-guide__icon"><Compass size={17}/></span><Stack gap={2}><Text as="strong" size={12.5}>{t('help.welcome_title')}</Text><Text size={10.75} color="var(--text-2)">{t('help.welcome_text')}</Text><Text size={9.75} color="var(--text-3)">{text(experience.guide.title)}</Text></Stack></Inline><Inline gap={6} align="center"><Button size="sm" variant="primary" onClick={()=>void start()}>{t('help.start_guide')} <ArrowRight className="ui-help-directional-icon" size={12}/></Button><Button size="sm" variant="ghost" onClick={()=>void dismiss()}>{t('help.not_now')}</Button><Button size="sm" variant="ghost" iconOnly aria-label={t('help.not_now')} onClick={()=>void dismiss()}><X size={13}/></Button></Inline></Inline></CardBody></Card></div>
}
