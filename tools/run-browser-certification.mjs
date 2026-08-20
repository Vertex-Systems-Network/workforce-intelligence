import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { browserInventory, findBrowserExecutable } from './e2e-browser.mjs'

const root = path.resolve(import.meta.dirname, '..')
const requestedMode = ['public', 'full', 'accessibility'].includes(process.argv[2]) ? process.argv[2] : 'public'
const requireSystemBrowsers = process.argv.includes('--require-system-browsers')
const playwrightBin = process.platform === 'win32'
  ? path.join(root, 'node_modules/.bin/playwright.cmd')
  : path.join(root, 'node_modules/.bin/playwright')

if (!fs.existsSync(playwrightBin)) {
  console.error('WorkIntel browser certification requires @playwright/test. Run npm install first.')
  process.exit(1)
}

const inventory = browserInventory()
const missingSystemBrowsers = ['chrome', 'edge', 'firefox'].filter(name => !inventory[name])
if (requireSystemBrowsers && missingSystemBrowsers.length) {
  console.error(`Cross-browser certification requires actual Chrome, Microsoft Edge and Firefox. Missing: ${missingSystemBrowsers.join(', ')}`)
  console.error('Run: node tools/e2e-browser-doctor.mjs --require-all')
  process.exit(1)
}

const installedBrowser = findBrowserExecutable()
if (!installedBrowser) {
  console.warn('No system Chromium-family browser was detected. Playwright-managed Chromium may still satisfy this run if installed.')
}

const mode = requestedMode === 'accessibility' ? 'full' : requestedMode
const profile = requestedMode === 'accessibility' ? 'accessibility' : 'standard'
console.log(`WorkIntel browser certification mode: ${requestedMode}`)
console.log(`Profile: ${profile}`)
console.log(`Primary Chromium-family browser: ${installedBrowser ?? 'Playwright-managed build'}`)
console.log(`Chrome: ${inventory.chrome ?? 'not detected'}`)
console.log(`Edge: ${inventory.edge ?? 'not detected'}`)
console.log(`Firefox system install: ${inventory.firefox ?? 'not detected'} (Playwright Firefox engine is used by the suite)`)

const result = spawnSync(playwrightBin, ['test', '--config=tools/playwright.config.mjs'], {
  cwd: root,
  stdio: 'inherit',
  env: {
    ...process.env,
    WORKINTEL_E2E_MODE: mode,
    WORKINTEL_E2E_PROFILE: profile,
    ...(installedBrowser && profile === 'standard' ? { WORKINTEL_E2E_BROWSER_EXECUTABLE: installedBrowser } : {}),
  },
})
process.exit(result.status ?? 1)
