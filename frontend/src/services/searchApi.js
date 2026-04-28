import { cachedGet } from './cachedApi.js'

/**
 * @param {string} query
 * @param {number} [limit]
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchGlobalSearch(query, limit = 6) {
  const params = new URLSearchParams()
  params.set('q', String(query || '').trim())
  params.set('limit', String(limit))
  return cachedGet(`/api/global-search?${params.toString()}`)
}
