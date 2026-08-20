import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root = path.resolve(import.meta.dirname, '../..')

/** Recursively collects first-party source files for lightweight frontend integrity tests. */
function collectFiles(directory, predicate) {
  const output = []
  if (!fs.existsSync(directory)) return output
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const full = path.join(directory, entry.name)
    if (entry.isDirectory()) {
      if (['node_modules', 'vendor', 'releases', 'build'].includes(entry.name)) continue
      output.push(...collectFiles(full, predicate))
    } else if (predicate(full)) output.push(full)
  }
  return output
}

/** Reads one project file using UTF-8 encoding. */
function read(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8')
}

test('package metadata identifies only WorkIntel', () => {
  const pkg = JSON.parse(read('package.json'))
  assert.equal(pkg.name, 'workintel-workforce-intelligence')
  assert.equal(pkg.private, true)
})

test('frontend source contains no known cross-project branding', () => {
  const files = collectFiles(path.join(root, 'resources'), file => /\.(ts|tsx|js|jsx|css)$/.test(file))
  const forbidden = /\b(Pella|VSN Builder|Shopify|Tropikal|Stom|Antalya Development|Pella Nova|Pella Force)\b/i
  const hits = files.filter(file => forbidden.test(fs.readFileSync(file, 'utf8')))
  assert.deepEqual(hits, [])
})

test('drag and drop dependencies use the approved libraries', () => {
  const pkg = JSON.parse(read('package.json'))
  assert.ok(pkg.dependencies.gridstack)
  assert.ok(pkg.dependencies['@dnd-kit/core'])
  const source = collectFiles(path.join(root, 'resources/js'), file => /\.(ts|tsx)$/.test(file)).map(file => fs.readFileSync(file, 'utf8')).join('\n')
  assert.equal(/\bdraggable\s*=/.test(source), false)
  // Native DataTransfer is allowed only for file-drop zones; sortable/domain drag-drop must use GridStack or dnd-kit.
  const nonFileTransfer = source.replace(/\.dataTransfer\.files\b/g, '')
  assert.equal(/\.dataTransfer\b/.test(nonFileTransfer), false)
})

test('critical application entry points exist', () => {
  for (const relative of ['resources/js/WorkforceApp.tsx', 'resources/js/pages/Tasks.tsx', 'resources/js/pages/Chat.tsx', 'resources/js/pages/SellerConsole.tsx']) {
    assert.equal(fs.existsSync(path.join(root, relative)), true, `${relative} is missing`)
  }
})
