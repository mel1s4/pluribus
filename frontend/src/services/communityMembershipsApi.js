import { apiJson, ensureCsrfCookie } from './api.js'
import { cachedGet, invalidateCache } from './cachedApi.js'

/**
 * @param {number} [page]
 * @param {number} [perPage]
 * @param {Record<string, unknown>} [requestOptions]
 */
export async function fetchCommunityMembershipsPage(page = 1, perPage = 20, requestOptions = {}) {
  const path = `/api/community/memberships?page=${page}&per_page=${perPage}`
  return cachedGet(path, { ...requestOptions })
}

/**
 * @param {{ user_id: number, role: 'admin'|'member' }} body
 * @param {Record<string, unknown>} [requestOptions]
 */
export async function addCommunityMember(body, requestOptions = {}) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/community/memberships', body, requestOptions)
  if (result.ok) {
    invalidateCache(/^\/api\/community\/memberships/)
  }
  return result
}

/**
 * @param {number|string} userId
 * @param {'admin'|'member'} role
 * @param {Record<string, unknown>} [requestOptions]
 */
export async function updateCommunityMembershipRole(userId, role, requestOptions = {}) {
  await ensureCsrfCookie()
  const slug = encodeURIComponent(String(userId))
  const result = await apiJson(
    'PATCH',
    `/api/community/memberships/${slug}`,
    { role },
    requestOptions,
  )
  if (result.ok) {
    invalidateCache(/^\/api\/community\/memberships/)
  }
  return result
}

/**
 * @param {number|string} userId
 * @param {Record<string, unknown>} [requestOptions]
 */
export async function removeCommunityMember(userId, requestOptions = {}) {
  await ensureCsrfCookie()
  const slug = encodeURIComponent(String(userId))
  const result = await apiJson('DELETE', `/api/community/memberships/${slug}`, undefined, requestOptions)
  if (result.ok) {
    invalidateCache(/^\/api\/community\/memberships/)
  }
  return result
}
