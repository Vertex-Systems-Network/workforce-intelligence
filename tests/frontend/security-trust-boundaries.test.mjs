import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'

const read = path => fs.readFileSync(path, 'utf8')

test('production builds cannot activate or render static demo authentication', () => {
  const authService = read('resources/js/auth/authService.ts')
  const login = read('resources/js/pages/auth/Login.tsx')
  const controller = read('app/Http/Controllers/Api/V1/AuthController.php')
  const config = read('config/workintel.php')
  const env = read('.env.example')

  assert.match(authService, /import\.meta\.env\?\.DEV\s*&&\s*import\.meta\.env\.VITE_AUTH_MODE === 'demo'/)
  assert.match(login, /runtimeDemos\.length > 0 \|\| authService\.mode === 'demo'/)
  assert.match(login, /authService\.mode === 'demo' \? 'owner@acme\.test' : ''/)
  assert.match(controller, /app\(\)->environment\('production'\) \|\| ! config\('workintel\.demo_accounts'\)/)
  assert.match(config, /env\('APP_ENV', 'production'\) !== 'production'[\s\S]*WORKINTEL_SHOW_DEMO_ACCOUNTS/)
  assert.match(env, /WORKINTEL_SHOW_DEMO_ACCOUNTS=false/)
})

test('outbound URL guard validates IPv4 and IPv6 and pins hostnames before requests', () => {
  const guard = read('app/Services/Security/OutboundUrlGuard.php')

  for (const token of [
    'DNS_A | DNS_AAAA',
    'FILTER_FLAG_NO_PRIV_RANGE',
    'FILTER_FLAG_NO_RES_RANGE',
    "'allow_redirects' => false",
    'CURLOPT_RESOLVE',
    'Single-label network hosts are not allowed',
    'Outbound destination could not be resolved',
  ]) assert.ok(guard.includes(token), token)
})

test('OIDC login verifies signed identity token and binds UserInfo to signed subject', () => {
  const oidc = read('app/Services/Enterprise/OidcService.php')

  for (const token of [
    'openssl_verify',
    'OPENSSL_ALGO_SHA256',
    "($header['alg'] ?? null) === 'RS256'",
    'OIDC ID token nonce is invalid.',
    'OIDC ID token audience is invalid.',
    'OIDC ID token issuer is invalid.',
    'OIDC UserInfo subject does not match the signed ID token.',
    "($profile['email_verified'] ?? null) === true",
    'This existing WorkIntel account is not linked to this workspace.',
  ]) assert.ok(oidc.includes(token), token)
})

test('production doctor fails closed on unsafe security posture', () => {
  const doctor = read('app/Console/Commands/ProductionCertificationDoctor.php')

  for (const token of [
    'production_https',
    'production_demo_accounts',
    'production_private_outbound',
    'production_csp',
    'production_hsts',
    'production_secure_cookie',
    'production_encrypted_session',
    'production_malware_scanning',
    'production_operator_identity',
  ]) assert.ok(doctor.includes(token), token)
})
