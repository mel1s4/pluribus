/**
 * Human-readable line for community wallet currency (display only; not ledger identity).
 * @param {string | null | undefined} name
 * @param {string | null | undefined} code
 * @returns {string}
 */
export function walletCurrencyDisplayLine(name, code) {
  const n = typeof name === 'string' && name.trim() ? name.trim() : ''
  const c = typeof code === 'string' && code.trim() ? code.trim() : ''
  if (n && c) {
    return `${n} (${c})`
  }
  if (n) {
    return n
  }
  if (c) {
    return c.toUpperCase()
  }
  return ''
}
