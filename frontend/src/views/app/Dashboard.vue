<template>
  <section class="page page--dashboard">
    <Title tag="h1">{{ t('dashboard.title') }}</Title>
    <p>{{ t('dashboard.description') }}</p>
  </section>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'
import { useAppShell } from '../../composables/useAppShell'

const router = useRouter()
const { setHeaderActions, clearHeaderActions } = useAppShell()

onMounted(() => {
  setHeaderActions([
    {
      id: 'go-settings',
      label: t('nav.settings'),
      variant: 'secondary',
      onClick: () => router.push('/settings'),
    },
  ])
})

onUnmounted(() => {
  clearHeaderActions()
})
</script>

<style lang="scss" scoped>
.page--dashboard {
  padding: 2rem;
}
</style>
