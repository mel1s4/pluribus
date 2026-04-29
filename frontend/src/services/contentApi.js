import { apiJson, ensureCsrfCookie } from './api.js'
import { cachedGet, invalidateCache } from './cachedApi.js'

function invalidateContentCaches() {
  invalidateCache(/^\/api\/groups/)
  invalidateCache(/^\/api\/calendars/)
  invalidateCache(/^\/api\/posts/)
  invalidateCache(/^\/api\/tasks/)
  invalidateCache(/^\/api\/discovery\//)
  invalidateCache(/^\/api\/events\//)
  invalidateCache(/^\/api\/folders/)
}

export function fetchGroups() {
  return cachedGet('/api/groups')
}

export function fetchGroup(groupId) {
  return cachedGet(`/api/groups/${groupId}`)
}

export async function createGroup(payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/groups', payload)
  if (result.ok) invalidateContentCaches()
  return result
}

export async function updateGroup(groupId, payload) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/groups/${groupId}`, payload)
  if (result.ok) invalidateContentCaches()
  return result
}

export async function deleteGroup(groupId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/groups/${groupId}`)
  if (result.ok) invalidateContentCaches()
  return result
}

export function fetchGroupMembers(groupId) {
  return cachedGet(`/api/groups/${groupId}/members`)
}

export async function addGroupMember(groupId, payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', `/api/groups/${groupId}/members`, payload)
  if (result.ok) invalidateContentCaches()
  return result
}

export async function updateGroupMember(groupId, userId, payload) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/groups/${groupId}/members/${userId}`, payload)
  if (result.ok) invalidateContentCaches()
  return result
}

export async function removeGroupMember(groupId, userId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/groups/${groupId}/members/${userId}`)
  if (result.ok) invalidateContentCaches()
  return result
}

export function fetchCalendars() {
  return cachedGet('/api/calendars')
}

export async function createCalendar(payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/calendars', payload)
  if (result.ok) invalidateContentCaches()
  return result
}

export async function updateCalendar(calendarId, payload) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/calendars/${calendarId}`, payload)
  if (result.ok) invalidateContentCaches()
  return result
}

export async function deleteCalendar(calendarId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/calendars/${calendarId}`)
  if (result.ok) invalidateContentCaches()
  return result
}

export function fetchPosts(params = {}) {
  const q = new URLSearchParams()
  if (params.type) q.set('type', String(params.type))
  if (params.calendar_id) q.set('calendar_id', String(params.calendar_id))
  const qs = q.toString()
  return cachedGet(`/api/posts${qs ? `?${qs}` : ''}`)
}

export async function createPost(payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/posts', payload)
  if (result.ok) invalidateContentCaches()
  return result
}

export async function updatePost(postId, payload) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/posts/${postId}`, payload)
  if (result.ok) invalidateContentCaches()
  return result
}

export async function deletePost(postId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/posts/${postId}`)
  if (result.ok) invalidateContentCaches()
  return result
}

export function fetchTasks(params = {}) {
  const q = new URLSearchParams()
  if (params.folder_id) q.set('folder_id', String(params.folder_id))
  if (params.calendar_id) q.set('calendar_id', String(params.calendar_id))
  if (params.only_open) q.set('only_open', '1')
  const qs = q.toString()
  return cachedGet(`/api/tasks${qs ? `?${qs}` : ''}`)
}

export async function createTask(payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/tasks', payload)
  if (result.ok) invalidateContentCaches()
  return result
}

export async function updateTask(taskId, payload) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/tasks/${taskId}`, payload)
  if (result.ok) invalidateContentCaches()
  return result
}

export async function deleteTask(taskId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/tasks/${taskId}`)
  if (result.ok) invalidateContentCaches()
  return result
}

export function fetchCalendarDiscovery(params = {}) {
  const q = new URLSearchParams()
  if (params.start) q.set('start', String(params.start))
  if (params.end) q.set('end', String(params.end))
  const ids = Array.isArray(params.calendar_ids) ? params.calendar_ids : []
  for (const id of ids) {
    if (id != null && id !== '') q.append('calendar_ids[]', String(id))
  }
  const qs = q.toString()
  const path = `/api/discovery/calendar${qs ? `?${qs}` : ''}`
  return cachedGet(path, { skipCache: Boolean(params.skipCache) })
}

/** @param {number|string} calendarId */
export function fetchCalendarEventsForCalendar(calendarId, params = {}) {
  const q = new URLSearchParams()
  if (params.start) q.set('start', String(params.start))
  if (params.end) q.set('end', String(params.end))
  const qs = q.toString()
  return cachedGet(`/api/calendars/${calendarId}/events${qs ? `?${qs}` : ''}`, {
    skipCache: Boolean(params.skipCache),
  })
}

/**
 * @param {'post'|'task'} entityType
 * @param {number|string} id
 * @param {{ start_at: string, end_at?: string|null, all_day?: boolean }} payload
 */
export async function rescheduleCalendarEvent(entityType, id, payload) {
  await ensureCsrfCookie()
  const type = entityType === 'task' ? 'task' : 'post'
  const result = await apiJson('PATCH', `/api/events/${type}/${id}/reschedule`, payload)
  if (result.ok) invalidateContentCaches()
  return result
}

export function fetchMapDiscovery(params = {}) {
  const q = new URLSearchParams()
  if (params.entity) q.set('entity', String(params.entity))
  if (params.post_type) q.set('post_type', String(params.post_type))
  for (const tag of (params.tags || [])) q.append('tags[]', String(tag))
  const qs = q.toString()
  return cachedGet(`/api/discovery/map${qs ? `?${qs}` : ''}`)
}

