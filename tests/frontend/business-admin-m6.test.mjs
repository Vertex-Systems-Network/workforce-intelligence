import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
/** Read one repository source file for M6 contract verification. */
const read=file=>fs.readFileSync(file,'utf8')
const pages=['Clients','ClientCommerce','FinanceOps','Payroll','PayrollCompliance','Billing','Reports','Insights','Enterprise','AccessControl','Modules','Settings']
test('M6 business and administration pages have no legacy TableWrap or browser prompts',()=>{for(const page of pages){const source=read(`resources/js/pages/${page}.tsx`);assert.equal((source.match(/<TableWrap\b/g)||[]).length,0,`${page} TableWrap`);assert.equal((source.match(/window\.prompt\(/g)||[]).length,0,`${page} prompt`)}})
test('M6 specialized module homes are permission-aware and partial-failure resilient',()=>{const files=['resources/js/clients-commerce/ClientsCommerceHome.tsx','resources/js/finance-payroll/FinancePayrollHome.tsx','resources/js/intelligence/IntelligenceHome.tsx','resources/js/administration/AdministrationHome.tsx'];for(const file of files){const source=read(file);assert.match(source,/canAccessPage/);assert.match(source,/Promise\.allSettled/)}})
test('M6 finance payroll reports and enterprise surfaces use DataGrid V3 and FormDialog',()=>{for(const file of ['FinanceOps','Payroll','PayrollCompliance','Reports','Insights','Enterprise']){const source=read(`resources/js/pages/${file}.tsx`);assert.ok((source.match(/<DataGrid\b/g)||[]).length>0,file)}assert.ok((read('resources/js/pages/FinanceOps.tsx').match(/<FormDialog\b/g)||[]).length>=6);assert.ok((read('resources/js/pages/Enterprise.tsx').match(/<FormDialog\b/g)||[]).length>=8)})
test('M6 client commerce and billing use WorkIntel-owned payment forms',()=>{assert.equal((read('resources/js/pages/ClientCommerce.tsx').match(/<Modal\b/g)||[]).length,0);assert.match(read('resources/js/pages/Billing.tsx'),/manual-billing-payment/);assert.match(read('resources/js/pages/ClientCommerce.tsx'),/<SettingRow/)})
