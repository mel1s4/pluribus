<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'
import PlaceMapDetailsSidebar from '../../components/App/PlaceMapDetailsSidebar.vue'
import { fetchCommunityPlacesMap, fetchPlace } from '../../services/placesApi.js'
import { useCommunityPlacesMap } from '../../composables/useCommunityPlacesMap.js'

const mapContainer = ref(null)
const places = ref([])
const placeDetailsById = ref({})
const selectedPlaceId = ref('')
const loading = ref(false)
const error = ref('')

const selectedPlace = computed(() => {
  const id = String(selectedPlaceId.value || '')
  if (!id) return null
  return placeDetailsById.value[id] || places.value.find((p) => String(p.id) === id) || null
})

const mapApi = useCommunityPlacesMap(mapContainer, {
  getPlaces: () => places.value,
  getSelectedPlaceId: () => selectedPlaceId.value,
  getSelectedPlace: () => selectedPlace.value,
  onSelectPlace: (id) => {
    selectedPlaceId.value = String(id)
  },
})

async function loadPlaces() {
  loading.value = true
  error.value = ''
  const { ok, status, data } = await fetchCommunityPlacesMap()
  loading.value = false
  if (!ok) {
    error.value = t('map.loadError').replace('{status}', String(status))
    places.value = []
    return
  }
  const list = Array.isArray(data?.data) ? data.data : []
  places.value = list
}

async function loadPlaceDetails(id) {
  const key = String(id || '')
  if (!key || placeDetailsById.value[key]) return
  const { ok, data } = await fetchPlace(key)
  if (!ok) return
  const place = data?.place
  if (!place || place.id == null) return
  placeDetailsById.value = {
    ...placeDetailsById.value,
    [String(place.id)]: place,
  }
}

function closeSidebar() {
  selectedPlaceId.value = ''
}

onMounted(async () => {
  mapApi.initMap()
  await loadPlaces()
})

watch(selectedPlaceId, async () => {
  await loadPlaceDetails(selectedPlaceId.value)
  await nextTick()
  mapApi.invalidateSize()
})

onBeforeUnmount(() => {
  mapApi.destroyMap()
})
</script>

<template>
  <section class="page page--map">
    <div class="map-view__head">
      <Title tag="h1">{{ t('map.title') }}</Title>
      <p class="page__muted">{{ t('map.placeholder') }}</p>
      <p v-if="loading" class="page__muted">{{ t('myPlaces.loading') }}</p>
      <p v-if="error" class="map-view__error">{{ error }}</p>
    </div>
    <div class="map-view__layout">
      <div ref="mapContainer" class="map-view__map" />
      <PlaceMapDetailsSidebar :place="selectedPlace" @close="closeSidebar" />
    </div>
  </section>
</template>

<style scoped lang="scss">
.page--map {
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-height: 0;
  height: 100%;
}

.page__muted {
  margin: 0;
  opacity: 0.8;
}

.map-view__head {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.map-view__layout {
  display: flex;
  border: 1px solid var(--border);
  border-radius: 8px;
  overflow: hidden;
  min-height: 65vh;
}

.map-view__map {
  flex: 1 1 auto;
  min-height: 65vh;
}

.map-view__error {
  margin: 0;
  color: var(--danger, #b00020);
}

@media (max-width: 960px) {
  .map-view__layout {
    flex-direction: column;
    min-height: 70vh;
  }

  .map-view__map {
    min-height: 50vh;
  }
}
</style>