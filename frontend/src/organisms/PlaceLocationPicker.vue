<script setup>
import { ref, watch, onMounted, onBeforeUnmount, computed, nextTick } from 'vue'
import PlaceLocationMapToolbar from '../molecules/PlaceLocationMapToolbar.vue'
import { usePlaceLeafletMap } from '../composables/usePlaceLeafletMap.js'
import { t } from '../i18n/i18n'

const props = defineProps({
  modelValue: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['update:modelValue'])

const mapContainer = ref(null)
const radiusKm = ref(1)
const geolocateBusy = ref(false)
const geolocateError = ref('')

function emitFull(next) {
  emit('update:modelValue', { ...props.modelValue, ...next })
}

const leaflet = usePlaceLeafletMap(mapContainer, {
  getModel: () => props.modelValue,
  getAreaType: () => props.modelValue.service_area_type || 'none',
  patchModel: emitFull,
})

const areaType = computed({
  get: () => props.modelValue.service_area_type || 'none',
  set: (v) => {
    const patch = { service_area_type: v }
    if (v === 'none') {
      patch.radius_meters = null
      patch.area_geojson = null
    } else if (v === 'radius') {
      patch.area_geojson = null
      patch.radius_meters = Math.max(1, Math.round(radiusKm.value * 1000))
    } else if (v === 'polygon') {
      patch.radius_meters = null
    }
    emitFull(patch)
    nextTick(() => {
      leaflet.teardownDraw()
      leaflet.setupDrawControls()
      leaflet.refreshOverlays()
    })
  },
})

function pushRadius() {
  if (areaType.value !== 'radius') return
  emitFull({ radius_meters: Math.max(1, Math.round(radiusKm.value * 1000)) })
  leaflet.refreshOverlays()
}

function syncRadiusKmFromModel() {
  const m = props.modelValue.radius_meters
  radiusKm.value = m ? Math.round((m / 1000) * 10) / 10 : 1
}

watch(
  () => props.modelValue.radius_meters,
  () => syncRadiusKmFromModel(),
  { immediate: true },
)

function geolocateErrorMessage(code) {
  if (code === 1) return t('myPlaces.geolocateDenied')
  if (code === 2) return t('myPlaces.geolocateUnavailable')
  if (code === 3) return t('myPlaces.geolocateTimeout')
  return t('myPlaces.geolocateUnavailable')
}

function onCenterMyLocation() {
  geolocateError.value = ''
  if (!navigator.geolocation) {
    geolocateError.value = t('myPlaces.geolocateUnavailable')
    return
  }
  geolocateBusy.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const lat = pos.coords.latitude
      const lng = pos.coords.longitude
      if (areaType.value === 'polygon') {
        leaflet.panTo(lat, lng)
      } else {
        emitFull({ latitude: lat, longitude: lng })
        leaflet.placeMarker(lat, lng)
        leaflet.refreshOverlays()
      }
      geolocateBusy.value = false
    },
    (err) => {
      geolocateBusy.value = false
      geolocateError.value = geolocateErrorMessage(err?.code ?? 2)
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
  )
}

function onSelectSearchResult({ lat, lng }) {
  geolocateError.value = ''
  if (areaType.value === 'polygon') {
    leaflet.panTo(lat, lng)
  } else {
    emitFull({ latitude: lat, longitude: lng })
    leaflet.placeMarker(lat, lng)
    leaflet.refreshOverlays()
  }
}

let resizeObserver = null

onMounted(() => {
  leaflet.initMap()
  resizeObserver = new ResizeObserver(() => leaflet.invalidateSize())
  if (mapContainer.value) {
    resizeObserver.observe(mapContainer.value)
  }
})

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  resizeObserver = null
  leaflet.destroyMap()
})
</script>

<template>
  <div class="place-location-picker">
    <p class="place-location-picker__location-intro">
      {{ t('myPlaces.locationIntro') }}
    </p>
    <fieldset class="place-location-picker__fieldset">
      <legend class="place-location-picker__legend">{{ t('myPlaces.serviceArea') }}</legend>
      <div class="place-location-picker__radios">
        <label class="place-location-picker__radio">
          <input
            v-model="areaType"
            type="radio"
            value="none"
          />
          <span>{{ t('myPlaces.serviceAreaNone') }}</span>
        </label>
        <label class="place-location-picker__radio">
          <input
            v-model="areaType"
            type="radio"
            value="radius"
          />
          <span>{{ t('myPlaces.serviceAreaRadius') }}</span>
        </label>
        <label class="place-location-picker__radio">
          <input
            v-model="areaType"
            type="radio"
            value="polygon"
          />
          <span>{{ t('myPlaces.serviceAreaPolygon') }}</span>
        </label>
      </div>
    </fieldset>

    <PlaceLocationMapToolbar
      :geolocate-busy="geolocateBusy"
      @center-my-location="onCenterMyLocation"
      @select-search-result="onSelectSearchResult"
    />

    <p
      v-if="geolocateError"
      class="place-location-picker__geo-error"
      role="alert"
    >
      {{ geolocateError }}
    </p>

    <div ref="mapContainer" class="place-location-picker__map" />

    <div v-if="areaType === 'radius'" class="place-location-picker__radius">
      <label class="place-location-picker__radius-label" for="place-radius-km">{{ t('myPlaces.radiusKm') }}</label>
      <input
        id="place-radius-km"
        v-model.number="radiusKm"
        class="place-location-picker__radius-input"
        type="number"
        min="0.1"
        max="50"
        step="0.1"
        @change="pushRadius"
      />
    </div>

    <p v-if="areaType === 'polygon'" class="place-location-picker__hint">
      {{ t('myPlaces.polygonHint') }}
    </p>
  </div>
</template>

<style lang="scss" scoped>
.place-location-picker {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.place-location-picker__location-intro {
  margin: 0;
  font-size: 0.85rem;
  opacity: 0.85;
}

.place-location-picker__fieldset {
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 0.75rem 1rem;
  margin: 0;
}

.place-location-picker__legend {
  padding: 0 0.35rem;
  font-size: 0.9rem;
}

.place-location-picker__radios {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1.25rem;
}

.place-location-picker__radio {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  cursor: pointer;
}

.place-location-picker__geo-error {
  margin: 0;
  font-size: 0.85rem;
  color: var(--danger, #b00020);
}

.place-location-picker__map {
  width: 100%;
  height: min(45vh, 360px);
  min-height: 220px;
  border-radius: 8px;
  border: 1px solid var(--border);
  overflow: hidden;
}

.place-location-picker__radius {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.place-location-picker__radius-label {
  font-size: 0.9rem;
}

.place-location-picker__radius-input {
  width: 5rem;
}

.place-location-picker__hint {
  margin: 0;
  font-size: 0.85rem;
  opacity: 0.85;
}

:deep(.place-location-picker__marker) {
  position: relative;
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
}

:deep(.place-location-picker__marker-dot) {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--marker-color);
  box-shadow: 0 0 0 2px rgba(var(--marker-rgb), 0.85);
}
</style>
