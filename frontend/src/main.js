import { createApp } from 'vue'
import '@fortawesome/fontawesome-free/css/all.min.css'
import './style.scss'
import App from './App.vue'
import router from './router'
import { initI18n } from './i18n/i18n'
import { initTheme } from './theme/theme'
import { communityDefaultLanguage, fetchCommunityBranding } from './composables/useCommunity'
import { resolveSession, sessionStatus } from './composables/useSession'

initTheme()

async function bootstrap() {
  await fetchCommunityBranding()
  await resolveSession()
  initI18n({
    defaultLanguage: communityDefaultLanguage.value,
    allowStoredLanguage: sessionStatus.value === 'authenticated',
  })
  createApp(App).use(router).mount('#app')
}

bootstrap()
