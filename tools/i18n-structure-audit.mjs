import fs from 'node:fs'
import path from 'node:path'

const root=path.resolve(import.meta.dirname,'..')
const locales=['en','tr','ru','ur','ar']
const domains=['core','workforce','business','studios','collaboration','help']
const failures=[]
/** Record one i18n source-structure assertion. */
function check(condition,message){if(!condition)failures.push(message)}
/** Read one project-relative source file. */
function read(relative){return fs.readFileSync(path.join(root,relative),'utf8')}
/** Extract object-literal keys from one generated locale block. */
function localeDomainKeys(domain,locale){
  const title=domain[0].toUpperCase()+domain.slice(1)
  const relative=`resources/js/i18n/locales/${domain}.ts`
  const source=read(relative)
  const match=source.match(new RegExp(`export const ${locale}${title}=\\{([\\s\\S]*?)\\n\\} as const`))
  check(Boolean(match),`Missing locale block ${locale}${title} in ${relative}`)
  return match?[...match[1].matchAll(/^\s*'([^']+)'\s*:/gm)].map(result=>result[1]):[]
}
/** Extract object-literal keys from generated one-entry-per-line page-copy registries. */
function literalKeys(source){return [...source.matchAll(/^\s*(['"])((?:\\.|(?!\1).)*)\1\s*:/gm)].map(match=>match[2].replaceAll("\\'","'"))}
/** Extract one generated page-copy registry body by its exact declaration marker. */
function registryBody(source,marker){const start=source.indexOf(marker);if(start<0)return null;const bodyStart=start+marker.length;const end=source.indexOf('\n}',bodyStart);return end<0?null:source.slice(bodyStart,end)}
/** Count top-level arguments in one copy(...) call while ignoring punctuation inside strings. */
function copyArgCount(line){
  const start=line.indexOf('copy(')
  if(start<0)return 0
  let quote=null,escape=false,depth=0,args=1
  for(let i=start+5;i<line.length;i++){
    const ch=line[i]
    if(quote){if(escape)escape=false;else if(ch==='\\')escape=true;else if(ch===quote)quote=null;continue}
    if(ch==="'"||ch==='"'||ch==='`'){quote=ch;continue}
    if(ch==='('){depth++;continue}
    if(ch===')'){if(depth===0)return args;depth--;continue}
    if(ch===','&&depth===0)args++
  }
  return -1
}

for(const domain of domains)check(fs.existsSync(path.join(root,`resources/js/i18n/locales/${domain}.ts`)),`Missing locale domain module: ${domain}`)
const localeKeySets={}
for(const locale of locales){
  const all=domains.flatMap(domain=>localeDomainKeys(domain,locale))
  const unique=new Set(all)
  check(all.length===unique.size,`${locale} has ${all.length-unique.size} duplicate translation keys across domains`)
  localeKeySets[locale]=unique
}
const english=[...(localeKeySets.en??new Set())].sort()
check(english.length>=300,`English translation catalog unexpectedly small: ${english.length}`)
for(const locale of locales.slice(1)){
  const keys=[...(localeKeySets[locale]??new Set())].sort()
  check(JSON.stringify(keys)===JSON.stringify(english),`${locale} translation keys do not match English`)
}

const catalog=read('resources/js/i18n/catalog.ts')
check(catalog.length<15000,`catalog.ts should remain an aggregator barrel; current bytes: ${catalog.length}`)
for(const domain of domains){
  const title=domain[0].toUpperCase()+domain.slice(1)
  check(catalog.includes(`...en${title}`),`catalog.ts does not compose English ${domain} domain`)
  check(catalog.includes(`./locales/${domain}`),`catalog.ts missing ${domain} domain import`)
}
check(catalog.includes('dictionaries[locale]?.[key]??en[key]??key'),'Canonical translation fallback chain changed')

const termKeys=[]
const phraseKeys=[]
let copyEntries=0
for(const domain of domains){
  const relative=`resources/js/i18n/page-copy/${domain}.ts`
  const phraseRelative=domain==='core'?'resources/js/i18n/page-copy/core-phrases.ts':relative
  check(fs.existsSync(path.join(root,relative)),`Missing page-copy domain module: ${relative}`)
  check(fs.existsSync(path.join(root,phraseRelative)),`Missing page-copy phrase module: ${phraseRelative}`)
  if(!fs.existsSync(path.join(root,relative))||!fs.existsSync(path.join(root,phraseRelative)))continue
  const source=read(relative)
  const phraseSource=read(phraseRelative)
  const termBody=registryBody(source,`export const ${domain}Terms:PageCopyRegistry={`)
  const phraseBody=registryBody(phraseSource,`export const ${domain}Phrases:PageCopyRegistry={`)
  check(termBody!==null,`${domain} terms registry missing`)
  check(phraseBody!==null,`${domain} phrases registry missing`)
  if(termBody!==null)termKeys.push(...literalKeys(termBody))
  if(phraseBody!==null)phraseKeys.push(...literalKeys(phraseBody))
  const copySources=domain==='core'?[source,phraseSource]:[source]
  for(const line of copySources.flatMap(item=>item.split('\n')).filter(line=>line.includes(':copy('))){
    copyEntries++
    check(copyArgCount(line)===4,`${relative} contains a page-copy entry without four locale values: ${line.trim().slice(0,100)}`)
  }
}
const termSet=new Set(termKeys),phraseSet=new Set(phraseKeys)
check(termKeys.length===termSet.size,`Page-copy term keys contain ${termKeys.length-termSet.size} duplicates across domains`)
check(phraseKeys.length===phraseSet.size,`Page-copy phrase keys contain ${phraseKeys.length-phraseSet.size} duplicates across domains`)
const cross=[...termSet].filter(key=>phraseSet.has(key)).sort()
const allowedCross=['Approve','Block','Comments','Document','Expires','Generated','Page','Preview','Reject','Role','Share','Templates','Variables'].sort()
check(JSON.stringify(cross)===JSON.stringify(allowedCross),`Unexpected term/phrase precedence collisions: ${cross.join(', ')}`)
check(copyEntries>=1700,`Page-copy registry unexpectedly small: ${copyEntries}`)

const pageCopy=read('resources/js/i18n/pageCopy.ts')
check(pageCopy.length<15000,`pageCopy.ts should remain an aggregator/runtime translator; current bytes: ${pageCopy.length}`)
for(const domain of domains)check(pageCopy.includes(`./page-copy/${domain}`),`pageCopy.ts missing ${domain} domain import`)
for(const token of ["if(locale==='en'||!value.trim())return value","if(!['tr','ru','ur','ar'].includes(locale))return value",'allPageCopyPhrases[source]'])check(pageCopy.includes(token),`Page-copy fallback contract missing: ${token}`)
check(pageCopy.includes('directOnlyPhraseKeys.has(source)'), 'Legacy M11 direct-only phrase precedence guard missing')
check(pageCopy.includes('legacyBasePhraseKeys'), 'Legacy public pageCopyPhrases export compatibility guard missing')

if(failures.length){for(const failure of failures)console.error(`FAIL: ${failure}`);process.exit(1)}
console.log('I18N structure audit: PASS')
console.log(`Catalog keys per core locale: ${english.length}`)
console.log(`Locale domain modules: ${domains.length}`)
console.log(`Page-copy entries: ${copyEntries}`)
console.log(`Page-copy term/phrase collisions (intentional precedence): ${cross.length}`)
