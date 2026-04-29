<script setup>
import { t } from '../i18n/i18n'

defineProps({
  form: { type: Object, required: true },
  groups: { type: Array, default: () => [] },
})
</script>

<template>
  <div class="post-composer-audience">
    <span class="post-composer-audience__legend">{{ t('calendar.visibility') }}</span>
    <div class="post-composer-audience__vis" role="group">
      <button
        type="button"
        class="post-composer-audience__visBtn"
        :class="{ 'is-on': form.visibility_scope === 'private' }"
        @click="form.visibility_scope = 'private'"
      >
        {{ t('calendar.private') }}
      </button>
      <button
        type="button"
        class="post-composer-audience__visBtn"
        :class="{ 'is-on': form.visibility_scope === 'community' }"
        @click="form.visibility_scope = 'community'"
      >
        {{ t('calendar.community') }}
      </button>
      <button
        type="button"
        class="post-composer-audience__visBtn"
        :class="{ 'is-on': form.visibility_scope === 'group' }"
        @click="form.visibility_scope = 'group'"
      >
        {{ t('calendar.group') }}
      </button>
    </div>

    <label v-if="form.visibility_scope === 'group'" class="post-composer-audience__field">
      <span class="post-composer-audience__label">{{ t('posts.composerGroupLabel') }}</span>
      <select v-model="form.shared_group_id" class="post-composer-audience__select">
        <option value="">{{ t('posts.composerGroupPlaceholder') }}</option>
        <option v-for="g in groups" :key="g.id" :value="String(g.id)">{{ g.name }}</option>
      </select>
    </label>
  </div>
</template>

<style scoped lang="scss">
.post-composer-audience {
  display: grid;
  gap: 1rem;
}

.post-composer-audience__legend {
  font-size: 0.875rem;
  font-weight: 600;
}

.post-composer-audience__vis {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.post-composer-audience__visBtn {
  padding: 0.45rem 0.9rem;
  border-radius: 0.45rem;
  border: 1px solid var(--border, #e5e7eb);
  background: var(--surface-2, #f3f4f6);
  font: inherit;
  font-size: 0.875rem;
  cursor: pointer;

  &.is-on {
    border-color: var(--btn-primary-bg, #2563eb);
    background: rgba(37, 99, 235, 0.1);
    color: var(--btn-primary-bg, #2563eb);
    font-weight: 600;
  }
}

.post-composer-audience__field {
  display: grid;
  gap: 0.35rem;
  margin: 0;
}

.post-composer-audience__label {
  font-size: 0.875rem;
  font-weight: 600;
}

.post-composer-audience__select {
  padding: 0.5rem 0.55rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  background: var(--bg, #fff);
  color: inherit;
  font: inherit;
  max-width: 24rem;
}
</style>
