<script setup>
import { ref, watch } from 'vue'
import { useCommunity } from '../composables/useCommunity'
import { t } from '../i18n/i18n'
import { fetchCommunityPlaceOffers } from '../services/placesApi.js'
import { formatOfferPrice } from '../utils/formatPrice'

const { communityCurrencyCode } = useCommunity()

function formatPrice(amount) {
  return formatOfferPrice(amount, communityCurrencyCode.value)
}

const props = defineProps({
  modelValue: {
    default: null,
    validator: (v) => v === null || v === undefined || typeof v === 'number',
  },
})

const emit = defineEmits(['update:modelValue'])

const q = ref('')
const loading = ref(false)
const results = ref([])
const selectedPreview = ref(null)

let debounceTimer = null

async function runSearch() {
  loading.value = true
  const { ok, data } = await fetchCommunityPlaceOffers({ q: q.value.trim() })
  loading.value = false
  if (!ok || !data) {
    results.value = []
    return
  }
  const raw = Array.isArray(data.data) ? data.data : []
  results.value = raw
}

function scheduleSearch() {
  window.clearTimeout(debounceTimer)
  debounceTimer = window.setTimeout(() => {
    runSearch()
  }, 320)
}

watch(q, () => {
  scheduleSearch()
})

watch(
  () => props.modelValue,
  async (id) => {
    if (id == null || id === '') {
      selectedPreview.value = null
      return
    }
    loading.value = true
    const { ok, data } = await fetchCommunityPlaceOffers({ q: '' })
    loading.value = false
    if (!ok || !data) return
    const raw = Array.isArray(data.data) ? data.data : []
    const hit = raw.find((o) => Number(o.id) === Number(id))
    selectedPreview.value = hit || null
    if (!hit && raw.length) {
      results.value = raw
    }
  },
  { immediate: true },
)

function pickOffer(o) {
  emit('update:modelValue', Number(o.id))
  selectedPreview.value = o
  results.value = []
  q.value = ''
}

function clearSelection() {
  emit('update:modelValue', null)
  selectedPreview.value = null
}

runSearch()
</script>

<template>
  <div class="req-ex-offer">
    <label class="req-ex-offer__label">{{ t('myPlaces.requirementExampleOffer') }}</label>
    <p class="req-ex-offer__hint">{{ t('myPlaces.requirementExampleOfferHint') }}</p>

    <div v-if="selectedPreview" class="req-ex-offer__picked">
      <div class="req-ex-offer__pickedBody">
        <span class="req-ex-offer__pickedTitle">{{ selectedPreview.title }}</span>
        <span v-if="selectedPreview.place?.name" class="req-ex-offer__pickedPlace">
          {{ selectedPreview.place.name }}
        </span>
        <span class="req-ex-offer__pickedPrice">{{ formatPrice(selectedPreview.price) }}</span>
      </div>
      <button type="button" class="req-ex-offer__clear" @click="clearSelection">
        {{ t('myPlaces.requirementExampleOfferClear') }}
      </button>
    </div>

    <input
      v-model="q"
      class="req-ex-offer__search"
      type="search"
      :placeholder="t('myPlaces.requirementExampleOfferSearch')"
      autocomplete="off"
    />
    <p v-if="loading" class="req-ex-offer__muted">{{ t('myPlaces.requirementExampleOfferLoading') }}</p>
    <ul v-else-if="results.length" class="req-ex-offer__results" role="listbox">
      <li
        v-for="o in results"
        :key="o.id"
        class="req-ex-offer__hit"
      >
        <button type="button" class="req-ex-offer__pick" @click="pickOffer(o)">
          <span class="req-ex-offer__hitTitle">{{ o.title }}</span>
          <span v-if="o.place?.name" class="req-ex-offer__hitPlace">{{ o.place.name }}</span>
          <span class="req-ex-offer__hitPrice">{{ formatPrice(o.price) }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>

<style lang="scss" scoped>
.req-ex-offer {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.req-ex-offer__label {
  font-size: 0.85rem;
  font-weight: 600;
  margin-top: 0.35rem;
}

.req-ex-offer__hint {
  margin: 0;
  font-size: 0.8rem;
  opacity: 0.85;
}

.req-ex-offer__picked {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.5rem;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--btn-bg, rgba(0, 0, 0, 0.03));
}

.req-ex-offer__pickedBody {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  flex: 1;
  min-width: 0;
}

.req-ex-offer__pickedTitle {
  font-weight: 600;
}

.req-ex-offer__pickedPlace {
  font-size: 0.85rem;
  opacity: 0.85;
}

.req-ex-offer__pickedPrice {
  font-variant-numeric: tabular-nums;
  font-size: 0.9rem;
}

.req-ex-offer__clear {
  cursor: pointer;
  font: inherit;
  padding: 0.2rem 0.45rem;
  border-radius: 4px;
  border: 1px solid var(--border);
  background: var(--bg);
}

.req-ex-offer__search {
  width: 100%;
  box-sizing: border-box;
}

.req-ex-offer__muted {
  margin: 0;
  font-size: 0.8rem;
  opacity: 0.8;
}

.req-ex-offer__results {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 10rem;
  overflow: auto;
  border: 1px solid var(--border);
  border-radius: 6px;
}

.req-ex-offer__hit {
  border-bottom: 1px solid var(--border);
}

.req-ex-offer__hit:last-child {
  border-bottom: none;
}

.req-ex-offer__pick {
  width: 100%;
  text-align: left;
  cursor: pointer;
  font: inherit;
  padding: 0.4rem 0.5rem;
  border: none;
  background: transparent;
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 0.75rem;
  align-items: baseline;
}

.req-ex-offer__pick:hover {
  background: var(--btn-bg);
}

.req-ex-offer__hitTitle {
  font-weight: 600;
  flex: 1;
  min-width: 6rem;
}

.req-ex-offer__hitPlace {
  font-size: 0.8rem;
  opacity: 0.85;
}

.req-ex-offer__hitPrice {
  font-variant-numeric: tabular-nums;
  font-size: 0.85rem;
}
</style>
