import { onBeforeUnmount, ref, watch } from 'vue'
import { getChatEcho } from './useChatRealtime.js'

/**
 * Live order updates use Laravel Echo + WebSockets (Reverb/Pusher).
 * Chat uses SSE instead; if WebSockets are unavailable here, add polling or an SSE stream for `PlaceOrdersChanged`.
 * @param {import('vue').Ref<number|string|null|undefined>} placeIdRef
 * @param {() => void} onRefresh
 */
export function usePlaceOrdersRealtime(placeIdRef, onRefresh) {
  const connected = ref(false)
  /** @type {string|null} */
  let channelName = null

  /**
   * @param {number} placeId
   */
  function join(placeId) {
    try {
      const e = getChatEcho()
      if (!e) {
        connected.value = false
        return
      }
      channelName = `place.${placeId}`
      e.private(channelName)
        .listen('.orders.changed', () => {
          onRefresh?.()
        })
        .subscribed(() => {
          connected.value = true
        })
    } catch {
      connected.value = false
    }
  }

  function leave() {
    if (!channelName) {
      return
    }
    try {
      const e = getChatEcho()
      e?.leave(channelName)
    } catch {
      /* Echo may be unavailable */
    }
    channelName = null
    connected.value = false
  }

  watch(
    placeIdRef,
    (next) => {
      leave()
      const id = next != null && next !== '' ? Number(next) : 0
      if (Number.isFinite(id) && id > 0) {
        join(id)
      }
    },
    { immediate: true },
  )

  onBeforeUnmount(() => {
    leave()
  })

  return { connected }
}
