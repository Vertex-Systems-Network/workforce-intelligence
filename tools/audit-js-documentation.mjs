import fs from 'fs';
import path from 'path';
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const ts = require('typescript');
/** Audits named first-party JavaScript and TypeScript declarations for JSDoc coverage. */
const roots=['resources','desktop-agent','browser-extension','tools','tests/frontend'];
const files=[];
for(const root of roots) if(fs.existsSync(root)) walk(root);
if(fs.existsSync('vite.config.ts')) files.push('vite.config.ts');
/** Recursively collects source files while excluding dependency and generated-release directories. */
function walk(dir){for(const entry of fs.readdirSync(dir,{withFileTypes:true})){const p=path.join(dir,entry.name);if(entry.isDirectory()){if(['node_modules','vendor','releases'].includes(entry.name))continue;walk(p)}else if(/\.(ts|tsx|js|jsx|mjs)$/.test(entry.name))files.push(p)}}
let total=0;const missing=[];
/** Returns whether the declaration has an immediately preceding JSDoc block. */
function hasDoc(text,node,sf){const pos=node.getStart(sf);const pre=text.slice(Math.max(0,pos-1200),pos);return /\/\*\*[\s\S]*?\*\/\s*$/.test(pre)}
for(const file of files){const text=fs.readFileSync(file,'utf8');const kind=file.endsWith('.tsx')?ts.ScriptKind.TSX:file.endsWith('.ts')?ts.ScriptKind.TS:file.endsWith('.jsx')?ts.ScriptKind.JSX:ts.ScriptKind.JS;const sf=ts.createSourceFile(file,text,ts.ScriptTarget.Latest,true,kind);
 /** Records one declaration in the documentation audit. */
 function record(node,label){total++;if(!hasDoc(text,node,sf)){const lc=sf.getLineAndCharacterOfPosition(node.getStart(sf));missing.push(`${file}:${lc.line+1} ${label}`)}}
 /** Visits declarations that require stable source documentation. */
 function visit(node){if(ts.isClassDeclaration(node)&&node.name)record(node,`class ${node.name.text}`);else if(ts.isInterfaceDeclaration(node)&&node.name)record(node,`interface ${node.name.text}`);else if(ts.isFunctionDeclaration(node)&&node.name)record(node,`function ${node.name.text}`);else if(ts.isMethodDeclaration(node)&&node.name)record(node,`method ${node.name.getText(sf)}`);else if(ts.isConstructorDeclaration(node))record(node,'constructor');else if(ts.isVariableStatement(node)){const named=node.declarationList.declarations.find(d=>ts.isIdentifier(d.name)&&d.initializer&&(ts.isArrowFunction(d.initializer)||ts.isFunctionExpression(d.initializer)));if(named)record(node,`function ${named.name.getText(sf)}`)}ts.forEachChild(node,visit)}
 visit(sf);
}
console.log(`JS/TS documented declarations: ${total}`);
if(missing.length){console.error(`Missing JSDoc declarations: ${missing.length}\n${missing.join('\n')}`);process.exit(1)}
console.log('Missing JSDoc declarations: 0');
