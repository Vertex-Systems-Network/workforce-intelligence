#!/usr/bin/env node

import { readFileSync } from 'node:fs'
import process from 'node:process'
import { pathToFileURL } from 'node:url'

const GITHUB_API_VERSION = '2026-03-10'
const API_ORIGIN = 'https://api.github.com'
const ENVIRONMENT = 'production-release'
const TOKEN_ENV = 'WORKINTEL_M14_ADMIN_AUDIT_TOKEN'
const MAX_LIST_ITEMS = 10_000
const ATTESTATION_KEYS = new Set(["admin_bypass_disabled_attested","required_reviewer_independence_attested","main_policy_is_branch_attested","agent_v_policy_is_tag_attested","release_policy_token_least_privilege_attested","windows_signer_fingerprint_matches_certificate_attested","apple_signer_fingerprint_matches_certificate_attested","no_organization_scope_release_credentials_attested","audit_token_least_privilege_attested","audited_by","audited_at"])

function fail(message) {
  throw new Error(`m14-release-admin-collector: ${message}`)
}

function parseArgs(args) {
  const allowed = new Set(['repository', 'source-sha', 'attestation-file'])
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

function requireRepository(value) {
  if (typeof value !== 'string' || !/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/.test(value)) {
    fail('--repository must be in owner/name form')
  }
  const [owner, name] = value.split('/')
  if (owner === '.' || owner === '..' || name === '.' || name === '..') {
    fail('--repository owner/name segments cannot be . or ..')
  }
  return value
}

function requireSha(value) {
  const sha = String(value || '').toLowerCase()
  if (!/^[0-9a-f]{40}$/.test(sha)) fail('--source-sha must be a 40-hex Git commit SHA')
  return sha
}

function requireAttestation(value) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) fail('attestation file must contain a JSON object')
  for (const key of Object.keys(value)) {
    if (!ATTESTATION_KEYS.has(key)) fail(`attestation contains unsupported field: ${key}`)
  }
  for (const key of ATTESTATION_KEYS) {
    if (!Object.hasOwn(value, key)) fail(`attestation is missing required field: ${key}`)
  }
  return value
}

async function fetchJson(request, url, token) {
  const response = await request(url, {
    method: 'GET',
    redirect: 'error',
    headers: {
      Accept: 'application/vnd.github+json',
      Authorization: `Bearer ${token}`,
      'X-GitHub-Api-Version': GITHUB_API_VERSION,
      'User-Agent': 'workintel-m14-admin-auditor',
    },
  })
  if (!response || response.ok !== true) {
    const status = response?.status ?? 'unknown'
    fail(`GitHub API request failed with status ${status}: ${url}`)
  }
  return response.json()
}

async function fetchCompleteList(request, url, token, key) {
  let page = 1
  let expectedTotal = null
  const items = []

  while (true) {
    const separator = url.includes('?') ? '&' : '?'
    const payload = await fetchJson(request, `${url}${separator}page=${page}`, token)
    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
      fail(`GitHub list payload must be an object: ${url}`)
    }
    if (!Number.isInteger(payload.total_count) || payload.total_count < 0) {
      fail(`GitHub list payload total_count must be a non-negative integer: ${url}`)
    }
    if (!Array.isArray(payload[key])) {
      fail(`GitHub list payload ${key} must be an array: ${url}`)
    }
    if (expectedTotal === null) {
      expectedTotal = payload.total_count
      if (expectedTotal > MAX_LIST_ITEMS) {
        fail(`GitHub list payload exceeds safety cap of ${MAX_LIST_ITEMS}: ${url}`)
      }
    } else if (payload.total_count !== expectedTotal) {
      fail(`GitHub list total_count changed during pagination: ${url}`)
    }

    items.push(...payload[key])
    if (items.length > expectedTotal) {
      fail(`GitHub list returned more items than total_count: ${url}`)
    }
    if (items.length === expectedTotal) break
    if (payload[key].length === 0) {
      fail(`GitHub list pagination ended before total_count was collected: ${url}`)
    }
    page += 1
  }

  return { total_count: expectedTotal ?? 0, [key]: items }
}

function normalizeAuditorIdentity(value) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) fail('authenticated auditor identity must be an object')
  const login = String(value.login || '').trim()
  if (login === '') fail('authenticated auditor identity login is required')
  if (!Number.isInteger(value.id) || value.id <= 0) fail('authenticated auditor identity id must be a positive integer')
  const type = String(value.type || '').trim()
  if (!['User', 'Bot'].includes(type)) fail('authenticated auditor identity type must be User or Bot')
  return { login, id: value.id, type }
}

export async function collectEvidence({
  repository,
  sourceSha,
  attestation,
  token,
  request = fetch,
  now = () => new Date(),
}) {
  const repo = requireRepository(repository)
  const sha = requireSha(sourceSha)
  requireAttestation(attestation)
  if (typeof token !== 'string' || token.trim() === '') fail(`${TOKEN_ENV} is required`)

  const [owner, name] = repo.split('/')
  const base = `${API_ORIGIN}/repos/${encodeURIComponent(owner)}/${encodeURIComponent(name)}`
  const environment = encodeURIComponent(ENVIRONMENT)

  const [
    auditorIdentity,
    immutableReleases,
    environmentState,
    deploymentBranchPolicies,
    environmentSecrets,
    environmentVariables,
    repositorySecrets,
    repositoryVariables,
  ] = await Promise.all([
    fetchJson(request, `${API_ORIGIN}/user`, token),
    fetchJson(request, `${base}/immutable-releases`, token),
    fetchJson(request, `${base}/environments/${environment}`, token),
    fetchCompleteList(request, `${base}/environments/${environment}/deployment-branch-policies?per_page=100`, token, 'branch_policies'),
    fetchCompleteList(request, `${base}/environments/${environment}/secrets?per_page=100`, token, 'secrets'),
    fetchCompleteList(request, `${base}/environments/${environment}/variables?per_page=100`, token, 'variables'),
    fetchCompleteList(request, `${base}/actions/secrets?per_page=100`, token, 'secrets'),
    fetchCompleteList(request, `${base}/actions/variables?per_page=100`, token, 'variables'),
  ])

  return {
    schema: 'workintel.m14-release-admin-evidence.v2',
    github_api_version: GITHUB_API_VERSION,
    auditor_identity: normalizeAuditorIdentity(auditorIdentity),
    repository: repo,
    source_contract_sha: sha,
    collected_at: now().toISOString(),
    immutable_releases: immutableReleases,
    environment: environmentState,
    deployment_branch_policies: deploymentBranchPolicies,
    environment_secrets: environmentSecrets,
    environment_variables: environmentVariables,
    repository_secrets: repositorySecrets,
    repository_variables: repositoryVariables,
    attestation,
  }
}

async function main() {
  const args = parseArgs(process.argv.slice(2))
  const repository = requireRepository(args.repository)
  const sourceSha = requireSha(args['source-sha'])
  const attestationFile = String(args['attestation-file'] || '')
  if (attestationFile === '') fail('--attestation-file is required')

  let attestation
  try {
    attestation = JSON.parse(readFileSync(attestationFile, 'utf8'))
  } catch (error) {
    fail(`could not read attestation JSON: ${error.message}`)
  }

  const token = process.env[TOKEN_ENV]
  const evidence = await collectEvidence({
    repository,
    sourceSha,
    attestation,
    token,
  })
  process.stdout.write(`${JSON.stringify(evidence, null, 2)}\n`)
}

const isCli = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href
if (isCli) {
  main().catch(error => {
    console.error(error.message)
    process.exit(1)
  })
}
