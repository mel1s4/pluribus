<script setup>
import Icon from '../atoms/Icon.vue'
import { t } from '../i18n/i18n'

defineProps({
  modelValue: {
    type: String,
    required: true,
    validator: (v) => ['list', 'grid', 'tree'].includes(v),
  },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="view-mode-switcher" role="group" :aria-label="t('folders.viewMode.label')">
    <button
      type="button"
      class="view-mode-switcher__btn"
      :class="{ 'is-active': modelValue === 'list' }"
      :aria-pressed="modelValue === 'list'"
      :aria-label="t('folders.viewMode.list')"
      :title="t('folders.viewMode.list')"
      @click="emit('update:modelValue', 'list')"
    >
      <Icon name="list" aria-hidden="true" />
    </button>
    <button
      type="button"
      class="view-mode-switcher__btn"
      :class="{ 'is-active': modelValue === 'grid' }"
      :aria-pressed="modelValue === 'grid'"
      :aria-label="t('folders.viewMode.grid')"
      :title="t('folders.viewMode.grid')"
      @click="emit('update:modelValue', 'grid')"
    >
      <Icon name="note-sticky" aria-hidden="true" />
    </button>
    <button
      type="button"
      class="view-mode-switcher__btn"
      :class="{ 'is-active': modelValue === 'tree' }"
      :aria-pressed="modelValue === 'tree'"
      :aria-label="t('folders.viewMode.tree')"
      :title="t('folders.viewMode.tree')"
      @click="emit('update:modelValue', 'tree')"
    >
      <Icon name="folder-open" aria-hidden="true" />
    </button>
  </div>
</template>

<style scoped lang="scss">
.view-mode-switcher {
  display: inline-flex;
  flex-shrink: 0;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  overflow: hidden;
  background: var(--bg, #fff);
}

.view-mode-switcher__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--text-muted, #6b7280);
  cursor: pointer;
  transition: background 100ms ease, color 100ms ease;

  &.is-active {
    background: var(--surface-2, rgba(0, 0, 0, 0.06));
    color: var(--text, #111827);
  }

  &:not(:last-child) {
    border-right: 1px solid var(--border, #e5e7eb);
  }

  &:hover:not(.is-active) {
    background: var(--surface-2, rgba(0, 0, 0, 0.03));
  }
}
</style>
