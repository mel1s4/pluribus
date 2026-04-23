<template>
  <section class="page page--map">
    <Title tag="h1">{{ t('map.title') }}</Title>
    <p class="page__muted">{{ t('map.placeholder') }}</p>
    <div ref="mapContainer" class="map-view__map" />
  </section>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import maplibregl from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'

const mapContainer = ref(null)
let map = null
let resizeObserver = null

onMounted(() => {
  const el = mapContainer.value
  if (!el) return

  map = new maplibregl.Map({
    container: el,
    // OSM-based streets (demotiles.maplibre.org is a minimal demo, not a road map)
    style: 'https://tiles.openfreemap.org/styles/liberty',
    center: [13.388, 52.517],
    zoom: 9.5,
  })

  resizeObserver = new ResizeObserver(() => {
    map?.resize()
  })
  resizeObserver.observe(el)
})

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  resizeObserver = null
  map?.remove()
  map = null
})
</script>

<style lang="scss" scoped>
.page--map {
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-height: 0;
}

.page__muted {
  opacity: 0.8;
}

.map-view__map {
  width: 100%;
  height: min(60vh, 560px);
  min-height: 280px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--border);
}
</style>
