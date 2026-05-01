<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import { refreshInAppNotificationsUnread } from '../../composables/useInAppNotificationsUnread.js'
import { t } from '../../i18n/i18n'
import {
  fetchNotificationsIndex,
  markAllNotificationsRead,
  markNotificationsRead,
} from '../../services/notificationsApi.js'

const router = useRouter()

const items = ref([])
const loading = ref(true)
const error = ref('')
const markAllBusy = ref(false)

function formatWhen(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return String(iso)
  }
}

async function load() {
  loading.value = true
  error.value = ''
  const res = await fetchNotificationsIndex({ page: 1, perPage: 50 })
  loading.value = false
  if (!res.ok) {
    error.value = t('notifications.loadError')
    items.value = []
    return
  }
  const rows = Array.isArray(res.data?.data) ? res.data.data : []
  items.value = rows
}

async function onMarkAllRead() {
  markAllBusy.value = true
  const res = await markAllNotificationsRead()
  markAllBusy.value = false
  if (res.ok) {
    await refreshInAppNotificationsUnread()
    await load()
  }
}

/**
 * @param {Record<string, unknown>|null|undefined} action
 */
function hrefForAction(action) {
  if (!action || typeof action !== 'object') return null
  const type = String(action.type || '')
  if (type === 'wallet_movement') {
    const id = action.wallet_transaction_id
    const slug = action.community_slug
    if (id == null) return null
    if (typeof slug === 'string' && slug.trim() !== '') {
      return `/${encodeURIComponent(slug.trim())}/wallet/movements/${id}`
    }
    return `/wallet/movements/${id}`
  }
  if (type === 'place_order') {
    if (action.place_id == null || action.order_id == null) return null
    return `/my-places/${action.place_id}/orders/${action.order_id}`
  }
  if (type === 'chat') {
    if (action.chat_id == null) return null
    return `/chats/${action.chat_id}`
  }
  if (type === 'buyer_order') {
    if (action.order_id == null) return null
    return `/orders/${action.order_id}`
  }
  if (type === 'place') {
    if (action.place_id == null) return null
    return `/my-places/${action.place_id}`
  }
  return null
}

/**
 * @param {{ id: string, action?: unknown, read_at?: string|null }} row
 */
async function onOpenItem(row) {
  const href = hrefForAction(
    row.action && typeof row.action === 'object' ? /** @type {Record<string, unknown>} */ (row.action) : null,
  )
  await markNotificationsRead([row.id])
  await refreshInAppNotificationsUnread()
  row.read_at = new Date().toISOString()
  if (href) {
    await router.push(href)
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="notification-page page page--notifications">
    <PageToolbarTitle route-key="notifications">
      <Title tag="h1">{{ t('notifications.title') }}</Title>
    </PageToolbarTitle>

    <div class="notification-page__toolbar">
      <Button
        type="button"
        variant="secondary"
        size="sm"
        :disabled="markAllBusy || items.length === 0"
        @click="onMarkAllRead"
      >
        {{ t('notifications.markAllRead') }}
      </Button>
    </div>

    <p v-if="loading" class="notification-page__muted">{{ t('notifications.loading') }}</p>
    <p v-else-if="error" class="notification-page__error">{{ error }}</p>
    <p v-else-if="items.length === 0" class="notification-page__muted">{{ t('notifications.empty') }}</p>

    <ul v-else class="notification-page__list" role="list">
      <li
        v-for="row in items"
        :key="row.id"
        class="notification-page__item"
        :class="{ 'notification-page__item--unread': !row.read_at }"
      >
        <button type="button" class="notification-page__itemBtn" @click="onOpenItem(row)">
          <span class="notification-page__itemTitle">{{ row.title }}</span>
          <span class="notification-page__itemBody">{{ row.body }}</span>
          <span class="notification-page__itemMeta">{{ formatWhen(row.created_at) }}</span>
          <span v-if="hrefForAction(row.action)" class="notification-page__itemHint">{{
            t('notifications.openHint')
          }}</span>
        </button>
      </li>
    </ul>
  </section>
</template>

<style lang="scss" scoped>
.notification-page {
  padding: 2rem;
}

.notification-page__toolbar {
  margin-top: 1rem;
  display: flex;
  justify-content: flex-end;
}

.notification-page__muted {
  margin-top: 0.75rem;
  opacity: 0.8;
}

.notification-page__error {
  margin-top: 0.75rem;
  color: var(--danger, #b91c1c);
}

.notification-page__list {
  list-style: none;
  margin: 1.25rem 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.notification-page__item {
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--bg);
}

.notification-page__item--unread {
  border-color: var(--accent, #2563eb);
}

.notification-page__itemBtn {
  display: block;
  width: 100%;
  text-align: left;
  padding: 0.85rem 1rem;
  border: none;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.notification-page__itemTitle {
  display: block;
  font-weight: 600;
}

.notification-page__itemBody {
  display: block;
  margin-top: 0.35rem;
  opacity: 0.9;
  white-space: pre-wrap;
  word-break: break-word;
}

.notification-page__itemMeta {
  display: block;
  margin-top: 0.5rem;
  font-size: 0.85rem;
  opacity: 0.65;
}

.notification-page__itemHint {
  display: block;
  margin-top: 0.35rem;
  font-size: 0.85rem;
  opacity: 0.75;
}
</style>
