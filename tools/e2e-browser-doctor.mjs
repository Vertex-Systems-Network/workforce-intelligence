import { browserInventory, findBrowserExecutable } from './e2e-browser.mjs'

const requireAll = process.argv.includes('--require-all')
const inventory = browserInventory()
const primary = findBrowserExecutable()
const missing = ['chrome', 'edge', 'firefox'].filter(name => !inventory[name])

if (!primary && !requireAll) {
  console.error('WorkIntel browser doctor: FAIL')
  console.error('No Chrome, Edge or Chromium executable was found. Install one, set WORKINTEL_E2E_BROWSER_EXECUTABLE, or run npx playwright install chromium.')
  process.exit(1)
}
if (requireAll && missing.length) {
  console.error('WorkIntel cross-browser doctor: FAIL')
  console.error(`Missing required system browsers: ${missing.join(', ')}`)
  console.error('Block N final workstation certification requires actual Chrome, Microsoft Edge and Mozilla Firefox.')
  process.exit(1)
}

console.log(`WorkIntel ${requireAll ? 'cross-browser' : 'browser'} doctor: PASS`)
for (const [name, executable] of Object.entries(inventory)) console.log(`${name}: ${executable ?? 'not detected'}`)
if (!requireAll && !inventory.firefox) console.log('Note: Firefox is optional for the normal profile; Block N cross-browser certification requires it.')
