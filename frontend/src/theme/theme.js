import { ref } from 'vue'

const STORAGE_KEY = 'pluribus.themeMode'
const SUPPORTED_MODES = ['system', 'light', 'dark']

export const themeMode = ref('system')

let mql = null
let listenerAttached = false

export function initTheme() {
  const stored = safeGetLocalStorage(STORAGE_KEY)
  const nextMode =
    stored && SUPPORTED_MODES.includes(stored) ? stored : 'system'

  themeMode.value = nextMode
  applyThemeForMode(nextMode)
  attachSystemListenerIfNeeded(nextMode)
}

export function setThemeMode(nextMode) {
  const mode = SUPPORTED_MODES.includes(nextMode) ? nextMode : 'system'
  themeMode.value = mode
  safeSetLocalStorage(STORAGE_KEY, mode)

  applyThemeForMode(mode)
  attachSystemListenerIfNeeded(mode)
}

function applyThemeForMode(mode) {
  if (typeof document === 'undefined') return

  if (mode === 'light') {
    document.documentElement.setAttribute('data-theme', 'light')
    return
  }

  if (mode === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark')
    return
  }

  document.documentElement.setAttribute(
    'data-theme',
    getSystemTheme()
  )
}

function getSystemTheme() {
  if (typeof window === 'undefined') return 'light'

  mql = mql ?? window.matchMedia('(prefers-color-scheme: dark)')
  return mql.matches ? 'dark' : 'light'
}

function attachSystemListenerIfNeeded(mode) {
  if (mode !== 'system') return
  if (listenerAttached) return

  mql = mql ?? window.matchMedia('(prefers-color-scheme: dark)')

  const onChange = () => {
    // Only apply if the user is still on "system".
    if (themeMode.value === 'system') {
      applyThemeForMode('system')
    }
  }

  listenerAttached = true
  if (mql.addEventListener) mql.addEventListener('change', onChange)
  else mql.addListener(onChange)
}

function safeGetLocalStorage(key) {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}

function safeSetLocalStorage(key, value) {
  try {
    localStorage.setItem(key, value)
  } catch {
    // Ignore (e.g., storage blocked).
  }
}

