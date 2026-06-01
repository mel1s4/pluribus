import { createApp } from 'vue'
import { registerSW } from 'virtual:pwa-register'
import './style.scss'
import App from './App.vue'
import router from './router'
import { initI18n } from './i18n/i18n'
import { initTheme } from './theme/theme'
import { communityDefaultLanguage, fetchCommunityBranding } from './composables/useCommunity'
import { communityHostSlug, resolveCommunityHost } from './composables/useCommunityHost'
import { resolveSession, sessionStatus } from './composables/useSession'
import { isLegacyChanteHost, redirectLegacyHostIfNeeded } from './legacyHostRedirect.js'
import './composables/useFavorites.js'

const BOOTSTRAP_TIMEOUT_MS = 8000

function settleWithin(promise, timeoutMs, label) {
  let timeoutId
  const timeoutPromise = new Promise((resolve) => {
    timeoutId = setTimeout(() => {
      console.warn(`[bootstrap] ${label} timed out after ${timeoutMs}ms`)
      resolve()
    }, timeoutMs)
  })
  return Promise.race([Promise.resolve(promise), timeoutPromise]).finally(() => {
    clearTimeout(timeoutId)
  })
}

/**
 * A leftover service worker from preview/production on the same origin can intercept
 * Vite pre-bundles under /node_modules/.vite/deps/ and break FullCalendar (corrupted
 * content, empty MIME). Unregister in dev before mounting.
 */
async function prepareDevServiceWorker() {
  if (!import.meta.env.DEV || typeof navigator === 'undefined' || !('serviceWorker' in navigator)) {
    return
  }
  const registrations = await navigator.serviceWorker.getRegistrations()
  await Promise.all(registrations.map((r) => r.unregister()))
}

function bootstrapApp() {
  prepareDevServiceWorker().then(() => {
    if (import.meta.env.PROD && !isLegacyChanteHost()) {
      const updateSW = registerSW({
        immediate: true,
        onNeedRefresh() {
          // Activate the new service worker immediately and then reload.
          void updateSW(true)
        },
        onOfflineReady() {
          console.info('[PWA] App is ready for offline usage.')
        },
      })
    }

    createApp(App).use(router).mount('#app')

  void resolveSession().catch(() => {})
  settleWithin(resolveCommunityHost(), BOOTSTRAP_TIMEOUT_MS, 'community-host').then(() => {
    const slug =
      typeof communityHostSlug.value === 'string' && communityHostSlug.value.trim() !== ''
        ? communityHostSlug.value.trim()
        : null
    return settleWithin(fetchCommunityBranding(slug), BOOTSTRAP_TIMEOUT_MS, 'community-branding')
  }).then(() => {
      if (sessionStatus.value === 'unknown') {
        // Never block public app shell forever because an upstream request stalled.
        sessionStatus.value = 'guest'
      }
      initI18n({
        defaultLanguage: communityDefaultLanguage.value,
        allowStoredLanguage: sessionStatus.value === 'authenticated',
      })
    })
  })
}

if (isLegacyChanteHost()) {
  void redirectLegacyHostIfNeeded()
} else {
  initTheme()
  bootstrapApp()
}
