import fs from 'node:fs'
/** Read one repository source file for M6 contract verification. */
const read=file=>fs.readFileSync(file,'utf8')
const failures=[]
const pages=['Clients','ClientCommerce','FinanceOps','Payroll','PayrollCompliance','Billing','Reports','Insights','Enterprise','AccessControl','Modules','Settings']
let grids=0,legacy=0,prompts=0,formDialogs=0
for(const page of pages){const source=read(`resources/js/pages/${page}.tsx`);grids+=(source.match(/<DataGrid\b/g)||[]).length;legacy+=(source.match(/<TableWrap\b/g)||[]).length;prompts+=(source.match(/window\.prompt\(/g)||[]).length;formDialogs+=(source.match(/<FormDialog\b/g)||[]).length}
const moduleHome=read('resources/js/components/ModuleHome.tsx')
const homes=[['clients-commerce','ClientsCommerceHome'],['finance-payroll','FinancePayrollHome'],['intelligence','IntelligenceHome'],['administration','AdministrationHome']]
for(const [moduleId,component] of homes){if(!moduleHome.includes(`moduleId==='${moduleId}'&&<${component}`))failures.push(`${moduleId} specialized module home is missing.`);const file=component==='ClientsCommerceHome'?'resources/js/clients-commerce/ClientsCommerceHome.tsx':component==='FinancePayrollHome'?'resources/js/finance-payroll/FinancePayrollHome.tsx':component==='IntelligenceHome'?'resources/js/intelligence/IntelligenceHome.tsx':'resources/js/administration/AdministrationHome.tsx';const source=read(file);if(!source.includes('Promise.allSettled'))failures.push(`${component} must tolerate partial summary endpoint failure.`);if(!source.includes('canAccessPage'))failures.push(`${component} must remain role/permission aware.`)}
if(legacy!==0)failures.push(`M6 workspace pages still contain ${legacy} legacy TableWrap surface(s).`)
if(prompts!==0)failures.push(`M6 workspace pages still contain ${prompts} browser-native prompt(s).`)
if(grids<28)failures.push(`M6 DataGrid V3 adoption unexpectedly regressed to ${grids}.`)
if(formDialogs<30)failures.push(`M6 FormDialog adoption unexpectedly regressed to ${formDialogs}.`)
const billing=read('resources/js/pages/Billing.tsx');if(!billing.includes('manual-billing-payment'))failures.push('Billing manual payment is not a WorkIntel form dialog.');if((billing.match(/<TableWrap\b/g)||[]).length)failures.push('Billing still uses TableWrap.')
const clients=read('resources/js/pages/Clients.tsx');if((clients.match(/<TableWrap\b/g)||[]).length)failures.push('Clients still uses TableWrap.');if(!clients.includes('clients.invoice-lines.'))failures.push('Client invoice line items are not DataGrid V3-backed.')
const commerce=read('resources/js/pages/ClientCommerce.tsx');if((commerce.match(/<Modal\b/g)||[]).length)failures.push('Client Commerce form workflows still bypass FormDialog.');if(!commerce.includes('<SettingRow'))failures.push('Client Commerce gateway settings are not using shared SettingRow.')
const finance=read('resources/js/pages/FinanceOps.tsx');if((finance.match(/<TableWrap\b/g)||[]).length)failures.push('Finance Ops still uses TableWrap.');if((finance.match(/<FormDialog\b/g)||[]).length<6)failures.push('Finance Ops forms are not fully standardized.')
const payroll=read('resources/js/pages/Payroll.tsx');if((payroll.match(/<TableWrap\b/g)||[]).length)failures.push('Payroll still uses TableWrap.');if((payroll.match(/<FormDialog\b/g)||[]).length<3)failures.push('Payroll forms are not standardized.')
const compliance=read('resources/js/pages/PayrollCompliance.tsx');if((compliance.match(/<TableWrap\b/g)||[]).length)failures.push('Payroll Compliance still uses TableWrap.')
const reports=read('resources/js/pages/Reports.tsx');if((reports.match(/<TableWrap\b/g)||[]).length)failures.push('Reports still uses TableWrap.');if(!/DataGridColumn<Record<string,\s*any>>/.test(reports))failures.push('Dynamic report result tables are not DataGrid V3-backed.')
const insights=read('resources/js/pages/Insights.tsx');if((insights.match(/<TableWrap\b/g)||[]).length)failures.push('Intelligence still uses TableWrap.');if(!insights.includes('<SettingRow'))failures.push('Intelligence settings have not adopted shared SettingRow.')
const enterprise=read('resources/js/pages/Enterprise.tsx');if((enterprise.match(/<TableWrap\b/g)||[]).length)failures.push('Enterprise still uses TableWrap.');if((enterprise.match(/<FormDialog\b/g)||[]).length<8)failures.push('Enterprise forms are not standardized.')
console.log(`M6 Business/Admin audit: pages ${pages.length}; DataGrid ${grids}; FormDialog ${formDialogs}; TableWrap ${legacy}; browser prompts ${prompts}`)
if(failures.length){console.error(`M6 Business/Admin audit: FAIL (${failures.length})`);for(const failure of failures)console.error(` - ${failure}`);process.exit(1)}
console.log('M6 Business/Admin audit: PASS')
