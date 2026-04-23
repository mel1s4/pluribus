import { apiJson } from './api.js'

/**
 * @param {number|string} userId
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchMemberProfile(userId) {
  return apiJson('GET', `/api/members/${userId}`)
}
