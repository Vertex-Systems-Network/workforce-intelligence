import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'

const read = path => fs.readFileSync(path, 'utf8')

test('browser tracker reads its server from the runtime-bound package and asks only for an enrollment code', () => {
  for (const prefix of ['browser-extension', 'browser-extension/firefox']) {
    const popup = read(`${prefix}/popup.js`)
    const html = read(`${prefix}/popup.html`)
    assert.match(popup, /workintel-server\.txt/)
    assert.match(popup, /ext\.runtime\.getURL\('workintel-server\.txt'\)/)
    assert.match(popup, /const serverUrl = await loadConfiguredServerUrl\(\)/)
    assert.match(popup, /message\('enroll', \{ server_url: serverUrl, enrollment_code: enrollmentCode \}\)/)
    assert.doesNotMatch(html, /id="server"/)
    assert.match(html, /id="configured-server"/)
    assert.match(html, /id="code"/)
    assert.match(html, /enter only your one-time enrollment code/i)
  }
})

test('desktop installers prefer the server binding injected by WorkIntel Downloads and retain explicit URL fallback', () => {
  const windows = read('desktop-agent/installers/windows/install.ps1')
  assert.match(windows, /\[Parameter\(Mandatory=\$false\)\]\[string\]\$ServerUrl/)
  assert.match(windows, /workintel-server\.txt/)
  assert.match(windows, /Get-BoundServerUrl/)
  assert.match(windows, /Download it from WorkIntel Downloads or pass -ServerUrl explicitly/)
  assert.match(windows, /& \$node @nodeTlsArgs \$agentPath enroll \$ServerUrl \$EnrollmentCode/)

  for (const file of ['desktop-agent/installers/macos/install.command', 'desktop-agent/installers/linux/install.sh']) {
    const installer = read(file)
    assert.match(installer, /workintel-server\.txt/)
    assert.match(installer, /\$# -eq 1/)
    assert.match(installer, /\$# -eq 2/)
    assert.match(installer, /Download it from WorkIntel Downloads or pass the server URL explicitly/)
  }
})

test('Windows installer uses the Windows trusted CA store when the installed Node supports it', () => {
  const installer = read('desktop-agent/installers/windows/install.ps1')
  assert.match(installer, /\$nodeHelp = \(& \$node --help \| Out-String\)/)
  assert.match(installer, /\$nodeHelp -match '--use-system-ca'/)
  assert.match(installer, /& \$node @nodeTlsArgs \$agentPath enroll/)
  assert.match(installer, /\$nodeTlsFlag = if \(\$nodeTlsArgs\.Count -gt 0\)/)
  assert.match(installer, /Trusted Root store/)
})

test('runtime deployment bundle verifies canonical bytes and injects only a server origin', () => {
  const service = read('app/Services/Installation/ConfiguredReleaseBundleService.php')
  const controller = read('app/Http/Controllers/Api/V1/ReleaseController.php')
  for (const token of ['hash_file', 'hash_equals', 'PharData', "'workintel-server.txt'", "'desktop-agent/workintel-server.txt'", 'normalizeOrigin']) assert.ok(service.includes(token), token)
  assert.doesNotMatch(service, /enrollment_code|access_token|device_token/i)
  assert.match(controller, /getSchemeAndHttpHost\(\)/)
  assert.match(controller, /X-Canonical-Release-SHA256/)
  assert.match(controller, /X-WorkIntel-Configured-Server/)
  assert.match(controller, /deleteFileAfterSend\(true\)/)
})

test('browser release manifests move together for the server-bound bootstrap change', () => {
  const chrome = JSON.parse(read('browser-extension/manifest.json'))
  const firefox = JSON.parse(read('browser-extension/firefox/manifest.json'))
  assert.equal(chrome.version, '1.0.1')
  assert.equal(firefox.version, chrome.version)
})
