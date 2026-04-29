import { apiJson, ensureCsrfCookie } from './api.js'

const PREFIX = '/api/cart'

/**
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchCart() {
  return apiJson('GET', PREFIX)
}

/**
 * @param {number|string} placeOfferId
 * @param {number} quantity
 * @param {number|null} [tableId]
 */
export async function upsertCartItem(placeOfferId, quantity, tableId = null) {
  await ensureCsrfCookie()
  return apiJson('POST', `${PREFIX}/items`, {
    place_offer_id: Number(placeOfferId),
    quantity: Number(quantity),
    table_id: tableId == null ? null : Number(tableId),
  })
}

/**
 * @param {number|string} placeOfferId
 */
export async function removeCartItem(placeOfferId) {
  await ensureCsrfCookie()
  return apiJson('DELETE', `${PREFIX}/items/${placeOfferId}`)
}

export async function clearCart() {
  await ensureCsrfCookie()
  return apiJson('DELETE', PREFIX)
}
