import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root=path.resolve(import.meta.dirname,'../..')
/** Read one WorkIntel source file for dependency-free Commerce V2 contracts. */
function read(relative){return fs.readFileSync(path.join(root,relative),'utf8')}

test('seller platform is a dedicated route instead of a tenant navigation target',()=>{
  const app=read('resources/js/app.tsx')
  const manifest=JSON.parse(read('resources/js/navigation.manifest.json'))
  const seller=read('resources/js/seller/SellerPlatformApp.tsx')
  assert.match(app,/SellerPlatformApp/)
  assert.match(app,/\/seller/)
  assert.equal(JSON.stringify(manifest).includes('"seller"'),false)
  assert.match(seller,/platformOperator/)
  assert.match(seller,/WorkIntel Seller Platform/)
})

test('seller plan editor exposes capability toggles and limits without prompt editing',()=>{
  const page=read('resources/js/pages/SellerConsole.tsx')
  assert.match(page,/seller-capability-matrix/)
  assert.match(page,/capability_catalog/)
  assert.match(page,/\/entitlements/)
  assert.match(page,/Save plan/)
  assert.doesNotMatch(page,/window\.prompt/)
  assert.match(page,/DataGrid/)
  assert.match(page,/seller-customers/)
})

test('workspace client payments expose gateway and recurring invoice administration',()=>{
  const page=read('resources/js/pages/ClientCommerce.tsx')
  for(const token of ['Client Payments','client-commerce-grid','Recurring invoices','Allowed Pay Now gateways','client_payments.manage','client_invoices.recurring_manage']) assert.ok(page.includes(token),token)
  assert.doesNotMatch(page,/window\.prompt/)
})

test('client portal provides Pay Now and hosted checkout reconciliation',()=>{
  const portal=read('resources/js/client-portal/ClientPortalApp.tsx')
  for(const token of ['PaymentPanel','Pay now','payment-options','payment_checkout','payment-checkouts','Check status']) assert.ok(portal.includes(token),token)
  assert.ok(portal.includes('window.location.assign(response.data.checkout_url)'))
})

test('commerce UI styles isolate seller and client payment surfaces',()=>{
  const css=read('resources/css/app.css')
  for(const token of ['.seller-shell','.seller-auth','.seller-capability-matrix','.client-commerce-grid','.client-payment-panel','.client-payment-method']) assert.ok(css.includes(token),token)
})

test('seller plan capabilities control workspace Client Payments navigation',()=>{
  const auth=read('app/Http/Controllers/Api/V1/AuthController.php')
  const access=read('resources/js/access.ts')
  const types=read('resources/js/auth/types.ts')
  assert.match(auth,/workspace\.subscription\.plan\.entitlements/)
  assert.match(auth,/'entitlements'\s*=>/)
  assert.match(types,/entitlements\?: Record<string,boolean\|number\|string\|null>/)
  assert.match(access,/feature\.client_payments/)
  assert.match(access,/feature\.recurring_client_invoices/)
})

test('remote provider enable flows surface automatic activation test outcomes without losing saved settings',()=>{
  const workspace=read('resources/js/pages/ClientCommerce.tsx')
  const seller=read('resources/js/pages/SellerConsole.tsx')
  for(const source of [workspace,seller]){
    assert.match(source,/activation_test/)
    assert.match(source,/remains disabled/)
  }
  assert.match(workspace,/saved, tested and enabled/)
  assert.match(seller,/saved, tested and enabled/)
})
