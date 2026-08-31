import assert from 'node:assert/strict'
import crypto from 'node:crypto'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { spawnSync } from 'node:child_process'
import test from 'node:test'

const read = file => fs.readFileSync(file, 'utf8')
const workflow = read('.github/workflows/desktop-agent-trusted-release.yml')
const receiptTool = 'tools/release-trust-receipt.mjs'

const sha256 = value => crypto.createHash('sha256').update(value).digest('hex')

test('M14 trusted release lane is structurally isolated from pull requests', () => {
  assert.ok(workflow.includes("tags: ['agent-v*']"))
  assert.ok(workflow.includes('workflow_dispatch:'))
  assert.ok(!workflow.includes('pull_request:'))
  assert.ok(workflow.includes('environment: production-release'))
  assert.ok(workflow.includes('permissions:\n  contents: read'))
  assert.ok(workflow.includes('contents: write'))
  assert.ok(!workflow.includes('self-hosted'))
  assert.ok(!workflow.includes('id-token: write'))
})

test('M14 release-critical actions and build graph remain immutable', () => {
  for (const token of [
    'actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1',
    'actions/setup-node@820762786026740c76f36085b0efc47a31fe5020',
    'actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a',
    'actions/download-artifact@37930b1c2abaa49bbe596cd826c3c89aef350131',
    "node-version: '22.23.2'",
    'npm ci --no-audit --no-fund',
    'npm audit --audit-level=high',
  ]) assert.ok(workflow.includes(token), token)
  assert.ok(!workflow.includes('npm install'))
  assert.ok(!/uses:\s+actions\/(checkout|setup-node|upload-artifact|download-artifact)@v\d+/.test(workflow))
})

test('M14 release authority binds source, tag version and immutable publication', () => {
  for (const token of [
    'git merge-base --is-ancestor "$source_sha" "$main_sha"',
    'Manual trusted release candidates must bind to the current protected-main head.',
    'Trusted release tags must point at the current protected-main head; stale main ancestors are not releasable.',
    'release_tag" != "agent-v${version}"',
    'ref: ${{ needs.authorize.outputs.source_sha }}',
    "if: github.event_name == 'push'",
    'Refusing to overwrite existing GitHub Release',
    '--verify-tag',
    '--draft',
    'gh release edit "$RELEASE_TAG" --draft=false',
  ]) assert.ok(workflow.includes(token), token)
  assert.ok(!workflow.includes('--clobber'))
})

test('M14 publication rechecks live main and tag refs before exposure', () => {
  assert.ok(workflow.includes('assert_live_release_refs()'))
  assert.ok(workflow.includes('git fetch --force origin "refs/tags/${RELEASE_TAG}:refs/tags/${RELEASE_TAG}"'))
  assert.ok(workflow.includes('current_main="$(git rev-parse origin/main)"'))
  assert.ok(workflow.includes('current_tag="$(git rev-list -n 1 "$RELEASE_TAG")"'))
  assert.ok(workflow.includes('Protected main moved after release authorization'))
  assert.ok(workflow.includes('Release tag moved after authorization'))
  assert.ok(workflow.indexOf('assert_live_release_refs\n          gh release edit "$RELEASE_TAG" --draft=false') > 0)
})

test('M14 rejects reused semantic versions before signing environments can run', () => {
  const manifestGuard = workflow.indexOf("fs.readFileSync('storage/app/releases/manifest.json','utf8')")
  const buildTrustJob = workflow.indexOf('\n  build-and-trust:')
  assert.ok(manifestGuard > 0, 'canonical release-manifest version guard is missing')
  assert.ok(buildTrustJob > manifestGuard, 'version guard must run in authorize before build-and-trust')
  assert.ok(workflow.includes('published_agent_version="$(node --input-type=commonjs'))
  assert.ok(workflow.includes("throw new Error('Canonical release manifest has no releases array')"))
  assert.ok(workflow.includes("if [ \"$published_agent_version\" != 'no' ]; then"))
  assert.ok(!workflow.includes('if node --input-type=commonjs -e "const fs=require'))
  assert.ok(workflow.includes('bump the native-agent version before signing/notarizing new bytes.'))
})

test('M14 trusted tag publication cannot reuse an already-published agent semantic version', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-m14-version-immutability-'))
  try {
    const artifact = path.join(root, 'WorkIntelAgent-Linux-1.2.2')
    const bytes = Buffer.from('existing-version-candidate')
    fs.writeFileSync(artifact, bytes)
    const digest = sha256(bytes)

    const result = spawnSync(process.execPath, [
      receiptTool,
      'create',
      '--artifact', artifact,
      '--output', `${artifact}.receipt.json`,
      '--platform', 'Linux',
      '--trust-state', 'HASH_VERIFIED',
      '--source-sha', '0123456789abcdef0123456789abcdef01234567',
      '--release-version', '1.2.2',
      '--unsigned-sha256', digest,
      '--verification-method', 'test checksum provenance',
    ], {
      encoding: 'utf8',
      env: {
        ...process.env,
        GITHUB_WORKFLOW: 'Desktop Agent Trusted Release',
        GITHUB_EVENT_NAME: 'push',
      },
    })

    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /already-published agent semantic version 1\.2\.2/)
    assert.ok(!fs.existsSync(`${artifact}.receipt.json`))
  } finally {
    fs.rmSync(root, { recursive: true, force: true })
  }
})

test('M14 Windows and macOS trust operations fail closed on missing organization credentials', () => {
  for (const token of [
    'WORKINTEL_WINDOWS_SIGNING_PFX_B64',
    'WORKINTEL_WINDOWS_SIGNING_PFX_PASSWORD',
    'WORKINTEL_WINDOWS_TIMESTAMP_URL',
    '/fd SHA256',
    '/tr $env:WINDOWS_TIMESTAMP_URL',
    '/td SHA256',
    'verify /pa /all /v',
    'WORKINTEL_APPLE_DEVELOPER_ID_P12_B64',
    'WORKINTEL_APPLE_SIGNING_IDENTITY',
    'WORKINTEL_APPLE_NOTARY_KEY_P8_B64',
    'codesign --force --options runtime --timestamp',
    'codesign --verify --strict --verbose=2',
    'xcrun notarytool submit',
    'notary_status',
    "!= 'Accepted'",
  ]) assert.ok(workflow.includes(token), token)
})

test('M14 platform receipts distinguish truthful trust states', () => {
  for (const token of [
    '--platform Windows',
    '--trust-state SIGNED',
    '--platform macOS',
    '--trust-state NOTARIZED',
    '--platform Linux',
    '--trust-state HASH_VERIFIED',
    '--external-evidence-id "$NOTARY_ID"',
  ]) assert.ok(workflow.includes(token), token)
})

test('release trust receipt creation and verification are tamper evident', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-m14-receipt-'))
  try {
    const artifact = path.join(root, 'WorkIntelAgent-Linux-1.2.2')
    const receipt = `${artifact}.receipt.json`
    const bytes = Buffer.from('trusted-linux-artifact')
    fs.writeFileSync(artifact, bytes)
    const digest = sha256(bytes)

    const create = spawnSync(process.execPath, [
      receiptTool,
      'create',
      '--artifact', artifact,
      '--output', receipt,
      '--platform', 'Linux',
      '--trust-state', 'HASH_VERIFIED',
      '--source-sha', '0123456789abcdef0123456789abcdef01234567',
      '--release-version', '1.2.2',
      '--unsigned-sha256', digest,
      '--verification-method', 'test checksum provenance',
    ], { encoding: 'utf8' })
    assert.equal(create.status, 0, create.stderr || create.stdout)

    const payload = JSON.parse(fs.readFileSync(receipt, 'utf8'))
    assert.equal(payload.final_sha256, digest)
    assert.equal(payload.trust_state, 'HASH_VERIFIED')
    assert.equal(payload.byte_changed_by_trust, false)

    const verify = spawnSync(process.execPath, [
      receiptTool,
      'verify',
      '--receipt', receipt,
      '--artifact-root', root,
    ], { encoding: 'utf8' })
    assert.equal(verify.status, 0, verify.stderr || verify.stdout)

    fs.appendFileSync(artifact, 'tamper')
    const tampered = spawnSync(process.execPath, [
      receiptTool,
      'verify',
      '--receipt', receipt,
      '--artifact-root', root,
    ], { encoding: 'utf8' })
    assert.notEqual(tampered.status, 0)
    assert.match(tampered.stderr, /Receipt (size|digest) mismatch/)
  } finally {
    fs.rmSync(root, { recursive: true, force: true })
  }
})

test('release trust receipt requires byte-changing evidence for signed states', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-m14-signed-receipt-'))
  try {
    const artifact = path.join(root, 'WorkIntelAgent-Windows-1.2.2.exe')
    fs.writeFileSync(artifact, 'signed-bytes')
    const unchangedDigest = sha256(Buffer.from('signed-bytes'))
    const result = spawnSync(process.execPath, [
      receiptTool,
      'create',
      '--artifact', artifact,
      '--output', `${artifact}.receipt.json`,
      '--platform', 'Windows',
      '--trust-state', 'SIGNED',
      '--source-sha', '0123456789abcdef0123456789abcdef01234567',
      '--release-version', '1.2.2',
      '--unsigned-sha256', unchangedDigest,
      '--verification-method', 'test signature verification',
    ], { encoding: 'utf8' })
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /SIGNED artifact did not change/)
  } finally {
    fs.rmSync(root, { recursive: true, force: true })
  }
})
