const defaultBase = 'http://localhost:9122'

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
  const raw = import.meta.env.VITE_API_BASE_URL
  const configured =
    typeof raw === 'string' && raw.length > 0 ? raw : defaultBase
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
  await fetch(`${apiBaseUrl()}/sanctum/csrf-cookie`, {
    method: 'GET',
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
}

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
  const res = await fetch(url, {
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
  const res = await fetch(url, opts)
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
