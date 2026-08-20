import { ArrowLeft, LayoutPanelLeft, Play, Circle } from 'lucide-react';
import { useState } from 'react';
import { EMPLOYEES, TIMELINE_EVENTS, APP_USAGE } from '../data';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, BarChart, Bar } from 'recharts';
import { Pressable, Box, Grid, Inline, Text } from '../design-system';
const WEEK_HOURS = [
    { day: 'Mon', hours: 8.2, active: 6.9 },
    { day: 'Tue', hours: 7.5, active: 6.1 },
    { day: 'Wed', hours: 8.8, active: 7.4 },
    { day: 'Thu', hours: 9.1, active: 7.8 },
    { day: 'Fri', hours: 6.4, active: 5.2 },
];
const ATTENDANCE_MONTH = [5, 5, 5, 5, 4, 5, 5, 5, 5, 5, 4, 5, 5, 5, 5, 5, 5, 4, 5, 5, 5, 5];
const statusColors: Record<string, string> = { working: 'var(--success)', idle: 'var(--warning)', break: 'var(--info)', meeting: 'var(--purple)', offline: 'var(--text-3)' };
const TABS = ['Overview', 'Timeline', 'Time', 'Activity', 'Apps & Sites', 'Screenshots', 'Attendance', 'Payroll', 'Projects', 'Notes', 'Devices'];
/** Handles the employee profile operation for the WorkIntel client. */ export default function EmployeeProfile({ empId, onBack }: {
    empId: number;
    onBack: () => void;
}) {
    const [tab, setTab] = useState('Overview');
    const emp = EMPLOYEES.find(e => e.id === empId) || EMPLOYEES[0];
    const timelineColors: Record<string, string> = {
        checkin: 'var(--success)',
        app: 'var(--accent)',
        web: 'var(--info)',
        idle: 'var(--warning)',
        break: 'var(--purple)',
        task: 'var(--cyan)',
        checkout: 'var(--danger)',
    };
    return (<Box p="24px 28px" maxWidth={1200}>
      {/* Back */}
      <Pressable onClick={onBack} display="flex" align="center" gap={6} bg="none" border="none" cursor="pointer" color="var(--text-3)" fontFamily="inherit" size={13} mb={20} p={0}>
        <ArrowLeft size={14}/>
        Back to People
      </Pressable>

      {/* Profile header */}
      <Box bg="var(--surface)" border="1px solid var(--border)" radius="var(--radius-xl)" p="24px 28px" mb={16}>
        <Inline align="flex-start" gap={20} mb={20}>
          <Box position="relative" shrink={0}>
            <Box width={64} height={64} radius="50%" bg={emp.avatarColor} display="flex" align="center" justify="center" size={22} weight={700} color="var(--icon-on-accent)">{emp.avatar}</Box>
            <Box as="span" position="absolute" bottom={2} right={2} width={14} height={14} radius="50%" bg={statusColors[emp.status]} border="2.5px solid var(--surface)"/>
          </Box>
          <Box flex={1}>
            <Inline align="center" gap={10} mb={4}>
              <Box as="h2" m={0} size={20} weight={700} color="var(--text)">{emp.name}</Box>
              <Box as="span" size={12} weight={500} p="3px 8px" radius={20} bg={`${statusColors[emp.status]}18`} color={statusColors[emp.status]}>{emp.status.charAt(0).toUpperCase() + emp.status.slice(1)}</Box>
              <Box as="span" size={11} p="2px 7px" radius={20} bg="var(--elevated)" color="var(--text-3)" textTransform="capitalize">{emp.location}</Box>
            </Inline>
            <Box size={14} color="var(--text-2)" mb={8}>{emp.role} · {emp.dept}</Box>
            <Box size={12} color="var(--text-3)">
              Manager: <Text color="var(--text-2)">{emp.manager}</Text>
              <Text m="0 8px" color="var(--border)">·</Text>
              {emp.email}
              <Text m="0 8px" color="var(--border)">·</Text>
              Joined {emp.joinDate}
            </Box>
          </Box>
          <Box className="ui-inline" gap={8}>
            <Pressable className="ui-button ui-button--outline">Message</Pressable>
            <Pressable p="6px 12px" radius="var(--radius)" border="none" bg="var(--accent)" color="var(--icon-on-accent)" cursor="pointer" size={13} fontFamily="inherit" weight={500} display="flex" align="center" gap={5}>
              <Play size={12} fill="currentColor"/>
              Start Timer
            </Pressable>
          </Box>
        </Inline>

        {/* Stats */}
        <Box display="grid" gridColumns="repeat(6, 1fr)" gap={1} bg="var(--border)" radius={8} overflow="hidden">
          {[
            { label: 'Today', value: emp.trackedToday, sub: `Active ${emp.activeTime}` },
            { label: 'This Week', value: emp.trackedWeek, sub: 'Tracked' },
            { label: 'This Month', value: emp.trackedMonth, sub: 'Tracked' },
            { label: 'Activity', value: `${emp.activity}%`, sub: 'Avg activity' },
            { label: 'Attendance', value: `${emp.attendance}%`, sub: 'This month' },
            { label: 'Idle Time', value: emp.idleTime, sub: 'Today' },
        ].map(s => (<Box key={s.label} bg="var(--surface)" p="14px 16px" textAlign="center">
              <Box size={11} color="var(--text-3)" mb={4}>{s.label}</Box>
              <Box className="stat-num" size={18} weight={600} color="var(--text)" lineHeight={1}>{s.value}</Box>
              <Box size={10} color="var(--text-3)" mt={3}>{s.sub}</Box>
            </Box>))}
        </Box>
      </Box>

      {/* Current work banner */}
      {emp.status === 'working' && (<Box bg="var(--accent-dim)" border="1px solid var(--accent-dim)" radius="var(--radius-lg)" p="12px 18px" mb={16} display="flex" align="center" gap={12}>
          <Box as="span" position="relative" display="inline-block" width={8} height={8} shrink={0}>
            <Box as="span" className="pulse" position="absolute" inset={0} radius="50%" bg="var(--accent)"/>
          </Box>
          <Text size={13} color="var(--text-2)">Currently working on</Text>
          <Text size={13} weight={500} color="var(--text)">{emp.project}</Text>
          <Text color="var(--text-3)">·</Text>
          <Text size={13} color="var(--text-2)">{emp.task}</Text>
          <Box flex={1}/>
          <Text className="stat-num" size={16} weight={600} color="var(--accent)">{emp.timer}</Text>
        </Box>)}

      {/* Tabs */}
      <Box display="flex" gap={0} borderBottom="1px solid var(--border)" mb={20} overflowX="auto">
        {TABS.map(t => (<Pressable key={t} onClick={() => setTab(t)} p="8px 16px" bg="none" border="none" cursor="pointer" fontFamily="inherit" size={13} color={tab === t ? 'var(--text)' : 'var(--text-3)'} weight={tab === t ? 500 : 400} borderBottom={`2px solid ${tab === t ? 'var(--accent)' : 'transparent'}`} mb={-1} whiteSpace="nowrap" transition="color 120ms">
            {t}
          </Pressable>))}
      </Box>

      {/* Overview tab */}
      {tab === 'Overview' && (<Grid columns="1fr 300px" gap={16}>
          <Box display="flex" direction="column" gap={16}>
            {/* Week hours */}
            <div className="ui-card ui-card__body">
              <Box as="h4" m="0 0 14px" size={13} weight={600} color="var(--text)">This Week's Hours</Box>
              <ResponsiveContainer width="100%" height={140}>
                <BarChart data={WEEK_HOURS} margin={{ top: 0, right: 0, left: -20, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="var(--border-muted)" vertical={false}/>
                  <XAxis dataKey="day" tick={{ fill: 'var(--text-3)', fontSize: 11 }} axisLine={false} tickLine={false}/>
                  <YAxis tick={{ fill: 'var(--text-3)', fontSize: 11 }} axisLine={false} tickLine={false}/>
                  <Tooltip contentStyle={{ background: 'var(--elevated)', border: '1px solid var(--border)', borderRadius: 8, color: 'var(--text)', fontSize: 12 }}/>
                  <Bar dataKey="hours" name="Tracked" fill="var(--accent)" fillOpacity={0.8} radius={[2, 2, 0, 0]}/>
                  <Bar dataKey="active" name="Active" fill="var(--success)" fillOpacity={0.6} radius={[2, 2, 0, 0]}/>
                </BarChart>
              </ResponsiveContainer>
            </div>

            {/* App usage */}
            <div className="ui-card ui-card__body">
              <Box as="h4" m="0 0 14px" size={13} weight={600} color="var(--text)">Top Applications Today</Box>
              <Box display="flex" direction="column" gap={10}>
                {APP_USAGE.map(app => (<div key={app.app}>
                    <Inline justify="space-between" align="center" mb={4}>
                      <Box className="ui-inline" gap={8}>
                        <Box width={24} height={24} radius={5} bg="var(--elevated)" display="flex" align="center" justify="center" size={11} color="var(--text-2)">
                          {app.app[0]}
                        </Box>
                        <Text size={13} color="var(--text)" weight={500}>{app.app}</Text>
                        <Box as="span" size={11} p="1px 6px" radius={20} bg={app.classification === 'productive' ? 'var(--success-dim)' : app.classification === 'unproductive' ? 'var(--danger-dim)' : 'var(--elevated)'} color={app.classification === 'productive' ? 'var(--success)' : app.classification === 'unproductive' ? 'var(--danger)' : 'var(--text-3)'}>
                          {app.classification}
                        </Box>
                      </Box>
                      <Text className="stat-num" size={12} color="var(--text-2)">{app.time}</Text>
                    </Inline>
                    <Box height={3} bg="var(--border)" radius={2}>
                      <Box height="100%" width={`${app.pct}%`} bg={app.classification === 'productive' ? 'var(--accent)' : app.classification === 'unproductive' ? 'var(--danger)' : 'var(--text-3)'} radius={2}/>
                    </Box>
                  </div>))}
              </Box>
            </div>
          </Box>

          {/* Right: Timeline today */}
          <div className="ui-card ui-card__body">
            <Box as="h4" m="0 0 14px" size={13} weight={600} color="var(--text)">Today's Timeline</Box>
            <Box position="relative">
              <Box position="absolute" left={16} top={0} bottom={0} width={1} bg="var(--border)"/>
              <Box display="flex" direction="column" gap={0}>
                {TIMELINE_EVENTS.map((ev, i) => (<Inline key={i} gap={12} p="6px 0" pl={4}>
                    <Box width={24} height={24} radius="50%" bg={timelineColors[ev.type] || 'var(--border)'} display="flex" align="center" justify="center" shrink={0} border="2px solid var(--surface)" zIndex={1} position="relative" ml={4}>
                      <Circle size={8} fill="var(--icon-on-accent)" color="var(--icon-on-accent)"/>
                    </Box>
                    <Box pt={3} flex={1} minWidth={0}>
                      <Box size={11} color="var(--text-3)" mb={1}>
                        {ev.time}{ev.endTime ? ` – ${ev.endTime}` : ''}
                        {(ev as any).duration && <Text ml={4}>· {(ev as any).duration}</Text>}
                      </Box>
                      <Box size={12} weight={500} color="var(--text)">{ev.label}</Box>
                      {ev.detail && <Box size={11} color="var(--text-3)" mt={1}>{ev.detail}</Box>}
                      {(ev as any).activity !== undefined && (ev as any).activity > 0 && (<Box size={10} color="var(--text-3)" mt={2}>{(ev as any).activity}% activity</Box>)}
                    </Box>
                  </Inline>))}
              </Box>
            </Box>
          </div>
        </Grid>)}

      {/* Timeline tab */}
      {tab === 'Timeline' && (<Box maxWidth={700}>
          <Inline gap={8} mb={20} wrap="wrap">
            {['Applications', 'Websites', 'Tasks', 'Projects', 'Idle', 'Screenshots'].map(f => (<Pressable key={f} p="4px 10px" radius={20} border="1px solid var(--border)" bg="var(--elevated)" color="var(--text-2)" cursor="pointer" fontFamily="inherit" size={12}>{f}</Pressable>))}
          </Inline>
          <Box position="relative" pl={60}>
            <Box position="absolute" left={52} top={0} bottom={0} width={1.5} bg="var(--border)"/>
            {TIMELINE_EVENTS.map((ev, i) => (<Box key={i} display="flex" gap={16} mb={12} position="relative">
                <Box position="absolute" left={-46} size={11} color="var(--text-3)" fontFamily="var(--font-mono)" whiteSpace="nowrap" top={4}>{ev.time}</Box>
                <Box position="absolute" left={-8} top={6} width={10} height={10} radius="50%" bg={timelineColors[ev.type]} border="2px solid var(--bg)" zIndex={1}/>
                <Box bg="var(--surface)" border="1px solid var(--border)" radius="var(--radius-lg)" p="12px 16px" flex={1}>
                  <Inline justify="space-between" align="flex-start" mb={4}>
                    <Box weight={500} size={13} color="var(--text)">{ev.label}</Box>
                    {(ev as any).duration && <Text className="stat-num" size={12} color="var(--text-3)">{(ev as any).duration}</Text>}
                  </Inline>
                  {ev.detail && <Box size={12} color="var(--text-2)">{ev.detail}</Box>}
                  {(ev as any).activity !== undefined && (ev as any).activity > 0 && (<Inline align="center" gap={6} mt={6}>
                      <Box height={3} flex={1} bg="var(--border)" radius={2}>
                        <Box height="100%" width={`${(ev as any).activity}%`} bg="var(--accent)" radius={2}/>
                      </Box>
                      <Box as="span" className="stat-num" size={11} color="var(--text-3)" minWidth={28}>{(ev as any).activity}%</Box>
                    </Inline>)}
                </Box>
              </Box>))}
          </Box>
        </Box>)}

      {/* Other tabs placeholder */}
      {!['Overview', 'Timeline'].includes(tab) && (<Box display="flex" align="center" justify="center" height={300} color="var(--text-3)" size={14} direction="column" gap={8}>
          <LayoutPanelLeft size={32}/>
          <span>{tab} content</span>
          <Text size={12} color="var(--text-3)" opacity={0.6}>Select different tab to explore</Text>
        </Box>)}
    </Box>);
}
