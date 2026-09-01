import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import test from 'node:test'

const workflow = fs.readFileSync('.github/workflows/desktop-agent-trusted-release.yml', 'utf8')
const verifier = 'tools/verify-release-tag-protection.mjs'

function ruleset(overrides = {}) {
  return {
    id: 700,
    name: 'immutable agent release tags',
    target: 'tag',
    source_type: 'Repository',
    enforcement: 'active',
    bypass_actors: [],
    conditions: {
      ref_name: {
        include: ['refs/tags/agent-v*'],
        exclude: [],
      },
    },
    rules: [{ type: 'update' }, { type: 'deletion' }],
    ...overrides,
  }
}

function verify(rulesets, tag = 'agent-v1.2.3') {
  return spawnSync(process.execPath, [verifier, '--tag', tag], {
    input: JSON.stringify(rulesets),
    encoding: 'utf8',
  })
}

test('M14 trusted tag protection is proven before privileged release jobs', () => {
  const authorize = workflow.indexOf('\n  authorize:')
  const sourceCertification = workflow.indexOf('Require exact-source main certification', authorize)
  const tagProtection = workflow.indexOf('Require immutable release tag protections', sourceCertification)
  const buildTrust = workflow.indexOf('\n  build-and-trust:')

  assert.ok(authorize > 0)
  assert.ok(sourceCertification > authorize)
  assert.ok(tagProtection > sourceCertification && tagProtection < buildTrust)
  assert.ok(workflow.includes("if: github.event_name == 'push'"))
  assert.ok(workflow.includes('targets=tag'))
  assert.ok(workflow.includes('includes_parents=true'))
  assert.ok(workflow.includes('verify-release-tag-protection.mjs --tag "$RELEASE_TAG"'))
  assert.ok(workflow.includes('assert_immutable_release_tag_rules() {'))
})

test('accepts active no-bypass tag ruleset with update and deletion restrictions', () => {
  const result = verify([ruleset()])
  assert.equal(result.status, 0, result.stderr || result.stdout)
  const evidence = JSON.parse(result.stdout)
  assert.equal(evidence.ref, 'refs/tags/agent-v1.2.3')
  assert.equal(evidence.protections.update_restricted, true)
  assert.equal(evidence.protections.deletion_restricted, true)
  assert.equal(evidence.protections.bypass_actors, 0)
})

test('accepts restrictions composed across multiple applicable no-bypass rulesets', () => {
  const updateOnly = ruleset({ id: 701, rules: [{ type: 'update' }] })
  const deleteOnly = ruleset({ id: 702, rules: [{ type: 'deletion' }] })
  const result = verify([updateOnly, deleteOnly])
  assert.equal(result.status, 0, result.stderr || result.stdout)
})

test('rejects missing, inactive, wrong-target, excluded and nonmatching tag protection', () => {
  const cases = [
    [],
    [ruleset({ enforcement: 'evaluate' })],
    [ruleset({ target: 'branch' })],
    [ruleset({ conditions: { ref_name: { include: ['refs/tags/agent-v*'], exclude: ['refs/tags/agent-v1.2.3'] } } })],
    [ruleset({ conditions: { ref_name: { include: ['refs/tags/browser-v*'], exclude: [] } } })],
  ]
  for (const fixture of cases) {
    const result = verify(fixture)
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /No active no-bypass tag ruleset applies/)
  }
})

test('rejects missing update or deletion restriction', () => {
  for (const rules of [[{ type: 'update' }], [{ type: 'deletion' }]]) {
    const result = verify([ruleset({ rules })])
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /do not enforce (update|deletion) restriction/)
  }
})

test('rejects bypass actors and hidden bypass evidence', () => {
  const withBypass = verify([ruleset({ bypass_actors: [{ actor_id: 1, actor_type: 'Team', bypass_mode: 'always' }] })])
  assert.notEqual(withBypass.status, 0)
  assert.match(withBypass.stderr, /permits bypass actors/)

  const hidden = ruleset()
  delete hidden.bypass_actors
  const hiddenResult = verify([hidden])
  assert.notEqual(hiddenResult.status, 0)
  assert.match(hiddenResult.stderr, /does not expose bypass_actors/)
})

test('accepts ~ALL include and rejects malformed input or tag', () => {
  const all = ruleset({ conditions: { ref_name: { include: ['~ALL'], exclude: [] } } })
  assert.equal(verify([all]).status, 0)

  const malformed = spawnSync(process.execPath, [verifier, '--tag', 'agent-v1.2.3'], {
    input: JSON.stringify({ rulesets: [] }),
    encoding: 'utf8',
  })
  assert.notEqual(malformed.status, 0)
  assert.match(malformed.stderr, /JSON array/)

  const wrongTag = verify([ruleset()], 'not-an-agent-tag')
  assert.notEqual(wrongTag.status, 0)
  assert.match(wrongTag.stderr, /agent-v<numeric-version>/)
})
