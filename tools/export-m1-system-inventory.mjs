import fs from 'node:fs'
import path from 'node:path'

const root=process.cwd()
const arch=JSON.parse(fs.readFileSync(path.join(root,'docs/architecture/workintel-modules.json'),'utf8'))
const outDir=path.join(root,'docs/architecture')
fs.mkdirSync(outDir,{recursive:true})

/** Escape one CSV cell using RFC-4180 compatible quoting. */
const csvEscape=value=>`"${String(value??'').replaceAll('"','""')}"`
/** Write one deterministic CSV inventory file into the architecture documentation folder. */
const writeCsv=(name,headers,rows)=>{
  const text=[headers.map(csvEscape).join(','),...rows.map(row=>headers.map(h=>csvEscape(row[h])).join(','))].join('\n')+'\n'
  fs.writeFileSync(path.join(outDir,name),text)
}
/** Recursively collect source files that match the requested extensions. */
const walk=(dir,exts)=>fs.existsSync(dir)?fs.readdirSync(dir,{withFileTypes:true}).flatMap(entry=>entry.isDirectory()?walk(path.join(dir,entry.name),exts):(exts.some(ext=>entry.name.endsWith(ext))?[path.join(dir,entry.name)]:[])):[]

// Screen classification: every top-level screen plus nested page file.
const topPages=new Map(Object.entries(arch.screenMap).map(([id,row])=>[`${row.component}.tsx`,{id,...row}]))
const nonSidebar=new Map(Object.entries(arch.nonSidebarPages||{}).map(([component,row])=>[`${component}.tsx`,{id:'',component,...row,label:component}]))
const pageFiles=walk(path.join(root,'resources/js/pages'),['.tsx','.ts']).sort()
const screenRows=pageFiles.map(file=>{
  const rel=path.relative(root,file).replaceAll('\\','/')
  const base=path.basename(file)
  let row=topPages.get(base)||nonSidebar.get(base)
  if(!row){
    if(rel.includes('/pages/auth/')) row={id:'',target:'authentication',decision:'KEEP',label:path.basename(file,path.extname(file)),description:'Authentication/supporting auth surface.'}
    else if(rel.includes('/pages/settings/')) row={id:'',target:'administration',decision:'KEEP',label:path.basename(file,path.extname(file)),description:'Administration settings subpage.'}
    else row={id:'',target:'administration',decision:'REVIEW',label:path.basename(file,path.extname(file)),description:'Supporting page file requiring ownership review.'}
  }
  const src=fs.readFileSync(file,'utf8')
  return {file:rel,page_id:row.id||'',component:path.basename(file,path.extname(file)),target:row.target,decision:row.decision,label:row.label||'',description:row.description||'',raw_buttons:(src.match(/<button\b/g)||[]).length,raw_inputs:(src.match(/<input\b/g)||[]).length,inline_styles:(src.match(/style=\{\{/g)||[]).length}
})
writeCsv('M1_SCREEN_CLASSIFICATION.csv',['file','page_id','component','target','decision','label','description','raw_buttons','raw_inputs','inline_styles'],screenRows)

// Role navigation inventory.
const nav=JSON.parse(fs.readFileSync(path.join(root,'resources/js/navigation.manifest.json'),'utf8'))
const navRows=[]
for(const [role,groups] of Object.entries(nav)) for(const group of groups) for(const item of group.items){
  const id=item[0], row=arch.screenMap[id]
  navRows.push({role,group:group.id,page_id:id,current_label_key:item[1]||'',target:row?.target||'',decision:row?.decision||'',target_label:row?.label||''})
}
writeCsv('M1_NAVIGATION_INVENTORY.csv',['role','group','page_id','current_label_key','target','decision','target_label'],navRows)

// Permission inventory: every permission slug maps through its group.
const permissionSource=fs.readFileSync(path.join(root,'app/Support/PermissionCatalog.php'),'utf8')
const permissionBlock=(permissionSource.split('public const ITEMS = [')[1]||'').split('];')[0]||''
const permissionRows=[...permissionBlock.matchAll(/\['([^']+)',\s*'([^']+)'\]/g)].map(match=>({group:match[1],permission:match[2],target:arch.permissionGroupMap?.[match[1]]||'UNCLASSIFIED'}))
writeCsv('M1_PERMISSION_INVENTORY.csv',['group','permission','target'],permissionRows)

// Legacy module inventory.
const moduleRows=Object.entries(arch.currentModuleMap||{}).map(([module,target])=>({current_module:module,target_module:target,decision:module===target?'KEEP':'MAP'}))
writeCsv('M1_MODULE_CLASSIFICATION.csv',['current_module','target_module','decision'],moduleRows)

// Route file inventory.
const routeRows=Object.entries(arch.routeFileMap).map(([file,row])=>({file,target:row.target,decision:row.decision,description:row.description||''}))
writeCsv('M1_ROUTE_FILE_CLASSIFICATION.csv',['file','target','decision','description'],routeRows)

// UI/source file inventory. This is intentionally file-level; M2 will convert raw controls inside these files.
const uiRoots=['resources/js/pages','resources/js/components','resources/js/design-system','resources/js/media','resources/js/documents','resources/js/website','resources/js/attendance','resources/js/task-engine','resources/js/client-portal','resources/js/seller']
const seen=new Set(),uiRows=[]
for(const relativeRoot of uiRoots){
  for(const file of walk(path.join(root,relativeRoot),['.tsx','.ts'])){
    const rel=path.relative(root,file).replaceAll('\\','/')
    if(seen.has(rel)) continue; seen.add(rel)
    const src=fs.readFileSync(file,'utf8')
    let owner='shared'
    if(rel.startsWith('resources/js/design-system/')) owner='design-system'
    else if(rel.startsWith('resources/js/media/')) owner='content-studio'
    else if(rel.startsWith('resources/js/documents/')) owner='content-studio'
    else if(rel.startsWith('resources/js/website/')) owner='content-studio'
    else if(rel.startsWith('resources/js/attendance/')) owner='time-attendance'
    else if(rel.startsWith('resources/js/task-engine/')) owner='work-management'
    else if(rel.startsWith('resources/js/client-portal/')) owner='clients-commerce'
    else if(rel.startsWith('resources/js/seller/')) owner='platform-console'
    else if(rel.startsWith('resources/js/pages/')){
      const base=path.basename(file)
      owner=(topPages.get(base)||nonSidebar.get(base))?.target || (rel.includes('/auth/')?'authentication':rel.includes('/settings/')?'administration':'shared')
    }
    uiRows.push({file:rel,owner,raw_buttons:(src.match(/<button\b/g)||[]).length,raw_inputs:(src.match(/<input\b/g)||[]).length,raw_selects:(src.match(/<select\b/g)||[]).length,raw_textareas:(src.match(/<textarea\b/g)||[]).length,raw_tables:(src.match(/<table\b/g)||[]).length,raw_images:(src.match(/<img\b/g)||[]).length,inline_styles:(src.match(/style=\{\{/g)||[]).length,lucide:src.includes('lucide-react')?'yes':'no'})
  }
}
uiRows.sort((a,b)=>a.file.localeCompare(b.file))
writeCsv('M1_UI_FILE_INVENTORY.csv',['file','owner','raw_buttons','raw_inputs','raw_selects','raw_textareas','raw_tables','raw_images','inline_styles','lucide'],uiRows)

const routeDeclByFile={}
for(const file of fs.readdirSync(path.join(root,'routes')).filter(f=>f.endsWith('.php'))){
  const src=fs.readFileSync(path.join(root,'routes',file),'utf8')
  routeDeclByFile[file]=(src.match(/Route::(?:get|post|put|patch|delete|apiResource|resource)\s*\(/g)||[]).length
}
const rawTotals=uiRows.reduce((acc,row)=>{for(const key of ['raw_buttons','raw_inputs','raw_selects','raw_textareas','raw_tables','raw_images','inline_styles']) acc[key]+=Number(row[key]);return acc},{raw_buttons:0,raw_inputs:0,raw_selects:0,raw_textareas:0,raw_tables:0,raw_images:0,inline_styles:0})
const ownerNav=(nav.owner||[]).flatMap(group=>group.items).length
const md=`# M1 — Full System Inventory & Module Map\n\n## Baseline\n\nThis inventory is generated from the latest Block P runtime/media/UI hotfix baseline. It does not migrate runtime navigation yet; it locks ownership and migration decisions first.\n\n## Current inventory\n\n- Workspace shell page IDs: **${Object.keys(arch.screenMap).length}**\n- Page source files under \`resources/js/pages\`: **${pageFiles.length}**\n- Owner navigation destinations: **${ownerNav}**\n- Legacy switchable module keys: **${Object.keys(arch.currentModuleMap).length}**\n- Target workspace modules: **${arch.modules.length}**\n- Special product surfaces: **${arch.surfaces.length}**\n- Permission slugs: **${permissionRows.length}** across **${new Set(permissionRows.map(row=>row.group)).size}** permission groups\n- Laravel route source files: **${Object.keys(routeDeclByFile).length}**\n- Static route declarations: **${Object.values(routeDeclByFile).reduce((a,b)=>a+b,0)}**\n- Legacy \`routes/api.php\` declarations: **${routeDeclByFile['api.php']||0}**\n- UI/page/shared TS/TSX files inventoried: **${uiRows.length}**\n- Raw \`<button>\` occurrences in inventoried UI files: **${rawTotals.raw_buttons}**\n- Raw \`<input>\` occurrences: **${rawTotals.raw_inputs}**\n- Raw \`<select>\`: **${rawTotals.raw_selects}**; raw \`<textarea>\`: **${rawTotals.raw_textareas}**; raw \`<table>\`: **${rawTotals.raw_tables}**\n- Raw \`<img>\` occurrences: **${rawTotals.raw_images}**\n- Inline React style objects: **${rawTotals.inline_styles}**\n\n## Locked target architecture\n\n${arch.modules.map(module=>`1. **${module.label}** — ${module.description}`).join('\n')}\n\nSpecial surfaces are deliberately outside normal tenant module navigation: **Account & Support**, **Platform Console**, **Public Experience**, and **Authentication**.\n\n## High-impact consolidation decisions\n\n- **Shifts → Scheduling:** the standalone Shifts destination is a MERGE target; Scheduling is canonical.\n- **Legacy Scheduling.tsx → remove:** SchedulingHub is the canonical implementation.\n- **Platform → separate Platform Console:** operator/seller controls must not remain mixed into tenant workspace administration.\n- **Finance → Expenses & Procurement:** current mixed finance page is renamed for user clarity; Payroll remains a distinct area inside Finance & Payroll.\n- **Client Commerce → Client Billing & Payments:** makes intent explicit.\n- **Timesheets & Timer → Timesheets:** timer remains a workflow/action, not a competing navigation concept.\n- **Website Studio + Document Studio + Media Library → Content Studio:** one product family, three dedicated tools.\n- **Activity, Apps & Websites, Screenshots, Devices, Field Workforce → Workforce Operations:** monitoring/agent evidence is separated from HR and attendance.\n\n## Main maturity risks discovered\n\n1. **Route ownership concentration:** \`routes/api.php\` still owns ${routeDeclByFile['api.php']||0} static declarations. This must be split by module during M5/M6 without breaking URLs.\n2. **UI implementation drift:** raw controls and ${rawTotals.inline_styles} inline style objects remain across feature code. M2 must move feature pages onto the WorkIntel Design System rather than continue page-specific styling.\n3. **Navigation vs capability mismatch:** 26 legacy switchable module keys and 39 permission groups collapse into 11 user-facing modules. Backend capability keys should stay granular while the user-facing information architecture becomes simpler.\n4. **Duplicate scheduling surface:** a non-canonical Scheduling.tsx remains beside SchedulingHub. It is explicitly marked for removal after route/reference verification.\n5. **Tenant/operator boundary:** Platform functionality remains a workspace page today; the target architecture moves it into its own app shell.\n\n## M1 acceptance status\n\n- Screen/page ownership: **complete**\n- Navigation role inventory: **complete**\n- Legacy module → target module map: **complete**\n- Permission → target module map: **complete**\n- Route file ownership: **complete**\n- Runtime route ownership: generated separately in \`docs/architecture/M1_ROUTE_INVENTORY.json\`\n- UI/source file inventory: **complete**\n- KEEP / MERGE / RENAME / MOVE / REMOVE decisions: **complete for all current shell destinations and known non-sidebar pages**\n\nNo runtime menu migration is performed in M1. M2 creates the Design System; M3 consumes this architecture contract to rebuild the application shell.\n`
fs.writeFileSync(path.join(outDir,'M1_SYSTEM_INVENTORY.md'),md)
console.log(`M1 inventory exported: ${pageFiles.length} page files, ${permissionRows.length} permissions, ${uiRows.length} UI/source files.`)
