<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from '../../lib/leaflet-init.js'
import { t } from '../../i18n/i18n'

const DEFAULT_CENTER = [52.517, 13.388]

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ latitude: null, longitude: null }),
  },
  readOnly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

const mapEl = ref(null)
let map = null
let marker = null

function validPair(lat, lng) {
  const a = Number(lat)
  const b = Number(lng)
  return Number.isFinite(a) && Number.isFinite(b)
}

function emitPos(lat, lng) {
  emit('update:modelValue', { latitude: lat, longitude: lng })
}

function clearMarker() {
  if (marker && map) {
    map.removeLayer(marker)
  }
  marker = null
}

function ensureMarker(lat, lng) {
  if (!map) {
    return
  }
  if (marker) {
    marker.setLatLng([lat, lng])
  } else {
    marker = L.marker([lat, lng], { draggable: !props.readOnly }).addTo(map)
    marker.on('dragend', () => {
      if (!marker) {
        return
      }
      const p = marker.getLatLng()
      emitPos(p.lat, p.lng)
    })
  }
}

function applyModelToMap() {
  if (!map) {
    return
  }
  const { latitude, longitude } = props.modelValue || {}
  if (validPair(latitude, longitude)) {
    const lat = Number(latitude)
    const lng = Number(longitude)
    ensureMarker(lat, lng)
    map.setView([lat, lng], Math.max(map.getZoom(), 10), { animate: false })
  } else {
    clearMarker()
    map.setView(DEFAULT_CENTER, 5, { animate: false })
  }
}

function onMapClick(e) {
  if (props.readOnly) {
    return
  }
  const { lat, lng } = e.latlng
  ensureMarker(lat, lng)
  emitPos(lat, lng)
}

function initMap() {
  const el = mapEl.value
  if (!el) {
    return
  }
  const start = (() => {
    const { latitude, longitude } = props.modelValue || {}
    if (validPair(latitude, longitude)) {
      return [Number(latitude), Number(longitude)]
    }
    return DEFAULT_CENTER
  })()
  const startZoom = validPair(props.modelValue?.latitude, props.modelValue?.longitude) ? 10 : 5
  map = L.map(el).setView(start, startZoom)
  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap &copy; CARTO',
    subdomains: 'abcd',
    maxZoom: 20,
  }).addTo(map)
  if (!props.readOnly) {
    map.on('click', onMapClick)
  }
  nextTick(() => {
    map?.invalidateSize()
    applyModelToMap()
  })
}

onMounted(() => {
  initMap()
})

onBeforeUnmount(() => {
  if (map) {
    map.remove()
  }
  map = null
  marker = null
})

watch(
  () => [props.modelValue?.latitude, props.modelValue?.longitude],
  () => {
    if (!map) {
      return
    }
    applyModelToMap()
  },
)

watch(
  () => props.readOnly,
  (ro) => {
    if (marker) {
      marker.dragging[ro ? 'disable' : 'enable']()
    }
  },
)

function clearCenter() {
  if (props.readOnly) {
    return
  }
  clearMarker()
  emit('update:modelValue', { latitude: null, longitude: null })
  if (map) {
    map.setView(DEFAULT_CENTER, 5)
  }
}
</script>

<template>
  <div class="community-map-center-picker">
    <p class="community-map-center-picker__hint">
      {{ t('communitySettings.mapCenterHint') }}
    </p>
    <div
      ref="mapEl"
      class="community-map-center-picker__map"
      role="presentation"
    />
    <p v-if="!readOnly" class="community-map-center-picker__help">
      {{ t('communitySettings.mapCenterHelp') }}
    </p>
    <button
      v-if="!readOnly"
      type="button"
      class="community-map-center-picker__clear btn btn--secondary btn--sm"
      @click="clearCenter"
    >
      {{ t('communitySettings.mapCenterClear') }}
    </button>
  </div>
</template>

<style scoped lang="scss">
.community-map-center-picker {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  width: 100%;
  max-width: 32rem;
}

.community-map-center-picker__hint,
.community-map-center-picker__help {
  margin: 0;
  font-size: 0.88rem;
  color: var(--muted, #6b7280);
}

.community-map-center-picker__map {
  width: 100%;
  height: 220px;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  overflow: hidden;
}
</style>
