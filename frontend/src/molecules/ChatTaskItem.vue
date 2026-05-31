<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import Icon from '../atoms/Icon.vue'
import DragHandle from '../atoms/DragHandle.vue'
import SelectionCheckbox from '../atoms/SelectionCheckbox.vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  kind: {
    type: String,
    required: true,
    validator: (v) => v === 'chat' || v === 'task' || v === 'note',
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
  showMenuActions: {
    type: Boolean,
    default: true,
  },
  /** Unread count for chat rows */
  unreadCount: {
    type: Number,
    default: 0,
  },
})

const emit = defineEmits(['open', 'toggleSelect', 'dragstart', 'dragend', 'menuAction'])

const kebabRef = ref(null)
const ctxOpen = ref(false)
const ctxX = ref(0)
const ctxY = ref(0)

function closeKebab() {
  const el = kebabRef.value
  if (el) el.open = false
}

function closeCtx() {
  ctxOpen.value = false
}

function onDocClick() {
  closeCtx()
}

function onDocKeydown(e) {
  if (e.key === 'Escape') closeCtx()
}

onMounted(() => {
  document.addEventListener('click', onDocClick)
  document.addEventListener('keydown', onDocKeydown)
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
  document.removeEventListener('keydown', onDocKeydown)
})

function onContextMenu(e) {
  if (!props.showMenuActions) return
  e.preventDefault()
  ctxX.value = e.clientX
  ctxY.value = e.clientY
  ctxOpen.value = true
}

function fireMenu(action) {
  closeKebab()
  closeCtx()
  emit('menuAction', { action, kind: props.kind, item: props.item })
}

function ctxFire(action, e) {
  e?.stopPropagation?.()
  fireMenu(action)
}
</script>

<template>
  <div
    class="chat-task-item"
    :class="[
      `chat-task-item--${layout}`,
      { 'is-selected': selected },
    ]"
    @contextmenu="onContextMenu"
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
      :label="kind === 'chat' ? t('folders.dragChat') : kind === 'note' ? t('folders.dragNote') : t('folders.dragTask')"
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
      <Icon v-else-if="kind === 'task'" name="list-check" class="chat-task-item__taskGlyph" aria-hidden="true" />
      <Icon v-else name="file-lines" class="chat-task-item__taskGlyph" aria-hidden="true" />
      <span class="chat-task-item__title">{{ item.title || (kind === 'chat' ? t('folders.chatUntitled') : kind === 'note' ? t('notes.untitled') : t('tasks.untitled')) }}</span>
      <span v-if="kind === 'task' && item.completed_at" class="chat-task-item__badge">{{ t('folders.taskDone') }}</span>
      <span v-if="kind === 'chat' && unreadCount > 0" class="chat-task-item__unread">
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>
    <details
      v-if="showMenuActions"
      ref="kebabRef"
      class="chat-task-item__kebab"
      @click.stop
    >
      <summary class="chat-task-item__kebabTrigger" :aria-label="t('folders.explorer.itemActions')">
        <Icon name="ellipsis-vertical" aria-hidden="true" />
      </summary>
      <div class="chat-task-item__kebabMenu" role="menu" @click.stop>
        <button type="button" class="chat-task-item__kebabItem" role="menuitem" @click="fireMenu('open')">
          {{ t('folders.explorer.open') }}
        </button>
        <template v-if="kind === 'chat'">
          <button type="button" class="chat-task-item__kebabItem" role="menuitem" @click="fireMenu('rename')">
            {{ t('chats.rename') }}
          </button>
          <button type="button" class="chat-task-item__kebabItem" role="menuitem" @click="fireMenu('edit')">
            {{ t('chats.edit') }}
          </button>
          <button type="button" class="chat-task-item__kebabItem" role="menuitem" @click="fireMenu('move')">
            {{ t('folders.explorer.moveToFolder') }}
          </button>
          <button type="button" class="chat-task-item__kebabItem chat-task-item__kebabItem--danger" role="menuitem" @click="fireMenu('delete')">
            {{ t('chats.delete') }}
          </button>
        </template>
        <template v-else-if="kind === 'task'">
          <button type="button" class="chat-task-item__kebabItem" role="menuitem" @click="fireMenu('toggleComplete')">
            {{ item.completed_at ? t('folders.explorer.markOpen') : t('folders.explorer.markDone') }}
          </button>
          <button type="button" class="chat-task-item__kebabItem" role="menuitem" @click="fireMenu('toggleStar')">
            {{ item.highlighted ? t('folders.explorer.unstar') : t('folders.explorer.star') }}
          </button>
          <button type="button" class="chat-task-item__kebabItem" role="menuitem" @click="fireMenu('move')">
            {{ t('folders.explorer.moveToFolder') }}
          </button>
          <button type="button" class="chat-task-item__kebabItem chat-task-item__kebabItem--danger" role="menuitem" @click="fireMenu('delete')">
            {{ t('tasks.delete') }}
          </button>
        </template>
        <template v-else>
          <button type="button" class="chat-task-item__kebabItem" role="menuitem" @click="fireMenu('move')">
            {{ t('folders.explorer.moveToFolder') }}
          </button>
          <button type="button" class="chat-task-item__kebabItem chat-task-item__kebabItem--danger" role="menuitem" @click="fireMenu('delete')">
            {{ t('notes.delete') }}
          </button>
        </template>
      </div>
    </details>

    <Teleport to="body">
      <div
        v-if="ctxOpen"
        class="chat-task-item__ctxBackdrop"
        aria-hidden="true"
        @click="closeCtx"
      />
      <ul
        v-if="ctxOpen"
        class="chat-task-item__ctxMenu"
        role="menu"
        :style="{ left: `${ctxX}px`, top: `${ctxY}px` }"
        @click.stop
      >
        <li>
          <button type="button" class="chat-task-item__ctxItem" role="menuitem" @click="ctxFire('open', $event)">
            {{ t('folders.explorer.open') }}
          </button>
        </li>
        <template v-if="kind === 'chat'">
          <li>
            <button type="button" class="chat-task-item__ctxItem" role="menuitem" @click="ctxFire('rename', $event)">
              {{ t('chats.rename') }}
            </button>
          </li>
          <li>
            <button type="button" class="chat-task-item__ctxItem" role="menuitem" @click="ctxFire('edit', $event)">
              {{ t('chats.edit') }}
            </button>
          </li>
          <li>
            <button type="button" class="chat-task-item__ctxItem" role="menuitem" @click="ctxFire('move', $event)">
              {{ t('folders.explorer.moveToFolder') }}
            </button>
          </li>
          <li>
            <button type="button" class="chat-task-item__ctxItem chat-task-item__ctxItem--danger" role="menuitem" @click="ctxFire('delete', $event)">
              {{ t('chats.delete') }}
            </button>
          </li>
        </template>
        <template v-else-if="kind === 'task'">
          <li>
            <button type="button" class="chat-task-item__ctxItem" role="menuitem" @click="ctxFire('toggleComplete', $event)">
              {{ item.completed_at ? t('folders.explorer.markOpen') : t('folders.explorer.markDone') }}
            </button>
          </li>
          <li>
            <button type="button" class="chat-task-item__ctxItem" role="menuitem" @click="ctxFire('toggleStar', $event)">
              {{ item.highlighted ? t('folders.explorer.unstar') : t('folders.explorer.star') }}
            </button>
          </li>
          <li>
            <button type="button" class="chat-task-item__ctxItem" role="menuitem" @click="ctxFire('move', $event)">
              {{ t('folders.explorer.moveToFolder') }}
            </button>
          </li>
          <li>
            <button type="button" class="chat-task-item__ctxItem chat-task-item__ctxItem--danger" role="menuitem" @click="ctxFire('delete', $event)">
              {{ t('tasks.delete') }}
            </button>
          </li>
        </template>
        <template v-else>
          <li>
            <button type="button" class="chat-task-item__ctxItem" role="menuitem" @click="ctxFire('move', $event)">
              {{ t('folders.explorer.moveToFolder') }}
            </button>
          </li>
          <li>
            <button type="button" class="chat-task-item__ctxItem chat-task-item__ctxItem--danger" role="menuitem" @click="ctxFire('delete', $event)">
              {{ t('notes.delete') }}
            </button>
          </li>
        </template>
      </ul>
    </Teleport>
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

.chat-task-item__unread {
  flex-shrink: 0;
  min-width: 1.2rem;
  height: 1.2rem;
  padding: 0 0.3rem;
  border-radius: 999px;
  background: #ef4444;
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
}

.chat-task-item__kebab {
  position: relative;
  flex-shrink: 0;
  list-style: none;
}

.chat-task-item__kebabTrigger {
  list-style: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  margin: 0;
  padding: 0;
  border: none;
  border-radius: 0.35rem;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.chat-task-item__kebabTrigger::-webkit-details-marker {
  display: none;
}

.chat-task-item__kebabTrigger:hover {
  background: var(--surface-2, rgba(0, 0, 0, 0.06));
}

.chat-task-item__kebabMenu {
  position: absolute;
  right: 0;
  top: 100%;
  z-index: 5;
  margin-top: 0.2rem;
  min-width: 11rem;
  padding: 0.35rem;
  background: var(--bg, #fff);
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

.chat-task-item__kebabItem {
  display: block;
  width: 100%;
  margin: 0;
  padding: 0.45rem 0.55rem;
  text-align: left;
  border: none;
  border-radius: 0.35rem;
  background: transparent;
  color: inherit;
  font: inherit;
  font-size: 0.9rem;
  cursor: pointer;
}

.chat-task-item__kebabItem:hover {
  background: var(--surface-2, rgba(0, 0, 0, 0.06));
}

.chat-task-item__kebabItem--danger:hover {
  background: rgba(220, 38, 38, 0.12);
  color: #dc2626;
}

.chat-task-item__ctxBackdrop {
  position: fixed;
  inset: 0;
  z-index: 9998;
}

.chat-task-item__ctxMenu {
  position: fixed;
  z-index: 9999;
  margin: 0;
  padding: 0.35rem;
  list-style: none;
  min-width: 11rem;
  background: var(--bg, #fff);
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.chat-task-item__ctxItem {
  display: block;
  width: 100%;
  margin: 0;
  padding: 0.45rem 0.55rem;
  text-align: left;
  border: none;
  border-radius: 0.35rem;
  background: transparent;
  color: inherit;
  font: inherit;
  font-size: 0.9rem;
  cursor: pointer;
}

.chat-task-item__ctxItem:hover {
  background: var(--surface-2, rgba(0, 0, 0, 0.06));
}

.chat-task-item__ctxItem--danger:hover {
  background: rgba(220, 38, 38, 0.12);
  color: #dc2626;
}
</style>
