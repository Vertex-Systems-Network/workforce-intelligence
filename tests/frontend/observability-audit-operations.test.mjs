import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const seller=readFileSync(new URL('../../resources/js/pages/SellerConsole.tsx',import.meta.url),'utf8')
const routes=readFileSync(new URL('../../routes/commerce.php',import.meta.url),'utf8')
const bootstrap=readFileSync(new URL('../../bootstrap/app.php',import.meta.url),'utf8')

/** Assert Seller Platform exposes the Block L observability dashboard and diagnostics controls. */
test('seller observability UI exposes health incidents rules and diagnostics',()=>{
  assert.match(seller,/Observability & Audit Operations/)
  assert.match(seller,/Diagnostics bundle/)
  assert.match(seller,/Alert incidents/)
  assert.match(seller,/Event ledger/)
  assert.match(seller,/Alert rules/)
  assert.match(seller,/seller-observability-events/)
})

/** Assert runtime request capture and seller-only API endpoints are wired. */
test('observability request middleware and seller api are wired',()=>{
  assert.match(bootstrap,/ObserveRequest::class/)
  assert.match(routes,/\/observability\/diagnostics/)
  assert.match(routes,/\/observability\/alerts\/\{alert\}\/acknowledge/)
})
