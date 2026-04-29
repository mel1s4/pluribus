<script setup>
import Icon from './Icon.vue'

defineProps({
  label: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <span
    class="drag-handle"
    role="img"
    :aria-label="label || undefined"
    data-drag-handle
  >
    <Icon name="grip-dots-vertical" :label="label" />
  </span>
</template>

<style scoped lang="scss">
.drag-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.15rem;
  color: var(--muted, #888);
  cursor: grab;
  touch-action: none;
  user-select: none;

  &:active {
    cursor: grabbing;
  }
}
</style>
