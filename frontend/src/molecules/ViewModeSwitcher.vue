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
      :title="t('folders.viewMode.list')"
      @click="emit('update:modelValue', 'list')"
    >
      <Icon name="list" />
      <span class="view-mode-switcher__text">{{ t('folders.viewMode.list') }}</span>
    </button>
    <button
      type="button"
      class="view-mode-switcher__btn"
      :class="{ 'is-active': modelValue === 'grid' }"
      :aria-pressed="modelValue === 'grid'"
      :title="t('folders.viewMode.grid')"
      @click="emit('update:modelValue', 'grid')"
    >
      <Icon name="note-sticky" />
      <span class="view-mode-switcher__text">{{ t('folders.viewMode.grid') }}</span>
    </button>
    <button
      type="button"
      class="view-mode-switcher__btn"
      :class="{ 'is-active': modelValue === 'tree' }"
      :aria-pressed="modelValue === 'tree'"
      :title="t('folders.viewMode.tree')"
      @click="emit('update:modelValue', 'tree')"
    >
      <Icon name="folder-open" />
      <span class="view-mode-switcher__text">{{ t('folders.viewMode.tree') }}</span>
    </button>
  </div>
</template>

<style scoped lang="scss">
.view-mode-switcher {
  display: inline-flex;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  overflow: hidden;
  background: var(--bg);
}

.view-mode-switcher__btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.4rem 0.65rem;
  border: none;
  background: transparent;
  color: inherit;
  cursor: pointer;
  font-size: 0.85rem;

  &.is-active {
    background: var(--surface-2, rgba(0, 0, 0, 0.06));
    font-weight: 600;
  }

  &:not(:last-child) {
    border-right: 1px solid var(--border);
  }
}

.view-mode-switcher__text {
  @media (max-width: 640px) {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    white-space: nowrap;
  }
}
</style>
