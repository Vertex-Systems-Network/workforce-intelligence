import { expect, test } from '@playwright/test'

const fullMode = process.env.WORKINTEL_E2E_MODE === 'full'
test.skip(!fullMode, 'Large-screen workspace certification runs only in full certification mode.')

/** Authenticate using the seeded owner certification account. */
async function login(page) {
  const email = process.env.WORKINTEL_E2E_EMAIL || 'owner@acme.test'
  const password = process.env.WORKINTEL_E2E_PASSWORD || 'password'
  await page.goto('/app')
  await page.getByRole('textbox', { name: /email/i }).fill(email)
  await page.locator('input[type="password"]').first().fill(password)
  await page.getByRole('button', { name: /^sign in/i }).click()
  await expect(page.locator('.ui-sidebar')).toBeVisible({ timeout: 15_000 })
}

/** Capture the geometry and representative operational typography for one private-shell surface. */
async function shellMetrics(page, surfaceSelector) {
  return await page.evaluate(selector => {
    const shell = document.querySelector('.ui-app-shell')
    const content = document.querySelector('.ui-app-shell__content')
    const main = document.querySelector('#workintel-main')
    const surface = document.querySelector(selector)
    if (!shell || !content || !main || !surface) throw new Error(`Missing large-screen surface ${selector}`)
    const rect = element => {
      const value = element.getBoundingClientRect()
      return { x: value.x, y: value.y, width: value.width, height: value.height, right: value.right, bottom: value.bottom }
    }
    const font = selectorValue => {
      const element = document.querySelector(selectorValue)
      return element ? Number.parseFloat(getComputedStyle(element).fontSize) : null
    }
    return {
      innerWidth: window.innerWidth,
      scrollWidth: document.documentElement.scrollWidth,
      shell: rect(shell),
      content: rect(content),
      main: rect(main),
      surface: rect(surface),
      fonts: {
        nav: font('.ui-nav-item'),
        pageDescription: font('.ui-page-description'),
        cardDescription: font('.ui-card-description'),
        label: font('.ui-label'),
        input: font('.ui-input'),
        tableHead: font('.ui-table th'),
        tableCell: font('.ui-table td'),
      },
    }
  }, surfaceSelector)
}

/** Assert the shell reaches the viewport edge and does not collapse into an accidental fixed-width island. */
function expectFullWidth(metrics) {
  expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.innerWidth + 2)
  expect(Math.abs(metrics.shell.x)).toBeLessThanOrEqual(2)
  expect(Math.abs(metrics.shell.right - metrics.innerWidth)).toBeLessThanOrEqual(2)
  expect(Math.abs(metrics.content.right - metrics.innerWidth)).toBeLessThanOrEqual(2)
  expect(Math.abs(metrics.main.right - metrics.innerWidth)).toBeLessThanOrEqual(2)
  expect(Math.abs(metrics.surface.right - metrics.innerWidth)).toBeLessThanOrEqual(2)
  expect(metrics.surface.width).toBeGreaterThan(metrics.content.width * 0.98)
}

/** Assert representative working text is not microcopy. */
function expectReadable(metrics) {
  if (metrics.fonts.nav !== null) expect(metrics.fonts.nav).toBeGreaterThanOrEqual(13)
  if (metrics.fonts.pageDescription !== null) expect(metrics.fonts.pageDescription).toBeGreaterThanOrEqual(13)
  if (metrics.fonts.cardDescription !== null) expect(metrics.fonts.cardDescription).toBeGreaterThanOrEqual(12.5)
  if (metrics.fonts.label !== null) expect(metrics.fonts.label).toBeGreaterThanOrEqual(13)
  if (metrics.fonts.input !== null) expect(metrics.fonts.input).toBeGreaterThanOrEqual(14)
  if (metrics.fonts.tableHead !== null) expect(metrics.fonts.tableHead).toBeGreaterThanOrEqual(12)
  if (metrics.fonts.tableCell !== null) expect(metrics.fonts.tableCell).toBeGreaterThanOrEqual(13)
}

test('Settings and Enterprise stay full-width and readable from 1280 through 4K', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop', 'The large-screen sweep runs once on the standard desktop browser project.')

  await page.setViewportSize({ width: 1280, height: 800 })
  await login(page)

  const viewports = [
    { width: 1280, height: 800 },
    { width: 1440, height: 900 },
    { width: 1920, height: 1080 },
    { width: 2560, height: 1440 },
    { width: 3840, height: 2160 },
  ]

  for (const viewport of viewports) {
    await page.setViewportSize(viewport)

    await page.goto('/app#settings')
    await expect(page.getByRole('heading', { name: /general workspace settings/i })).toBeVisible({ timeout: 15_000 })
    const settings = await shellMetrics(page, '.settings-center-layout')
    expectFullWidth(settings)
    expectReadable(settings)

    await page.goto('/app#enterprise')
    await expect(page.getByRole('heading', { name: /enterprise identity & governance/i })).toBeVisible({ timeout: 15_000 })
    const enterprise = await shellMetrics(page, '.ui-page')
    expectFullWidth(enterprise)
    expectReadable(enterprise)
  }
})
