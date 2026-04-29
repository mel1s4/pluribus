<script setup>
import { t } from '../../i18n/i18n'

defineProps({
  searchQuery: { type: String, default: '' },
  /** @type {'all'|'open'|'done'} */
  statusFilter: { type: String, default: 'all' },
  /** '' = all folders, else folder id as string */
  folderScope: { type: String, default: '' },
  folders: { type: Array, default: () => [] },
  /** Hide search (e.g. when search lives inline on the page) */
  hideSearch: { type: Boolean, default: false },
})

const emit = defineEmits(['update:searchQuery', 'update:statusFilter', 'update:folderScope'])
</script>

<template>
  <div class="task-toolbar" :class="{ 'task-toolbar--no-search': hideSearch }">
    <label v-if="!hideSearch" class="task-toolbar__search">
      <span class="task-toolbar__sr">{{ t('tasks.searchLabel') }}</span>
      <input
        type="search"
        class="task-toolbar__input"
        :value="searchQuery"
        :placeholder="t('tasks.searchPlaceholder')"
        @input="emit('update:searchQuery', $event.target.value)"
      />
    </label>

    <div class="task-toolbar__segments" role="tablist" :aria-label="t('tasks.statusFilterLabel')">
      <button
        type="button"
        role="tab"
        class="task-toolbar__seg"
        :class="{ 'is-active': statusFilter === 'all' }"
        :aria-selected="statusFilter === 'all'"
        @click="emit('update:statusFilter', 'all')"
      >
        {{ t('tasks.filterAll') }}
      </button>
      <button
        type="button"
        role="tab"
        class="task-toolbar__seg"
        :class="{ 'is-active': statusFilter === 'open' }"
        :aria-selected="statusFilter === 'open'"
        @click="emit('update:statusFilter', 'open')"
      >
        {{ t('tasks.filterOpen') }}
      </button>
      <button
        type="button"
        role="tab"
        class="task-toolbar__seg"
        :class="{ 'is-active': statusFilter === 'done' }"
        :aria-selected="statusFilter === 'done'"
        @click="emit('update:statusFilter', 'done')"
      >
        {{ t('tasks.filterDone') }}
      </button>
    </div>

    <label class="task-toolbar__folder">
      <span>{{ t('tasks.folderScope') }}</span>
      <select
        class="task-toolbar__select"
        :value="folderScope"
        @change="emit('update:folderScope', $event.target.value)"
      >
        <option value="">{{ t('tasks.allFolders') }}</option>
        <option value="0">{{ t('tasks.unfiled') }}</option>
        <option v-for="folder in folders" :key="folder.id" :value="String(folder.id)">
          {{ folder.name }}
        </option>
      </select>
    </label>
  </div>
</template>

<style scoped lang="scss">
.task-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.75rem 1rem;
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--border, #e5e7eb);
}

.task-toolbar--no-search {
  border-bottom: none;
  padding-top: 0;
}

.task-toolbar__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.task-toolbar__search {
  flex: 1 1 12rem;
  min-width: 0;
  position: relative;
}

.task-toolbar__input {
  width: 100%;
  padding: 0.5rem 0.65rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  font: inherit;
  background: var(--surface, #fff);
}

.task-toolbar__segments {
  display: inline-flex;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  overflow: hidden;
  flex-shrink: 0;
}

.task-toolbar__seg {
  border: none;
  background: var(--surface, #fff);
  padding: 0.45rem 0.75rem;
  font: inherit;
  font-size: 0.875rem;
  cursor: pointer;
  color: var(--text-muted, #6b7280);
  &:not(:last-child) {
    border-right: 1px solid var(--border, #e5e7eb);
  }
  &:hover {
    background: var(--surface-2, rgba(0, 0, 0, 0.04));
  }
  &.is-active {
    background: var(--surface-2, rgba(0, 0, 0, 0.06));
    color: var(--text, #111827);
    font-weight: 600;
  }
}

.task-toolbar__folder {
  display: grid;
  gap: 0.25rem;
  font-size: 0.8rem;
  color: var(--text-muted, #6b7280);
  flex: 0 1 14rem;
}

.task-toolbar__select {
  padding: 0.45rem 0.5rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.45rem;
  font: inherit;
  min-width: 0;
}

@media (max-width: 767px) {
  .task-toolbar {
    flex-direction: column;
    align-items: stretch;
  }
  .task-toolbar__segments {
    width: 100%;
    justify-content: stretch;
  }
  .task-toolbar__seg {
    flex: 1;
    text-align: center;
  }
  .task-toolbar__folder {
    flex: none;
    width: 100%;
  }
  .task-toolbar__select {
    width: 100%;
  }
}
</style>
