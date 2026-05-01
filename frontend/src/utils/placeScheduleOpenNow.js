import { PLACE_SCHEDULE_DAY_KEYS, normalizeServiceSchedule } from './placeSchedule.js'

/** JavaScript Sunday=0 … Saturday=6 → schedule day keys */
const JS_DAY_TO_KEY = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat']

/**
 * @param {string} s
 * @returns {number | null} minutes since midnight
 */
export function parseMinutesSinceMidnight(s) {
  if (typeof s !== 'string') return null
  const m = s.trim().match(/^(\d{1,2}):(\d{2})$/)
  if (!m) return null
  const h = Number(m[1])
  const min = Number(m[2])
  if (!Number.isFinite(h) || !Number.isFinite(min) || h > 23 || min > 59) return null
  return h * 60 + min
}

/**
 * @param {string} hhmm
 * @param {Date} [refDate]
 */
export function formatScheduleClockLabel(hhmm, refDate = new Date()) {
  const mins = parseMinutesSinceMidnight(hhmm)
  if (mins == null) return ''
  const d = new Date(refDate)
  d.setHours(Math.floor(mins / 60), mins % 60, 0, 0)
  return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
}

/**
 * @param {number} openMin
 * @param {number} closeMin
 * @param {number} nowMin
 */
function slotContainsNow(openMin, closeMin, nowMin) {
  if (closeMin > openMin) {
    return nowMin >= openMin && nowMin < closeMin
  }
  if (closeMin < openMin) {
    return nowMin >= openMin || nowMin < closeMin
  }
  return false
}

function addDays(date, n) {
  const d = new Date(date)
  d.setDate(d.getDate() + n)
  return d
}

/**
 * @param {Record<string, Array<{ open: string, close: string }>>} norm
 * @param {Date} from
 * @returns {{ hhmm: string, dayOffset: number, date: Date } | null}
 */
function findNextOpening(norm, from) {
  for (let offset = 0; offset <= 7; offset += 1) {
    const d = addDays(from, offset)
    const key = JS_DAY_TO_KEY[d.getDay()]
    const slots = norm[key] || []
    if (!slots.length) {
      continue
    }
    const sorted = [...slots].sort(
      (a, b) =>
        (parseMinutesSinceMidnight(a.open) ?? 0) - (parseMinutesSinceMidnight(b.open) ?? 0),
    )
    const nowMin = from.getHours() * 60 + from.getMinutes()
    if (offset === 0) {
      for (const slot of sorted) {
        const openM = parseMinutesSinceMidnight(slot.open)
        if (openM == null) continue
        if (openM > nowMin) {
          return { hhmm: slot.open, dayOffset: 0, date: d }
        }
      }
      continue
    }
    const first = sorted[0]
    if (first?.open) {
      return { hhmm: first.open, dayOffset: offset, date: d }
    }
  }
  return null
}

/**
 * @param {unknown} schedule
 * @param {Date} [now]
 */
export function getPlaceScheduleOpenStatus(schedule, now = new Date()) {
  const norm = normalizeServiceSchedule(schedule)
  const hasAny = PLACE_SCHEDULE_DAY_KEYS.some((d) => (norm[d] || []).length > 0)
  if (!hasAny) {
    return {
      code: /** @type {const} */ ('no_hours'),
      todaySummary: '',
      closesAtDisplay: null,
      opensNextDisplay: null,
      opensNextDayOffset: null,
      opensNextWeekday: null,
    }
  }

  const dayKey = JS_DAY_TO_KEY[now.getDay()]
  const slots = norm[dayKey] || []
  const nowMin = now.getHours() * 60 + now.getMinutes()

  const todaySummary = slots.map((s) => `${s.open}–${s.close}`).join(', ')

  if (slots.length === 0) {
    const next = findNextOpening(norm, now)
    const opensNextDisplay = next ? formatScheduleClockLabel(next.hhmm, now) : null
    const opensNextDayOffset = next?.dayOffset ?? null
    const opensNextWeekday =
      next && next.dayOffset > 0
        ? next.date.toLocaleDateString(undefined, { weekday: 'short' })
        : null
    return {
      code: /** @type {const} */ ('closed_today'),
      todaySummary: '',
      closesAtDisplay: null,
      opensNextDisplay,
      opensNextDayOffset,
      opensNextWeekday,
    }
  }

  for (const slot of slots) {
    const openMin = parseMinutesSinceMidnight(slot.open)
    const closeMin = parseMinutesSinceMidnight(slot.close)
    if (openMin == null || closeMin == null) {
      continue
    }
    if (slotContainsNow(openMin, closeMin, nowMin)) {
      return {
        code: /** @type {const} */ ('open'),
        todaySummary,
        closesAtDisplay: formatScheduleClockLabel(slot.close, now),
        opensNextDisplay: null,
        opensNextDayOffset: null,
        opensNextWeekday: null,
      }
    }
  }

  const next = findNextOpening(norm, now)
  const opensNextDisplay = next ? formatScheduleClockLabel(next.hhmm, now) : null
  const opensNextDayOffset = next?.dayOffset ?? null
  const opensNextWeekday =
    next && next.dayOffset > 0
      ? next.date.toLocaleDateString(undefined, { weekday: 'short' })
      : null
  return {
    code: /** @type {const} */ ('closed'),
    todaySummary,
    closesAtDisplay: null,
    opensNextDisplay,
    opensNextDayOffset,
    opensNextWeekday,
  }
}
