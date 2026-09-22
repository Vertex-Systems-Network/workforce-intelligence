#!/usr/bin/env node

import process from 'node:process'

const SCHEMA = 'workintel.m14-release-admin-evidence.v1'
const GITHUB_API_VERSION = '2026-03-10'
const REQUIRED_ENVIRONMENT = 'production-release'
const MAX_EVIDENCE_AGE_MS = 30 * 60 * 1000
const REQUIRED_SECRETS = [
  'WORKINTEL_RELEASE_POLICY_READ_TOKEN',
  'WORKINTEL_WINDOWS_SIGNING_PFX_B64',
  'WORKINTEL_WINDOWS_SIGNING_PFX_PASSWORD',
  'WORKINTEL_APPLE_DEVELOPER_ID_P12_B64',
  'WORKINTEL_APPLE_DEVELOPER_ID_P12_PASSWORD',
  'WORKINTEL_APPLE_SIGNING_IDENTITY',
  'WORKINTEL_APPLE_NOTARY_KEY_P8_B64',
  'WORKINTEL_APPLE_NOTARY_KEY_ID',
  'WORKINTEL_APPLE_NOTARY_ISSUER_ID',
]
const REQUIRED_VARIABLES = [
  'WORKINTEL_WINDOWS_TIMESTAMP_URL',
  'WORKINTEL_WINDOWS_SIGNING_CERT_SHA256',
  'WORKINTEL_APPLE_SIGNING_CERT_SHA256',
]
const EVIDENCE_KEYS = new Set(["schema","github_api_version","repository","source_contract_sha","collected_at","immutable_releases","environment","deployment_branch_policies","environment_secrets","environment_variables","repository_secrets","repository_variables","attestation"])
const ATTESTATION_KEYS = new Set(["admin_bypass_disabled_attested","required_reviewer_independence_attested","main_policy_is_branch_attested","agent_v_policy_is_tag_attested","release_policy_token_least_privilege_attested","windows_signer_fingerprint_matches_certificate_attested","apple_signer_fingerprint_matches_certificate_attested","no_organization_scope_release_credentials_attested","audit_token_least_privilege_attested","audited_by","audited_at"])

function fail(message) {
  console.error(`m14-release-admin-config: ${message}`)
  process.exit(1)
}

function requireObject(value, label) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) fail(`${label} must be an object`)
  return value
}

function requireString(value, label) {
  if (typeof value !== 'string' || value.trim() === '') fail(`${label} must be a non-empty string`)
  return value.trim()
}

function requireTrue(value, label) {
  if (value !== true) fail(`${label} must be true`)
}

function requireExactKeys(object, allowed, label) {
  for (const key of Object.keys(object)) {
    if (!allowed.has(key)) fail(`${label} contains unsupported field: ${key}`)
  }
  for (const key of allowed) {
    if (!Object.hasOwn(object, key)) fail(`${label} is missing required field: ${key}`)
  }
}

function requireTimestamp(value, label) {
  const text = requireString(value, label)
  if (Number.isNaN(Date.parse(text))) fail(`${label} must be an ISO-8601 timestamp`)
  return text
}

function normalizeFingerprint(value, label) {
  const text = requireString(value, label).toLowerCase()
  if (!/^[0-9a-f]{64}$/.test(text)) fail(`${label} must be a 64-hex SHA-256 fingerprint`)
  return text
}

function mapByName(items, label) {
  if (!Array.isArray(items)) fail(`${label} must be an array`)
  const map = new Map()
  for (const item of items) {
    const name = requireString(item?.name, `${label}[].name`)
    if (map.has(name)) fail(`${label} contains duplicate name: ${name}`)
    map.set(name, item)
  }
  return map
}

function requireCompleteList(container, key, label) {
  const object = requireObject(container, label)
  const items = object[key]
  if (!Array.isArray(items)) fail(`${label}.${key} must be an array`)
  if (!Number.isInteger(object.total_count) || object.total_count < 0) {
    fail(`${label}.total_count must be a non-negative integer`)
  }
  if (object.total_count !== items.length) {
    fail(`${label} appears truncated or paginated: total_count ${object.total_count} != collected ${items.length}`)
  }
  return items
}

function verifyEvidence(evidence, expectedRepository, expectedSourceSha, verifiedAt) {
  requireObject(evidence, 'evidence')
  requireExactKeys(evidence, EVIDENCE_KEYS, 'evidence')
  if (evidence.schema !== SCHEMA) fail(`schema must be ${SCHEMA}`)
  if (evidence.github_api_version !== GITHUB_API_VERSION) fail(`github_api_version must be ${GITHUB_API_VERSION}`)
  if (requireString(evidence.repository, 'repository') !== expectedRepository) {
    fail(`repository must be ${expectedRepository}`)
  }
  const sourceContractSha = requireString(evidence.source_contract_sha, 'source_contract_sha').toLowerCase()
  if (!/^[0-9a-f]{40}$/.test(sourceContractSha)) fail('source_contract_sha must be a 40-hex Git commit SHA')
  if (sourceContractSha !== expectedSourceSha) fail(`source_contract_sha must match expected source ${expectedSourceSha}`)
  const collectedAt = requireTimestamp(evidence.collected_at, 'collected_at')
  const collectedAtMs = Date.parse(collectedAt)
  const verifiedAtMs = Date.parse(verifiedAt)
  if (collectedAtMs > verifiedAtMs) fail('collected_at cannot be later than verifier system time')
  if (verifiedAtMs - collectedAtMs > MAX_EVIDENCE_AGE_MS) {
    fail('external admin evidence is stale; collect a fresh snapshot within 30 minutes of verification')
  }

  const immutable = requireObject(evidence.immutable_releases, 'immutable_releases')
  requireTrue(immutable.enabled, 'immutable_releases.enabled')

  const environment = requireObject(evidence.environment, 'environment')
  if (environment.name !== REQUIRED_ENVIRONMENT) fail(`environment.name must be ${REQUIRED_ENVIRONMENT}`)
  if (!Number.isInteger(environment.id) || environment.id <= 0) fail('environment.id must be a positive integer')
  const expectedEnvironmentUrl = `https://api.github.com/repos/${expectedRepository}/environments/${REQUIRED_ENVIRONMENT}`
  if (environment.url !== expectedEnvironmentUrl) fail(`environment.url must be ${expectedEnvironmentUrl}`)

  const reviewerRules = (Array.isArray(environment.protection_rules) ? environment.protection_rules : [])
    .filter(rule => rule && rule.type === 'required_reviewers')
  if (reviewerRules.length !== 1) fail('production-release must expose exactly one required_reviewers protection rule')
  const reviewerRule = reviewerRules[0]
  requireTrue(reviewerRule.prevent_self_review, 'required_reviewers.prevent_self_review')
  if (!Array.isArray(reviewerRule.reviewers) || reviewerRule.reviewers.length === 0) {
    fail('required_reviewers.reviewers must contain at least one user or team')
  }
  for (const entry of reviewerRule.reviewers) {
    const type = String(entry?.type || '')
    const reviewer = requireObject(entry?.reviewer, 'required_reviewers.reviewers[].reviewer')
    if (type === 'User') requireString(reviewer.login, 'required_reviewers User login')
    else if (type === 'Team') requireString(reviewer.slug, 'required_reviewers Team slug')
    else fail('required_reviewers reviewer type must be User or Team')
  }

  const deploymentPolicy = requireObject(environment.deployment_branch_policy, 'environment.deployment_branch_policy')
  if (deploymentPolicy.custom_branch_policies !== true || deploymentPolicy.protected_branches !== false) {
    fail('production-release must use custom deployment branch/tag policies')
  }

  const branchPolicyItems = requireCompleteList(
    evidence.deployment_branch_policies,
    'branch_policies',
    'deployment_branch_policies',
  )
  const policies = mapByName(branchPolicyItems, 'deployment_branch_policies.branch_policies')
  const requiredPolicyNames = ['main', 'agent-v*']
  if (!policies.has('main')) fail('deployment policies must include main')
  if (!policies.has('agent-v*')) fail('deployment policies must include agent-v*')
  if (policies.size !== requiredPolicyNames.length) {
    fail('production-release must not authorize deployment policies beyond main and agent-v*')
  }
  for (const name of requiredPolicyNames) {
    const policy = requireObject(policies.get(name), `deployment policy ${name}`)
    if (!Number.isInteger(policy.id) || policy.id <= 0) fail(`deployment policy ${name} id must be a positive integer`)
    requireString(policy.node_id, `deployment policy ${name} node_id`)
  }

  const secretItems = requireCompleteList(evidence.environment_secrets, 'secrets', 'environment_secrets')
  const secretMap = mapByName(secretItems, 'environment_secrets.secrets')
  for (const name of REQUIRED_SECRETS) {
    if (!secretMap.has(name)) fail(`missing production-release environment secret: ${name}`)
  }
  if (secretMap.size !== REQUIRED_SECRETS.length) {
    fail('production-release must not contain environment secrets outside the M14 allowlist')
  }

  const variableItems = requireCompleteList(evidence.environment_variables, 'variables', 'environment_variables')
  const variableMap = mapByName(variableItems, 'environment_variables.variables')
  for (const name of REQUIRED_VARIABLES) {
    if (!variableMap.has(name)) fail(`missing production-release environment variable: ${name}`)
  }
  if (variableMap.size !== REQUIRED_VARIABLES.length) {
    fail('production-release must not contain environment variables outside the M14 allowlist')
  }

  const repositorySecretItems = requireCompleteList(evidence.repository_secrets, 'secrets', 'repository_secrets')
  const repositorySecretMap = mapByName(repositorySecretItems, 'repository_secrets.secrets')
  for (const name of REQUIRED_SECRETS) {
    if (repositorySecretMap.has(name)) fail(`M14 secret must not exist at repository scope: ${name}`)
  }

  const repositoryVariableItems = requireCompleteList(evidence.repository_variables, 'variables', 'repository_variables')
  const repositoryVariableMap = mapByName(repositoryVariableItems, 'repository_variables.variables')
  for (const name of REQUIRED_VARIABLES) {
    if (repositoryVariableMap.has(name)) fail(`M14 variable must not exist at repository scope: ${name}`)
  }

  const timestampUrl = requireString(variableMap.get('WORKINTEL_WINDOWS_TIMESTAMP_URL')?.value, 'WORKINTEL_WINDOWS_TIMESTAMP_URL')
  let parsedTimestampUrl
  try {
    parsedTimestampUrl = new URL(timestampUrl)
  } catch {
    fail('WORKINTEL_WINDOWS_TIMESTAMP_URL must be a valid URL')
  }
  if (parsedTimestampUrl.protocol !== 'https:') fail('WORKINTEL_WINDOWS_TIMESTAMP_URL must use https')

  const windowsSigner = normalizeFingerprint(
    variableMap.get('WORKINTEL_WINDOWS_SIGNING_CERT_SHA256')?.value,
    'WORKINTEL_WINDOWS_SIGNING_CERT_SHA256',
  )
  const appleSigner = normalizeFingerprint(
    variableMap.get('WORKINTEL_APPLE_SIGNING_CERT_SHA256')?.value,
    'WORKINTEL_APPLE_SIGNING_CERT_SHA256',
  )

  const attestation = requireObject(evidence.attestation, 'attestation')
  requireExactKeys(attestation, ATTESTATION_KEYS, 'attestation')
  requireTrue(attestation.admin_bypass_disabled_attested, 'attestation.admin_bypass_disabled_attested')
  requireTrue(attestation.required_reviewer_independence_attested, 'attestation.required_reviewer_independence_attested')
  requireTrue(attestation.main_policy_is_branch_attested, 'attestation.main_policy_is_branch_attested')
  requireTrue(attestation.agent_v_policy_is_tag_attested, 'attestation.agent_v_policy_is_tag_attested')
  requireTrue(attestation.release_policy_token_least_privilege_attested, 'attestation.release_policy_token_least_privilege_attested')
  requireTrue(attestation.windows_signer_fingerprint_matches_certificate_attested, 'attestation.windows_signer_fingerprint_matches_certificate_attested')
  requireTrue(attestation.apple_signer_fingerprint_matches_certificate_attested, 'attestation.apple_signer_fingerprint_matches_certificate_attested')
  requireTrue(attestation.no_organization_scope_release_credentials_attested, 'attestation.no_organization_scope_release_credentials_attested')
  requireTrue(attestation.audit_token_least_privilege_attested, 'attestation.audit_token_least_privilege_attested')
  const auditedBy = requireString(attestation.audited_by, 'attestation.audited_by')
  const auditedAt = requireTimestamp(attestation.audited_at, 'attestation.audited_at')
  const auditedAtMs = Date.parse(auditedAt)
  if (auditedAtMs > verifiedAtMs) fail('attestation.audited_at cannot be later than verifier system time')
  if (verifiedAtMs - auditedAtMs > MAX_EVIDENCE_AGE_MS) {
    fail('administrator attestation is stale; audit within 30 minutes of verification')
  }
  if (Math.abs(auditedAtMs - collectedAtMs) > MAX_EVIDENCE_AGE_MS) {
    fail('administrator attestation and GitHub snapshot must be collected within 30 minutes of each other')
  }

  return {
    schema: SCHEMA,
    github_api_version: GITHUB_API_VERSION,
    repository: expectedRepository,
    source_contract_sha: sourceContractSha,
    collected_at: collectedAt,
    verified_at: verifiedAt,
    immutable_releases: {
      enabled: true,
      enforced_by_owner: immutable.enforced_by_owner === true,
    },
    environment: {
      name: REQUIRED_ENVIRONMENT,
      required_reviewer_count: reviewerRule.reviewers.length,
      prevent_self_review: true,
      custom_deployment_policies: true,
    },
    deployment_policies: {
      main: 'branch-attested',
      'agent-v*': 'tag-attested',
    },
    required_secret_count: REQUIRED_SECRETS.length,
    required_variable_count: REQUIRED_VARIABLES.length,
    fingerprints: {
      windows_signing_cert_sha256: windowsSigner,
      apple_signing_cert_sha256: appleSigner,
    },
    attestation: {
      admin_bypass_disabled: true,
      reviewer_independence: true,
      release_policy_token_least_privilege: true,
      windows_signer_fingerprint_matches_certificate: true,
      apple_signer_fingerprint_matches_certificate: true,
      no_organization_scope_release_credentials: true,
      audit_token_least_privilege: true,
      audited_by: auditedBy,
      audited_at: auditedAt,
    },
  }
}

function parseArgs(args) {
  const allowed = new Set(['repository', 'source-sha'])
  const parsed = {}
  for (let i = 0; i < args.length; i += 1) {
    const token = args[i]
    if (!token.startsWith('--')) fail(`unexpected argument: ${token}`)
    const key = token.slice(2)
    if (!allowed.has(key)) fail(`unsupported argument: --${key}`)
    if (Object.hasOwn(parsed, key)) fail(`duplicate argument: --${key}`)
    const value = args[i + 1]
    if (!value || value.startsWith('--')) fail(`missing value for --${key}`)
    parsed[key] = value
    i += 1
  }
  return parsed
}

const args = parseArgs(process.argv.slice(2))
const expectedRepository = requireString(args.repository, '--repository')
const expectedSourceSha = requireString(args['source-sha'], '--source-sha').toLowerCase()
if (!/^[0-9a-f]{40}$/.test(expectedSourceSha)) fail('--source-sha must be a 40-hex Git commit SHA')

let raw = ''
process.stdin.setEncoding('utf8')
process.stdin.on('data', chunk => { raw += chunk })
process.stdin.on('end', () => {
  let evidence
  try {
    evidence = JSON.parse(raw)
  } catch (error) {
    fail(`could not parse JSON evidence: ${error.message}`)
  }
  const verifiedAt = new Date().toISOString()
  const result = verifyEvidence(evidence, expectedRepository, expectedSourceSha, verifiedAt)
  console.log(JSON.stringify(result, null, 2))
})
