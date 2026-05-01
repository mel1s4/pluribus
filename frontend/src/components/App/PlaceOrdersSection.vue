<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import { useCommunity } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'
import { fetchPlaceOrders, patchPlaceOrderStatus } from '../../services/ordersApi'
import { fetchOffers } from '../../services/placesApi'
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
const offers = ref(/** @type {unknown[]} */ ([]))
const offersLoading = ref(false)

/** @type {import('vue').Ref<string[]>} */
const filterOfferIds = ref([])
const filterTags = ref('')
/** @type {import('vue').Ref<string[]>} */
const filterStatuses = ref([])

const STATUS_OPTIONS = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled']

const hasNext = computed(() => {
  const m = meta.value
  if (!m || typeof m !== 'object') {
    return false
  }
  return Boolean(m.current_page && m.last_page && m.current_page < m.last_page)
})

const liveOrdersQuery = computed(() => {
  const q = /** @type {Record<string, string>} */ ({})
  if (filterOfferIds.value.length) {
    q.offers = filterOfferIds.value.join(',')
  }
  const tags = filterTags.value
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean)
  if (tags.length) {
    q.tags = tags.join(',')
  }
  if (filterStatuses.value.length) {
    q.statuses = filterStatuses.value.join(',')
  }
  return q
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

function currentFilters() {
  const ids = filterOfferIds.value.map((s) => Number(s)).filter((n) => Number.isFinite(n) && n > 0)
  const tags = filterTags.value
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean)
  const st = filterStatuses.value.filter((s) => STATUS_OPTIONS.includes(s))
  return {
    place_offer_ids: ids.length ? ids : undefined,
    tags: tags.length ? tags : undefined,
    statuses: st.length ? st : undefined,
  }
}

async function loadOffers() {
  offersLoading.value = true
  const { ok, data } = await fetchOffers(props.placeId)
  offersLoading.value = false
  if (!ok || !data || typeof data !== 'object') {
    offers.value = []
    return
  }
  const raw = 'data' in data && Array.isArray(data.data) ? data.data : []
  offers.value = raw
}

async function load() {
  loading.value = true
  error.value = ''
  const { ok, data, status } = await fetchPlaceOrders(props.placeId, page.value, currentFilters())
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

function applyFilters() {
  page.value = 1
  void load()
}

function clearFilters() {
  filterOfferIds.value = []
  filterTags.value = ''
  filterStatuses.value = []
  page.value = 1
  void load()
}

/**
 * @param {string} st
 * @param {Event} ev
 */
function toggleStatus(st, ev) {
  const tEl = ev.target
  if (!tEl || !(tEl instanceof HTMLInputElement)) {
    return
  }
  if (tEl.checked) {
    if (!filterStatuses.value.includes(st)) {
      filterStatuses.value = [...filterStatuses.value, st]
    }
  } else {
    filterStatuses.value = filterStatuses.value.filter((x) => x !== st)
  }
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
  if (!hasNext.value) {
    return
  }
  page.value += 1
}

function prevPage() {
  if (page.value <= 1) {
    return
  }
  page.value -= 1
}

watch(
  () => props.placeId,
  () => {
    page.value = 1
    void loadOffers()
    void load()
  },
)

watch(page, () => {
  void load()
})

void loadOffers()
void load()
</script>

<template>
  <div class="place-orders">
    <div class="place-orders__headRow">
      <Title tag="h2" class="place-orders__title">{{ t('orders.placeOrdersTitle') }}</Title>
      <RouterLink
        class="place-orders__liveLink"
        :to="{ name: 'placeLiveOrders', params: { placeId: String(placeId) }, query: liveOrdersQuery }"
      >
        {{ t('orders.liveViewOpen') }}
      </RouterLink>
    </div>
    <p class="place-orders__intro">{{ t('orders.placeOrdersIntro') }}</p>

    <Card class="place-orders__filters">
      <h3 class="place-orders__filtersTitle">{{ t('orders.filtersHeading') }}</h3>
      <label class="place-orders__filterLab">
        <span>{{ t('orders.filterOffers') }}</span>
        <select
          v-model="filterOfferIds"
          class="place-orders__multi"
          multiple
          size="4"
          :disabled="offersLoading"
        >
          <option v-for="o in offers" :key="o.id" :value="String(o.id)">
            {{ o.title }}
          </option>
        </select>
      </label>
      <label class="place-orders__filterLab">
        <span>{{ t('orders.filterTags') }}</span>
        <input v-model="filterTags" type="text" class="place-orders__input" />
      </label>
      <fieldset class="place-orders__fieldset">
        <legend class="place-orders__legend">{{ t('orders.filterStatuses') }}</legend>
        <label v-for="st in STATUS_OPTIONS" :key="st" class="place-orders__checkLab">
          <input
            type="checkbox"
            :checked="filterStatuses.includes(st)"
            @change="toggleStatus(st, $event)"
          />
          {{ statusLabel(st) }}
        </label>
      </fieldset>
      <div class="place-orders__filterActions">
        <Button type="button" variant="primary" size="sm" @click="applyFilters">
          {{ t('orders.filterApply') }}
        </Button>
        <Button type="button" variant="secondary" size="sm" @click="clearFilters">
          {{ t('orders.filterClear') }}
        </Button>
      </div>
    </Card>

    <p v-if="loading" class="place-orders__muted">{{ t('orders.loading') }}</p>
    <p v-else-if="error" class="place-orders__err" role="alert">{{ error }}</p>
    <p v-else-if="orders.length === 0" class="place-orders__muted">{{ t('orders.empty') }}</p>

    <ul v-else class="place-orders__list" role="list">
      <li v-for="row in orders" :key="rowId(row)">
        <Card class="place-orders__card">
          <RouterLink
            class="place-orders__cardLink"
            :to="{ name: 'placeOrderDetail', params: { placeId: String(placeId), orderId: String(rowId(row)) } }"
            :aria-label="
              t('orders.openOrderDetailAria').replace(
                '{number}',
                row && row.order_number ? String(row.order_number) : String(rowId(row)),
              )
            "
          >
            <div class="place-orders__row">
              <div>
                <p class="place-orders__num">#{{ row && row.order_number ? row.order_number : '—' }}</p>
                <p v-if="row && row.customer" class="place-orders__customer">{{ row.customer.name }}</p>
                <p class="place-orders__date">
                  {{ row && row.created_at ? String(row.created_at).slice(0, 16).replace('T', ' ') : '' }}
                </p>
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
            <ul v-if="row && Array.isArray(row.items) && row.items.length" class="place-orders__lines">
              <li v-for="ln in row.items" :key="ln && ln.id" class="place-orders__line">
                {{ lineTitle(ln) }} × {{ ln?.quantity }}
                <span v-if="ln?.table?.name" class="place-orders__muted"> — {{ ln.table.name }}</span>
              </li>
            </ul>
          </RouterLink>
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

.place-orders__headRow {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.35rem;
}

.place-orders__title {
  margin: 0;
  font-size: 1.1rem;
}

.place-orders__liveLink {
  font-size: 0.9rem;
  color: var(--primary, #2563eb);
  text-decoration: none;
}

.place-orders__liveLink:hover {
  text-decoration: underline;
}

.place-orders__intro {
  margin: 0 0 1rem;
  font-size: 0.9rem;
  color: var(--text-muted, #64748b);
}

.place-orders__filters {
  padding: 1rem;
  margin-bottom: 1.25rem;
}

.place-orders__filtersTitle {
  margin: 0 0 0.75rem;
  font-size: 1rem;
}

.place-orders__filterLab {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 0.75rem;
  font-size: 0.85rem;
}

.place-orders__multi {
  font: inherit;
  min-height: 6rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  padding: 0.35rem;
}

.place-orders__input {
  font: inherit;
  padding: 0.4rem 0.5rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
}

.place-orders__fieldset {
  border: none;
  margin: 0 0 0.75rem;
  padding: 0;
}

.place-orders__legend {
  font-size: 0.85rem;
  padding: 0;
  margin-bottom: 0.35rem;
}

.place-orders__checkLab {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  margin-right: 0.75rem;
  margin-bottom: 0.35rem;
  font-size: 0.85rem;
}

.place-orders__filterActions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
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

.place-orders__cardLink {
  display: block;
  color: inherit;
  text-decoration: none;
  border-radius: 0.35rem;
  margin: -0.15rem;
  padding: 0.15rem;
}

.place-orders__cardLink:hover .place-orders__num {
  color: var(--primary, #2563eb);
}

.place-orders__cardLink:focus-visible {
  outline: 2px solid var(--primary, #2563eb);
  outline-offset: 2px;
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

.place-orders__customer {
  margin: 0.25rem 0 0;
  font-size: 0.9rem;
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

.place-orders__lines {
  list-style: none;
  margin: 0 0 0.65rem;
  padding: 0.5rem 0 0;
  border-top: 1px solid var(--border);
  font-size: 0.85rem;
}

.place-orders__line {
  margin: 0.25rem 0;
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
