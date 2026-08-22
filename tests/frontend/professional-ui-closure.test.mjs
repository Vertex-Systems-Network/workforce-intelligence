import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root = process.cwd()
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8')

const marketing = read('resources/js/pages/MarketingWebsite.tsx')
const professionalCss = read('resources/css/professional-ui.css')
const responsiveCss = read('resources/css/professional-ui-responsive.css')
const manifest = JSON.parse(read('resources/js/navigation.manifest.json'))
const packageJson = JSON.parse(read('package.json'))
const shellNavigation = read('resources/js/shellNavigation.ts')
const blade = read('resources/views/app.blade.php')
const architecture = read('docs/architecture/SYSTEM_ARCHITECTURE_AND_FLOW.md')
const hygieneAudit = read('tools/dead-source-audit.mjs')

/** Marketing information architecture must represent every owner-level product area. */
test('marketing website represents every owner navigation area', () => {
  const sectionForGroup = {
    home: 'command-center',
    'work-management': 'work-management',
    collaboration: 'collaboration',
    'time-attendance': 'time-attendance',
    'people-hr': 'people-hr',
    'workforce-operations': 'workforce-operations',
    'clients-commerce': 'clients-commerce',
    'content-studio': 'content-studio',
    'finance-payroll': 'finance-payroll',
    intelligence: 'intelligence-reports',
    administration: 'administration',
    'account-support': 'account-installation',
  }
  for (const group of manifest.owner) {
    assert.ok(sectionForGroup[group.id], `missing marketing mapping for owner group ${group.id}`)
    assert.match(marketing, new RegExp(`id:'${sectionForGroup[group.id]}'`), `marketing section missing for ${group.id}`)
  }
})

/** Current owner destinations must remain discoverable in public product copy. */
test('marketing copy covers current owner feature destinations', () => {
  const expectedLabels = [
    'Home', 'Live Team', 'Approvals', 'Projects', 'Tasks', 'Automation Studio', 'Team Chat',
    'Scheduling', 'Attendance', 'Leave', 'Timesheets', 'People', 'HRIS', 'Organization',
    'Performance', 'Activity', 'Apps & Sites', 'Screenshots', 'Field Workforce', 'Devices',
    'Clients', 'Client payments', 'Website Studio', 'Documents', 'Media Library',
    'Finance & expenses', 'Payroll', 'Payroll compliance', 'Billing', 'Workforce Intelligence',
    'Reports', 'Modules', 'Enterprise', 'Access Control', 'Settings', 'Trash & lifecycle',
    'Downloads', 'My Access',
  ]
  for (const label of expectedLabels) assert.ok(marketing.includes(label), `missing marketing feature copy: ${label}`)
})

test('marketing navigation uses real semantic destinations', () => {
  for (const anchor of ['href="#platform"', 'href="#workforce-operations"', 'href="#security"', 'href="#architecture"']) assert.ok(marketing.includes(anchor), `missing ${anchor}`)
  assert.ok(marketing.includes('aria-label="Marketing navigation"'))
  assert.ok(marketing.includes('id="marketing-main"'))
  assert.ok(!marketing.includes('>Security</Pressable>'), 'Security must not be a fake app redirect control')
})

test('professional UI establishes readable primary typography and control sizing', () => {
  assert.match(professionalCss, /body\s*\{[\s\S]*?font-size:\s*14px;/)
  assert.match(professionalCss, /\.ui-page-title\s*\{[^}]*font-size:\s*22px;/)
  assert.match(professionalCss, /\.ui-nav-item\s*\{[^}]*font-size:\s*14px;/)
  assert.match(professionalCss, /\.ui-sidebar__module-label\s*\{[^}]*font-size:\s*12\.5px;/)
  assert.ok(professionalCss.includes('--wi-control-h: 38px'))
  assert.ok(professionalCss.includes('@media (pointer: coarse)'))
  assert.ok(professionalCss.includes('@media (prefers-reduced-motion: reduce)'))
  assert.ok(professionalCss.includes('@media (forced-colors: active)'))
})

test('secondary and operational UI text stays readable across marketing, auth, chat and commerce', () => {
  for (const marker of [
    '.marketing-product-kpi span { font-size: 12px; }',
    '.marketing-visual-card small { font-size: 12px; }',
    '.marketing-security-card p { font-size: 13px; }',
    '.auth-check { font-size: 13px; }',
    '.chat-message-text { font-size: 14px; line-height: 1.6; }',
    '.chat-draft-status { font-size: 12px; }',
    '.chat-sync-state { font-size: 11px; }',
    '.chat-history-list article > div { font-size: 12px; }',
    '.seller-capability-row small { font: 500 12px/1.45 var(--font-mono); }',
    '.client-portal-auth .ui-page-description { font-size: 14px; line-height: 1.6; }',
    '.client-portal-secure { font-size: 12px; }',
    '.client-payment-method small,',
    'grid-template-columns: repeat(2, minmax(0, 1fr))',
    'min-height: 44px',
  ]) assert.ok(responsiveCss.includes(marker), `missing responsive readability contract: ${marker}`)
  assert.equal(responsiveCss.includes('overflow-x: auto'), false)
  assert.ok(responsiveCss.includes('.chat-poll-trigger,\n  .chat-sync-state { font-size: 0; }'), 'mobile icon-only status treatment must remain explicit')
})

test('repository exposes unified local quality and real opt-in WAVE commands', () => {
  assert.equal(packageJson.scripts.quality, 'npm run verify:source && npm run accessibility:audit && npm run performance:audit')
  assert.equal(packageJson.scripts['quality:full'], 'npm run quality && npm run build')
  assert.equal(packageJson.scripts['accessibility:wave'], 'node tools/wave-accessibility-audit.mjs')
  const wave = read('tools/wave-accessibility-audit.mjs')
  assert.ok(wave.includes('https://wave.webaim.org/api/request'))
  assert.ok(wave.includes('Hosted WAVE requires a publicly reachable URL'))
  assert.ok(wave.includes('WAVE_MAX_CONTRAST_ERRORS'))
})

test('source hygiene rejects temporary root placeholders and dead interaction patterns', () => {
  assert.ok(hygieneAudit.includes("'__noop__'"))
  assert.ok(hygieneAudit.includes("fs.readdirSync(root, { withFileTypes: true })"))
  assert.ok(hygieneAudit.includes('Empty public runtime assets committed'))
  assert.ok(hygieneAudit.includes('dummy-link'))
  assert.ok(hygieneAudit.includes('browser-native-dialog'))
  assert.ok(hygieneAudit.includes('empty-handler'))
  assert.ok(hygieneAudit.includes('unfinished-comment'))
})

test('browser history traversal is synchronized with hash-addressable shell state', () => {
  assert.ok(shellNavigation.includes("addEventListener('popstate'"))
  assert.ok(shellNavigation.includes("dispatchEvent(new Event('hashchange'))"))
  assert.ok(shellNavigation.includes('window.history.pushState'))
  assert.ok(shellNavigation.includes('window.history.replaceState'))
})

test('favicon is real, referenced and no empty ico placeholder remains', () => {
  const favicon = path.join(root, 'public/favicon.svg')
  assert.ok(fs.existsSync(favicon))
  assert.ok(fs.statSync(favicon).size > 100)
  assert.equal(fs.existsSync(path.join(root, 'public/favicon.ico')), false)
  assert.ok(blade.includes("asset('favicon.svg')"))
  assert.ok(read('public/manifest.webmanifest').includes('/favicon.svg'))
})

test('architecture ledger contains runtime, navigation, authorization and tracking flow charts', () => {
  assert.ok(architecture.includes('## 1. Runtime architecture'))
  assert.ok(architecture.includes('## 2. Private shell navigation'))
  assert.ok(architecture.includes('## 3. Workspace authorization pipeline'))
  assert.ok(architecture.includes('## 5. Workforce tracking ingestion'))
  assert.ok((architecture.match(/```mermaid/g) ?? []).length >= 5)
})
