import { ref } from 'vue'
import { hasCapability } from './useCapabilities.js'
import { sessionStatus } from './useSession.js'
import { fetchNotificationsIndex } from '../services/notificationsApi.js'

/** In-app notification bell badge (distinct from chat unread). */
export const inAppNotificationsUnread = ref(0)

let pollTimer = null

export async function refreshInAppNotificationsUnread() {
  if (sessionStatus.value !== 'authenticated' || !hasCapability('notifications.view')) {
    inAppNotificationsUnread.value = 0
    return
  }
  const res = await fetchNotificationsIndex({ page: 1, perPage: 1, unread: true })
  if (!res.ok || !res.data || typeof res.data !== 'object') {
    return
  }
  const total = res.data.meta && typeof res.data.meta.total === 'number' ? res.data.meta.total : 0
  inAppNotificationsUnread.value = Math.max(0, Math.floor(total))
}

export function startInAppNotificationsPolling() {
  if (pollTimer != null) {
    return
  }
  void refreshInAppNotificationsUnread()
  pollTimer = setInterval(() => {
    void refreshInAppNotificationsUnread()
  }, 90000)
}

export function stopInAppNotificationsPolling() {
  if (pollTimer != null) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}
