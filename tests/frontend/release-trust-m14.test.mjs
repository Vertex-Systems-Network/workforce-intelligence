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

test('M14 release-critical actions and Node trust runtime remain immutable', () => {
  const setupNodePin = 'actions/setup-node@820762786026740c76f36085b0efc47a31fe5020'
  for (const token of [
    'actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1',
    setupNodePin,
    'actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a',
    'actions/download-artifact@37930b1c2abaa49bbe596cd826c3c89aef350131',
    "node-version: '22.23.2'",
    'npm ci --no-audit --no-fund',
    'npm audit --audit-level=high',
  ]) assert.ok(workflow.includes(token), token)
  assert.ok(!workflow.includes('npm install'))
  assert.ok(!/uses:\s+actions\/(checkout|setup-node|upload-artifact|download-artifact)@v\d+/.test(workflow))

  assert.equal(workflow.split(setupNodePin).length - 1, 3, 'authorize, build-and-trust and publish must each pin setup-node')
  assert.equal(workflow.split("node-version: '22.23.2'").length - 1, 3, 'every Node-using trust job must pin Node 22.23.2')

  const authorizeJob = workflow.indexOf('\n  authorize:')
  const authorizeSetup = workflow.indexOf(setupNodePin, authorizeJob)
  const authorityScript = workflow.indexOf('- id: source', authorizeJob)
  assert.ok(authorizeSetup > authorizeJob && authorizeSetup < authorityScript, 'authorize must pin Node before source/version authority parsing')

  const publishJob = workflow.indexOf('\n  publish:')
  const publishSetup = workflow.indexOf(setupNodePin, publishJob)
  const receiptVerification = workflow.indexOf('Verify exact trusted asset set and receipts', publishJob)
  assert.ok(publishSetup > publishJob && publishSetup < receiptVerification, 'publish must pin Node before receipt verification')
})

test('M14 release authority binds source, dispatch ref, tag version and immutable publication', () => {
  for (const token of [
    'git merge-base --is-ancestor "$source_sha" "$main_sha"',
    "[ \"$GITHUB_EVENT_NAME\" = 'workflow_dispatch' ] && [ \"$GITHUB_REF\" != 'refs/heads/main' ]",
    'Manual trusted release workflows must be dispatched from refs/heads/main.',
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

  const dispatchRefGuard = workflow.indexOf('Manual trusted release workflows must be dispatched from refs/heads/main.')
  const buildTrustJob = workflow.indexOf('\n  build-and-trust:')
  assert.ok(dispatchRefGuard > 0 && dispatchRefGuard < buildTrustJob, 'dispatch ref must fail closed before signing/notarization jobs')
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

test('M14 release rollback only deletes a draft owned by the current run', () => {
  const createRelease = workflow.indexOf('gh release create "$RELEASE_TAG" trusted-release-assets/*')
  const markCreated = workflow.indexOf('created=1', createRelease)
  const publishRelease = workflow.indexOf('gh release edit "$RELEASE_TAG" --draft=false')
  const clearCreated = workflow.indexOf('created=0', publishRelease)

  assert.ok(workflow.includes('release_owner_marker="workintel-release-${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT}-$(uuidgen)"'))
  assert.ok(workflow.includes('if [ "$created" -eq 1 ]; then'))
  assert.ok(workflow.includes("release_is_draft=\"$(gh release view \"$RELEASE_TAG\" --json isDraft --jq '.isDraft' 2>/dev/null || true)\""))
  assert.ok(workflow.includes("release_body=\"$(gh release view \"$RELEASE_TAG\" --json body --jq '.body' 2>/dev/null || true)\""))
  assert.ok(workflow.includes('grep -Fq "<!-- ${release_owner_marker} -->"'))
  assert.ok(workflow.includes("release_notes=\"$(printf '%s\\n\\n<!-- %s -->'"))
  assert.ok(markCreated > createRelease, 'release ownership flag must only be set after create succeeds')
  assert.ok(clearCreated > publishRelease, 'rollback ownership must be cleared after publication succeeds')
  assert.ok(!workflow.includes('if [ "$published" -eq 0 ] && gh release view'))
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

test('release trust receipt rejects a platform trust-state mismatch', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-m14-platform-state-'))
  try {
    const artifact = path.join(root, 'WorkIntelAgent-Windows-1.2.3.exe')
    const receipt = `${artifact}.receipt.json`
    const finalBytes = Buffer.from('signed-windows-bytes')
    fs.writeFileSync(artifact, finalBytes)

    const create = spawnSync(process.execPath, [
      receiptTool,
      'create',
      '--artifact', artifact,
      '--output', receipt,
      '--platform', 'Windows',
      '--trust-state', 'SIGNED',
      '--source-sha', '0123456789abcdef0123456789abcdef01234567',
      '--release-version', '1.2.3',
      '--unsigned-sha256', sha256(Buffer.from('unsigned-windows-bytes')),
      '--verification-method', 'test Authenticode verification',
    ], { encoding: 'utf8' })
    assert.equal(create.status, 0, create.stderr || create.stdout)

    const payload = JSON.parse(fs.readFileSync(receipt, 'utf8'))
    payload.platform = 'Linux'
    fs.writeFileSync(receipt, `${JSON.stringify(payload, null, 2)}\n`)

    const verify = spawnSync(process.execPath, [
      receiptTool,
      'verify',
      '--receipt', receipt,
      '--artifact-root', root,
    ], { encoding: 'utf8' })
    assert.notEqual(verify.status, 0)
    assert.match(verify.stderr, /Invalid trust state for Linux: expected HASH_VERIFIED/)
  } finally {
    fs.rmSync(root, { recursive: true, force: true })
  }
})

test('release trust receipt requires notarization evidence id', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-m14-notary-evidence-'))
  try {
    const artifact = path.join(root, 'WorkIntelAgent-macOS-1.2.3.zip')
    fs.writeFileSync(artifact, 'notarized-macos-bytes')
    const result = spawnSync(process.execPath, [
      receiptTool,
      'create',
      '--artifact', artifact,
      '--output', `${artifact}.receipt.json`,
      '--platform', 'macOS',
      '--trust-state', 'NOTARIZED',
      '--source-sha', '0123456789abcdef0123456789abcdef01234567',
      '--release-version', '1.2.3',
      '--unsigned-sha256', sha256(Buffer.from('unsigned-macos-bytes')),
      '--verification-method', 'test Apple notarytool Accepted',
    ], { encoding: 'utf8' })
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /NOTARIZED receipt requires external evidence id/)
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