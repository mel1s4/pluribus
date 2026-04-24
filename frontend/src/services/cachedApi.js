import { apiJson } from './api.js'

const DEFAULT_TTL_MS = 5 * 60 * 1000

const cache = new Map()
const pending = new Map()

/**
 * Deep snapshot so callers cannot mutate cached payloads.
 * @param {{ ok: boolean, status: number, data: unknown }} res
 */
function snapshotResponse(res) {
  try {
    return {
      ok: res.ok,
      status: res.status,
      data: structuredClone(res.data),
    }
  } catch {
    try {
      return {
        ok: res.ok,
        status: res.status,
        data: JSON.parse(JSON.stringify(res.data)),
      }
    } catch {
      return { ok: res.ok, status: res.status, data: res.data }
    }
  }
}

/**
 * Cached GET with TTL and in-flight deduplication.
 * @param {string} path
 * @param {{ ttl?: number, skipCache?: boolean }} [options]
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function cachedGet(path, options = {}) {
  const { ttl = DEFAULT_TTL_MS, skipCache = false } = options

  if (skipCache) {
    const fresh = await apiJson('GET', path)
    return snapshotResponse(fresh)
  }

  const cacheKey = path.startsWith('/') ? path : `/${path}`
  const now = Date.now()

  const hit = cache.get(cacheKey)
  if (hit && now < hit.expiresAt) {
    return snapshotResponse(hit.response)
  }

  if (pending.has(cacheKey)) {
    const shared = await pending.get(cacheKey)
    return snapshotResponse(shared)
  }

  const promise = apiJson('GET', path).then((response) => {
    pending.delete(cacheKey)
    if (response.ok) {
      cache.set(cacheKey, {
        response: snapshotResponse(response),
        expiresAt: now + ttl,
      })
    }
    return response
  }).catch((err) => {
    pending.delete(cacheKey)
    throw err
  })

  pending.set(cacheKey, promise)
  const response = await promise
  return snapshotResponse(response)
}

/**
 * @param {RegExp|string|null|undefined} pattern
 * When null/undefined, clears entire cache.
 */
export function invalidateCache(pattern) {
  if (pattern == null) {
    cache.clear()
    return
  }
  if (pattern instanceof RegExp) {
    for (const key of cache.keys()) {
      if (pattern.test(key)) {
        cache.delete(key)
      }
    }
    return
  }
  if (typeof pattern === 'string') {
    const key = pattern.startsWith('/') ? pattern : `/${pattern}`
    cache.delete(key)
  }
}

export function clearAllCache() {
  cache.clear()
}
