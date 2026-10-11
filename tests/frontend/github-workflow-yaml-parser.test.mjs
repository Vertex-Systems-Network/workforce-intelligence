import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'

const ci = fs.readFileSync('.github/workflows/ci.yml', 'utf8')
const validator = fs.readFileSync('tools/validate-github-workflow-yaml.rb', 'utf8')

test('Issue 157 validates all workflow YAML before dependency installation and browser tests', () => {
  const gate = ci.indexOf('ruby tools/validate-github-workflow-yaml.rb --self-test')
  const install = ci.indexOf('composer install --prefer-dist --no-interaction')
  const browser = ci.indexOf('npm run test:e2e:full')
  assert.ok(gate > 0, 'required Linux CI must execute the workflow parser')
  assert.ok(install > gate, 'workflow parser must run before dependency installation')
  assert.ok(browser > install, 'existing browser certification must remain after the parser')
  assert.ok(ci.includes('npm audit --audit-level=high'))
  assert.ok(ci.includes('composer audit --locked --abandoned=report'))
})

test('Issue 157 Ruby parser audits every workflow and rejects malformed/empty files fail closed', () => {
  for (const marker of [
    "require 'psych'",
    "'.github', 'workflows', '*.{yml,yaml}'",
    'Psych.parse_file(file)',
    "raise Psych::SyntaxError.new(file, 1, 1, 0, 'empty workflow', '')",
    'INVALID',
    'failures.positive?',
    'workflows.empty?',
  ]) assert.ok(validator.includes(marker), 'workflow validator missing ' + marker)
})

test('Issue 157 parser fixture rejects outdented Node heredoc and accepts corrected indentation', () => {
  assert.ok(validator.includes('Psych.parse(malformed)'))
  assert.ok(validator.includes('Psych.parse(corrected)'))
  assert.ok(validator.includes('rescue Psych::SyntaxError'))
  assert.ok(validator.includes('malformed unindented heredoc was accepted'))
  assert.ok(validator.includes('heredoc parser regression passed'))
})
