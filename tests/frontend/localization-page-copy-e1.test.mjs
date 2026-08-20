import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'
import { readSource } from './source-bundles.mjs'

const root=path.resolve(import.meta.dirname,'../..')
/** Read one WorkIntel source file for dependency-free E.1 localization contracts. */
function read(relative){return fs.readFileSync(path.join(root,relative),'utf8')}

test('legacy page-copy bridge is mounted and preserves source text across locale switches',()=>{
  const app=read('resources/js/app.tsx')
  const bridge=read('resources/js/i18n/LegacyLocalizationBridge.tsx')
  assert.ok(app.includes('LegacyLocalizationBridge'))
  assert.ok(bridge.includes('new WeakMap<Text,TextState>()'))
  assert.ok(bridge.includes('new WeakMap<Element,Map<string,AttrState>>()'))
  assert.ok(bridge.includes("[data-business-value=\"true\"]"))
  assert.ok(bridge.includes('[data-no-auto-i18n="true"]'))
  assert.ok(bridge.includes('[contenteditable="true"]'))
  for(const tag of ['pre','code','script','style'])assert.ok(bridge.includes(tag),tag)
  assert.equal(bridge.includes('.value='),false,'translation bridge must never write form values')
})

test('canonical catalog falls back to deterministic page-copy translator',()=>{
  const catalog=read('resources/js/i18n/catalog.ts')
  assert.ok(catalog.includes("import { translatePageCopy } from './pageCopy'"))
  assert.ok(catalog.includes('translatePageCopy(locale,value)'))
})

test('page-copy translator is decomposed into six domain registries',()=>{
  const barrel=read('resources/js/i18n/pageCopy.ts')
  const domains=['core','workforce','business','studios','collaboration','help']
  for(const domain of domains){
    assert.ok(barrel.includes(`./page-copy/${domain}`),domain)
    const phraseSource=read(`resources/js/i18n/page-copy/${domain==='core'?'core-phrases':domain}.ts`)
    assert.ok(phraseSource.includes(`${domain}Phrases`),`${domain} phrases`)
  }
  assert.ok(barrel.length<15000,`pageCopy barrel regressed to ${barrel.length} bytes`)
})

test('deep modules retain deterministic four-language page copy after domain split',()=>{
  const copy=readSource('resources/js/i18n/pageCopy.ts')
  const critical=[
    'Activity ≠ Productivity.',
    'Top Applications & Websites',
    'Full URL storage is intentionally disabled.',
    'Create a single- or multiple-choice poll for this conversation.',
    'Attribute access policy',
    'One reporting layer across time, attendance, payroll, activity, projects and people.',
    'No approval workflow history for this week yet.',
    'Trash is empty',
    'Reset your password',
    'Storage health',
    'Approved Work Locations',
    'Govern external access, retention, Legal hold, eDiscovery exports and DLP for workplace chat.',
    'Saved only for your account in this workspace.',
  ]
  for(const phrase of critical)assert.ok(copy.includes(`'${phrase.replaceAll("'","\\'")}'`)||copy.includes(phrase),phrase)
})

test('translator leaves unsupported locales and unknown business data untouched',()=>{
  const barrel=read('resources/js/i18n/pageCopy.ts')
  const copy=readSource('resources/js/i18n/pageCopy.ts')
  assert.ok(barrel.includes("if(locale==='en'||!value.trim())return value"))
  assert.ok(barrel.includes("if(!['tr','ru','ur','ar'].includes(locale))return value"))
  assert.ok(barrel.includes('return translated?`${leading}${translated}${trailing}`:value'))
  assert.equal(copy.includes("'Acme Corp':copy("),false,'sample company data must not be registered as product copy')
  assert.equal(copy.includes("'company.com, subsidiary.com':copy("),false,'sample domain data must stay literal')
})

test('technical examples remain literal rather than being localized as product prose',()=>{
  const copy=readSource('resources/js/i18n/pageCopy.ts')
  for(const literal of ['/help','payload.status','from:12','before:2026-08-01','you@company.com']){
    assert.equal(copy.includes(`'${literal}':copy(`),false,literal)
  }
})
