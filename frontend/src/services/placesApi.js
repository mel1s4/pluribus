import { apiBaseUrl, apiForm, apiJson, ensureCsrfCookie } from './api.js'
import { cachedGet, invalidateCache } from './cachedApi.js'

function invalidatePlacesCaches() {
  invalidateCache(/^\/api\/places/)
  invalidateCache('/api/community-map/places')
  invalidateCache(/^\/api\/community-place-offers/)
}

/**
 * @param {string} slug
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchPublicPlaceBySlug(slug) {
  const encoded = encodeURIComponent(slug)
  return cachedGet(`/api/places/${encoded}/public`, { skipCache: true })
}

/**
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchPlaces() {
  return cachedGet('/api/places')
}

/**
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchCommunityPlacesMap() {
  return cachedGet('/api/community-map/places')
}

/**
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchPlace(id) {
  return cachedGet(`/api/places/${id}`)
}

/**
 * @param {string} slug
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchPlaceBySlug(slug) {
  return fetchPlace(slug)
}

/**
 * @param {Record<string, unknown>|FormData} body
 */
export async function createPlace(body) {
  await ensureCsrfCookie()
  const result = body instanceof FormData
    ? await apiForm('POST', '/api/places', body)
    : await apiJson('POST', '/api/places', body)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} id
 * @param {Record<string, unknown>|FormData} body
 */
export async function updatePlace(id, body) {
  await ensureCsrfCookie()
  const result = body instanceof FormData
    ? await apiForm('PATCH', `/api/places/${id}`, body)
    : await apiJson('PATCH', `/api/places/${id}`, body)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} id
 */
export async function deletePlace(id) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/places/${id}`)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} placeId
 */
export async function fetchOffers(placeId) {
  return cachedGet(`/api/places/${placeId}/offers`)
}

/**
 * @param {number|string} placeId
 * @param {FormData|Record<string, unknown>} payload
 */
export async function createOffer(placeId, payload) {
  await ensureCsrfCookie()
  const result = payload instanceof FormData
    ? await apiForm('POST', `/api/places/${placeId}/offers`, payload)
    : await apiJson('POST', `/api/places/${placeId}/offers`, payload)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} placeId
 * @param {number|string} offerId
 * @param {FormData|Record<string, unknown>} payload
 */
export async function updateOffer(placeId, offerId, payload) {
  await ensureCsrfCookie()
  const result = payload instanceof FormData
    ? await apiForm('PATCH', `/api/places/${placeId}/offers/${offerId}`, payload)
    : await apiJson('PATCH', `/api/places/${placeId}/offers/${offerId}`, payload)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} placeId
 * @param {number|string} offerId
 */
export async function deleteOffer(placeId, offerId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/places/${placeId}/offers/${offerId}`)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} placeId
 */
export async function fetchRequirements(placeId) {
  return cachedGet(`/api/places/${placeId}/requirements`)
}

/**
 * @param {number|string} placeId
 * @param {FormData|Record<string, unknown>} payload
 */
export async function createRequirement(placeId, payload) {
  await ensureCsrfCookie()
  const result = payload instanceof FormData
    ? await apiForm('POST', `/api/places/${placeId}/requirements`, payload)
    : await apiJson('POST', `/api/places/${placeId}/requirements`, payload)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} placeId
 * @param {number|string} requirementId
 * @param {FormData|Record<string, unknown>} payload
 */
export async function updateRequirement(placeId, requirementId, payload) {
  await ensureCsrfCookie()
  const result = payload instanceof FormData
    ? await apiForm('PATCH', `/api/places/${placeId}/requirements/${requirementId}`, payload)
    : await apiJson('PATCH', `/api/places/${placeId}/requirements/${requirementId}`, payload)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} placeId
 * @param {number|string} requirementId
 */
export async function deleteRequirement(placeId, requirementId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/places/${placeId}/requirements/${requirementId}`)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {{ q?: string, page?: number }} params
 */
export async function fetchCommunityPlaceOffers(params = {}) {
  const q = new URLSearchParams()
  if (params.q) q.set('q', String(params.q))
  if (params.page) q.set('page', String(params.page))
  const qs = q.toString()
  const suffix = qs ? `?${qs}` : ''
  return cachedGet(`/api/community-place-offers${suffix}`)
}

/**
 * @param {number|string} placeId
 * @param {number|string} requirementId
 * @param {FormData|Record<string, unknown>} payload
 */
export async function createRequirementResponse(placeId, requirementId, payload) {
  await ensureCsrfCookie()
  const result = payload instanceof FormData
    ? await apiForm('POST', `/api/places/${placeId}/requirements/${requirementId}/responses`, payload)
    : await apiJson('POST', `/api/places/${placeId}/requirements/${requirementId}/responses`, payload)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} placeId
 * @param {number|string} requirementId
 * @param {number|string} responseId
 */
export async function deleteRequirementResponse(placeId, requirementId, responseId) {
  await ensureCsrfCookie()
  const result = await apiJson(
    'DELETE',
    `/api/places/${placeId}/requirements/${requirementId}/responses/${responseId}`,
  )
  if (result.ok) invalidatePlacesCaches()
  return result
}

export function downloadOffersCsvUrl(placeId) {
  return `${apiBaseUrl()}/api/places/${placeId}/offers/export.csv`
}

export function downloadRequirementsCsvUrl(placeId) {
  return `${apiBaseUrl()}/api/places/${placeId}/requirements/export.csv`
}

export async function uploadOffersCsv(placeId, file) {
  await ensureCsrfCookie()
  const formData = new FormData()
  formData.append('file', file)
  const result = await apiForm('POST', `/api/places/${placeId}/offers/import.csv`, formData)
  if (result.ok) invalidatePlacesCaches()
  return result
}

export async function uploadRequirementsCsv(placeId, file) {
  await ensureCsrfCookie()
  const formData = new FormData()
  formData.append('file', file)
  const result = await apiForm('POST', `/api/places/${placeId}/requirements/import.csv`, formData)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} placeId
 */
export async function fetchPlaceAudienceMembers(placeId) {
  return cachedGet(`/api/places/${placeId}/audience-members`)
}

/**
 * @param {number|string} placeId
 */
export async function fetchAudiences(placeId) {
  return cachedGet(`/api/places/${placeId}/audiences`)
}

/**
 * @param {number|string} placeId
 * @param {Record<string, unknown>} body
 */
export async function createAudience(placeId, body) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', `/api/places/${placeId}/audiences`, body)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} placeId
 * @param {number|string} audienceId
 * @param {Record<string, unknown>} body
 */
export async function updateAudience(placeId, audienceId, body) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/places/${placeId}/audiences/${audienceId}`, body)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} placeId
 * @param {number|string} audienceId
 */
export async function deleteAudience(placeId, audienceId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/places/${placeId}/audiences/${audienceId}`)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} placeId
 */
export async function fetchPlaceAdministrators(placeId) {
  return cachedGet(`/api/places/${placeId}/administrators`)
}

/**
 * @param {number|string} placeId
 * @param {Record<string, unknown>} body
 */
export async function addPlaceAdministrator(placeId, body) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', `/api/places/${placeId}/administrators`, body)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} placeId
 * @param {number|string} userId
 * @param {Record<string, unknown>} body
 */
export async function updatePlaceAdministrator(placeId, userId, body) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/places/${placeId}/administrators/${userId}`, body)
  if (result.ok) invalidatePlacesCaches()
  return result
}

/**
 * @param {number|string} placeId
 * @param {number|string} userId
 */
export async function removePlaceAdministrator(placeId, userId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/places/${placeId}/administrators/${userId}`)
  if (result.ok) invalidatePlacesCaches()
  return result
}
