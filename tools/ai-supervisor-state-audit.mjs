import fs from 'node:fs'
const fail=m=>{throw new Error('[ai-supervisor-state-audit] '+m)}
const p={state:'docs/ai-state/CURRENT-STATE.yaml',checkpoint:'docs/ai-state/LAST-CHECKPOINT.md',journal:'docs/ai-state/EXECUTION-JOURNAL.md',queue:'docs/ai-state/COORDINATION-QUEUE.yaml',claims:'docs/ai-state/DETERMINISTIC-CLAIMS.yaml',runner:'benchmarks/runner/registry.json',agents:'AGENTS.md',fast:'docs/ai-state/FAST-BATCH-EXECUTION.md',response:'docs/ai-state/USER-RESPONSE-CONTRACT.md',options:'.ai/NEXT-ACTION-OPTIONS.md',statusCheckpoint:'docs/status/AI_CHECKPOINT.md',m14:'docs/architecture/M14_RELEASE_TRUST_READINESS.md'}
for(const x of Object.values(p))if(!fs.existsSync(x))fail('missing '+x)
for(const [x,max] of [[p.state,12*1024],[p.checkpoint,16*1024],[p.journal,32*1024]])if(fs.statSync(x).size>max)fail(x+' exceeds compact size limit')
const parse=x=>{try{return JSON.parse(fs.readFileSync(x,'utf8'))}catch(e){fail(x+' must remain JSON-compatible YAML: '+e.message)}}
const s=parse(p.state),q=parse(p.queue),c=parse(p.claims),r=parse(p.runner),a=fs.readFileSync(p.agents,'utf8'),cp=fs.readFileSync(p.checkpoint,'utf8'),readme=fs.readFileSync('README.md','utf8'),fast=fs.readFileSync(p.fast,'utf8'),response=fs.readFileSync(p.response,'utf8'),options=fs.readFileSync(p.options,'utf8'),statusCheckpoint=fs.readFileSync(p.statusCheckpoint,'utf8'),m14=fs.readFileSync(p.m14,'utf8')
for(const k of ['observed_main_sha','active_issue','active_pr','active_branch','current_milestone','milestone_status','last_completed_milestone','exact_next_safe_action','pending_runner_ids','blocked_runner_ids','current_blockers','timeout_control','fallback_work_policy','maintenance_discovery_policy','state_sync_policy','response_status'])if(!(k in s))fail('CURRENT-STATE missing '+k)
if(s.compact_state_path!=='docs/ai-state')fail('compact_state_path mismatch')
if(!/^[0-9a-f]{40}$/i.test(s.observed_main_sha||''))fail('observed_main_sha invalid')
if(!['PLANNING','IMPLEMENTING','VERIFYING','WAITING_EXTERNAL','BLOCKED','COMPLETE'].includes(s.milestone_status))fail('milestone_status unsupported')
if(s.timeout_control?.max_consolidated_status_refreshes_per_milestone!==1)fail('status refresh budget must default to one')
if(s.timeout_control?.tight_polling_allowed!==false)fail('tight polling must be false')
if(s.timeout_control?.rerun_on_message_delivery_timeout!==false)fail('message timeout rerun must be false')
if(s.timeout_control?.fast_batch_default!==true)fail('Fast-Batch must default true')
if(s.timeout_control?.routine_substep_confirmation_required!==false)fail('routine substep confirmation must default false')
if(s.timeout_control?.continuous_execution_default!==true)fail('continuous execution must default true')
if(s.timeout_control?.chain_safe_milestones_without_user_reply!==true)fail('safe milestone chaining must default true')
if(s.timeout_control?.technical_blocker_confirmation_required!==false)fail('technical blocker confirmation must default false')
if(s.timeout_control?.waiting_external_lane_stops_execution_window!==false)fail('waiting external lane must not stop execution window')
if(s.timeout_control?.readme_progress_sync_each_milestone!==true)fail('README progress sync must remain enabled for each milestone')
if(s.timeout_control?.authority_drift_requires_user_confirmation!==false)fail('authority drift must not require user confirmation')
if(s.timeout_control?.rehydrate_material_drift_in_same_window!==true)fail('material drift must rehydrate in the same execution window')
if(s.timeout_control?.terminal_engineering_choice_prompt_default!==false)fail('terminal engineering choice prompts must default false')
const fw=s.fallback_work_policy
if(!fw||fw.enabled!==true||fw.scan_before_terminal_handoff!==true||fw.automatic_selection!==true)fail('fallback work policy must stay enabled and automatic')
if(fw.stop_early_to_conserve_tokens!==false)fail('fallback policy must forbid voluntary token-conservation stop')
if(fw.may_invent_product_scope!==false)fail('fallback policy must not invent product scope')
if(fw.state_pointer_only_drift_is_actionable!==false)fail('pointer-only state drift must not be standalone fallback work')
if(fw.empty_queue_green_ci_is_terminal_proof!==false)fail('empty queue + green CI must not be terminal proof')
if(fw.maintenance_discovery_required_before_terminal!==true)fail('maintenance discovery must be required before terminal handoff')
const md=s.maintenance_discovery_policy
if(!md||md.enabled!==true||md.required_before_no_actionable_work_claim!==true||md.one_bounded_fresh_pass_per_execution_window!==true)fail('maintenance discovery policy must remain enabled and required')
if(md.no_tight_repeat_of_clean_lane!==true)fail('clean maintenance discovery lanes must not tight-loop')
if(md.finding_becomes_actionable_maintenance_lane!==true)fail('maintenance discovery findings must become actionable lanes')
if(md.may_create_tracking_issue_for_concrete_finding!==true)fail('concrete maintenance findings must be trackable without owner choice')
if(md.no_noop_or_status_only_commits!==true)fail('maintenance discovery must forbid no-op/status-only commits')
const discoveryLanes=['ci_test_reliability','dependency_supply_chain_security','source_quality_regression_test_gaps','semantic_docs_governance_drift','accessibility_performance_browser_runtime','blocked_issue_non_destructive_diagnostics']
if(JSON.stringify(md.lanes)!==JSON.stringify(discoveryLanes))fail('maintenance discovery lane order drifted')
const sp=s.state_sync_policy
if(!sp||sp.observed_main_sha_is_resume_anchor_not_live_authority!==true)fail('observed main SHA must remain a resume anchor')
if(sp.recursive_post_merge_state_only_pr_forbidden!==true)fail('recursive post-merge state-only PRs must be forbidden')
if(sp.pointer_only_drift_reconciled_in_memory!==true)fail('pointer-only drift must reconcile in memory')
if(sp.semantic_status_drift_requires_source_sync!==true)fail('semantic status drift must require source sync')
if(sp.merge_evidence_may_live_on_pr_issue_until_next_substantive_mutation!==true)fail('merge evidence deferral contract missing')
if(sp.committed_state_is_semantic_checkpoint!==true)fail('committed state must be a semantic checkpoint')
if(sp.live_transaction_status_read_from_github!==true)fail('live transaction status must come from GitHub')
if(sp.committed_active_pr_is_live_authority!==false)fail('committed active_pr must not be live authority')
if(sp.committed_active_branch_is_live_authority!==false)fail('committed active_branch must not be live authority')
if(sp.readme_includes_volatile_transaction_fields!==false)fail('README must exclude volatile transaction fields')
if(sp.post_merge_state_only_reconciliation_required!==false)fail('post-merge state-only reconciliation must not be required')
if(sp.closed_authority_issue_requires_user_prompt!==false)fail('closed authority issue must not require user prompt')
if(sp.last_completed_transaction_evidence_may_live_on_github!==true)fail('transaction completion evidence may live on GitHub')
const fallbackOrder=['accepted_open_pr_repair_review_merge','accepted_actionable_open_issue','ci_test_security_review_audit_state_branch_repair','authorized_dependency_supply_chain_maintenance','docs_compact_state_readme_coordination_lifecycle_reconciliation','non_destructive_evidence_diagnostics_for_existing_blocker','maintenance_discovery_sweep']
if(JSON.stringify(fw.order)!==JSON.stringify(fallbackOrder))fail('fallback work order drifted')
const rs=s.response_status
if(!rs||typeof rs!=='object')fail('response_status must be an object')
for(const k of ['repository_name','current_work','current_module','module_progress','overall_progress','bar_cells'])if(!(k in rs))fail('response_status missing '+k)
if(rs.repository_name!=='Vertex-Systems-Network/workforce-intelligence')fail('response_status repository_name mismatch')
if(typeof rs.current_work!=='string'||!rs.current_work.trim())fail('response_status current_work required')
if(typeof rs.current_module!=='string'||!rs.current_module.trim())fail('response_status current_module required')
if(!Number.isInteger(rs.bar_cells)||rs.bar_cells!==10)fail('response_status bar_cells must be 10')
for(const [name,p] of [['module_progress',rs.module_progress],['overall_progress',rs.overall_progress]]){
 if(!p||typeof p!=='object')fail('response_status '+name+' must be an object')
 if(!Number.isInteger(p.percent)||p.percent<0||p.percent>100)fail('response_status '+name+'.percent must be 0..100 integer')
 if(typeof p.basis!=='string'||!p.basis.trim())fail('response_status '+name+'.basis required')
}
if(typeof rs.overall_progress.scope!=='string'||!rs.overall_progress.scope.trim())fail('response_status overall_progress.scope required')
const progressBar=percent=>{const cells=Math.max(0,Math.min(10,Math.round(percent/10)));return '█'.repeat(cells)+'░'.repeat(10-cells)}
for(const marker of ['<!-- AI-PROGRESS:START -->','<!-- AI-PROGRESS:END -->','## AI Development Progress'])if(!readme.includes(marker))fail('README progress block missing '+marker)
const readmeExpected=[
  '**Repo:** `'+rs.repository_name+'`',
  '**Current Work:** '+rs.current_work,
  '**Current Module:** '+rs.current_module,
  '**Module Progress:** ['+progressBar(rs.module_progress.percent)+'] **'+rs.module_progress.percent+'%**',
  '**Overall Progress:** ['+progressBar(rs.overall_progress.percent)+'] **'+rs.overall_progress.percent+'%** — '+rs.overall_progress.scope,
  '**Active Issue:** '+(s.active_issue===null?'none':'#'+s.active_issue),
  '**Last Completed:** '+s.last_completed_milestone,
  '**Next Action:** '+s.exact_next_safe_action,
]
for(const marker of readmeExpected)if(!readme.includes(marker))fail('README progress block stale/missing: '+marker)
const progressBlock=readme.slice(readme.indexOf('<!-- AI-PROGRESS:START -->'),readme.indexOf('<!-- AI-PROGRESS:END -->'))
for(const volatile of ['**Active PR:**','**Active Branch:**'])if(progressBlock.includes(volatile))fail('README progress block contains volatile transaction field '+volatile)

if(q.non_authoritative_resume_index!==true)fail('coordination queue must be non-authoritative')
if(!Array.isArray(q.issues)||!Array.isArray(q.pull_requests))fail('coordination queue arrays missing')
const claimIds=new Set(); for(const x of c.claims||[]){if(!/^CLAIM-\d{3,}$/.test(x.id||''))fail('claim id invalid');if(claimIds.has(x.id))fail('duplicate claim '+x.id);claimIds.add(x.id)}
const runnerIds=new Set((r.entries||[]).map(x=>x.id))
for(const k of ['pending_runner_ids','blocked_runner_ids'])for(const id of s[k])if(!runnerIds.has(id))fail(k+' references missing '+id)
for(const id of s.blocked_runner_ids){const e=r.entries.find(x=>x.id===id);if(e?.definition_status!=='blocked')fail(id+' is not blocked in registry')}
for(const m of ['## Verified','## Not Verified','## Known Risk','## Next Action'])if(!cp.includes(m))fail('LAST-CHECKPOINT missing '+m)
for(const m of ['This is not a repository-wide stop','mandatory maintenance-discovery sweep','no-work handoff is valid only after that bounded fresh sweep is clean'])if(!cp.includes(m))fail('LAST-CHECKPOINT missing maintenance continuation: '+m)
if(cp.includes('Continue only when new authorized non-secret evidence is available'))fail('LAST-CHECKPOINT still contains external-wait terminal instruction')
for(const m of ['Current continuation semantics — 2026-10-08','closed authorization provenance','not a live gate','Live PR, branch, check and protected-main identities are resolved from GitHub at runtime','Historical promotion authority issue','superseded by owner-authorized AI-only single-maintainer review governance in Issue #96','current continuation must use live GitHub state'])if(!statusCheckpoint.includes(m))fail('AI_CHECKPOINT stale authority/review mirror: '+m)
for(const forbidden of ['**Current product authority issue:**','Independent review is required.','obtain independent review'])if(statusCheckpoint.includes(forbidden))fail('AI_CHECKPOINT contains superseded live review/authority gate: '+forbidden)
for(const m of ['**State:** `VERIFYING / PARTIALLY COMPLETE`','**Authority provenance:** historical owner approval in closed GitHub Issue #50','**Current continuation authority:** open Issue #62','never a reason to prompt for re-approval'])if(!m14.includes(m))fail('M14 authority mirror stale: '+m)
for(const forbidden of ['GitHub Issue #50 is the current planning/product-authority gate only','stop and rehydrate if main/head/scope/governance/authorization moved materially'])if(a.includes(forbidden))fail('AGENTS contains stale prompt trigger: '+forbidden)
for(const m of ['Do not show numbered next-action choices by default','Closed/superseded Issues, stale compact-state pointers','no open PR','maintenance-discovery sweep'])if(!response.includes(m))fail('response contract missing prompt/discovery suppression: '+m)
for(const m of ['Routine technical work never requires user confirmation','Before asking, perform a fresh live-authority reconciliation','Maintenance discovery when surfaced queues are empty','no open PR + green CI + externally blocked active milestone'])if(!fast.includes(m))fail('Fast-Batch prompt/discovery contract missing: '+m)
for(const m of ['autonomous continuation','Do **not** present numbered 1/2/3 engineering choices','resume/rehydration entry','bounded maintenance-discovery sweep'])if(!options.includes(m))fail('next-action options contract missing autonomous/discovery rule: '+m)
if(options.includes('Respond with 1 to 3 shuffled valid next-action options'))fail('URL-only repository entry must not force numbered options')
for(const m of ['docs/ai-state/CURRENT-STATE.yaml','Runner registration NEVER grants execution authority','at most one consolidated CI/status refresh','OPEN GitHub Issues first','Message delivery timed out','Repo:','Current Work:','Current Module:','Module Progress:','Overall Progress:','README progress synchronization contract','every completed milestone','Fast-Batch default','Continuous autonomous execution window','Milestone completion is a checkpoint','Do **not** ask the user to diagnose or confirm a technical repair','A single blocked lane','Mandatory fallback work scan','Mandatory maintenance-discovery sweep before "no actionable work"','Do not voluntarily stop merely to conserve tokens/context','Post-merge recursion guard','expected post-merge pointer drift','User-prompt suppression and authority reconciliation','Committed state is a semantic checkpoint, not a live transaction ledger','reconcile protected `main`, OPEN Issues, OPEN PRs','do not hand control back merely because repository state moved'])if(!a.includes(m))fail('AGENTS missing '+m)
console.log('AI supervisor compact state valid.')
