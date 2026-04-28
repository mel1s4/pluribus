import { apiJson, ensureCsrfCookie } from './api.js'
import { cachedGet, invalidateCache } from './cachedApi.js'

export function fetchCommunity() {
  return cachedGet('/api/community')
}

/**
 * @param {{ currency_code: string | null }} body
 */
export async function patchCommunityCurrency(body) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', '/api/community/currency', body)
  if (result.ok) {
    invalidateCache(/^\/api\/community/)
  }
  return result
}

export function fetchCommunityLeadership() {
  return cachedGet('/api/community/leadership')
}
