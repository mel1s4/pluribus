<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'
import PlaceMapDetailsSidebar from '../../components/App/PlaceMapDetailsSidebar.vue'
import { fetchCommunityPlacesMap, fetchPlace } from '../../services/placesApi.js'
import { fetchCommunity } from '../../services/communityApi.js'
import { useCommunityPlacesMap } from '../../composables/useCommunityPlacesMap.js'

const route = useRoute()
const router = useRouter()

const mapContainer = ref(null)
const places = ref([])
const placeDetailsById = ref({})
const loading = ref(false)
const error = ref('')
const community = ref(null)
const locating = ref(false)

function defaultMapCenter() {
  const c = community.value
  if (c == null) {
    return null
  }
  const lat = Number(c.latitude)
  const lng = Number(c.longitude)
  if (Number.isFinite(lat) && Number.isFinite(lng)) {
    return [lat, lng]
  }
  return null
}

async function loadCommunity() {
  const { ok, data } = await fetchCommunity()
  if (ok && data?.community) {
    community.value = data.community
  }
}

const selectedPlaceId = computed(() => {
  const raw = route.params.placeId
  if (typeof raw === 'string' && /^\d+$/.test(raw)) {
    return raw
  }
  return ''
})

const mapSidebarTab = computed(() => {
  const raw = route.params.tab
  if (raw === 'overview' || raw === 'offers' || raw === 'requirements') {
    return raw
  }
  if (raw === undefined) {
    return 'overview'
  }
  return 'overview'
})

const selectedPlace = computed(() => {
  const id = String(selectedPlaceId.value || '')
  if (!id) return null
  return placeDetailsById.value[id] || places.value.find((p) => String(p.id) === id) || null
})

const mapApi = useCommunityPlacesMap(mapContainer, {
  getPlaces: () => places.value,
  getSelectedPlaceId: () => selectedPlaceId.value,
  getSelectedPlace: () => selectedPlace.value,
  getDefaultCenter: defaultMapCenter,
  getViewStorageKey: () => {
    const id = String(community.value?.id || 'default')
    return `community-map:view-state:v1:${id}`
  },
  onSelectPlace: (id) => {
    router.push({
      name: 'map',
      params: { placeId: String(id), tab: 'overview' },
    })
  },
})

async function onShowMyLocation() {
  if (locating.value) return
  locating.value = true
  error.value = ''
  const result = await mapApi.showMyLocation()
  locating.value = false
  if (result?.ok) return
  if (result?.reason === 'denied') {
    error.value = t('map.geolocateDenied')
    return
  }
  if (result?.reason === 'timeout') {
    error.value = t('map.geolocateTimeout')
    return
  }
  error.value = t('map.geolocateUnavailable')
}

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
  router.push({ name: 'map' })
}

function onMapSidebarTabSelect(next) {
  const id = selectedPlaceId.value
  if (!id) return
  router.push({
    name: 'map',
    params: { placeId: id, tab: next },
  })
}

onMounted(async () => {
  await loadCommunity()
  mapApi.initMap()
  await loadPlaces()
})

watch(selectedPlaceId, async () => {
  await loadPlaceDetails(selectedPlaceId.value)
  await nextTick()
  mapApi.invalidateSize()
})

watch(
  () => [route.params.placeId, route.params.tab],
  ([pId, t]) => {
    if (pId == null) {
      if (t != null) {
        router.replace({ name: 'map' })
      }
      return
    }
    if (typeof pId === 'string' && pId.length > 0 && !/^\d+$/.test(pId)) {
      router.replace({ name: 'map' })
      return
    }
    if (pId && t != null && t !== 'overview' && t !== 'offers' && t !== 'requirements') {
      router.replace({
        name: 'map',
        params: { placeId: String(pId), tab: 'overview' },
      })
    }
  },
  { immediate: true },
)

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
      <div class="map-view__map-wrap">
        <div class="map-view__controls">
          <button
            type="button"
            class="map-view__my-location-btn"
            :disabled="locating"
            @click="onShowMyLocation"
          >
            {{ locating ? t('map.geolocateLoading') : t('map.showMyLocation') }}
          </button>
        </div>
        <div ref="mapContainer" class="map-view__map" />
      </div>
      <PlaceMapDetailsSidebar
        :place="selectedPlace"
        :active-tab="mapSidebarTab"
        @close="closeSidebar"
        @select-tab="onMapSidebarTabSelect"
      />
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

.map-view__map-wrap {
  position: relative;
  flex: 1 1 auto;
  min-height: 65vh;
}

.map-view__controls {
  position: absolute;
  top: 0.75rem;
  left: 0.75rem;
  z-index: 500;
}

.map-view__my-location-btn {
  border: 1px solid var(--border);
  background: var(--surface, #fff);
  color: var(--text, #111827);
  border-radius: 8px;
  padding: 0.45rem 0.7rem;
  font-weight: 600;
  cursor: pointer;
}

.map-view__my-location-btn:disabled {
  opacity: 0.65;
  cursor: wait;
}

:deep(.community-user-location-marker) {
  position: relative;
}

:deep(.community-user-location-marker__dot) {
  position: absolute;
  inset: 8px;
  border-radius: 999px;
  background: #ff1f1f;
  box-shadow: 0 0 0 2px #fff;
}

:deep(.community-user-location-marker__pulse) {
  position: absolute;
  inset: 0;
  border-radius: 999px;
  background: rgba(255, 31, 31, 0.28);
  animation: map-view-location-pulse 1.8s ease-out infinite;
}

@keyframes map-view-location-pulse {
  0% {
    transform: scale(0.4);
    opacity: 0.95;
  }
  70% {
    transform: scale(1.8);
    opacity: 0;
  }
  100% {
    transform: scale(1.8);
    opacity: 0;
  }
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

  .map-view__map-wrap {
    min-height: 50vh;
  }
}
</style>