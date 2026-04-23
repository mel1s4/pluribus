import { apiForm, apiJson, ensureCsrfCookie } from './api.js'

/**
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchPlaces() {
  return apiJson('GET', '/api/places')
}

/**
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchPlace(id) {
  return apiJson('GET', `/api/places/${id}`)
}

/**
 * @param {Record<string, unknown>|FormData} body
 */
export async function createPlace(body) {
  await ensureCsrfCookie()
  if (body instanceof FormData) {
    return apiForm('POST', '/api/places', body)
  }
  return apiJson('POST', '/api/places', body)
}

/**
 * @param {number|string} id
 * @param {Record<string, unknown>|FormData} body
 */
export async function updatePlace(id, body) {
  await ensureCsrfCookie()
  if (body instanceof FormData) {
    return apiForm('PATCH', `/api/places/${id}`, body)
  }
  return apiJson('PATCH', `/api/places/${id}`, body)
}

/**
 * @param {number|string} id
 */
export async function deletePlace(id) {
  await ensureCsrfCookie()
  return apiJson('DELETE', `/api/places/${id}`)
}

/**
 * @param {number|string} placeId
 */
export async function fetchOffers(placeId) {
  return apiJson('GET', `/api/places/${placeId}/offers`)
}

/**
 * @param {number|string} placeId
 * @param {FormData|Record<string, unknown>} payload
 */
export async function createOffer(placeId, payload) {
  await ensureCsrfCookie()
  if (payload instanceof FormData) {
    return apiForm('POST', `/api/places/${placeId}/offers`, payload)
  }
  return apiJson('POST', `/api/places/${placeId}/offers`, payload)
}

/**
 * @param {number|string} placeId
 * @param {number|string} offerId
 * @param {FormData|Record<string, unknown>} payload
 */
export async function updateOffer(placeId, offerId, payload) {
  await ensureCsrfCookie()
  if (payload instanceof FormData) {
    return apiForm('PATCH', `/api/places/${placeId}/offers/${offerId}`, payload)
  }
  return apiJson('PATCH', `/api/places/${placeId}/offers/${offerId}`, payload)
}

/**
 * @param {number|string} placeId
 * @param {number|string} offerId
 */
export async function deleteOffer(placeId, offerId) {
  await ensureCsrfCookie()
  return apiJson('DELETE', `/api/places/${placeId}/offers/${offerId}`)
}

/**
 * @param {number|string} placeId
 */
export async function fetchPlaceAudienceMembers(placeId) {
  return apiJson('GET', `/api/places/${placeId}/audience-members`)
}

/**
 * @param {number|string} placeId
 */
export async function fetchAudiences(placeId) {
  return apiJson('GET', `/api/places/${placeId}/audiences`)
}

/**
 * @param {number|string} placeId
 * @param {Record<string, unknown>} body
 */
export async function createAudience(placeId, body) {
  await ensureCsrfCookie()
  return apiJson('POST', `/api/places/${placeId}/audiences`, body)
}

/**
 * @param {number|string} placeId
 * @param {number|string} audienceId
 * @param {Record<string, unknown>} body
 */
export async function updateAudience(placeId, audienceId, body) {
  await ensureCsrfCookie()
  return apiJson('PATCH', `/api/places/${placeId}/audiences/${audienceId}`, body)
}

/**
 * @param {number|string} placeId
 * @param {number|string} audienceId
 */
export async function deleteAudience(placeId, audienceId) {
  await ensureCsrfCookie()
  return apiJson('DELETE', `/api/places/${placeId}/audiences/${audienceId}`)
}

/**
 * @param {number|string} placeId
 */
export async function fetchPlaceAdministrators(placeId) {
  return apiJson('GET', `/api/places/${placeId}/administrators`)
}

/**
 * @param {number|string} placeId
 * @param {Record<string, unknown>} body
 */
export async function addPlaceAdministrator(placeId, body) {
  await ensureCsrfCookie()
  return apiJson('POST', `/api/places/${placeId}/administrators`, body)
}

/**
 * @param {number|string} placeId
 * @param {number|string} userId
 * @param {Record<string, unknown>} body
 */
export async function updatePlaceAdministrator(placeId, userId, body) {
  await ensureCsrfCookie()
  return apiJson('PATCH', `/api/places/${placeId}/administrators/${userId}`, body)
}

/**
 * @param {number|string} placeId
 * @param {number|string} userId
 */
export async function removePlaceAdministrator(placeId, userId) {
  await ensureCsrfCookie()
  return apiJson('DELETE', `/api/places/${placeId}/administrators/${userId}`)
}
