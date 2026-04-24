import { apiJson } from './api.js'

/**
 * @param {number|string} userSlug username, profile_slug UUID, or legacy numeric id
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function fetchMemberProfile(userSlug) {
  return apiJson('GET', `/api/members/${encodeURIComponent(String(userSlug))}`)
}
