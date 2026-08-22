import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(import.meta.dirname, '..')
const failures = []

/** Convert a six-digit hexadecimal color into WCAG relative luminance. */
function luminance(hex) {
  const raw = hex.replace('#', '')
  const channels = [0, 2, 4].map(offset => Number.parseInt(raw.slice(offset, offset + 2), 16) / 255)
  const linear = channels.map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4)
  return (0.2126 * linear[0]) + (0.7152 * linear[1]) + (0.0722 * linear[2])
}

/** Return the WCAG contrast ratio between two opaque six-digit hexadecimal colors. */
function contrast(foreground, background) {
  const a = luminance(foreground)
  const b = luminance(background)
  return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)
}

/** Parse one theme variable block from the application CSS. */
function themeTokens(css, selector) {
  const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const match = css.match(new RegExp(`${escaped}\\s*\\{([\\s\\S]*?)\\}`))
  const tokens = {}
  for (const row of match?.[1]?.matchAll(/--([a-z0-9-]+):\s*(#[0-9a-f]{6})\s*;/gi) ?? []) tokens[row[1]] = row[2]
  return tokens
}

/** Read a UTF-8 source file relative to the project root. */
function source(relative) {
  const target = path.join(root, relative)
  if (!fs.existsSync(target)) {
    failures.push(`Missing required accessibility source: ${relative}`)
    return ''
  }
  return fs.readFileSync(target, 'utf8')
}

/** Require every marker in one source file so shared accessibility contracts cannot silently regress. */
function requireMarkers(relative, markers) {
  const body = source(relative)
  for (const marker of markers) if (!body.includes(marker)) failures.push(`${relative}: missing ${marker}`)
}

requireMarkers('resources/js/design-system/accessibility.ts', ['FOCUSABLE_SELECTOR', 'useFocusTrap', 'focusableElements', 'Escape', 'preventScroll'])
requireMarkers('resources/js/design-system/index.tsx', ['aria-modal="true"', 'role="tablist"', 'aria-selected', 'aria-sort', 'aria-current', 'role="progressbar"', 'aria-haspopup'])
requireMarkers('resources/js/WorkforceApp.tsx', ['ui-skip-link', 'id="workintel-main"'])
requireMarkers('resources/js/seller/SellerPlatformApp.tsx', ['ui-skip-link', 'id="seller-main"'])
requireMarkers('resources/js/client-portal/ClientPortalApp.tsx', ['ui-skip-link', '<Modal', 'LoadingState', 'EmptyState'])
requireMarkers('resources/js/design-system/index.tsx', ['export function Modal', 'useFocusTrap(open,dialogRef', 'aria-modal="true"'])
requireMarkers('resources/js/website/WebsiteRenderer.tsx', ['ui-skip-link', 'id="website-main"', 'aria-live="polite"'])
requireMarkers('resources/js/documents/PublicDocumentSignApp.tsx', ['ui-skip-link', 'id="document-sign-main"', 'aria-pressed'])
requireMarkers('resources/js/components/Sidebar.tsx', ['<nav', 'common.workspace_navigation', 'aria-label'])
requireMarkers('resources/js/pages/MarketingWebsite.tsx', ['id="marketing-main"', 'aria-label="Marketing navigation"', 'id="platform"', 'id="security"', 'role="img"', 'aria-labelledby'])
requireMarkers('resources/js/shellNavigation.ts', ['popstate', "new Event('hashchange')", 'pushState', 'replaceState'])
requireMarkers('resources/views/app.blade.php', ['rel="icon"', "asset('favicon.svg')", 'color-scheme', 'prefers-reduced-motion:reduce'])
requireMarkers('resources/css/app.css', ['prefers-reduced-motion:reduce', '@media(pointer:coarse)', '@media(forced-colors:active)', '.ui-skip-link', ':focus-visible'])
requireMarkers('resources/css/professional-ui.css', ['font-size: 14px', '--wi-control-h: 38px', '.ui-page-title', '.ui-sidebar__module-label', '.marketing-feature-section', '@media (pointer: coarse)', '@media (prefers-reduced-motion: reduce)', '@media (forced-colors: active)'])
requireMarkers('resources/css/professional-ui-responsive.css', ['grid-template-columns: repeat(2, minmax(0, 1fr))', 'min-height: 44px', '.marketing-product-kpi span { font-size: 12px; }', '.marketing-security-card p { font-size: 13px; }', '.chat-message-text { font-size: 14px; line-height: 1.6; }', '.chat-sync-state { font-size: 11px; }', '.client-portal-secure { font-size: 12px; }'])
requireMarkers('tools/e2e-browser.mjs', ['findChromeExecutable', 'findEdgeExecutable', 'findFirefoxExecutable', 'browserInventory'])
requireMarkers('tools/playwright.config.mjs', ['accessibilityProjects', 'firefox-desktop', 'touch-mobile', 'reflow-200pct-equivalent'])
requireMarkers('tools/run-browser-certification.mjs', ['accessibility', '--require-system-browsers', 'WORKINTEL_E2E_PROFILE'])
requireMarkers('tests/e2e/accessibility-platform.spec.mjs', ['focus', 'reduced motion', 'RTL', 'touch'])
requireMarkers('tools/wave-accessibility-audit.mjs', ['WAVE_API_KEY', 'WORKINTEL_WAVE_URL', 'https://wave.webaim.org/api/request', 'WAVE_MAX_ERRORS', 'WAVE_MAX_CONTRAST_ERRORS'])
requireMarkers('package.json', ['"quality": "npm run verify:source && npm run accessibility:audit && npm run performance:audit"', '"accessibility:wave": "node tools/wave-accessibility-audit.mjs"'])

const favicon = path.join(root, 'public/favicon.svg')
if (!fs.existsSync(favicon) || fs.statSync(favicon).size < 100) failures.push('public/favicon.svg must be a non-empty real favicon asset')
if (fs.existsSync(path.join(root, 'public/favicon.ico')) && fs.statSync(path.join(root, 'public/favicon.ico')).size === 0) failures.push('public/favicon.ico is an empty placeholder and must not be committed')

const professionalCss = source('resources/css/professional-ui.css')
const responsiveCss = source('resources/css/professional-ui-responsive.css')
const bodyFont = professionalCss.match(/body\s*\{[^}]*font-size:\s*([\d.]+)px/s)
if (!bodyFont || Number(bodyFont[1]) < 14) failures.push('Professional UI body font size must be at least 14px')
const pageTitleFont = professionalCss.match(/\.ui-page-title\s*\{[^}]*font-size:\s*([\d.]+)px/s)
if (!pageTitleFont || Number(pageTitleFont[1]) < 20) failures.push('Professional UI page title must be at least 20px')
const navFont = professionalCss.match(/\.ui-nav-item\s*\{[^}]*font-size:\s*([\d.]+)px/s)
if (!navFont || Number(navFont[1]) < 14) failures.push('Primary navigation text must be at least 14px')
const moduleFont = professionalCss.match(/\.ui-sidebar__module-label\s*\{[^}]*font-size:\s*([\d.]+)px/s)
if (!moduleFont || Number(moduleFont[1]) < 12) failures.push('Module navigation labels must be at least 12px')
if (responsiveCss.includes('overflow-x: auto')) failures.push('Mobile marketing navigation must expose destinations without hidden horizontal scrolling')

const catalog = source('resources/js/i18n/locales/core.ts')
for (const key of ['common.skip_to_content', 'common.data_table', 'common.toggle_setting', 'common.workspace_navigation', 'common.options', 'common.progress', 'common.tabs']) {
  const occurrences = catalog.split(`'${key}'`).length - 1
  if (occurrences !== 5) failures.push(`resources/js/i18n/locales/core.ts: ${key} must exist exactly once in each of five locales; found ${occurrences}`)
}

const appCss = source('resources/css/app.css')
const dark = themeTokens(appCss, ':root, :root[data-theme="dark"]')
const light = themeTokens(appCss, ':root[data-theme="light"]')
for (const [theme, tokens] of [['dark', dark], ['light', light]]) {
  const textBackgrounds = ['bg', 'surface', 'elevated']
  for (const textToken of ['text', 'text-2', 'text-3', 'accent']) {
    for (const backgroundToken of textBackgrounds) {
      if (!tokens[textToken] || !tokens[backgroundToken]) continue
      const ratio = contrast(tokens[textToken], tokens[backgroundToken])
      if (ratio < 4.5) failures.push(`${theme} contrast ${textToken}/${backgroundToken} = ${ratio.toFixed(2)}; expected >= 4.50`)
    }
  }
  for (const solid of ['accent-solid', 'accent-solid-hover']) {
    if (!tokens[solid]) failures.push(`${theme}: missing --${solid}`)
    else {
      const ratio = contrast('#ffffff', tokens[solid])
      if (ratio < 4.5) failures.push(`${theme} contrast white/${solid} = ${ratio.toFixed(2)}; expected >= 4.50`)
    }
  }
}

if (failures.length) {
  console.error('WorkIntel accessibility source audit: FAIL')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('WorkIntel accessibility source audit: PASS')
console.log('WCAG-oriented focus, semantics, readable typography, responsive reflow, RTL, contrast, favicon and browser-matrix contracts are present; the optional WAVE adapter is configured separately for public URLs.')
