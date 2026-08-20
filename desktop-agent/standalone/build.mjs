import { build } from 'esbuild'
import { copyFileSync, mkdirSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { spawnSync } from 'node:child_process'

const here=dirname(fileURLToPath(import.meta.url));const agent=resolve(here,'../native-agent.mjs');const out=resolve(here,'dist');mkdirSync(out,{recursive:true})
const bundle=resolve(out,'agent.cjs');await build({entryPoints:[agent],bundle:true,platform:'node',format:'cjs',target:'node20',outfile:bundle,banner:{js:'/* WorkIntel Agent standalone bundle */'}})
const blob=resolve(out,'sea-prep.blob');writeFileSync(resolve(out,'sea-config.json'),JSON.stringify({main:bundle,output:blob,disableExperimentalSEAWarning:true,useSnapshot:false,useCodeCache:false},null,2))
/** Handles the run operation for the WorkIntel application. */ function run(cmd,args){const r=spawnSync(cmd,args,{stdio:'inherit',shell:process.platform==='win32'});if(r.status!==0)process.exit(r.status??1)}
run(process.execPath,['--experimental-sea-config',resolve(out,'sea-config.json')])
const executable=resolve(out,process.platform==='win32'?'WorkIntelAgent.exe':'WorkIntelAgent');copyFileSync(process.execPath,executable)
if(process.platform==='darwin')run('codesign',['--remove-signature',executable])
const postject=process.platform==='win32'?resolve(here,'node_modules/.bin/postject.cmd'):resolve(here,'node_modules/.bin/postject')
const args=[executable,'NODE_SEA_BLOB',blob,'--sentinel-fuse','NODE_SEA_FUSE_fce680ab2cc467b6e072b8b5df1996b2']
if(process.platform==='darwin')args.push('--macho-segment-name','NODE_SEA')
run(postject,args)
if(process.platform==='darwin')run('codesign',['--sign','-',executable])
console.log(`Standalone agent: ${executable}`)
