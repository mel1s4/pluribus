import { ref } from 'vue'
import { messages } from './messages'
import { DEFAULT_LANGUAGE, isSupportedLanguage } from './locales'

const STORAGE_KEY = 'pluribus.language'

export const language = ref(DEFAULT_LANGUAGE)

export function initI18n(options = {}) {
  const stored = safeGetLocalStorage(STORAGE_KEY)
  const communityDefault =
    typeof options.defaultLanguage === 'string' && isSupportedLanguage(options.defaultLanguage)
      ? options.defaultLanguage
      : DEFAULT_LANGUAGE
  const allowStoredLanguage = options.allowStoredLanguage !== false
  const next = allowStoredLanguage && stored && isSupportedLanguage(stored) ? stored : communityDefault

  language.value = next
  // Helps screen readers and browser translation hints.
  document.documentElement.lang = next
}

export function setLanguage(nextLang) {
  const lang = isSupportedLanguage(nextLang) ? nextLang : DEFAULT_LANGUAGE
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
export function applyJoinInvitationPageLanguage(lang) {
  if (languageBeforeJoinInvitationPage === null) {
    languageBeforeJoinInvitationPage = language.value
  }
  const next = typeof lang === 'string' && isSupportedLanguage(lang) ? lang : DEFAULT_LANGUAGE
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
  const langTable = messages[language.value] ?? messages.en
  return langTable?.[key] ?? messages.en?.[key] ?? key
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

