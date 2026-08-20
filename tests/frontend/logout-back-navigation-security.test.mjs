import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const authContext=readFileSync(new URL('../../resources/js/auth/AuthContext.tsx',import.meta.url),'utf8')
const authService=readFileSync(new URL('../../resources/js/auth/authService.ts',import.meta.url),'utf8')
const apiClient=readFileSync(new URL('../../resources/js/api/client.ts',import.meta.url),'utf8')
const app=readFileSync(new URL('../../resources/js/app.tsx',import.meta.url),'utf8')
const css=readFileSync(new URL('../../resources/css/app.css',import.meta.url),'utf8')
const headers=readFileSync(new URL('../../app/Http/Middleware/SecurityHeaders.php',import.meta.url),'utf8')
const sw=readFileSync(new URL('../../public/sw.js',import.meta.url),'utf8')

/** Assert browser back/forward cache cannot reveal a protected React snapshot after logout. */
test('private shell masks bfcache snapshots and revalidates the Laravel session on pageshow',()=>{
  assert.match(app,/pagehide/)
  assert.match(app,/data-workintel-private-snapshot/)
  assert.match(css,/data-workintel-private-snapshot/)
  assert.match(authContext,/pageshow/)
  assert.match(authContext,/event\.persisted/)
  assert.match(authContext,/back_forward/)
  assert.match(authContext,/validateSession\(true\)/)
})

/** Assert logout remains locally terminal even if browser history restores an older page entry. */
test('logout invalidates local and cross-tab auth state before awaiting the server',()=>{
  assert.match(authService,/LOCAL_LOGOUT_KEY/)
  assert.match(authService,/keepalive:\s*true/)
  assert.match(authService,/getItem\(LOCAL_LOGOUT_KEY\) === '1'/)
  assert.match(authContext,/workintel-auth-invalidated-at/)
  assert.match(authContext,/workintel:auth-invalidated/)
  assert.match(authContext,/setSession\(null\)/)
})

/** Assert expired authenticated API requests invalidate cached client auth immediately. */
test('401 and 419 responses invalidate the shared auth context',()=>{
  assert.match(apiClient,/\[401, 419\]/)
  assert.match(apiClient,/workintel:auth-invalidated/)
  assert.match(apiClient,/invalidateAuthOnStatus\(response\.status, path\)/)
})

/** Assert private server responses and private service-worker navigation are never cache-backed. */
test('private app shell uses no-store headers and network-only service-worker navigation',()=>{
  assert.match(headers,/no-store, private, max-age=0, must-revalidate/)
  assert.match(headers,/Pragma/)
  assert.match(headers,/Expires/)
  assert.doesNotMatch(sw,/SHELL=\['\/app'/)
  assert.match(sw,/fetch\(request,\{cache:'no-store'\}\)/)
})
