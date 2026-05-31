/**
 * @param {string | number | null | undefined} amount
 * @param {string | null | undefined} currencyCode ISO 4217 (MXN, USD, EUR)
 * @returns {string}
 */
export function formatLocalCurrencyPrice(amount, currencyCode) {
  const n = Number(amount)
  if (!Number.isFinite(n)) {
    return amount == null || amount === '' ? '' : String(amount)
  }
  const code = typeof currencyCode === 'string' && currencyCode.trim() ? currencyCode.trim() : 'MXN'
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: code }).format(n)
  } catch {
    return `${code} ${n.toFixed(2)}`
  }
}
