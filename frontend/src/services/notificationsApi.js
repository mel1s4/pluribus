import { apiJson, ensureCsrfCookie } from './api.js'

/**
 * @param {{ page?: number, perPage?: number, unread?: boolean }} [opts]
 */
export async function fetchNotificationsIndex(opts = {}) {
  const page = Math.max(1, Number(opts.page) || 1)
  const perPage = Math.min(100, Math.max(1, Number(opts.perPage) || 20))
  const q = new URLSearchParams()
  q.set('page', String(page))
  q.set('per_page', String(perPage))
  if (opts.unread) {
    q.set('unread', '1')
  }
  return apiJson('GET', `/api/notifications?${q.toString()}`)
}

/**
 * @param {string[]} ids
 */
export async function markNotificationsRead(ids) {
  await ensureCsrfCookie()
  return apiJson('POST', '/api/notifications/read', { ids })
}

export async function markAllNotificationsRead() {
  await ensureCsrfCookie()
  return apiJson('POST', '/api/notifications/read-all', {})
}
