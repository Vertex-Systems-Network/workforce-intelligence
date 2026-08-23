import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'

const read = path => fs.readFileSync(path, 'utf8')

test('browser tracker treats the server input as a base URL and normalizes enrollment endpoints', () => {
  for (const prefix of ['browser-extension', 'browser-extension/firefox']) {
    const popup = read(`${prefix}/popup.js`)
    const html = read(`${prefix}/popup.html`)
    assert.match(popup, /function normalizeServerUrl\(value\)/)
    assert.match(popup, /\/api\/v1\/agent\/enroll/)
    assert.match(popup, /\/api\/v1\/browser\/enroll/)
    assert.match(popup, /return url\.origin/)
    assert.match(popup, /const serverUrl = normalizeServerUrl\(\$\('server'\)\.value\)/)
    assert.match(html, /Workforce server base URL/)
  }
})

test('Windows installer normalizes displayed enrollment endpoints before invoking the native agent', () => {
  const installer = read('desktop-agent/installers/windows/install.ps1')
  assert.match(installer, /function Normalize-ServerUrl/)
  assert.match(installer, /\/api\/v1\/agent\/enroll/)
  assert.match(installer, /\/api\/v1\/browser\/enroll/)
  assert.match(installer, /GetLeftPart\(\[System\.UriPartial\]::Authority\)/)
  assert.match(installer, /\$ServerUrl = Normalize-ServerUrl \$ServerUrl/)
  assert.match(installer, /native-agent\.mjs' \$agentPath -Force/)
})

test('Windows installer uses the Windows trusted CA store when the installed Node supports it', () => {
  const installer = read('desktop-agent/installers/windows/install.ps1')
  assert.match(installer, /\$nodeHelp = \(& \$node --help \| Out-String\)/)
  assert.match(installer, /\$nodeHelp -match '--use-system-ca'/)
  assert.match(installer, /& \$node @nodeTlsArgs \$agentPath enroll/)
  assert.match(installer, /\$nodeTlsFlag = if \(\$nodeTlsArgs\.Count -gt 0\)/)
  assert.match(installer, /Trusted Root store/)
})
