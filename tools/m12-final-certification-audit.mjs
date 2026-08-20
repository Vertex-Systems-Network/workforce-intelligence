import fs from 'node:fs'
import path from 'node:path'
import { spawnSync } from 'node:child_process'

const root=path.resolve(import.meta.dirname,'..'),failures=[]
/** Read one required final-certification source file or record it as missing. */
function read(relative){const target=path.join(root,relative);if(!fs.existsSync(target)){failures.push(`Missing ${relative}`);return''}return fs.readFileSync(target,'utf8')}
/** Require one marker without aborting the remaining M12 checks. */
function need(body,marker,label){if(!body.includes(marker))failures.push(`${label}: missing ${marker}`)}

const pkg=JSON.parse(read('package.json')||'{}'),vite=read('vite.config.ts'),ci=read('.github/workflows/ci.yml'),release=read('verify-release.cmd'),clean=read('verify-clean-install.cmd'),a11y=read('tests/e2e/accessibility-platform.spec.mjs'),budgets=read('docs/release/M12_CERTIFICATION_BUDGETS.json'),doctor=read('app/Console/Commands/FinalCertificationDoctor.php')
for(const script of ['performance:audit','performance:audit:build','audit:final-certification','certify'])if(!pkg.scripts?.[script])failures.push(`package.json missing ${script}`)
for(const marker of ['manualChunks','vendor-react','vendor-charts','vendor-editor','vendor-interaction','chunkSizeWarningLimit','sourcemap: false'])need(vite,marker,'Vite performance split')
for(const marker of ['performance:audit:build','workintel:final-certification','test:e2e:accessibility'])need(ci,marker,'CI final certification')
for(const marker of ['M12 final certification source audit','M12 production performance budget','M12 final Laravel doctor']){need(release,marker,'verify-release');need(clean,marker,'verify-clean-install')}
for(const marker of ['duplicate IDs','form controls have accessible names','main landmark','reduced motion','RTL','touch'])need(a11y,marker,'browser accessibility coverage')
for(const marker of ['javascript_total_gzip_bytes_max','routes_min','scheduled_workintel_jobs_max'])need(budgets,marker,'budget manifest')
for(const marker of ['ROUTES_MIN','SCHEDULED_WORKINTEL_MAX','--require-build','no_vite_hot'])need(doctor,marker,'final doctor')
if(fs.existsSync(path.join(root,'public/hot')))failures.push('public/hot must not exist in a certification source tree.')
const perf=spawnSync(process.execPath,[path.join(root,'tools/performance-budget-audit.mjs')],{cwd:root,encoding:'utf8'});if(perf.status!==0)failures.push(`Performance source budget failed: ${(perf.stderr||perf.stdout).trim()}`)
if(failures.length){console.error('M12 final certification source audit: FAIL');for(const failure of failures)console.error(`- ${failure}`);process.exit(1)}
console.log('M12 final certification source audit: PASS')
console.log((perf.stdout||'').trim())
