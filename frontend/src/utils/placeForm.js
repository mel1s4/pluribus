/**
 * Shared helpers for place create/edit flows.
 */

export function emptyPlaceDraft() {
  return {
    id: null,
    name: '',
    description: '',
    tags: [],
    latitude: null,
    longitude: null,
    service_area_type: 'none',
    radius_meters: null,
    area_geojson: null,
    logo_url: null,
    logoFile: null,
    removeLogo: false,
  }
}

/**
 * Build multipart body for PATCH places when a logo file or explicit removal is included.
 * @param {object} draft from emptyPlaceDraft / draftFromPlace
 * @returns {FormData}
 */
export function placeToFormData(draft) {
  const fd = new FormData()
  fd.append('name', draft.name.trim())
  if (draft.description != null && draft.description !== '') {
    fd.append('description', String(draft.description))
  }
  fd.append('tags', JSON.stringify(Array.isArray(draft.tags) ? draft.tags : []))
  if (draft.latitude != null && draft.longitude != null) {
    fd.append('latitude', String(draft.latitude))
    fd.append('longitude', String(draft.longitude))
  }
  fd.append('service_area_type', draft.service_area_type)
  if (draft.radius_meters != null && draft.radius_meters !== '') {
    fd.append('radius_meters', String(draft.radius_meters))
  }
  if (draft.area_geojson != null) {
    fd.append('area_geojson', JSON.stringify(draft.area_geojson))
  } else {
    fd.append('area_geojson', '')
  }
  if (draft.logoFile instanceof File) {
    fd.append('logo', draft.logoFile)
  }
  if (draft.removeLogo) {
    fd.append('remove_logo', '1')
  }
  return fd
}

/**
 * Multipart for POST create with optional logo (name, description, tags only for place fields).
 * @param {object} draft
 */
export function placeCreateToFormData(draft) {
  const fd = new FormData()
  fd.append('name', draft.name.trim())
  if (draft.description != null && draft.description !== '') {
    fd.append('description', String(draft.description))
  }
  fd.append('tags', JSON.stringify(Array.isArray(draft.tags) ? draft.tags : []))
  if (draft.logoFile instanceof File) {
    fd.append('logo', draft.logoFile)
  }
  return fd
}

export function placeApiErrorMessage(data, status, fallbackTemplate) {
  if (data && typeof data === 'object') {
    if (typeof data.message === 'string' && data.message.length) {
      return data.message
    }
    if (data.errors && typeof data.errors === 'object') {
      return Object.values(data.errors)
        .flat()
        .map(String)
        .join(' ')
    }
  }
  return fallbackTemplate.replace('{status}', String(status))
}
