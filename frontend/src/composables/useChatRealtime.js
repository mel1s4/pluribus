import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { onBeforeUnmount, ref, watch } from 'vue'

window.Pusher = Pusher

let echo

function resolveAuthEndpoint() {
  const base = import.meta.env.VITE_API_BASE_URL || 'http://localhost:9122'
  return `${base.replace(/\/$/, '')}/broadcasting/auth`
}

function getEcho() {
  if (!echo) {
    echo = new Echo({
      broadcaster: 'pusher',
      key: import.meta.env.VITE_PUSHER_APP_KEY || 'pluribus-key',
      // pusher-js 8+ requires cluster even when wsHost points at Reverb / self-hosted.
      cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
      wsHost: import.meta.env.VITE_WS_HOST || '127.0.0.1',
      wsPort: Number(import.meta.env.VITE_WS_PORT || 6001),
      wssPort: Number(import.meta.env.VITE_WS_PORT || 6001),
      forceTLS: import.meta.env.VITE_WS_SCHEME === 'wss',
      enabledTransports: ['ws', 'wss'],
      disableStats: true,
      authEndpoint: resolveAuthEndpoint(),
      auth: {
        headers: {
          Accept: 'application/json',
        },
      },
    })
  }
  return echo
}

export function useChatRealtime(chatIdRef, onMessage) {
  const connected = ref(false)
  let channelName = null

  function join(chatId) {
    if (!chatId) return
    const e = getEcho()
    channelName = `private-chat.${chatId}`
    e.private(`chat.${chatId}`)
      .listen('.message.sent', (event) => {
        if (event && event.message) {
          onMessage(event.message)
        }
      })
      .subscribed(() => {
        connected.value = true
      })
  }

  function leave() {
    if (!channelName) return
    getEcho().leave(channelName)
    channelName = null
    connected.value = false
  }

  watch(chatIdRef, (next, prev) => {
    if (prev) leave()
    if (next) join(next)
  }, { immediate: true })

  onBeforeUnmount(() => {
    leave()
  })

  return { connected }
}
