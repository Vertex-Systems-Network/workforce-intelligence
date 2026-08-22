import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'

const read=file=>fs.readFileSync(file,'utf8')

test('TypeScript 7 CLI runs beside the TypeScript 6 compiler API compatibility package',()=>{
  const pkg=JSON.parse(read('package.json'))
  assert.equal(pkg.devDependencies['@typescript/native'],'npm:typescript@^7.0.2')
  assert.equal(pkg.devDependencies.typescript,'npm:@typescript/typescript6@^6.0.2')
  assert.ok(pkg.scripts.typecheck.includes('tsc --noEmit'))
})

test('JSX binding audit requires a compiler-API capable module and degrades safely',()=>{
  const audit=read('tools/audit-jsx-component-bindings.mjs')
  for(const token of [
    "typeof ts.createSourceFile==='function'",
    "typeof ts.forEachChild==='function'",
    'ts.ScriptTarget?.Latest!==undefined',
    'ts.ScriptKind?.TSX!==undefined',
    "compilerApi(await import('typescript'))",
    'const failures=ts?await auditWithTypeScript(ts,files):auditFallback(files)',
  ])assert.ok(audit.includes(token),token)
})
