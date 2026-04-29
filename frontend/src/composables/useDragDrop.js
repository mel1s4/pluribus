import { ref } from 'vue'

const DEFAULT_MIME = 'application/x-pluribus-folder-dnd'

/**
 * @param {{ mimeType?: string }} [options]
 */
export function useDragDrop(options = {}) {
  const mimeType = options.mimeType || DEFAULT_MIME
  /** @type {import('vue').Ref<string|null>} */
  const dragOverTargetId = ref(null)
  /** @type {import('vue').Ref<boolean>} */
  const isDragging = ref(false)

  /**
   * @param {DragEvent} e
   * @param {{ type: 'chat'|'task'|'folder', id: number|string }} payload
   */
  function onDragStart(e, payload) {
    if (!e.dataTransfer) return
    isDragging.value = true
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData(mimeType, JSON.stringify(payload))
    e.dataTransfer.setData('text/plain', `${payload.type}:${payload.id}`)
  }

  function onDragEnd() {
    isDragging.value = false
    dragOverTargetId.value = null
  }

  /**
   * @param {DragEvent} e
   * @param {string|number|null} targetFolderId null = unfilled/root drop zone
   */
  function onDragOverFolder(e, targetFolderId) {
    e.preventDefault()
    if (e.dataTransfer) e.dataTransfer.dropEffect = 'move'
    dragOverTargetId.value =
      targetFolderId === null || targetFolderId === 'root' ? 'root' : String(targetFolderId)
  }

  function onDragLeaveFolder(e, targetFolderId) {
    const key =
      targetFolderId === null || targetFolderId === 'root' ? 'root' : String(targetFolderId)
    if (dragOverTargetId.value === key) {
      const related = e.relatedTarget
      if (related && e.currentTarget instanceof Node && e.currentTarget.contains(related)) return
      dragOverTargetId.value = null
    }
  }

  /**
   * @param {DragEvent} e
   * @returns {{ type: string, id: number }|null}
   */
  function readPayload(e) {
    const raw = e.dataTransfer?.getData(mimeType)
    if (!raw) {
      const plain = e.dataTransfer?.getData('text/plain')
      if (plain && /^[a-z]+:\d+$/i.test(plain)) {
        const [type, id] = plain.split(':')
        return { type, id: Number(id) }
      }
      return null
    }
    try {
      const o = JSON.parse(raw)
      if (o && o.type && o.id != null) return { type: String(o.type), id: Number(o.id) }
    } catch {
      /* ignore */
    }
    return null
  }

  return {
    mimeType,
    dragOverTargetId,
    isDragging,
    onDragStart,
    onDragEnd,
    onDragOverFolder,
    onDragLeaveFolder,
    readPayload,
  }
}
