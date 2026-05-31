import { apiJson, ensureCsrfCookie } from './api.js'

/**
 * @param {string} slug
 * @param {{ per_page?: number }} [opts]
 */
export function fetchCommunityProjects(slug, opts = {}) {
  const s = typeof slug === 'string' ? slug.trim() : ''
  const q = new URLSearchParams()
  if (opts.per_page != null) q.set('per_page', String(opts.per_page))
  const qs = q.toString()
  const path = `/api/communities/${encodeURIComponent(s)}/projects${qs ? `?${qs}` : ''}`
  return apiJson('GET', path)
}

/**
 * @param {string} slug
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

/**
 * @param {string} slug
 * @param {number} projectId
 * @param {Record<string, unknown>} payload
 */
export async function createProjectArgument(slug, projectId, payload) {
  await ensureCsrfCookie()
  const s = typeof slug === 'string' ? slug.trim() : ''
  return apiJson(
    'POST',
    `/api/communities/${encodeURIComponent(s)}/projects/${encodeURIComponent(String(projectId))}/arguments`,
    payload,
  )
}

/**
 * @param {string} slug
 * @param {number} projectId
 * @param {number} argumentId
 * @param {Record<string, unknown>} payload
 */
export async function updateProjectArgument(slug, projectId, argumentId, payload) {
  await ensureCsrfCookie()
  const s = typeof slug === 'string' ? slug.trim() : ''
  return apiJson(
    'PATCH',
    `/api/communities/${encodeURIComponent(s)}/projects/${encodeURIComponent(String(projectId))}/arguments/${encodeURIComponent(String(argumentId))}`,
    payload,
  )
}

/**
 * @param {string} slug
 * @param {number} projectId
 * @param {number} argumentId
 */
export async function deleteProjectArgument(slug, projectId, argumentId) {
  await ensureCsrfCookie()
  const s = typeof slug === 'string' ? slug.trim() : ''
  return apiJson(
    'DELETE',
    `/api/communities/${encodeURIComponent(s)}/projects/${encodeURIComponent(String(projectId))}/arguments/${encodeURIComponent(String(argumentId))}`,
  )
}
