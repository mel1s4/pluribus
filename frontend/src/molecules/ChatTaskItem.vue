<script setup>
import Icon from '../atoms/Icon.vue'
import DragHandle from '../atoms/DragHandle.vue'
import SelectionCheckbox from '../atoms/SelectionCheckbox.vue'
import { t } from '../i18n/i18n'

defineProps({
  kind: {
    type: String,
    required: true,
    validator: (v) => v === 'chat' || v === 'task',
  },
  item: {
    type: Object,
    required: true,
  },
  selected: {
    type: Boolean,
    default: false,
  },
  showCheckbox: {
    type: Boolean,
    default: false,
  },
  draggable: {
    type: Boolean,
    default: true,
  },
  /** 'list' | 'grid' */
  layout: {
    type: String,
    default: 'list',
  },
})

const emit = defineEmits(['open', 'toggleSelect', 'dragstart', 'dragend'])
</script>

<template>
  <div
    class="chat-task-item"
    :class="[
      `chat-task-item--${layout}`,
      { 'is-selected': selected },
    ]"
  >
    <SelectionCheckbox
      v-if="showCheckbox"
      class="chat-task-item__check"
      :model-value="selected"
      :aria-label="t('folders.selectItem')"
      @update:model-value="emit('toggleSelect', $event)"
    />
    <DragHandle
      v-if="draggable"
      class="chat-task-item__drag"
      :label="kind === 'chat' ? t('folders.dragChat') : t('folders.dragTask')"
    />
    <button
      type="button"
      class="chat-task-item__body"
      :draggable="draggable"
      @click="emit('open')"
      @dragstart="emit('dragstart', $event)"
      @dragend="emit('dragend', $event)"
    >
      <span
        v-if="kind === 'chat'"
        class="chat-task-item__icon"
        :style="item.icon_bg_color ? { background: item.icon_bg_color } : undefined"
      >
        {{ item.icon_emoji || '💬' }}
      </span>
      <Icon v-else name="list-check" class="chat-task-item__taskGlyph" aria-hidden="true" />
      <span class="chat-task-item__title">{{ item.title || (kind === 'chat' ? t('folders.chatUntitled') : t('tasks.untitled')) }}</span>
      <span v-if="kind === 'task' && item.completed_at" class="chat-task-item__badge">{{ t('folders.taskDone') }}</span>
    </button>
  </div>
</template>

<style scoped lang="scss">
.chat-task-item {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  padding: 0.35rem 0.5rem;
  background: var(--bg);

  &.is-selected {
    background: var(--selection-bg, rgba(37, 99, 235, 0.08));
    border-color: var(--accent, #2563eb);
  }
}

.chat-task-item--grid {
  flex-direction: column;
  align-items: stretch;
  height: 100%;

  .chat-task-item__body {
    flex-direction: column;
    align-items: flex-start;
  }
}

.chat-task-item__check {
  flex-shrink: 0;
}

.chat-task-item__drag {
  flex-shrink: 0;
  cursor: grab;
}

.chat-task-item__body {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
  font: inherit;
  color: inherit;
  padding: 0.25rem;
  border-radius: 0.35rem;

  &:hover {
    background: var(--surface-2, rgba(0, 0, 0, 0.04));
  }
}

.chat-task-item__icon {
  width: 2rem;
  height: 2rem;
  border-radius: 0.4rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  background: var(--surface-2, #e5e7eb);
  flex-shrink: 0;
}

.chat-task-item__taskGlyph {
  width: 1.25rem;
  height: 1.25rem;
  flex-shrink: 0;
  opacity: 0.85;
}

.chat-task-item__title {
  flex: 1;
  min-width: 0;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-task-item--grid .chat-task-item__title {
  white-space: normal;
}

.chat-task-item__badge {
  font-size: 0.7rem;
  text-transform: uppercase;
  opacity: 0.7;
}
</style>
