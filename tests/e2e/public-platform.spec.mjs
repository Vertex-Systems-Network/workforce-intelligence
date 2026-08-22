import { expect, test } from '@playwright/test'

/** Assert the current document does not create a viewport-wide horizontal overflow. */
async function expectNoViewportOverflow(page) {
  const dimensions = await page.evaluate(() => ({ width: window.innerWidth, scrollWidth: document.documentElement.scrollWidth }))
  expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.width + 2)
}

test('marketing website exposes complete platform navigation and a real favicon', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { name: /run work, time, people and operations/i })).toBeVisible()
  await expect(page.locator('.marketing-feature-section')).toHaveCount(12)
  await expect(page.locator('link[rel="icon"]')).toHaveAttribute('href', /favicon\.svg$/)
  await expect(page.getByRole('navigation', { name: /marketing navigation/i })).toBeVisible()

  await page.getByRole('link', { name: /^Security$/i }).click()
  await expect(page).toHaveURL(/#security$/)
  await expect(page.getByRole('heading', { name: /tracking only works when access and privacy rules are explicit/i })).toBeVisible()

  await page.getByRole('link', { name: /^How it works$/i }).click()
  await expect(page).toHaveURL(/#architecture$/)
  await expect(page.getByRole('heading', { name: /one application shell, multiple governed surfaces/i })).toBeVisible()
  await expectNoViewportOverflow(page)
})

test('public health and workspace login shell render without viewport overflow', async ({ page, request }) => {
  const health = await request.get('/health/live')
  expect(health.ok()).toBeTruthy()
  await expect(await health.json()).toMatchObject({ ok: true })

  await page.goto('/app')
  await expect(page.getByRole('button', { name: /sign in/i })).toBeVisible()
  await expect(page.getByRole('textbox', { name: /email/i })).toBeVisible()
  await expectNoViewportOverflow(page)
})

test('seller surface is separated from workspace login', async ({ page }) => {
  await page.goto('/seller')
  await expect(page.getByRole('heading', { name: /seller sign in/i })).toBeVisible()
  await expect(page.getByRole('button', { name: /sign in to seller platform/i })).toBeVisible()
  await expectNoViewportOverflow(page)
})
