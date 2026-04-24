import { apiJson, ensureCsrfCookie } from './api.js'
import { cachedGet, invalidateCache } from './cachedApi.js'

/**
 * @param {number|string} id
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchUser(id) {
  return cachedGet(`/api/users/${encodeURIComponent(String(id))}`)
}

/**
 * @param {number} [page]
 * @param {number} [perPage]
 */
export async function fetchUsersPage(page = 1, perPage = 20) {
  return cachedGet(`/api/users?page=${page}&per_page=${perPage}`)
}

export async function fetchInvitations() {
  return cachedGet('/api/invitations')
}

/**
 * @param {Record<string, unknown>} body
 */
export async function createUser(body) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/users', body)
  if (result.ok) {
    invalidateCache(/^\/api\/users/)
  }
  return result
}

/**
 * @param {number|string} id
 * @param {Record<string, unknown>} body
 */
export async function updateUser(id, body) {
  await ensureCsrfCookie()
  const slug = encodeURIComponent(String(id))
  const result = await apiJson('PATCH', `/api/users/${slug}`, body)
  if (result.ok) {
    invalidateCache(/^\/api\/users/)
    invalidateCache(`/api/users/${slug}`)
  }
  return result
}

/**
 * @param {unknown} data
 * @param {number} status
 * @param {string} fallback
 */
export function userApiErrorMessage(data, status, fallback) {
  if (data && typeof data === 'object' && data.message) {
    return String(data.message)
  }
  if (data && typeof data === 'object' && data.errors) {
    return Object.values(data.errors).flat().join(' ')
  }
  return String(fallback).replace('{status}', String(status))
}
