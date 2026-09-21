import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'

const read=p=>fs.readFileSync(p,'utf8')
const parse=p=>JSON.parse(read(p))

test('compact AI supervisor state is machine-readable, bounded and resume-safe', () => {
  const state=parse('docs/ai-state/CURRENT-STATE.yaml')
  const queue=parse('docs/ai-state/COORDINATION-QUEUE.yaml')
  const claims=parse('docs/ai-state/DETERMINISTIC-CLAIMS.yaml')
  const registry=parse('benchmarks/runner/registry.json')

  assert.equal(state.compact_state_path,'docs/ai-state')
  assert.match(state.observed_main_sha,/^[0-9a-f]{40}$/i)
  for(const key of ['active_issue','active_pr','active_branch','current_milestone','milestone_status','last_completed_milestone','exact_next_safe_action','pending_runner_ids','blocked_runner_ids','current_blockers','timeout_control']) assert.ok(Object.hasOwn(state,key),`CURRENT-STATE missing ${key}`)
  assert.equal(state.timeout_control.max_consolidated_status_refreshes_per_milestone,1)
  assert.equal(state.timeout_control.tight_polling_allowed,false)
  assert.equal(state.timeout_control.rerun_on_message_delivery_timeout,false)

  assert.ok(fs.statSync('docs/ai-state/CURRENT-STATE.yaml').size<=12*1024)
  assert.ok(fs.statSync('docs/ai-state/LAST-CHECKPOINT.md').size<=16*1024)
  assert.ok(fs.statSync('docs/ai-state/EXECUTION-JOURNAL.md').size<=32*1024)

  assert.equal(queue.non_authoritative_resume_index,true)
  assert.ok(queue.issues.some(x=>x.number===70&&x.state==='open'))
  assert.ok(queue.pull_requests.some(x=>x.number===65&&x.state==='open'))
  assert.equal(new Set(claims.claims.map(x=>x.id)).size,claims.claims.length)

  const ids=new Set(registry.entries.map(x=>x.id))
  for(const id of [...state.pending_runner_ids,...state.blocked_runner_ids]) assert.ok(ids.has(id),`missing runner ${id}`)
})

test('issue 70 runner diagnostic is immediate by safety class but unauthorized and blocked', () => {
  const registry=parse('benchmarks/runner/registry.json')
  const task=registry.entries.find(x=>x.id==='RB-005')
  assert.ok(task)
  assert.equal(task.execution_policy,'immediate')
  assert.equal(task.immediate_reason,'incident-recovery-and-data-safety')
  assert.equal(task.security_critical,true)
  assert.equal(task.authorization.state,'not-authorized')
  assert.equal(task.status,'blocked')
  assert.match(task.source_identity.candidate_head_sha,/^[0-9a-f]{40}$/i)
})
