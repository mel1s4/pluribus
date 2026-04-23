export const DEFAULT_LANGUAGE = 'en'

export const SUPPORTED_LANGUAGES = [
  { code: 'en', labelKey: 'settings.language.english' },
  { code: 'es', labelKey: 'settings.language.spanish' },
]

export const SUPPORTED_LANGUAGE_CODES = SUPPORTED_LANGUAGES.map((entry) => entry.code)

export function isSupportedLanguage(value) {
  return SUPPORTED_LANGUAGE_CODES.includes(value)
}
