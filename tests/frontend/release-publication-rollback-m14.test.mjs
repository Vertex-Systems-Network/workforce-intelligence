import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'

const workflow = fs.readFileSync('.github/workflows/desktop-agent-trusted-release.yml', 'utf8')

test('M14 release cleanup covers partial gh release create failures without deleting unrelated releases', () => {
  const ownerMarker = workflow.indexOf('release_owner_marker="workintel-release-${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT}-$(uuidgen)"')
  const attemptedInit = workflow.indexOf('create_attempted=0', ownerMarker)
  const cleanupHelper = workflow.indexOf('cleanup_owned_draft() {', attemptedInit)
  const cleanup = workflow.indexOf('cleanup() {', cleanupHelper)
  const attemptedCleanup = workflow.indexOf('elif [ "$create_attempted" -eq 1 ]; then', cleanup)
  const releaseNotes = workflow.indexOf('release_notes=', cleanup)
  const markAttempted = workflow.indexOf('create_attempted=1', releaseNotes)
  const createRelease = workflow.indexOf('gh release create "$RELEASE_TAG" trusted-release-assets/*', markAttempted)
  const markCreated = workflow.indexOf('created=1', createRelease)

  assert.ok(ownerMarker > 0, 'unique per-run release ownership marker is required')
  assert.ok(attemptedInit > ownerMarker, 'create-attempt state must start disabled')
  assert.ok(cleanupHelper > attemptedInit, 'owned-draft cleanup helper must be installed before release creation')
  assert.ok(attemptedCleanup > cleanup, 'EXIT cleanup must probe a failed/partial create attempt')
  assert.ok(markAttempted > releaseNotes && markAttempted < createRelease, 'create attempt must be recorded before gh release create can partially succeed')
  assert.ok(markCreated > createRelease, 'confirmed creation remains distinct from an attempted creation')

  assert.ok(workflow.includes("release_is_draft=\"$(gh release view \"$RELEASE_TAG\" --json isDraft --jq '.isDraft' 2>/dev/null || true)\""))
  assert.ok(workflow.includes("release_body=\"$(gh release view \"$RELEASE_TAG\" --json body --jq '.body' 2>/dev/null || true)\""))
  assert.ok(workflow.includes('grep -Fq "<!-- ${release_owner_marker} -->"'))
  assert.ok(workflow.includes('gh release delete "$RELEASE_TAG" --yes || true'))
  assert.ok(!workflow.includes('--clobber'))
})

test('M14 publication binds remote draft asset bytes to the locally verified trusted set before exposure', () => {
  const helper = workflow.indexOf('assert_remote_release_assets_match() {')
  const createRelease = workflow.indexOf('gh release create "$RELEASE_TAG" trusted-release-assets/*')
  const firstRemoteCheck = workflow.indexOf('assert_remote_release_assets_match', createRelease)
  const finalRefCheck = workflow.indexOf('assert_live_release_refs', firstRemoteCheck)
  const finalRemoteCheck = workflow.indexOf('assert_remote_release_assets_match', finalRefCheck)
  const publishRelease = workflow.indexOf('gh release edit "$RELEASE_TAG" --draft=false')

  assert.ok(helper > 0, 'remote release-asset verification helper is required')
  assert.ok(workflow.includes('"repos/${GITHUB_REPOSITORY}/releases/tags/${RELEASE_TAG}"'))
  assert.ok(workflow.includes("String(asset.digest || '').toLowerCase() !== expectedDigest"))
  assert.ok(workflow.includes('Number(asset.size) !== bytes.length'))
  assert.ok(workflow.includes("asset.state !== 'uploaded'"))
  assert.ok(workflow.includes("crypto.createHash('sha256').update(bytes).digest('hex')"))
  assert.ok(firstRemoteCheck > createRelease, 'uploaded draft bytes must be verified after release creation')
  assert.ok(finalRemoteCheck > finalRefCheck && finalRemoteCheck < publishRelease, 'remote bytes must be re-verified immediately before public exposure')
})
