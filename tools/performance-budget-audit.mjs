import fs from 'node:fs'
import path from 'node:path'
import zlib from 'node:zlib'

const root=path.resolve(import.meta.dirname,'..')
const budgets=JSON.parse(fs.readFileSync(path.join(root,'docs/release/M12_CERTIFICATION_BUDGETS.json'),'utf8'))
const requireBuild=process.argv.includes('--require-build')
const failures=[]

/** Recursively collect files matching one extension allowlist. */
function filesUnder(relative,extensions){const base=path.join(root,relative),out=[];if(!fs.existsSync(base))return out;for(const entry of fs.readdirSync(base,{withFileTypes:true})){const target=path.join(base,entry.name),rel=path.relative(root,target).replaceAll('\\','/');if(entry.isDirectory())out.push(...filesUnder(rel,extensions));else if(extensions.has(path.extname(entry.name)))out.push({path:rel,bytes:fs.statSync(target).size})}return out}
/** Add one bounded performance failure while preserving the complete audit report. */
function maxCheck(label,value,max){if(value>max)failures.push(`${label}: ${value} > ${max}`)}

const js=filesUnder('resources/js',new Set(['.ts','.tsx','.js','.mjs']))
const css=filesUnder('resources/css',new Set(['.css']))
const jsTotal=js.reduce((sum,row)=>sum+row.bytes,0),cssTotal=css.reduce((sum,row)=>sum+row.bytes,0)
const nonI18n=js.filter(row=>!row.path.startsWith('resources/js/i18n/')).sort((a,b)=>b.bytes-a.bytes)
const i18n=js.filter(row=>row.path.startsWith('resources/js/i18n/')).sort((a,b)=>b.bytes-a.bytes)
maxCheck('source JS/TS total bytes',jsTotal,budgets.source.js_ts_total_bytes_max)
maxCheck('source JS/TS file count',js.length,budgets.source.js_ts_files_max)
maxCheck('largest non-i18n source bytes',nonI18n[0]?.bytes??0,budgets.source.largest_non_i18n_source_bytes_max)
maxCheck('largest i18n source bytes',i18n[0]?.bytes??0,budgets.source.i18n_source_bytes_max)
maxCheck('source CSS total bytes',cssTotal,budgets.source.css_total_bytes_max)
console.log(`M12 source budgets: JS/TS ${js.length} files / ${jsTotal} bytes; largest non-i18n ${nonI18n[0]?.bytes??0}; CSS ${cssTotal} bytes.`)

const buildRoot=path.join(root,'public/build')
if(requireBuild&&!fs.existsSync(path.join(buildRoot,'manifest.json')))failures.push('Production build manifest is required but public/build/manifest.json is missing.')
if(fs.existsSync(path.join(buildRoot,'manifest.json'))){
 const assets=filesUnder('public/build',new Set(['.js','.css','.map']))
 const maps=assets.filter(row=>row.path.endsWith('.map')),builtJs=assets.filter(row=>row.path.endsWith('.js')),builtCss=assets.filter(row=>row.path.endsWith('.css'))
 const builtJsTotal=builtJs.reduce((s,r)=>s+r.bytes,0),builtCssTotal=builtCss.reduce((s,r)=>s+r.bytes,0)
 const gzipTotal=builtJs.reduce((sum,row)=>sum+zlib.gzipSync(fs.readFileSync(path.join(root,row.path)),{level:9}).length,0)
 const largest=[...builtJs].sort((a,b)=>b.bytes-a.bytes)[0]
 maxCheck('built JavaScript total bytes',builtJsTotal,budgets.build.javascript_total_bytes_max)
 maxCheck('built JavaScript gzip bytes',gzipTotal,budgets.build.javascript_total_gzip_bytes_max)
 maxCheck('largest JavaScript asset bytes',largest?.bytes??0,budgets.build.largest_javascript_asset_bytes_max)
 maxCheck('built CSS total bytes',builtCssTotal,budgets.build.css_total_bytes_max)
 maxCheck('build asset count',assets.length,budgets.build.asset_count_max)
 if(!budgets.build.source_maps_allowed&&maps.length)failures.push(`Production build contains ${maps.length} source map(s).`)
 console.log(`M12 build budgets: JS ${builtJsTotal} bytes / gzip ${gzipTotal}; largest ${largest?.bytes??0}; CSS ${builtCssTotal}; assets ${assets.length}.`)
}

if(failures.length){console.error('M12 performance budget audit: FAIL');for(const failure of failures)console.error(`- ${failure}`);process.exit(1)}
console.log(`M12 performance budget audit: PASS${requireBuild?' (build required)':''}`)
