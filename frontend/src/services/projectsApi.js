import { apiJson, ensureCsrfCookie } from './api.js'

/**
 * @param {string} slug
 * @param {{
 *   per_page?: number,
 *   status?: string,
 *   q?: string,
 *   has_budget?: boolean,
 *   has_job_positions?: boolean,
 *   has_deadline?: boolean,
 * }} [opts]
 */
export function fetchCommunityProjects(slug, opts = {}) {
  const s = typeof slug === 'string' ? slug.trim() : ''
  const q = new URLSearchParams()
  if (opts.per_page != null) q.set('per_page', String(opts.per_page))
  if (opts.status) q.set('status', opts.status)
  if (opts.q) q.set('q', opts.q)
  if (opts.has_budget != null) q.set('has_budget', opts.has_budget ? '1' : '0')
  if (opts.has_job_positions != null) q.set('has_job_positions', opts.has_job_positions ? '1' : '0')
  if (opts.has_deadline != null) q.set('has_deadline', opts.has_deadline ? '1' : '0')
  const qs = q.toString()
  const path = `/api/communities/${encodeURIComponent(s)}/projects${qs ? `?${qs}` : ''}`
  return apiJson('GET', path)
}

/**
 * @param {{ per_page?: number }} [opts]
 */
export function fetchMyProjects(opts = {}) {
  const q = new URLSearchParams()
  if (opts.per_page != null) q.set('per_page', String(opts.per_page))
  const qs = q.toString()
  const path = `/api/my-projects${qs ? `?${qs}` : ''}`
  return apiJson('GET', path)
}

/**
 * @param {string} slug
 * @param {number} projectId
 */
export function fetchCommunityProject(slug, projectId) {
  const s = typeof slug === 'string' ? slug.trim() : ''
  const path = `/api/communities/${encodeURIComponent(s)}/projects/${encodeURIComponent(String(projectId))}`
  return apiJson('GET', path)
}

/**
 * @param {string} slug
 * @param {Record<string, unknown>} payload
 */
export async function createCommunityProject(slug, payload) {
  await ensureCsrfCookie()
  const s = typeof slug === 'string' ? slug.trim() : ''
  return apiJson('POST', `/api/communities/${encodeURIComponent(s)}/projects`, payload)
}

/**
 * @param {string} slug
 * @param {number} projectId
 * @param {Record<string, unknown>} payload
 */
export async function updateCommunityProject(slug, projectId, payload) {
  await ensureCsrfCookie()
  const s = typeof slug === 'string' ? slug.trim() : ''
  return apiJson(
    'PATCH',
    `/api/communities/${encodeURIComponent(s)}/projects/${encodeURIComponent(String(projectId))}`,
    payload,
  )
}

/**
 * @param {string} slug
 * @param {number} projectId
 */
export async function deleteCommunityProject(slug, projectId) {
  await ensureCsrfCookie()
  const s = typeof slug === 'string' ? slug.trim() : ''
  return apiJson(
    'DELETE',
    `/api/communities/${encodeURIComponent(s)}/projects/${encodeURIComponent(String(projectId))}`,
  )
}
