export type Project = { id:number; name:string; code?:string|null }
export type UserName = { first_name:string; last_name:string }
export type Member = { id:number; name?:string; user?:UserName }
export type TaskStatus = {
  id:number; name:string; slug:string; color:string; group:string; sort_order:number;
  is_default:boolean; is_completed:boolean; allowed_to_ids:number[];
}
export type TaskTag = { id:number; name:string; slug:string; color:string }
export type Task = {
  id:number; workspace_id:number; project_id:number; parent_id:number|null; task_status_id:number|null;
  owner_member_id:number|null; title:string; description:string|null; description_html:string|null;
  status:string; priority:'low'|'medium'|'high'|'critical'; estimated_minutes:number|null;
  start_at:string|null; due_at:string|null; position:number; billable:boolean; client_visible:boolean;
  completed_at:string|null; project:Project; workflow_status:TaskStatus|null; owner?:Member|null;
  assignees:Member[]; observers:Member[]; tags:TaskTag[]; checklist_total?:number; checklist_completed?:number;
}
export type TaskComment = { id:number; body:string; created_at:string; member:Member }
export type TaskAttachment = { id:number; original_name:string; mime_type:string|null; size_bytes:number; created_at:string; member:Member }
export type TaskChecklistItem = {
  id:number; title:string; sort_order:number; is_completed:boolean; due_at:string|null;
  assignee_member_id:number|null; assignee?:Member|null; completed_by?:Member|null; completed_at:string|null;
}
export type TaskDependency = { id:number; type:string; depends_on_task:Task }
export type TaskDependent = { id:number; type:string; task:Task }
export type TaskRelation = { id:number; type:'related'|'duplicate'; source_task_id:number; target_task_id:number; target_task:Task }
export type TaskInverseRelation = { id:number; type:'related'|'duplicate'; source_task_id:number; target_task_id:number; source_task:Task }
export type TaskActivity = { id:number; action:string; metadata:Record<string,unknown>|null; created_at:string; actor?:Member|null }
export type TaskRecurrence = { id:number; frequency:'daily'|'weekly'|'monthly'; interval:number; starts_on:string; ends_on:string|null; next_run_at:string; active:boolean }
export type TaskDetails = Task & {
  comments:TaskComment[]; attachments:TaskAttachment[]; subtasks:Task[]; checklist_items:TaskChecklistItem[];
  dependencies:TaskDependency[]; dependents:TaskDependent[]; relations:TaskRelation[]; inverse_relations:TaskInverseRelation[];
  activities:TaskActivity[]; recurrence:TaskRecurrence|null; recurrence_instances:Task[]; parent?:Task|null;
}
export type WorkflowPayload = { statuses:TaskStatus[]; tags:TaskTag[]; can_manage_workflow:boolean }
/** Handles the member label operation for the WorkIntel client. */ export const memberLabel = (member?:Member|null) => member?.name || [member?.user?.first_name,member?.user?.last_name].filter(Boolean).join(' ') || 'Unassigned'
