import fs from 'node:fs'
const fail=m=>{throw new Error('[runner-benchmark-audit] '+m)}
let r;try{r=JSON.parse(fs.readFileSync('benchmarks/runner/registry.json','utf8'))}catch(e){fail('registry invalid JSON: '+e.message)}
if(r.schema_version!==3)fail('schema_version must be 3')
if(r.execution_policy!=='authorization-aware-definitions-with-external-exact-head-results')fail('execution policy mismatch')
if(r.result_envelope_schema!=='benchmarks/runner/result-envelope.schema.json')fail('result envelope schema path mismatch')
const ids=new Set(),templates=new Set(),status=new Set(['queued','blocked','superseded']),policies=new Set(['final-runner-batch','immediate']),auth=new Set(['repository-policy','explicit-current','not-authorized','expired','consumed'])
for(const e of r.entries){
 if(!/^RB-\d{3,}$/.test(e.id))fail(e.id+': invalid id')
 if(ids.has(e.id))fail(e.id+': duplicate benchmark id');ids.add(e.id)
 if(typeof e.dedup_key_template!=='string'||!e.dedup_key_template.includes('{candidate_head_sha}'))fail(e.id+': dedup_key_template must include {candidate_head_sha}')
 if(templates.has(e.dedup_key_template))fail(e.id+': duplicate deterministic dedup template');templates.add(e.dedup_key_template)
 if(!e.registered_source_identity||!/^[0-9a-f]{40}$/i.test(e.registered_source_identity.registered_head_sha||''))fail(e.id+': exact registered source SHA required')
 if('candidate_head_sha' in e.registered_source_identity)fail(e.id+': committed registry must not store runtime candidate_head_sha')
 if(!policies.has(e.execution_policy))fail(e.id+': execution policy invalid')
 if(e.execution_policy==='immediate'&&!(typeof e.immediate_reason==='string'&&e.immediate_reason))fail(e.id+': immediate reason required')
 if(!auth.has(e.authorization?.state))fail(e.id+': authorization state invalid')
 if(typeof e.security_critical!=='boolean'||typeof e.merge_blocking!=='boolean')fail(e.id+': safety classifications required')
 if(!Number.isInteger(e.expected_runner_time?.minutes)||e.expected_runner_time.minutes<=0)fail(e.id+': expected runner time required')
 for(const k of ['environment','matrix','inputs','fixtures'])if(!Array.isArray(e.execution_identity?.[k])||!e.execution_identity[k].length)fail(e.id+': '+k+' identity required')
 if(!Array.isArray(e.commands)||!e.commands.length)fail(e.id+': command/workflow required')
 if(!status.has(e.definition_status))fail(e.id+': definition_status invalid')
 if(e.result_recording?.mode!=='external-exact-head-envelope')fail(e.id+': result recording mode invalid')
 if(e.result_recording?.schema!=='benchmarks/runner/result-envelope.schema.json')fail(e.id+': result schema mismatch')
 if(e.result_recording?.candidate_source_must_not_be_mutated_for_result_recording!==true)fail(e.id+': candidate mutation guard required')
 if('verification' in e)fail(e.id+': committed task definition must not contain terminal verification evidence')
}
console.log('Runner benchmark registry valid: schema v3 task definitions with external exact-head result envelopes.')
