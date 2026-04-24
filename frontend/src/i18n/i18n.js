import { ref } from 'vue'
import enMessages from './locales/en.js'
import { DEFAULT_LANGUAGE, isSupportedLanguage } from './locales.js'

const STORAGE_KEY = 'pluribus.language'

export const language = ref(DEFAULT_LANGUAGE)
/** True after first {@link initI18n} completes (locales applied). */
export const i18nReady = ref(false)

const loadedMessages = new Map()

const enTable = enMessages?.default ?? enMessages
loadedMessages.set('en', enTable)

async function ensureLocaleLoaded(lang) {
  if (!isSupportedLanguage(lang)) {
    return
  }
  if (loadedMessages.has(lang)) {
    return
  }
  if (lang === 'en') {
    return
  }
  const mod = await import('./locales/es.js')
  const table = mod.default ?? mod
  loadedMessages.set('es', table)
}

export function initI18n(options = {}) {
  const stored = safeGetLocalStorage(STORAGE_KEY)
  const communityDefault =
    typeof options.defaultLanguage === 'string' && isSupportedLanguage(options.defaultLanguage)
      ? options.defaultLanguage
      : DEFAULT_LANGUAGE
  const allowStoredLanguage = options.allowStoredLanguage !== false
  const next = allowStoredLanguage && stored && isSupportedLanguage(stored) ? stored : communityDefault

  return ensureLocaleLoaded(next)
    .then(() => {
      language.value = next
      document.documentElement.lang = next
    })
    .catch((err) => {
      console.error('i18n init failed', err)
    })
    .finally(() => {
      i18nReady.value = true
    })
}

export async function setLanguage(nextLang) {
  const lang = isSupportedLanguage(nextLang) ? nextLang : DEFAULT_LANGUAGE
  await ensureLocaleLoaded(lang)
  language.value = lang
  safeSetLocalStorage(STORAGE_KEY, lang)
  document.documentElement.lang = lang
}

/** Snapshot for the public join-invitation page so we can restore after leaving. */
let languageBeforeJoinInvitationPage = null

/**
 * Apply UI language for the invitation registration route (community default),
 * without writing localStorage. Call {@link clearJoinInvitationPageLanguage} on unmount.
 */
export async function applyJoinInvitationPageLanguage(lang) {
  if (languageBeforeJoinInvitationPage === null) {
    languageBeforeJoinInvitationPage = language.value
  }
  const next = typeof lang === 'string' && isSupportedLanguage(lang) ? lang : DEFAULT_LANGUAGE
  await ensureLocaleLoaded(next)
  language.value = next
  document.documentElement.lang = next
}

export function clearJoinInvitationPageLanguage() {
  if (languageBeforeJoinInvitationPage === null) {
    return
  }
  language.value = languageBeforeJoinInvitationPage
  document.documentElement.lang = languageBeforeJoinInvitationPage
  languageBeforeJoinInvitationPage = null
}

export function t(key) {
  const langTable =
    loadedMessages.get(language.value)
    ?? loadedMessages.get('en')
    ?? enTable
  return langTable?.[key] ?? enTable?.[key] ?? key
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
