<script setup>
import { computed } from 'vue'
import Icon from '../atoms/Icon.vue'
import { t } from '../i18n/i18n'
import FolderTreeChild from './FolderTree.vue'

const props = defineProps({
  /** tree nodes from buildFolderTree */
  nodes: {
    type: Array,
    default: () => [],
  },
  expandedIds: {
    type: Object,
    default: () => ({}),
  },
  activeFolderId: {
    type: [Number, String, null],
    default: null,
  },
})

const emit = defineEmits(['toggle', 'select'])

const hasNodes = computed(() => props.nodes && props.nodes.length > 0)

function isExpanded(id) {
  return Boolean(props.expandedIds?.[Number(id)])
}

function onToggle(node, e) {
  e.stopPropagation()
  emit('toggle', node.id)
}

function onSelect(node) {
  emit('select', node.id)
}
</script>

<template>
  <ul v-if="hasNodes" class="folder-tree" role="tree" :aria-label="t('folders.treeLabel')">
    <li v-for="node in nodes" :key="node.id" class="folder-tree__node" role="treeitem" :aria-expanded="node.children?.length ? isExpanded(node.id) : undefined">
      <div
        class="folder-tree__row"
        :class="{ 'is-active': activeFolderId != null && Number(activeFolderId) === Number(node.id) }"
      >
        <button
          v-if="node.children && node.children.length"
          type="button"
          class="folder-tree__twisty"
          :aria-label="isExpanded(node.id) ? t('folders.collapse') : t('folders.expand')"
          @click="onToggle(node, $event)"
        >
          <Icon :name="isExpanded(node.id) ? 'chevron-down' : 'chevron-right'" aria-hidden="true" />
        </button>
        <span v-else class="folder-tree__twistySpacer" aria-hidden="true" />
        <button type="button" class="folder-tree__label" @click="onSelect(node)">
          <span class="folder-tree__emoji" :style="node.icon_bg_color ? { background: node.icon_bg_color } : undefined">{{ node.icon_emoji || '📁' }}</span>
          <span class="folder-tree__name">{{ node.name || t('folders.unnamed') }}</span>
        </button>
      </div>
      <FolderTreeChild
        v-if="node.children && node.children.length && isExpanded(node.id)"
        class="folder-tree__children"
        :nodes="node.children"
        :expanded-ids="expandedIds"
        :active-folder-id="activeFolderId"
        @toggle="emit('toggle', $event)"
        @select="emit('select', $event)"
      />
    </li>
  </ul>
  <p v-else class="folder-tree__empty">{{ t('folders.treeEmpty') }}</p>
</template>

<style scoped lang="scss">
.folder-tree {
  list-style: none;
  margin: 0;
  padding: 0;
}

.folder-tree__children {
  padding-left: 1rem;
  margin-top: 0.15rem;
}

.folder-tree__row {
  display: flex;
  align-items: center;
  gap: 0.15rem;
  border-radius: 0.35rem;

  &.is-active {
    background: var(--selection-bg, rgba(37, 99, 235, 0.1));
  }
}

.folder-tree__twisty,
.folder-tree__twistySpacer {
  width: 1.75rem;
  height: 1.75rem;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.folder-tree__twisty {
  border: none;
  background: transparent;
  cursor: pointer;
  color: inherit;
  padding: 0;
  border-radius: 0.25rem;

  &:hover {
    background: var(--surface-2, rgba(0, 0, 0, 0.06));
  }
}

.folder-tree__label {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  border: none;
  background: transparent;
  cursor: pointer;
  text-align: left;
  font: inherit;
  color: inherit;
  padding: 0.25rem 0.35rem;
  border-radius: 0.35rem;

  &:hover {
    background: var(--surface-2, rgba(0, 0, 0, 0.04));
  }
}

.folder-tree__emoji {
  width: 1.65rem;
  height: 1.65rem;
  border-radius: 0.35rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  background: var(--surface-2, #e5e7eb);
  flex-shrink: 0;
}

.folder-tree__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.folder-tree__empty {
  margin: 0;
  font-size: 0.9rem;
  opacity: 0.75;
}
</style>
