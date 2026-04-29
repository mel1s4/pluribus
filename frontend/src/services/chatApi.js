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

export async function sendChatMessage(chatId, body) {
  await ensureCsrfCookie()
  return apiJson('POST', `/api/chats/${chatId}/messages`, { body })
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

export function fetchChatBackups(chatId) {
  return cachedGet(`/api/chats/${chatId}/backups`)
}

export async function createChatBackup(chatId) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', `/api/chats/${chatId}/backups`)
  if (result.ok) invalidateChatCaches()
  return result
}
