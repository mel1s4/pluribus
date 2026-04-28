import { watch } from 'vue'
import L from '../lib/leaflet-init.js'

const DEFAULT_CENTER = [52.517, 13.388]
const DEFAULT_STORAGE_KEY = 'community-map:view-state:v1'
const GEOLOCATE_ZOOM = 15

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
})

function hasCoordinates(place) {
  const lat = Number(place?.latitude)
  const lng = Number(place?.longitude)
  return Number.isFinite(lat) && Number.isFinite(lng)
}

function normalizedHexColor(input) {
  if (typeof input !== 'string') return null
  const trimmed = input.trim()
  return /^#[0-9A-Fa-f]{6}$/.test(trimmed) ? trimmed : null
}

function markerIconForPlace(place) {
  const color = normalizedHexColor(place?.logo_background_color)
  if (!color) return null
  return L.divIcon({
    className: 'community-place-marker',
    html: `<span style="display:block;width:18px;height:18px;border-radius:999px;background:${color};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.35);"></span>`,
    iconSize: [18, 18],
    iconAnchor: [9, 9],
  })
}

export function useCommunityPlacesMap(mapContainerRef, ctx) {
  let map = null
  let markersLayer = null
  let serviceLayer = null
  let userLocationLayer = null
  const markerById = new Map()
  let isRestoringView = false
  let hasRestoredView = false
  let hasAutoFitBounds = false

  function storageKey() {
    if (typeof ctx.getViewStorageKey === 'function') {
      const customKey = ctx.getViewStorageKey()
      if (typeof customKey === 'string' && customKey.trim().length > 0) {
        return customKey.trim()
      }
    }
    return DEFAULT_STORAGE_KEY
  }

  function readSavedView() {
    if (typeof window === 'undefined' || !window.localStorage) return null
    try {
      const raw = window.localStorage.getItem(storageKey())
      if (!raw) return null
      const parsed = JSON.parse(raw)
      const lat = Number(parsed?.lat)
      const lng = Number(parsed?.lng)
      const zoom = Number(parsed?.zoom)
      if (!Number.isFinite(lat) || !Number.isFinite(lng) || !Number.isFinite(zoom)) return null
      return { center: [lat, lng], zoom }
    } catch {
      return null
    }
  }

  function persistCurrentView() {
    if (!map || typeof window === 'undefined' || !window.localStorage || isRestoringView) return
    const center = map.getCenter()
    const zoom = map.getZoom()
    if (!Number.isFinite(center?.lat) || !Number.isFinite(center?.lng) || !Number.isFinite(zoom)) return
    try {
      window.localStorage.setItem(
        storageKey(),
        JSON.stringify({
          lat: Number(center.lat),
          lng: Number(center.lng),
          zoom: Number(zoom),
          updatedAt: Date.now(),
        }),
      )
    } catch {
      // Ignore storage write failures (private mode, quota, etc.)
    }
  }

  function resolveDefaultView() {
    if (typeof ctx.getDefaultCenter === 'function') {
      const c = ctx.getDefaultCenter()
      if (
        Array.isArray(c)
        && c.length === 2
        && Number.isFinite(Number(c[0]))
        && Number.isFinite(Number(c[1]))
      ) {
        return { center: [Number(c[0]), Number(c[1])], zoom: 10 }
      }
    }
    return { center: DEFAULT_CENTER, zoom: 5 }
  }

  function initMap() {
    const el = mapContainerRef.value
    if (!el) return
    const savedView = readSavedView()
    const { center, zoom } = savedView || resolveDefaultView()
    hasRestoredView = savedView != null
    map = L.map(el).setView(center, zoom)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
      attribution: '&copy; OpenStreetMap &copy; CARTO',
      subdomains: 'abcd',
      maxZoom: 20,
    }).addTo(map)
    markersLayer = L.layerGroup().addTo(map)
    serviceLayer = L.layerGroup().addTo(map)
    userLocationLayer = L.layerGroup().addTo(map)
    map.on('moveend zoomend', persistCurrentView)
    renderPlaces()
    renderSelectedServiceArea()
  }

  function renderPlaces() {
    if (!map || !markersLayer) return
    markerById.clear()
    markersLayer.clearLayers()
    const places = ctx.getPlaces()
    const bounds = []
    for (const place of places) {
      if (!hasCoordinates(place)) continue
      const lat = Number(place.latitude)
      const lng = Number(place.longitude)
      const placeIcon = markerIconForPlace(place)
      const markerOptions = { title: place.name || '' }
      if (placeIcon) markerOptions.icon = placeIcon
      const marker = L.marker([lat, lng], markerOptions)
      marker.on('click', () => ctx.onSelectPlace(place.id))
      marker.addTo(markersLayer)
      markerById.set(String(place.id), marker)
      bounds.push([lat, lng])
    }
    if (bounds.length > 0 && !hasRestoredView && !hasAutoFitBounds) {
      map.fitBounds(bounds, { padding: [24, 24], maxZoom: 13 })
      hasAutoFitBounds = true
    } else if (typeof ctx.getDefaultCenter === 'function') {
      const c = ctx.getDefaultCenter()
      if (
        Array.isArray(c)
        && c.length === 2
        && Number.isFinite(Number(c[0]))
        && Number.isFinite(Number(c[1]))
      ) {
        map.setView([Number(c[0]), Number(c[1])], 11, { animate: false })
      } else {
        map.setView(DEFAULT_CENTER, 5, { animate: false })
      }
    } else {
      map.setView(DEFAULT_CENTER, 5, { animate: false })
    }
  }

  function renderSelectedServiceArea() {
    if (!serviceLayer) return
    serviceLayer.clearLayers()
    const place = ctx.getSelectedPlace()
    if (!place || !hasCoordinates(place)) return
    const lat = Number(place.latitude)
    const lng = Number(place.longitude)
    const areaType = place.service_area_type || 'none'
    if (areaType === 'radius' && Number(place.radius_meters) > 0) {
      L.circle([lat, lng], {
        radius: Number(place.radius_meters),
        color: '#2563eb',
        fillColor: '#2563eb',
        fillOpacity: 0.15,
      }).addTo(serviceLayer)
    }
    if (areaType === 'polygon' && place.area_geojson?.type === 'Polygon') {
      L.geoJSON(
        { type: 'Feature', geometry: place.area_geojson },
        {
          style: {
            color: '#2563eb',
            fillColor: '#2563eb',
            fillOpacity: 0.15,
          },
        },
      ).addTo(serviceLayer)
    }
  }

  function focusSelected() {
    if (!map) return
    const place = ctx.getSelectedPlace()
    if (!place || !hasCoordinates(place)) return
    map.setView([Number(place.latitude), Number(place.longitude)], Math.max(13, map.getZoom()), { animate: true })
  }

  function invalidateSize() {
    map?.invalidateSize()
  }

  function renderUserLocation(lat, lng) {
    if (!userLocationLayer) return
    userLocationLayer.clearLayers()
    const pulseIcon = L.divIcon({
      className: 'community-user-location-marker',
      html:
        '<span class="community-user-location-marker__pulse"></span><span class="community-user-location-marker__dot"></span>',
      iconSize: [24, 24],
      iconAnchor: [12, 12],
    })
    L.marker([lat, lng], { icon: pulseIcon, interactive: false }).addTo(userLocationLayer)
  }

  async function showMyLocation() {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      return { ok: false, reason: 'unsupported' }
    }
    const position = await new Promise((resolve, reject) => {
      navigator.geolocation.getCurrentPosition(resolve, reject, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 60000,
      })
    }).catch((error) => ({ error }))
    if (position?.error) {
      const code = Number(position.error.code)
      if (code === 1) return { ok: false, reason: 'denied' }
      if (code === 3) return { ok: false, reason: 'timeout' }
      return { ok: false, reason: 'unavailable' }
    }
    const lat = Number(position?.coords?.latitude)
    const lng = Number(position?.coords?.longitude)
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
      return { ok: false, reason: 'unavailable' }
    }
    if (!map) return { ok: false, reason: 'unavailable' }
    renderUserLocation(lat, lng)
    isRestoringView = true
    map.setView([lat, lng], Math.max(GEOLOCATE_ZOOM, map.getZoom()), { animate: true })
    isRestoringView = false
    persistCurrentView()
    return { ok: true }
  }

  function destroyMap() {
    markerById.clear()
    if (map) {
      map.off('moveend zoomend', persistCurrentView)
      map.remove()
      map = null
    }
    markersLayer = null
    serviceLayer = null
    userLocationLayer = null
    hasAutoFitBounds = false
    hasRestoredView = false
  }

  watch(
    () => ctx.getPlaces(),
    () => {
      renderPlaces()
      renderSelectedServiceArea()
    },
    { deep: true },
  )

  watch(
    () => ctx.getSelectedPlaceId(),
    () => {
      renderSelectedServiceArea()
      focusSelected()
    },
  )

  return {
    initMap,
    destroyMap,
    invalidateSize,
    showMyLocation,
  }
}
