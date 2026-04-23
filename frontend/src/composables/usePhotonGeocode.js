const PHOTON_BASE = 'https://photon.komoot.io/api/'

/**
 * @param {Record<string, unknown>} p Photon/OSM feature properties
 * @returns {string}
 */
function localityFromProps(p) {
  const raw = [p.city, p.town, p.village, p.district, p.county, p.state].find(
    (x) => typeof x === 'string' && x.trim(),
  )
  return typeof raw === 'string' ? raw.trim() : ''
}

/**
 * @param {GeoJSON.Feature} feature
 * @returns {{ id: string, lat: number, lng: number, title: string, subtitle: string } | null}
 */
export function photonFeatureToSuggestion(feature, index) {
  const props = feature.properties && typeof feature.properties === 'object' ? feature.properties : {}
  const coords = feature.geometry?.coordinates
  if (!Array.isArray(coords) || coords.length < 2) return null
  const lng = Number(coords[0])
  const lat = Number(coords[1])
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null

  const hn = typeof props.housenumber === 'string' ? props.housenumber.trim() : ''
  const street = typeof props.street === 'string' ? props.street.trim() : ''
  const streetLine = [hn, street].filter(Boolean).join(' ').trim()
  const name = typeof props.name === 'string' ? props.name.trim() : ''
  const locality = localityFromProps(props)
  const postcode = typeof props.postcode === 'string' ? props.postcode.trim() : ''
  const country = typeof props.country === 'string' ? props.country.trim() : ''

  const title = streetLine || name || locality || country || '—'

  const subtitleBits = []
  if (postcode) subtitleBits.push(postcode)
  if (locality && locality !== title) subtitleBits.push(locality)
  if (country) subtitleBits.push(country)
  const subtitle = subtitleBits.join(' · ')

  const osmId = props.osm_id != null ? String(props.osm_id) : ''
  const id = osmId ? `osm-${osmId}` : `idx-${index}`

  return { id, lat, lng, title, subtitle }
}

/**
 * @param {string} query
 * @param {{ lang?: string, signal?: AbortSignal, limit?: number }} [opts]
 * @returns {Promise<Array<{ id: string, lat: number, lng: number, title: string, subtitle: string }>>}
 */
export async function fetchPhotonSuggestions(query, opts = {}) {
  const { lang = 'en', signal, limit = 8 } = opts
  const q = query.trim()
  if (q.length < 3) return []

  const url = new URL(PHOTON_BASE)
  url.searchParams.set('q', q)
  url.searchParams.set('lang', lang)
  url.searchParams.set('limit', String(limit))

  const res = await fetch(url.toString(), {
    signal,
    headers: { Accept: 'application/json' },
  })
  if (!res.ok) {
    throw new Error(`Photon HTTP ${res.status}`)
  }
  const data = await res.json()
  const features = Array.isArray(data.features) ? data.features : []
  const out = []
  features.forEach((f, i) => {
    const row = photonFeatureToSuggestion(f, i)
    if (row) out.push(row)
  })
  return out
}
