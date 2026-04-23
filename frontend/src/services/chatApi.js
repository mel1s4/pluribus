import { apiJson, ensureCsrfCookie } from './api.js'

export function fetchChats() {
  return apiJson('GET', '/api/chats')
}

export function fetchChat(chatId) {
  return apiJson('GET', `/api/chats/${chatId}`)
}

export async function createChat(payload) {
  await ensureCsrfCookie()
  return apiJson('POST', '/api/chats', payload)
}

export async function updateChat(chatId, payload) {
  await ensureCsrfCookie()
  return apiJson('PATCH', `/api/chats/${chatId}`, payload)
}

export async function deleteChat(chatId) {
  await ensureCsrfCookie()
  return apiJson('DELETE', `/api/chats/${chatId}`)
}

export function fetchChatMessages(chatId, cursor = null) {
  const suffix = cursor ? `?cursor=${encodeURIComponent(cursor)}` : ''
  return apiJson('GET', `/api/chats/${chatId}/messages${suffix}`)
}

export async function sendChatMessage(chatId, body) {
  await ensureCsrfCookie()
  return apiJson('POST', `/api/chats/${chatId}/messages`, { body })
}

export function fetchChatFolders() {
  return apiJson('GET', '/api/chat-folders')
}

export async function createChatFolder(payload) {
  await ensureCsrfCookie()
  return apiJson('POST', '/api/chat-folders', payload)
}

export async function updateChatFolder(folderId, payload) {
  await ensureCsrfCookie()
  return apiJson('PATCH', `/api/chat-folders/${folderId}`, payload)
}

export async function deleteChatFolder(folderId) {
  await ensureCsrfCookie()
  return apiJson('DELETE', `/api/chat-folders/${folderId}`)
}

export function fetchChatBackups(chatId) {
  return apiJson('GET', `/api/chats/${chatId}/backups`)
}

export async function createChatBackup(chatId) {
  await ensureCsrfCookie()
  return apiJson('POST', `/api/chats/${chatId}/backups`)
}
