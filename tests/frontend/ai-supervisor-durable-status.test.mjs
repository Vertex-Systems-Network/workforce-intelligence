import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'
import { assertDurableProgressStatus } from '../../tools/ai-supervisor-durable-status.mjs'

const stable = {
  current_work: 'M14 admin evidence remains Not Verified; safe CI maintenance continues independently.',
  last_completed_milestone: 'The workflow YAML guard merged after six exact-head green checks.',
  exact_next_safe_action: 'Select evidence-backed safe maintenance from current GitHub issues and preserve privileged release gates.',
}

test('durable progress fields permit historical facts and a merge-stable next action', () => {
  assert.doesNotThrow(() => assertDurableProgressStatus(stable))
  const state = JSON.parse(fs.readFileSync('docs/ai-state/CURRENT-STATE.yaml', 'utf8'))
  assert.doesNotThrow(() => assertDurableProgressStatus({
    current_work: state.response_status.current_work,
    last_completed_milestone: state.last_completed_milestone,
    exact_next_safe_action: state.exact_next_safe_action,
  }))
})

test('durable progress guard rejects active PR, exact-head and resulting-main check transactions', () => {
  const cases = [
    'Certify PR #159 before merge',
    'Awaiting current PR Windows checks',
    'Under exact-head certification',
    'Verify resulting-main CI',
    'Pending post-merge checks',
    'Certifying exact-head quality gates',
  ]
  for (const transient of cases) {
    assert.throws(
      () => assertDurableProgressStatus({...stable, exact_next_safe_action: transient}),
      /durable progress field exact_next_safe_action contains/,
      transient,
    )
  }
})

test('durable guard validates all three status fields and fails closed on empty values', () => {
  for (const field of Object.keys(stable)) {
    assert.throws(() => assertDurableProgressStatus({...stable, [field]: 'PR #42 requires merge'}), new RegExp(field))
    assert.throws(() => assertDurableProgressStatus({...stable, [field]: ''}), new RegExp(field))
  }
  const audit = fs.readFileSync('tools/ai-supervisor-state-audit.mjs', 'utf8')
  assert.ok(audit.includes('assertDurableProgressStatus({current_work:'))
  assert.ok(audit.includes("from './ai-supervisor-durable-status.mjs'"))
})
