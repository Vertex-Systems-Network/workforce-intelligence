import { expect, test } from '@playwright/test'

/** Assert the current document does not create a viewport-wide horizontal overflow. */
async function expectNoViewportOverflow(page) {
  const dimensions = await page.evaluate(() => ({ width: window.innerWidth, scrollWidth: document.documentElement.scrollWidth }))
  expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.width + 2)
}

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
