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
const agents = read('AGENTS.md')
const runnerRegistry = JSON.parse(read('benchmarks/runner/registry.json'))
const runnerGuide = read('docs/release/RUNNER_BENCHMARK_REGISTER.md')
const runnerAudit = read('tools/runner-benchmark-audit.mjs')
const ciWorkflow = read('.github/workflows/ci.yml')
const qualityWorkflow = read('.github/workflows/code-quality.yml')

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
  assert.equal(packageJson.scripts.quality, 'npm run verify:source && npm run audit:ai-supervisor-state && npm run audit:runner-benchmarks && npm run accessibility:audit && npm run performance:audit')
  assert.equal(packageJson.scripts['audit:ai-supervisor-state'], 'node tools/ai-supervisor-state-audit.mjs')
  assert.equal(packageJson.scripts['audit:runner-benchmarks'], 'node tools/runner-benchmark-audit.mjs')
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

test('AI execution contract preserves supervisor resume, milestone, timeout and authority discipline', () => {
  for (const marker of [
    'docs/ai-state/CURRENT-STATE.yaml',
    'one user `continue`, `resume`, or numeric next-action selection authorizes one **bounded logical milestone**',
    'Do not require the user to reply',
    'OPEN GitHub Issues first',
    'at most one consolidated CI/status refresh',
    'Runner registration NEVER grants execution authority',
    'security-critical validation',
    'exact-head merge-required checks',
    'CURRENT-STATE.yaml` <= 12 KiB',
    'Message delivery timed out',
    'Do not merge because an older SHA was green',
    'GitHub-hosted',
    'Repo:',
    'Current Work:',
    'Current Module:',
    'Module Progress:',
    'Overall Progress:',
  ]) assert.ok(agents.includes(marker), `AGENTS.md missing supervisor execution contract: ${marker}`)
})

test('runner benchmark uses source definitions plus non-self-invalidating exact-head result envelopes', () => {
  assert.equal(runnerRegistry.schema_version, 3)
  assert.equal(runnerRegistry.execution_policy, 'authorization-aware-definitions-with-external-exact-head-results')
  assert.equal(runnerRegistry.result_envelope_schema, 'benchmarks/runner/result-envelope.schema.json')
  assert.ok(runnerRegistry.entries.length >= 5)
  const ids = new Set()
  const templates = new Set()
  for (const entry of runnerRegistry.entries) {
    assert.match(entry.id, /^RB-\d{3,}$/)
    assert.equal(ids.has(entry.id), false)
    ids.add(entry.id)
    assert.ok(entry.dedup_key_template.includes('{candidate_head_sha}'))
    assert.equal(templates.has(entry.dedup_key_template), false)
    templates.add(entry.dedup_key_template)
    assert.match(entry.registered_source_identity.registered_head_sha, /^[0-9a-f]{40}$/i)
    assert.equal(Object.hasOwn(entry.registered_source_identity, 'candidate_head_sha'), false)
    assert.equal(Object.hasOwn(entry, 'verification'), false)
    assert.equal(entry.result_recording.mode, 'external-exact-head-envelope')
    assert.equal(entry.result_recording.candidate_source_must_not_be_mutated_for_result_recording, true)
  }
  assert.equal(packageJson.scripts['validate:runner-result'], 'node tools/validate-runner-result-envelope.mjs')
  for (const marker of [
    'task-definition registry',
    'Committing a terminal result into the same candidate source branch would change the candidate SHA',
    'result-envelope.schema.json',
    'non-source evidence surface',
    'one consolidated remote CI/status refresh',
  ]) assert.ok(runnerGuide.includes(marker), `runner guide missing: ${marker}`)
  for (const marker of [
    'schema_version must be 3',
    'committed registry must not store runtime candidate_head_sha',
    'committed task definition must not contain terminal verification evidence',
    'candidate mutation guard required',
  ]) assert.ok(runnerAudit.includes(marker), `runner audit missing: ${marker}`)
})

test('CI avoids duplicate feature-branch push runs and cancels stale PR work', () => {
  for (const workflow of [ciWorkflow, qualityWorkflow]) {
    assert.ok(workflow.includes('push:\n    branches: [main]'), 'push certification must be limited to main')
    assert.ok(workflow.includes('pull_request:\n    branches: [main]'), 'pull-request certification must target main')
    assert.ok(workflow.includes('cancel-in-progress: true'), 'stale certification work must be cancelled')
  }
  assert.ok(ciWorkflow.includes('group: workintel-ci-${{ github.event.pull_request.number || github.ref }}'))
  assert.ok(qualityWorkflow.includes('group: workintel-quality-${{ github.event.pull_request.number || github.ref }}'))
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
