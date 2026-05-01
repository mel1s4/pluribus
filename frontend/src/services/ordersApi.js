import { apiJson, ensureCsrfCookie } from './api.js'

/**
 * @param {{ notes?: string }} [body]
 */
export async function createOrder(body = {}) {
  await ensureCsrfCookie()
  return apiJson('POST', '/api/orders', body)
}

/**
 * @param {number} [page]
 */
export async function fetchMyOrders(page = 1) {
  const q = page > 1 ? `?page=${page}` : ''
  return apiJson('GET', `/api/orders${q}`)
}

/**
 * @param {number|string} orderId
 */
export async function fetchOrder(orderId) {
  return apiJson('GET', `/api/orders/${orderId}`)
}

/**
 * @param {number|string} placeId
 * @param {number} [page]
 * @param {{
 *   per_page?: number,
 *   place_offer_ids?: number[],
 *   tags?: string[],
 *   statuses?: string[],
 * }} [filters]
 */
export async function fetchPlaceOrders(placeId, page = 1, filters = {}) {
  const params = new URLSearchParams()
  if (page > 1) {
    params.set('page', String(page))
  }
  if (filters.per_page != null && filters.per_page > 0) {
    params.set('per_page', String(filters.per_page))
  }
  if (Array.isArray(filters.place_offer_ids) && filters.place_offer_ids.length) {
    for (const id of filters.place_offer_ids) {
      params.append('place_offer_ids[]', String(id))
    }
  }
  if (Array.isArray(filters.tags) && filters.tags.length) {
    for (const tag of filters.tags) {
      if (typeof tag === 'string' && tag.trim()) {
        params.append('tags[]', tag.trim())
      }
    }
  }
  if (Array.isArray(filters.statuses) && filters.statuses.length) {
    for (const st of filters.statuses) {
      if (typeof st === 'string' && st) {
        params.append('statuses[]', st)
      }
    }
  }
  const q = params.toString() ? `?${params.toString()}` : ''
  return apiJson('GET', `/api/places/${placeId}/orders${q}`)
}

/**
 * @param {number|string} placeId
 * @param {number|string} orderId
 */
export async function fetchPlaceOrder(placeId, orderId) {
  return apiJson('GET', `/api/places/${placeId}/orders/${orderId}`)
}

/**
 * @param {number|string} placeId
 * @param {number|string} orderId
 * @param {string} status
 */
export async function patchPlaceOrderStatus(placeId, orderId, status) {
  await ensureCsrfCookie()
  return apiJson('PATCH', `/api/places/${placeId}/orders/${orderId}`, { status })
}

/**
 * @param {number|string|null} tableId
 */
export async function patchPlaceOrderItemTable(placeId, orderId, itemId, tableId) {
  await ensureCsrfCookie()
  return apiJson('PATCH', `/api/places/${placeId}/orders/${orderId}/items/${itemId}/table`, {
    table_id: tableId == null ? null : Number(tableId),
  })
}
