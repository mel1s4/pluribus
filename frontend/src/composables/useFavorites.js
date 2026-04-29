import { computed, ref, watch } from 'vue'
import { sessionStatus } from './useSession'
import { t } from '../i18n/i18n'
import { isSidebarLinkDefAccessible, sidebarDefByKey } from '../navigation/sidebarLinks'
import {
  createFavorite as apiCreateFavorite,
  deleteFavorite as apiDeleteFavorite,
  fetchFavorites,
  reorderFavorites as apiReorderFavorites,
} from '../services/favoritesApi'

const STORAGE_KEY = 'pluribus.favorites'

/** @type {import('vue').Ref<Array<{ routeKey: string, order: number }>>} */
const favorites = ref([])
const loading = ref(false)
const hydrated = ref(false)
let loadPromise = null

function readCache() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    if (!parsed || !Array.isArray(parsed.favorites)) return []
    return parsed.favorites
      .filter((r) => r && typeof r.routeKey === 'string')
      .map((r) => ({ routeKey: r.routeKey, order: Number(r.order) || 0 }))
      .sort((a, b) => a.order - b.order || a.routeKey.localeCompare(b.routeKey))
  } catch {
    return []
  }
}

function writeCache(list) {
  try {
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        favorites: list.map((f) => ({ routeKey: f.routeKey, order: f.order })),
      }),
    )
  } catch {
    /* ignore */
  }
}

function normalizeFromApi(payload) {
  if (!payload || !Array.isArray(payload.favorites)) return []
  return payload.favorites
    .map((row) => ({
      routeKey: String(row.route_key || ''),
      order: Number(row.order) || 0,
    }))
    .filter((r) => r.routeKey.length > 0)
    .sort((a, b) => a.order - b.order || a.routeKey.localeCompare(b.routeKey))
}

async function refreshFromServer() {
  if (sessionStatus.value !== 'authenticated') return
  const { ok, data } = await fetchFavorites()
  if (ok && data && typeof data === 'object') {
    favorites.value = normalizeFromApi(data)
    writeCache(favorites.value)
  }
}

function resetFavorites() {
  favorites.value = []
  hydrated.value = false
  try {
    localStorage.removeItem(STORAGE_KEY)
  } catch {
    /* ignore */
  }
}

export async function loadFavorites() {
  if (sessionStatus.value !== 'authenticated') {
    return
  }
  if (loadPromise) {
    return loadPromise
  }
  loading.value = true
  loadPromise = (async () => {
    const cached = readCache()
    if (cached.length > 0) {
      favorites.value = cached
    }
    const { ok, data } = await fetchFavorites()
    if (ok && data && typeof data === 'object') {
      favorites.value = normalizeFromApi(data)
      writeCache(favorites.value)
      hydrated.value = true
    } else if (!hydrated.value && cached.length > 0) {
      favorites.value = cached
      hydrated.value = true
    } else if (!hydrated.value) {
      hydrated.value = true
    }
  })()
  try {
    await loadPromise
  } finally {
    loading.value = false
    loadPromise = null
  }
}

watch(
  sessionStatus,
  (s) => {
    if (s === 'authenticated') {
      void loadFavorites()
    }
    if (s === 'guest') {
      resetFavorites()
    }
  },
  { immediate: true },
)

function navItemFromKey(routeKey) {
  const def = sidebarDefByKey(routeKey)
  if (!def || !isSidebarLinkDefAccessible(def)) return null
  return {
    key: def.key,
    to: def.to,
    icon: def.icon,
    label: t(def.labelKey),
  }
}

/**
 * @param {Array<{ routeKey: string, order: number }>} ordered
 */
function navItemsFromOrdered(ordered) {
  const out = []
  for (const row of ordered) {
    const item = navItemFromKey(row.routeKey)
    if (item) out.push(item)
  }
  return out
}

export function useFavorites() {
  const favoriteKeysSet = computed(() => new Set(favorites.value.map((f) => f.routeKey)))

  const favoriteNavItems = computed(() => navItemsFromOrdered(favorites.value))

  const quickNavFavoriteItems = computed(() => navItemsFromOrdered(favorites.value).slice(0, 5))

  function isFavorite(routeKey) {
    return favoriteKeysSet.value.has(routeKey)
  }

  async function addFavorite(routeKey) {
    const def = sidebarDefByKey(routeKey)
    if (!def || !isSidebarLinkDefAccessible(def)) return { ok: false }
    if (isFavorite(routeKey)) return { ok: true }
    const maxOrder = favorites.value.reduce((m, f) => Math.max(m, f.order), -1)
    const next = [...favorites.value, { routeKey, order: maxOrder + 1 }]
    favorites.value = next
    writeCache(favorites.value)
    const { ok, status, data } = await apiCreateFavorite(routeKey)
    if (!ok) {
      favorites.value = favorites.value.filter((f) => f.routeKey !== routeKey)
      writeCache(favorites.value)
      return { ok: false, status, data }
    }
    await refreshFromServer()
    return { ok: true, status }
  }

  async function removeFavorite(routeKey) {
    const prev = [...favorites.value]
    favorites.value = favorites.value.filter((f) => f.routeKey !== routeKey)
    writeCache(favorites.value)
    const { ok, status } = await apiDeleteFavorite(routeKey)
    if (!ok && status !== 404) {
      favorites.value = prev
      writeCache(favorites.value)
      return { ok: false, status }
    }
    await refreshFromServer()
    return { ok: true, status }
  }

  async function toggleFavorite(routeKey) {
    if (isFavorite(routeKey)) {
      return removeFavorite(routeKey)
    }
    return addFavorite(routeKey)
  }

  /**
   * @param {string[]} orderedRouteKeys — full new order (all favorites)
   */
  async function saveOrder(orderedRouteKeys) {
    const payload = orderedRouteKeys.map((routeKey, index) => ({
      route_key: routeKey,
      order: index,
    }))
    const prev = [...favorites.value]
    favorites.value = orderedRouteKeys.map((routeKey, index) => ({
      routeKey,
      order: index,
    }))
    writeCache(favorites.value)
    const { ok, data, status } = await apiReorderFavorites(payload)
    if (!ok) {
      favorites.value = prev
      writeCache(favorites.value)
      return { ok: false, status, data }
    }
    if (data && typeof data === 'object') {
      favorites.value = normalizeFromApi(data)
      writeCache(favorites.value)
    }
    return { ok: true, status }
  }

  return {
    favorites,
    loading,
    hydrated,
    favoriteNavItems,
    quickNavFavoriteItems,
    isFavorite,
    addFavorite,
    removeFavorite,
    toggleFavorite,
    saveOrder,
    loadFavorites,
  }
}
