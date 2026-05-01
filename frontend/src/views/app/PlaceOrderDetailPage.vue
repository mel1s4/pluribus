<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import { useCommunity } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'
import { fetchPlaceOrder, patchPlaceOrderStatus } from '../../services/ordersApi'
import { formatOfferPrice } from '../../utils/formatPrice'

const route = useRoute()
const router = useRouter()
const { communityCurrencyCode } = useCommunity()

const loading = ref(true)
const error = ref('')
const order = ref(null)
const busy = ref(false)

const placeId = computed(() => String(route.params.placeId || ''))
const orderId = computed(() => String(route.params.orderId || ''))

const placeName = computed(() => {
  const o = order.value
  if (!o || typeof o !== 'object' || !Array.isArray(o.items) || !o.items.length) {
    return ''
  }
  const first = o.items[0]
  if (first && typeof first === 'object' && first.place && typeof first.place === 'object' && 'name' in first.place) {
    return String(first.place.name || '')
  }
  return ''
})

const STATUS_OPTIONS = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled']

function formatPrice(amount) {
  return formatOfferPrice(amount, communityCurrencyCode.value)
}

function statusLabel(status) {
  const key = `orders.status.${status}`
  const out = t(key)
  return out === key ? String(status) : out
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

function printOrder() {
  window.print()
}

function goBack() {
  router.push({ name: 'placeEdit', params: { placeId: placeId.value, tab: 'orders' } })
}

async function load() {
  const pid = placeId.value
  const oid = orderId.value
  if (!pid || !oid) {
    loading.value = false
    return
  }
  loading.value = true
  error.value = ''
  order.value = null
  const { ok, data, status } = await fetchPlaceOrder(pid, oid)
  loading.value = false
  if (!ok) {
    error.value = t('orders.placeOrderDetailLoadError').replace('{status}', String(status))
    return
  }
  const body = data && typeof data === 'object' ? data : {}
  order.value = 'order' in body ? body.order : null
}

/**
 * @param {Event} ev
 */
async function onStatusChange(ev) {
  const target = ev.target
  if (!target || !(target instanceof HTMLSelectElement)) {
    return
  }
  const next = target.value
  const pid = placeId.value
  const oid = orderId.value
  if (!pid || !oid) {
    return
  }
  busy.value = true
  const { ok, data, status } = await patchPlaceOrderStatus(pid, oid, next)
  busy.value = false
  if (!ok) {
    window.alert(
      data && typeof data === 'object' && 'message' in data && typeof data.message === 'string'
        ? data.message
        : `HTTP ${status}`,
    )
    await load()
    return
  }
  await load()
}

watch([placeId, orderId], () => {
  void load()
}, { immediate: true })
</script>

<template>
  <div class="place-order-detail">
    <div class="place-order-detail__toolbar">
      <button type="button" class="place-order-detail__back" @click="goBack">
        {{ t('orders.backToPlaceOrders') }}
      </button>
      <Button
        v-if="order"
        type="button"
        variant="secondary"
        size="sm"
        @click="printOrder"
      >
        {{ t('orders.print') }}
      </Button>
    </div>

    <p v-if="loading" class="place-order-detail__muted">{{ t('orders.loading') }}</p>
    <p v-else-if="error" class="place-order-detail__err" role="alert">{{ error }}</p>

    <template v-else-if="order">
      <Title tag="h1" class="place-order-detail__title">{{ t('orders.detailTitle') }}</Title>
      <p v-if="placeName" class="place-order-detail__placeName">{{ placeName }}</p>
      <p class="place-order-detail__meta">
        <strong>#{{ order.order_number || '—' }}</strong>
        <template v-if="order.customer && order.customer.name">
          · {{ order.customer.name }}
        </template>
        · {{ order.created_at ? String(order.created_at).slice(0, 16).replace('T', ' ') : '' }}
      </p>

      <Card class="place-order-detail__card">
        <div class="place-order-detail__amounts">
          <p v-if="order.place_subtotal != null" class="place-order-detail__sub">
            {{ t('cart.subtotalPlace') }}: <strong>{{ formatPrice(order.place_subtotal) }}</strong>
          </p>
          <p class="place-order-detail__total">
            {{ t('orders.total') }}: <strong>{{ formatPrice(order.total_amount != null ? order.total_amount : '0') }}</strong>
          </p>
        </div>

        <ul v-if="Array.isArray(order.items) && order.items.length" class="place-order-detail__lines" role="list">
          <li v-for="ln in order.items" :key="ln && ln.id" class="place-order-detail__line">
            {{ lineTitle(ln) }} × {{ ln?.quantity }}
            <span v-if="ln?.table?.name" class="place-order-detail__muted"> — {{ ln.table.name }}</span>
          </li>
        </ul>

        <label class="place-order-detail__statusRow">
          <span>{{ t('orders.status') }}</span>
          <select
            class="place-order-detail__select"
            :value="order.status ? order.status : 'pending'"
            :disabled="busy"
            @change="onStatusChange"
          >
            <option v-for="st in STATUS_OPTIONS" :key="st" :value="st">
              {{ statusLabel(st) }}
            </option>
          </select>
        </label>
      </Card>

      <Card v-if="order.notes" class="place-order-detail__card">
        <h2 class="place-order-detail__h2">{{ t('orders.notesHeading') }}</h2>
        <p class="place-order-detail__notes">{{ order.notes }}</p>
      </Card>
    </template>
  </div>
</template>

<style lang="scss" scoped>
.place-order-detail {
  max-width: 48rem;
  margin: 0 auto;
  padding: 1rem 1rem 2.5rem;
}

.place-order-detail__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.place-order-detail__back {
  cursor: pointer;
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
  font: inherit;
}

.place-order-detail__muted {
  color: var(--text-muted, #64748b);
}

.place-order-detail__err {
  color: #b91c1c;
}

.place-order-detail__title {
  margin: 0 0 0.25rem;
}

.place-order-detail__placeName {
  margin: 0 0 0.35rem;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text-muted, #64748b);
}

.place-order-detail__meta {
  margin: 0 0 1rem;
  font-size: 0.9rem;
  color: var(--text-muted, #64748b);
}

.place-order-detail__card {
  padding: 1rem;
  margin-bottom: 1rem;
}

.place-order-detail__amounts {
  margin-bottom: 0.65rem;
  text-align: right;
  font-size: 0.9rem;
}

.place-order-detail__sub {
  margin: 0 0 0.25rem;
}

.place-order-detail__total {
  margin: 0;
}

.place-order-detail__lines {
  list-style: none;
  margin: 0 0 0.65rem;
  padding: 0.5rem 0 0;
  border-top: 1px solid var(--border);
  font-size: 0.85rem;
}

.place-order-detail__line {
  margin: 0.25rem 0;
}

.place-order-detail__statusRow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
}

.place-order-detail__select {
  font: inherit;
  padding: 0.35rem 0.5rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  min-width: 10rem;
}

.place-order-detail__h2 {
  margin: 0 0 0.5rem;
  font-size: 1rem;
}

.place-order-detail__notes {
  margin: 0;
  white-space: pre-wrap;
}

@media print {
  .place-order-detail__toolbar {
    display: none !important;
  }

  .place-order-detail {
    padding: 0;
    max-width: none;
  }

  .place-order-detail__select {
    border: none;
    padding: 0;
    appearance: none;
    font-weight: 600;
  }
}
</style>
