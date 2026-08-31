#!/usr/bin/env node

import crypto from 'node:crypto'
import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'

function fail(message) {
  console.error(`release-trust-receipt: ${message}`)
  process.exit(1)
}

function parseArgs(values) {
  const args = {}
  for (let index = 0; index < values.length; index += 1) {
    const token = values[index]
    if (!token.startsWith('--')) fail(`Unexpected argument: ${token}`)
    const key = token.slice(2)
    const value = values[index + 1]
    if (!value || value.startsWith('--')) fail(`Missing value for --${key}`)
    args[key] = value
    index += 1
  }
  return args
}

function requireArg(args, key) {
  const value = args[key]
  if (!value) fail(`Missing required --${key}`)
  return value
}

function sha256(file) {
  return crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex')
}

function requireSha(value, label, length = 64) {
  const pattern = new RegExp(`^[0-9a-f]{${length}}$`)
  if (!pattern.test(value)) fail(`${label} must be a lowercase ${length}-hex digest`)
}

function readReceipt(file) {
  let payload
  try {
    payload = JSON.parse(fs.readFileSync(file, 'utf8'))
  } catch (error) {
    fail(`Could not parse receipt ${file}: ${error.message}`)
  }
  return payload
}

function validateReceiptShape(receipt) {
  if (receipt.schema_version !== 1) fail('Unsupported receipt schema version')
  if (!['Windows', 'macOS', 'Linux'].includes(receipt.platform)) fail('Invalid platform')
  if (!['HASH_VERIFIED', 'SIGNED', 'NOTARIZED'].includes(receipt.trust_state)) fail('Invalid trust state')
  if (!/^\d+(?:\.\d+){1,3}$/.test(receipt.release_version)) fail('Invalid release version')
  requireSha(receipt.source_sha, 'source_sha', 40)
  requireSha(receipt.unsigned_sha256, 'unsigned_sha256')
  requireSha(receipt.final_sha256, 'final_sha256')
  if (!Number.isInteger(receipt.final_size_bytes) || receipt.final_size_bytes <= 0) fail('Invalid final_size_bytes')
  if (typeof receipt.byte_changed_by_trust !== 'boolean') fail('Invalid byte_changed_by_trust')
  if (!receipt.artifact || path.basename(receipt.artifact) !== receipt.artifact) fail('Invalid artifact name')
  if (!receipt.verification || typeof receipt.verification.method !== 'string' || !receipt.verification.method) fail('Missing verification method')
  if (!receipt.workflow || typeof receipt.workflow !== 'object') fail('Missing workflow evidence')

  if (receipt.trust_state === 'HASH_VERIFIED' && receipt.byte_changed_by_trust) {
    fail('HASH_VERIFIED receipt cannot claim trust processing changed bytes')
  }
  if (['SIGNED', 'NOTARIZED'].includes(receipt.trust_state) && !receipt.byte_changed_by_trust) {
    fail(`${receipt.trust_state} receipt must prove byte-changing trust processing`)
  }
}

function createReceipt(args) {
  const artifact = path.resolve(requireArg(args, 'artifact'))
  const output = path.resolve(requireArg(args, 'output'))
  const platform = requireArg(args, 'platform')
  const trustState = requireArg(args, 'trust-state')
  const sourceSha = requireArg(args, 'source-sha').toLowerCase()
  const releaseVersion = requireArg(args, 'release-version')
  const unsignedSha = requireArg(args, 'unsigned-sha256').toLowerCase()
  const verificationMethod = requireArg(args, 'verification-method')

  if (!fs.existsSync(artifact) || !fs.statSync(artifact).isFile()) fail(`Artifact does not exist: ${artifact}`)
  const finalSize = fs.statSync(artifact).size
  if (finalSize <= 0) fail('Artifact is empty')

  requireSha(sourceSha, 'source_sha', 40)
  requireSha(unsignedSha, 'unsigned_sha256')
  if (!/^\d+(?:\.\d+){1,3}$/.test(releaseVersion)) fail('Invalid release version')
  if (!['Windows', 'macOS', 'Linux'].includes(platform)) fail('Invalid platform')
  if (!['HASH_VERIFIED', 'SIGNED', 'NOTARIZED'].includes(trustState)) fail('Invalid trust state')

  const finalSha = sha256(artifact)
  const changed = unsignedSha !== finalSha
  if (trustState === 'HASH_VERIFIED' && changed) fail('HASH_VERIFIED artifact must match its pre-trust digest')
  if (['SIGNED', 'NOTARIZED'].includes(trustState) && !changed) fail(`${trustState} artifact did not change from its unsigned digest`)

  const receipt = {
    schema_version: 1,
    artifact: path.basename(artifact),
    platform,
    trust_state: trustState,
    source_sha: sourceSha,
    release_version: releaseVersion,
    unsigned_sha256: unsignedSha,
    final_sha256: finalSha,
    final_size_bytes: finalSize,
    byte_changed_by_trust: changed,
    verification: {
      method: verificationMethod,
      external_evidence_id: args['external-evidence-id'] || null,
    },
    workflow: {
      repository: process.env.GITHUB_REPOSITORY || null,
      workflow: process.env.GITHUB_WORKFLOW || null,
      run_id: process.env.GITHUB_RUN_ID || null,
      run_attempt: process.env.GITHUB_RUN_ATTEMPT || null,
      event_name: process.env.GITHUB_EVENT_NAME || null,
      ref: process.env.GITHUB_REF || null,
    },
  }

  validateReceiptShape(receipt)
  fs.mkdirSync(path.dirname(output), { recursive: true })
  fs.writeFileSync(output, `${JSON.stringify(receipt, null, 2)}\n`, { flag: 'wx' })
  console.log(`Wrote release trust receipt: ${output}`)
}

function verifyReceipt(args) {
  const receiptFile = path.resolve(requireArg(args, 'receipt'))
  const artifactRoot = path.resolve(args['artifact-root'] || path.dirname(receiptFile))
  const receipt = readReceipt(receiptFile)
  validateReceiptShape(receipt)

  const artifact = path.join(artifactRoot, receipt.artifact)
  if (!fs.existsSync(artifact) || !fs.statSync(artifact).isFile()) fail(`Receipt artifact is missing: ${artifact}`)
  const actualSize = fs.statSync(artifact).size
  if (actualSize !== receipt.final_size_bytes) fail(`Receipt size mismatch for ${receipt.artifact}`)
  const actualSha = sha256(artifact)
  if (actualSha !== receipt.final_sha256) fail(`Receipt digest mismatch for ${receipt.artifact}`)

  console.log(`Verified release trust receipt: ${receipt.artifact} ${receipt.final_sha256}`)
}

const [command, ...rawArgs] = process.argv.slice(2)
if (!command || !['create', 'verify'].includes(command)) {
  fail('Usage: release-trust-receipt.mjs <create|verify> [arguments]')
}

const args = parseArgs(rawArgs)
if (command === 'create') createReceipt(args)
if (command === 'verify') verifyReceipt(args)
