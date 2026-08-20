import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'

const architecture=JSON.parse(fs.readFileSync('docs/architecture/workintel-modules.json','utf8'))
const sidebar=fs.readFileSync('resources/js/components/Sidebar.tsx','utf8')
const app=fs.readFileSync('resources/js/WorkforceApp.tsx','utf8')
const nav=JSON.parse(fs.readFileSync('resources/js/navigation.manifest.json','utf8'))

test('M1 architecture declares the locked workspace module taxonomy',()=>{
  assert.deepEqual(architecture.modules.map(module=>module.id),[
    'home','work-management','collaboration','time-attendance','people-hr','workforce-operations','clients-commerce','content-studio','finance-payroll','intelligence','administration',
  ])
  assert.equal(architecture.surfaces.find(surface=>surface.id==='platform-console')?.kind,'separate-app-shell')
  assert.equal(architecture.principles.featurePagesUseDesignSystemOnly,true)
  assert.equal(architecture.principles.descriptionsRequiredForPrimaryPages,true)
})

test('every current sidebar page has a documented target and decision',()=>{
  const pageType=(sidebar.match(/export type Page\s*=([\s\S]*?)(?:\n\n|\/\*\*)/)||[])[1]||''
  const ids=[...pageType.matchAll(/'([^']+)'/g)].map(match=>match[1])
  assert.equal(ids.length,39)
  for(const id of ids){
    const row=architecture.screenMap[id]
    assert.ok(row,`missing architecture row for ${id}`)
    assert.ok(row.description,`missing description for ${id}`)
    assert.ok(['KEEP','MERGE','RENAME','MOVE','REMOVE'].includes(row.decision),`bad decision for ${id}`)
  }
})

test('navigation and application rendering cannot introduce an unclassified page',()=>{
  const cases=[...app.matchAll(/case\s+'([^']+)'\s*:/g)].map(match=>match[1])
  const navIds=[...new Set(Object.values(nav).flatMap(groups=>groups.flatMap(group=>group.items.map(item=>item[0]))))]
  for(const id of [...cases,...navIds]) assert.ok(architecture.screenMap[id],`unclassified runtime page ${id}`)
})

test('known confusing destinations have explicit consolidation decisions',()=>{
  assert.equal(architecture.screenMap.shifts.decision,'MERGE')
  assert.equal(architecture.screenMap.shifts.mergeInto,'schedule')
  assert.equal(architecture.screenMap.platform.target,'platform-console')
  assert.equal(architecture.screenMap.platform.decision,'REMOVE')
  assert.equal(architecture.screenMap.platform.canonicalPath,'/seller')
  assert.equal(architecture.screenMap['client-commerce'].label,'Client Billing & Payments')
  assert.equal(architecture.screenMap.finance.label,'Expenses & Procurement')
  assert.equal(architecture.nonSidebarPages.Scheduling.decision,'REMOVE')
})
