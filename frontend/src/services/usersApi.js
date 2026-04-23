import { apiJson, ensureCsrfCookie } from './api.js'

/**
 * @param {number|string} id
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchUser(id) {
  return apiJson('GET', `/api/users/${id}`)
}

/**
 * @param {Record<string, unknown>} body
 */
export async function createUser(body) {
  await ensureCsrfCookie()
  return apiJson('POST', '/api/users', body)
}

/**
 * @param {number|string} id
 * @param {Record<string, unknown>} body
 */
export async function updateUser(id, body) {
  await ensureCsrfCookie()
  return apiJson('PATCH', `/api/users/${id}`, body)
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
