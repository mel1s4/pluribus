<script setup>
import { computed } from 'vue'
import Button from '../atoms/Button.vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  count: {
    type: Number,
    default: 0,
  },
})

const selectedLabel = computed(() => t('folders.bulkActions.selected').replace('{count}', String(props.count)))

const emit = defineEmits(['move', 'delete', 'clear', 'selectAll'])
</script>

<template>
  <div v-if="count > 0" class="folder-bulk-actions" role="toolbar" :aria-label="t('folders.bulkActions.toolbar')">
    <span class="folder-bulk-actions__count">{{ selectedLabel }}</span>
    <div class="folder-bulk-actions__btns">
      <Button size="sm" variant="secondary" @click="emit('selectAll')">{{ t('folders.bulkActions.selectAll') }}</Button>
      <Button size="sm" variant="secondary" @click="emit('move')">{{ t('folders.bulkActions.moveSelected') }}</Button>
      <Button size="sm" variant="danger" @click="emit('delete')">{{ t('folders.bulkActions.deleteSelected') }}</Button>
      <Button size="sm" variant="ghost" @click="emit('clear')">{{ t('folders.bulkActions.clearSelection') }}</Button>
    </div>
  </div>
</template>

<style scoped lang="scss">
.folder-bulk-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.65rem;
  padding: 0.65rem 0.85rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--surface-1, rgba(0, 0, 0, 0.03));
  margin-bottom: 0.75rem;
}

.folder-bulk-actions__count {
  font-weight: 600;
  font-size: 0.9rem;
}

.folder-bulk-actions__btns {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-left: auto;
}
</style>
