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
 */
export async function fetchPlaceOrders(placeId, page = 1) {
  const q = page > 1 ? `?page=${page}` : ''
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
