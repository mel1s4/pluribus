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
  /** (type, id) => boolean */
  isSelected: {
    type: Function,
    default: () => false,
  },
  /** (chatId) => number */
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
  <ul class="item-list-view" role="list">
    <li v-for="row in items" :key="row.key" class="item-list-view__li" role="listitem">
      <ChatTaskItem
        v-if="row.kind === 'chat'"
        kind="chat"
        :item="row.item"
        layout="list"
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
        layout="list"
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
        layout="list"
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
.item-list-view {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.item-list-view__li {
  margin: 0;
}
</style>
