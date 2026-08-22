import fs from 'node:fs'
import path from 'node:path'

const root = process.cwd()
const sourceRoot = path.join(root, 'resources/js')
const extensions = ['.ts', '.tsx', '.js', '.jsx', '.css', '.json']
const ignored = new Set(['vite-env.d.ts'])
// DEV-08 closes all known browser-source exceptions. New standalone roots must be
// consciously reintroduced here and justified in the architecture ledger.
const standaloneRoots = new Set()
const retiredPaths = [
  'resources/js/pages/EmployeeProfile.tsx',
  'resources/js/data.ts',
  'resources/js/i18n/humanLabels.tsx',
  'resources/js/design-system/ToolkitPreview.tsx',
  'resources/js/media/AvatarCropper.tsx',
]

/** Return every source file that participates in the browser module graph. */
function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap(entry => {
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) return walk(full)
    if (!extensions.includes(path.extname(entry.name))) return []
    return [full]
  })
}

/** Normalize an absolute source path to a portable resources/js relative path. */
function relative(file) {
  return path.relative(sourceRoot, file).replaceAll(path.sep, '/')
}

/** Resolve one repository-relative ESM import without depending on node_modules. */
function resolveImport(sourceFile, specifier) {
  if (!specifier.startsWith('.')) return null
  const base = path.resolve(path.dirname(sourceFile), specifier)
  const candidates = path.extname(base)
    ? [base]
    : [
        ...extensions.map(extension => `${base}${extension}`),
        ...extensions.map(extension => path.join(base, `index${extension}`)),
      ]
  for (const candidate of candidates) {
    if (fs.existsSync(candidate) && fs.statSync(candidate).isFile() && candidate.startsWith(sourceRoot + path.sep)) return candidate
  }
  return null
}

const files = walk(sourceRoot)
const byRelative = new Map(files.map(file => [relative(file), file]))
const graph = new Map(files.map(file => [relative(file), new Set()]))
const importPatterns = [
  /(?:import|export)\s+(?:[^'\"]*?\s+from\s+)?['\"]([^'\"]+)['\"]/g,
  /import\(\s*['\"]([^'\"]+)['\"]\s*\)/g,
]

for (const file of files) {
  const source = fs.readFileSync(file, 'utf8')
  const node = graph.get(relative(file))
  for (const pattern of importPatterns) {
    pattern.lastIndex = 0
    for (const match of source.matchAll(pattern)) {
      const resolved = resolveImport(file, match[1])
      if (resolved) node.add(relative(resolved))
    }
  }
}

/** Traverse the static browser import graph from one or more explicit entrypoints. */
function reachableFrom(roots) {
  const reached = new Set()
  const stack = [...roots]
  while (stack.length) {
    const current = stack.pop()
    if (!graph.has(current) || reached.has(current)) continue
    reached.add(current)
    for (const dependency of graph.get(current)) stack.push(dependency)
  }
  return reached
}

const runtimeReachable = reachableFrom(['app.tsx'])
const intentionalReachable = reachableFrom(standaloneRoots)
const allowed = new Set([...runtimeReachable, ...intentionalReachable, ...ignored])
const unreachable = [...byRelative.keys()].filter(file => !allowed.has(file)).sort()
const failures = []

if (unreachable.length) failures.push(`Unreachable browser source files: ${unreachable.join(', ')}`)
for (const retired of retiredPaths) if (fs.existsSync(path.join(root, retired))) failures.push(`Retired source returned: ${retired}`)

const debugResidue = []
for (const file of files.filter(file => /\.[jt]sx?$/.test(file))) {
  const source = fs.readFileSync(file, 'utf8')
  const lines = source.split(/\r?\n/)
  lines.forEach((line, index) => {
    if (/\bconsole\.(?:log|debug)\s*\(/.test(line) || /\bdebugger\s*;?/.test(line)) debugResidue.push(`${relative(file)}:${index + 1}`)
  })
}
if (debugResidue.length) failures.push(`Production debug residue: ${debugResidue.join(', ')}`)

/** Scan versioned runtime/source areas for editor backups, rejected patches and empty public placeholders. */
function repositoryHygiene() {
  const scanRoots = ['public', 'resources', 'app', 'routes', 'config', 'database', 'tools', 'tests', 'docs']
  const junkNames = /(?:~|\.bak|\.backup|\.old|\.orig|\.rej|\.tmp|\.temp|\.swp|\.swo)$/i
  const exactJunk = new Set(['.DS_Store', 'Thumbs.db', 'desktop.ini'])
  const junk = []
  const emptyPublic = []
  const visit = directory => {
    if (!fs.existsSync(directory)) return
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
      const full = path.join(directory, entry.name)
      if (entry.isDirectory()) {
        visit(full)
        continue
      }
      const repoPath = path.relative(root, full).replaceAll(path.sep, '/')
      if (junkNames.test(entry.name) || exactJunk.has(entry.name)) junk.push(repoPath)
      if (repoPath.startsWith('public/') && fs.statSync(full).size === 0) emptyPublic.push(repoPath)
    }
  }
  for (const directory of scanRoots) visit(path.join(root, directory))
  if (junk.length) failures.push(`Junk/editor artifacts committed: ${junk.sort().join(', ')}`)
  if (emptyPublic.length) failures.push(`Empty public runtime assets committed: ${emptyPublic.sort().join(', ')}`)
}
repositoryHygiene()

const peopleSource = fs.readFileSync(path.join(sourceRoot, 'pages/People.tsx'), 'utf8')
for (const marker of ['setMemberAvatar', 'openSecurity', 'profile photo']) {
  if (!peopleSource.includes(marker)) failures.push(`Canonical People ownership marker missing: ${marker}`)
}

console.log(`DEV-08 dead-source audit: ${runtimeReachable.size} runtime source files reachable from app.tsx`)
console.log(`Intentional standalone roots: ${standaloneRoots.size ? [...standaloneRoots].join(', ') : 'none'}`)
console.log(`Unreachable source debt: ${unreachable.length}; production console.log/debugger debt: ${debugResidue.length}`)
if (failures.length) {
  console.error(`DEV-08 dead-source audit: FAIL (${failures.length})`)
  for (const failure of failures) console.error(` - ${failure}`)
  process.exit(1)
}
console.log('DEV-08 dead-source audit: PASS')
