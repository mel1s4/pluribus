import { ref } from 'vue'
import { apiJson, ensureCsrfCookie } from '../services/api'
import { clearAllCache } from '../services/cachedApi.js'

export const sessionUser = ref(null)
export const sessionStatus = ref('unknown')

const HAD_AUTH_STORAGE_KEY = 'pluribus_had_auth_session'

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
  const { ok, status, data } = await apiJson('GET', '/api/user')
  if (status === 401) {
    sessionUser.value = null
    sessionStatus.value = 'guest'
    return
  }
  if (ok && data && typeof data === 'object' && 'user' in data && data.user) {
    sessionUser.value = data.user
    sessionStatus.value = 'authenticated'
    markHadAuthenticatedSession()
    return
  }
  sessionUser.value = null
  sessionStatus.value = 'guest'
}

/**
 * @param {{ email: string, password: string, remember?: boolean }} payload
 */
export async function loginRequest(payload) {
  await ensureCsrfCookie()
  return apiJson('POST', '/api/login', {
    email: payload.email,
    password: payload.password,
    remember: Boolean(payload.remember),
  })
}

export async function logoutRequest() {
  await ensureCsrfCookie()
  const out = await apiJson('POST', '/api/logout')
  sessionUser.value = null
  sessionStatus.value = 'guest'
  clearHadAuthenticatedSession()
  clearAllCache()
  return out
}

export function setSessionFromLoginUser(user) {
  sessionUser.value = user
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
  sessionUser.value = null
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
    resolveSession,
    loginRequest,
    logoutRequest,
    setSessionFromLoginUser,
  }
}
