<script setup>
import ChatTaskItem from '../molecules/ChatTaskItem.vue'

defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  showCheckboxes: {
    type: Boolean,
    default: false,
  },
  isSelected: {
    type: Function,
    default: () => false,
  },
})

const emit = defineEmits(['openChat', 'openTask', 'toggleSelect', 'dragStartChat', 'dragEndChat', 'dragStartTask', 'dragEndTask'])
</script>

<template>
  <ul class="item-grid-view" role="list">
    <li v-for="row in items" :key="row.key" class="item-grid-view__li" role="listitem">
      <ChatTaskItem
        v-if="row.kind === 'chat'"
        kind="chat"
        :item="row.item"
        layout="grid"
        :selected="isSelected('chat', row.item.id)"
        :show-checkbox="showCheckboxes"
        @open="emit('openChat', row.item)"
        @toggle-select="emit('toggleSelect', 'chat', row.item.id, $event)"
        @dragstart="emit('dragStartChat', row.item, $event)"
        @dragend="emit('dragEndChat', $event)"
      />
      <ChatTaskItem
        v-else
        kind="task"
        :item="row.item"
        layout="grid"
        :selected="isSelected('task', row.item.id)"
        :show-checkbox="showCheckboxes"
        @open="emit('openTask', row.item)"
        @toggle-select="emit('toggleSelect', 'task', row.item.id, $event)"
        @dragstart="emit('dragStartTask', row.item, $event)"
        @dragend="emit('dragEndTask', $event)"
      />
    </li>
  </ul>
</template>

<style scoped lang="scss">
.item-grid-view {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 0.65rem;
}

.item-grid-view__li {
  margin: 0;
  min-height: 100%;
}
</style>
