import { defineConfig } from '@playwright/test'
import path from 'node:path'
import { browserInventory, findBrowserExecutable } from './e2e-browser.mjs'

const root = path.resolve(import.meta.dirname, '..')
const inventory = browserInventory()
const browserExecutable = findBrowserExecutable()
const baseURL = process.env.WORKINTEL_E2E_BASE_URL || 'http://127.0.0.1:8777'
const profile = process.env.WORKINTEL_E2E_PROFILE || 'standard'
const statefulDomains = Array.from(new Set([
  ...(process.env.SANCTUM_STATEFUL_DOMAINS || '').split(',').map(value => value.trim()).filter(Boolean),
  new URL(baseURL).host,
])).join(',')

/** Build a Chromium-family project with an optional branded system browser executable. */
function chromiumProject(name, viewport, executablePath, extraUse = {}) {
  return {
    name,
    use: {
      browserName: 'chromium',
      viewport,
      launchOptions: executablePath ? { executablePath } : undefined,
      ...extraUse,
    },
  }
}

/** Standard certification remains fast and runs the three responsive viewports on one Chromium-family browser. */
const standardProjects = [
  chromiumProject('desktop', { width: 1440, height: 900 }, browserExecutable),
  chromiumProject('tablet', { width: 1024, height: 768 }, browserExecutable, { hasTouch: true }),
  chromiumProject('mobile', { width: 390, height: 844 }, browserExecutable, { hasTouch: true, isMobile: true }),
]

/** Accessibility certification expands the matrix across Chrome/Chromium, Edge, Firefox and touch/reflow viewports. */
const accessibilityProjects = [
  inventory.chrome
    ? chromiumProject('chrome-desktop', { width: 1440, height: 900 }, inventory.chrome)
    : chromiumProject('chromium-desktop', { width: 1440, height: 900 }, inventory.chromium),
  ...(inventory.edge ? [chromiumProject('edge-desktop', { width: 1440, height: 900 }, inventory.edge)] : []),
  {
    name: 'firefox-desktop',
    use: {
      browserName: 'firefox',
      viewport: { width: 1440, height: 900 },
    },
  },
  chromiumProject('reflow-200pct-equivalent', { width: 640, height: 720 }, inventory.chrome ?? inventory.chromium),
  chromiumProject('touch-tablet', { width: 1024, height: 768 }, inventory.chrome ?? inventory.chromium, { hasTouch: true }),
  chromiumProject('touch-mobile', { width: 390, height: 844 }, inventory.chrome ?? inventory.chromium, { hasTouch: true, isMobile: true }),
]

export default defineConfig({
  testDir: path.join(root, 'tests/e2e'),
  timeout: 45_000,
  expect: { timeout: 8_000 },
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [['list']],
  outputDir: path.join(root, 'storage/app/private/e2e-results'),
  use: {
    baseURL,
    headless: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=8777',
    cwd: root,
    url: `${baseURL}/health/live`,
    reuseExistingServer: false,
    timeout: 45_000,
    stdout: 'pipe',
    stderr: 'pipe',
    env: {
      SANCTUM_STATEFUL_DOMAINS: statefulDomains,
    },
  },
  projects: profile === 'accessibility' ? accessibilityProjects : standardProjects,
})