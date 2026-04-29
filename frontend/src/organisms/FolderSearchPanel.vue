<script setup>
import Icon from '../atoms/Icon.vue'
import { t } from '../i18n/i18n'

defineProps({
  query: {
    type: String,
    default: '',
  },
  filterType: {
    type: String,
    default: 'all',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  folders: {
    type: Array,
    default: () => [],
  },
  chats: {
    type: Array,
    default: () => [],
  },
  tasks: {
    type: Array,
    default: () => [],
  },
  recentQueries: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:query', 'update:filterType', 'pickRecent', 'openFolder', 'openChat', 'openTask'])
</script>

<template>
  <section class="folder-search-panel" aria-label="Search">
    <div class="folder-search-panel__row">
      <Icon name="magnifying-glass" class="folder-search-panel__icon" aria-hidden="true" />
      <input
        :value="query"
        type="search"
        class="folder-search-panel__input"
        :placeholder="t('folders.search')"
        autocomplete="off"
        @input="emit('update:query', $event.target.value)"
      >
      <span v-if="loading" class="folder-search-panel__loading">{{ t('folders.searchLoading') }}</span>
    </div>
    <div class="folder-search-panel__filters">
      <label class="folder-search-panel__filter">
        <span class="sr-only">{{ t('folders.filterType') }}</span>
        <select
          :value="filterType"
          @change="emit('update:filterType', $event.target.value)"
        >
          <option value="all">{{ t('folders.filterAll') }}</option>
          <option value="folder">{{ t('folders.filterFolders') }}</option>
          <option value="chat">{{ t('folders.filterChats') }}</option>
          <option value="task">{{ t('folders.filterTasks') }}</option>
        </select>
      </label>
    </div>
    <div v-if="recentQueries.length && !query.trim()" class="folder-search-panel__recent">
      <span class="folder-search-panel__recentLabel">{{ t('folders.recentSearches') }}</span>
      <button
        v-for="q in recentQueries"
        :key="q"
        type="button"
        class="folder-search-panel__chip"
        @click="emit('pickRecent', q)"
      >
        {{ q }}
      </button>
    </div>
    <div v-if="query.trim()" class="folder-search-panel__results">
      <div v-if="folders.length" class="folder-search-panel__block">
        <h3 class="folder-search-panel__h">{{ t('folders.searchFoldersHeading') }}</h3>
        <ul class="folder-search-panel__list">
          <li v-for="f in folders" :key="'f-' + f.id">
            <button type="button" class="folder-search-panel__hit" @click="emit('openFolder', f.id)">
              {{ f.icon_emoji || '📁' }} {{ f.name }}
            </button>
          </li>
        </ul>
      </div>
      <div v-if="chats.length" class="folder-search-panel__block">
        <h3 class="folder-search-panel__h">{{ t('folders.searchChatsHeading') }}</h3>
        <ul class="folder-search-panel__list">
          <li v-for="c in chats" :key="'c-' + c.id">
            <button type="button" class="folder-search-panel__hit" @click="emit('openChat', c.id)">
              {{ c.title || t('folders.chatUntitled') }}
            </button>
          </li>
        </ul>
      </div>
      <div v-if="tasks.length" class="folder-search-panel__block">
        <h3 class="folder-search-panel__h">{{ t('folders.searchTasksHeading') }}</h3>
        <ul class="folder-search-panel__list">
          <li v-for="tk in tasks" :key="'t-' + tk.id">
            <button type="button" class="folder-search-panel__hit" @click="emit('openTask', tk.id)">
              {{ tk.title || t('tasks.untitled') }}
            </button>
          </li>
        </ul>
      </div>
      <p v-if="!loading && !folders.length && !chats.length && !tasks.length" class="folder-search-panel__empty">
        {{ t('folders.searchNoResults') }}
      </p>
    </div>
  </section>
</template>

<style scoped lang="scss">
.folder-search-panel {
  border: 1px solid var(--border);
  border-radius: 0.65rem;
  padding: 0.85rem;
  background: var(--bg);
  margin-bottom: 1rem;
}

.folder-search-panel__row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.folder-search-panel__icon {
  width: 1.1rem;
  height: 1.1rem;
  flex-shrink: 0;
  opacity: 0.75;
}

.folder-search-panel__input {
  flex: 1;
  min-width: 0;
}

.folder-search-panel__loading {
  font-size: 0.8rem;
  opacity: 0.75;
  flex-shrink: 0;
}

.folder-search-panel__filters {
  margin-top: 0.65rem;
}

.folder-search-panel__filter select {
  width: 100%;
  max-width: 14rem;
}

.folder-search-panel__recent {
  margin-top: 0.75rem;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
}

.folder-search-panel__recentLabel {
  font-size: 0.85rem;
  opacity: 0.8;
  width: 100%;
}

.folder-search-panel__chip {
  font-size: 0.8rem;
  padding: 0.25rem 0.5rem;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--surface-1, rgba(0, 0, 0, 0.03));
  cursor: pointer;
  color: inherit;
}

.folder-search-panel__results {
  margin-top: 0.85rem;
}

.folder-search-panel__block {
  margin-bottom: 0.75rem;
}

.folder-search-panel__h {
  margin: 0 0 0.35rem;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  opacity: 0.75;
}

.folder-search-panel__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.folder-search-panel__hit {
  display: block;
  width: 100%;
  text-align: left;
  border: none;
  background: transparent;
  padding: 0.35rem 0.25rem;
  border-radius: 0.35rem;
  cursor: pointer;
  font: inherit;
  color: var(--link, #2563eb);

  &:hover {
    text-decoration: underline;
  }
}

.folder-search-panel__empty {
  margin: 0;
  font-size: 0.9rem;
  opacity: 0.8;
}

.sr-only {
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
</style>
