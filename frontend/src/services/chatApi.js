import { apiJson, ensureCsrfCookie } from './api.js'
import { cachedGet, invalidateCache } from './cachedApi.js'

function invalidateChatCaches() {
  invalidateCache(/^\/api\/chats/)
  invalidateCache(/^\/api\/folders/)
}

function invalidateFolderRelatedCaches() {
  invalidateChatCaches()
  invalidateCache(/^\/api\/tasks/)
}

export function fetchChats() {
  return cachedGet('/api/chats')
}

export function fetchChat(chatId) {
  return cachedGet(`/api/chats/${chatId}`)
}

export async function createChat(payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/chats', payload)
  if (result.ok) invalidateChatCaches()
  return result
}

export async function updateChat(chatId, payload) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/chats/${chatId}`, payload)
  if (result.ok) invalidateChatCaches()
  return result
}

export async function deleteChat(chatId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/chats/${chatId}`)
  if (result.ok) invalidateChatCaches()
  return result
}

export function fetchChatMessages(chatId, cursor = null) {
  const suffix = cursor ? `?cursor=${encodeURIComponent(cursor)}` : ''
  return cachedGet(`/api/chats/${chatId}/messages${suffix}`, { skipCache: true })
}

export function fetchChatUpdates(sinceId = 0, limit = 100) {
  const q = new URLSearchParams()
  q.set('since_id', String(Math.max(0, Number(sinceId) || 0)))
  q.set('limit', String(Math.min(100, Math.max(1, Number(limit) || 100))))
  return cachedGet(`/api/chats/updates?${q.toString()}`, { skipCache: true })
}

export async function sendChatMessage(chatId, body) {
  await ensureCsrfCookie()
  return apiJson('POST', `/api/chats/${chatId}/messages`, { body })
}

export async function markChatRead(chatId) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', `/api/chats/${chatId}/read`)
  if (result.ok) invalidateChatCaches()
  return result
}

export async function addChatMembers(chatId, userIds) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', `/api/chats/${chatId}/members`, { user_ids: userIds })
  if (result.ok) invalidateChatCaches()
  return result
}

export async function removeChatMember(chatId, userId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/chats/${chatId}/members/${userId}`)
  if (result.ok) invalidateChatCaches()
  return result
}

export function fetchFolders() {
  return cachedGet('/api/folders')
}

export async function createFolder(payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/folders', payload)
  if (result.ok) invalidateChatCaches()
  return result
}

export async function updateFolder(folderId, payload) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/folders/${folderId}`, payload)
  if (result.ok) invalidateChatCaches()
  return result
}

export async function deleteFolder(folderId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/folders/${folderId}`)
  if (result.ok) invalidateFolderRelatedCaches()
  return result
}

/** @param {{ q: string, type?: 'all'|'folder'|'chat'|'task' }} params */
export function searchFoldersAndItems(params) {
  const q = new URLSearchParams()
  q.set('q', String(params.q))
  if (params.type) q.set('type', String(params.type))
  return cachedGet(`/api/folders/search?${q.toString()}`, { skipCache: true })
}

export function fetchFolderStats(folderId) {
  return cachedGet(`/api/folders/${folderId}/stats`, { skipCache: true })
}

export async function bulkMoveFolderItems(payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/folders/bulk-move', payload)
  if (result.ok) invalidateFolderRelatedCaches()
  return result
}

export async function reorderFolders(payload) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', '/api/folders/reorder', payload)
  if (result.ok) invalidateChatCaches()
  return result
}

export async function shareFolderWithGroup(folderId, groupId) {
  return updateFolder(folderId, { shared_group_id: groupId })
}

export async function unshareFolderWithGroup(folderId) {
  return updateFolder(folderId, { shared_group_id: null })
}

export function fetchChatBackups(chatId) {
  return cachedGet(`/api/chats/${chatId}/backups`)
}

export async function createChatBackup(chatId) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', `/api/chats/${chatId}/backups`)
  if (result.ok) invalidateChatCaches()
  return result
}
