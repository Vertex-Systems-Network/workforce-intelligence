import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { readSource } from './source-bundles.mjs'

/** Read one repository source file for the M2 contract tests. */
const read=file=>readSource(file)

test('M2 promotes one authoritative WorkIntel design-system source',()=>{
  assert.equal(fs.existsSync('resources/js/ui'),false)
  for(const file of ['resources/js/design-system/index.tsx','resources/js/design-system/tokens.css','resources/js/design-system/toolkit.css','resources/js/design-system/README.md']) assert.equal(fs.existsSync(file),true,file)
  const people=read('resources/js/pages/People.tsx')
  const website=read('resources/js/pages/WebsiteStudio.tsx')
  assert.match(people,/from '\.\.\/design-system'/)
  assert.match(website,/from '\.\.\/design-system'/)
})

test('M2 design system owns interactive and media primitives used by feature code',()=>{
  const ui=read('resources/js/design-system/index.tsx')
  for(const component of ['Button','Pressable','Checkbox','Radio','ChoiceInput','HiddenFileInput','Input','Select','Option','Textarea','Modal','Drawer','Dropdown','Tooltip','DataGrid','TableWrap','ViewModeToggle','Image','ProgressBar','Box','Stack','Inline','Grid','Text','Form','Label','Link']) assert.match(ui,new RegExp(`export (?:function|const) ${component}\\b`),component)
})

test('M2 feature code contains no direct interactive or image tags outside the public website renderer',()=>{
  const failures=[]
  /** Recursively inspect feature TSX files for raw interactive/media elements. */
  const walk=dir=>{for(const entry of fs.readdirSync(dir,{withFileTypes:true})){const full=path.join(dir,entry.name);if(entry.isDirectory())walk(full);else if(entry.name.endsWith('.tsx')&&!full.includes(`${path.sep}design-system${path.sep}`)&&!full.endsWith(path.join('website','WebsiteRenderer.tsx'))){const text=read(full);const hits=[...text.matchAll(/<(button|input|select|option|textarea|table|img|form|label|a|progress)\b/g)];if(hits.length)failures.push(`${full}: ${hits.map(hit=>hit[1]).join(',')}`)}}}
  walk('resources/js')
  assert.deepEqual(failures,[])
})

test('M2 release gates enforce design-system audit and Lucide-only icon policy',()=>{
  const audit=read('tools/design-system-audit.mjs')
  const release=read('verify-release.cmd')
  const clean=read('verify-clean-install.cmd')
  const pkg=JSON.parse(read('package.json'))
  assert.match(audit,/lucide-react/)
  assert.match(audit,/raw <\$\{match\[1\]\}>/)
  assert.match(release,/design-system-audit\.mjs/)
  assert.match(clean,/design-system-audit\.mjs/)
  assert.match(pkg.scripts.test,/design-system-audit\.mjs/)
  assert.match(pkg.scripts.build,/design-system-audit\.mjs/)
  assert.match(pkg.scripts.typecheck,/design-system-audit\.mjs/)
})

test('M2 Batch 2 cuts static inline layout debt and ratchets the reduced baseline',()=>{
  const baseline=JSON.parse(read('docs/architecture/M2_INLINE_STYLE_BASELINE.json'))
  assert.equal(baseline.previousBaseline,445)
  assert.equal(baseline.total,0)
  assert.deepEqual(baseline.files,{})
  assert.match(read('tools/design-system-audit.mjs'),/inline styles increased/)
})

test('M2 visual-state and page-CSS isolation contracts are release enforced',()=>{
  const audit=read('tools/design-system-audit.mjs')
  const css=read('resources/js/design-system/toolkit.css')
  for(const marker of ['.ui-button:hover',':focus-visible','.ui-input:disabled','[aria-invalid=\"true\"]','.ui-button.is-loading','[dir=\"rtl\"]','prefers-reduced-motion:reduce','@media(max-width:760px)']) assert.match(css,new RegExp(marker.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')))
  assert.match(audit,/allowedPageCss/)
  assert.match(audit,/top-level \.ui-\*/)
})
