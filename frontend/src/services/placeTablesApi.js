import { apiJson, ensureCsrfCookie } from './api.js'

export async function fetchPlaceTables(placeId) {
  return apiJson('GET', `/api/places/${placeId}/tables`)
}

export async function createPlaceTable(placeId, name) {
  await ensureCsrfCookie()
  return apiJson('POST', `/api/places/${placeId}/tables`, { name })
}

export async function updatePlaceTable(placeId, tableId, name) {
  await ensureCsrfCookie()
  return apiJson('PATCH', `/api/places/${placeId}/tables/${tableId}`, { name })
}

export async function deletePlaceTable(placeId, tableId) {
  await ensureCsrfCookie()
  return apiJson('DELETE', `/api/places/${placeId}/tables/${tableId}`)
}

export async function createTableAccessLink(placeId, tableId) {
  await ensureCsrfCookie()
  return apiJson('POST', `/api/places/${placeId}/tables/${tableId}/access-links`)
}

export async function rotateTableAccessLink(placeId, tableId) {
  await ensureCsrfCookie()
  return apiJson('POST', `/api/places/${placeId}/tables/${tableId}/access-links/rotate`)
}

export async function resolveTableAccessToken(token) {
  return apiJson('GET', `/api/table-access/${token}`)
}

export async function consumeTableAccessToken(token) {
  await ensureCsrfCookie()
  return apiJson('POST', `/api/table-access/${token}/consume`)
}
