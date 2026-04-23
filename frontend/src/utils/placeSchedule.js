/** @typedef {{ open: string, close: string }} PlaceTimeSlot */

/** @type {readonly string[]} */
export const PLACE_SCHEDULE_DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']

/**
 * @returns {Record<string, PlaceTimeSlot[]>}
 */
export function emptyServiceSchedule() {
  return Object.fromEntries(PLACE_SCHEDULE_DAY_KEYS.map((k) => [k, []]))
}

/**
 * @param {unknown} raw
 * @returns {Record<string, PlaceTimeSlot[]>}
 */
export function normalizeServiceSchedule(raw) {
  const out = emptyServiceSchedule()
  if (!raw || typeof raw !== 'object') {
    return out
  }
  const o = /** @type {Record<string, unknown>} */ (raw)
  for (const day of PLACE_SCHEDULE_DAY_KEYS) {
    const slots = o[day]
    if (!Array.isArray(slots)) {
      continue
    }
    const list = []
    for (const slot of slots) {
      if (!slot || typeof slot !== 'object') {
        continue
      }
      const s = /** @type {Record<string, unknown>} */ (slot)
      const open = typeof s.open === 'string' ? s.open.trim() : ''
      const close = typeof s.close === 'string' ? s.close.trim() : ''
      if (open === '' || close === '') {
        continue
      }
      list.push({ open, close })
      if (list.length >= 12) {
        break
      }
    }
    out[day] = list
  }
  return out
}
