import { ref } from 'vue'
import { messages } from './messages'

const STORAGE_KEY = 'pluribus.language'
const SUPPORTED_LANGS = ['en', 'es']

export const language = ref('en')

export function initI18n() {
  const stored = safeGetLocalStorage(STORAGE_KEY)
  const next = stored && SUPPORTED_LANGS.includes(stored) ? stored : 'en'

  language.value = next
  // Helps screen readers and browser translation hints.
  document.documentElement.lang = next
}

export function setLanguage(nextLang) {
  const lang = SUPPORTED_LANGS.includes(nextLang) ? nextLang : 'en'
  language.value = lang
  safeSetLocalStorage(STORAGE_KEY, lang)
  document.documentElement.lang = lang
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

