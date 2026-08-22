import { expect, test } from '@playwright/test'

const fullMode = process.env.WORKINTEL_E2E_MODE === 'full'
test.skip(!fullMode, 'Authenticated platform journeys run only in full certification mode.')

/** Authenticate using a seeded or explicitly supplied certification account. */
async function login(page) {
  const email = process.env.WORKINTEL_E2E_EMAIL || 'owner@acme.test'
  const password = process.env.WORKINTEL_E2E_PASSWORD || 'password'
  await page.goto('/app')
  const emailInput = page.getByRole('textbox', { name: /email/i })
  await emailInput.fill(email)
  const passwordInput = page.locator('input[type="password"]').first()
  await passwordInput.fill(password)
  await page.getByRole('button', { name: /^sign in/i }).click()
  await expect(page.locator('.ui-sidebar')).toBeVisible({ timeout: 15_000 })
}

/** Assert horizontal layout remains bounded to the viewport. */
async function expectNoViewportOverflow(page) {
  const dimensions = await page.evaluate(() => ({ width: window.innerWidth, scrollWidth: document.documentElement.scrollWidth }))
  expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.width + 2)
}

test('workspace clicks stay synchronized through browser Back and Forward', async ({ page }) => {
  await login(page)
  const liveTeam = page.getByRole('button', { name: /^Live Team$/i })
  await expect(liveTeam).toBeVisible()
  await liveTeam.click()
  await expect(page).toHaveURL(/#live$/)
  await expect(page.getByRole('heading', { name: /live workforce/i })).toBeVisible()

  const home = page.getByRole('button', { name: /^Home$/i })
  await expect(home).toBeVisible()
  await home.click()
  await expect(page).toHaveURL(/#overview$/)

  await page.goBack()
  await expect(page).toHaveURL(/#live$/)
  await expect(page.getByRole('heading', { name: /live workforce/i })).toBeVisible()

  await page.goForward()
  await expect(page).toHaveURL(/#overview$/)
  await expect(page.locator('#workintel-main')).toBeVisible()
})

test('workspace dropdown remains open through scroll and action menus portal outside tables', async ({ page }) => {
  await login(page)
  const workspaceTrigger = page.locator('.ui-topbar .ui-dropdown-anchor').first()
  await workspaceTrigger.click()
  const menu = page.getByRole('menu').first()
  await expect(menu).toBeVisible()
  await page.mouse.wheel(0, 180)
  await expect(menu).toBeVisible()

  const people = page.getByRole('button', { name: /^People$/i })
  if (await people.count()) {
    await people.click()
    const action = page.getByRole('button', { name: /^Actions for /i }).first()
    if (await action.count()) {
      await action.click()
      const actionMenu = page.getByRole('menu').last()
      await expect(actionMenu).toBeVisible()
      const box = await actionMenu.boundingBox()
      expect(box).not.toBeNull()
      if (box) {
        expect(box.y).toBeGreaterThanOrEqual(0)
        expect(box.y + box.height).toBeLessThanOrEqual((await page.evaluate(() => innerHeight)) + 2)
      }
    }
  }
})

test('navigation is stable through repeated language switching and RTL does not overflow', async ({ page }) => {
  await login(page)
  const language = page.getByRole('button', { name: /language/i })
  if (!(await language.count())) return
  const startingNavCount = await page.locator('.ui-nav-item').count()

  for (const target of ['Türkçe', 'العربية', 'اردو', 'Русский', 'English']) {
    await language.click()
    const option = page.getByRole('option', { name: target })
    if (await option.count()) await option.click()
  }
  expect(await page.locator('.ui-nav-item').count()).toBe(startingNavCount)
  expect(await page.locator('.ui-nav-item').evaluateAll(nodes => new Set(nodes.map(node => node.textContent?.trim())).size)).toBeGreaterThan(0)

  await language.click()
  const arabic = page.getByRole('option', { name: 'العربية' })
  if (await arabic.count()) {
    await arabic.click()
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
    await expectNoViewportOverflow(page)
  }
})

test('DataGrid and chat destinations load without uncaught page errors', async ({ page }) => {
  const errors = []
  page.on('pageerror', error => errors.push(error.message))
  await login(page)

  for (const destination of [/^People$/i, /^Chat$/i]) {
    const button = page.getByRole('button', { name: destination })
    if (!(await button.count())) continue
    await button.click()
    await page.waitForTimeout(400)
    await expectNoViewportOverflow(page)
  }
  expect(errors).toEqual([])
})

test('platform operator can open dedicated seller shell', async ({ page }) => {
  await login(page)
  await page.goto('/seller')
  const sellerHeader = page.getByText('WorkIntel Seller Platform').first()
  await expect(sellerHeader).toBeVisible()
  await expectNoViewportOverflow(page)
})


test('logout followed by browser Back never reveals the previous protected workspace snapshot', async ({ page }) => {
  await login(page)
  await page.goto('/app?history-entry=protected')
  await expect(page.locator('.ui-sidebar')).toBeVisible({ timeout: 15_000 })
  await page.goto('/app?history-entry=logout')
  await expect(page.locator('.ui-sidebar')).toBeVisible({ timeout: 15_000 })

  await page.getByRole('button', { name: /account menu/i }).click()
  await page.getByRole('menuitem', { name: /sign out/i }).click()
  await expect(page.getByRole('button', { name: /^sign in/i })).toBeVisible({ timeout: 15_000 })

  await page.goBack()
  await expect(page.getByRole('button', { name: /^sign in/i })).toBeVisible({ timeout: 15_000 })
  await expect(page.locator('.ui-sidebar')).toHaveCount(0)
})

test('M11 Help Center keyboard and RTL flow remains accessible', async ({ page }) => {
  await login(page)
  await page.keyboard.press('F1')
  const help = page.getByRole('dialog', { name: /help center/i }).first()
  await expect(help).toBeVisible()
  await expect(help.getByRole('tab')).toHaveCount(3)
  await expect(help.getByRole('textbox', { name: /search help center/i })).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(help).toBeHidden()

  const language = page.getByRole('button', { name: /language/i })
  if (await language.count()) {
    await language.click()
    const arabic = page.getByRole('option', { name: 'العربية' })
    if (await arabic.count()) {
      await arabic.click()
      await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
      await page.keyboard.press('F1')
      await expect(page.getByRole('dialog').last()).toBeVisible()
      await expectNoViewportOverflow(page)
    }
  }
})
