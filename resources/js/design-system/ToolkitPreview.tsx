import { ChevronDown, CircleQuestionMark } from 'lucide-react'
import { useState } from 'react'
import { Alert, Avatar, Badge, Button, Card, CardBody, CardHeader, Divider, Drawer, Dropdown, Field, IconButton, Inline, Input, Modal, PageHeader, Popover, Progress, SearchInput, Segmented, Select, Stack, StatCard, Switch, Tabs, Textarea, Tooltip } from './index'

/** Handles the toolkit preview operation for the WorkIntel client. */ export default function ToolkitPreview() {
  const [modal, setModal] = useState(false)
  const [drawer, setDrawer] = useState(false)
  const [enabled, setEnabled] = useState(true)
  const [segment, setSegment] = useState<'day'|'week'|'month'>('week')
  const [tab, setTab] = useState<'overview'|'activity'|'history'>('overview')

  return <div>
    <PageHeader title="UI Toolkit" description="Reusable primitives used across WorkIntel. Extend these components instead of creating page-specific controls." actions={<><Button variant="outline" onClick={() => setDrawer(true)}>Open Drawer</Button><Button variant="primary" onClick={() => setModal(true)}>Open Modal</Button></>} />
    <Stack gap={16}>
      <Card><CardHeader title="Actions & overlays" description="Buttons, menus, popovers and tooltips" /><CardBody><Inline gap={8} style={{ flexWrap:'wrap' }}>
        <Button variant="primary">Primary action</Button><Button variant="secondary">Secondary</Button><Button variant="outline">Outline</Button><Button variant="ghost">Ghost</Button><Button variant="danger">Danger</Button>
        <Dropdown trigger={<Button variant="outline">Dropdown <ChevronDown size={13} /></Button>} items={[{header:true,label:'Actions'},{label:'View details',meta:'↵'},{label:'Duplicate'},{separator:true},{label:'Delete',danger:true}]} />
        <Popover trigger={<Button variant="outline">Popover</Button>}><div style={{ color:'var(--text)', fontWeight:600, marginBottom:4 }}>Quick filter</div><div style={{ color:'var(--text-3)', fontSize:12 }}>Use popovers for compact contextual controls.</div></Popover>
        <Tooltip content="Useful hover context"><IconButton variant="outline" aria-label="Help"><CircleQuestionMark size={15}/></IconButton></Tooltip>
      </Inline></CardBody></Card>

      <Card><CardHeader title="Forms" description="Field, input, select, textarea and switch" /><CardBody><div style={{ display:'grid', gridTemplateColumns:'repeat(2,minmax(0,1fr))', gap:12 }}>
        <Field label="Employee"><Input placeholder="Search employee…" /></Field><Field label="Project"><Select defaultValue="platform"><option value="platform">WorkIntel Platform</option><option value="api">API Platform</option></Select></Field><Field label="Search"><SearchInput placeholder="Search…" /></Field><Field label="Notes"><Textarea placeholder="Add internal note…" /></Field>
      </div><div style={{ marginTop:14, display:'flex', alignItems:'center', justifyContent:'space-between' }}><div><div style={{ color:'var(--text)', fontSize:13, fontWeight:500 }}>Enable screenshot capture</div><div style={{ color:'var(--text-3)', fontSize:11 }}>Example reusable switch control</div></div><Switch checked={enabled} onChange={setEnabled} /></div></CardBody></Card>

      <Card><CardHeader title="Navigation & status" /><CardBody><Stack gap={14}><Segmented value={segment} onChange={setSegment} options={[{value:'day',label:'Day'},{value:'week',label:'Week'},{value:'month',label:'Month'}]} /><Tabs value={tab} onChange={setTab} tabs={[{value:'overview',label:'Overview'},{value:'activity',label:'Activity'},{value:'history',label:'History'}]} /><Inline gap={7}><Badge tone="success" dot>Working</Badge><Badge tone="warning" dot>Idle</Badge><Badge tone="danger" dot>Offline</Badge><Badge tone="accent">Admin</Badge><Avatar name="Ahmed Khan" /></Inline></Stack></CardBody></Card>

      <div style={{ display:'grid', gridTemplateColumns:'repeat(3,minmax(0,1fr))', gap:12 }}><StatCard label="Tracked today" value="142h 32m" sub="+8.4% vs yesterday" /><StatCard label="Active workers" value="17" sub="3 on break" /><StatCard label="Payroll accrued" value="$4,820" sub="Current period" /></div>

      <Card><CardHeader title="Feedback & progress" /><CardBody><Stack gap={12}><Alert tone="info">Activity percentage is a device-interaction signal, not a productivity score.</Alert><Alert tone="warning">3 employees are missing a clock-out.</Alert><div><Inline style={{ justifyContent:'space-between', marginBottom:6 }}><span style={{ color:'var(--text-2)', fontSize:12 }}>Project capacity</span><span className="stat-num" style={{ color:'var(--text)', fontSize:12 }}>72%</span></Inline><Progress value={72} /></div></Stack></CardBody></Card>
    </Stack>

    <Modal open={modal} onClose={() => setModal(false)} title="Reusable modal" description="Use for focused create/edit workflows" footer={<><Button variant="outline" onClick={() => setModal(false)}>Cancel</Button><Button variant="primary" onClick={() => setModal(false)}>Save changes</Button></>}><Field label="Task name"><Input defaultValue="Animation Timeline Editor" /></Field></Modal>
    <Drawer open={drawer} onClose={() => setDrawer(false)} title="Reusable drawer" description="Use for details without losing page context" footer={<Button variant="primary" onClick={() => setDrawer(false)}>Done</Button>}><Stack gap={12}><Alert tone="success">This drawer is part of the shared toolkit.</Alert><Field label="Status"><Select><option>Working</option><option>Idle</option><option>Offline</option></Select></Field></Stack></Drawer>
  </div>
}
