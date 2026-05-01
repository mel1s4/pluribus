function defaultBase() {
  if (typeof window === 'undefined') {
    return 'http://localhost:9122'
  }
  return `http://${window.location.hostname}:9122`
}

/**
 * When the SPA is opened on 127.0.0.1 but VITE_API_BASE_URL points at localhost
 * (or the reverse), the browser stores Sanctum CSRF cookies on the API host while
 * document.cookie on the page cannot read them — login POSTs miss X-XSRF-TOKEN (419).
 * Align hostname only for this local dev pair.
 */
function alignLocalDevApiHost(base) {
  if (typeof window === 'undefined') {
    return base.replace(/\/$/, '')
  }
  let url
  try {
    url = new URL(base)
  } catch {
    return base.replace(/\/$/, '')
  }
  const pageHost = window.location.hostname
  const apiHost = url.hostname
  const isLocal = (h) => h === 'localhost' || h === '127.0.0.1'
  if (isLocal(apiHost) && isLocal(pageHost) && apiHost !== pageHost) {
    url.hostname = pageHost
    return url.origin
  }
  return base.replace(/\/$/, '')
}

export function apiBaseUrl() {
  const raw = import.meta?.env?.VITE_API_BASE_URL
  const configured =
    typeof raw === 'string' && raw.length > 0 ? raw : defaultBase()
  return alignLocalDevApiHost(configured)
}

function readCookie(name) {
  if (typeof document === 'undefined') {
    return ''
  }
  const parts = `; ${document.cookie}`.split(`; ${name}=`)
  if (parts.length === 2) {
    const rawValue = parts.pop().split(';').shift() || ''
    return decodeURIComponent(rawValue)
  }
  return ''
}

function xsrfHeaders() {
  const token = readCookie('XSRF-TOKEN')
  if (!token) {
    return {}
  }
  return { 'X-XSRF-TOKEN': token }
}

export class ApiTimeoutError extends Error {
  /**
   * @param {string} method
   * @param {string} path
   * @param {number} timeoutMs
   */
  constructor(method, path, timeoutMs) {
    super(`${method} ${path} timed out after ${timeoutMs}ms`)
    this.name = 'ApiTimeoutError'
    this.method = method
    this.path = path
    this.timeoutMs = timeoutMs
  }
}

const REQUEST_TIMEOUTS = {
  auth: 6000,
  standard: 10000,
  heavy: 20000,
}

const REQUEST_TIMEOUT_RULES = [
  {
    match: (method, path) =>
      (method === 'GET' && path === '/api/user')
      || (method === 'POST' && path === '/api/login')
      || (method === 'POST' && path === '/api/logout')
      || (method === 'POST' && path === '/api/password/forgot')
      || (method === 'POST' && path === '/api/password/reset')
      || (method === 'POST' && path === '/api/visitor-auth/request-link')
      || (method === 'POST' && path.startsWith('/api/visitor-auth/consume/')),
    timeoutMs: REQUEST_TIMEOUTS.auth,
  },
  {
    match: (_, path) =>
      path.startsWith('/api/chats/')
      && (path.endsWith('/messages') || path.includes('/backups')),
    timeoutMs: REQUEST_TIMEOUTS.heavy,
  },
]

function resolveTimeoutMs(method, path) {
  const normalizedMethod = String(method || 'GET').toUpperCase()
  for (const rule of REQUEST_TIMEOUT_RULES) {
    if (rule.match(normalizedMethod, path)) {
      return rule.timeoutMs
    }
  }
  return REQUEST_TIMEOUTS.standard
}

/**
 * @param {string} method
 * @param {string} path
 * @param {string} url
 * @param {RequestInit} init
 * @param {number|undefined} timeoutMsOverride
 * @returns {Promise<Response>}
 */
async function requestWithTimeout(method, path, url, init, timeoutMsOverride) {
  const timeoutMs = timeoutMsOverride ?? resolveTimeoutMs(method, path)
  const supportsAbortTimeout =
    typeof AbortSignal !== 'undefined'
    && typeof AbortSignal.timeout === 'function'
  const signal = supportsAbortTimeout ? AbortSignal.timeout(timeoutMs) : undefined
  try {
    return await fetch(url, {
      ...init,
      ...(signal ? { signal } : {}),
    })
  } catch (error) {
    if (error instanceof Error && error.name === 'TimeoutError') {
      throw new ApiTimeoutError(String(method).toUpperCase(), path, timeoutMs)
    }
    throw error
  }
}

/** Headers for authenticated GET `/api/chats/stream` (Sanctum cookie + CSRF). */
export function chatSseHeaders() {
  return {
    Accept: 'text/event-stream',
    ...xsrfHeaders(),
  }
}

function shouldSkipGlobalUnauthorizedHandler(method, path) {
  if (method === 'GET' && /^\/api\/places\/[^/]+\/public$/.test(path)) {
    return true
  }
  return (
    (method === 'GET' && path === '/api/user')
    || (method === 'POST' && path === '/api/login')
    || (method === 'POST' && path === '/api/logout')
  )
}

export async function ensureCsrfCookie() {
  // #region agent log
  fetch('http://127.0.0.1:7800/ingest/b3c811d3-7ec8-4727-aae6-1a8e45b40a1e', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': '808933' },
    body: JSON.stringify({
      sessionId: '808933',
      runId: 'login-hang-v2',
      hypothesisId: 'H7',
      location: 'api.js:ensureCsrfCookie:entry',
      message: 'ensureCsrfCookie start',
      data: {},
      timestamp: Date.now(),
    }),
  }).catch(() => {})
  // #endregion
  const now = Date.now()
  if (csrfReadyUntil > now) {
    return
  }
  if (pendingCsrfRequest) {
    await pendingCsrfRequest
    return
  }

  pendingCsrfRequest = (async () => {
  try {
      await requestWithTimeout('GET', '/sanctum/csrf-cookie', `${apiBaseUrl()}/sanctum/csrf-cookie`, {
        method: 'GET',
        credentials: 'include',
        headers: { Accept: 'application/json' },
      }, 3000)
      csrfReadyUntil = Date.now() + CSRF_CACHE_TTL_MS
  } catch (err) {
    // #region agent log
    fetch('http://127.0.0.1:7800/ingest/b3c811d3-7ec8-4727-aae6-1a8e45b40a1e', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': '808933' },
      body: JSON.stringify({
        sessionId: '808933',
        runId: 'login-hang-v2',
        hypothesisId: 'H11',
        location: 'api.js:ensureCsrfCookie:catch',
        message: 'ensureCsrfCookie failed or timed out',
        data: {
          errorName: err instanceof Error ? err.name : typeof err,
          errorMessage: err instanceof Error ? err.message : String(err),
        },
        timestamp: Date.now(),
      }),
    }).catch(() => {})
    // #endregion
      csrfReadyUntil = 0
    }
  })()
  try {
    await pendingCsrfRequest
  } finally {
    pendingCsrfRequest = null
  }
  // #region agent log
  fetch('http://127.0.0.1:7800/ingest/b3c811d3-7ec8-4727-aae6-1a8e45b40a1e', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': '808933' },
    body: JSON.stringify({
      sessionId: '808933',
      runId: 'login-hang-v2',
      hypothesisId: 'H7',
      location: 'api.js:ensureCsrfCookie:exit',
      message: 'ensureCsrfCookie done',
      data: {},
      timestamp: Date.now(),
    }),
  }).catch(() => {})
  // #endregion
}

const CSRF_CACHE_TTL_MS = 60_000
let csrfReadyUntil = 0
let pendingCsrfRequest = null

/**
 * @param {string} method
 * @param {string} path
 * @param {Record<string, unknown>|undefined} body
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
/**
 * @param {string} method
 * @param {string} path
 * @param {FormData} formData
 * @returns {Promise<{ ok: boolean, status: number, data: unknown }>}
 */
export async function apiForm(method, path, formData) {
  await ensureCsrfCookie()
  const url = `${apiBaseUrl()}${path.startsWith('/') ? path : `/${path}`}`
  const headers = {
    Accept: 'application/json',
    ...xsrfHeaders(),
  }
  const res = await requestWithTimeout(method, path, url, {
    method,
    credentials: 'include',
    headers,
    body: formData,
  })
  const text = await res.text()
  let data = null
  if (text) {
    try {
      data = JSON.parse(text)
    } catch {
      data = { message: text }
    }
  }
  if (res.status === 401 && !shouldSkipGlobalUnauthorizedHandler(method, path)) {
    const { applyUnauthorizedFromApi } = await import('../composables/useSession.js')
    await applyUnauthorizedFromApi(path, method)
  }
  return { ok: res.ok, status: res.status, data }
}

export async function apiJson(method, path, body) {
  const url = `${apiBaseUrl()}${path.startsWith('/') ? path : `/${path}`}`
  // #region agent log
  if (path === '/api/login') {
    fetch('http://127.0.0.1:7800/ingest/b3c811d3-7ec8-4727-aae6-1a8e45b40a1e', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': '808933' },
      body: JSON.stringify({
        sessionId: '808933',
        runId: 'login-hang-v2',
        hypothesisId: 'H8',
        location: 'api.js:apiJson:loginRequestStart',
        message: 'apiJson login request start',
        data: { method, path },
        timestamp: Date.now(),
      }),
    }).catch(() => {})
  }
  // #endregion
  const headers = {
    Accept: 'application/json',
    ...xsrfHeaders(),
  }
  const opts = {
    method,
    credentials: 'include',
    headers,
  }
  if (
    body !== undefined
    && method !== 'GET'
    && method !== 'HEAD'
  ) {
    headers['Content-Type'] = 'application/json'
    opts.body = JSON.stringify(body)
  }
  const res = await requestWithTimeout(method, path, url, opts)
  // #region agent log
  if (path === '/api/login') {
    fetch('http://127.0.0.1:7800/ingest/b3c811d3-7ec8-4727-aae6-1a8e45b40a1e', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': '808933' },
      body: JSON.stringify({
        sessionId: '808933',
        runId: 'login-hang-v2',
        hypothesisId: 'H8',
        location: 'api.js:apiJson:loginResponseStart',
        message: 'apiJson login response received',
        data: { status: res.status, ok: res.ok },
        timestamp: Date.now(),
      }),
    }).catch(() => {})
  }
  // #endregion
  const text = await res.text()
  let data = null
  if (text) {
    try {
      data = JSON.parse(text)
    } catch {
      data = { message: text }
    }
  }
  if (res.status === 401 && !shouldSkipGlobalUnauthorizedHandler(method, path)) {
    const { applyUnauthorizedFromApi } = await import('../composables/useSession.js')
    await applyUnauthorizedFromApi(path, method)
  }
  return { ok: res.ok, status: res.status, data }
}

/**
 * @returns {Promise<{ ok: boolean, status: number, data: unknown, error?: string }>}
 */
export async function getHealth() {
  try {
    const { ok, status, data } = await apiJson('GET', '/api/health')
    if (!ok) {
      return {
        ok: false,
        status,
        data,
        error: `HTTP ${status}`,
      }
    }
    return { ok: true, status, data }
  } catch (e) {
    const message = e instanceof Error ? e.message : String(e)
    return { ok: false, status: 0, data: null, error: message }
  }
}
