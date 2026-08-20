import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'

/** Read one repository source file for DEV-08 contract assertions. */
const read = file => fs.readFileSync(file, 'utf8')
const retiredPaths = [
  'resources/js/pages/EmployeeProfile.tsx',
  'resources/js/data.ts',
  'resources/js/i18n/humanLabels.tsx',
  'resources/js/design-system/ToolkitPreview.tsx',
  'resources/js/media/AvatarCropper.tsx',
]

test('DEV-08 keeps retired orphan/demo browser source out and records the People owner', () => {
  for (const file of retiredPaths) assert.equal(fs.existsSync(file), false, `${file} must stay retired`)
  const people = read('resources/js/pages/People.tsx')
  for (const marker of ['setMemberAvatar', 'openSecurity', 'profile photo']) assert.ok(people.includes(marker), marker)
})

test('DEV-08 dead-source audit is a release gate with zero standalone source exceptions', () => {
  const pkg = JSON.parse(read('package.json'))
  assert.match(pkg.scripts.test, /dead-source-audit\.mjs/)
  assert.match(pkg.scripts.typecheck, /dead-source-audit\.mjs/)
  assert.match(pkg.scripts.build, /dead-source-audit\.mjs/)
  const audit = read('tools/dead-source-audit.mjs')
  assert.match(audit, /const standaloneRoots = new Set\(\)/)
  for (const path of retiredPaths) assert.ok(audit.includes(path), `${path} must remain guarded`)
  for (const marker of ['app.tsx', 'console\\.(?:log|debug)', 'setMemberAvatar']) assert.ok(audit.includes(marker), marker)
})
