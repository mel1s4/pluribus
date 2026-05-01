<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import QRCode from 'qrcode'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import { useCommunity } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'
import {
  createTableAccessLink,
  fetchPlaceTableDetail,
  fetchPlaceTables,
  rotateTableAccessLink,
} from '../../services/placeTablesApi.js'
import { patchPlaceOrderItemTable, patchPlaceOrderStatus } from '../../services/ordersApi.js'
import { formatOfferPrice } from '../../utils/formatPrice'

const route = useRoute()
const router = useRouter()
const { communityCurrencyCode } = useCommunity()

const placeId = computed(() => String(route.params.placeId || ''))
const tableId = computed(() => String(route.params.tableId || ''))

const loading = ref(true)
const error = ref('')
const detail = ref(/** @type {null | { table?: unknown, seatings?: unknown[], orders?: unknown[] }} */ (null))
const tables = ref(/** @type {unknown[]} */ ([]))
const inviteUrl = ref('')
const inviteImage = ref('')
const busyKey = ref('')

const tableName = computed(() => {
  const trow = detail.value?.table
  if (trow && typeof trow === 'object' && 'name' in trow) {
    return String(/** @type {{ name?: string }} */ (trow).name || '')
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

function rowOrderId(row) {
  if (row && typeof row === 'object' && 'id' in row) {
    return Number(row.id)
  }
  return 0
}

function lineTitle(line) {
  const snap = line && typeof line === 'object' && 'offer_snapshot' in line ? line.offer_snapshot : null
  if (snap && typeof snap === 'object' && 'title' in snap && typeof snap.title === 'string') {
    return snap.title
  }
  return '—'
}

async function load() {
  const pid = placeId.value
  const tid = tableId.value
  if (!pid || !tid) {
    loading.value = false
    return
  }
  loading.value = true
  error.value = ''
  const [{ ok, data, status }, tablesRes] = await Promise.all([
    fetchPlaceTableDetail(pid, tid),
    fetchPlaceTables(pid),
  ])
  loading.value = false
  if (!ok) {
    error.value = t('myPlaces.tableDetailLoadError').replace('{status}', String(status))
    detail.value = null
    return
  }
  const body = data && typeof data === 'object' ? data : {}
  detail.value = body
  if (tablesRes.ok && tablesRes.data && typeof tablesRes.data === 'object' && Array.isArray(tablesRes.data.data)) {
    tables.value = tablesRes.data.data
  } else {
    tables.value = []
  }
}

async function genInvite(rotate) {
  const pid = placeId.value
  const tid = tableId.value
  const call = rotate ? rotateTableAccessLink : createTableAccessLink
  busyKey.value = 'invite'
  const { ok, data, status } = await call(pid, tid)
  busyKey.value = ''
  if (!ok) {
    window.alert(`HTTP ${status}`)
    return
  }
  const url = data?.access_link?.url
  if (typeof url !== 'string' || !url) {
    return
  }
  inviteUrl.value = url
  inviteImage.value = await QRCode.toDataURL(url, { width: 240, margin: 2, errorCorrectionLevel: 'M' })
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
  const id = rowOrderId(row)
  if (!id) {
    return
  }
  busyKey.value = `st-${id}`
  const { ok, data, status } = await patchPlaceOrderStatus(placeId.value, id, next)
  busyKey.value = ''
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

/**
 * @param {unknown} order
 * @param {unknown} line
 * @param {Event} ev
 */
async function onReassignLine(order, line, ev) {
  const target = ev.target
  if (!target || !(target instanceof HTMLSelectElement)) {
    return
  }
  const raw = target.value
  const nextTableId = raw === '' ? null : Number(raw)
  const oid = rowOrderId(order)
  const lid =
    line && typeof line === 'object' && 'id' in line && line.id != null ? Number(line.id) : 0
  if (!oid || !lid) {
    return
  }
  busyKey.value = `ln-${oid}-${lid}`
  const { ok, data, status } = await patchPlaceOrderItemTable(placeId.value, oid, lid, nextTableId)
  busyKey.value = ''
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

function goBack() {
  router.push({ name: 'placeEdit', params: { placeId: placeId.value, tab: 'tables' } })
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="place-table-detail">
    <div class="place-table-detail__toolbar">
      <Button type="button" variant="secondary" size="sm" @click="goBack">
        {{ t('myPlaces.tableDetailBack') }}
      </Button>
    </div>

    <p v-if="loading" class="place-table-detail__muted">{{ t('orders.loading') }}</p>
    <p v-else-if="error" class="place-table-detail__err" role="alert">{{ error }}</p>
    <template v-else-if="detail?.table">
      <Title tag="h1" class="place-table-detail__title">{{ tableName }}</Title>

      <section class="place-table-detail__section">
        <h2 class="place-table-detail__h2">{{ t('myPlaces.tableInviteHeading') }}</h2>
        <p class="place-table-detail__hint">{{ t('myPlaces.tableInviteHint') }}</p>
        <div class="place-table-detail__inviteActions">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            :disabled="busyKey === 'invite'"
            @click="genInvite(false)"
          >
            {{ t('myPlaces.tableInviteCreate') }}
          </Button>
          <Button
            type="button"
            variant="secondary"
            size="sm"
            :disabled="busyKey === 'invite'"
            @click="genInvite(true)"
          >
            {{ t('myPlaces.tableInviteRotate') }}
          </Button>
        </div>
        <div v-if="inviteUrl" class="place-table-detail__inviteOut">
          <img v-if="inviteImage" :src="inviteImage" alt="" class="place-table-detail__qr" />
          <a :href="inviteUrl" target="_blank" rel="noreferrer" class="place-table-detail__link">{{ inviteUrl }}</a>
        </div>
      </section>

      <section class="place-table-detail__section">
        <h2 class="place-table-detail__h2">{{ t('myPlaces.tableSeatingsHeading') }}</h2>
        <p
          v-if="!detail.seatings || !Array.isArray(detail.seatings) || detail.seatings.length === 0"
          class="place-table-detail__muted"
        >
          {{ t('myPlaces.tableSeatingsEmpty') }}
        </p>
        <ul v-else class="place-table-detail__list">
          <li v-for="s in detail.seatings" :key="s && s.id" class="place-table-detail__li">
            <strong>{{ s?.user?.name || '—' }}</strong>
            <span v-if="s?.last_seen_at" class="place-table-detail__muted">
              — {{ String(s.last_seen_at).slice(0, 16).replace('T', ' ') }}
            </span>
          </li>
        </ul>
      </section>

      <section class="place-table-detail__section">
        <h2 class="place-table-detail__h2">{{ t('myPlaces.tableOrdersHeading') }}</h2>
        <p
          v-if="!detail.orders || !Array.isArray(detail.orders) || detail.orders.length === 0"
          class="place-table-detail__muted"
        >
          {{ t('orders.empty') }}
        </p>
        <ul v-else class="place-table-detail__orderList">
          <li v-for="ord in detail.orders" :key="rowOrderId(ord)">
            <Card class="place-table-detail__card">
              <div class="place-table-detail__orderHead">
                <div>
                  <p class="place-table-detail__orderNum">#{{ ord?.order_number || '—' }}</p>
                  <p v-if="ord?.customer" class="place-table-detail__customer">
                    {{ ord.customer.name }}
                  </p>
                  <p class="place-table-detail__muted">
                    {{ ord?.created_at ? String(ord.created_at).slice(0, 16).replace('T', ' ') : '' }}
                  </p>
                </div>
                <label class="place-table-detail__statusLab">
                  <span>{{ t('orders.status') }}</span>
                  <select
                    class="place-table-detail__select"
                    :value="ord?.status || 'pending'"
                    :disabled="busyKey === `st-${rowOrderId(ord)}`"
                    @change="onStatusChange(ord, $event)"
                  >
                    <option v-for="st in STATUS_OPTIONS" :key="st" :value="st">
                      {{ statusLabel(st) }}
                    </option>
                  </select>
                </label>
              </div>
              <ul class="place-table-detail__lines">
                <li
                  v-for="ln in ord?.items || []"
                  :key="ln && ln.id"
                  class="place-table-detail__line"
                >
                  <div class="place-table-detail__lineMain">
                    <span>{{ lineTitle(ln) }}</span>
                    <span class="place-table-detail__muted">× {{ ln?.quantity }}</span>
                    <span>{{ formatPrice(ln?.subtotal) }}</span>
                  </div>
                  <label class="place-table-detail__reassign">
                    <span>{{ t('myPlaces.tableReassignLabel') }}</span>
                    <select
                      class="place-table-detail__select place-table-detail__select--sm"
                      :value="ln?.table_id != null ? String(ln.table_id) : ''"
                      :disabled="busyKey === `ln-${rowOrderId(ord)}-${ln?.id}`"
                      @change="onReassignLine(ord, ln, $event)"
                    >
                      <option value="">{{ t('myPlaces.tableReassignNone') }}</option>
                      <option v-for="tb in tables" :key="tb.id" :value="String(tb.id)">
                        {{ tb.name }}
                      </option>
                    </select>
                  </label>
                </li>
              </ul>
            </Card>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<style scoped lang="scss">
.place-table-detail {
  max-width: 48rem;
  margin: 0 auto;
  padding: 0 1rem 2rem;
}

.place-table-detail__toolbar {
  margin-bottom: 1rem;
}

.place-table-detail__title {
  margin: 0 0 1rem;
  font-size: 1.35rem;
}

.place-table-detail__section {
  margin-bottom: 1.75rem;
}

.place-table-detail__h2 {
  margin: 0 0 0.35rem;
  font-size: 1.05rem;
}

.place-table-detail__hint {
  margin: 0 0 0.75rem;
  font-size: 0.85rem;
  color: var(--text-muted, #64748b);
}

.place-table-detail__inviteActions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.place-table-detail__inviteOut {
  display: grid;
  gap: 0.5rem;
}

.place-table-detail__qr {
  width: 160px;
  height: 160px;
}

.place-table-detail__link {
  word-break: break-all;
  font-size: 0.85rem;
}

.place-table-detail__list,
.place-table-detail__orderList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.place-table-detail__li {
  padding: 0.35rem 0;
}

.place-table-detail__card {
  padding: 1rem;
}

.place-table-detail__orderHead {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.place-table-detail__orderNum {
  margin: 0;
  font-weight: 700;
}

.place-table-detail__customer {
  margin: 0.25rem 0 0;
  font-size: 0.9rem;
}

.place-table-detail__statusLab {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.85rem;
}

.place-table-detail__lines {
  list-style: none;
  margin: 0;
  padding: 0;
  border-top: 1px solid var(--border);
}

.place-table-detail__line {
  padding: 0.65rem 0;
  border-bottom: 1px solid var(--border);
}

.place-table-detail__line:last-child {
  border-bottom: none;
}

.place-table-detail__lineMain {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: baseline;
  margin-bottom: 0.35rem;
}

.place-table-detail__reassign {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.8rem;
}

.place-table-detail__select {
  font: inherit;
  padding: 0.35rem 0.5rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  min-width: 9rem;
}

.place-table-detail__select--sm {
  min-width: 7rem;
}

.place-table-detail__muted {
  color: var(--text-muted, #64748b);
}

.place-table-detail__err {
  color: #b91c1c;
}
</style>
