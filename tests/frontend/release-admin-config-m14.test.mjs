import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import test from 'node:test'

const verifier = 'tools/verify-m14-release-admin-config.mjs'
const repository = 'Vertex-Systems-Network/workforce-intelligence'
const sourceSha = '0123456789abcdef0123456789abcdef01234567'

function isoOffset(milliseconds) {
  return new Date(Date.now() + milliseconds).toISOString()
}

function evidence(overrides = {}) {
  return {
    schema: 'workintel.m14-release-admin-evidence.v1',
    github_api_version: '2026-03-10',
    repository,
    source_contract_sha: sourceSha,
    collected_at: isoOffset(-10 * 60 * 1000),
    immutable_releases: { enabled: true, enforced_by_owner: false },
    environment: {
      id: 7001,
      name: 'production-release',
      url: `https://api.github.com/repos/${repository}/environments/production-release`,
      protection_rules: [{
        type: 'required_reviewers',
        prevent_self_review: true,
        reviewers: [{ type: 'User', reviewer: { login: 'release-reviewer', id: 55 } }],
      }],
      deployment_branch_policy: {
        protected_branches: false,
        custom_branch_policies: true,
      },
    },
    deployment_branch_policies: {
      total_count: 2,
      branch_policies: [
        { id: 801, node_id: 'policy-main', name: 'main' },
        { id: 802, node_id: 'policy-agent-v', name: 'agent-v*' },
      ],
    },
    environment_secrets: {
      total_count: 9,
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
      total_count: 3,
      variables: [
        { name: 'WORKINTEL_WINDOWS_TIMESTAMP_URL', value: 'https://timestamp.example.test' },
        { name: 'WORKINTEL_WINDOWS_SIGNING_CERT_SHA256', value: 'a'.repeat(64) },
        { name: 'WORKINTEL_APPLE_SIGNING_CERT_SHA256', value: 'b'.repeat(64) },
      ],
    },
    repository_secrets: {
      total_count: 0,
      secrets: [],
    },
    repository_variables: {
      total_count: 0,
      variables: [],
    },
    attestation: {
      admin_bypass_disabled_attested: true,
      required_reviewer_independence_attested: true,
      main_policy_is_branch_attested: true,
      agent_v_policy_is_tag_attested: true,
      release_policy_token_least_privilege_attested: true,
      windows_signer_fingerprint_matches_certificate_attested: true,
      apple_signer_fingerprint_matches_certificate_attested: true,
      no_organization_scope_release_credentials_attested: true,
      audit_token_least_privilege_attested: true,
      audited_by: 'release-admin@example.test',
      audited_at: isoOffset(-5 * 60 * 1000),
    },
    ...overrides,
  }
}

function verify(payload) {
  return spawnSync(process.execPath, [
    verifier,
    '--repository', repository,
    '--source-sha', sourceSha,
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

test('rejects evidence collected under a different GitHub API version', () => {
  const payload = evidence({ github_api_version: '2022-11-28' })
  const result = verify(payload)
  assert.notEqual(result.status, 0)
  assert.match(result.stderr, /github_api_version must be 2026-03-10/)
})

test('rejects unknown evidence or attestation fields to prevent accidental secret serialization', () => {
  const extraEvidence = evidence()
  extraEvidence.accidental_token = 'should-never-be-serialized'
  const evidenceResult = verify(extraEvidence)
  assert.notEqual(evidenceResult.status, 0)
  assert.match(evidenceResult.stderr, /evidence contains unsupported field: accidental_token/)

  const extraAttestation = evidence()
  extraAttestation.attestation.password = 'should-never-be-serialized'
  const attestationResult = verify(extraAttestation)
  assert.notEqual(attestationResult.status, 0)
  assert.match(attestationResult.stderr, /attestation contains unsupported field: password/)
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

test('binds environment evidence to the expected repository and validates reviewer identity shape', () => {
  const wrongUrl = evidence()
  wrongUrl.environment.url = 'https://api.github.com/repos/other/repository/environments/production-release'
  assert.notEqual(verify(wrongUrl).status, 0)

  const malformedReviewer = evidence()
  malformedReviewer.environment.protection_rules[0].reviewers = [{ type: 'User', reviewer: {} }]
  assert.notEqual(verify(malformedReviewer).status, 0)

  const unsupportedReviewer = evidence()
  unsupportedReviewer.environment.protection_rules[0].reviewers = [{ type: 'Robot', reviewer: { login: 'bot' } }]
  assert.notEqual(verify(unsupportedReviewer).status, 0)
})

test('requires custom main and agent-v deployment policies plus audit type attestations', () => {
  const missingTag = evidence()
  missingTag.deployment_branch_policies.branch_policies = [{ id: 801, node_id: 'policy-main', name: 'main' }]
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

test('rejects duplicate or truncated GitHub list evidence', () => {
  const duplicatePolicy = evidence()
  duplicatePolicy.deployment_branch_policies.branch_policies.push({
    id: 803,
    node_id: 'policy-main-duplicate',
    name: 'main',
  })
  duplicatePolicy.deployment_branch_policies.total_count = 3
  assert.notEqual(verify(duplicatePolicy).status, 0)

  const truncatedPolicies = evidence()
  truncatedPolicies.deployment_branch_policies.total_count = 3
  assert.notEqual(verify(truncatedPolicies).status, 0)
  assert.match(verify(truncatedPolicies).stderr, /truncated or paginated/)

  const duplicateVariable = evidence()
  duplicateVariable.environment_variables.variables.push({
    name: 'WORKINTEL_WINDOWS_TIMESTAMP_URL',
    value: 'https://other.example.test',
  })
  duplicateVariable.environment_variables.total_count = 4
  assert.notEqual(verify(duplicateVariable).status, 0)

  const truncatedSecrets = evidence()
  truncatedSecrets.environment_secrets.total_count = 10
  assert.notEqual(verify(truncatedSecrets).status, 0)
  assert.match(verify(truncatedSecrets).stderr, /truncated or paginated/)
})

test('rejects extra privileged deployment policies, secrets, and variables', () => {
  const extraPolicy = evidence()
  extraPolicy.deployment_branch_policies.branch_policies.push({
    id: 803,
    node_id: 'policy-staging',
    name: 'staging',
  })
  extraPolicy.deployment_branch_policies.total_count = 3
  const policyResult = verify(extraPolicy)
  assert.notEqual(policyResult.status, 0)
  assert.match(policyResult.stderr, /must not authorize deployment policies beyond main and agent-v\*/)

  const extraSecret = evidence()
  extraSecret.environment_secrets.secrets.push({ name: 'UNRELATED_PRODUCTION_SECRET' })
  extraSecret.environment_secrets.total_count = 10
  const secretResult = verify(extraSecret)
  assert.notEqual(secretResult.status, 0)
  assert.match(secretResult.stderr, /must not contain environment secrets outside the M14 allowlist/)

  const extraVariable = evidence()
  extraVariable.environment_variables.variables.push({ name: 'UNRELATED_RELEASE_FLAG', value: 'true' })
  extraVariable.environment_variables.total_count = 4
  const variableResult = verify(extraVariable)
  assert.notEqual(variableResult.status, 0)
  assert.match(variableResult.stderr, /must not contain environment variables outside the M14 allowlist/)
})

test('rejects unknown or duplicate CLI arguments', () => {
  for (const args of [
    [verifier, '--repository', repository, '--source-sha', sourceSha, '--unexpected', 'value'],
    [verifier, '--repository', repository, '--repository', repository, '--source-sha', sourceSha],
  ]) {
    const result = spawnSync(process.execPath, args, {
      input: JSON.stringify(evidence()),
      encoding: 'utf8',
    })
    assert.notEqual(result.status, 0)
  }
})

test('requires the complete environment secret inventory without reading secret values', () => {
  const payload = evidence()
  payload.environment_secrets.secrets = payload.environment_secrets.secrets.filter(
    item => item.name !== 'WORKINTEL_APPLE_NOTARY_ISSUER_ID',
  )
  payload.environment_secrets.total_count = payload.environment_secrets.secrets.length
  const result = verify(payload)
  assert.notEqual(result.status, 0)
  assert.match(result.stderr, /WORKINTEL_APPLE_NOTARY_ISSUER_ID/)
})

test('requires M14 credentials to remain environment-scoped', () => {
  const repoSecret = evidence()
  repoSecret.repository_secrets = {
    total_count: 1,
    secrets: [{ name: 'WORKINTEL_RELEASE_POLICY_READ_TOKEN' }],
  }
  const secretResult = verify(repoSecret)
  assert.notEqual(secretResult.status, 0)
  assert.match(secretResult.stderr, /must not exist at repository scope/)

  const repoVariable = evidence()
  repoVariable.repository_variables = {
    total_count: 1,
    variables: [{ name: 'WORKINTEL_WINDOWS_SIGNING_CERT_SHA256', value: 'a'.repeat(64) }],
  }
  const variableResult = verify(repoVariable)
  assert.notEqual(variableResult.status, 0)
  assert.match(variableResult.stderr, /must not exist at repository scope/)
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
    'no_organization_scope_release_credentials_attested',
    'audit_token_least_privilege_attested',
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


test('rejects stale, future, or time-incoherent admin evidence', () => {
  const stale = evidence({ collected_at: isoOffset(-31 * 60 * 1000) })
  assert.notEqual(verify(stale).status, 0)
  assert.match(verify(stale).stderr, /evidence is stale/)

  const future = evidence({ collected_at: isoOffset(60 * 1000) })
  assert.notEqual(verify(future).status, 0)
  assert.match(verify(future).stderr, /cannot be later than verifier/)

  const staleAudit = evidence()
  staleAudit.attestation.audited_at = isoOffset(-31 * 60 * 1000)
  assert.notEqual(verify(staleAudit).status, 0)
  assert.match(verify(staleAudit).stderr, /attestation is stale/)

  const auditTooFarFromSnapshot = evidence({ collected_at: isoOffset(-1 * 60 * 1000) })
  auditTooFarFromSnapshot.attestation.audited_at = isoOffset(-32 * 60 * 1000)
  assert.notEqual(verify(auditTooFarFromSnapshot).status, 0)
  assert.match(verify(auditTooFarFromSnapshot).stderr, /within 30 minutes of each other/)

  const auditAfterVerification = evidence()
  auditAfterVerification.attestation.audited_at = isoOffset(60 * 1000)
  assert.notEqual(verify(auditAfterVerification).status, 0)
  assert.match(verify(auditAfterVerification).stderr, /cannot be later than verifier/)
})

test('accepts a fresh administrator attestation made shortly before live collection', () => {
  const payload = evidence({ collected_at: isoOffset(-2 * 60 * 1000) })
  payload.attestation.audited_at = isoOffset(-5 * 60 * 1000)
  const result = verify(payload)
  assert.equal(result.status, 0, result.stderr)
})


test('does not allow caller-controlled verifier time to bypass freshness', () => {
  const payload = evidence({ collected_at: isoOffset(-31 * 60 * 1000) })
  const result = spawnSync(process.execPath, [
    verifier,
    '--repository', repository,
    '--source-sha', sourceSha,
    '--as-of', payload.collected_at,
  ], {
    input: JSON.stringify(payload),
    encoding: 'utf8',
  })
  assert.notEqual(result.status, 0)
  assert.match(result.stderr, /unsupported argument: --as-of/)
})
