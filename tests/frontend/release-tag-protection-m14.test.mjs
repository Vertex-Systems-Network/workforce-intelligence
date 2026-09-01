import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import test from 'node:test'

const workflow = fs.readFileSync('.github/workflows/desktop-agent-trusted-release.yml', 'utf8')
const verifier = 'tools/verify-release-tag-protection.mjs'
const canonicalAttestationPath = 'docs/operations/M14_RELEASE_TAG_RULESET_ATTESTATION.json'
const UPDATED_AT = '2026-09-02T00:00:00Z'

function ruleset(overrides = {}) {
  return {
    id: 700,
    name: 'immutable agent release tags',
    target: 'tag',
    source_type: 'Repository',
    enforcement: 'active',
    updated_at: UPDATED_AT,
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

function attestation(overrides = {}) {
  return {
    schema: 'workintel.release-tag-ruleset-attestation.v1',
    status: 'VERIFIED',
    ruleset_id: 700,
    ruleset_updated_at: UPDATED_AT,
    no_bypass_actors_attested: true,
    audited_by: 'release-admin@example.test',
    audited_at: '2026-09-02T00:05:00Z',
    ...overrides,
  }
}

function verify(rulesets, tag = 'agent-v1.2.3', audit = attestation()) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'm14-tag-audit-'))
  const auditPath = path.join(dir, 'attestation.json')
  fs.writeFileSync(auditPath, JSON.stringify(audit))
  try {
    return spawnSync(process.execPath, [verifier, '--tag', tag, '--attestation', auditPath], {
      input: JSON.stringify(rulesets),
      encoding: 'utf8',
    })
  } finally {
    fs.rmSync(dir, { recursive: true, force: true })
  }
}

test('M14 trusted tag protection stays unprivileged and runs before release authority', () => {
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
  assert.ok(workflow.includes('GH_TOKEN: ${{ github.token }}'))
  assert.ok(workflow.includes('verify-release-tag-protection.mjs --tag "$RELEASE_TAG"'))
  assert.ok(workflow.includes('assert_immutable_release_tag_rules() {'))
  assert.equal(/RULESET.*TOKEN|ADMIN.*TOKEN|PAT.*TOKEN/i.test(workflow), false)
})

test('canonical attestation remains fail-closed until administrator evidence is recorded', () => {
  const canonical = JSON.parse(fs.readFileSync(canonicalAttestationPath, 'utf8'))
  assert.equal(canonical.schema, 'workintel.release-tag-ruleset-attestation.v1')
  assert.equal(canonical.status, 'NOT_CONFIGURED')
  assert.equal(canonical.no_bypass_actors_attested, false)
})

test('accepts hidden bypass field when exact immutable snapshot is externally attested', () => {
  const result = verify([ruleset()])
  assert.equal(result.status, 0, result.stderr || result.stdout)
  const evidence = JSON.parse(result.stdout)
  assert.equal(evidence.ruleset.id, 700)
  assert.equal(evidence.ruleset.updated_at, UPDATED_AT)
  assert.equal(evidence.protections.update_restricted, true)
  assert.equal(evidence.protections.deletion_restricted, true)
  assert.equal(evidence.protections.no_bypass_actors, true)
  assert.equal(evidence.protections.bypass_evidence, 'attested-snapshot')
})

test('accepts visible empty bypass actors and records API plus snapshot evidence', () => {
  const result = verify([ruleset({ bypass_actors: [] })])
  assert.equal(result.status, 0, result.stderr || result.stdout)
  assert.equal(JSON.parse(result.stdout).protections.bypass_evidence, 'api-plus-attested-snapshot')
})

test('rejects visible bypass actors even when snapshot claims none', () => {
  const result = verify([ruleset({ bypass_actors: [{ actor_id: 1, actor_type: 'Team', bypass_mode: 'always' }] })])
  assert.notEqual(result.status, 0)
  assert.match(result.stderr, /currently exposes bypass actors/)
})

test('rejects absent or wrong attested ruleset identity', () => {
  const missing = verify([])
  assert.notEqual(missing.status, 0)
  assert.match(missing.stderr, /Attested ruleset 700 was not returned/)

  const wrongId = verify([ruleset({ id: 701 })])
  assert.notEqual(wrongId.status, 0)
  assert.match(wrongId.stderr, /Attested ruleset 700 was not returned/)
})

test('rejects changed snapshot timestamp', () => {
  const result = verify([ruleset({ updated_at: '2026-09-02T00:10:00Z' })])
  assert.notEqual(result.status, 0)
  assert.match(result.stderr, /updated_at changed from attested snapshot/)
})

test('rejects inactive, wrong-target, excluded and nonmatching rulesets', () => {
  const cases = [
    [ruleset({ enforcement: 'evaluate' }), /not active/],
    [ruleset({ target: 'branch' }), /does not target tags/],
    [ruleset({ conditions: { ref_name: { include: ['refs/tags/agent-v*'], exclude: ['refs/tags/agent-v1.2.3'] } } }), /does not apply/],
    [ruleset({ conditions: { ref_name: { include: ['refs/tags/browser-v*'], exclude: [] } } }), /does not apply/],
  ]
  for (const [fixture, pattern] of cases) {
    const result = verify([fixture])
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, pattern)
  }
})

test('rejects missing update or deletion restriction in the attested ruleset', () => {
  for (const rules of [[{ type: 'update' }], [{ type: 'deletion' }]]) {
    const result = verify([ruleset({ rules })])
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /does not enforce (update|deletion) restriction/)
  }
})

test('rejects unverified, non-no-bypass, wrong-ID and stale attestation', () => {
  const cases = [
    [attestation({ status: 'NOT_CONFIGURED' }), /status must be VERIFIED/],
    [attestation({ no_bypass_actors_attested: false }), /explicitly attest no bypass actors/],
    [attestation({ ruleset_id: 701 }), /Attested ruleset 701 was not returned/],
    [attestation({ ruleset_updated_at: '2026-09-01T23:00:00Z' }), /updated_at changed from attested snapshot/],
    [attestation({ audited_by: '' }), /audited_by is required/],
    [attestation({ audited_at: 'not-a-date' }), /audited_at must be an ISO-8601 timestamp/],
  ]
  for (const [audit, pattern] of cases) {
    const result = verify([ruleset()], 'agent-v1.2.3', audit)
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, pattern)
  }
})

test('accepts ~ALL include and rejects malformed input or tag', () => {
  assert.equal(verify([ruleset({ conditions: { ref_name: { include: ['~ALL'], exclude: [] } } })]).status, 0)

  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'm14-tag-audit-'))
  const auditPath = path.join(dir, 'attestation.json')
  fs.writeFileSync(auditPath, JSON.stringify(attestation()))
  try {
    const malformed = spawnSync(process.execPath, [verifier, '--tag', 'agent-v1.2.3', '--attestation', auditPath], {
      input: JSON.stringify({ rulesets: [] }),
      encoding: 'utf8',
    })
    assert.notEqual(malformed.status, 0)
    assert.match(malformed.stderr, /JSON array/)

    const wrongTag = spawnSync(process.execPath, [verifier, '--tag', 'not-an-agent-tag', '--attestation', auditPath], {
      input: JSON.stringify([ruleset()]),
      encoding: 'utf8',
    })
    assert.notEqual(wrongTag.status, 0)
    assert.match(wrongTag.stderr, /agent-v<numeric-version>/)
  } finally {
    fs.rmSync(dir, { recursive: true, force: true })
  }
})
