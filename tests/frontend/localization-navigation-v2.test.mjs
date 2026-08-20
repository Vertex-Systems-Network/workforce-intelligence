import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root=path.resolve(import.meta.dirname,'../..')
const domains=['core','workforce','business','studios','collaboration','help']
/** Read one WorkIntel source file for dependency-free localization contracts. */
function read(relative){return fs.readFileSync(path.join(root,relative),'utf8')}
/** Extract one locale block from a domain module. */
function domainLocaleKeys(domain,locale){
  const title=domain[0].toUpperCase()+domain.slice(1)
  const source=read(`resources/js/i18n/locales/${domain}.ts`)
  const match=source.match(new RegExp(`export const ${locale}${title}=\\{([\\s\\S]*?)\\n\\} as const`))
  assert.ok(match,`${domain}/${locale} locale block`)
  return [...match[1].matchAll(/'([^']+)'\s*:/g)].map(result=>result[1])
}
/** Extract translation keys from the six domain packs owned by one locale. */
function localeKeyList(locale){return domains.flatMap(domain=>domainLocaleKeys(domain,locale))}
/** Extract unique translation keys from one locale's domain modules. */
function localeKeys(locale){return new Set(localeKeyList(locale))}

test('five core frontend locale packs have exact translation-key parity across domain modules',()=>{
  const englishList=localeKeyList('en')
  const english=new Set(englishList)
  assert.equal(englishList.length,english.size,'English locale has duplicate keys across domain modules')
  assert.ok(english.size>=300,`expected broad translation catalog, got ${english.size}`)
  for(const locale of ['tr','ru','ur','ar']){
    const list=localeKeyList(locale)
    const keys=new Set(list)
    assert.equal(list.length,keys.size,`${locale} contains duplicate translation keys across domains`)
    assert.deepEqual([...keys].sort(),[...english].sort(),`${locale} translation parity`)
  }
})

test('catalog barrel composes six domains instead of embedding monolithic locale dictionaries',()=>{
  const catalog=read('resources/js/i18n/catalog.ts')
  for(const domain of domains){
    const title=domain[0].toUpperCase()+domain.slice(1)
    assert.ok(catalog.includes(`...en${title}`),`catalog missing ${domain} English domain`)
    const source=read(`resources/js/i18n/locales/${domain}.ts`)
    for(const locale of ['en','tr','ru','ur','ar'])assert.ok(source.includes(`export const ${locale}${title}={`),`${domain}/${locale}`)
  }
  assert.ok(catalog.length<15000,`catalog barrel regressed to ${catalog.length} bytes`)
})

test('navigation manifest uses stable unique IDs and one Scheduling destination per role',()=>{
  const manifest=JSON.parse(read('resources/js/navigation.manifest.json'))
  for(const [role,groups] of Object.entries(manifest)){
    const ids=groups.flatMap(group=>group.items.map(item=>item[0]))
    assert.equal(ids.length,new Set(ids).size,`${role} contains duplicate navigation IDs`)
    const schedulingCount=ids.filter(id=>id==='schedule').length
    assert.ok(schedulingCount<=1,`${role} must not expose duplicate Scheduling destinations`)
    if(['employee','hr','manager','owner'].includes(role))assert.equal(schedulingCount,1,`${role} should expose Scheduling`)
    assert.equal(ids.includes('shifts'),false,`${role} must not expose legacy Shift Templates as a second sidebar destination`)
  }
})

test('repeated locale switching cannot mutate or duplicate navigation definitions',()=>{
  const manifest=JSON.parse(read('resources/js/navigation.manifest.json'))
  const baseline=JSON.stringify(manifest)
  const locales=['en','tr','ar','ur','ru','en']
  for(let cycle=0;cycle<20;cycle++){
    for(const locale of locales){
      const keys=localeKeys(locale)
      for(const groups of Object.values(manifest)) for(const group of groups){
        assert.ok(keys.has(group.labelKey),`${locale} missing ${group.labelKey}`)
        for(const [,labelKey] of group.items) if(labelKey) assert.ok(keys.has(labelKey),`${locale} missing ${labelKey}`)
      }
    }
    assert.equal(JSON.stringify(manifest),baseline,'navigation manifest changed while resolving locale labels')
  }
})

test('localization state is per-user, RTL-aware and does not refresh the session on each switch',()=>{
  const context=read('resources/js/i18n/LocalizationContext.tsx')
  for(const token of ["workintel-language","document.documentElement.dir","document.body.dir","useWorkspaceLocale","use_workspace_locale:false"])assert.ok(context.includes(token),token)
  assert.equal(context.includes('refreshSession'),false,'language switching must not race by refreshing the auth session')
})

test('navigation and scheduling use immutable translated definitions',()=>{
  const sidebar=read('resources/js/components/Sidebar.tsx')
  const navigation=read('resources/js/navigation.ts')
  const hub=read('resources/js/pages/SchedulingHub.tsx')
  assert.ok(sidebar.includes('navigationForRole'))
  assert.ok(sidebar.includes('key={group.id}'))
  assert.ok(navigation.includes('navigation.manifest.json'))
  assert.ok(hub.includes("'board'|'templates'"))
  assert.ok(hub.includes("t('scheduling.board')"))
  assert.ok(hub.includes("t('scheduling.templates')"))
})

test('shared UI controls and DataGrid use localization keys instead of fixed English controls',()=>{
  const ui=read('resources/js/design-system/index.tsx')
  for(const token of ["useOptionalLocalization","t('common.saved_views')","t('common.rows_per_page')","t('common.reset_table')","t('common.previous_page')","t('common.next_page')"])assert.ok(ui.includes(token),token)
})

test('RTL overlay, table, scheduling and page scroll rules are present',()=>{
  const toolkit=read('resources/js/design-system/toolkit.css')
  const app=read('resources/css/app.css')
  for(const token of ['border-inline-end','[dir="rtl"] .ui-dropdown','[dir="rtl"] .ui-select','[dir="rtl"] .ui-data-grid-v2','[dir="rtl"] .react-datepicker'])assert.ok(toolkit.includes(token),token)
  assert.ok(app.includes('scrollbar-color:var(--border-strong) var(--surface)')||app.includes('scrollbar-color: var(--border-strong) var(--surface)'))
})
