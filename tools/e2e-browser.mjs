import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

/** Return the first existing executable from a platform-specific candidate list. */
function firstExisting(candidates) {
  return candidates.filter(Boolean).find(candidate => fs.existsSync(candidate)) ?? null
}

/** Return Chrome when installed as a system browser rather than a Playwright-managed Chromium build. */
export function findChromeExecutable() {
  return firstExisting([
    process.platform === 'win32' ? path.join(process.env.PROGRAMFILES ?? '', 'Google/Chrome/Application/chrome.exe') : '',
    process.platform === 'win32' ? path.join(process.env['PROGRAMFILES(X86)'] ?? '', 'Google/Chrome/Application/chrome.exe') : '',
    process.platform === 'win32' ? path.join(process.env.LOCALAPPDATA ?? '', 'Google/Chrome/Application/chrome.exe') : '',
    process.platform === 'darwin' ? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' : '',
    process.platform === 'linux' ? '/usr/bin/google-chrome' : '',
    process.platform === 'linux' ? '/usr/bin/google-chrome-stable' : '',
  ])
}

/** Return Microsoft Edge when installed on the current workstation. */
export function findEdgeExecutable() {
  return firstExisting([
    process.platform === 'win32' ? path.join(process.env['PROGRAMFILES(X86)'] ?? '', 'Microsoft/Edge/Application/msedge.exe') : '',
    process.platform === 'win32' ? path.join(process.env.PROGRAMFILES ?? '', 'Microsoft/Edge/Application/msedge.exe') : '',
    process.platform === 'darwin' ? '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge' : '',
    process.platform === 'linux' ? '/usr/bin/microsoft-edge' : '',
    process.platform === 'linux' ? '/usr/bin/microsoft-edge-stable' : '',
  ])
}

/** Return Mozilla Firefox from an explicitly certified binary or a standard workstation installation. */
export function findFirefoxExecutable() {
  const explicit = process.env.WORKINTEL_E2E_FIREFOX_EXECUTABLE?.trim()
  return firstExisting([
    explicit,
    process.platform === 'win32' ? path.join(process.env.PROGRAMFILES ?? '', 'Mozilla Firefox/firefox.exe') : '',
    process.platform === 'win32' ? path.join(process.env['PROGRAMFILES(X86)'] ?? '', 'Mozilla Firefox/firefox.exe') : '',
    process.platform === 'darwin' ? '/Applications/Firefox.app/Contents/MacOS/firefox' : '',
    process.platform === 'linux' ? '/usr/bin/firefox' : '',
    process.platform === 'linux' ? '/usr/bin/firefox-esr' : '',
  ])
}

/** Return an installed Chromium executable suitable for the normal fast certification profile. */
export function findChromiumExecutable() {
  const explicit = process.env.WORKINTEL_E2E_BROWSER_EXECUTABLE?.trim()
  const home = os.homedir()
  return firstExisting([
    explicit,
    process.platform === 'linux' ? '/usr/bin/chromium' : '',
    process.platform === 'linux' ? '/usr/bin/chromium-browser' : '',
    home ? path.join(home, '.cache/ms-playwright/chromium/chrome-linux/chrome') : '',
    home ? path.join(home, '.cache/ms-playwright/chromium_headless_shell/chrome-linux/headless_shell') : '',
  ])
}

/** Report system/provisioned browser paths used by Block N Chrome/Edge/Firefox certification. */
export function browserInventory() {
  return {
    chrome: findChromeExecutable(),
    edge: findEdgeExecutable(),
    firefox: findFirefoxExecutable(),
    chromium: findChromiumExecutable(),
  }
}

/** Preserve the historic helper while preferring Chrome, then Edge, then Chromium for normal E2E runs. */
export function findBrowserExecutable() {
  const explicit = process.env.WORKINTEL_E2E_BROWSER_EXECUTABLE?.trim()
  if (explicit && fs.existsSync(explicit)) return explicit
  return findChromeExecutable() ?? findEdgeExecutable() ?? findChromiumExecutable()
}
