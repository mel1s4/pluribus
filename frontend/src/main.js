import { createApp } from 'vue'
import { registerSW } from 'virtual:pwa-register'
import './style.scss'
import App from './App.vue'
import router from './router'
import { initI18n } from './i18n/i18n'
import { initTheme } from './theme/theme'
import { communityDefaultLanguage, fetchCommunityBranding } from './composables/useCommunity'
import { resolveSession, sessionStatus } from './composables/useSession'

initTheme()

registerSW({ immediate: true })

createApp(App).use(router).mount('#app')

Promise.all([fetchCommunityBranding(), resolveSession()]).then(() =>
  initI18n({
    defaultLanguage: communityDefaultLanguage.value,
    allowStoredLanguage: sessionStatus.value === 'authenticated',
  }),
)
