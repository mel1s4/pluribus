import { apiForm, apiJson, ensureCsrfCookie } from './api.js'
import { userApiErrorMessage } from './usersApi.js'

/**
 * @param {Record<string, unknown>} body
 */
export async function updateProfile(body) {
  await ensureCsrfCookie()
  return apiJson('PATCH', '/api/profile', body)
}

/**
 * @param {File} file
 */
export async function uploadProfileAvatar(file) {
  const fd = new FormData()
  fd.append('avatar', file)
  return apiForm('POST', '/api/profile/avatar', fd)
}

export async function deleteProfileAvatar() {
  await ensureCsrfCookie()
  return apiJson('DELETE', '/api/profile/avatar')
}

/**
 * @param {unknown} data
 * @param {number} status
 * @param {string} fallback
 */
export function profileApiErrorMessage(data, status, fallback) {
  return userApiErrorMessage(data, status, fallback)
}
