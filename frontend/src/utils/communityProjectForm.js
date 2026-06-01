/** @typedef {'draft' | 'active' | 'completed' | 'archived'} ProjectStatus */

/** @type {ProjectStatus[]} */
export const PROJECT_STATUSES = ['draft', 'active', 'completed', 'archived']

/**
 * @param {unknown} unitCost
 * @param {unknown} units
 */
export function lineSubtotal(unitCost, units) {
  const u = parseFloat(String(unitCost))
  const n = parseFloat(String(units))
  if (!Number.isFinite(u) || !Number.isFinite(n)) return '0.00'
  return (u * n).toFixed(2)
}

/**
 * @param {Array<{ unit_cost?: unknown, units?: unknown, cost?: unknown }>} rows
 */
export function budgetTotalFromRows(rows) {
  if (!Array.isArray(rows)) return '0.00'
  let total = 0
  for (const row of rows) {
    if (row.unit_cost != null || row.units != null) {
      total += parseFloat(lineSubtotal(row.unit_cost, row.units))
    } else if (row.cost != null) {
      total += parseFloat(String(row.cost)) || 0
    }
  }
  return total.toFixed(2)
}

/**
 * @param {Array<{ name?: string, description?: string, unit_cost?: unknown, units?: unknown }>} rows
 */
export function buildBudgetPayload(rows) {
  return rows
    .filter((r) => typeof r.name === 'string' && r.name.trim() !== '')
    .map((r, i) => ({
      name: r.name.trim(),
      description:
        typeof r.description === 'string' && r.description.trim() !== '' ? r.description.trim() : undefined,
      unit_cost: String(r.unit_cost ?? '0').trim() || '0',
      units: String(r.units ?? '1').trim() || '1',
      sort_order: i,
    }))
}

/**
 * @param {Array<{ title?: string, tasks?: Array<{ body?: string }> }>} positions
 */
export function buildJobPositionsPayload(positions) {
  return positions
    .filter((p) => typeof p.title === 'string' && p.title.trim() !== '')
    .map((p, i) => ({
      title: p.title.trim(),
      sort_order: i,
      tasks: (Array.isArray(p.tasks) ? p.tasks : [])
        .filter((t) => typeof t.body === 'string' && t.body.trim() !== '')
        .map((t) => ({ body: t.body.trim() })),
    }))
}

/**
 * @param {Record<string, unknown> | null | undefined} p
 */
export function budgetRowsFromProject(p) {
  const items = Array.isArray(p?.budget_items) ? p.budget_items : []
  return items.map((r) => ({
    name: r.name || '',
    description: r.description || '',
    unit_cost: r.unit_cost != null ? String(r.unit_cost) : '',
    units: r.units != null ? String(r.units) : '1',
  }))
}

/**
 * @param {Record<string, unknown> | null | undefined} p
 */
export function jobPositionsFromProject(p) {
  const positions = Array.isArray(p?.job_positions) ? p.job_positions : []
  return positions.map((pos) => ({
    title: pos.title || '',
    tasks: (Array.isArray(pos.tasks) ? pos.tasks : []).map((t) => ({ body: t.body || '' })),
  }))
}

/**
 * @param {string | null | undefined} iso
 */
export function deadlineToInputValue(iso) {
  if (!iso || typeof iso !== 'string') return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/**
 * @param {string} localValue
 */
export function deadlineFromInputValue(localValue) {
  if (!localValue || typeof localValue !== 'string') return undefined
  const d = new Date(localValue)
  if (Number.isNaN(d.getTime())) return undefined
  return d.toISOString()
}

/**
 * @param {string | null | undefined} iso
 */
export function formatDeadline(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleString()
}
