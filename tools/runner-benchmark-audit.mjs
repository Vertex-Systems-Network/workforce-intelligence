import fs from 'node:fs'
const fail=m=>{throw new Error('[runner-benchmark-audit] '+m)}
let r; try{r=JSON.parse(fs.readFileSync('benchmarks/runner/registry.json','utf8'))}catch(e){fail('registry invalid JSON: '+e.message)}
if(r.schema_version!==2)fail('schema_version must be 2')
if(r.execution_policy!=='authorization-aware-final-batch-with-immediate-exceptions')fail('execution policy mismatch')
const ids=new Set(),dedup=new Set(),status=new Set(['queued','ready','running','blocked','passed','failed','superseded']),policies=new Set(['final-runner-batch','immediate']),auth=new Set(['repository-policy','explicit-current','not-authorized','expired','consumed'])
for(const e of r.entries){
 if(!/^RB-\d{3,}$/.test(e.id))fail(e.id+': invalid id')
 if(ids.has(e.id))fail(e.id+': duplicate benchmark id'); ids.add(e.id)
 if(typeof e.dedup_key!=='string'||!e.dedup_key)fail(e.id+': missing deterministic dedup key')
 if(dedup.has(e.dedup_key))fail(e.id+': duplicate deterministic dedup key'); dedup.add(e.dedup_key)
 if(!e.source_identity||!/^[0-9a-f]{40}$/i.test(e.source_identity.registered_head_sha||''))fail(e.id+': exact registered source SHA required')
 if(e.source_identity.candidate_head_sha!==null&&!/^[0-9a-f]{40}$/i.test(e.source_identity.candidate_head_sha))fail(e.id+': candidate SHA invalid')
 if(!policies.has(e.execution_policy))fail(e.id+': execution policy invalid')
 if(e.execution_policy==='immediate'&&!(typeof e.immediate_reason==='string'&&e.immediate_reason))fail(e.id+': immediate reason required')
 if(!auth.has(e.authorization?.state))fail(e.id+': authorization state invalid')
 if(typeof e.security_critical!=='boolean'||typeof e.merge_blocking!=='boolean')fail(e.id+': safety classifications required')
 if(!Number.isInteger(e.expected_runner_time?.minutes)||e.expected_runner_time.minutes<=0)fail(e.id+': expected runner time required')
 for(const k of ['environment','matrix','inputs','fixtures'])if(!Array.isArray(e.execution_identity?.[k])||!e.execution_identity[k].length)fail(e.id+': '+k+' identity required')
 if(!Array.isArray(e.commands)||!e.commands.length)fail(e.id+': command/workflow required')
 if(!status.has(e.status))fail(e.id+': status invalid')
 if(['ready','running','passed','failed'].includes(e.status)&&!e.source_identity.candidate_head_sha)fail(e.id+': exact candidate SHA required before ready/running/terminal')
 if(e.status==='running'&&!['repository-policy','explicit-current'].includes(e.authorization.state))fail(e.id+': running requires current execution authorization; registration never grants authority')
 const v=e.verification
 if(!v||!Array.isArray(v.evidence))fail(e.id+': verification invalid')
 for(const ev of v.evidence)if(ev?.immutable!==true||!ev.ref)fail(e.id+': terminal evidence must be immutable=true')
 if(['passed','failed'].includes(e.status)){
   if(!/^[0-9a-f]{40}$/i.test(v.head_sha||''))fail(e.id+': terminal verification SHA required')
   if(v.head_sha!==e.source_identity.candidate_head_sha)fail(e.id+': terminal verification SHA must equal exact candidate_head_sha')
   if(Number.isNaN(Date.parse(v.verified_at))||!v.evidence.length)fail(e.id+': immutable terminal evidence required')
 }else if(v.head_sha!==null||v.verified_at!==null||v.evidence.length)fail(e.id+': nonterminal verification must be empty')
}
console.log('Runner benchmark registry valid: schema v2, authorization-aware, deduplicated, exact-head disciplined.')
