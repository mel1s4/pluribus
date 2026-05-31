import { apiBaseUrl, apiJson, ensureCsrfCookie, xsrfHeaders } from './api.js'
import { cachedGet, invalidateCache } from './cachedApi.js'

/**
 * @param {{ headers?: Record<string, string>, ttl?: number, skipCache?: boolean }} [requestOptions]
 */
export function fetchCommunity(requestOptions) {
  return cachedGet('/api/community', requestOptions || {})
}

/**
 * @param {string} slug
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchCommunityMicrosite(slug) {
  const path = `/api/communities/${encodeURIComponent(slug)}/microsite`
  return apiJson('GET', path)
}

/**
 * Public legal markdown for a community (no auth).
 * @param {string} slug
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export function fetchCommunityLegalDocumentsPublic(slug) {
  const path = `/api/communities/${encodeURIComponent(slug)}/legal-documents`
  return apiJson('GET', path)
}

/**
 * @param {string} slug
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchCommunityCredits(slug) {
  const path = `/api/communities/${encodeURIComponent(slug)}/credits`
  return apiJson('GET', path)
}

/**
 * Download signed ledger JSON for a community (member-only).
 * @param {string} slug
 * @returns {Promise<{ ok: boolean, status: number, error?: string }>}
 */
export async function downloadCommunityLedgerExport(slug) {
  await ensureCsrfCookie()
  const path = `/api/communities/${encodeURIComponent(slug)}/credits/ledger-export`
  const url = `${apiBaseUrl()}${path}`
  const res = await fetch(url, {
    method: 'GET',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...xsrfHeaders(),
    },
  })
  if (res.status === 401) {
    const { applyUnauthorizedFromApi } = await import('../composables/useSession.js')
    await applyUnauthorizedFromApi(path, 'GET')
  }
  if (!res.ok) {
    return { ok: false, status: res.status }
  }
  const blob = await res.blob()
  const cd = res.headers.get('Content-Disposition') || ''
  const match = cd.match(/filename\*?=(?:UTF-8'')?["']?([^"';]+)["']?/i)
  const filename =
    (match && match[1] ? decodeURIComponent(match[1].trim()) : null)
    || `community-${slug}-ledger-export.json`
  const objectUrl = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = objectUrl
  a.download = filename
  a.rel = 'noopener'
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(objectUrl)
  return { ok: true, status: res.status }
}

/**
 * @param {{ currency_code?: string | null, currency_name?: string | null, local_currency_code?: string | null }} body
 * @param {{ headers?: Record<string, string> }} [requestOptions]
 */
export async function patchCommunityCurrency(body, requestOptions) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', '/api/community/currency', body, requestOptions)
  if (result.ok) {
    invalidateCache(/^\/api\/community/)
  }
  return result
}

/**
 * @param {{ terms_markdown?: string | null, privacy_policy_markdown?: string | null }} body
 * @param {{ headers?: Record<string, string> }} [requestOptions]
 */
export async function patchCommunityLegalDocuments(body, requestOptions) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', '/api/community/legal-documents', body, requestOptions)
  if (result.ok) {
    invalidateCache(/^\/api\/community/)
  }
  return result
}

/**
 * @param {{ headers?: Record<string, string>, ttl?: number, skipCache?: boolean }} [requestOptions]
 */
export function fetchCommunityLeadership(requestOptions) {
  return cachedGet('/api/community/leadership', requestOptions || {})
}

export function fetchCommunities() {
  return cachedGet('/api/communities')
}

/**
 * @param {number|string} communityId
 * @param {{ headers?: Record<string, string>, ttl?: number, skipCache?: boolean }} [requestOptions]
 */
export function fetchCommunityById(communityId, requestOptions) {
  const id = encodeURIComponent(String(communityId))
  return cachedGet(`/api/communities/${id}`, requestOptions || {})
}

export function fetchMyCommunities() {
  return cachedGet('/api/my-communities')
}

/**
 * @param {{ name: string, slug?: string, description?: string | null, rules?: string | null, logo?: string | null, default_language?: string, currency_code?: string | null }} body
 */
export async function createCommunity(body) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/communities', body)
  if (result.ok) {
    invalidateCache(/^\/api\/communities/)
  }
  return result
}

/**
 * @param {number|string} communityId
 * @param {{ name: string, slug?: string, description?: string | null, rules?: string | null, logo?: string | null, default_language?: string, currency_code?: string | null }} body
 */
export async function patchCommunity(communityId, body) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/communities/${communityId}`, body)
  if (result.ok) {
    invalidateCache(/^\/api\/communities/)
    invalidateCache(new RegExp(`^/api/communities/${encodeURIComponent(String(communityId))}(\\?|$)`))
    invalidateCache(/^\/api\/community/)
  }
  return result
}

/**
 * @param {string} token
 */
export async function joinCommunityByInvitation(token) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', `/api/my-communities/join/${encodeURIComponent(token)}`)
  if (result.ok) {
    invalidateCache(/^\/api\/my-communities/)
    invalidateCache(/^\/api\/user/)
  }
  return result
}
