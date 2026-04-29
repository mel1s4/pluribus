/**
 * @typedef {object} DiscoveryEventRow
 * @property {'post'|'task'} entity_type
 * @property {number} id
 * @property {string} title
 * @property {string|null} [start_at]
 * @property {string|null} [end_at]
 * @property {boolean} [all_day]
 * @property {number|null} [calendar_id]
 * @property {string|null} [type]
 * @property {string|null} [recurrence_rule]
 * @property {string|null} [recurrence_id]
 * @property {string|null} [_recurrenceInstanceStart]
 * @property {boolean} [_isRecurrenceInstance]
 */

/**
 * @param {unknown[]} calendars
 * @returns {Map<number, { id: number, name: string, color?: string }>}
 */
export function buildCalendarLookup(calendars) {
  const map = new Map()
  if (!Array.isArray(calendars)) return map
  for (const c of calendars) {
    if (c && typeof c === 'object' && typeof c.id === 'number') {
      map.set(c.id, c)
    }
  }
  return map
}

/**
 * @param {DiscoveryEventRow} row
 * @param {Map<number, { id: number, name: string, color?: string }>} calendarById
 * @returns {object} FullCalendar event object
 */
export function transformDiscoveryEventToFullCalendar(row, calendarById) {
  const entityType = row.entity_type === 'task' ? 'task' : 'post'
  const cal = row.calendar_id != null ? calendarById.get(row.calendar_id) : null
  const color = cal?.color || '#64748b'
  const instanceKey =
    row._isRecurrenceInstance && row.start_at
      ? `__${row.start_at}`
      : ''
  const id = `${entityType}-${row.id}${instanceKey}`

  const isInstance = Boolean(row._isRecurrenceInstance)

  return {
    id,
    title: row.title || '',
    start: row.start_at || undefined,
    end: row.end_at || undefined,
    allDay: Boolean(row.all_day),
    classNames: [`fc-event-entity-${entityType}`],
    backgroundColor: color,
    borderColor: color,
    textColor: '#0f172a',
    editable: !isInstance,
    startEditable: !isInstance,
    durationEditable: !isInstance,
    extendedProps: {
      entityType,
      entityId: row.id,
      calendarId: row.calendar_id,
      postType: row.type,
      recurrenceRule: row.recurrence_rule,
      recurrenceId: row.recurrence_id,
      recurrenceInstanceId: row._recurrenceInstanceId || null,
      seriesStartAt: row._seriesStartAt || null,
      isRecurrenceInstance: Boolean(row._isRecurrenceInstance),
      raw: { ...row },
    },
  }
}

/**
 * @param {DiscoveryEventRow[]} rows
 * @param {Map<number, object>} calendarById
 * @returns {object[]}
 */
export function discoveryRowsToFullCalendarEvents(rows, calendarById) {
  return rows.map((r) => transformDiscoveryEventToFullCalendar(r, calendarById))
}
