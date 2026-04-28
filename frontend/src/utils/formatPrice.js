import { language } from '../i18n/i18n'

/**
 * @param {string | number | null | undefined} amount
 * @param {string | null | undefined} currencyCode Max 4 chars from community settings; optional.
 * @returns {string}
 */
export function formatOfferPrice(amount, currencyCode) {
  const n = Number(amount)
  if (!Number.isFinite(n)) {
    return amount == null || amount === '' ? '' : String(amount)
  }
  const lang = language.value
  const locale = lang === 'es' ? 'es-ES' : 'en-US'
  const formatted = new Intl.NumberFormat(locale, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n)
  const c = typeof currencyCode === 'string' ? currencyCode.trim() : ''
  if (!c.length) {
    return formatted
  }
  return `${c} ${formatted}`
}
