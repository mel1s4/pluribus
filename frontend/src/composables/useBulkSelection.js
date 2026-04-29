import { computed, ref } from 'vue'

/**
 * @param {{ getOrderedIds?: () => Array<string> }} [options]
 */
export function useBulkSelection(options = {}) {
  /** @type {import('vue').Ref<Set<string>>} */
  const selectedKeys = ref(new Set())
  /** @type {import('vue').Ref<string|null>} */
  const lastClickedKey = ref(null)

  const count = computed(() => selectedKeys.value.size)

  function keyFor(type, id) {
    return `${type}:${Number(id)}`
  }

  function parseKey(key) {
    const i = key.indexOf(':')
    if (i === -1) return null
    return { type: key.slice(0, i), id: Number(key.slice(i + 1)) }
  }

  function isSelected(type, id) {
    return selectedKeys.value.has(keyFor(type, id))
  }

  function toggle(type, id, opts = {}) {
    const k = keyFor(type, id)
    const next = new Set(selectedKeys.value)
    if (opts.shift && options.getOrderedIds) {
      const ordered = options.getOrderedIds()
      const last = lastClickedKey.value
      if (last && ordered.length) {
        const a = ordered.indexOf(last)
        const b = ordered.indexOf(k)
        if (a !== -1 && b !== -1) {
          const [lo, hi] = a < b ? [a, b] : [b, a]
          for (let i = lo; i <= hi; i++) next.add(ordered[i])
          selectedKeys.value = next
          return
        }
      }
    }
    if (next.has(k)) next.delete(k)
    else next.add(k)
    selectedKeys.value = next
    lastClickedKey.value = k
  }

  function select(type, id) {
    const next = new Set(selectedKeys.value)
    next.add(keyFor(type, id))
    selectedKeys.value = next
    lastClickedKey.value = keyFor(type, id)
  }

  function deselect(type, id) {
    const next = new Set(selectedKeys.value)
    next.delete(keyFor(type, id))
    selectedKeys.value = next
  }

  function selectAllFromKeys(keys) {
    selectedKeys.value = new Set(keys)
  }

  function clear() {
    selectedKeys.value = new Set()
    lastClickedKey.value = null
  }

  const items = computed(() =>
    [...selectedKeys.value].map((k) => parseKey(k)).filter(Boolean),
  )

  return {
    selectedKeys,
    count,
    items,
    keyFor,
    isSelected,
    toggle,
    select,
    deselect,
    selectAllFromKeys,
    clear,
    lastClickedKey,
  }
}
