import { apiJson, ensureCsrfCookie } from './api'

/**
 * @param {string} method
 * @param {string} path
 * @param {Record<string, unknown>|undefined} body
 */
async function apiJsonMutating(method, path, body) {
  await ensureCsrfCookie()
  return apiJson(method, path, body)
}

export function fetchFavorites() {
  return apiJson('GET', '/api/user-favorites')
}

export function createFavorite(routeKey) {
  return apiJsonMutating('POST', '/api/user-favorites', { route_key: routeKey })
}

export function deleteFavorite(routeKey) {
  const encoded = encodeURIComponent(routeKey)
  return apiJsonMutating('DELETE', `/api/user-favorites/${encoded}`)
}

/**
 * @param {Array<{ route_key: string, order: number }>} favorites
 */
export function reorderFavorites(favorites) {
  return apiJsonMutating('PUT', '/api/user-favorites/reorder', { favorites })
}
