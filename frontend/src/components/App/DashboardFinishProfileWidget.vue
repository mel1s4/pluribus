<script setup>
import { computed } from 'vue'
import { t } from '../../i18n/i18n'

const props = defineProps({
  user: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['open-profile'])

const checks = computed(() => {
  const user = props.user || {}
  const hasProfilePicture = Boolean(user.avatar_url)
  const hasPhone = Array.isArray(user.phone_numbers) && user.phone_numbers.length > 0
  const hasContactEmail = Array.isArray(user.contact_emails) && user.contact_emails.length > 0

  return [
    {
      id: 'avatar',
      done: hasProfilePicture,
      label: t('dashboard.finishProfile.missing.avatar'),
    },
    {
      id: 'phone',
      done: hasPhone,
      label: t('dashboard.finishProfile.missing.phone'),
    },
    {
      id: 'email',
      done: hasContactEmail,
      label: t('dashboard.finishProfile.missing.email'),
    },
  ]
})

const completion = computed(() => {
  const total = checks.value.length
  const completed = checks.value.filter((item) => item.done).length
  return Math.round((completed / total) * 100)
})

const missingItems = computed(() => checks.value.filter((item) => !item.done))
</script>

<template>
  <article class="dashboard-widget">
    <h2 class="dashboard-widget__title">{{ t('dashboard.finishProfile.title') }}</h2>
    <p class="dashboard-widget__body">
      {{ t('dashboard.finishProfile.progress').replace('{percent}', String(completion)) }}
    </p>
    <ol class="dashboard-widget__list">
      <li v-for="item in missingItems" :key="item.id">{{ item.label }}</li>
    </ol>
    <button type="button" class="dashboard-widget__action" @click="emit('open-profile')">
      {{ t('dashboard.finishProfile.action') }}
    </button>
  </article>
</template>

<style scoped lang="scss">
.dashboard-widget {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--bg);
  padding: 1rem;
}

.dashboard-widget__title {
  margin: 0;
  font-size: 1rem;
}

.dashboard-widget__body {
  margin: 0;
  font-size: 0.92rem;
}

.dashboard-widget__list {
  margin: 0 0 0 1.1rem;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.9rem;
}

.dashboard-widget__action {
  align-self: flex-start;
  cursor: pointer;
  padding: 0.45rem 0.85rem;
  border-radius: 6px;
  border: 1px solid var(--accent, #3b5bdb);
  background: var(--accent, #3b5bdb);
  color: #fff;
  font: inherit;
}
</style>
