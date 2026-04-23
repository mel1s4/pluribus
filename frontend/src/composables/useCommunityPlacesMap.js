import { watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const DEFAULT_CENTER = [52.517, 13.388]

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

export function useCommunityPlacesMap(mapContainerRef, ctx) {
  let map = null
  let markersLayer = null
  let serviceLayer = null
  const markerById = new Map()

  function initMap() {
    const el = mapContainerRef.value
    if (!el) return
    map = L.map(el).setView(DEFAULT_CENTER, 5)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
      attribution: '&copy; OpenStreetMap &copy; CARTO',
      subdomains: 'abcd',
      maxZoom: 20,
    }).addTo(map)
    markersLayer = L.layerGroup().addTo(map)
    serviceLayer = L.layerGroup().addTo(map)
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
      const marker = L.marker([Number(place.latitude), Number(place.longitude)], {
        title: place.name || '',
      })
      marker.on('click', () => ctx.onSelectPlace(place.id))
      marker.addTo(markersLayer)
      markerById.set(String(place.id), marker)
      bounds.push([Number(place.latitude), Number(place.longitude)])
    }
    if (bounds.length > 0) {
      map.fitBounds(bounds, { padding: [24, 24], maxZoom: 13 })
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

  function destroyMap() {
    markerById.clear()
    if (map) {
      map.remove()
      map = null
    }
    markersLayer = null
    serviceLayer = null
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
  }
}
