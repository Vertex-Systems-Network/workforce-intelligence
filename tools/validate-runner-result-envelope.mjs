import fs from 'node:fs'
const fail=m=>{throw new Error('[runner-result-envelope] '+m)}
const file=process.argv[2]
if(!file)fail('usage: node tools/validate-runner-result-envelope.mjs <result.json>')
let x;try{x=JSON.parse(fs.readFileSync(file,'utf8'))}catch(e){fail('invalid JSON: '+e.message)}
if(x.schema_version!==1)fail('schema_version must be 1')
if(!/^RB-\d{3,}$/.test(x.task_id||''))fail('task_id must match RB-###')
if(x.candidate_source_identity?.repository!=='Vertex-Systems-Network/workforce-intelligence')fail('repository mismatch')
if(typeof x.candidate_source_identity?.ref!=='string'||!x.candidate_source_identity.ref)fail('candidate ref required')
if(!/^[0-9a-f]{40}$/i.test(x.candidate_source_identity?.head_sha||''))fail('exact candidate head SHA required')
if(typeof x.dedup_key!=='string'||!x.dedup_key.includes(x.candidate_source_identity.head_sha))fail('dedup_key must include exact candidate SHA')
if(!['repository-policy','explicit-current'].includes(x.authorization?.state))fail('terminal result requires current execution authorization')
if(typeof x.authorization?.authority_ref!=='string'||!x.authorization.authority_ref)fail('authority_ref required')
for(const k of ['environment','matrix','inputs','fixtures'])if(!Array.isArray(x.execution_identity?.[k])||!x.execution_identity[k].length)fail(k+' identity required')
if(!['passed','failed'].includes(x.status))fail('terminal status must be passed or failed')
for(const k of ['started_at','completed_at'])if(typeof x[k]!=='string'||Number.isNaN(Date.parse(x[k])))fail(k+' must be parseable timestamp')
if(new Date(x.completed_at)<new Date(x.started_at))fail('completed_at cannot precede started_at')
if(!Array.isArray(x.evidence)||!x.evidence.length)fail('terminal immutable evidence required')
for(const ev of x.evidence)if(ev?.immutable!==true||typeof ev.ref!=='string'||!ev.ref||typeof ev.kind!=='string'||!ev.kind)fail('each evidence item requires kind/ref and immutable=true')
console.log('Runner result envelope valid for '+x.task_id+' @ '+x.candidate_source_identity.head_sha+'.')
