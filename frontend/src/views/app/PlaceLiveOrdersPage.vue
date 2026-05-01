<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import { useCommunity } from '../../composables/useCommunity'
import { usePlaceOrdersRealtime } from '../../composables/usePlaceOrdersRealtime.js'
import { t } from '../../i18n/i18n'
import { fetchPlaceOrders, patchPlaceOrderStatus } from '../../services/ordersApi'
import { formatOfferPrice } from '../../utils/formatPrice'

const route = useRoute()
const router = useRouter()
const { communityCurrencyCode } = useCommunity()

const placeId = computed(() => String(route.params.placeId || ''))
const placeIdNum = computed(() => {
  const n = Number(placeId.value)
  return Number.isFinite(n) && n > 0 ? n : null
})

const loading = ref(true)
const error = ref('')
const orders = ref([])
const busyId = ref(0)

const STATUS_OPTIONS = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled']

const POLL_MS = 16000
/** @type {ReturnType<typeof setInterval> | null} */
let pollTimer = null

function parseListParam(raw) {
  if (typeof raw !== 'string' || !raw.trim()) {
    return []
  }
  return raw
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean)
}

const filtersFromRoute = computed(() => {
  const q = route.query
  const offerRaw = typeof q.offers === 'string' ? q.offers : ''
  const offerIds = parseListParam(offerRaw)
    .map((s) => Number(s))
    .filter((n) => Number.isFinite(n) && n > 0)
  const tags = parseListParam(typeof q.tags === 'string' ? q.tags : '')
  const statuses = parseListParam(typeof q.statuses === 'string' ? q.statuses : '').filter((s) =>
    STATUS_OPTIONS.includes(s),
  )
  return {
    place_offer_ids: offerIds.length ? offerIds : undefined,
    tags: tags.length ? tags : undefined,
    statuses: statuses.length ? statuses : undefined,
  }
})

const filterSummary = computed(() => {
  const f = filtersFromRoute.value
  const parts = []
  if (f.place_offer_ids?.length) {
    parts.push(`${t('orders.filterOffers')}: ${f.place_offer_ids.join(', ')}`)
  }
  if (f.tags?.length) {
    parts.push(`${t('orders.filterTags')}: ${f.tags.join(', ')}`)
  }
  if (f.statuses?.length) {
    parts.push(`${t('orders.filterStatuses')}: ${f.statuses.join(', ')}`)
  }
  return parts.length ? parts.join(' · ') : t('orders.placeOrdersIntro')
})

function formatPrice(amount) {
  return formatOfferPrice(amount, communityCurrencyCode.value)
}

function statusLabel(status) {
  const key = `orders.status.${status}`
  const out = t(key)
  return out === key ? String(status) : out
}

/**
 * @param {unknown} row
 */
function rowId(row) {
  if (row && typeof row === 'object' && 'id' in row) {
    return Number(row.id)
  }
  return 0
}

/**
 * @param {unknown} line
 */
function lineTitle(line) {
  const snap = line && typeof line === 'object' && 'offer_snapshot' in line ? line.offer_snapshot : null
  if (snap && typeof snap === 'object' && 'title' in snap && typeof snap.title === 'string') {
    return snap.title
  }
  return '—'
}

async function loadOrders() {
  const pid = placeId.value
  if (!pid) {
    return
  }
  loading.value = true
  error.value = ''
  const { ok, data, status } = await fetchPlaceOrders(pid, 1, {
    ...filtersFromRoute.value,
    per_page: 60,
  })
  loading.value = false
  if (!ok) {
    error.value = t('orders.placeLoadError') + (status ? ` (${status})` : '')
    orders.value = []
    return
  }
  const payload = data && typeof data === 'object' ? data : {}
  orders.value = Array.isArray(payload.data) ? payload.data : []
}

/**
 * @param {unknown} row
 * @param {Event} ev
 */
async function onStatusChange(row, ev) {
  const target = ev.target
  if (!target || !(target instanceof HTMLSelectElement)) {
    return
  }
  const next = target.value
  const id = rowId(row)
  if (!id) {
    return
  }
  busyId.value = id
  const { ok, data, status } = await patchPlaceOrderStatus(placeId.value, id, next)
  busyId.value = 0
  if (!ok) {
    window.alert(
      data && typeof data === 'object' && 'message' in data && typeof data.message === 'string'
        ? data.message
        : `HTTP ${status}`,
    )
    await loadOrders()
    return
  }
  await loadOrders()
}

function exitLive() {
  router.push({ name: 'placeEdit', params: { placeId: placeId.value, tab: 'orders' } })
}

const { connected: realtimeConnected } = usePlaceOrdersRealtime(placeIdNum, () => {
  void loadOrders()
})

function startPoll() {
  clearPoll()
  pollTimer = setInterval(() => void loadOrders(), POLL_MS)
}

function clearPoll() {
  if (pollTimer != null) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

watch(
  () => route.fullPath,
  () => {
    void loadOrders()
  },
)

onMounted(() => {
  void loadOrders()
  startPoll()
})

onUnmounted(() => {
  clearPoll()
})
</script>

<template>
  <div class="place-live-orders">
    <header class="place-live-orders__header">
      <Title tag="h1" class="place-live-orders__title">{{ t('orders.liveViewTitle') }}</Title>
      <div class="place-live-orders__headerActions">
        <span v-if="realtimeConnected" class="place-live-orders__liveDot" aria-hidden="true" />
        <span v-if="realtimeConnected" class="place-live-orders__liveLabel">{{ t('orders.liveRealtimeOn') }}</span>
        <Button type="button" variant="secondary" size="sm" @click="exitLive">
          {{ t('orders.liveViewExit') }}
        </Button>
      </div>
    </header>
    <p class="place-live-orders__filters">{{ filterSummary }}</p>

    <p v-if="loading" class="place-live-orders__muted">{{ t('orders.loading') }}</p>
    <p v-else-if="error" class="place-live-orders__err" role="alert">{{ error }}</p>
    <p v-else-if="orders.length === 0" class="place-live-orders__muted">{{ t('orders.empty') }}</p>

    <ul v-else class="place-live-orders__list" role="list">
      <li v-for="row in orders" :key="rowId(row)">
        <Card class="place-live-orders__card">
          <div class="place-live-orders__row">
            <div>
              <p class="place-live-orders__num">#{{ row && row.order_number ? row.order_number : '—' }}</p>
              <p v-if="row && row.customer" class="place-live-orders__customer">{{ row.customer.name }}</p>
              <p class="place-live-orders__date">
                {{ row && row.created_at ? String(row.created_at).slice(0, 16).replace('T', ' ') : '' }}
              </p>
            </div>
            <div class="place-live-orders__amounts">
              <p v-if="row && row.place_subtotal != null" class="place-live-orders__sub">
                {{ t('cart.subtotalPlace') }}: <strong>{{ formatPrice(row.place_subtotal) }}</strong>
              </p>
            </div>
          </div>
          <ul v-if="row && Array.isArray(row.items) && row.items.length" class="place-live-orders__lines">
            <li v-for="ln in row.items" :key="ln && ln.id" class="place-live-orders__line">
              <span class="place-live-orders__lineTitle">{{ lineTitle(ln) }}</span>
              <span class="place-live-orders__qty">× {{ ln?.quantity }}</span>
              <span v-if="ln?.table?.name" class="place-live-orders__muted"> — {{ ln.table.name }}</span>
            </li>
          </ul>
          <label class="place-live-orders__statusRow">
            <span>{{ t('orders.status') }}</span>
            <select
              class="place-live-orders__select"
              :value="row && row.status ? row.status : 'pending'"
              :disabled="busyId === rowId(row)"
              @change="onStatusChange(row, $event)"
            >
              <option v-for="st in STATUS_OPTIONS" :key="st" :value="st">
                {{ statusLabel(st) }}
              </option>
            </select>
          </label>
        </Card>
      </li>
    </ul>
  </div>
</template>

<style scoped lang="scss">
.place-live-orders {
  min-height: 100vh;
  padding: 1rem 1.25rem 2rem;
  max-width: 56rem;
  margin: 0 auto;
  font-size: 1.05rem;
}

.place-live-orders__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.place-live-orders__title {
  margin: 0;
  font-size: clamp(1.35rem, 3vw, 1.85rem);
}

.place-live-orders__headerActions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.place-live-orders__liveDot {
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 50%;
  background: #22c55e;
  display: inline-block;
}

.place-live-orders__liveLabel {
  font-size: 0.8rem;
  color: var(--text-muted, #64748b);
}

.place-live-orders__filters {
  margin: 0 0 1.25rem;
  font-size: 0.9rem;
  color: var(--text-muted, #64748b);
  line-height: 1.4;
}

.place-live-orders__muted {
  color: var(--text-muted, #64748b);
}

.place-live-orders__err {
  color: #b91c1c;
}

.place-live-orders__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.place-live-orders__card {
  padding: 1.15rem 1.25rem;
}

.place-live-orders__row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.65rem;
}

.place-live-orders__num {
  margin: 0;
  font-weight: 800;
  font-size: 1.15rem;
}

.place-live-orders__customer {
  margin: 0.35rem 0 0;
  font-size: 1rem;
}

.place-live-orders__date {
  margin: 0.35rem 0 0;
  font-size: 0.9rem;
  color: var(--text-muted, #64748b);
}

.place-live-orders__amounts {
  text-align: right;
  font-size: 1rem;
}

.place-live-orders__sub {
  margin: 0;
}

.place-live-orders__lines {
  list-style: none;
  margin: 0 0 0.75rem;
  padding: 0.65rem 0 0;
  border-top: 1px solid var(--border);
}

.place-live-orders__line {
  margin: 0.4rem 0;
  font-size: 1.05rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: baseline;
}

.place-live-orders__lineTitle {
  font-weight: 600;
}

.place-live-orders__qty {
  color: var(--text-muted, #64748b);
}

.place-live-orders__statusRow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  font-size: 1rem;
}

.place-live-orders__select {
  font: inherit;
  font-size: 1rem;
  padding: 0.45rem 0.65rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  min-width: 11rem;
}
</style>
