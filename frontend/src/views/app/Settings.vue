<template>
  <section class="page page--settings">
    <PageToolbarTitle route-key="settings">
      <Title tag="h1">{{ t('settings.title') }}</Title>
    </PageToolbarTitle>

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

    <div class="settings-sign-out">
      <h2 class="settings-sign-out__title">{{ t('settings.account') }}</h2>
      <p class="settings-sign-out__hint">{{ t('settings.signOutHint') }}</p>
      <Button
        type="button"
        variant="secondary"
        :loading="signingOut"
        @click="signOut"
      >
        {{ t('settings.signOut') }}
      </Button>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { t, language, setLanguage } from '../../i18n/i18n'
import { SUPPORTED_LANGUAGES } from '../../i18n/locales'
import { themeMode, setThemeMode } from '../../theme/theme'
import Title from '../../atoms/Title.vue'
import Button from '../../atoms/Button.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
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

const signingOut = ref(false)

async function signOut() {
  if (signingOut.value) return
  signingOut.value = true
  try {
    await logoutRequest()
    await router.replace({ name: 'login' })
  } finally {
    signingOut.value = false
  }
}

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
      onClick: signOut,
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

.settings-sign-out {
  margin-top: 1.5rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: 0.75rem;
}

.settings-sign-out__title {
  margin: 0;
  font-size: 1.1rem;
}

.settings-sign-out__hint {
  margin: 0.5rem 0 0.75rem;
  font-size: 0.9rem;
  opacity: 0.85;
  max-width: 36rem;
}
</style>
