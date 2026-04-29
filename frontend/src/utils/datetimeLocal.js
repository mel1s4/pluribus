/**
 * Convert an ISO string or Date to `datetime-local` input value (local timezone).
 * @param {string|Date|null|undefined} val
 * @returns {string}
 */
export function toDatetimeLocal(val) {
  if (!val) return ''
  const d = val instanceof Date ? val : new Date(val)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/**
 * Parse `datetime-local` string to ISO 8601 or null.
 * @param {string} s
 * @returns {string|null}
 */
export function fromDatetimeLocal(s) {
  if (!s) return null
  const d = new Date(s)
  if (Number.isNaN(d.getTime())) return null
  return d.toISOString()
}
