<script setup>
import { t } from '../../i18n/i18n'

const emit = defineEmits(['open-create-place'])
</script>

<template>
  <article class="dashboard-widget">
    <h2 class="dashboard-widget__title">{{ t('dashboard.setupPlace.title') }}</h2>
    <p class="dashboard-widget__body">{{ t('dashboard.setupPlace.body') }}</p>
    <button type="button" class="dashboard-widget__action" @click="emit('open-create-place')">
      {{ t('dashboard.setupPlace.action') }}
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
