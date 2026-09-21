import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'

/** Read one repository source file for static security contracts. */
const read = path => fs.readFileSync(path, 'utf8')

test('production builds cannot activate or render static demo authentication', () => {
  const authService = read('resources/js/auth/authService.ts')
  const login = read('resources/js/pages/auth/Login.tsx')
  const controller = read('app/Http/Controllers/Api/V1/AuthController.php')
  const config = read('config/workintel.php')
  const env = read('.env.example')

  assert.match(authService, /const AUTH_MODE = 'laravel' as const/)
  assert.doesNotMatch(authService, /DEMO_ACCOUNTS|VITE_AUTH_MODE|workintel-demo-session/)
  assert.match(login, /runtimeDemos\.length > 0/)
  assert.doesNotMatch(login, /DEMO_ACCOUNTS|CLIENT_PORTAL_DEMO|owner@acme\.test|authService\.mode/)
  assert.match(controller, /app\(\)->environment\('production'\) \|\| ! config\('workintel\.demo_accounts'\)/)
  assert.match(config, /env\('APP_ENV', 'production'\) !== 'production'[\s\S]*WORKINTEL_SHOW_DEMO_ACCOUNTS/)
  assert.match(env, /WORKINTEL_SHOW_DEMO_ACCOUNTS=false/)
  assert.equal(fs.existsSync('resources/js/auth/demoData.ts'), false)
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
  const controller = read('app/Http/Controllers/Api/V1/EnterpriseSsoController.php')

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
    'OIDC browser state binding is missing or invalid.',
    "(! isset($key['alg']) || $key['alg'] === 'RS256')",
    "array_key_exists('nbf', $claims)",
  ]) assert.ok(oidc.includes(token), token)

  assert.ok(oidc.includes("return in_array('mfa', $methods, true);"))
  assert.doesNotMatch(oidc, /'mfa', 'otp'/)

  for (const token of [
    'authorizationRequest',
    'browserStateCookieName',
    "'lax'",
    'withCookie',
  ]) assert.ok(controller.includes(token), token)
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


test('aggregate and demo seeders are forbidden in production', () => {
  const databaseSeeder = read('database/seeders/DatabaseSeeder.php')
  const demoSeeder = read('database/seeders/DemoWorkspaceSeeder.php')
  const accessSeeder = read('database/seeders/AccessControlSeeder.php')
  const identitySeeder = read('database/seeders/IdentitySeeder.php')
  const doctor = read('app/Console/Commands/ProductionCertificationDoctor.php')

  assert.match(databaseSeeder, /environment\('production'\)[\s\S]*must never run in production/)
  assert.match(demoSeeder, /environment\('production'\)[\s\S]*known demo credentials/)
  assert.match(accessSeeder, /environment\('production'\)[\s\S]*forbidden in production/)
  assert.match(identitySeeder, /environment\('production'\)[\s\S]*forbidden in production/)
  assert.match(doctor, /production_demo_identities/)
  assert.match(doctor, /production_demo_portal_identities/)
})

test('platform operator access is bound to verified email plus stable production user ID', () => {
  const service = read('app/Services/Commerce/PlatformOperatorService.php')
  const config = read('config/workintel.php')

  assert.match(service, /email_verified_at/)
  assert.match(service, /environment\('production'\)/)
  assert.match(service, /operator_user_ids/)
  assert.match(config, /WORKINTEL_PLATFORM_OPERATOR_USER_IDS/)
})
