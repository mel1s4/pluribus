<script setup>
import { computed } from 'vue'
import { t } from '../../i18n/i18n'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
})

const unreadChats = computed(() => (
  Array.isArray(props.items)
    ? props.items.slice(0, 5)
    : []
))

function formatChatTimestamp(chat) {
  const source = chat?.last_message_at || chat?.updated_at || chat?.created_at || null
  if (!source) return ''
  const parsed = new Date(source)
  if (Number.isNaN(parsed.getTime())) return ''
  return parsed.toLocaleDateString()
}

function chatTitle(chat) {
  const title = String(chat?.title || '').trim()
  return title || t('chats.defaultConversation')
}
</script>

<template>
  <article class="dashboard-widget">
    <h2 class="dashboard-widget__title">{{ t('dashboard.inbox.title') }}</h2>
    <p class="dashboard-widget__body">{{ t('dashboard.inbox.body') }}</p>

    <ul v-if="unreadChats.length" class="dashboard-widget__list">
      <li v-for="chat in unreadChats" :key="chat.id">
        <RouterLink :to="`/chats/${chat.id}`" class="dashboard-widget__itemLink">
          <span class="dashboard-widget__itemTitle">{{ chatTitle(chat) }}</span>
          <span class="dashboard-widget__itemMeta">
            {{ t('dashboard.inbox.unreadLabel').replace('{count}', String(chat.unread_count || 0)) }}
          </span>
          <span v-if="formatChatTimestamp(chat)" class="dashboard-widget__itemDate">
            {{ formatChatTimestamp(chat) }}
          </span>
        </RouterLink>
      </li>
    </ul>
    <p v-else class="dashboard-widget__empty">{{ t('dashboard.inbox.empty') }}</p>
  </article>
</template>

<style scoped lang="scss">
.dashboard-widget {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--bg);
  padding: 1rem;
}

.dashboard-widget__title {
  margin: 0;
  font-size: 1rem;
}

.dashboard-widget__body {
  margin: 0;
  font-size: 0.92rem;
}

.dashboard-widget__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.dashboard-widget__itemLink {
  display: flex;
  flex-direction: column;
  gap: 0.12rem;
  text-decoration: none;
  color: inherit;
}

.dashboard-widget__itemTitle {
  font-size: 0.92rem;
  font-weight: 600;
}

.dashboard-widget__itemMeta,
.dashboard-widget__itemDate,
.dashboard-widget__empty {
  margin: 0;
  font-size: 0.82rem;
  opacity: 0.82;
}
</style>
