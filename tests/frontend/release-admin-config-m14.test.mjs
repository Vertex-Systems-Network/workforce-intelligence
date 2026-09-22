import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import test from 'node:test'

const verifier = 'tools/verify-m14-release-admin-config.mjs'
const repository = 'Vertex-Systems-Network/workforce-intelligence'
const sourceSha = '0123456789abcdef0123456789abcdef01234567'
const collectedAt = '2026-09-23T12:00:00Z'
const verifiedAt = '2026-09-23T12:20:00Z'

function evidence(overrides = {}) {
  return {
    schema: 'workintel.m14-release-admin-evidence.v1',
    repository,
    source_contract_sha: sourceSha,
    collected_at: collectedAt,
    immutable_releases: { enabled: true, enforced_by_owner: false },
    environment: {
      name: 'production-release',
      protection_rules: [{
        type: 'required_reviewers',
        prevent_self_review: true,
        reviewers: [{ type: 'User', reviewer: { login: 'release-reviewer' } }],
      }],
      deployment_branch_policy: {
        protected_branches: false,
        custom_branch_policies: true,
      },
    },
    deployment_branch_policies: {
      total_count: 2,
      branch_policies: [{ name: 'main' }, { name: 'agent-v*' }],
    },
    environment_secrets: {
      secrets: [
        'WORKINTEL_RELEASE_POLICY_READ_TOKEN',
        'WORKINTEL_WINDOWS_SIGNING_PFX_B64',
        'WORKINTEL_WINDOWS_SIGNING_PFX_PASSWORD',
        'WORKINTEL_APPLE_DEVELOPER_ID_P12_B64',
        'WORKINTEL_APPLE_DEVELOPER_ID_P12_PASSWORD',
        'WORKINTEL_APPLE_SIGNING_IDENTITY',
        'WORKINTEL_APPLE_NOTARY_KEY_P8_B64',
        'WORKINTEL_APPLE_NOTARY_KEY_ID',
        'WORKINTEL_APPLE_NOTARY_ISSUER_ID',
      ].map(name => ({ name })),
    },
    environment_variables: {
      variables: [
        { name: 'WORKINTEL_WINDOWS_TIMESTAMP_URL', value: 'https://timestamp.example.test' },
        { name: 'WORKINTEL_WINDOWS_SIGNING_CERT_SHA256', value: 'a'.repeat(64) },
        { name: 'WORKINTEL_APPLE_SIGNING_CERT_SHA256', value: 'b'.repeat(64) },
      ],
    },
    attestation: {
      admin_bypass_disabled_attested: true,
      required_reviewer_independence_attested: true,
      main_policy_is_branch_attested: true,
      agent_v_policy_is_tag_attested: true,
      release_policy_token_least_privilege_attested: true,
      windows_signer_fingerprint_matches_certificate_attested: true,
      apple_signer_fingerprint_matches_certificate_attested: true,
      audited_by: 'release-admin@example.test',
      audited_at: '2026-09-23T12:10:00Z',
    },
    ...overrides,
  }
}

function verify(payload) {
  return spawnSync(process.execPath, [
    verifier,
    '--repository', repository,
    '--source-sha', sourceSha,
    '--as-of', verifiedAt,
  ], {
    input: JSON.stringify(payload),
    encoding: 'utf8',
  })
}

test('accepts complete M14 external admin evidence', () => {
  const result = verify(evidence())
  assert.equal(result.status, 0, result.stderr)
  const output = JSON.parse(result.stdout)
  assert.equal(output.immutable_releases.enabled, true)
  assert.equal(output.environment.prevent_self_review, true)
  assert.equal(output.required_secret_count, 9)
  assert.equal(output.required_variable_count, 3)
  assert.equal(output.fingerprints.windows_signing_cert_sha256, 'a'.repeat(64))
  assert.equal(output.fingerprints.apple_signing_cert_sha256, 'b'.repeat(64))
})

test('fails closed when immutable releases are not verified', () => {
  const result = verify(evidence({ immutable_releases: { enabled: false } }))
  assert.notEqual(result.status, 0)
  assert.match(result.stderr, /immutable_releases\.enabled must be true/)
})

test('requires protected environment reviewer and prevent-self-review', () => {
  for (const protection_rules of [
    [],
    [{ type: 'required_reviewers', prevent_self_review: false, reviewers: [{ type: 'User', reviewer: { login: 'release-reviewer' } }] }],
    [{ type: 'required_reviewers', prevent_self_review: true, reviewers: [] }],
  ]) {
    const base = evidence()
    base.environment.protection_rules = protection_rules
    const result = verify(base)
    assert.notEqual(result.status, 0)
  }
})

test('requires custom main and agent-v deployment policies plus audit type attestations', () => {
  const missingTag = evidence()
  missingTag.deployment_branch_policies.branch_policies = [{ name: 'main' }]
  assert.notEqual(verify(missingTag).status, 0)

  const wrongMode = evidence()
  wrongMode.environment.deployment_branch_policy = { protected_branches: true, custom_branch_policies: false }
  assert.notEqual(verify(wrongMode).status, 0)

  for (const key of ['main_policy_is_branch_attested', 'agent_v_policy_is_tag_attested']) {
    const payload = evidence()
    payload.attestation[key] = false
    const result = verify(payload)
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, new RegExp(key))
  }
})

test('requires the complete environment secret inventory without reading secret values', () => {
  const payload = evidence()
  payload.environment_secrets.secrets = payload.environment_secrets.secrets.filter(
    item => item.name !== 'WORKINTEL_APPLE_NOTARY_ISSUER_ID',
  )
  const result = verify(payload)
  assert.notEqual(result.status, 0)
  assert.match(result.stderr, /WORKINTEL_APPLE_NOTARY_ISSUER_ID/)
})

test('requires signer fingerprint variables and https timestamp endpoint', () => {
  for (const [name, value, pattern] of [
    ['WORKINTEL_WINDOWS_SIGNING_CERT_SHA256', 'abc', /64-hex/],
    ['WORKINTEL_APPLE_SIGNING_CERT_SHA256', 'xyz', /64-hex/],
    ['WORKINTEL_WINDOWS_TIMESTAMP_URL', 'http://timestamp.example.test', /must use https/],
  ]) {
    const payload = evidence()
    payload.environment_variables.variables.find(item => item.name === name).value = value
    const result = verify(payload)
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, pattern)
  }
})

test('requires explicit attestations for API-invisible release authority facts', () => {
  for (const key of [
    'admin_bypass_disabled_attested',
    'required_reviewer_independence_attested',
    'release_policy_token_least_privilege_attested',
    'windows_signer_fingerprint_matches_certificate_attested',
    'apple_signer_fingerprint_matches_certificate_attested',
  ]) {
    const payload = evidence()
    payload.attestation[key] = false
    const result = verify(payload)
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, new RegExp(key))
  }
})

test('rejects wrong repository, environment, malformed audit identity and timestamp', () => {
  const wrongRepo = evidence({ repository: 'other/repository' })
  assert.notEqual(verify(wrongRepo).status, 0)

  const wrongEnvironment = evidence()
  wrongEnvironment.environment.name = 'staging'
  assert.notEqual(verify(wrongEnvironment).status, 0)

  for (const [key, value] of [['audited_by', ''], ['audited_at', 'not-a-date']]) {
    const payload = evidence()
    payload.attestation[key] = value
    assert.notEqual(verify(payload).status, 0)
  }
})


test('binds the admin evidence packet to the exact M14 source contract SHA', () => {
  const wrong = evidence({ source_contract_sha: 'f'.repeat(40) })
  const result = verify(wrong)
  assert.notEqual(result.status, 0)
  assert.match(result.stderr, /must match expected source/)
})


test('rejects stale, future, or audit-order-invalid admin evidence', () => {
  const stale = evidence({ collected_at: '2026-09-23T11:49:59Z' })
  assert.notEqual(verify(stale).status, 0)
  assert.match(verify(stale).stderr, /evidence is stale/)

  const future = evidence({ collected_at: '2026-09-23T12:20:01Z' })
  assert.notEqual(verify(future).status, 0)
  assert.match(verify(future).stderr, /cannot be later than verifier/)

  const auditBeforeCollection = evidence()
  auditBeforeCollection.attestation.audited_at = '2026-09-23T11:59:59Z'
  assert.notEqual(verify(auditBeforeCollection).status, 0)
  assert.match(verify(auditBeforeCollection).stderr, /cannot predate collected_at/)

  const auditAfterVerification = evidence()
  auditAfterVerification.attestation.audited_at = '2026-09-23T12:20:01Z'
  assert.notEqual(verify(auditAfterVerification).status, 0)
  assert.match(verify(auditAfterVerification).stderr, /cannot be later than verifier/)
})
