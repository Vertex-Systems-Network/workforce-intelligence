import type { LocaleCode } from './catalog'
import {copy,coreTerms,type CoreLocale,type LocaleCopy} from './page-copy/core'
import { corePhrases } from './page-copy/core-phrases'
import { workforceTerms,workforcePhrases } from './page-copy/workforce'
import { businessTerms,businessPhrases } from './page-copy/business'
import { studiosTerms,studiosPhrases } from './page-copy/studios'
import { collaborationTerms,collaborationPhrases } from './page-copy/collaboration'
import { helpTerms,helpPhrases } from './page-copy/help'

/** Aggregated short UI vocabulary. Domain modules own the source entries. */
export const pageCopyTerms:Record<string,LocaleCopy>={...coreTerms,...workforceTerms,...businessTerms,...studiosTerms,...collaborationTerms,...helpTerms}
/** All exact UI phrases aggregated from domain-owned registries. */
const allPageCopyPhrases:Record<string,LocaleCopy>={...corePhrases,...workforcePhrases,...businessPhrases,...collaborationPhrases,...helpPhrases,...studiosPhrases}
/** Preserve the legacy public base-phrase registry contract while domain modules own the physical entries. */
const legacyBasePhraseKeys=["No project","Save Policy","Effective from","Create poll","Continue to sign in","Create invitation","Create Key","New password","Select employee","Account recovery","Add Expense","Approved domains","Assign Shift","Billable project","Choose from Media Library","Code expires in","Compliance pack","Custom Employee Fields","Day of month","Due date","Edit history","Email verification","End Break","Generate Client Report","Generate Code","Library root","Mark Paid","Message deleted","No department","Profile photo","Role name","Save settings","Select role","Start Free","State / region","Tracked Usage","Workforce Intelligence","All projects","All client projects","No saved reports","No report runs","Saved Reports","Recent Runs","Report Builder","Date Range","Run Now","Run Snapshot","Save Report","Create Schedule","Last Run","Next Run","Last 7 days","Last 30 days","This week","Last week","This month","Last month","Bar Chart","Line Chart","Area Chart","No matching records","No records","No data","Read only","Manage widgets","Previous week","Next week","No notifications yet.","No members available in your scope.","No task tags yet.","No projects match the current view.","No direct project expenses.","No screenshots for this filter","No internal permissions are assigned to this role.","No module-level restrictions for this role.","Checking subscription, entitlements and usage.","Effective features for this workspace","Hard limits are enforced by the API","Platform operator access is required.","Choose project…","Search tasks, people or tags…","Add a progress note, handoff, or decision…","Add or replace one employee shift in this roster. Changes stay draft until published.","Assign one shift to multiple people and days in the selected week.","Define scheduled hours, break allowance and grace period.","Create the first project to start organizing work.","Clear the search or table filters to see more projects.","Create a task or switch to the board to add work.","Clear project, status, search or table filters.","Upload and crop a photo, or choose an existing image from Media Library.","Personal identity, profile photo and account preferences","Change your password and revoke other signed-in sessions","Your workspace role, permissions and personal sign-in security","Preparing datasets, saved reports and run history.","Configure one reusable report definition.","No rows match these filters","Requests use the unified approval inbox.","Approved time, expenses and procurement only.","Only approved claims enter job cost and reimbursement.","Working days and available balance are validated against the selected policy.","Rules are enforced by the backend when requests are submitted and approved.","Provider credentials stay seller-side. Workspace buyers only see enabled provider names.","Paid plans use the P11 checkout lifecycle; activation occurs after provider confirmation.","Manual-provider invoices can be settled here; provider-managed invoices use their adapter/webhook.","Screenshot capture is controlled by workspace policy. Files are private and only permission-scoped users can view them.","Server stores only a SHA-256 token hash. Raw scan token is displayed once.","Enrollment links a physical installation to one workspace member. Re-enrollment rotates the device token.","Effective date","Leave type","New conversation","Pay Type","Record Payment","Remove condition","Retention days","Save changes","Save Workflow","Secondary color","Set up authenticator","Start date","Sticky page header","Submit Request","Support email","Target type","Tracked today","Work email","Workspace name","Department ID","Team ID","Edit price","Recalculate","Period start","Period end"] as const
export const pageCopyPhrases:Record<string,LocaleCopy>=Object.fromEntries(legacyBasePhraseKeys.map(key=>[key,allPageCopyPhrases[key]]))
/** M11 role/help phrases were direct-only in the legacy translator and are intentionally excluded from templated term composition. */
const directOnlyPhraseKeys=new Set<string>(["Set up your workspace for reliable operations","Start with workspace identity and access, then configure the operating modules your team will use every day.","Workspace settings reflect your organization.","Members have intentional roles and scopes.","Core work, time and reporting modules are ready for use.","Keep workspace configuration understandable and safe","Validate access, modules and settings before supporting daily operations.","Permissions are intentional.","Workspace configuration is supportable.","Operational modules expose the right data.","Run the team from work, approvals and time signals","Use the manager path to keep delivery, team workload and exceptions visible without browsing administrative areas.","Work is assigned and prioritized.","Approvals and attendance exceptions are visible.","Team communication stays linked to work.","Establish clean people and lifecycle operations","Start with people records and organization structure, then validate attendance, leave and performance workflows.","People records are complete within your scope.","Organization relationships are understandable.","HR exceptions and development workflows are visible.","Prepare payroll from approved workforce evidence","Validate attendance and approved time before operating payroll, compliance and finance workflows.","Time inputs are reviewable.","Payroll runs use approved evidence.","Compliance and finance outputs are discoverable.","Know what to do, when to do it, and where to find your records","Your Start Here path focuses on assigned work, attendance, schedule, time, requests and collaboration.","You can find assigned work.","You know where attendance and time records live.","Requests, pay and conversations are easy to return to."])

const lowerTermIndex=new Map(Object.entries(pageCopyTerms).map(([key,value])=>[key.toLowerCase(),value]))

const verbs:Record<string,LocaleCopy>={
  Add:copy('{{object}} Ekle','Добавить {{object}}','{{object}} شامل کریں','إضافة {{object}}'),
  Create:copy('{{object}} Oluştur','Создать {{object}}','{{object}} بنائیں','إنشاء {{object}}'),
  Edit:copy('{{object}} Düzenle','Изменить {{object}}','{{object}} ترمیم کریں','تعديل {{object}}'),
  Delete:copy('{{object}} Sil','Удалить {{object}}','{{object}} حذف کریں','حذف {{object}}'),
  Select:copy('{{object}} Seç','Выбрать {{object}}','{{object}} منتخب کریں','اختيار {{object}}'),
  Choose:copy('{{object}} Seç','Выбрать {{object}}','{{object}} منتخب کریں','اختيار {{object}}'),
  Save:copy('{{object}} Kaydet','Сохранить {{object}}','{{object}} محفوظ کریں','حفظ {{object}}'),
  Manage:copy('{{object}} Yönet','Управлять: {{object}}','{{object}} منظم کریں','إدارة {{object}}'),
  Generate:copy('{{object}} Oluştur','Создать {{object}}','{{object}} تیار کریں','إنشاء {{object}}'),
  Assign:copy('{{object}} Ata','Назначить {{object}}','{{object}} تفویض کریں','تعيين {{object}}'),
  Remove:copy('{{object}} Kaldır','Удалить {{object}}','{{object}} ہٹائیں','إزالة {{object}}'),
  View:copy('{{object}} Görüntüle','Просмотреть {{object}}','{{object}} دیکھیں','عرض {{object}}'),
  Open:copy('{{object}} Aç','Открыть {{object}}','{{object}} کھولیں','فتح {{object}}'),
  Download:copy('{{object}} İndir','Скачать {{object}}','{{object}} ڈاؤن لوڈ کریں','تنزيل {{object}}'),
}

const qualifiers:Record<string,LocaleCopy>={
  All:copy('Tüm {{object}}','Все {{object}}','تمام {{object}}','كل {{object}}'),
  New:copy('Yeni {{object}}','Новый: {{object}}','نیا {{object}}','{{object}} جديد'),
  Previous:copy('Önceki {{object}}','Предыдущий: {{object}}','پچھلا {{object}}','{{object}} السابق'),
  Next:copy('Sonraki {{object}}','Следующий: {{object}}','اگلا {{object}}','{{object}} التالي'),
  Active:copy('Aktif {{object}}','Активные {{object}}','فعال {{object}}','{{object}} النشطة'),
  Approved:copy('Onaylı {{object}}','Утверждённые {{object}}','منظور شدہ {{object}}','{{object}} المعتمدة'),
  Allowed:copy('İzin verilen {{object}}','Разрешённые {{object}}','اجازت یافتہ {{object}}','{{object}} المسموح بها'),
  Current:copy('Mevcut {{object}}','Текущий {{object}}','موجودہ {{object}}','{{object}} الحالي'),
  Latest:copy('En son {{object}}','Последний {{object}}','تازہ ترین {{object}}','أحدث {{object}}'),
  My:copy('{{object}}','Мои {{object}}','میرے {{object}}','{{object}} الخاصة بي'),
  Any:copy('Herhangi bir {{object}}','Любые {{object}}','کوئی بھی {{object}}','أي {{object}}'),
  Default:copy('Varsayılan {{object}}','{{object}} по умолчанию','ڈیفالٹ {{object}}','{{object}} الافتراضي'),
}

/** Translate a registered short domain term or return null when it is business/user data. */
function term(locale:CoreLocale,value:string):string|null{
  const source=value.trim()
  const exact=(directOnlyPhraseKeys.has(source)?undefined:allPageCopyPhrases[source])??pageCopyTerms[source]??lowerTermIndex.get(source.toLowerCase())
  if(exact)return exact[locale]
  const words=source.split(/\s+/)
  if(words.length>5)return null
  const translated=words.map(word=>(pageCopyTerms[word]??lowerTermIndex.get(word.toLowerCase()))?.[locale]??null)
  if(translated.some(value=>value===null))return null
  return translated.join(' ')
}

/** Apply one product-safe sentence template to short static labels. */
function templated(locale:CoreLocale,value:string):string|null{
  let match=value.match(/^(Add|Create|Edit|Delete|Select|Choose|Save|Manage|Generate|Assign|Remove|View|Open|Download)\s+(.+?)(…|\.\.\.)?$/)
  if(match){const object=term(locale,match[2]);if(object){const pattern=verbs[match[1]][locale].replace('{{object}}',object);return pattern+(match[3]?'…':'')}}
  match=value.match(/^(All|New|Previous|Next|Active|Approved|Allowed|Current|Latest|My|Any|Default)\s+(.+)$/)
  if(match){const object=term(locale,match[2]);if(object)return qualifiers[match[1]][locale].replace('{{object}}',object)}
  match=value.match(/^No\s+(.+?)(\s+yet)?\.?$/i)
  if(match){const object=term(locale,match[1]);if(object){if(locale==='tr')return `${object} yok${match[2]?' henüz':''}.`.replace(' yok henüz',' henüz yok');if(locale==='ru')return `${match[2]?'Пока нет':'Нет'} ${object.toLowerCase()}.`;if(locale==='ur')return `${match[2]?'ابھی ':''}کوئی ${object} نہیں۔`;return `${match[2]?'لا يوجد حتى الآن':'لا يوجد'} ${object}.`}}
  match=value.match(/^Loading\s+(.+?)(…|\.\.\.)$/i)
  if(match){const object=term(locale,match[1]);if(object){if(locale==='tr')return `${object} yükleniyor…`;if(locale==='ru')return `Загрузка: ${object}…`;if(locale==='ur')return `${object} لوڈ ہو رہا ہے…`;return `جارٍ تحميل ${object}…`}}
  match=value.match(/^Search\s+(.+?)(…|\.\.\.)$/i)
  if(match){const object=term(locale,match[1]);if(object){if(locale==='tr')return `${object} ara…`;if(locale==='ru')return `Поиск: ${object}…`;if(locale==='ur')return `${object} تلاش کریں…`;return `بحث في ${object}…`}}
  match=value.match(/^(.+):$/)
  if(match){const label=term(locale,match[1]);if(label)return `${label}:`}
  match=value.match(/^(\d+)\s+(minutes?|hours?|days?)$/i)
  if(match){const n=match[1],unit=match[2].toLowerCase();const units={minute:copy('dakika','минута','منٹ','دقيقة'),minutes:copy('dakika','минут','منٹ','دقائق'),hour:copy('saat','час','گھنٹہ','ساعة'),hours:copy('saat','часов','گھنٹے','ساعات'),day:copy('gün','день','دن','يوم'),days:copy('gün','дней','دن','أيام')} as Record<string,LocaleCopy>;return `${n} ${units[unit][locale]}`}
  match=value.match(/^(\d+)-(hour|day)$/i)
  if(match){const n=match[1],unit=match[2].toLowerCase();if(locale==='tr')return `${n} ${unit==='hour'?'saatlik':'günlük'}`;if(locale==='ru')return `${n}-${unit==='hour'?'часовой':'дневный'}`;if(locale==='ur')return `${n} ${unit==='hour'?'گھنٹے':'دن'} کا`;return `${n} ${unit==='hour'?'ساعة':'أيام'}`}
  match=value.match(/^(.+?)\s+(settings|history|policy|rules|endpoint|ID|name|date|days|hours|status|type|access)$/i)
  if(match){const subject=term(locale,match[1]),kind=term(locale,match[2]);if(subject&&kind){if(locale==='tr')return `${subject} ${kind}`;if(locale==='ru')return `${kind}: ${subject}`;if(locale==='ur')return `${subject} ${kind}`;return `${kind} ${subject}`}}
  return null
}


/** Translate static legacy page copy while leaving unknown/dynamic business data unchanged. */
export function translatePageCopy(locale:LocaleCode,value:string):string{
  if(locale==='en'||!value.trim())return value
  if(!['tr','ru','ur','ar'].includes(locale))return value
  const core=locale as CoreLocale
  const leading=value.match(/^\s*/)?.[0]??'';const trailing=value.match(/\s*$/)?.[0]??'';const source=value.trim()
  const translated=(allPageCopyPhrases[source]??pageCopyTerms[source])?.[core]??templated(core,source)??term(core,source)
  return translated?`${leading}${translated}${trailing}`:value
}

/** Return whether a static English UI phrase has a deterministic localized rendering. */
export function hasPageCopyTranslation(value:string):boolean{
  const source=value.trim();return Boolean(allPageCopyPhrases[source]||pageCopyTerms[source]||templated('tr',source)||term('tr',source))
}
