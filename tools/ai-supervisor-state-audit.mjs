import fs from 'node:fs'
const fail=m=>{throw new Error('[ai-supervisor-state-audit] '+m)}
const p={state:'docs/ai-state/CURRENT-STATE.yaml',checkpoint:'docs/ai-state/LAST-CHECKPOINT.md',journal:'docs/ai-state/EXECUTION-JOURNAL.md',queue:'docs/ai-state/COORDINATION-QUEUE.yaml',claims:'docs/ai-state/DETERMINISTIC-CLAIMS.yaml',runner:'benchmarks/runner/registry.json',agents:'AGENTS.md'}
for(const x of Object.values(p))if(!fs.existsSync(x))fail('missing '+x)
for(const [x,max] of [[p.state,12*1024],[p.checkpoint,16*1024],[p.journal,32*1024]])if(fs.statSync(x).size>max)fail(x+' exceeds compact size limit')
const parse=x=>{try{return JSON.parse(fs.readFileSync(x,'utf8'))}catch(e){fail(x+' must remain JSON-compatible YAML: '+e.message)}}
const s=parse(p.state),q=parse(p.queue),c=parse(p.claims),r=parse(p.runner),a=fs.readFileSync(p.agents,'utf8'),cp=fs.readFileSync(p.checkpoint,'utf8')
for(const k of ['observed_main_sha','active_issue','active_pr','active_branch','current_milestone','milestone_status','last_completed_milestone','exact_next_safe_action','pending_runner_ids','blocked_runner_ids','current_blockers','timeout_control'])if(!(k in s))fail('CURRENT-STATE missing '+k)
if(s.compact_state_path!=='docs/ai-state')fail('compact_state_path mismatch')
if(!/^[0-9a-f]{40}$/i.test(s.observed_main_sha||''))fail('observed_main_sha invalid')
if(!['PLANNING','IMPLEMENTING','VERIFYING','WAITING_EXTERNAL','BLOCKED','COMPLETE'].includes(s.milestone_status))fail('milestone_status unsupported')
if(s.timeout_control?.max_consolidated_status_refreshes_per_milestone!==1)fail('status refresh budget must default to one')
if(s.timeout_control?.tight_polling_allowed!==false)fail('tight polling must be false')
if(s.timeout_control?.rerun_on_message_delivery_timeout!==false)fail('message timeout rerun must be false')
if(q.non_authoritative_resume_index!==true)fail('coordination queue must be non-authoritative')
if(!Array.isArray(q.issues)||!Array.isArray(q.pull_requests))fail('coordination queue arrays missing')
const claimIds=new Set(); for(const x of c.claims||[]){if(!/^CLAIM-\d{3,}$/.test(x.id||''))fail('claim id invalid');if(claimIds.has(x.id))fail('duplicate claim '+x.id);claimIds.add(x.id)}
const runnerIds=new Set((r.entries||[]).map(x=>x.id))
for(const k of ['pending_runner_ids','blocked_runner_ids'])for(const id of s[k])if(!runnerIds.has(id))fail(k+' references missing '+id)
for(const id of s.blocked_runner_ids){const e=r.entries.find(x=>x.id===id);if(e?.definition_status!=='blocked')fail(id+' is not blocked in registry')}
for(const m of ['## Verified','## Not Verified','## Known Risk','## Next Action'])if(!cp.includes(m))fail('LAST-CHECKPOINT missing '+m)
for(const m of ['docs/ai-state/CURRENT-STATE.yaml','Runner registration NEVER grants execution authority','at most one consolidated CI/status refresh','OPEN GitHub Issues first','Message delivery timed out'])if(!a.includes(m))fail('AGENTS missing '+m)
console.log('AI supervisor compact state valid.')
