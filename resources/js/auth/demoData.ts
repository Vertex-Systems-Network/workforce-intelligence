import type { AuthUser, WorkspaceRole } from './types'

/** Describes the demo account data contract used by the WorkIntel client. */ export interface DemoAccount { password:string; user:AuthUser }

const rolePermissions:Record<string,string[]>={
  owner:['*'], admin:['*'],
  manager:['people.view_team','organization.view','projects.view_all','projects.manage','tasks.view_all','tasks.manage','time.view_own','time.view_team','time.manage','attendance.view_own','attendance.view_team','attendance.manage','scheduling.view_own','scheduling.view_team','scheduling.manage','activity.view_own','activity.view_team','screenshots.view_team','reports.view','reports.manage'],
  'team-lead':['people.view_team','organization.view','projects.view_assigned','tasks.view_team','tasks.manage_team','time.view_own','time.view_team','attendance.view_own','attendance.view_team','scheduling.view_own','scheduling.view_team','activity.view_own','activity.view_team','screenshots.view_team','reports.view'],
  hr:['people.view_all','people.manage','organization.view','organization.manage','attendance.view_own','attendance.view_team','attendance.manage','attendance.policy_manage','scheduling.view_own','scheduling.view_team','scheduling.manage','time.view_own','time.view_team','reports.view','reports.manage','devices.view'],
  'payroll-manager':['people.view_all','organization.view','time.view_all','attendance.view_own','attendance.view_team','scheduling.view_own','scheduling.view_team','payroll.view_own','payroll.view_all','payroll.manage','reports.view','reports.manage'],
  employee:['projects.view_assigned','tasks.view_own','time.view_own','attendance.view_own','scheduling.view_own','activity.view_own','screenshots.view_own','payroll.view_own'],
  client:[],
  'project-coordinator':['projects.view_assigned','tasks.view_team','tasks.manage_team','time.view_own','time.view_team','reports.view','approvals.view_own','approvals.review'],
}
/** Handles the ws operation for the WorkIntel client. */ const ws=(id:number,name:string,slug:string,plan:'Free'|'Silver'|'Gold'|'Platinum',role:WorkspaceRole)=>({id,name,slug,plan,role,roles:[role],permissions:rolePermissions[role]??[]})

export const DEMO_ACCOUNTS:DemoAccount[]=[
 {password:'password',user:{id:101,firstName:'Sarah',lastName:'Chen',email:'owner@acme.test',jobTitle:'Workspace Owner',avatar:'SC',activeWorkspaceId:1,workspaces:[ws(1,'Acme Corp','acme-corp','Gold','owner')]}},
 {password:'password',user:{id:102,firstName:'Olivia',lastName:'Brooks',email:'admin@acme.test',jobTitle:'Workspace Administrator',avatar:'OB',activeWorkspaceId:1,workspaces:[ws(1,'Acme Corp','acme-corp','Gold','admin')]}},
 {password:'password',user:{id:103,firstName:'Nadia',lastName:'Rahman',email:'hr@acme.test',jobTitle:'HR Manager',avatar:'NR',activeWorkspaceId:1,workspaces:[ws(1,'Acme Corp','acme-corp','Gold','hr')]}},
 {password:'password',user:{id:104,firstName:'James',lastName:'Liu',email:'manager@acme.test',jobTitle:'Engineering Manager',avatar:'JL',activeWorkspaceId:1,workspaces:[ws(1,'Acme Corp','acme-corp','Gold','manager')]}},
 {password:'password',user:{id:105,firstName:'Omar',lastName:'Saleh',email:'teamlead@acme.test',jobTitle:'Engineering Team Lead',avatar:'OS',activeWorkspaceId:1,workspaces:[ws(1,'Acme Corp','acme-corp','Gold','team-lead')]}},
 {password:'password',user:{id:106,firstName:'Maya',lastName:'Patel',email:'payroll@acme.test',jobTitle:'Payroll Manager',avatar:'MP',activeWorkspaceId:1,workspaces:[ws(1,'Acme Corp','acme-corp','Gold','payroll-manager')]}},
 {password:'password',user:{id:107,firstName:'Ahmed',lastName:'Khan',email:'employee@acme.test',jobTitle:'Frontend Developer',avatar:'AK',activeWorkspaceId:1,workspaces:[ws(1,'Acme Corp','acme-corp','Gold','employee')]}},
 {password:'password',user:{id:108,firstName:'Aisha',lastName:'Noor',email:'coordinator@acme.test',jobTitle:'Project Coordinator',avatar:'AN',activeWorkspaceId:1,workspaces:[ws(1,'Acme Corp','acme-corp','Gold','project-coordinator')]}},
]

export const CLIENT_PORTAL_DEMO={label:'Client Portal',email:'client@techcorp.test',password:'password',path:'/portal/acme-corp'}
