import assert from 'node:assert/strict'
import crypto from 'node:crypto'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { spawnSync } from 'node:child_process'
import test from 'node:test'

const read = file => fs.readFileSync(file, 'utf8')
const workflow = read('.github/workflows/desktop-agent-trusted-release.yml')
const immutableEvidenceWorkflow = read('.github/workflows/m14-immutable-release-evidence.yml')
const productionReleaseControlEvidenceWorkflow = read('.github/workflows/m14-production-release-control-evidence.yml')
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


test('M14 requires live GitHub immutable-release policy before trust work and public exposure', () => {
  for (const token of [
    '\n  release-policy:',
    'environment: production-release',
    'WORKINTEL_RELEASE_POLICY_READ_TOKEN',
    'repos/${GITHUB_REPOSITORY}/immutable-releases',
    "X-GitHub-Api-Version: 2026-03-10",
    "jq -e '.enabled == true'",
    'needs: [authorize, release-policy]',
    'needs: [authorize, release-policy, build-and-trust]',
    'assert_immutable_release_policy()',
  ]) assert.ok(workflow.includes(token), token)

  const policyJob = workflow.indexOf('\n  release-policy:')
  const buildTrustJob = workflow.indexOf('\n  build-and-trust:')
  assert.ok(policyJob > 0 && policyJob < buildTrustJob, 'immutable-release policy must pass before signing/notarization jobs')

  const publishStep = workflow.lastIndexOf('gh release edit "$RELEASE_TAG" --draft=false')
  const finalPolicyCheck = workflow.lastIndexOf('assert_immutable_release_policy', publishStep)
  const finalRefCheck = workflow.lastIndexOf('assert_live_release_refs', publishStep)
  assert.ok(finalPolicyCheck > 0 && finalPolicyCheck < finalRefCheck, 'immutable-release policy must be rechecked immediately before final release exposure sequence')
})

test('M14 scopes the release-policy administration token only to policy verification shell steps', () => {
  const publishJob = workflow.indexOf('\n  publish:')
  const publishSteps = workflow.indexOf('\n    steps:', publishJob)
  const publishJobHeader = workflow.slice(publishJob, publishSteps)
  assert.ok(!publishJobHeader.includes('WORKINTEL_RELEASE_POLICY_READ_TOKEN'), 'publish job-level env must not expose the Administration-read token to every action')

  const publishPolicyStep = workflow.indexOf('- name: Create and atomically expose new trusted release', publishJob)
  const publishPolicyRun = workflow.indexOf('        run: |', publishPolicyStep)
  const publishPolicyHeader = workflow.slice(publishPolicyStep, publishPolicyRun)
  assert.ok(publishPolicyHeader.includes('RELEASE_POLICY_TOKEN: ${{ secrets.WORKINTEL_RELEASE_POLICY_READ_TOKEN }}'), 'publication policy token must be step-scoped')

  assert.equal(
    workflow.split('WORKINTEL_RELEASE_POLICY_READ_TOKEN').length - 1,
    2,
    'policy token should appear only in the pre-trust policy step and final publication step',
  )
})

test('M14 publication rechecks live main and tag refs before exposure', () => {
  assert.ok(workflow.includes('assert_live_release_refs()'))
  assert.ok(workflow.includes('git fetch --force origin "refs/tags/${RELEASE_TAG}:refs/tags/${RELEASE_TAG}"'))
  assert.ok(workflow.includes('current_main="$(git rev-parse origin/main)"'))
  assert.ok(workflow.includes('current_tag="$(git rev-list -n 1 "$RELEASE_TAG")"'))
  assert.ok(workflow.includes('Protected main moved after release authorization'))
  assert.ok(workflow.includes('Release tag moved after authorization'))
  const publishStep = workflow.lastIndexOf('gh release edit "$RELEASE_TAG" --draft=false')
  const finalRefCheck = workflow.lastIndexOf('assert_live_release_refs', publishStep)
  const finalAssetCheck = workflow.lastIndexOf('assert_remote_release_assets_match', publishStep)
  assert.ok(finalRefCheck > 0 && finalRefCheck < publishStep, 'live main/tag refs must be rechecked before exposure')
  assert.ok(finalAssetCheck > finalRefCheck && finalAssetCheck < publishStep, 'remote asset bytes must be rechecked after live refs and before exposure')
})

test('M14 publication verifies receipts against the exact release run', () => {
  for (const token of [
    '--expected-source-sha "$SOURCE_SHA"',
    '--expected-release-version "$VERSION"',
    '--expected-repository "$GITHUB_REPOSITORY"',
    '--expected-workflow "$GITHUB_WORKFLOW"',
    '--expected-run-id "$GITHUB_RUN_ID"',
    '--expected-run-attempt "$GITHUB_RUN_ATTEMPT"',
    '--expected-event-name "$GITHUB_EVENT_NAME"',
    '--expected-ref "$GITHUB_REF"',
  ]) assert.ok(workflow.includes(token), token)
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
    'WORKINTEL_WINDOWS_SIGNING_CERT_SHA256',
    'WORKINTEL_WINDOWS_TIMESTAMP_URL',
    '/fd SHA256',
    '/tr $env:WINDOWS_TIMESTAMP_URL',
    '/td SHA256',
    'verify /pa /all /v',
    'WORKINTEL_APPLE_DEVELOPER_ID_P12_B64',
    'WORKINTEL_APPLE_SIGNING_IDENTITY',
    'WORKINTEL_APPLE_SIGNING_CERT_SHA256',
    'WORKINTEL_APPLE_NOTARY_KEY_P8_B64',
    'openssl pkcs12 -in "$p12_path" -clcerts -nokeys -passin env:APPLE_DEVELOPER_ID_P12_PASSWORD',
    'Imported Apple Developer ID certificate SHA-256 fingerprint does not match the approved signer identity.',
    'Expected exactly one imported macOS Code Signing identity matching the approved Developer ID',
    'codesign --force --options runtime --timestamp',
    'codesign --verify --strict --verbose=2',
    'xcrun notarytool submit',
    'notary_status',
    "!= 'Accepted'",
  ]) assert.ok(workflow.includes(token), token)
})

test('M14 Windows signing selects exactly one newly imported private-key Code Signing certificate', () => {
  for (const token of [
    "$certificateStorePath = 'Cert:\\CurrentUser\\My'",
    '$certificateStoreBefore = @(Get-ChildItem $certificateStorePath',
    '$importedCertificates = @(Import-PfxCertificate',
    '$newCertificates = @(Get-ChildItem $certificateStorePath',
    '$codeSigningCandidates = @($newCertificates',
    "1.3.6.1.5.5.7.3.3",
    '$_.HasPrivateKey',
    'Expected exactly one newly imported Code Signing certificate with a private key',
    '$certificate = $codeSigningCandidates[0]',
    '$certificateThumbprint = [string]$certificate.Thumbprint',
    '$certificate.GetCertHashString([System.Security.Cryptography.HashAlgorithmName]::SHA256)',
    '$certificateSha256 -ne $expectedCertificateSha256',
    'Imported Windows Code Signing certificate SHA-256 fingerprint does not match the approved signer identity.',
    'WINDOWS_SIGNING_CERT_SHA256=$certificateSha256',
    'foreach ($thumbprint in $importedThumbprints)',
    'Remove-Item "$certificateStorePath\\$thumbprint"',
  ]) assert.ok(workflow.includes(token), token)
  assert.ok(!workflow.includes('$certificateThumbprint = $certificate.Thumbprint'))
})

test('M14 platform receipts distinguish truthful trust states', () => {
  for (const token of [
    '--platform Windows',
    '--trust-state SIGNED',
    '--external-evidence-id "authenticode-cert-sha256:$env:WINDOWS_SIGNING_CERT_SHA256"',
    '--platform macOS',
    '--trust-state NOTARIZED',
    '--platform Linux',
    '--trust-state HASH_VERIFIED',
    '--external-evidence-id "apple-notary:$NOTARY_ID;developer-id-cert-sha256:$APPLE_SIGNING_CERT_SHA256"',
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

test('release trust receipt requires macOS signer fingerprint evidence', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-m14-macos-signer-evidence-'))
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
      '--external-evidence-id', 'apple-notary:test-notary-id',
    ], { encoding: 'utf8' })
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /NOTARIZED macOS receipt requires Apple notary id and Developer ID certificate SHA-256 evidence/)
  } finally {
    fs.rmSync(root, { recursive: true, force: true })
  }
})

test('release trust receipt accepts bound macOS notary and signer evidence', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-m14-macos-bound-evidence-'))
  try {
    const artifact = path.join(root, 'WorkIntelAgent-macOS-1.2.3.zip')
    fs.writeFileSync(artifact, 'notarized-macos-bytes')
    const receipt = `${artifact}.receipt.json`
    const create = spawnSync(process.execPath, [
      receiptTool,
      'create',
      '--artifact', artifact,
      '--output', receipt,
      '--platform', 'macOS',
      '--trust-state', 'NOTARIZED',
      '--source-sha', '0123456789abcdef0123456789abcdef01234567',
      '--release-version', '1.2.3',
      '--unsigned-sha256', sha256(Buffer.from('unsigned-macos-bytes')),
      '--verification-method', 'test Apple notarytool Accepted',
      '--external-evidence-id', `apple-notary:test-notary-id;developer-id-cert-sha256:${'b'.repeat(64)}`,
    ], { encoding: 'utf8' })
    assert.equal(create.status, 0, create.stderr)
    const verify = spawnSync(process.execPath, [
      receiptTool,
      'verify',
      '--receipt', receipt,
      '--artifact-root', root,
    ], { encoding: 'utf8' })
    assert.equal(verify.status, 0, verify.stderr)
  } finally {
    fs.rmSync(root, { recursive: true, force: true })
  }
})

test('release trust receipt requires Windows signer fingerprint evidence', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-m14-windows-signer-evidence-'))
  try {
    const artifact = path.join(root, 'WorkIntelAgent-Windows-1.2.3.exe')
    fs.writeFileSync(artifact, 'signed-windows-bytes')
    const result = spawnSync(process.execPath, [
      receiptTool,
      'create',
      '--artifact', artifact,
      '--output', `${artifact}.receipt.json`,
      '--platform', 'Windows',
      '--trust-state', 'SIGNED',
      '--source-sha', '0123456789abcdef0123456789abcdef01234567',
      '--release-version', '1.2.3',
      '--unsigned-sha256', sha256(Buffer.from('unsigned-windows-bytes')),
      '--verification-method', 'test Authenticode verification',
    ], { encoding: 'utf8' })
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /SIGNED Windows receipt requires Authenticode certificate SHA-256 evidence/)
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
      '--external-evidence-id', `authenticode-cert-sha256:${'a'.repeat(64)}`,
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
    assert.match(verify.stderr, /Artifact name does not match Linux release version 1\.2\.3|Invalid trust state for Linux/)
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
      '--external-evidence-id', `authenticode-cert-sha256:${'a'.repeat(64)}`,
    ], { encoding: 'utf8' })
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /SIGNED artifact did not change/)
  } finally {
    fs.rmSync(root, { recursive: true, force: true })
  }
})

test('release trust receipt pairs artifact filename and receipt filename', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-m14-receipt-pairing-'))
  try {
    const wrongArtifact = path.join(root, 'unexpected-linux-name')
    fs.writeFileSync(wrongArtifact, 'linux-bytes')
    const wrongArtifactCreate = spawnSync(process.execPath, [
      receiptTool,
      'create',
      '--artifact', wrongArtifact,
      '--output', `${wrongArtifact}.receipt.json`,
      '--platform', 'Linux',
      '--trust-state', 'HASH_VERIFIED',
      '--source-sha', '0123456789abcdef0123456789abcdef01234567',
      '--release-version', '1.2.3',
      '--unsigned-sha256', sha256(Buffer.from('linux-bytes')),
      '--verification-method', 'test checksum provenance',
    ], { encoding: 'utf8' })
    assert.notEqual(wrongArtifactCreate.status, 0)
    assert.match(wrongArtifactCreate.stderr, /Artifact name does not match Linux release version 1\.2\.3/)

    const artifact = path.join(root, 'WorkIntelAgent-Linux-1.2.3')
    const receipt = `${artifact}.receipt.json`
    fs.writeFileSync(artifact, 'linux-bytes')
    const create = spawnSync(process.execPath, [
      receiptTool,
      'create',
      '--artifact', artifact,
      '--output', receipt,
      '--platform', 'Linux',
      '--trust-state', 'HASH_VERIFIED',
      '--source-sha', '0123456789abcdef0123456789abcdef01234567',
      '--release-version', '1.2.3',
      '--unsigned-sha256', sha256(Buffer.from('linux-bytes')),
      '--verification-method', 'test checksum provenance',
    ], { encoding: 'utf8' })
    assert.equal(create.status, 0, create.stderr || create.stdout)

    const detached = path.join(root, 'detached.receipt.json')
    fs.renameSync(receipt, detached)
    const verify = spawnSync(process.execPath, [
      receiptTool,
      'verify',
      '--receipt', detached,
      '--artifact-root', root,
    ], { encoding: 'utf8' })
    assert.notEqual(verify.status, 0)
    assert.match(verify.stderr, /Receipt filename does not pair exactly with its artifact/)
  } finally {
    fs.rmSync(root, { recursive: true, force: true })
  }
})

test('release trust receipt publication expectations bind source and workflow run', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'workintel-m14-publication-binding-'))
  try {
    const artifact = path.join(root, 'WorkIntelAgent-Linux-1.2.3')
    const receipt = `${artifact}.receipt.json`
    const bytes = Buffer.from('linux-publication-bytes')
    const sourceSha = '0123456789abcdef0123456789abcdef01234567'
    fs.writeFileSync(artifact, bytes)
    const workflowEnv = {
      ...process.env,
      GITHUB_REPOSITORY: 'Vertex-Systems-Network/workforce-intelligence',
      GITHUB_WORKFLOW: 'Desktop Agent Trusted Release',
      GITHUB_RUN_ID: '123456789',
      GITHUB_RUN_ATTEMPT: '2',
      GITHUB_EVENT_NAME: 'workflow_dispatch',
      GITHUB_REF: 'refs/heads/main',
    }

    const create = spawnSync(process.execPath, [
      receiptTool,
      'create',
      '--artifact', artifact,
      '--output', receipt,
      '--platform', 'Linux',
      '--trust-state', 'HASH_VERIFIED',
      '--source-sha', sourceSha,
      '--release-version', '1.2.3',
      '--unsigned-sha256', sha256(bytes),
      '--verification-method', 'test checksum provenance',
    ], { encoding: 'utf8', env: workflowEnv })
    assert.equal(create.status, 0, create.stderr || create.stdout)

    const expectedArgs = [
      '--receipt', receipt,
      '--artifact-root', root,
      '--expected-source-sha', sourceSha,
      '--expected-release-version', '1.2.3',
      '--expected-repository', workflowEnv.GITHUB_REPOSITORY,
      '--expected-workflow', workflowEnv.GITHUB_WORKFLOW,
      '--expected-run-id', workflowEnv.GITHUB_RUN_ID,
      '--expected-run-attempt', workflowEnv.GITHUB_RUN_ATTEMPT,
      '--expected-event-name', workflowEnv.GITHUB_EVENT_NAME,
      '--expected-ref', workflowEnv.GITHUB_REF,
    ]
    const verify = spawnSync(process.execPath, [receiptTool, 'verify', ...expectedArgs], { encoding: 'utf8' })
    assert.equal(verify.status, 0, verify.stderr || verify.stdout)

    const wrongSourceArgs = [...expectedArgs]
    wrongSourceArgs[wrongSourceArgs.indexOf('--expected-source-sha') + 1] = 'fedcba9876543210fedcba9876543210fedcba98'
    const wrongSource = spawnSync(process.execPath, [receiptTool, 'verify', ...wrongSourceArgs], { encoding: 'utf8' })
    assert.notEqual(wrongSource.status, 0)
    assert.match(wrongSource.stderr, /Receipt source SHA does not match the authorized publication source/)

    const wrongRunArgs = [...expectedArgs]
    wrongRunArgs[wrongRunArgs.indexOf('--expected-run-id') + 1] = '987654321'
    const wrongRun = spawnSync(process.execPath, [receiptTool, 'verify', ...wrongRunArgs], { encoding: 'utf8' })
    assert.notEqual(wrongRun.status, 0)
    assert.match(wrongRun.stderr, /Receipt workflow run id does not match the authorized publication run/)
  } finally {
    fs.rmSync(root, { recursive: true, force: true })
  }
})


test('M14 verifies the published immutable release and every asset as the final publication postcondition', () => {
  for (const token of [
    'verify_published_immutable_release()',
    'gh release verify "$RELEASE_TAG"',
    'gh release verify-asset "$RELEASE_TAG" "$asset"',
    'publish_status=0',
    'gh release edit "$RELEASE_TAG" --draft=false || publish_status=$?',
    'if ! verify_published_immutable_release; then',
    'Release publication command returned non-zero, but the immutable published postcondition is verified.',
  ]) assert.ok(workflow.includes(token), token)

  const expose = workflow.lastIndexOf('gh release edit "$RELEASE_TAG" --draft=false || publish_status=$?')
  const verified = workflow.lastIndexOf('if ! verify_published_immutable_release; then')
  const releaseSuccess = workflow.lastIndexOf('Published and verified immutable trusted release')
  assert.ok(expose > 0 && verified > expose && releaseSuccess > verified, 'immutable release verification must be the final publication postcondition')
})


test('M14 independent immutable-release evidence lane is read-only, main-bound and sanitized', () => {
  for (const token of [
    'workflow_dispatch:',
    'permissions:\n  contents: read',
    'environment: production-release',
    'WORKINTEL_RELEASE_POLICY_READ_TOKEN',
    'repos/${GITHUB_REPOSITORY}/immutable-releases',
    '--method GET',
    "X-GitHub-Api-Version: 2026-03-10",
    "type == \"object\" and .enabled == true",
    'refs/heads/main',
    'Protected main moved before immutable-release evidence collection.',
    'Protected main moved during immutable-release evidence collection.',
    'workintel.m14-immutable-release-evidence.v1',
    'evidence_type: "github-api-live-read"',
    'immutable_releases: { enabled: true }',
    'actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a',
  ]) assert.ok(immutableEvidenceWorkflow.includes(token), token)

  assert.equal(
    immutableEvidenceWorkflow.split('WORKINTEL_RELEASE_POLICY_READ_TOKEN').length - 1,
    1,
    'Administration-read token must be scoped to exactly one evidence step',
  )

  for (const forbidden of [
    'pull_request:',
    'push:',
    'contents: write',
    'id-token: write',
    'self-hosted',
    '--method POST',
    '--method PUT',
    '--method PATCH',
    '--method DELETE',
    'gh release create',
    'gh release edit',
    'git push',
  ]) assert.ok(!immutableEvidenceWorkflow.includes(forbidden), forbidden)
})


test('M14 production-release control evidence lane is read-only, main-bound and excludes signer authority', () => {
  for (const token of [
    'workflow_dispatch:',
    'permissions:\n  contents: read',
    'environment: production-release',
    'WORKINTEL_M14_ADMIN_AUDIT_TOKEN',
    'WORKINTEL_RELEASE_POLICY_READ_TOKEN',
    'repos/${GITHUB_REPOSITORY}/environments/production-release',
    'deployment-branch-policies',
    'environment_secret_names',
    'repository_secret_names',
    'prevent_self_review == true',
    'deployment_branch_policy.protected_branches == false',
    'custom_branch_policies == true',
    'protected_branches == false',
    '["agent-v*", "main"]',
    'workintel.m14-production-release-control-evidence.v1',
    'Protected main moved before production-release evidence collection.',
    'Protected main moved during production-release evidence collection.',
    'actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a',
  ]) assert.ok(productionReleaseControlEvidenceWorkflow.includes(token), token)

  assert.equal(
    productionReleaseControlEvidenceWorkflow.split('WORKINTEL_M14_ADMIN_AUDIT_TOKEN').length - 1,
    3,
    'admin-audit token name should appear only in the evidence secret binding and scope-placement assertions',
  )

  for (const forbidden of [
    'pull_request:',
    'push:',
    'contents: write',
    'id-token: write',
    'self-hosted',
    '--method POST',
    '--method PUT',
    '--method PATCH',
    '--method DELETE',
    'gh release create',
    'gh release edit',
    'git push',
    'WORKINTEL_WINDOWS_SIGNING_PFX_B64: ${{ secrets.',
    'WORKINTEL_APPLE_DEVELOPER_ID_P12_B64: ${{ secrets.',
  ]) assert.ok(!productionReleaseControlEvidenceWorkflow.includes(forbidden), forbidden)

  assert.ok(
    !productionReleaseControlEvidenceWorkflow.includes('select(.type == "branch_policy")'),
    'deployment policy must be validated from deployment_branch_policy plus the policy list, not protection_rules',
  )
})
