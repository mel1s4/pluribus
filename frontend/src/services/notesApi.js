import { apiJson, ensureCsrfCookie } from './api.js'
import { cachedGet, invalidateCache } from './cachedApi.js'

export function invalidateNoteCaches() {
  invalidateCache(/^\/api\/notes/)
}

/**
 * @param {number} [folderId]
 */
export function fetchNotes(folderId) {
  const q = folderId != null && Number.isFinite(Number(folderId)) ? `?folder_id=${encodeURIComponent(String(folderId))}` : ''
  return cachedGet(`/api/notes${q}`)
}

export function fetchNote(noteId) {
  return cachedGet(`/api/notes/${noteId}`)
}

export async function createNote(payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/notes', payload)
  if (result.ok) invalidateNoteCaches()
  return result
}

export async function updateNote(noteId, payload) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/notes/${noteId}`, payload)
  if (result.ok) invalidateNoteCaches()
  return result
}

export async function deleteNote(noteId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/notes/${noteId}`)
  if (result.ok) invalidateNoteCaches()
  return result
}

export async function acquireNoteLock(noteId, sessionId) {
  await ensureCsrfCookie()
  return apiJson('POST', `/api/notes/${noteId}/lock`, { session_id: sessionId })
}

export async function renewNoteLock(noteId, sessionId) {
  await ensureCsrfCookie()
  return apiJson('PUT', `/api/notes/${noteId}/lock`, { session_id: sessionId })
}

export async function releaseNoteLock(noteId, sessionId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/notes/${noteId}/lock`, { session_id: sessionId })
  if (result.ok) invalidateNoteCaches()
  return result
}

export function fetchNoteRevisions(noteId, cursor = null) {
  const q = new URLSearchParams()
  if (cursor != null) q.set('cursor', String(cursor))
  const suffix = q.toString() ? `?${q.toString()}` : ''
  return cachedGet(`/api/notes/${noteId}/revisions${suffix}`, { skipCache: true })
}

export function fetchNoteRevision(noteId, revisionId) {
  return cachedGet(`/api/notes/${noteId}/revisions/${revisionId}`, { skipCache: true })
}

export async function revertNote(noteId, revisionId, editorSessionId) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', `/api/notes/${noteId}/revert`, {
    revision_id: revisionId,
    editor_session_id: editorSessionId,
  })
  if (result.ok) invalidateNoteCaches()
  return result
}

export function fetchNoteCollaborators(noteId) {
  return cachedGet(`/api/notes/${noteId}/collaborators`, { skipCache: true })
}

export async function addNoteCollaborator(noteId, payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', `/api/notes/${noteId}/collaborators`, payload)
  if (result.ok) invalidateNoteCaches()
  return result
}

export async function updateNoteCollaborator(noteId, userId, payload) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/notes/${noteId}/collaborators/${userId}`, payload)
  if (result.ok) invalidateNoteCaches()
  return result
}

export async function removeNoteCollaborator(noteId, userId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/notes/${noteId}/collaborators/${userId}`)
  if (result.ok) invalidateNoteCaches()
  return result
}

/** Stable per-tab session for note edit locks (survives refresh within tab). */
export function getNoteEditorTabSessionId() {
  if (typeof sessionStorage === 'undefined') {
    return typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : String(Date.now())
  }
  const key = 'pluribus.noteEditorTabSessionId'
  let id = sessionStorage.getItem(key)
  if (!id) {
    id = crypto.randomUUID()
    sessionStorage.setItem(key, id)
  }
  return id
}
