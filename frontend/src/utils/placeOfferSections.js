/**
 * Build storefront-style sections from offers when at least one has a category.
 *
 * @param {unknown[]|null|undefined} offers
 * @param {string} otherCategoryLabel Label for offers with no category when grouping is active
 * @returns {{ key: string, title: string | null, offers: unknown[] }[]}
 */
export function buildPlaceOfferSections(offers, otherCategoryLabel) {
  const list = Array.isArray(offers) ? offers : []

  /** @param {unknown} o */
  function trimmedCategory(o) {
    if (!o || typeof o !== 'object' || !('category' in o)) {
      return ''
    }
    const c = /** @type {{ category?: unknown }} */ (o).category
    return typeof c === 'string' ? c.trim() : ''
  }

  const hasAny = list.some((o) => trimmedCategory(o) !== '')
  if (!hasAny) {
    return [{ key: 'all', title: null, offers: list }]
  }

  /** @type {Map<string, unknown[]>} */
  const grouped = new Map()
  const uncategorized = []
  for (const o of list) {
    const cat = trimmedCategory(o)
    if (cat === '') {
      uncategorized.push(o)
    } else {
      if (!grouped.has(cat)) {
        grouped.set(cat, [])
      }
      grouped.get(cat)?.push(o)
    }
  }

  const keys = [...grouped.keys()].sort((a, b) => a.localeCompare(b))
  const out = keys.map((k) => ({ key: k, title: k, offers: grouped.get(k) ?? [] }))
  if (uncategorized.length) {
    out.push({ key: '__other__', title: otherCategoryLabel, offers: uncategorized })
  }
  return out
}
