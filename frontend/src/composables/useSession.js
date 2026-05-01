import { ref } from 'vue'
import { apiJson, ensureCsrfCookie } from '../services/api'
import { clearAllCache } from '../services/cachedApi.js'

export const sessionUser = ref(null)
export const sessionStatus = ref('unknown')

/** @type {import('vue').Ref<{ active: boolean, actor?: object, started_at?: string, ends_at?: string }>} */
export const sessionPersonification = ref({ active: false })

const HAD_AUTH_STORAGE_KEY = 'pluribus_had_auth_session'
const SESSION_RESOLVE_TIMEOUT_MS = 8000

/**
 * Incremented when login/logout/unauthorized explicitly changes session state.
 * In-flight resolveSession() calls from an older epoch must not apply results — they would
 * race bootstrap or a prior navigation and overwrite a valid authenticated session.
 */
let sessionResolutionEpoch = 0
let pendingSessionResolution = null

function bumpSessionResolutionEpoch() {
  sessionResolutionEpoch += 1
}

function withTimeout(promise, timeoutMs, label) {
  let timeoutId
  const timeoutPromise = new Promise((_, reject) => {
    timeoutId = setTimeout(() => {
      reject(new Error(`${label} timed out after ${timeoutMs}ms`))
    }, timeoutMs)
  })
  return Promise.race([promise, timeoutPromise]).finally(() => {
    clearTimeout(timeoutId)
  })
}

function markHadAuthenticatedSession() {
  try {
    sessionStorage.setItem(HAD_AUTH_STORAGE_KEY, '1')
  } catch {
    /* ignore quota / private mode */
  }
}

export function clearHadAuthenticatedSession() {
  try {
    sessionStorage.removeItem(HAD_AUTH_STORAGE_KEY)
  } catch {
    /* ignore */
  }
}

export function hadAuthenticatedSessionMarker() {
  try {
    return sessionStorage.getItem(HAD_AUTH_STORAGE_KEY) === '1'
  } catch {
    return false
  }
}

export async function resolveSession() {
  if (pendingSessionResolution) {
    return pendingSessionResolution
  }
  const epochAtStart = sessionResolutionEpoch
  const run = (async () => {
    try {
      const { ok, status, data } = await withTimeout(
        apiJson('GET', '/api/user'),
        SESSION_RESOLVE_TIMEOUT_MS,
        'resolveSession',
      )
      if (epochAtStart !== sessionResolutionEpoch) {
        return
      }
      if (status === 401) {
        sessionUser.value = null
        sessionPersonification.value = { active: false }
        sessionStatus.value = 'guest'
        return
      }
      if (ok && data && typeof data === 'object' && 'user' in data && data.user) {
        sessionUser.value = data.user
        sessionPersonification.value =
          data.personification && typeof data.personification === 'object'
            ? data.personification
            : { active: false }
        sessionStatus.value = 'authenticated'
        markHadAuthenticatedSession()
        return
      }
      sessionUser.value = null
      sessionPersonification.value = { active: false }
      sessionStatus.value = 'guest'
    } catch {
      if (epochAtStart !== sessionResolutionEpoch) {
        return
      }
      // Fail open for public pages: never leave the app in "unknown" forever.
      sessionUser.value = null
      sessionPersonification.value = { active: false }
      sessionStatus.value = 'guest'
    } finally {
      pendingSessionResolution = null
    }
  })()
  pendingSessionResolution = run
  return run
}

/**
 * @param {{ email: string, password: string, remember?: boolean }} payload
 */
export async function loginRequest(payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/login', {
    email: payload.email,
    password: payload.password,
    remember: Boolean(payload.remember),
  })
  return result
}

/**
 * @param {{ email: string }} payload
 */
export async function requestVisitorLoginLink(payload) {
  await ensureCsrfCookie()
  return apiJson('POST', '/api/visitor-auth/request-link', {
    email: payload.email,
  })
}

/**
 * @param {string} token
 */
export async function consumeVisitorLoginLink(token) {
  await ensureCsrfCookie()
  return apiJson('POST', `/api/visitor-auth/consume/${token}`)
}

/**
 * @param {{ email: string }} payload
 */
export async function requestPasswordReset(payload) {
  await ensureCsrfCookie()
  return apiJson('POST', '/api/password/forgot', {
    email: payload.email,
  })
}

/**
 * @param {{ token: string, email: string, password: string, password_confirmation: string }} payload
 */
export async function resetPassword(payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/password/reset', {
    token: payload.token,
    email: payload.email,
    password: payload.password,
    password_confirmation: payload.password_confirmation,
  })
  if (result.ok) {
    bumpSessionResolutionEpoch()
    // Server invalidated every session for this user; clear local mirror so
    // the post-reset redirect to /login isn't bounced back to /dashboard.
    sessionUser.value = null
    sessionPersonification.value = { active: false }
    sessionStatus.value = 'guest'
    clearHadAuthenticatedSession()
    clearAllCache()
  }
  return result
}

export async function logoutRequest() {
  await ensureCsrfCookie()
  const out = await apiJson('POST', '/api/logout')
  bumpSessionResolutionEpoch()
  sessionUser.value = null
  sessionPersonification.value = { active: false }
  sessionStatus.value = 'guest'
  clearHadAuthenticatedSession()
  clearAllCache()
  return out
}

/**
 * @param {object} user
 * @param {{ active?: boolean } | null | undefined} personification
 */
export function setSessionFromLoginUser(user, personification) {
  bumpSessionResolutionEpoch()
  sessionUser.value = user
  sessionPersonification.value =
    personification && typeof personification === 'object' ? personification : { active: false }
  sessionStatus.value = 'authenticated'
  markHadAuthenticatedSession()
  clearAllCache()
}

/**
 * Session expired or revoked while the app still thought the user was signed in.
 * Skips /api/user (handled by resolveSession + router) and failed /api/login attempts.
 */
export async function applyUnauthorizedFromApi(path, method) {
  if (
    (method === 'GET' && path === '/api/user')
    || (method === 'POST' && path === '/api/login')
    || (method === 'POST' && path === '/api/logout')
  ) {
    return
  }
  if (sessionStatus.value !== 'authenticated') {
    return
  }
  bumpSessionResolutionEpoch()
  sessionUser.value = null
  sessionPersonification.value = { active: false }
  sessionStatus.value = 'guest'
  clearHadAuthenticatedSession()
  clearAllCache()
  const { default: router } = await import('../router/index.js')
  const route = router.currentRoute.value
  if (route.name === 'login' || !route.meta.requiresAuth) {
    return
  }
  await router.replace({
    name: 'login',
    query: {
      redirect: route.fullPath,
      sessionEnded: '1',
    },
  })
}

export function useSession() {
  return {
    user: sessionUser,
    status: sessionStatus,
    personification: sessionPersonification,
    resolveSession,
    loginRequest,
    requestVisitorLoginLink,
    consumeVisitorLoginLink,
    requestPasswordReset,
    resetPassword,
    logoutRequest,
    setSessionFromLoginUser,
  }
}
