<script setup>
import { computed, defineAsyncComponent, watch } from 'vue'
import { useRoute } from 'vue-router'
import { communityName } from './composables/useCommunity'
import { sessionStatus } from './composables/useSession'
import { i18nReady, language } from './i18n/i18n'
import { syncDocumentTitle } from './utils/documentTitle'

const PublicLayout = defineAsyncComponent(() => import('./layouts/PublicLayout.vue'))
const AppShellLayout = defineAsyncComponent(() => import('./layouts/AppShellLayout.vue'))

const route = useRoute()

const showBootstrap = computed(
  () => sessionStatus.value === 'unknown' || !i18nReady.value,
)

const layoutComponent = computed(() =>
  route.meta.layout === 'public' ? PublicLayout : AppShellLayout,
)

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
</style>
