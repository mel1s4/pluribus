import { ref, watch } from 'vue'
import { searchFoldersAndItems } from '../services/chatApi.js'

const STORAGE_KEY = 'pluribus.folderSearch.recent'
const MAX_RECENT = 8

function loadRecent() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return []
    const arr = JSON.parse(raw)
    return Array.isArray(arr) ? arr.filter((x) => typeof x === 'string').slice(0, MAX_RECENT) : []
  } catch {
    return []
  }
}

function saveRecent(list) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list.slice(0, MAX_RECENT)))
  } catch {
    /* ignore */
  }
}

/**
 * @param {{ debounceMs?: number }} [opts]
 */
export function useFolderSearch(opts = {}) {
  const debounceMs = opts.debounceMs ?? 320
  const query = ref('')
  const filterType = ref(/** @type {'all'|'folder'|'chat'|'task'} */ ('all'))
  const loading = ref(false)
  const folders = ref([])
  const chats = ref([])
  const tasks = ref([])
  const recentQueries = ref(loadRecent())
  let timer = null

  async function runSearch(q) {
    const trimmed = q.trim()
    if (!trimmed) {
      folders.value = []
      chats.value = []
      tasks.value = []
      loading.value = false
      return
    }
    loading.value = true
    const res = await searchFoldersAndItems({
      q: trimmed,
      type: filterType.value === 'all' ? undefined : filterType.value,
    })
    loading.value = false
    if (!res.ok) return
    const d = res.data && typeof res.data === 'object' ? res.data : {}
    const unwrap = (x) => {
      if (!x) return []
      if (Array.isArray(x)) return x
      if (Array.isArray(x.data)) return x.data
      return []
    }
    folders.value = unwrap(d.folders)
    chats.value = unwrap(d.chats)
    tasks.value = unwrap(d.tasks)
  }

  function pushRecent(q) {
    const t = q.trim()
    if (!t) return
    const next = [t, ...recentQueries.value.filter((x) => x !== t)].slice(0, MAX_RECENT)
    recentQueries.value = next
    saveRecent(next)
  }

  watch(
    () => [query.value, filterType.value],
    () => {
      if (timer) clearTimeout(timer)
      timer = setTimeout(() => {
        runSearch(query.value)
      }, debounceMs)
    },
  )

  return {
    query,
    filterType,
    loading,
    folders,
    chats,
    tasks,
    recentQueries,
    runSearch,
    pushRecent,
  }
}
