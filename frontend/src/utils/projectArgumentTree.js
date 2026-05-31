/**
 * @param {Array<{ id: number, parent_id: number | null, sort_order?: number }>} args
 * @returns {Map<string | number, Array<Record<string, unknown>>>}
 */
export function buildChildrenMap(args) {
  const map = new Map()
  for (const a of args) {
    const k = a.parent_id == null ? '__root' : a.parent_id
    if (!map.has(k)) map.set(k, [])
    map.get(k).push(a)
  }
  for (const list of map.values()) {
    list.sort((x, y) => (Number(x.sort_order || 0) - Number(y.sort_order || 0)) || Number(x.id) - Number(y.id))
  }
  return map
}

/**
 * @param {number} id
 * @param {Map<string | number, Array<{ id: number }>>} childrenMap
 * @param {Map<number, number>} [memo]
 * @returns {number}
 */
export function subtreeWeight(id, childrenMap, memo = new Map()) {
  if (memo.has(id)) return memo.get(id)
  const kids = childrenMap.get(id) || []
  let w = 1
  for (const c of kids) {
    w += subtreeWeight(c.id, childrenMap, memo)
  }
  memo.set(id, w)
  return w
}
