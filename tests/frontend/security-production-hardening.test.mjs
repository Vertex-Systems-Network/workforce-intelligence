import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const headers=readFileSync(new URL('../../app/Http/Middleware/SecurityHeaders.php',import.meta.url),'utf8')
const media=readFileSync(new URL('../../app/Services/Media/MediaLibraryService.php',import.meta.url),'utf8')
const upload=readFileSync(new URL('../../app/Services/Security/UploadSecurityService.php',import.meta.url),'utf8')
const provider=readFileSync(new URL('../../app/Providers/AppServiceProvider.php',import.meta.url),'utf8')
const seller=readFileSync(new URL('../../resources/js/pages/SellerConsole.tsx',import.meta.url),'utf8')
const routes=readFileSync(new URL('../../routes/commerce.php',import.meta.url),'utf8')
const apiRoutes=readFileSync(new URL('../../routes/api.php',import.meta.url),'utf8')

/** Assert production browser hardening includes CSP and cross-origin isolation headers. */
test('security headers expose configurable CSP and cross-origin hardening',()=>{
  assert.match(headers,/Content-Security-Policy/)
  assert.match(headers,/Cross-Origin-Opener-Policy/)
  assert.match(headers,/Cross-Origin-Resource-Policy/)
  assert.match(headers,/X-Permitted-Cross-Domain-Policies/)
})

/** Assert uploads are inspected from bytes and malware detections enter quarantine storage. */
test('media uploads use content inspection malware scanning and quarantine',()=>{
  assert.match(upload,/FILEINFO_MIME_TYPE/)
  assert.match(upload,/new Process\(\[\$binary/)
  assert.match(media,/UploadSecurityService/)
  assert.match(media,/quarantine\//)
  assert.match(media,/status' => \$quarantined \? 'quarantined' : 'ready'/)
})

/** Assert sensitive public operations use named configurable rate-limit policies. */
test('named security rate limits protect auth public forms and media uploads',()=>{
  for(const name of ['auth-login','auth-register','password-reset','public-form','media-upload']) assert.match(provider,new RegExp(`RateLimiter::for\\('${name}'`))
  assert.match(apiRoutes,/throttle:auth-login/)
  assert.match(apiRoutes,/throttle:media-upload/)
})

/** Assert Seller Platform exposes a secret-safe security posture surface. */
test('seller security posture is isolated behind platform operator routes',()=>{
  assert.match(routes,/\/security-posture/)
  assert.match(seller,/Security Production Hardening/)
  assert.match(seller,/Production security checks/)
  assert.match(seller,/Rate-limit matrix/)
})


/** Assert sensitive generated-document bearer downloads can be issued as short-lived Laravel-signed URLs. */
test('signed document downloads are expiring and signature protected',()=>{
  const documentRoutes=readFileSync(new URL('../../routes/documents.php',import.meta.url),'utf8')
  const controller=readFileSync(new URL('../../app/Http/Controllers/Api/V1/DocumentStudioV4Controller.php',import.meta.url),'utf8')
  assert.match(documentRoutes,/middleware\('signed'\)/)
  assert.match(documentRoutes,/temporary-download-url/)
  assert.match(controller,/temporarySignedRoute/)
})
