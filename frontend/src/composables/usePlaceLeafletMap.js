import { watch } from 'vue'
import L from '../lib/leaflet-init.js'
import { loadLeafletDraw } from '../lib/leaflet-draw-loader.js'

const MARKER_COLOR = '#3388ff'

const DEFAULT_CENTER = { lat: 52.517, lng: 13.388 }

function hasValidPin(m) {
  const lat = m.latitude
  const lng = m.longitude
  if (lat == null || lng == null) return false
  const la = Number(lat)
  const lo = Number(lng)
  return Number.isFinite(la) && Number.isFinite(lo)
}

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
})

/**
 * Leaflet map + marker + radius circle + polygon draw for place location.
 * @param {import('vue').Ref<HTMLElement | null>} mapContainerRef
 * @param {{
 *   getModel: () => Record<string, unknown>,
 *   getAreaType: () => string,
 *   patchModel: (partial: Record<string, unknown>) => void,
 *   readOnly?: boolean,
 * }} ctx
 */
export function usePlaceLeafletMap(mapContainerRef, ctx) {
  const readOnly = Boolean(ctx.readOnly)
  function showPinForModel(m) {
    const pin = hasValidPin(m)
    if (!pin) return false
    const loc = m.location_type || 'none'
    const sat = m.service_area_type || 'none'
    return loc === 'point' || sat === 'radius' || sat === 'polygon'
  }
  let map = null
  let marker = null
  let circle = null
  let drawControl = null
  let drawnItems = null
  let readonlyPolygonLayer = null

  function makeMarkerIcon() {
    const hex = MARKER_COLOR.replace('#', '')
    const r = parseInt(hex.slice(0, 2), 16)
    const g = parseInt(hex.slice(2, 4), 16)
    const b = parseInt(hex.slice(4, 6), 16)
    return L.divIcon({
      className: 'place-location-picker__marker-wrap',
      html: `<div class="place-location-picker__marker" style="--marker-rgb:${r},${g},${b};--marker-color:${MARKER_COLOR};">
      <span class="place-location-picker__marker-dot"></span>
    </div>`,
      iconSize: [48, 48],
      iconAnchor: [24, 24],
    })
  }

  function placeMarker(lat, lng) {
    if (!map) return
    if (marker) map.removeLayer(marker)
    marker = L.marker([lat, lng], {
      draggable: !readOnly,
      icon: makeMarkerIcon(),
    }).addTo(map)
    if (!readOnly) {
      marker.on('dragend', (e) => {
        const p = e.target.getLatLng()
        ctx.patchModel({ latitude: p.lat, longitude: p.lng })
        refreshOverlays()
      })
    }
    map.setView([lat, lng], Math.max(map.getZoom(), 13), { animate: false })
  }

  function removeMarker() {
    if (map && marker) {
      map.removeLayer(marker)
    }
    marker = null
  }

  function refreshOverlays() {
    if (!map) return
    const m = ctx.getModel()
    const pin = hasValidPin(m)
    const lat = pin ? Number(m.latitude) : NaN
    const lng = pin ? Number(m.longitude) : NaN
    const sat = m.service_area_type
    const rm = m.radius_meters
    const showPin = showPinForModel(m)
    if (marker) {
      if (showPin) {
        marker.setLatLng([lat, lng])
      } else {
        removeMarker()
      }
    }
    if (circle) {
      map.removeLayer(circle)
      circle = null
    }
    if (sat === 'radius' && rm && pin && showPin) {
      circle = L.circle([lat, lng], {
        radius: rm,
        color: MARKER_COLOR,
        fillColor: MARKER_COLOR,
        fillOpacity: 0.2,
      }).addTo(map)
    }
  }

  function handlePolygonLayer(layer) {
    const geoJson = layer.toGeoJSON()
    const geometry = JSON.parse(JSON.stringify(geoJson.geometry))
    const coords = geometry.coordinates?.[0]
    if (coords?.length) {
      const first = coords[0]
      const last = coords[coords.length - 1]
      if (first[0] !== last[0] || first[1] !== last[1]) {
        coords.push([first[0], first[1]])
      }
    }
    const center = layer.getBounds().getCenter()
    ctx.patchModel({
      area_geojson: geometry,
      latitude: center.lat,
      longitude: center.lng,
      location_type: 'point',
    })
    placeMarker(center.lat, center.lng)
  }

  function teardownDraw() {
    if (!map) return
    const drawEvent = typeof L !== 'undefined' && L.Draw?.Event
    if (drawEvent) {
      map.off(drawEvent.CREATED)
      map.off(drawEvent.EDITED)
      map.off(drawEvent.DELETED)
    }
    if (drawControl) {
      try {
        map.removeControl(drawControl)
      } catch {
        // ignore
      }
      drawControl = null
    }
  }

  function clearReadonlyPolygon() {
    if (map && readonlyPolygonLayer) {
      map.removeLayer(readonlyPolygonLayer)
    }
    readonlyPolygonLayer = null
  }

  function addReadonlyPolygonLayer() {
    if (!map || !readOnly) return
    clearReadonlyPolygon()
    const m = ctx.getModel()
    const geo = m.area_geojson
    if (ctx.getAreaType() !== 'polygon' || !geo || typeof geo !== 'object' || geo.type !== 'Polygon') {
      return
    }
    readonlyPolygonLayer = L.geoJSON(
      { type: 'Feature', geometry: geo },
      {
        style: {
          color: MARKER_COLOR,
          fillColor: MARKER_COLOR,
          fillOpacity: 0.2,
        },
      },
    ).addTo(map)
    try {
      map.fitBounds(readonlyPolygonLayer.getBounds(), { padding: [24, 24], maxZoom: 15 })
    } catch {
      /* ignore invalid bounds */
    }
  }

  async function setupDrawControls() {
    if (!map) return
    if (readOnly) {
      addReadonlyPolygonLayer()
      return
    }

    // Load leaflet-draw only if needed
    if (ctx.getAreaType() === 'polygon') {
      await loadLeafletDraw()
    }

    teardownDraw()
    if (!drawnItems) {
      drawnItems = new L.FeatureGroup()
      map.addLayer(drawnItems)
    } else {
      drawnItems.clearLayers()
    }

    const geo = ctx.getModel().area_geojson
    if (ctx.getAreaType() === 'polygon' && geo?.type === 'Polygon') {
      const gj = L.geoJSON(
        { type: 'Feature', geometry: geo },
        {
          style: {
            color: MARKER_COLOR,
            fillColor: MARKER_COLOR,
            fillOpacity: 0.2,
          },
        },
      )
      gj.eachLayer((ly) => {
        drawnItems.addLayer(ly)
      })
    }

    if (ctx.getAreaType() !== 'polygon') {
      return
    }

    drawControl = new L.Control.Draw({
      draw: {
        polygon: {
          allowIntersection: false,
          shapeOptions: { color: MARKER_COLOR, fillColor: MARKER_COLOR, fillOpacity: 0.2 },
        },
        polyline: false,
        rectangle: false,
        circle: false,
        circlemarker: false,
        marker: false,
      },
      edit: { featureGroup: drawnItems, remove: true },
    })
    map.addControl(drawControl)

    map.on(L.Draw.Event.CREATED, (e) => {
      drawnItems.clearLayers()
      const layer = e.layer
      layer.setStyle({ color: MARKER_COLOR, fillColor: MARKER_COLOR, fillOpacity: 0.2 })
      drawnItems.addLayer(layer)
      handlePolygonLayer(layer)
    })

    map.on(L.Draw.Event.EDITED, (e) => {
      e.layers.eachLayer((ly) => {
        if (ly instanceof L.Polygon) {
          handlePolygonLayer(ly)
        }
      })
    })

    map.on(L.Draw.Event.DELETED, () => {
      ctx.patchModel({ area_geojson: null })
    })
  }

  function canSetPointFromMapClick() {
    if (ctx.getAreaType() === 'polygon') return false
    const m = ctx.getModel()
    const loc = m.location_type || 'none'
    const sat = m.service_area_type || 'none'
    if (sat === 'none' && loc === 'none') return false
    return loc === 'point' || sat === 'radius'
  }

  function onMapClick(e) {
    if (readOnly) return
    if (!canSetPointFromMapClick()) return
    const { lat, lng } = e.latlng
    ctx.patchModel({ latitude: lat, longitude: lng })
    placeMarker(lat, lng)
    refreshOverlays()
  }

  function initMap() {
    const el = mapContainerRef.value
    if (!el) return
    const m = ctx.getModel()
    const pin = hasValidPin(m)
    const showPin = showPinForModel(m)
    const centerLat = pin ? Number(m.latitude) : DEFAULT_CENTER.lat
    const centerLng = pin ? Number(m.longitude) : DEFAULT_CENTER.lng
    const zoom = pin && showPin ? 13 : pin ? 11 : 5
    map = L.map(el).setView([centerLat, centerLng], zoom)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
      attribution: '&copy; OpenStreetMap &copy; CARTO',
      subdomains: 'abcd',
      maxZoom: 20,
    }).addTo(map)

    if (!readOnly) {
      map.on('click', onMapClick)
    }

    if (pin && showPin) {
      placeMarker(Number(m.latitude), Number(m.longitude))
    }
    setupDrawControls()
    refreshOverlays()
  }

  function panTo(lat, lng, zoom = 15) {
    if (!map) return
    map.setView([lat, lng], Math.max(map.getZoom(), zoom), { animate: true })
  }

  function destroyMap() {
    teardownDraw()
    clearReadonlyPolygon()
    if (map) {
      map.remove()
      map = null
    }
    marker = null
    circle = null
    drawnItems = null
  }

  function invalidateSize() {
    map?.invalidateSize()
  }

  watch(
    () => {
      const m = ctx.getModel()
      return [m.latitude, m.longitude, m.service_area_type, m.location_type]
    },
    () => {
      if (!map) return
      if (ctx.getModel().service_area_type === 'polygon') return
      const m = ctx.getModel()
      const pin = hasValidPin(m)
      const showPin = showPinForModel(m)
      if (!pin || !showPin) {
        removeMarker()
        refreshOverlays()
        return
      }
      if (!marker) {
        placeMarker(Number(m.latitude), Number(m.longitude))
      } else {
        marker.setLatLng([Number(m.latitude), Number(m.longitude)])
      }
      refreshOverlays()
    },
  )

  return {
    initMap,
    destroyMap,
    placeMarker,
    refreshOverlays,
    setupDrawControls,
    teardownDraw,
    panTo,
    invalidateSize,
    getMap: () => map,
  }
}
