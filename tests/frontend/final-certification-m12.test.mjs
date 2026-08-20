import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'
/** Read one repository file for M12 dependency-free release contracts. */
const read=file=>fs.readFileSync(file,'utf8')

test('M12 defines measurable source build and runtime release budgets',()=>{const b=JSON.parse(read('docs/release/M12_CERTIFICATION_BUDGETS.json'));assert.ok(b.source.js_ts_total_bytes_max>0);assert.ok(b.build.javascript_total_gzip_bytes_max>0);assert.ok(b.runtime.routes_min>=700);assert.ok(b.runtime.routes_max>b.runtime.routes_min)})
test('M12 production build splits heavyweight vendor families and disables source maps',()=>{const vite=read('vite.config.ts');for(const token of ['manualChunks','vendor-react','vendor-charts','vendor-editor','vendor-interaction','sourcemap: false'])assert.ok(vite.includes(token),token)})
test('M12 package and CI make performance and final runtime certification mandatory',()=>{const pkg=JSON.parse(read('package.json')),ci=read('.github/workflows/ci.yml');for(const key of ['performance:audit','performance:audit:build','audit:final-certification','certify'])assert.ok(pkg.scripts[key],key);assert.match(ci,/performance:audit:build/);assert.match(ci,/workintel:final-certification/)})
test('M12 final Laravel doctor guards route scheduler build and hot-file release state',()=>{const doctor=read('app/Console/Commands/FinalCertificationDoctor.php');for(const token of ['route_budget','scheduler_budget','frontend_build','no_vite_hot','PDO::getAvailableDrivers'])assert.ok(doctor.includes(token),token)})
test('M12 browser contract scans duplicate IDs forms landmarks focus RTL reduced-motion touch and reflow',()=>{const e2e=read('tests/e2e/accessibility-platform.spec.mjs');for(const token of ['duplicate IDs','form controls have accessible names','main landmark','focus','RTL','reduced motion','touch','expectReflow'])assert.ok(e2e.includes(token),token)})
