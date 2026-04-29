<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import { useCommunity } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'
import { fetchMyOrders } from '../../services/ordersApi'
import { formatOfferPrice } from '../../utils/formatPrice'

const router = useRouter()
const { communityCurrencyCode } = useCommunity()

const loading = ref(true)
const error = ref('')
const orders = ref([])
const meta = ref(null)
const page = ref(1)

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
  const { ok, data, status } = await fetchMyOrders(page.value)
  loading.value = false
  if (!ok) {
    error.value = t('orders.loadError') + (status ? ` (${status})` : '')
    orders.value = []
    meta.value = null
    return
  }
  const payload = data && typeof data === 'object' ? data : {}
  orders.value = Array.isArray(payload.data) ? payload.data : []
  meta.value = 'meta' in payload && payload.meta && typeof payload.meta === 'object' ? payload.meta : null
}

function goDetail(row) {
  const id = rowId(row)
  if (!id) return
  void router.push({ name: 'orderDetail', params: { orderId: String(id) } })
}

function nextPage() {
  if (!hasNext.value) return
  page.value += 1
}

function prevPage() {
  if (page.value <= 1) return
  page.value -= 1
}

watch(page, () => {
  void load()
})

void load()
</script>

<template>
  <div class="orders-page">
    <Title tag="h1" class="orders-page__title">{{ t('orders.title') }}</Title>

    <p v-if="loading" class="orders-page__muted">{{ t('orders.loading') }}</p>
    <p v-else-if="error" class="orders-page__err" role="alert">{{ error }}</p>
    <p v-else-if="orders.length === 0" class="orders-page__muted">{{ t('orders.empty') }}</p>

    <ul v-else class="orders-page__list" role="list">
      <li v-for="row in orders" :key="rowId(row)">
        <Card class="orders-page__card">
          <div class="orders-page__rowTop">
            <div>
              <p class="orders-page__num">
                {{ t('orders.orderNumber') }} #{{ row && row.order_number ? row.order_number : '—' }}
              </p>
              <p class="orders-page__date">
                {{ row && row.created_at ? String(row.created_at).slice(0, 10) : '' }}
              </p>
            </div>
            <span
              class="orders-page__badge"
              :data-status="row && row.status ? row.status : ''"
            >{{ statusLabel(row && row.status ? row.status : '') }}</span>
          </div>
          <div class="orders-page__rowBottom">
            <p class="orders-page__total">
              {{ t('orders.total') }}: <strong>{{ formatPrice(row && row.total_amount != null ? row.total_amount : '0') }}</strong>
            </p>
            <Button type="button" variant="secondary" size="sm" @click="goDetail(row)">
              {{ t('orders.view') }}
            </Button>
          </div>
        </Card>
      </li>
    </ul>

    <div v-if="!loading && orders.length && meta" class="orders-page__pager">
      <Button type="button" variant="secondary" size="sm" :disabled="page <= 1" @click="prevPage">
        ←
      </Button>
      <span class="orders-page__pageInfo">{{ page }} / {{ meta.last_page || 1 }}</span>
      <Button type="button" variant="secondary" size="sm" :disabled="!hasNext" @click="nextPage">
        →
      </Button>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.orders-page {
  max-width: 40rem;
  margin: 0 auto;
  padding: 1rem 1rem 2.5rem;
}

.orders-page__title {
  margin: 0 0 1rem;
}

.orders-page__muted {
  color: var(--text-muted, #64748b);
}

.orders-page__err {
  color: #b91c1c;
}

.orders-page__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.orders-page__card {
  padding: 1rem;
}

.orders-page__rowTop {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.75rem;
  margin-bottom: 0.65rem;
}

.orders-page__num {
  margin: 0;
  font-weight: 700;
  font-size: 0.95rem;
}

.orders-page__date {
  margin: 0.25rem 0 0;
  font-size: 0.8rem;
  color: var(--text-muted, #64748b);
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

.orders-page__rowBottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.orders-page__total {
  margin: 0;
  font-size: 0.9rem;
}

.orders-page__pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  margin-top: 1rem;
}

.orders-page__pageInfo {
  font-size: 0.85rem;
  color: var(--text-muted, #64748b);
}
</style>
