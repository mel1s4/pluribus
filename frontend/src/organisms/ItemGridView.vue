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
  getChatUnread: {
    type: Function,
    default: () => 0,
  },
})

const emit = defineEmits([
  'openChat',
  'openTask',
  'openNote',
  'toggleSelect',
  'dragStartChat',
  'dragEndChat',
  'dragStartTask',
  'dragEndTask',
  'dragStartNote',
  'dragEndNote',
  'menuAction',
])
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
        :unread-count="getChatUnread(row.item.id)"
        @open="emit('openChat', row.item)"
        @toggle-select="emit('toggleSelect', 'chat', row.item.id, $event)"
        @dragstart="emit('dragStartChat', row.item, $event)"
        @dragend="emit('dragEndChat', $event)"
        @menu-action="emit('menuAction', $event)"
      />
      <ChatTaskItem
        v-else-if="row.kind === 'task'"
        kind="task"
        :item="row.item"
        layout="grid"
        :selected="isSelected('task', row.item.id)"
        :show-checkbox="showCheckboxes"
        @open="emit('openTask', row.item)"
        @toggle-select="emit('toggleSelect', 'task', row.item.id, $event)"
        @dragstart="emit('dragStartTask', row.item, $event)"
        @dragend="emit('dragEndTask', $event)"
        @menu-action="emit('menuAction', $event)"
      />
      <ChatTaskItem
        v-else-if="row.kind === 'note'"
        kind="note"
        :item="row.item"
        layout="grid"
        :selected="isSelected('note', row.item.id)"
        :show-checkbox="showCheckboxes"
        @open="emit('openNote', row.item)"
        @toggle-select="emit('toggleSelect', 'note', row.item.id, $event)"
        @dragstart="emit('dragStartNote', row.item, $event)"
        @dragend="emit('dragEndNote', $event)"
        @menu-action="emit('menuAction', $event)"
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
