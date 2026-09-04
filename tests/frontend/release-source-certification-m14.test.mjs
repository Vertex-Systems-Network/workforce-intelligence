import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import test from 'node:test'

const workflow = fs.readFileSync('.github/workflows/desktop-agent-trusted-release.yml', 'utf8')
const verifier = 'tools/verify-release-source-certification.mjs'
const sourceSha = '0123456789abcdef0123456789abcdef01234567'

/** Creates one GitHub workflow-run fixture with secure exact-source defaults. */
function workflowRun(overrides = {}) {
  return {
    id: 100,
    run_number: 10,
    run_attempt: 1,
    name: 'WorkIntel CI',
    path: '.github/workflows/ci.yml',
    event: 'push',
    head_branch: 'main',
    head_sha: sourceSha,
    status: 'completed',
    conclusion: 'success',
    created_at: '2026-09-01T00:00:00Z',
    ...overrides,
  }
}

/** Returns the complete required exact-source certification fixture. */
function certifiedRuns() {
  return [
    workflowRun(),
    workflowRun({
      id: 101,
      name: 'WorkIntel Code Quality',
      path: '.github/workflows/code-quality.yml',
      run_number: 11,
    }),
    workflowRun({
      id: 102,
      name: 'WorkIntel Windows Certification',
      path: '.github/workflows/windows-certification.yml',
      run_number: 12,
    }),
  ]
}

/** Executes the exact-source verifier with one workflow-runs API fixture. */
function verify(runs, sha = sourceSha) {
  return spawnSync(process.execPath, [verifier, '--source-sha', sha], {
    input: JSON.stringify({ total_count: runs.length, workflow_runs: runs }),
    encoding: 'utf8',
  })
}

test('M14 trusted release checks exact-source certification before privileged trust jobs', () => {
  const authorize = workflow.indexOf('\n  authorize:')
  const certification = workflow.indexOf('Require exact-source main certification', authorize)
  const buildTrust = workflow.indexOf('\n  build-and-trust:')

  assert.ok(authorize > 0)
  assert.ok(certification > authorize && certification < buildTrust)
  assert.ok(workflow.includes('actions: read'))
  assert.ok(workflow.includes('GH_TOKEN: ${{ github.token }}'))
  assert.ok(workflow.includes('"repos/${GITHUB_REPOSITORY}/actions/runs"'))
  assert.ok(workflow.includes('-f head_sha="$SOURCE_SHA"'))
  assert.ok(workflow.includes('-f event=push'))
  assert.ok(workflow.includes('verify-release-source-certification.mjs --source-sha "$SOURCE_SHA"'))
})

test('exact-source certification accepts the three required successful main push workflows', () => {
  const result = verify(certifiedRuns())
  assert.equal(result.status, 0, result.stderr || result.stdout)
  const evidence = JSON.parse(result.stdout)
  assert.equal(evidence.source_sha, sourceSha)
  assert.deepEqual(
    evidence.certifications.map(item => item.name),
    ['WorkIntel CI', 'WorkIntel Code Quality', 'WorkIntel Windows Certification'],
  )
})

test('exact-source certification rejects a missing required workflow', () => {
  const result = verify(certifiedRuns().slice(0, 2))
  assert.notEqual(result.status, 0)
  assert.match(result.stderr, /Missing exact-source main push certification: WorkIntel Windows Certification/)
})

test('exact-source certification rejects failed or incomplete newest evidence', () => {
  for (const override of [
    { status: 'completed', conclusion: 'failure' },
    { status: 'in_progress', conclusion: null },
  ]) {
    const runs = certifiedRuns()
    runs.push(workflowRun({
      id: 999,
      run_number: 99,
      created_at: '2026-09-02T00:00:00Z',
      ...override,
    }))
    const result = verify(runs)
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /WorkIntel CI exact-source certification is not successful/)
  }
})

test('exact-source certification rejects wrong event, branch, SHA or workflow path', () => {
  for (const override of [
    { event: 'pull_request' },
    { head_branch: 'feature/not-main' },
    { head_sha: 'fedcba9876543210fedcba9876543210fedcba98' },
    { path: '.github/workflows/renamed-ci.yml' },
  ]) {
    const runs = certifiedRuns()
    runs[0] = workflowRun(override)
    const result = verify(runs)
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /Missing exact-source main push certification: WorkIntel CI/)
  }
})

test('exact-source certification rejects malformed API input and source SHA', () => {
  const malformed = spawnSync(process.execPath, [verifier, '--source-sha', sourceSha], {
    input: JSON.stringify({ workflow_runs: 'not-an-array' }),
    encoding: 'utf8',
  })
  assert.notEqual(malformed.status, 0)
  assert.match(malformed.stderr, /workflow_runs array/)

  const wrongSha = verify(certifiedRuns(), 'not-a-sha')
  assert.notEqual(wrongSha.status, 0)
  assert.match(wrongSha.stderr, /source-sha must be one exact lowercase 40-hex Git commit SHA/)
})
