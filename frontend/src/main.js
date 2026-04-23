import { createApp } from 'vue'
import '@fortawesome/fontawesome-free/css/all.min.css'
import './style.scss'
import App from './App.vue'
import router from './router'
import { initI18n } from './i18n/i18n'
import { initTheme } from './theme/theme'
import { fetchCommunityBranding } from './composables/useCommunity'
import { resolveSession } from './composables/useSession'

initTheme()
initI18n()

async function bootstrap() {
  await resolveSession()
  await fetchCommunityBranding()
  createApp(App).use(router).mount('#app')
}

bootstrap()
