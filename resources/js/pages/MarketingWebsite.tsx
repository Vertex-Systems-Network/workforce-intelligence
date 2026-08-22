import type { LucideIcon } from 'lucide-react'
import {
  ArrowRight, BarChart3, BriefcaseBusiness, CalendarCheck2, Check, CircleDollarSign,
  Clock3, FileText, FolderKanban, Gauge, Globe2, HardDrive, Images, KeyRound,
  LayoutDashboard, LockKeyhole, MessageSquareText, Monitor, Network, Play, Receipt,
  Settings2, ShieldCheck, Sparkles, Trash2, UserRoundCog, Users, WalletCards, Workflow,
} from 'lucide-react'
import { Button } from '../design-system'

type MarketingFeature = { name:string; description:string }
type MarketingSection = {
  id:string
  eyebrow:string
  title:string
  description:string
  icon:LucideIcon
  metric:string
  metricLabel:string
  features:MarketingFeature[]
}

const platformSections:MarketingSection[] = [
  {
    id:'command-center', eyebrow:'Command center', title:'Know what needs attention before opening another report.', icon:Gauge,
    description:'Role-aware home views combine live work, attendance, projects, tasks and alerts so owners, managers and employees start from the context that matters to them.',
    metric:'Live', metricLabel:'role-aware workspace signals',
    features:[
      {name:'Home',description:'Executive and role-specific operational overview.'},
      {name:'Live Team',description:'Working, idle, break, meeting and offline status.'},
    ],
  },
  {
    id:'work-management', eyebrow:'Work management', title:'Plan the work, assign ownership and keep approvals moving.', icon:FolderKanban,
    description:'Projects, tasks, approvals and controlled automations share one workspace and one permission model instead of becoming separate operational silos.',
    metric:'4', metricLabel:'connected work surfaces',
    features:[
      {name:'Approvals',description:'Requests, review queues and approval workflows.'},
      {name:'Projects',description:'Ownership, members, dates, delivery and financial context.'},
      {name:'Tasks',description:'Priorities, assignments, dependencies and board workflows.'},
      {name:'Automation Studio',description:'Trigger repeatable workflows with controlled execution.'},
    ],
  },
  {
    id:'collaboration', eyebrow:'Collaboration', title:'Keep work conversations attached to the workspace.', icon:MessageSquareText,
    description:'Team chat provides channels, direct messages, attachments, reactions, replies, pinned context and unread state without pushing operational conversations into a separate product.',
    metric:'1', metricLabel:'shared communication layer',
    features:[
      {name:'Team Chat',description:'Channels, direct messages, attachments and activity context.'},
      {name:'Message controls',description:'Replies, reactions, pins, unread markers and member context.'},
    ],
  },
  {
    id:'time-attendance', eyebrow:'Time & attendance', title:'Turn schedules and tracked hours into auditable workforce records.', icon:CalendarCheck2,
    description:'Scheduling, attendance, leave and timesheets use the same member and workspace context, making exceptions and approvals easier to trace.',
    metric:'24/7', metricLabel:'time and attendance visibility',
    features:[
      {name:'Scheduling',description:'Team schedules, shift planning and availability.'},
      {name:'Shift templates',description:'Reusable shift patterns and scheduling templates.'},
      {name:'Attendance',description:'Clock in/out, late arrivals, breaks and overtime.'},
      {name:'Leave',description:'Leave requests, balances and approval state.'},
      {name:'Timesheets',description:'Tracked time, billable status and approval history.'},
    ],
  },
  {
    id:'people-hr', eyebrow:'People & HR', title:'Give people operations one consistent source of workforce context.', icon:Users,
    description:'Profiles, HR records, reporting lines and performance workflows sit behind the same role and permission rules as the operational workspace.',
    metric:'360°', metricLabel:'people and organization context',
    features:[
      {name:'People',description:'Member directory, profiles and administrative scope.'},
      {name:'HRIS',description:'Employment records and HR information.'},
      {name:'Organization',description:'Teams, structure and reporting relationships.'},
      {name:'Performance',description:'Goals, reviews and growth context.'},
    ],
  },
  {
    id:'workforce-operations', eyebrow:'Workforce operations', title:'See activity without losing the difference between presence and productivity.', icon:Monitor,
    description:'Desktop and browser telemetry is separated into activity, apps, screenshots, field work and device health so each signal can be governed independently.',
    metric:'5', metricLabel:'operational telemetry surfaces',
    features:[
      {name:'Activity',description:'Tracked activity and work-state timelines.'},
      {name:'Apps & Sites',description:'Application and domain-level usage context.'},
      {name:'Screenshots',description:'Controlled capture, retention and privacy settings.'},
      {name:'Field Workforce',description:'Field work, forms and incident context.'},
      {name:'Devices',description:'Agent enrollment, health, commands and sync status.'},
      {name:'Desktop + browser agents',description:'Offline-safe and browser-side activity ingestion.'},
    ],
  },
  {
    id:'clients-commerce', eyebrow:'Clients & commerce', title:'Connect client delivery to the commercial side of the work.', icon:BriefcaseBusiness,
    description:'Client records, invoices, payments and client-facing project visibility are connected to the same workspace data rather than duplicated in a disconnected portal.',
    metric:'B2B', metricLabel:'client delivery and payment flow',
    features:[
      {name:'Clients',description:'Client records, projects and administrative ownership.'},
      {name:'Client payments',description:'Invoice payment options and checkout workflow.'},
      {name:'Recurring invoices',description:'Repeat billing schedules and invoice operations.'},
      {name:'Client Portal',description:'Secure project, invoice and report visibility for clients.'},
    ],
  },
  {
    id:'content-studio', eyebrow:'Content studio', title:'Create, publish and reuse business content from one governed library.', icon:Images,
    description:'Website Studio, document generation and the media library share assets and access rules so content stays reusable and traceable.',
    metric:'3', metricLabel:'connected content systems',
    features:[
      {name:'Website Studio',description:'Workspace sites, pages, publishing and form submissions.'},
      {name:'Documents',description:'Templates, generated documents and signing workflows.'},
      {name:'Media Library',description:'Folders, collections, versions, renditions and usage tracking.'},
    ],
  },
  {
    id:'finance-payroll', eyebrow:'Finance & payroll', title:'Translate approved work into payroll and financial context.', icon:CircleDollarSign,
    description:'Expenses, compensation, payroll runs, compliance and billing are separated by permission while remaining connected to people, time and project data.',
    metric:'4', metricLabel:'financial control surfaces',
    features:[
      {name:'Finance & expenses',description:'Expenses, reimbursements, procurement and job-cost context.'},
      {name:'Payroll',description:'Hourly, daily, monthly, yearly and project-based compensation.'},
      {name:'Payroll compliance',description:'Compliance, contractor and export workflows.'},
      {name:'Billing',description:'Workspace plan and commercial administration.'},
    ],
  },
  {
    id:'intelligence-reports', eyebrow:'Intelligence & reports', title:'Separate raw tracking from the decisions managers actually need.', icon:BarChart3,
    description:'Workforce intelligence and reports turn time, attendance, activity, projects and payroll data into permission-aware views for individuals, teams and leadership.',
    metric:'1→N', metricLabel:'signals transformed into decisions',
    features:[
      {name:'Workforce Intelligence',description:'Focused, productive, active, tracked and billable context.'},
      {name:'Reports',description:'Operational, team and leadership reporting surfaces.'},
    ],
  },
  {
    id:'administration', eyebrow:'Administration', title:'Control modules, access, settings and lifecycle without hidden rules.', icon:Settings2,
    description:'Administrative controls make workspace capabilities explicit: what is enabled, who can access it, how the workspace is branded, and how data is retained or restored.',
    metric:'Policy', metricLabel:'explicit workspace governance',
    features:[
      {name:'Modules',description:'Enable, label and expose workspace capabilities.'},
      {name:'Enterprise',description:'Identity, SCIM, security and enterprise governance controls.'},
      {name:'Access Control',description:'Roles, permissions, scopes and explicit access rules.'},
      {name:'Settings',description:'Workspace identity, locale, currency, timezone and appearance.'},
      {name:'Trash & lifecycle',description:'Restore and purge governed workspace records.'},
    ],
  },
  {
    id:'account-installation', eyebrow:'Account & installation', title:'Make setup, downloads and personal access understandable.', icon:HardDrive,
    description:'Installation guidance, release downloads and personal access visibility are kept separate from workspace administration so members know what they can change and why.',
    metric:'Self', metricLabel:'service and installation clarity',
    features:[
      {name:'Downloads',description:'Desktop release downloads and installation center guidance.'},
      {name:'My Access',description:'Personal account, role and effective workspace access.'},
    ],
  },
]

/** Render an illustrative product surface without pretending sample marketing data is live customer data. */
function ProductVisual({section,index}:{section:MarketingSection;index:number}){
  const rows=section.features.slice(0,4)
  return <div className="marketing-feature-visual" role="img" aria-label={`${section.title} product interface illustration`}>
    <div className="marketing-feature-visual__chrome"><span/><span/><span/><strong>WorkIntel · Product preview</strong></div>
    <div className="marketing-feature-visual__content">
      <div className="marketing-visual-grid">
        <div className="marketing-visual-card"><small>Workspace</small><strong>{String(index+1).padStart(2,'0')}</strong></div>
        <div className="marketing-visual-card"><small>Capability</small><strong>{section.metric}</strong></div>
        <div className="marketing-visual-card"><small>Status</small><strong>Ready</strong></div>
      </div>
      {index%3===1 ? <div className="marketing-visual-bars" aria-hidden="true">{Array.from({length:7},(_,item)=><span key={item}/>)}</div> : <div className="marketing-visual-table">{rows.map((feature,rowIndex)=><div className="marketing-visual-row" key={feature.name}><strong>{feature.name}</strong><span>{rowIndex%2===0?'Operational':'Configured'}</span><span className="marketing-visual-pill">{rowIndex%3===0?'Active':'Visible'}</span></div>)}</div>}
    </div>
  </div>
}

/** Render the public WorkIntel product website as a complete, accessible platform overview. */
export default function MarketingWebsite(){
  return <div className="marketing-site">
    <a className="ui-skip-link" href="#marketing-main">Skip to main content</a>
    <header className="marketing-header">
      <div className="marketing-shell marketing-header__inner">
        <a className="marketing-brand" href="/" aria-label="WorkIntel home"><span className="marketing-brand__mark" aria-hidden="true"><LayoutDashboard size={17}/></span><span>WorkIntel</span></a>
        <nav className="marketing-nav" aria-label="Marketing navigation">
          <a href="#platform">Platform</a>
          <a href="#workforce-operations">Tracking</a>
          <a href="#security">Security</a>
          <a href="#architecture">How it works</a>
        </nav>
        <div className="marketing-header__actions"><Button variant="ghost" onClick={()=>window.location.assign('/app')}>Log in</Button><Button variant="primary" onClick={()=>window.location.assign('/app')}>Open Workspace <ArrowRight size={14}/></Button></div>
      </div>
    </header>

    <main id="marketing-main" tabIndex={-1}>
      <div className="marketing-shell">
        <section className="marketing-hero" aria-labelledby="marketing-hero-title">
          <div className="marketing-hero__copy">
            <span className="marketing-eyebrow"><Sparkles size={13}/> Workforce intelligence platform</span>
            <h1 id="marketing-hero-title">Run work, time, people and operations from one system.</h1>
            <p className="marketing-hero__lead">WorkIntel connects projects, time tracking, attendance, workforce activity, HR, payroll, clients, content and reporting without collapsing them into one confusing screen.</p>
            <div className="marketing-hero__actions"><Button size="lg" variant="primary" onClick={()=>window.location.assign('/app')}>Open Workspace <ArrowRight size={15}/></Button><Button size="lg" variant="outline" onClick={()=>document.getElementById('platform')?.scrollIntoView({behavior:'smooth'})}><Play size={15}/> Explore platform</Button></div>
            <div className="marketing-trust"><span><Check size={14}/> Permission-aware</span><span><Check size={14}/> Desktop + browser tracking</span><span><Check size={14}/> Privacy controls</span><span><Check size={14}/> Responsive workspace</span></div>
          </div>

          <div className="marketing-product-frame" role="img" aria-label="WorkIntel workspace dashboard product preview">
            <div className="marketing-product-window">
              <div className="marketing-product-toolbar"><i/><i/><i/><strong>Acme Corp · Command Center</strong></div>
              <div className="marketing-product-body">
                <div className="marketing-product-sidebar" aria-hidden="true"><div className="marketing-product-navline is-active"/>{Array.from({length:8},(_,index)=><div className="marketing-product-navline" style={{width:`${58+(index%3)*12}%`}} key={index}/>)}</div>
                <div className="marketing-product-main"><div className="marketing-product-title"/><div className="marketing-product-copy"/><div className="marketing-product-kpis"><div className="marketing-product-kpi"><span>Working now</span><strong>17</strong></div><div className="marketing-product-kpi"><span>Tracked today</span><strong>142h</strong></div><div className="marketing-product-kpi"><span>Attendance</span><strong>86%</strong></div></div><div className="marketing-product-chart"/></div>
              </div>
            </div>
          </div>
        </section>

        <section className="marketing-metrics" aria-label="Platform scope">
          <div className="marketing-metric"><strong>12</strong><span>product areas presented as clear operational modules</span></div>
          <div className="marketing-metric"><strong>1</strong><span>workspace identity and permission model across the platform</span></div>
          <div className="marketing-metric"><strong>5</strong><span>supported localization catalogs in the current application</span></div>
          <div className="marketing-metric"><strong>AA</strong><span>WCAG-oriented keyboard, contrast, reflow and naming contracts</span></div>
        </section>

        <section className="marketing-platform" id="platform" aria-labelledby="platform-title">
          <div className="marketing-section-heading"><span className="marketing-kicker">Complete platform</span><h2 id="platform-title">Every major system capability has a place, purpose and visible relationship.</h2><p>The website now explains the same module structure users encounter after signing in. Each section below maps to real application capabilities rather than generic marketing categories.</p></div>
          {platformSections.map((section,index)=>{const Icon=section.icon;return <section className="marketing-feature-section" id={section.id} key={section.id} aria-labelledby={`${section.id}-title`}>
            <div className="marketing-feature-copy"><span className="marketing-eyebrow"><Icon size={13}/>{section.eyebrow}</span><h3 id={`${section.id}-title`}>{section.title}</h3><p>{section.description}</p><ul className="marketing-feature-list">{section.features.map(feature=><li key={feature.name}><Check size={15}/><span><strong>{feature.name}</strong>{feature.description}</span></li>)}</ul></div>
            <ProductVisual section={section} index={index}/>
          </section>})}
        </section>

        <section className="marketing-security" id="security" aria-labelledby="security-title">
          <div className="marketing-security__grid">
            <div className="marketing-section-heading"><span className="marketing-kicker">Security & privacy</span><h2 id="security-title">Tracking only works when access and privacy rules are explicit.</h2><p>Workspace permissions, module gates, enterprise identity controls and configurable tracking policies are part of the product architecture—not afterthoughts bolted onto reporting.</p></div>
            <div className="marketing-security__cards">
              <article className="marketing-security-card"><KeyRound size={22}/><strong>Role & permission controls</strong><p>Role-aware pages, permission checks, explicit scopes and module visibility determine what each member can access.</p></article>
              <article className="marketing-security-card"><ShieldCheck size={22}/><strong>Privacy controls</strong><p>Screenshot, activity, exclusion and retention settings keep monitoring behavior visible and configurable.</p></article>
              <article className="marketing-security-card"><Network size={22}/><strong>Enterprise identity</strong><p>Enterprise surfaces cover identity, SCIM and security administration behind dedicated permissions.</p></article>
              <article className="marketing-security-card"><LockKeyhole size={22}/><strong>Workspace isolation</strong><p>Authenticated API traffic resolves workspace context before module, entitlement and permission middleware executes.</p></article>
            </div>
          </div>
        </section>

        <section className="marketing-security" id="architecture" aria-labelledby="architecture-title">
          <div className="marketing-security__grid">
            <div className="marketing-section-heading"><span className="marketing-kicker">How it works</span><h2 id="architecture-title">One application shell, multiple governed surfaces.</h2><p>The Laravel application serves one React entry point. The browser selects the public marketing site, private workspace, client portal, seller platform, public document signing or published website surface from the route. Private workspace API calls then pass authentication, workspace resolution and capability gates before business controllers execute.</p></div>
            <div className="marketing-security__cards">
              <article className="marketing-security-card"><Globe2 size={22}/><strong>Public surfaces</strong><p>Marketing, client portal, document signing and published workspace websites remain separated by route and purpose.</p></article>
              <article className="marketing-security-card"><UserRoundCog size={22}/><strong>Private workspace</strong><p>The shell resolves user, active workspace, role, modules and permissions before rendering destinations.</p></article>
              <article className="marketing-security-card"><Workflow size={22}/><strong>API pipeline</strong><p>Sanctum authentication, workspace middleware and capability checks protect the operational API.</p></article>
              <article className="marketing-security-card"><WalletCards size={22}/><strong>Connected business data</strong><p>Projects, time, attendance, clients, documents and payroll share workspace context without sharing unrestricted access.</p></article>
            </div>
          </div>
        </section>

        <section className="marketing-cta" aria-labelledby="marketing-cta-title"><h2 id="marketing-cta-title">Open the workspace when you need the system—not when you need another marketing promise.</h2><p>The application is organized around real operational modules, with role-aware access and a consistent design system across desktop and responsive layouts.</p><Button size="lg" variant="primary" onClick={()=>window.location.assign('/app')}>Open WorkIntel <ArrowRight size={15}/></Button></section>
      </div>
    </main>

    <footer className="marketing-footer"><div className="marketing-shell marketing-footer__inner"><span>WorkIntel · Workforce Intelligence Platform</span><span>Time · Attendance · Work · People · Operations · Payroll · Intelligence</span></div></footer>
  </div>
}
