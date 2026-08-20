import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root=path.resolve(import.meta.dirname,'../..')
const locales=['en','tr','ru','ur','ar']
const domains=['core','workforce','business','studios','collaboration','help']
/** Read one project-relative source file. */
function read(relative){return fs.readFileSync(path.join(root,relative),'utf8')}
/** Extract one generated page-copy registry body. */
function registryBody(source,marker){const start=source.indexOf(marker);if(start<0)return '';const bodyStart=start+marker.length;const end=source.indexOf('\n}',bodyStart);return end<0?'':source.slice(bodyStart,end)}
/** Extract generated dictionary keys from one locale block in a domain module. */
function keys(domain,locale){
  const title=domain[0].toUpperCase()+domain.slice(1)
  const source=read(`resources/js/i18n/locales/${domain}.ts`)
  const block=source.match(new RegExp(`export const ${locale}${title}=\\{([\\s\\S]*?)\\n\\} as const`))?.[1]??''
  return [...block.matchAll(/^\s*'([^']+)'\s*:/gm)].map(match=>match[1])
}

test('DEV-06 keeps i18n entry points as small aggregation barrels',()=>{
  const catalog=read('resources/js/i18n/catalog.ts')
  const pageCopy=read('resources/js/i18n/pageCopy.ts')
  assert.ok(catalog.length<15000,`catalog barrel ${catalog.length} bytes`)
  assert.ok(pageCopy.length<15000,`pageCopy barrel ${pageCopy.length} bytes`)
  for(const domain of domains){
    assert.ok(pageCopy.includes(`./page-copy/${domain}`),domain)
    const source=read(`resources/js/i18n/locales/${domain}.ts`)
    const title=domain[0].toUpperCase()+domain.slice(1)
    for(const locale of locales)assert.ok(source.includes(`export const ${locale}${title}={`),`${domain}/${locale}`)
  }
  assert.ok(pageCopy.includes('legacyBasePhraseKeys'),'legacy pageCopyPhrases export shape')
  assert.ok(pageCopy.includes('directOnlyPhraseKeys.has(source)'),'legacy direct-only phrase precedence')
})

test('DEV-06 locale domain modules preserve exact five-locale key parity without duplicates',()=>{
  const byLocale={}
  for(const locale of locales){
    const all=domains.flatMap(domain=>keys(domain,locale))
    assert.equal(all.length,new Set(all).size,`${locale} duplicate keys`)
    byLocale[locale]=[...new Set(all)].sort()
  }
  assert.equal(byLocale.en.length,447)
  for(const locale of locales.slice(1))assert.deepEqual(byLocale[locale],byLocale.en,`${locale} parity`)
})

test('DEV-06 page-copy domains have no same-layer duplicate keys and retain explicit phrase precedence',()=>{
  const termKeys=[]
  const phraseKeys=[]
  for(const domain of domains){
    const source=read(`resources/js/i18n/page-copy/${domain}.ts`)
    const phraseSource=read(`resources/js/i18n/page-copy/${domain==='core'?'core-phrases':domain}.ts`)
    const term=registryBody(source,`export const ${domain}Terms:PageCopyRegistry={`)
    const phrase=registryBody(phraseSource,`export const ${domain}Phrases:PageCopyRegistry={`)
    termKeys.push(...[...term.matchAll(/^\s*'([^']+)'\s*:/gm)].map(match=>match[1]))
    phraseKeys.push(...[...phrase.matchAll(/^\s*'([^']+)'\s*:/gm)].map(match=>match[1]))
  }
  assert.equal(termKeys.length,new Set(termKeys).size,'duplicate terms across domains')
  assert.equal(phraseKeys.length,new Set(phraseKeys).size,'duplicate phrases across domains')
  const terms=new Set(termKeys)
  const cross=[...new Set(phraseKeys)].filter(key=>terms.has(key)).sort()
  assert.deepEqual(cross,['Approve','Block','Comments','Document','Expires','Generated','Page','Preview','Reject','Role','Share','Templates','Variables'].sort())
})
