<template>
  <PublicLayout v-if="layout === 'public'">
    <RouterView />
  </PublicLayout>
  <AppShellLayout v-else>
    <RouterView />
  </AppShellLayout>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppShellLayout from './layouts/AppShellLayout.vue'
import PublicLayout from './layouts/PublicLayout.vue'
import { communityName } from './composables/useCommunity'
import { language } from './i18n/i18n'
import { syncDocumentTitle } from './utils/documentTitle'

const route = useRoute()

const layout = computed(() => (route.meta.layout === 'public' ? 'public' : 'app'))

watch(
  () => [route.fullPath, communityName.value, language.value],
  () => syncDocumentTitle(route),
  { immediate: true },
)
</script>
