/**
 * Short label for task row (start / end hint).
 * @param {{ start_at?: string|null, end_at?: string|null, all_day?: boolean }} task
 */
export function formatTaskRowMeta(task) {
  const start = task.start_at ? new Date(task.start_at) : null
  const end = task.end_at ? new Date(task.end_at) : null
  if (start && !Number.isNaN(start.getTime())) {
    const opts = task.all_day
      ? { dateStyle: 'short' }
      : { dateStyle: 'short', timeStyle: 'short' }
    const a = start.toLocaleString(undefined, opts)
    if (end && !Number.isNaN(end.getTime())) {
      const b = end.toLocaleString(undefined, opts)
      return `${a} – ${b}`
    }
    return a
  }
  return ''
}
