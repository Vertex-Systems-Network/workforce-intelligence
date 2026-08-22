import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'
import { pathToFileURL } from 'node:url'

/** Recursively return TSX files below one directory. */
function filesIn(dir){
  const rows=[]
  for(const entry of fs.readdirSync(dir,{withFileTypes:true})){
    const full=path.join(dir,entry.name)
    if(entry.isDirectory())rows.push(...filesIn(full))
    else if(entry.isFile()&&full.endsWith('.tsx'))rows.push(full)
  }
  return rows
}

/** Normalize an imported module and require the TypeScript 6-style compiler API used by this audit. */
function compilerApi(module){
  const candidates=[module,module?.default]
  return candidates.find(ts=>
    ts&&typeof ts.createSourceFile==='function'&&typeof ts.forEachChild==='function'&&
    ts.ScriptTarget?.Latest!==undefined&&ts.ScriptKind?.TSX!==undefined
  )??null
}

/** Load a compiler-API capable TypeScript module, preferring the project compatibility package. */
async function loadTypeScript(){
  try{
    const local=compilerApi(await import('typescript'))
    if(local)return local
  }catch{}
  try{
    const root=execSync('npm root -g',{stdio:['ignore','pipe','ignore']}).toString().trim()
    const candidate=path.join(root,'typescript','lib','typescript.js')
    if(fs.existsSync(candidate)){
      const global=compilerApi(await import(pathToFileURL(candidate).href))
      if(global)return global
    }
  }catch{}
  return null
}

/** Add every identifier represented by a TypeScript binding name. */
function addBinding(ts,node,names){
  if(!node)return
  if(ts.isIdentifier(node)){names.add(node.text);return}
  if(ts.isObjectBindingPattern(node)||ts.isArrayBindingPattern(node))for(const element of node.elements){if(ts.isBindingElement(element))addBinding(ts,element.name,names)}
}

/** Audit JSX component tags against imports and in-file declarations. */
async function auditWithTypeScript(ts,files){
  const failures=[]
  for(const file of files){
    const source=fs.readFileSync(file,'utf8')
    const sf=ts.createSourceFile(file,source,ts.ScriptTarget.Latest,true,ts.ScriptKind.TSX)
    const bound=new Set(['React'])
    const used=new Set()
    /** Collect declarations and referenced JSX component tags from one AST node. */
    const visit=node=>{
      if(ts.isImportClause(node)){
        if(node.name)bound.add(node.name.text)
        if(node.namedBindings){
          if(ts.isNamespaceImport(node.namedBindings))bound.add(node.namedBindings.name.text)
          if(ts.isNamedImports(node.namedBindings))for(const element of node.namedBindings.elements)bound.add(element.name.text)
        }
      }
      if(ts.isVariableDeclaration(node))addBinding(ts,node.name,bound)
      if((ts.isFunctionDeclaration(node)||ts.isClassDeclaration(node))&&node.name)bound.add(node.name.text)
      if(ts.isParameter(node))addBinding(ts,node.name,bound)
      if(ts.isJsxOpeningElement(node)||ts.isJsxSelfClosingElement(node)){
        let tag=node.tagName
        while(ts.isPropertyAccessExpression(tag))tag=tag.expression
        if(ts.isIdentifier(tag)&&/^[A-Z]/.test(tag.text))used.add(tag.text)
      }
      ts.forEachChild(node,visit)
    }
    visit(sf)
    for(const name of used)if(!bound.has(name))failures.push(`${file}: JSX component <${name}> is used without an import or local declaration.`)
  }
  return failures
}

/** Conservative dependency-free fallback used only when no compiler API can be loaded. */
function auditFallback(files){
  const failures=[]
  for(const file of files){
    const source=fs.readFileSync(file,'utf8')
    const bound=new Set(['React'])
    for(const match of source.matchAll(/import\s+([A-Za-z_$][\w$]*)\s*(?:,|from)/g))bound.add(match[1])
    for(const match of source.matchAll(/import\s*\{([^}]+)\}\s*from/g))for(const part of match[1].split(',')){const clean=part.trim().replace(/^type\s+/,'');const alias=clean.split(/\s+as\s+/).pop()?.trim();if(alias)bound.add(alias)}
    for(const match of source.matchAll(/(?:function|class|const|let|var)\s+([A-Z][A-Za-z0-9_$]*)/g))bound.add(match[1])
    const used=new Set([...source.matchAll(/<([A-Z][A-Za-z0-9_$]*)\b/g)].map(match=>match[1]))
    for(const name of used)if(!bound.has(name))failures.push(`${file}: JSX component <${name}> may be unbound.`)
  }
  return failures
}

const files=filesIn(path.resolve('resources/js'))
const ts=await loadTypeScript()
const failures=ts?await auditWithTypeScript(ts,files):auditFallback(files)
console.log(`JSX/TSX component binding audit: ${files.length} files; ${failures.length} failures.`)
if(failures.length){for(const failure of failures)console.error(` - ${failure}`);process.exit(1)}
