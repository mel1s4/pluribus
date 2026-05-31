<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import { useCommunity } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'
import { formatOfferPrice } from '../../utils/formatPrice'
import { formatLocalCurrencyPrice } from '../../utils/formatLocalCurrencyPrice'
import Title from '../../atoms/Title.vue'
import PlaceTagsField from '../../molecules/PlaceTagsField.vue'
import {
  deleteOffer,
  downloadOffersCsvUrl,
  fetchOffers,
  uploadOffersCsv,
} from '../../services/placesApi.js'

const props = defineProps({
  placeId: {
    type: [Number, String],
    required: true,
  },
})

const emit = defineEmits(['changed'])
const router = useRouter()

const offers = ref([])
const error = ref('')
const csvInput = ref(null)
const currentPage = ref(1)
const totalPages = ref(1)
const hasNextPage = ref(false)
const hasPrevPage = ref(false)

const { communityCurrencyCode } = useCommunity()

function formatPrice(amount) {
  if (amount == null || amount === '') return '—'
  return formatOfferPrice(amount, communityCurrencyCode.value)
}

function formatLocalPrice(amount, currencyCode) {
  if (amount == null || amount === '') return ''
  return formatLocalCurrencyPrice(amount, currencyCode)
}

async function load(page = 1) {
  error.value = ''
  const { ok, status, data } = await fetchOffers(props.placeId, page)
  if (!ok) {
    error.value = t('myPlaces.offersLoadError').replace('{status}', String(status))
    offers.value = []
    return
  }
  offers.value = Array.isArray(data?.data) ? data.data : []
  if (data?.meta) {
    currentPage.value = data.meta.current_page || 1
    totalPages.value = data.meta.last_page || 1
  }
  if (data?.links) {
    hasNextPage.value = Boolean(data.links.next)
    hasPrevPage.value = Boolean(data.links.prev)
  }
}

watch(
  () => props.placeId,
  () => {
    load(1)
  },
  { immediate: true },
)

function goToEditPage(o) {
  router.push({ name: 'placeOfferEdit', params: { placeId: String(props.placeId), offerId: String(o.id) } })
}

async function removeOffer(o) {
  if (!window.confirm(t('myPlaces.deleteOfferConfirm'))) return
  const { ok, status } = await deleteOffer(props.placeId, o.id)
  if (!ok) {
    error.value = t('myPlaces.offerDeleteError').replace('{status}', String(status))
    return
  }
  await load(currentPage.value)
  emit('changed')
}

function goToCreateOfferPage() {
  router.push({ name: 'placeOfferCreate', params: { placeId: String(props.placeId) } })
}

function downloadCsv() {
  window.open(downloadOffersCsvUrl(props.placeId), '_blank', 'noopener')
}

function openCsvPicker() {
  csvInput.value?.click()
}

async function handleCsvSelected(event) {
  const file = event?.target?.files?.[0]
  if (!file) return
  const { ok, status, data } = await uploadOffersCsv(props.placeId, file)
  event.target.value = ''
  if (!ok) {
    error.value = t('myPlaces.offersUploadError').replace('{status}', String(status))
    return
  }
  const created = Number(data?.created ?? 0)
  const updated = Number(data?.updated ?? 0)
  const failed = Number(data?.failed ?? 0)
  window.alert(`Offer CSV import complete.\nCreated: ${created}\nUpdated: ${updated}\nFailed: ${failed}`)
  await load(currentPage.value)
  emit('changed')
}

function loadPrev() {
  if (hasPrevPage.value) load(currentPage.value - 1)
}

function loadNext() {
  if (hasNextPage.value) load(currentPage.value + 1)
}
</script>

<template>
  <section class="place-offers">
    <div class="place-offers__head">
      <Title tag="h3" class="place-offers__title">{{ t('myPlaces.offersHeading') }}</Title>
      <Button
        type="button"
        variant="primary"
        size="sm"
        @click="goToCreateOfferPage"
      >
        {{ t('myPlaces.addOffer') }}
      </Button>
      <Button type="button" variant="ghost" size="sm" @click="downloadCsv">
        Download CSV
      </Button>
      <Button type="button" variant="ghost" size="sm" @click="openCsvPicker">
        Upload CSV
      </Button>
      <input ref="csvInput" type="file" accept=".csv,text/csv" class="place-offers__csvInput" @change="handleCsvSelected" />
    </div>
    <p v-if="error" class="place-offers__error">{{ error }}</p>

    <ul v-if="offers.length" class="place-offers__list">
      <li
        v-for="o in offers"
        :key="o.id"
        class="place-offers__row"
      >
        <span class="place-offers__name">{{ o.title }}</span>
        <div class="place-offers__prices">
          <span v-if="o.price != null && o.price !== ''" class="place-offers__price">{{ formatPrice(o.price) }}</span>
          <span v-if="o.local_price" class="place-offers__localPrice">{{ formatLocalPrice(o.local_price, o.local_currency_code) }}</span>
        </div>
        <span v-if="o.category" class="place-offers__category">{{ o.category }}</span>
        <span v-if="o.tags?.length" class="place-offers__tags">{{ o.tags.join(', ') }}</span>
        <Button
          type="button"
          variant="link"
          size="sm"
          @click="goToEditPage(o)"
        >
          {{ t('myPlaces.edit') }}
        </Button>
        <Button
          type="button"
          variant="danger"
          size="sm"
          @click="removeOffer(o)"
        >
          {{ t('myPlaces.delete') }}
        </Button>
      </li>
    </ul>

    <div v-if="totalPages > 1" class="place-offers__pagination">
      <Button type="button" variant="ghost" size="sm" :disabled="!hasPrevPage" @click="loadPrev">
        Previous
      </Button>
      <span class="place-offers__page-info">Page {{ currentPage }} of {{ totalPages }}</span>
      <Button type="button" variant="ghost" size="sm" :disabled="!hasNextPage" @click="loadNext">
        Next
      </Button>
    </div>

  </section>
</template>

<style lang="scss" scoped>
.place-offers {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border);
}

.place-offers__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.place-offers__title {
  font-size: 1.05rem;
}

.place-offers__subtitle {
  font-size: 0.95rem;
  margin: 0 0 0.35rem;
}

.place-offers__error {
  color: var(--danger, #b00020);
  margin: 0;
  font-size: 0.9rem;
}

.place-offers__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.place-offers__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 0.75rem;
  padding: 0.35rem 0;
  border-bottom: 1px solid var(--border);
}

.place-offers__name {
  flex: 1;
  min-width: 8rem;
}

.place-offers__prices {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.place-offers__price {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.place-offers__localPrice {
  font-variant-numeric: tabular-nums;
  font-size: 0.8rem;
  opacity: 0.8;
}

.place-offers__category {
  font-size: 0.8rem;
  font-weight: 600;
  max-width: 10rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.place-offers__tags {
  font-size: 0.8rem;
  opacity: 0.85;
  max-width: 12rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.place-offers__hint {
  margin: 0;
  font-size: 0.8rem;
  opacity: 0.85;
}

.place-offers__form {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  max-width: 28rem;
}

.place-offers__label {
  font-size: 0.85rem;
  margin-top: 0.35rem;
}

.place-offers__input,
.place-offers__textarea,
.place-offers__file {
  width: 100%;
  box-sizing: border-box;
}

.place-offers__actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.place-offers__gallery {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.place-offers__gallery-caption {
  margin: 0;
  font-size: 0.8rem;
  opacity: 0.85;
}

.place-offers__thumb-wrap {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.place-offers__thumb {
  width: 48px;
  height: 48px;
  object-fit: cover;
  border-radius: 4px;
}

.place-offers__check {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.85rem;
}

.place-offers__csvInput {
  display: none;
}

.place-offers__pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border);
}

.place-offers__page-info {
  font-size: 0.9rem;
  opacity: 0.8;
}
</style>
