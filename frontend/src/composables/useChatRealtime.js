import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

let echo

function realtimeDisabled() {
  return import.meta.env.VITE_REALTIME_ENABLED === 'false'
}

function resolveAuthEndpoint() {
  const base = import.meta.env.VITE_API_BASE_URL || 'http://localhost:9122'
  return `${base.replace(/\/$/, '')}/broadcasting/auth`
}

/**
 * Echo/Pusher client for non-chat features (e.g. place order updates). Chat uses SSE.
 * Set VITE_REALTIME_ENABLED=false when Reverb/WebSocket is not deployed to avoid connection errors.
 */
export function getChatEcho() {
  if (realtimeDisabled()) {
    return null
  }
  if (!echo) {
    echo = new Echo({
      broadcaster: 'pusher',
      key: import.meta.env.VITE_PUSHER_APP_KEY || 'pluribus-key',
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
