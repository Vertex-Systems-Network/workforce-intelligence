import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(import.meta.dirname, '..')
const forbiddenBranding = /\b(Pella|VSN Builder|Shopify|Tropikal|Stom|Antalya Development|Pella Nova|Pella Force)\b/i
const temporaryName = /^(copy|old|temp|tmp|untitled|pasted)([._-]|$)/i
const legacyStageName = /^(P\d+_|PHASE_|MILESTONE_|README_PHASE|README_MILESTONE|WorkIntel_Milestone)/i
const allowedRoot = new Set([
  '.editorconfig','.env.example','.gitattributes','.github','.gitignore','README.md','app','artisan','bootstrap','browser-extension',
  'composer.json','composer.lock','config','database','deploy','desktop-agent','docs','lang','package.json','package-lock.json',
  'phpunit.xml','public','resources','routes','run-workintel-doctor.cmd','setup-development.cmd','setup-realtime-chat.cmd','start-laragon-development.cmd','storage','tests','tools',
  'tsconfig.json','verify-block-n-final-sync.cmd','verify-clean-install.cmd','verify-release.cmd','vite.config.ts','workintel-doctor.php'
])
const sourceExtensions = new Set(['.php','.ts','.tsx','.js','.jsx','.mjs','.css','.md','.json','.yml','.yaml','.cmd','.sh'])
const ignoredDirectories = new Set(['vendor','node_modules','build','releases'])

/** Recursively collects source files while excluding dependencies and generated release artifacts. */
function collect(directory) {
  const files = []
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    if (ignoredDirectories.has(entry.name)) continue
    const full = path.join(directory, entry.name)
    if (entry.isDirectory()) files.push(...collect(full))
    else files.push(full)
  }
  return files
}

const errors = []
for (const entry of fs.readdirSync(root)) {
  if (!allowedRoot.has(entry) && !['vendor','node_modules'].includes(entry)) errors.push(`Unexpected top-level entry: ${entry}`)
}
const files = collect(root)
for (const file of files) {
  const relative = path.relative(root, file).replaceAll('\\','/')
  const name = path.basename(file)
  if (name.includes(' ') || /[()[\]{}]/.test(name) || temporaryName.test(name) || /\.(bak|tmp|old|orig|rej)$/i.test(name)) {
    errors.push(`Improper filename: ${relative}`)
  }
  if (!relative.startsWith('database/migrations/') && legacyStageName.test(name)) {
    errors.push(`Historical/stage filename must be descriptive in the clean release: ${relative}`)
  }
  const ext = path.extname(file).toLowerCase()
  if (!sourceExtensions.has(ext) && !['.gitignore','.gitattributes','.editorconfig','.gitkeep','.htaccess'].includes(name)) continue
  let text = ''
  try { text = fs.readFileSync(file, 'utf8') } catch { continue }
  if (!['tools/audit-source-integrity.mjs','tests/frontend/source-integrity.test.mjs'].includes(relative) && forbiddenBranding.test(text)) errors.push(`Cross-project reference: ${relative}`)
}
if (!fs.existsSync(path.join(root,'.env.example'))) errors.push('Missing .env.example')
if (!fs.existsSync(path.join(root,'database/database.sqlite'))) errors.push('Missing database/database.sqlite placeholder')
const pkg = JSON.parse(fs.readFileSync(path.join(root,'package.json'),'utf8'))
for (const script of ['test','typecheck','build','test:e2e:public','test:e2e:full']) if (!pkg.scripts?.[script]) errors.push(`Missing npm script: ${script}`)
if (pkg.name !== 'workintel-workforce-intelligence') errors.push(`Unexpected npm package name: ${pkg.name}`)
const composer = JSON.parse(fs.readFileSync(path.join(root,'composer.json'),'utf8'))
if (composer.name !== 'workintel/workforce-intelligence') errors.push(`Unexpected Composer package name: ${composer.name}`)
console.log(`Integrity files scanned: ${files.length}`)
if (errors.length) {
  console.error(`Source integrity failures: ${errors.length}\n${errors.join('\n')}`)
  process.exit(1)
}
console.log('Source integrity failures: 0')
