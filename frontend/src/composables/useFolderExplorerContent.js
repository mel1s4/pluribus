/**
 * Shared helpers for Folders explorer (root + folder detail): merge chats/tasks/notes into rows and filter.
 */

export function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

/**
 * @param {unknown} folderId Target folder numeric id, or `null` for unfiled-only (folder_id null/undefined).
 */
function itemMatchesFolder(folderId, itemFolderId) {
  if (folderId === null) {
    return itemFolderId == null || itemFolderId === undefined
  }
  return Number(itemFolderId) === Number(folderId)
}

/**
 * @param {Array<Record<string, unknown>>} chats
 * @param {Array<Record<string, unknown>>} tasks
 * @param {Array<Record<string, unknown>>} notes
 * @param {number|null} folderId
 * @returns {Array<{ key: string, kind: 'chat'|'task'|'note', item: Record<string, unknown>, sort: number }>}
 */
export function buildMergedRows(chats, tasks, notes, folderId) {
  const rows = []
  for (const c of chats) {
    if (!itemMatchesFolder(folderId, c.folder_id)) continue
    rows.push({
      key: `chat:${c.id}`,
      kind: 'chat',
      item: c,
      sort: new Date(c.updated_at || c.created_at || 0).getTime(),
    })
  }
  for (const tk of tasks) {
    if (!itemMatchesFolder(folderId, tk.folder_id)) continue
    rows.push({
      key: `task:${tk.id}`,
      kind: 'task',
      item: tk,
      sort: new Date(tk.updated_at || tk.created_at || 0).getTime(),
    })
  }
  for (const n of notes) {
    if (!itemMatchesFolder(folderId, n.folder_id)) continue
    rows.push({
      key: `note:${n.id}`,
      kind: 'note',
      item: n,
      sort: new Date(n.updated_at || n.created_at || 0).getTime(),
    })
  }
  rows.sort((a, b) => b.sort - a.sort)
  return rows
}

/**
 * @param {ReturnType<typeof buildMergedRows>} rows
 * @param {{ filterKind: 'all'|'chat'|'task'|'note', taskFilter: 'all'|'open'|'done', textFilter: string }} opts
 */
export function filterExplorerRows(rows, opts) {
  const { filterKind, taskFilter, textFilter } = opts
  let out = rows
  if (filterKind === 'chat') out = out.filter((r) => r.kind === 'chat')
  if (filterKind === 'task') out = out.filter((r) => r.kind === 'task')
  if (filterKind === 'note') out = out.filter((r) => r.kind === 'note')
  if (filterKind === 'task' || filterKind === 'all') {
    if (taskFilter === 'open') {
      out = out.filter((r) => r.kind !== 'task' || !r.item.completed_at)
    }
    if (taskFilter === 'done') {
      out = out.filter((r) => r.kind !== 'task' || r.item.completed_at)
    }
  }
  const q = textFilter.trim().toLowerCase()
  if (q) {
    out = out.filter((r) => {
      const title = String(r.item.title || '').toLowerCase()
      const desc = String(r.item.description || '').toLowerCase()
      const md = String(r.item.content_markdown || '').toLowerCase()
      return title.includes(q) || desc.includes(q) || md.includes(q)
    })
  }
  return out
}
