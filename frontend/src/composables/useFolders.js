import { computed, ref } from 'vue'
import {
  createFolder,
  deleteFolder,
  fetchFolders,
  updateFolder,
} from '../services/chatApi.js'

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

/**
 * @param {Array<{ id: number|string, parent_id?: number|string|null }>} flat
 */
export function buildFolderTree(flat) {
  const byId = new Map()
  for (const f of flat) {
    byId.set(Number(f.id), { ...f, children: [] })
  }
  const roots = []
  for (const node of byId.values()) {
    const pid = node.parent_id != null ? Number(node.parent_id) : null
    if (pid != null && byId.has(pid)) {
      byId.get(pid).children.push(node)
    } else {
      roots.push(node)
    }
  }
  function sortChildren(n) {
    n.children.sort(
      (a, b) =>
        (Number(a.sort_order) || 0) - (Number(b.sort_order) || 0) ||
        Number(a.id) - Number(b.id),
    )
    n.children.forEach(sortChildren)
  }
  roots.sort(
    (a, b) =>
      (Number(a.sort_order) || 0) - (Number(b.sort_order) || 0) ||
      Number(a.id) - Number(b.id),
  )
  roots.forEach(sortChildren)
  return roots
}

/**
 * @param {Array<{ id: number|string, parent_id?: number|string|null }>} flat
 * @param {number|string} folderId
 * @returns {Array<{ id: number, name?: string }>}
 */
export function getFolderAncestors(flat, folderId) {
  const id = Number(folderId)
  const byId = new Map(flat.map((f) => [Number(f.id), f]))
  const path = []
  let cur = byId.get(id)
  const guard = new Set()
  while (cur && !guard.has(Number(cur.id))) {
    guard.add(Number(cur.id))
    path.unshift({ id: Number(cur.id), name: cur.name, icon_emoji: cur.icon_emoji, icon_bg_color: cur.icon_bg_color })
    const pid = cur.parent_id != null ? Number(cur.parent_id) : null
    cur = pid != null ? byId.get(pid) : null
  }
  return path
}

export function useFolders() {
  const folders = ref([])
  const loading = ref(false)
  const error = ref(null)

  const folderTree = computed(() => buildFolderTree(folders.value))

  async function load() {
    loading.value = true
    error.value = null
    const res = await fetchFolders()
    loading.value = false
    if (!res.ok) {
      error.value = res
      return res
    }
    folders.value = unwrapList(res.data)
    return res
  }

  function folderById(id) {
    return folders.value.find((f) => Number(f.id) === Number(id)) || null
  }

  function childrenOf(parentId) {
    const pid = parentId == null ? null : Number(parentId)
    return folders.value.filter((f) => {
      const p = f.parent_id != null ? Number(f.parent_id) : null
      return p === pid
    })
  }

  return {
    folders,
    folderTree,
    loading,
    error,
    load,
    folderById,
    childrenOf,
    createFolder,
    updateFolder,
    deleteFolder,
    buildFolderTree,
    getFolderAncestors,
  }
}
