import { useState } from 'react'
import { CalendarClock, LayoutTemplate } from 'lucide-react'
import Scheduling from './Scheduling'
import Shifts from './Shifts'
import { Segmented } from '../design-system'
import { useLocalization } from '../i18n/LocalizationContext'

/** Combine roster scheduling and reusable shift templates behind one navigation item. */
export default function SchedulingHub({initialTab='board'}:{initialTab?:'board'|'templates'}={}){
  const {t}=useLocalization()
  const [tab,setTab]=useState<'board'|'templates'>(initialTab)
  return <div className="scheduling-hub">
    <div className="scheduling-hub__tabs"><Segmented value={tab} onChange={value=>setTab(value as 'board'|'templates')} options={[
      {value:'board',label:<><CalendarClock size={13}/>{t('scheduling.board')}</>},
      {value:'templates',label:<><LayoutTemplate size={13}/>{t('scheduling.templates')}</>},
    ]}/></div>
    {tab==='board'?<Scheduling/>:<Shifts/>}
  </div>
}
