export type Status = 'working' | 'idle' | 'break' | 'meeting' | 'offline'
export type PayrollStatus = 'draft' | 'calculated' | 'review' | 'approved' | 'paid'
export type ProjectStatus = 'active' | 'paused' | 'completed' | 'overbudget'
export type TaskStatus = 'todo' | 'in_progress' | 'review' | 'done'
export type Priority = 'low' | 'medium' | 'high' | 'critical'
export type AttendanceStatus = 'present' | 'absent' | 'late' | 'wfh' | 'leave' | 'overtime'

/** Describes the employee data contract used by the WorkIntel client. */ export interface Employee {
  id: number
  name: string
  email: string
  role: string
  dept: string
  manager: string
  status: Status
  project: string
  task: string
  timer: string
  activity: number
  activeTime: string
  idleTime: string
  trackedToday: string
  trackedWeek: string
  trackedMonth: string
  app: string
  lastScreenshot: string
  device: string
  lastSync: string
  avatar: string
  avatarColor: string
  cost: string
  attendance: number
  joinDate: string
  payRate: number
  payType: 'hourly' | 'monthly'
  location: 'remote' | 'office' | 'hybrid'
}

export const EMPLOYEES: Employee[] = [
  { id: 1, name: 'Ahmed Khan', email: 'ahmed@acme.co', role: 'Frontend Developer', dept: 'Engineering', manager: 'Sarah Chen', status: 'working', project: 'WorkIntel Platform', task: 'Animation Timeline Editor', timer: '02:14:18', activity: 78, activeTime: '1h 45m', idleTime: '29m', trackedToday: '6h 22m', trackedWeek: '28h 14m', trackedMonth: '102h 8m', app: 'VS Code', lastScreenshot: '2 min ago', device: 'MacBook Pro', lastSync: '1 min ago', avatar: 'AK', avatarColor: '#6366f1', cost: '$85/hr', attendance: 96, joinDate: '2023-03-15', payRate: 85, payType: 'hourly', location: 'remote' },
  { id: 2, name: 'Priya Sharma', email: 'priya@acme.co', role: 'Product Designer', dept: 'Design', manager: 'Sarah Chen', status: 'working', project: 'Design System', task: 'Component Library v2', timer: '03:41:02', activity: 91, activeTime: '3h 15m', idleTime: '26m', trackedToday: '7h 11m', trackedWeek: '32h 5m', trackedMonth: '118h 44m', app: 'Figma', lastScreenshot: '1 min ago', device: 'MacBook Air M2', lastSync: 'Just now', avatar: 'PS', avatarColor: '#ec4899', cost: '$75/hr', attendance: 100, joinDate: '2022-08-01', payRate: 75, payType: 'hourly', location: 'hybrid' },
  { id: 3, name: 'Marcus Webb', email: 'marcus@acme.co', role: 'Backend Engineer', dept: 'Engineering', manager: 'James Liu', status: 'meeting', project: 'API Platform', task: 'Auth Service Refactor', timer: '01:08:44', activity: 62, activeTime: '0h 42m', idleTime: '26m', trackedToday: '4h 55m', trackedWeek: '21h 40m', trackedMonth: '88h 20m', app: 'Zoom', lastScreenshot: '14 min ago', device: 'Windows 11 PC', lastSync: '3 min ago', avatar: 'MW', avatarColor: '#f59e0b', cost: '$95/hr', attendance: 88, joinDate: '2021-11-20', payRate: 95, payType: 'hourly', location: 'office' },
  { id: 4, name: 'Sarah Chen', email: 'sarah@acme.co', role: 'Engineering Manager', dept: 'Engineering', manager: 'Alex Torres', status: 'working', project: 'WorkIntel Platform', task: 'Sprint Planning Review', timer: '04:02:11', activity: 84, activeTime: '3h 21m', idleTime: '41m', trackedToday: '7h 44m', trackedWeek: '35h 12m', trackedMonth: '142h 0m', app: 'Linear', lastScreenshot: '5 min ago', device: 'MacBook Pro M3', lastSync: '1 min ago', avatar: 'SC', avatarColor: '#22c55e', cost: '$120/hr', attendance: 98, joinDate: '2020-06-10', payRate: 120, payType: 'hourly', location: 'hybrid' },
  { id: 5, name: 'Jordan Lee', email: 'jordan@acme.co', role: 'QA Engineer', dept: 'Engineering', manager: 'James Liu', status: 'idle', project: 'WorkIntel Platform', task: 'Integration Tests', timer: '00:22:15', activity: 18, activeTime: '0h 04m', idleTime: '18m', trackedToday: '3h 12m', trackedWeek: '18h 30m', trackedMonth: '75h 15m', app: 'Chrome', lastScreenshot: '8 min ago', device: 'Ubuntu 22.04', lastSync: '5 min ago', avatar: 'JL', avatarColor: '#3b82f6', cost: '$65/hr', attendance: 91, joinDate: '2023-01-09', payRate: 65, payType: 'hourly', location: 'remote' },
  { id: 6, name: 'Mei Tanaka', email: 'mei@acme.co', role: 'Data Analyst', dept: 'Analytics', manager: 'Alex Torres', status: 'working', project: 'Analytics Pipeline', task: 'Dashboard Metrics ETL', timer: '05:11:30', activity: 95, activeTime: '4h 55m', idleTime: '16m', trackedToday: '8h 02m', trackedWeek: '39h 44m', trackedMonth: '158h 22m', app: 'DBeaver', lastScreenshot: '3 min ago', device: 'MacBook Pro M2', lastSync: 'Just now', avatar: 'MT', avatarColor: '#a855f7', cost: '$80/hr', attendance: 100, joinDate: '2022-02-14', payRate: 80, payType: 'hourly', location: 'remote' },
  { id: 7, name: 'Liam O\'Brien', email: 'liam@acme.co', role: 'DevOps Engineer', dept: 'Infrastructure', manager: 'James Liu', status: 'break', project: 'Infrastructure', task: 'K8s Cluster Upgrade', timer: '00:15:02', activity: 0, activeTime: '0h 00m', idleTime: '15m', trackedToday: '5h 28m', trackedWeek: '24h 11m', trackedMonth: '96h 40m', app: '—', lastScreenshot: '15 min ago', device: 'Linux Workstation', lastSync: '15 min ago', avatar: 'LO', avatarColor: '#06b6d4', cost: '$90/hr', attendance: 93, joinDate: '2022-05-30', payRate: 90, payType: 'hourly', location: 'office' },
  { id: 8, name: 'Fatima Al-Hassan', email: 'fatima@acme.co', role: 'UX Researcher', dept: 'Design', manager: 'Sarah Chen', status: 'offline', project: 'User Research', task: 'Interview Analysis', timer: '—', activity: 0, activeTime: '—', idleTime: '—', trackedToday: '0h 00m', trackedWeek: '16h 20m', trackedMonth: '64h 10m', app: '—', lastScreenshot: '3 hrs ago', device: 'MacBook Air', lastSync: '3 hrs ago', avatar: 'FA', avatarColor: '#f97316', cost: '$70/hr', attendance: 78, joinDate: '2023-07-01', payRate: 70, payType: 'hourly', location: 'remote' },
]

/** Describes the project data contract used by the WorkIntel client. */ export interface Project {
  id: number
  name: string
  client: string
  members: number
  status: ProjectStatus
  tracked: string
  budget: number
  used: number
  billable: string
  cost: string
  progress: number
  deadline: string
  color: string
}

export const PROJECTS: Project[] = [
  { id: 1, name: 'WorkIntel Platform', client: 'Internal', members: 5, status: 'active', tracked: '248h 14m', budget: 400, used: 310, billable: '198h', cost: '$24,800', progress: 62, deadline: '2026-09-30', color: '#6366f1' },
  { id: 2, name: 'Design System', client: 'Internal', members: 3, status: 'active', tracked: '182h 55m', budget: 250, used: 183, billable: '140h', cost: '$14,200', progress: 73, deadline: '2026-10-15', color: '#ec4899' },
  { id: 3, name: 'API Platform', client: 'TechCorp Inc.', members: 4, status: 'active', tracked: '320h 40m', budget: 500, used: 321, billable: '320h', cost: '$30,400', progress: 64, deadline: '2026-08-31', color: '#22c55e' },
  { id: 4, name: 'Analytics Pipeline', client: 'DataFlow Ltd.', members: 2, status: 'active', tracked: '98h 10m', budget: 160, used: 98, billable: '98h', cost: '$7,840', progress: 61, deadline: '2026-11-01', color: '#a855f7' },
  { id: 5, name: 'Infrastructure', client: 'Internal', members: 2, status: 'active', tracked: '144h 22m', budget: 200, used: 144, billable: '0h', cost: '$12,996', progress: 72, deadline: '2026-12-31', color: '#06b6d4' },
  { id: 6, name: 'Mobile App v3', client: 'RetailCo', members: 6, status: 'paused', tracked: '512h 08m', budget: 600, used: 512, billable: '490h', cost: '$46,080', progress: 85, deadline: '2026-07-15', color: '#f59e0b' },
]

/** Describes the time entry data contract used by the WorkIntel client. */ export interface TimeEntry {
  id: number
  employee: string
  project: string
  task: string
  date: string
  start: string
  end: string
  duration: string
  billable: boolean
  manual: boolean
  approved: boolean | null
}

export const TIME_ENTRIES: TimeEntry[] = [
  { id: 1, employee: 'Ahmed Khan', project: 'WorkIntel Platform', task: 'Animation Timeline Editor', date: 'Today', start: '09:02', end: '11:16', duration: '2h 14m', billable: true, manual: false, approved: null },
  { id: 2, employee: 'Ahmed Khan', project: 'WorkIntel Platform', task: 'Motion Editor Fixes', date: 'Today', start: '11:20', end: '12:45', duration: '1h 25m', billable: true, manual: false, approved: null },
  { id: 3, employee: 'Priya Sharma', project: 'Design System', task: 'Component Library v2', date: 'Today', start: '09:15', end: '12:55', duration: '3h 40m', billable: false, manual: false, approved: null },
  { id: 4, employee: 'Marcus Webb', project: 'API Platform', task: 'Auth Service Refactor', date: 'Today', start: '08:30', end: '10:18', duration: '1h 48m', billable: true, manual: false, approved: null },
  { id: 5, employee: 'Sarah Chen', project: 'WorkIntel Platform', task: 'Sprint Planning Review', date: 'Today', start: '08:00', end: '12:02', duration: '4h 02m', billable: false, manual: false, approved: null },
  { id: 6, employee: 'Jordan Lee', project: 'WorkIntel Platform', task: 'Integration Tests', date: 'Today', start: '10:05', end: '10:27', duration: '0h 22m', billable: true, manual: false, approved: null },
  { id: 7, employee: 'Ahmed Khan', project: 'WorkIntel Platform', task: 'Animation Timeline Editor', date: 'Yesterday', start: '09:00', end: '12:30', duration: '3h 30m', billable: true, manual: false, approved: true },
  { id: 8, employee: 'Marcus Webb', project: 'API Platform', task: 'Database Schema Migration', date: 'Yesterday', start: '13:00', end: '17:00', duration: '4h 00m', billable: true, manual: false, approved: true },
]

/** Describes the payroll entry data contract used by the WorkIntel client. */ export interface PayrollEntry {
  id: number
  employee: string
  avatar: string
  avatarColor: string
  role: string
  basePay: number
  hours: number
  overtime: number
  overtimePay: number
  bonus: number
  commission: number
  deduction: number
  tax: number
  adjustment: number
  netPay: number
  status: PayrollStatus
}

export const PAYROLL: PayrollEntry[] = [
  { id: 1, employee: 'Ahmed Khan', avatar: 'AK', avatarColor: '#6366f1', role: 'Frontend Developer', basePay: 6800, hours: 80, overtime: 4, overtimePay: 510, bonus: 0, commission: 0, deduction: 200, tax: 1528, adjustment: 0, netPay: 5582, status: 'calculated' },
  { id: 2, employee: 'Priya Sharma', avatar: 'PS', avatarColor: '#ec4899', role: 'Product Designer', basePay: 6000, hours: 80, overtime: 8, overtimePay: 900, bonus: 500, commission: 0, deduction: 180, tax: 1544, adjustment: 0, netPay: 5676, status: 'review' },
  { id: 3, employee: 'Marcus Webb', avatar: 'MW', avatarColor: '#f59e0b', role: 'Backend Engineer', basePay: 7600, hours: 76, overtime: 0, overtimePay: 0, bonus: 0, commission: 0, deduction: 220, tax: 1676, adjustment: -200, netPay: 5504, status: 'calculated' },
  { id: 4, employee: 'Sarah Chen', avatar: 'SC', avatarColor: '#22c55e', role: 'Engineering Manager', basePay: 9600, hours: 80, overtime: 6, overtimePay: 1080, bonus: 1000, commission: 0, deduction: 300, tax: 2476, adjustment: 0, netPay: 8904, status: 'approved' },
  { id: 5, employee: 'Jordan Lee', avatar: 'JL', avatarColor: '#3b82f6', role: 'QA Engineer', basePay: 5200, hours: 80, overtime: 0, overtimePay: 0, bonus: 0, commission: 0, deduction: 160, tax: 1108, adjustment: 0, netPay: 3932, status: 'draft' },
  { id: 6, employee: 'Mei Tanaka', avatar: 'MT', avatarColor: '#a855f7', role: 'Data Analyst', basePay: 6400, hours: 80, overtime: 12, overtimePay: 1440, bonus: 200, commission: 0, deduction: 190, tax: 1770, adjustment: 0, netPay: 6080, status: 'review' },
]

export const ATTENDANCE_TODAY = [
  { name: 'Ahmed Khan', avatar: 'AK', color: '#6366f1', shift: '09:00–18:00', clockIn: '09:02', clockOut: '—', breaks: '45m', worked: '6h 22m', late: '—', overtime: '—', status: 'present' as AttendanceStatus },
  { name: 'Priya Sharma', avatar: 'PS', color: '#ec4899', shift: '09:00–18:00', clockIn: '08:58', clockOut: '—', breaks: '30m', worked: '7h 11m', late: '—', overtime: '—', status: 'present' as AttendanceStatus },
  { name: 'Marcus Webb', avatar: 'MW', color: '#f59e0b', shift: '09:00–18:00', clockIn: '09:34', clockOut: '—', breaks: '1h 10m', worked: '4h 55m', late: '34m', overtime: '—', status: 'late' as AttendanceStatus },
  { name: 'Sarah Chen', avatar: 'SC', color: '#22c55e', shift: '08:00–17:00', clockIn: '08:00', clockOut: '—', breaks: '20m', worked: '7h 44m', late: '—', overtime: '2h 44m', status: 'overtime' as AttendanceStatus },
  { name: 'Jordan Lee', avatar: 'JL', color: '#3b82f6', shift: '10:00–19:00', clockIn: '10:10', clockOut: '—', breaks: '20m', worked: '3h 12m', late: '10m', overtime: '—', status: 'late' as AttendanceStatus },
  { name: 'Mei Tanaka', avatar: 'MT', color: '#a855f7', shift: '09:00–18:00', clockIn: '08:55', clockOut: '—', breaks: '10m', worked: '8h 02m', late: '—', overtime: '1h 02m', status: 'overtime' as AttendanceStatus },
  { name: 'Liam O\'Brien', avatar: 'LO', color: '#06b6d4', shift: '09:00–18:00', clockIn: '09:05', clockOut: '—', breaks: '1h 30m', worked: '5h 28m', late: '—', overtime: '—', status: 'present' as AttendanceStatus },
  { name: 'Fatima Al-Hassan', avatar: 'FA', color: '#f97316', shift: '09:00–18:00', clockIn: '—', clockOut: '—', breaks: '—', worked: '0h 00m', late: '—', overtime: '—', status: 'absent' as AttendanceStatus },
]

export const HOURLY_ACTIVITY = [
  { hour: '09:00', activity: 82, tracked: 58, idle: 2 },
  { hour: '10:00', activity: 91, tracked: 60, idle: 0 },
  { hour: '11:00', activity: 87, tracked: 57, idle: 3 },
  { hour: '12:00', activity: 34, tracked: 28, idle: 32 },
  { hour: '13:00', activity: 61, tracked: 42, idle: 18 },
  { hour: '14:00', activity: 88, tracked: 58, idle: 2 },
  { hour: '15:00', activity: 79, tracked: 54, idle: 6 },
  { hour: '16:00', activity: 71, tracked: 49, idle: 11 },
  { hour: '17:00', activity: 55, tracked: 38, idle: 22 },
  { hour: '18:00', activity: 22, tracked: 15, idle: 45 },
]

export const WEEKLY_TREND = [
  { day: 'Mon', tracked: 142, active: 118, idle: 24 },
  { day: 'Tue', tracked: 158, active: 131, idle: 27 },
  { day: 'Wed', tracked: 135, active: 112, idle: 23 },
  { day: 'Thu', tracked: 162, active: 140, idle: 22 },
  { day: 'Fri', tracked: 148, active: 121, idle: 27 },
  { day: 'Sat', tracked: 24, active: 20, idle: 4 },
  { day: 'Sun', tracked: 8, active: 6, idle: 2 },
]

export const APP_USAGE = [
  { app: 'VS Code', category: 'Development', time: '4h 21m', pct: 42, users: 4, classification: 'productive' as const },
  { app: 'Figma', category: 'Design', time: '2h 15m', pct: 21, users: 2, classification: 'productive' as const },
  { app: 'Chrome', category: 'Browser', time: '1h 43m', pct: 16, users: 8, classification: 'neutral' as const },
  { app: 'Zoom', category: 'Communication', time: '1h 12m', pct: 11, users: 5, classification: 'productive' as const },
  { app: 'Slack', category: 'Communication', time: '0h 44m', pct: 7, users: 8, classification: 'neutral' as const },
  { app: 'YouTube', category: 'Video', time: '0h 18m', pct: 3, users: 3, classification: 'unproductive' as const },
]

export const TIMELINE_EVENTS = [
  { time: '09:02', type: 'checkin', label: 'Checked In', detail: '', app: '' },
  { time: '09:04', endTime: '09:38', type: 'app', label: 'VS Code', detail: 'Project: WorkIntel Platform · Task: Motion Editor', app: 'VS Code', duration: '34 min', activity: 87 },
  { time: '09:38', endTime: '09:44', type: 'web', label: 'Chrome', detail: 'github.com', app: 'Chrome', duration: '6 min', activity: 72 },
  { time: '09:44', endTime: '10:31', type: 'app', label: 'VS Code', detail: 'Project: WorkIntel Platform · Bug Fixes', app: 'VS Code', duration: '47 min', activity: 91 },
  { time: '10:31', endTime: '10:45', type: 'idle', label: 'Idle', detail: '', app: '', duration: '14 min', activity: 0 },
  { time: '10:45', type: 'task', label: 'Started task', detail: 'GSAP Timeline UI', app: '' },
  { time: '10:45', endTime: '12:22', type: 'app', label: 'VS Code', detail: 'Project: WorkIntel Platform · GSAP Timeline UI', app: 'VS Code', duration: '1h 37 min', activity: 88 },
  { time: '12:22', endTime: '13:05', type: 'break', label: 'Lunch Break', detail: '', app: '', duration: '43 min', activity: 0 },
  { time: '13:05', endTime: '14:48', type: 'app', label: 'VS Code', detail: 'Project: WorkIntel Platform · Animation Timeline Editor', app: 'VS Code', duration: '1h 43 min', activity: 82 },
  { time: '14:48', endTime: '15:12', type: 'web', label: 'Figma', detail: 'figma.com · Design reference', app: 'Figma', duration: '24 min', activity: 78 },
]

export const NOTIFICATIONS = [
  { id: 1, category: 'attendance', title: 'Missing clock-out', body: 'Fatima Al-Hassan did not clock out yesterday', time: '8 min ago', read: false, severity: 'warning' as const },
  { id: 2, category: 'payroll', title: 'Payroll ready for review', body: 'August payroll is calculated and ready for your approval', time: '1 hr ago', read: false, severity: 'info' as const },
  { id: 3, category: 'tracking', title: '2 employees working overtime', body: 'Sarah Chen and Mei Tanaka have exceeded 8h today', time: '2 hrs ago', read: false, severity: 'warning' as const },
  { id: 4, category: 'system', title: 'Agent outdated on 1 device', body: "Liam O'Brien's Linux Workstation is running agent v2.8.1", time: '4 hrs ago', read: true, severity: 'warning' as const },
  { id: 5, category: 'security', title: 'New login from unknown device', body: 'Marcus Webb logged in from a new Windows device', time: '1 day ago', read: true, severity: 'danger' as const },
]
