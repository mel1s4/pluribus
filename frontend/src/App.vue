<script setup>
import { computed, defineAsyncComponent, watch } from 'vue'
import { useRoute } from 'vue-router'
import { communityName } from './composables/useCommunity'
import { sessionStatus } from './composables/useSession'
import { i18nReady, language } from './i18n/i18n'
import { syncDocumentTitle } from './utils/documentTitle'
import { useVersionCheck } from './composables/useVersionCheck'

const PublicLayout = defineAsyncComponent(() => import('./layouts/PublicLayout.vue'))
const AppShellLayout = defineAsyncComponent(() => import('./layouts/AppShellLayout.vue'))

const route = useRoute()

const showBootstrap = computed(
  () => sessionStatus.value === 'unknown' || !i18nReady.value,
)

const layoutComponent = computed(() =>
  route.meta.layout === 'public' ? PublicLayout : AppShellLayout,
)

// Check for new app version automatically
const { hasNewVersion, reloadNow } = useVersionCheck()

watch(
  () => [route.fullPath, communityName.value, language.value],
  () => syncDocumentTitle(route),
  { immediate: true },
)
</script>

<template>
  <div v-if="showBootstrap" class="app-bootstrap" role="status">
    <div class="app-bootstrap__spinner" aria-hidden="true" />
    <span class="app-bootstrap__srOnly">Loading…</span>
  </div>
  <component :is="layoutComponent" v-else>
    <RouterView />
    
    <!-- New version notification -->
    <div v-if="hasNewVersion" class="app-update-banner" role="alert">
      <div class="app-update-banner__content">
        <span class="app-update-banner__text">
          A new version is available. Reloading to update...
        </span>
        <button 
          type="button" 
          class="app-update-banner__button" 
          @click="reloadNow"
        >
          Reload Now
        </button>
      </div>
    </div>
  </component>
</template>

<style scoped lang="scss">
.app-bootstrap {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: var(--app-bg, #f4f4f5);
}

.app-bootstrap__spinner {
  width: 2.25rem;
  height: 2.25rem;
  border: 3px solid rgba(0, 0, 0, 0.12);
  border-top-color: rgba(0, 0, 0, 0.45);
  border-radius: 50%;
  animation: app-bootstrap-spin 0.7s linear infinite;
}

.app-bootstrap__srOnly {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

@keyframes app-bootstrap-spin {
  to {
    transform: rotate(360deg);
  }
}

.app-update-banner {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 10000;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 0.75rem 1rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  animation: slideDown 0.3s ease-out;
}

.app-update-banner__content {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
}

.app-update-banner__text {
  font-size: 0.9rem;
  font-weight: 500;
}

.app-update-banner__button {
  padding: 0.4rem 1rem;
  background: white;
  color: #667eea;
  border: none;
  border-radius: 4px;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  transition: transform 0.15s ease;
}

.app-update-banner__button:hover {
  transform: scale(1.05);
}

.app-update-banner__button:active {
  transform: scale(0.98);
}

@keyframes slideDown {
  from {
    transform: translateY(-100%);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}
</style>
