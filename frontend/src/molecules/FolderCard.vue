<script setup>
import Icon from '../atoms/Icon.vue'
import DragHandle from '../atoms/DragHandle.vue'
import { t } from '../i18n/i18n'

defineProps({
  folder: {
    type: Object,
    required: true,
  },
  draggable: {
    type: Boolean,
    default: false,
  },
  showDragHandle: {
    type: Boolean,
    default: false,
  },
  /** 'list' | 'grid' */
  layout: {
    type: String,
    default: 'grid',
  },
  isDropOver: {
    type: Boolean,
    default: false,
  },
  chatCount: {
    type: Number,
    default: 0,
  },
  taskCount: {
    type: Number,
    default: 0,
  },
})

const emit = defineEmits(['open', 'menu', 'dragstart', 'dragend', 'dragover', 'dragleave', 'drop'])
</script>

<template>
  <article
    class="folder-card"
    :class="[
      `folder-card--${layout}`,
      { 'is-drop-over': isDropOver, 'folder-card--draggable': draggable },
    ]"
    @dragover.prevent="$emit('dragover', $event)"
    @dragleave="$emit('dragleave', $event)"
    @drop.prevent="$emit('drop', $event)"
  >
    <div class="folder-card__main">
      <DragHandle
        v-if="showDragHandle && draggable"
        class="folder-card__drag"
        :label="t('folders.dragFolder')"
        @pointerdown.stop
      />
      <button
        type="button"
        class="folder-card__open"
        :draggable="draggable"
        @click="emit('open', folder)"
        @dragstart="emit('dragstart', $event)"
        @dragend="emit('dragend', $event)"
      >
        <span
          class="folder-card__icon"
          :style="folder.icon_bg_color ? { background: folder.icon_bg_color } : undefined"
        >
          {{ folder.icon_emoji || '📁' }}
        </span>
        <span class="folder-card__name">{{ folder.name || t('folders.unnamed') }}</span>
        <span v-if="chatCount + taskCount > 0" class="folder-card__meta">
          {{ chatCount }} {{ t('folders.stats.chatsShort') }} · {{ taskCount }} {{ t('folders.stats.tasksShort') }}
        </span>
      </button>
    </div>
    <details class="folder-card__menu" @click.stop>
      <summary class="folder-card__kebab" :aria-label="t('folders.actions')">
        <Icon name="ellipsis-vertical" aria-hidden="true" />
      </summary>
      <div role="menu" class="folder-card__menuPanel">
        <button type="button" role="menuitem" class="folder-card__menuItem" @click="emit('menu', { action: 'rename', folder })">
          {{ t('folders.rename') }}
        </button>
        <button type="button" role="menuitem" class="folder-card__menuItem" @click="emit('menu', { action: 'editIcon', folder })">
          {{ t('folders.editIcon') }}
        </button>
        <button type="button" role="menuitem" class="folder-card__menuItem folder-card__menuItem--danger" @click="emit('menu', { action: 'delete', folder })">
          {{ t('folders.delete') }}
        </button>
      </div>
    </details>
  </article>
</template>

<style scoped lang="scss">
.folder-card {
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  border: 1px solid var(--border);
  border-radius: 0.65rem;
  background: var(--bg);
  padding: 0.5rem 0.5rem 0.5rem 0.35rem;
  position: relative;

  &.is-drop-over {
    outline: 2px dashed var(--accent, #2563eb);
    outline-offset: 2px;
  }
}

.folder-card__main {
  display: flex;
  align-items: flex-start;
  gap: 0.25rem;
  flex: 1;
  min-width: 0;
}

.folder-card__drag {
  flex-shrink: 0;
  margin-top: 0.35rem;
}

.folder-card__open {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.35rem;
  border: none;
  background: transparent;
  cursor: pointer;
  text-align: left;
  padding: 0.25rem 0.35rem;
  border-radius: 0.4rem;
  font: inherit;
  color: inherit;

  &:hover {
    background: var(--surface-2, rgba(0, 0, 0, 0.04));
  }
}

.folder-card--list .folder-card__open {
  flex-direction: row;
  align-items: center;
}

.folder-card__icon {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  background: var(--surface-2, #e5e7eb);
  flex-shrink: 0;
}

.folder-card__name {
  font-weight: 600;
  word-break: break-word;
}

.folder-card__meta {
  font-size: 0.8rem;
  opacity: 0.75;
}

.folder-card--list .folder-card__meta {
  margin-left: auto;
}

.folder-card__kebab {
  list-style: none;
  cursor: pointer;
  padding: 0.35rem;
  border-radius: 0.35rem;

  &::-webkit-details-marker {
    display: none;
  }

  &:hover {
    background: var(--surface-2, rgba(0, 0, 0, 0.06));
  }
}

.folder-card__menu {
  position: relative;
}

.folder-card__menuPanel {
  position: absolute;
  right: 0;
  top: 100%;
  z-index: 5;
  min-width: 10rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--bg);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
  padding: 0.25rem;
  margin-top: 0.15rem;
}

.folder-card__menuItem {
  display: block;
  width: 100%;
  text-align: left;
  border: none;
  background: transparent;
  padding: 0.45rem 0.6rem;
  border-radius: 0.35rem;
  font: inherit;
  cursor: pointer;
  color: inherit;

  &:hover {
    background: var(--surface-2, rgba(0, 0, 0, 0.06));
  }

  &--danger {
    color: var(--danger, #b91c1c);
  }
}
</style>
