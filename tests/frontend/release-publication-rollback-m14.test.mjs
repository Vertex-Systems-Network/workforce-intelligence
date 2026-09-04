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
