import React from 'react'
import { createRoot } from 'react-dom/client'
import WorkforceApp from './WorkforceApp'
import MarketingWebsite from './pages/MarketingWebsite'
import ClientPortalApp from './client-portal/ClientPortalApp'
import SellerPlatformApp from './seller/SellerPlatformApp'
import PublicDocumentSignApp from './documents/PublicDocumentSignApp'
import PublicWebsiteApp from './website/PublicWebsiteApp'
import AppErrorBoundary from './AppErrorBoundary'
import { ThemeProvider } from './theme'
import { AuthProvider } from './auth/AuthContext'
import { LocalizationProvider } from './i18n/LocalizationContext'
import LegacyLocalizationBridge from './i18n/LegacyLocalizationBridge'
import { RequestProgress } from './components/LoadingStates'
import { ToastViewport } from './design-system/toast'
import { ConfirmProvider } from './design-system'
import '../css/app.css'
import '../css/professional-ui.css'

const rootElement = document.getElementById('root')

if (!rootElement) {
  throw new Error('React root element #root was not found in the Laravel application view.')
}

window.__WORKINTEL_REACT_MOUNTED__ = true

const path = window.location.pathname.replace(/\/+$/, '') || '/'
const isClientPortal = path.startsWith('/portal/')
const isMarketingSite = path === '/'
const isSellerPlatform = path === '/seller' || path.startsWith('/seller/')
const isPublicDocumentSign = path.startsWith('/document-sign/')
const isPublicWebsite = path.startsWith('/site/') || path.startsWith('/site-preview/') || Boolean((window as any).__WORKINTEL_PUBLIC_WEBSITE_HOST__)
const isPrivateShell = !isPublicWebsite && !isPublicDocumentSign && !isClientPortal && !isMarketingSite

if (isPrivateShell) {
  document.documentElement.dataset.workintelPrivateShell = 'true'
  /** Hide protected DOM before the browser captures a back/forward-cache snapshot. */
  window.addEventListener('pagehide', () => {
    document.documentElement.setAttribute('data-workintel-private-snapshot', 'hidden')
  })
}

createRoot(rootElement).render(
  <React.StrictMode>
    <AppErrorBoundary>
      <ThemeProvider>
        {isPublicWebsite ? (
          <>
            <RequestProgress />
            <ToastViewport />
            <PublicWebsiteApp />
          </>
        ) : isPublicDocumentSign ? (
          <>
            <RequestProgress />
            <ToastViewport />
            <PublicDocumentSignApp />
          </>
        ) : isClientPortal ? (
          <ClientPortalApp />
        ) : isMarketingSite ? (
          <MarketingWebsite />
        ) : isSellerPlatform ? (
          <AuthProvider>
            <LocalizationProvider>
              <LegacyLocalizationBridge />
              <RequestProgress />
              <ToastViewport />
              <SellerPlatformApp />
            </LocalizationProvider>
          </AuthProvider>
        ) : (
          <AuthProvider>
            <LocalizationProvider>
              <LegacyLocalizationBridge />
              <RequestProgress />
              <ToastViewport />
              <ConfirmProvider><WorkforceApp /></ConfirmProvider>
            </LocalizationProvider>
          </AuthProvider>
        )}
      </ThemeProvider>
    </AppErrorBoundary>
  </React.StrictMode>,
)

if ('serviceWorker' in navigator && window.location.protocol !== 'file:') {
  window.addEventListener('load', () => { void navigator.serviceWorker.register('/sw.js').catch(() => undefined) })
}
