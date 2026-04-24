import { apiJson, ensureCsrfCookie } from './api.js'
import { cachedGet, invalidateCache } from './cachedApi.js'

function invalidateChatCaches() {
  invalidateCache(/^\/api\/chats/)
  invalidateCache(/^\/api\/chat-folders/)
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

export function fetchChatFolders() {
  return cachedGet('/api/chat-folders')
}

export async function createChatFolder(payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/chat-folders', payload)
  if (result.ok) invalidateChatCaches()
  return result
}

export async function updateChatFolder(folderId, payload) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/chat-folders/${folderId}`, payload)
  if (result.ok) invalidateChatCaches()
  return result
}

export async function deleteChatFolder(folderId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/chat-folders/${folderId}`)
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
