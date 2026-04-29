<script setup>
import { computed, ref, watch } from 'vue'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import { useCommunity } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'
import { fetchPlaceOrders, patchPlaceOrderStatus } from '../../services/ordersApi'
import { formatOfferPrice } from '../../utils/formatPrice'

const props = defineProps({
  placeId: {
    type: [Number, String],
    required: true,
  },
})

const { communityCurrencyCode } = useCommunity()

const loading = ref(true)
const error = ref('')
const orders = ref([])
const meta = ref(null)
const page = ref(1)
const busyId = ref(0)

const STATUS_OPTIONS = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled']

const hasNext = computed(() => {
  const m = meta.value
  if (!m || typeof m !== 'object') return false
  return Boolean(m.current_page && m.last_page && m.current_page < m.last_page)
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

async function load() {
  loading.value = true
  error.value = ''
  const { ok, data, status } = await fetchPlaceOrders(props.placeId, page.value)
  loading.value = false
  if (!ok) {
    error.value = t('orders.placeLoadError') + (status ? ` (${status})` : '')
    orders.value = []
    meta.value = null
    return
  }
  const payload = data && typeof data === 'object' ? data : {}
  orders.value = Array.isArray(payload.data) ? payload.data : []
  meta.value = 'meta' in payload && payload.meta && typeof payload.meta === 'object' ? payload.meta : null
}

/**
 * @param {unknown} row
 * @param {Event} ev
 */
async function onStatusChange(row, ev) {
  const target = ev.target
  if (!target || !(target instanceof HTMLSelectElement)) return
  const next = target.value
  const id = rowId(row)
  if (!id) return
  busyId.value = id
  const { ok, data, status } = await patchPlaceOrderStatus(props.placeId, id, next)
  busyId.value = 0
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

function nextPage() {
  if (!hasNext.value) return
  page.value += 1
}

function prevPage() {
  if (page.value <= 1) return
  page.value -= 1
}

watch(
  () => props.placeId,
  () => {
    page.value = 1
    void load()
  },
)

watch(page, () => {
  void load()
})

void load()
</script>

<template>
  <div class="place-orders">
    <Title tag="h2" class="place-orders__title">{{ t('orders.placeOrdersTitle') }}</Title>
    <p class="place-orders__intro">{{ t('orders.placeOrdersIntro') }}</p>

    <p v-if="loading" class="place-orders__muted">{{ t('orders.loading') }}</p>
    <p v-else-if="error" class="place-orders__err" role="alert">{{ error }}</p>
    <p v-else-if="orders.length === 0" class="place-orders__muted">{{ t('orders.empty') }}</p>

    <ul v-else class="place-orders__list" role="list">
      <li v-for="row in orders" :key="rowId(row)">
        <Card class="place-orders__card">
          <div class="place-orders__row">
            <div>
              <p class="place-orders__num">#{{ row && row.order_number ? row.order_number : '—' }}</p>
              <p class="place-orders__date">{{ row && row.created_at ? String(row.created_at).slice(0, 16).replace('T', ' ') : '' }}</p>
            </div>
            <div class="place-orders__amounts">
              <p v-if="row && row.place_subtotal != null" class="place-orders__sub">
                {{ t('cart.subtotalPlace') }}: <strong>{{ formatPrice(row.place_subtotal) }}</strong>
              </p>
              <p class="place-orders__total">
                {{ t('orders.total') }}: {{ formatPrice(row && row.total_amount != null ? row.total_amount : '0') }}
              </p>
            </div>
          </div>
          <label class="place-orders__statusRow">
            <span>{{ t('orders.status') }}</span>
            <select
              class="place-orders__select"
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

    <div v-if="!loading && orders.length && meta" class="place-orders__pager">
      <Button type="button" variant="secondary" size="sm" :disabled="page <= 1" @click="prevPage">←</Button>
      <span class="place-orders__pageInfo">{{ page }} / {{ meta.last_page || 1 }}</span>
      <Button type="button" variant="secondary" size="sm" :disabled="!hasNext" @click="nextPage">→</Button>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.place-orders {
  max-width: 48rem;
}

.place-orders__title {
  margin: 0 0 0.35rem;
  font-size: 1.1rem;
}

.place-orders__intro {
  margin: 0 0 1rem;
  font-size: 0.9rem;
  color: var(--text-muted, #64748b);
}

.place-orders__muted {
  color: var(--text-muted, #64748b);
}

.place-orders__err {
  color: #b91c1c;
}

.place-orders__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.place-orders__card {
  padding: 1rem;
}

.place-orders__row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.65rem;
}

.place-orders__num {
  margin: 0;
  font-weight: 700;
}

.place-orders__date {
  margin: 0.25rem 0 0;
  font-size: 0.8rem;
  color: var(--text-muted, #64748b);
}

.place-orders__amounts {
  text-align: right;
  font-size: 0.85rem;
}

.place-orders__sub {
  margin: 0 0 0.25rem;
}

.place-orders__total {
  margin: 0;
}

.place-orders__statusRow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
}

.place-orders__select {
  font: inherit;
  padding: 0.35rem 0.5rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  min-width: 10rem;
}

.place-orders__pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  margin-top: 1rem;
}

.place-orders__pageInfo {
  font-size: 0.85rem;
  color: var(--text-muted, #64748b);
}
</style>
