import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'

const files = [
  'docs/operations/M14_WINDOWS_PROVIDER_CONTACT_SEND_PATHS.md',
  'docs/operations/M14_WINDOWS_PROVIDER_COMPATIBILITY_AUDIT.md',
  'docs/operations/M14_WINDOWS_PROVIDER_QUALIFICATION_INQUIRIES.md',
  'docs/operations/M14_SIGNER_CREDENTIAL_ACQUISITION_CHECKLIST.md',
]

test('four historical signer/provider playbooks put current owner authority before operational steps', () => {
  for (const path of files) {
    const source = fs.readFileSync(path, 'utf8')
    const titleEnd = source.indexOf('\n')
    const current = source.indexOf('## Current owner authority — 2026-10-12')
    assert.ok(current > titleEnd && current < 150, path + ': authority banner not first')
    for (const marker of [
      'DEFERRED — NOT CURRENTLY AUTHORIZED',
      '2026-10-08',
      'Issue #123',
      'Issue #62',
      'Do **not** resend September provider inquiries',
      'Not Verified',
      '70% WAITING_EXTERNAL',
      'trusted publication must fail closed',
    ]) assert.ok(source.includes(marker), path + ': missing ' + marker)
  }
})

test('historical future instructions and critical publication protections are retained', () => {
  const contact = fs.readFileSync(files[0], 'utf8')
  const compatibility = fs.readFileSync(files[1], 'utf8')
  const inquiries = fs.readFileSync(files[2], 'utf8')
  const credentials = fs.readFileSync(files[3], 'utf8')
  assert.ok(contact.includes('Recommended SSL.com send path'))
  assert.ok(compatibility.includes('Recommended provider qualification sequence'))
  assert.ok(inquiries.includes('Handling provider replies'))
  assert.ok(credentials.includes('Non-negotiable identity rule'))
  assert.ok(credentials.includes('## Apple — acquisition checklist'))
})
