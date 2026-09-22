import assert from 'node:assert/strict'
import test from 'node:test'

import { collectEvidence } from '../../tools/collect-m14-release-admin-evidence.mjs'

const repository = 'Vertex-Systems-Network/workforce-intelligence'
const sourceSha = '0123456789abcdef0123456789abcdef01234567'
const auditToken = 'super-secret-auditor-token'
const validAttestation = {
  admin_bypass_disabled_attested: true,
  required_reviewer_independence_attested: true,
  main_policy_is_branch_attested: true,
  agent_v_policy_is_tag_attested: true,
  release_policy_token_least_privilege_attested: true,
  windows_signer_fingerprint_matches_certificate_attested: true,
  apple_signer_fingerprint_matches_certificate_attested: true,
  no_organization_scope_release_credentials_attested: true,
  audit_token_least_privilege_attested: true,
  audited_by: 'release-reviewer',
  audited_at: '2026-09-23T11:59:00Z',
}

function response(payload, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    async json() {
      return payload
    },
  }
}

function apiPayload(url) {
  if (url.endsWith('/immutable-releases')) return { enabled: true, enforced_by_owner: false }
  if (url.endsWith('/environments/production-release')) {
    return {
      id: 7001,
      name: 'production-release',
      url: `https://api.github.com/repos/${repository}/environments/production-release`,
      protection_rules: [],
      deployment_branch_policy: { protected_branches: false, custom_branch_policies: true },
    }
  }
  if (url.includes('/deployment-branch-policies?')) return { total_count: 0, branch_policies: [] }
  if (url.includes('/environments/production-release/secrets?')) return { total_count: 0, secrets: [] }
  if (url.includes('/environments/production-release/variables?')) return { total_count: 0, variables: [] }
  if (url.includes('/actions/secrets?')) return { total_count: 0, secrets: [] }
  if (url.includes('/actions/variables?')) return { total_count: 0, variables: [] }
  throw new Error(`unexpected test URL: ${url}`)
}

test('collects live admin evidence only from pinned GitHub API endpoints', async () => {
  const calls = []
  const request = async (url, options) => {
    calls.push({ url, options })
    return response(apiPayload(url))
  }

  const attestation = {
    admin_bypass_disabled_attested: true,
    required_reviewer_independence_attested: true,
    main_policy_is_branch_attested: true,
    agent_v_policy_is_tag_attested: true,
    release_policy_token_least_privilege_attested: true,
    windows_signer_fingerprint_matches_certificate_attested: true,
    apple_signer_fingerprint_matches_certificate_attested: true,
    no_organization_scope_release_credentials_attested: true,
    audited_by: 'release-reviewer',
    audited_at: '2026-09-23T11:59:00Z',
  }
  const evidence = await collectEvidence({
    repository,
    sourceSha,
    attestation,
    token: auditToken,
    request,
    now: () => new Date('2026-09-23T12:00:00Z'),
  })

  assert.equal(calls.length, 7)
  assert.equal(evidence.repository, repository)
  assert.equal(evidence.source_contract_sha, sourceSha)
  assert.equal(evidence.collected_at, '2026-09-23T12:00:00.000Z')
  assert.deepEqual(evidence.attestation, attestation)
  assert.equal(JSON.stringify(evidence).includes(auditToken), false)

  for (const call of calls) {
    assert.match(call.url, /^https:\/\/api\.github\.com\/repos\/Vertex-Systems-Network\/workforce-intelligence\//)
    assert.equal(call.options.method, 'GET')
    assert.equal(call.options.redirect, 'error')
    assert.equal(call.options.headers.Authorization, `Bearer ${auditToken}`)
    assert.equal(call.options.headers['X-GitHub-Api-Version'], '2026-03-10')
  }
})

test('never accepts caller-controlled GitHub API origins', async () => {
  const urls = []
  await collectEvidence({
    repository,
    sourceSha,
    attestation: { ...validAttestation },
    token: auditToken,
    request: async url => {
      urls.push(url)
      return response(apiPayload(url))
    },
  })
  assert.ok(urls.every(url => url.startsWith('https://api.github.com/')))
})

test('rejects unknown attestation fields before any GitHub request', async () => {
  let calls = 0
  await assert.rejects(
    () => collectEvidence({
      repository,
      sourceSha,
      attestation: { ...validAttestation, password: 'do-not-serialize' },
      token: auditToken,
      request: async () => {
        calls += 1
        return response({})
      },
    }),
    /attestation contains unsupported field: password/,
  )
  assert.equal(calls, 0)
})

test('fails closed when the auditor token is missing', async () => {
  await assert.rejects(
    () => collectEvidence({
      repository,
      sourceSha,
      attestation: { ...validAttestation },
      token: '',
      request: async url => response(apiPayload(url)),
    }),
    /WORKINTEL_M14_ADMIN_AUDIT_TOKEN is required/,
  )
})

test('fails closed on any non-success GitHub API response', async () => {
  await assert.rejects(
    () => collectEvidence({
      repository,
      sourceSha,
      attestation: { ...validAttestation },
      token: auditToken,
      request: async () => response({ message: 'forbidden' }, 403),
    }),
    /GitHub API request failed with status 403/,
  )
})

test('validates repository and exact source SHA before making requests', async () => {
  let calls = 0
  const request = async url => {
    calls += 1
    return response(apiPayload(url))
  }

  await assert.rejects(
    () => collectEvidence({
      repository: 'https://evil.example/repo',
      sourceSha,
      attestation: { ...validAttestation },
      token: auditToken,
      request,
    }),
    /owner\/name form/,
  )
  await assert.rejects(
    () => collectEvidence({
      repository,
      sourceSha: 'not-a-sha',
      attestation: { ...validAttestation },
      token: auditToken,
      request,
    }),
    /40-hex Git commit SHA/,
  )
  assert.equal(calls, 0)
})
