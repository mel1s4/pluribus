<template>
  <section class="page page--settings">
    <Title tag="h1">{{ t('settings.title') }}</Title>

    <SettingsGroup
      :title="t('settings.theme')"
      name="theme"
      v-model="themeChoice"
      :options="themeOptions"
    />

    <SettingsGroup
      :title="t('settings.language')"
      name="lang"
      v-model="langChoice"
      :options="langOptions"
    />
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { t, language, setLanguage } from '../../i18n/i18n'
import { SUPPORTED_LANGUAGES } from '../../i18n/locales'
import { themeMode, setThemeMode } from '../../theme/theme'
import Title from '../../atoms/Title.vue'
import SettingsGroup from '../../molecules/SettingsGroup.vue'
import { useAppShell } from '../../composables/useAppShell'
import { logoutRequest } from '../../composables/useSession'

const router = useRouter()
const { setHeaderActions, clearHeaderActions } = useAppShell()

const themeChoice = computed({
  get: () => themeMode.value,
  set: (next) => setThemeMode(next),
})

const langChoice = computed({
  get: () => language.value,
  set: (next) => setLanguage(next),
})

const themeOptions = computed(() => [
  { value: 'system', label: t('settings.theme.system') },
  { value: 'light', label: t('settings.theme.light') },
  { value: 'dark', label: t('settings.theme.dark') },
])

const langOptions = computed(() =>
  SUPPORTED_LANGUAGES.map((entry) => ({
    value: entry.code,
    label: t(entry.labelKey),
  })),
)

onMounted(() => {
  setHeaderActions([
    {
      id: 'to-dashboard',
      label: t('nav.dashboard'),
      variant: 'secondary',
      onClick: () => router.push('/dashboard'),
    },
    {
      id: 'sign-out',
      label: t('settings.signOut'),
      variant: 'secondary',
      onClick: async () => {
        await logoutRequest()
        await router.replace({ name: 'login' })
      },
    },
  ])
})

onUnmounted(() => {
  clearHeaderActions()
})
</script>

<style lang="scss" scoped>
.page--settings {
  padding: 2rem;
  max-width: 720px;
  margin: 0 auto;
}
</style>
