/**
 * Minimal RRULE expansion (no external deps) for FREQ=DAILY|WEEKLY|MONTHLY,
 * INTERVAL, COUNT, UNTIL. BYDAY and other advanced rules fall back to single row.
 */

/**
 * @param {string} ruleStr
 * @returns {Record<string, string>}
 */
function parseRruleParts(ruleStr) {
  const raw = String(ruleStr).trim().replace(/^RRULE:/i, '')
  const parts = {}
  for (const seg of raw.split(';')) {
    const idx = seg.indexOf('=')
    if (idx === -1) continue
    const k = seg.slice(0, idx).trim().toUpperCase()
    const v = seg.slice(idx + 1).trim()
    if (k) parts[k] = v
  }
  return parts
}

/**
 * @param {string} untilVal
 * @returns {Date|null}
 */
function parseUntil(untilVal) {
  if (!untilVal) return null
  const s = String(untilVal).trim()
  if (/^\d{8}T\d{6}Z?$/i.test(s)) {
    const y = +s.slice(0, 4)
    const m = +s.slice(4, 6) - 1
    const d = +s.slice(6, 8)
    const h = +s.slice(9, 11)
    const min = +s.slice(11, 13)
    const sec = +s.slice(13, 15)
    return new Date(Date.UTC(y, m, d, h, min, sec))
  }
  const d = new Date(s)
  return Number.isNaN(d.getTime()) ? null : d
}

function addDays(d, days) {
  const x = new Date(d.getTime())
  x.setUTCDate(x.getUTCDate() + days)
  return x
}

function addMonthsUtc(d, months) {
  const x = new Date(d.getTime())
  const day = x.getUTCDate()
  x.setUTCMonth(x.getUTCMonth() + months)
  if (x.getUTCDate() < day) {
    x.setUTCDate(0)
  }
  return x
}

/**
 * @param {Date} cur
 * @param {string} freq
 * @param {number} interval
 * @returns {Date}
 */
function advanceOccurrence(cur, freq, interval) {
  const f = (freq || 'DAILY').toUpperCase()
  if (f === 'WEEKLY') {
    return addDays(cur, 7 * interval)
  }
  if (f === 'MONTHLY') {
    return addMonthsUtc(cur, interval)
  }
  return addDays(cur, interval)
}

/**
 * @param {Date} dtStartUtc
 * @param {Record<string, string>} parts
 * @param {Date} rangeStart
 * @param {Date} rangeEnd
 * @returns {Date[]|null} null = rule not supported by this expander
 */
function enumerateOccurrences(dtStartUtc, parts, rangeStart, rangeEnd) {
  const freq = (parts.FREQ || 'DAILY').toUpperCase()
  if (freq !== 'DAILY' && freq !== 'WEEKLY' && freq !== 'MONTHLY') {
    return null
  }
  if (parts.BYDAY || parts.BYMONTHDAY || parts.BYSETPOS) {
    return null
  }

  const interval = Math.max(1, parseInt(parts.INTERVAL || '1', 10) || 1)
  const maxCount = parts.COUNT ? Math.min(5000, parseInt(parts.COUNT, 10) || 0) : 5000
  const until = parseUntil(parts.UNTIL || '')

  const out = []
  let cur = new Date(dtStartUtc.getTime())
  let index = 0
  const guard = 10000

  let steps = 0
  while (cur < rangeStart && index < maxCount && steps < guard) {
    if (until && cur.getTime() > until.getTime()) return []
    cur = advanceOccurrence(cur, freq, interval)
    index++
    steps++
  }

  while (cur.getTime() <= rangeEnd.getTime() && index < maxCount && steps < guard) {
    if (until && cur.getTime() > until.getTime()) break
    if (cur.getTime() >= rangeStart.getTime()) {
      out.push(new Date(cur.getTime()))
    }
    cur = advanceOccurrence(cur, freq, interval)
    index++
    steps++
  }

  return out
}

function rowOverlapsRange(row, rangeStart, rangeEnd) {
  if (!row.start_at) return false
  const start = new Date(row.start_at)
  if (Number.isNaN(start.getTime())) return false
  const end = row.end_at ? new Date(row.end_at) : start
  return start.getTime() <= rangeEnd.getTime() && end.getTime() >= rangeStart.getTime()
}

/**
 * @param {object} row
 * @returns {number} duration in ms (0 if unknown)
 */
function durationMs(row) {
  if (!row.start_at || !row.end_at) return 0
  const a = new Date(row.start_at).getTime()
  const b = new Date(row.end_at).getTime()
  if (Number.isNaN(a) || Number.isNaN(b) || b < a) return 0
  return b - a
}

/**
 * Expand a discovery row into dated instances for [rangeStart, rangeEnd].
 * @param {object} row
 * @param {Date} rangeStart
 * @param {Date} rangeEnd
 * @returns {object[]}
 */
export function expandRowForRange(row, rangeStart, rangeEnd) {
  const ruleStr = row.recurrence_rule
  if (!ruleStr || typeof ruleStr !== 'string' || !ruleStr.trim()) {
    return [row]
  }
  if (!row.start_at) {
    return [row]
  }

  const dtStart = new Date(row.start_at)
  if (Number.isNaN(dtStart.getTime())) {
    return [row]
  }

  const parts = parseRruleParts(ruleStr)
  const dates = enumerateOccurrences(dtStart, parts, rangeStart, rangeEnd)
  if (dates === null) {
    return rowOverlapsRange(row, rangeStart, rangeEnd) ? [{ ...row }] : []
  }
  if (!dates.length) {
    return []
  }

  const dur = durationMs(row)

  return dates.map((instanceStart) => {
    const startIso = instanceStart.toISOString()
    let endIso = row.end_at
    if (dur > 0) {
      endIso = new Date(instanceStart.getTime() + dur).toISOString()
    }
    return {
      ...row,
      start_at: startIso,
      end_at: endIso,
      _recurrenceInstanceId: startIso,
      _seriesStartAt: row.start_at,
      _isRecurrenceInstance: true,
    }
  })
}

/**
 * @param {object[]} rows
 * @param {Date|string} rangeStart
 * @param {Date|string} rangeEnd
 * @returns {object[]}
 */
export function expandDiscoveryRowsForRange(rows, rangeStart, rangeEnd) {
  const rs = rangeStart instanceof Date ? rangeStart : new Date(rangeStart)
  const re = rangeEnd instanceof Date ? rangeEnd : new Date(rangeEnd)
  if (Number.isNaN(rs.getTime()) || Number.isNaN(re.getTime())) {
    return rows.slice()
  }

  const out = []
  for (const row of rows) {
    const expanded = expandRowForRange(row, rs, re)
    out.push(...expanded)
  }
  return out
}
