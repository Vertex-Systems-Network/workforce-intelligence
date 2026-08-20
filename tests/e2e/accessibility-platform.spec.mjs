import { expect, test } from '@playwright/test'

const fullMode = process.env.WORKINTEL_E2E_MODE === 'full'
test.skip(!fullMode, 'Accessibility journeys run with full seeded certification data.')

/** Authenticate with the deterministic certification owner unless the environment supplies another account. */
async function login(page) {
  const email = process.env.WORKINTEL_E2E_EMAIL || 'owner@acme.test'
  const password = process.env.WORKINTEL_E2E_PASSWORD || 'password'
  await page.goto('/app')
  await page.getByRole('textbox', { name: /email/i }).fill(email)
  await page.locator('input[type="password"]').first().fill(password)
  await page.getByRole('button', { name: /^sign in/i }).click()
  await expect(page.locator('.ui-sidebar')).toBeVisible({ timeout: 15_000 })
}

/** Return high-priority WCAG naming defects without requiring an external axe runtime package. */
async function highPriorityNameIssues(page, scope = 'body') {
  return page.locator(scope).evaluate(root => {
    const visible = element => {
      const style = getComputedStyle(element)
      const rect = element.getBoundingClientRect()
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0
    }
    const text = element => (element.getAttribute('aria-label') || element.getAttribute('title') || element.textContent || '').trim()
    const issues = []
    root.querySelectorAll('button,a[href]').forEach(element => {
      if (visible(element) && !text(element)) issues.push(`${element.tagName.toLowerCase()}:unnamed-control`)
    })
    root.querySelectorAll('img').forEach(element => {
      if (!element.hasAttribute('alt')) issues.push('img:missing-alt')
    })
    root.querySelectorAll('[role="dialog"]').forEach(element => {
      if (!element.getAttribute('aria-label') && !element.getAttribute('aria-labelledby')) issues.push('dialog:unnamed')
    })
    return issues
  })
}

/** Assert the document remains usable at the narrow CSS-pixel width equivalent to 200% zoom on a 1280px desktop. */
async function expectReflow(page) {
  const metrics = await page.evaluate(() => ({ viewport: innerWidth, pageWidth: document.documentElement.scrollWidth }))
  expect(metrics.pageWidth).toBeLessThanOrEqual(metrics.viewport + 2)
}

test('skip link reaches the main workspace landmark and critical controls have accessible names', async ({ page }) => {
  await login(page)
  const skip = page.locator('.ui-skip-link').first()
  await skip.focus()
  await expect(skip).toBeFocused()
  await page.keyboard.press('Enter')
  await expect(page.locator('#workintel-main')).toBeFocused()
  expect(await highPriorityNameIssues(page, '.ui-topbar')).toEqual([])
})

test('command palette traps keyboard focus, closes on Escape and restores the opener', async ({ page }) => {
  await login(page)
  const opener = page.getByRole('button', { name: /search pages/i }).first()
  await opener.focus()
  await opener.click()
  const dialog = page.getByRole('dialog', { name: /search pages/i })
  await expect(dialog).toBeVisible()
  await expect(dialog.locator('input')).toBeFocused()
  for (let index = 0; index < 12; index += 1) {
    await page.keyboard.press('Tab')
    expect(await dialog.evaluate((node, active) => node.contains(active), await page.evaluateHandle(() => document.activeElement))).toBeTruthy()
  }
  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
  await expect(opener).toBeFocused()
})

test('dropdown and tab primitives support keyboard navigation and focus return', async ({ page }) => {
  await login(page)
  const workspaceTrigger = page.locator('.ui-topbar .ui-dropdown-anchor').first().locator('button').first()
  await workspaceTrigger.focus()
  await page.keyboard.press('ArrowDown')
  const menu = page.getByRole('menu').first()
  await expect(menu).toBeVisible()
  const firstItem = menu.getByRole('menuitem').first()
  if (await firstItem.count()) await expect(firstItem).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(menu).toBeHidden()
  await expect(workspaceTrigger).toBeFocused()

  const scheduling = page.getByRole('button', { name: /^Scheduling$/i })
  if (await scheduling.count()) {
    await scheduling.click()
    const tabs = page.getByRole('tab')
    if (await tabs.count() > 1) {
      const active = page.getByRole('tab', { selected: true }).first()
      await active.focus()
      await page.keyboard.press('ArrowRight')
      await expect(page.getByRole('tab', { selected: true }).first()).toBeFocused()
    }
  }
})

test('reduced motion, RTL and 200 percent equivalent reflow preserve operability', async ({ page }, testInfo) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await login(page)
  const durationMs = await page.locator('.ui-sidebar').evaluate(element => Math.max(...getComputedStyle(element).transitionDuration.split(',').map(value => { const trimmed=value.trim(); return trimmed.endsWith('ms')?parseFloat(trimmed):parseFloat(trimmed)*1000 })))
  expect(durationMs).toBeLessThanOrEqual(1)

  const language = page.getByRole('button', { name: /language/i })
  if (await language.count()) {
    await language.click()
    const arabic = page.getByRole('option', { name: 'العربية' })
    if (await arabic.count()) {
      await arabic.click()
      await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
      expect(await highPriorityNameIssues(page, '.ui-topbar')).toEqual([])
    }
  }

  if (testInfo.project.name === 'reflow-200pct-equivalent') await expectReflow(page)
})

test('touch profile exposes minimum-size primary controls', async ({ page }, testInfo) => {
  test.skip(!testInfo.project.name.startsWith('touch-'), 'Touch sizing is measured only on touch projects.')
  await login(page)
  const controls = page.locator('.ui-topbar button:visible')
  const count = Math.min(await controls.count(), 8)
  for (let index = 0; index < count; index += 1) {
    const box = await controls.nth(index).boundingBox()
    if (!box) continue
    expect(box.height).toBeGreaterThanOrEqual(40)
  }
})

test('main landmark, duplicate IDs and form controls have accessible names', async ({ page }) => {
  await login(page)
  const structural = await page.evaluate(() => {
    const visible = element => {
      const style = getComputedStyle(element)
      const rect = element.getBoundingClientRect()
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0
    }
    const ids = [...document.querySelectorAll('[id]')].map(element => element.id).filter(Boolean)
    const duplicates = [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))]
    const unnamed = [...document.querySelectorAll('input:not([type="hidden"]),select,textarea')].filter(visible).filter(control => {
      if ((control.getAttribute('aria-label') || '').trim()) return false
      const labelledBy = (control.getAttribute('aria-labelledby') || '').trim()
      if (labelledBy && labelledBy.split(/\s+/).some(id => (document.getElementById(id)?.textContent || '').trim())) return false
      if (control.closest('label')) return false
      if (control.id && document.querySelector(`label[for="${CSS.escape(control.id)}"]`)) return false
      return true
    }).map(control => `${control.tagName.toLowerCase()}:${control.getAttribute('name') || control.id || control.getAttribute('type') || 'unnamed'}`)
    const mains = [...document.querySelectorAll('main,[role="main"]')].filter(visible).length
    return { duplicates, unnamed, mains }
  })
  expect(structural.duplicates, 'duplicate IDs').toEqual([])
  expect(structural.unnamed, 'form controls have accessible names').toEqual([])
  expect(structural.mains, 'main landmark').toBeGreaterThanOrEqual(1)
})
