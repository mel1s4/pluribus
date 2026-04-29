<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import { useCommunity } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'
import { fetchOrder } from '../../services/ordersApi'
import { formatOfferPrice } from '../../utils/formatPrice'

const route = useRoute()
const router = useRouter()
const { communityCurrencyCode } = useCommunity()

const loading = ref(true)
const error = ref('')
const order = ref(null)

const STEPS = ['pending', 'confirmed', 'preparing', 'ready', 'completed']

function formatPrice(amount) {
  return formatOfferPrice(amount, communityCurrencyCode.value)
}

function statusLabel(status) {
  const key = `orders.status.${status}`
  const out = t(key)
  return out === key ? String(status) : out
}

const orderId = computed(() => {
  const raw = route.params.orderId
  return typeof raw === 'string' ? raw : ''
})

function stepIndex(status) {
  if (status === 'cancelled') {
    return -1
  }
  const i = STEPS.indexOf(String(status))
  return i >= 0 ? i : 0
}

const groupedItems = computed(() => {
  const o = order.value
  if (!o || typeof o !== 'object' || !Array.isArray(o.items)) {
    return []
  }
  /** @type {Map<number, { place: { id: number, name?: string }, lines: unknown[] }>} */
  const map = new Map()
  for (const item of o.items) {
    if (!item || typeof item !== 'object') continue
    const pid = typeof item.place_id === 'number' ? item.place_id : Number(item.place_id)
    const place = item.place && typeof item.place === 'object' ? item.place : { id: pid, name: '' }
    if (!map.has(pid)) {
      map.set(pid, { place, lines: [] })
    }
    map.get(pid).lines.push(item)
  }
  return [...map.values()]
})

/**
 * @param {unknown} snap
 */
function snapTitle(snap) {
  if (snap && typeof snap === 'object' && 'title' in snap && typeof snap.title === 'string') {
    return snap.title
  }
  return '—'
}

/**
 * @param {unknown} snap
 */
function snapPhoto(snap) {
  if (snap && typeof snap === 'object' && 'photo_url' in snap && typeof snap.photo_url === 'string') {
    return snap.photo_url
  }
  return ''
}

async function load() {
  const id = orderId.value
  if (!id) return
  loading.value = true
  error.value = ''
  order.value = null
  const { ok, data, status } = await fetchOrder(id)
  loading.value = false
  if (!ok) {
    error.value = t('orders.loadError') + (status ? ` (${status})` : '')
    return
  }
  const payload = data && typeof data === 'object' && 'order' in data ? data.order : null
  order.value = payload
}

watch(orderId, () => {
  void load()
})

void load()
</script>

<template>
  <div class="order-detail-page">
    <div class="order-detail-page__head">
      <button type="button" class="order-detail-page__back" @click="router.push({ name: 'orders' })">
        {{ t('orders.backToList') }}
      </button>
    </div>

    <p v-if="loading" class="order-detail-page__muted">{{ t('orders.loading') }}</p>
    <p v-else-if="error" class="order-detail-page__err" role="alert">{{ error }}</p>

    <template v-else-if="order">
      <Title tag="h1" class="order-detail-page__title">{{ t('orders.detailTitle') }}</Title>
      <p class="order-detail-page__meta">
        <strong>#{{ order.order_number }}</strong>
        · {{ String(order.created_at || '').slice(0, 16).replace('T', ' ') }}
      </p>

      <Card class="order-detail-page__card">
        <h2 class="order-detail-page__h2">{{ t('orders.timelineHeading') }}</h2>
        <div v-if="order.status === 'cancelled'" class="order-detail-page__cancelled">
          {{ statusLabel('cancelled') }}
        </div>
        <ol v-else class="order-detail-page__timeline" aria-label="Order status">
          <li
            v-for="(st, i) in STEPS"
            :key="st"
            class="order-detail-page__step"
            :class="{
              'is-done': stepIndex(order.status) > i,
              'is-current': stepIndex(order.status) === i,
            }"
          >
            <span class="order-detail-page__dot" />
            <span class="order-detail-page__stepLabel">{{ statusLabel(st) }}</span>
          </li>
        </ol>
        <p class="order-detail-page__statusPill">
          <span class="orders-page__badge" :data-status="order.status">{{ statusLabel(order.status) }}</span>
        </p>
      </Card>

      <Card v-if="order.notes" class="order-detail-page__card">
        <h2 class="order-detail-page__h2">{{ t('orders.notesHeading') }}</h2>
        <p class="order-detail-page__notes">{{ order.notes }}</p>
      </Card>

      <Card class="order-detail-page__card">
        <h2 class="order-detail-page__h2">{{ t('orders.itemsHeading') }}</h2>
        <div
          v-for="(grp, gi) in groupedItems"
          :key="gi"
          class="order-detail-page__group"
        >
          <h3 v-if="grp.place && grp.place.name" class="order-detail-page__place">
            {{ grp.place.name }}
          </h3>
          <ul class="order-detail-page__lines" role="list">
            <li
              v-for="(line, li) in grp.lines"
              :key="li"
              class="order-detail-page__line"
            >
              <img
                v-if="snapPhoto(line.offer_snapshot)"
                class="order-detail-page__thumb"
                :src="snapPhoto(line.offer_snapshot)"
                alt=""
                loading="lazy"
              />
              <div class="order-detail-page__lineBody">
                <p class="order-detail-page__lineTitle">{{ snapTitle(line.offer_snapshot) }}</p>
                <p class="order-detail-page__lineMeta">
                  × {{ line.quantity }}
                  · {{ formatPrice(line.unit_price) }}
                  · <strong>{{ formatPrice(line.subtotal) }}</strong>
                </p>
              </div>
            </li>
          </ul>
        </div>
        <p class="order-detail-page__grandTotal">
          {{ t('orders.total') }}: <strong>{{ formatPrice(order.total_amount) }}</strong>
        </p>
      </Card>
    </template>
  </div>
</template>

<style lang="scss" scoped>
.order-detail-page {
  max-width: 42rem;
  margin: 0 auto;
  padding: 1rem 1rem 2.5rem;
}

.order-detail-page__head {
  margin-bottom: 0.75rem;
}

.order-detail-page__back {
  cursor: pointer;
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
  font: inherit;
}

.order-detail-page__muted {
  color: var(--text-muted, #64748b);
}

.order-detail-page__err {
  color: #b91c1c;
}

.order-detail-page__title {
  margin: 0 0 0.35rem;
}

.order-detail-page__meta {
  margin: 0 0 1rem;
  font-size: 0.9rem;
  color: var(--text-muted, #64748b);
}

.order-detail-page__card {
  margin-bottom: 1rem;
}

.order-detail-page__h2 {
  margin: 0 0 0.75rem;
  font-size: 1rem;
}

.order-detail-page__cancelled {
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  background: #fee2e2;
  color: #b91c1c;
  font-weight: 600;
}

.order-detail-page__timeline {
  list-style: none;
  margin: 0 0 0.75rem;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.order-detail-page__step {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.78rem;
  color: #94a3b8;
}

.order-detail-page__step.is-done {
  color: #0d9488;
}

.order-detail-page__step.is-current {
  color: #0f172a;
  font-weight: 700;
}

.order-detail-page__dot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 50%;
  background: currentColor;
}

.order-detail-page__statusPill {
  margin: 0;
}

.order-detail-page__notes {
  margin: 0;
  white-space: pre-wrap;
}

.order-detail-page__place {
  margin: 0 0 0.5rem;
  font-size: 0.9rem;
  font-weight: 700;
  color: #0d9488;
}

.order-detail-page__lines {
  list-style: none;
  margin: 0 0 1rem;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.order-detail-page__line {
  display: flex;
  gap: 0.65rem;
  align-items: flex-start;
}

.order-detail-page__thumb {
  width: 3rem;
  height: 3rem;
  object-fit: cover;
  border-radius: 0.45rem;
}

.order-detail-page__lineTitle {
  margin: 0 0 0.2rem;
  font-weight: 600;
  font-size: 0.9rem;
}

.order-detail-page__lineMeta {
  margin: 0;
  font-size: 0.8rem;
  color: var(--text-muted, #64748b);
}

.order-detail-page__grandTotal {
  margin: 0;
  text-align: right;
  font-size: 1rem;
}

.orders-page__badge {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0.25rem 0.5rem;
  border-radius: 999px;
  background: #e2e8f0;
  color: #334155;
}

.orders-page__badge[data-status='pending'] {
  background: #fef9c3;
  color: #854d0e;
}

.orders-page__badge[data-status='confirmed'] {
  background: #dbeafe;
  color: #1e40af;
}

.orders-page__badge[data-status='preparing'] {
  background: #ffedd5;
  color: #9a3412;
}

.orders-page__badge[data-status='ready'] {
  background: #d1fae5;
  color: #047857;
}

.orders-page__badge[data-status='completed'] {
  background: #f1f5f9;
  color: #475569;
}

.orders-page__badge[data-status='cancelled'] {
  background: #fee2e2;
  color: #b91c1c;
}
</style>
