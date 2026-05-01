import { ref } from 'vue'

const permission = ref(typeof Notification === 'undefined' ? 'unsupported' : Notification.permission)

/** Sync ref from the browser without prompting (safe on page load). */
function syncNotificationPermission() {
  if (typeof Notification === 'undefined') {
    permission.value = 'unsupported'
    return permission.value
  }
  permission.value = Notification.permission
  return permission.value
}

/**
 * Must run from a short user-generated event (click/tap) or browsers may reject it.
 * Use syncNotificationPermission on init instead.
 */
async function requestPermission() {
  if (typeof Notification === 'undefined') return permission.value
  if (Notification.permission === 'granted') {
    permission.value = 'granted'
    return permission.value
  }
  if (Notification.permission === 'denied') {
    permission.value = 'denied'
    return permission.value
  }
  try {
    permission.value = await Notification.requestPermission()
  } catch {
    permission.value = typeof Notification !== 'undefined' ? Notification.permission : 'unsupported'
  }
  return permission.value
}

function notify({ title, body, tag }) {
  if (typeof Notification === 'undefined') return
  if (document.visibilityState === 'visible') return
  if (Notification.permission !== 'granted') return
  try {
    const notification = new Notification(title, {
      body,
      tag: tag || undefined,
      silent: true,
    })
    notification.onclick = () => window.focus()
  } catch {
    // Ignore Notification API failures.
  }
}

export function useMessageNotifications() {
  return {
    permission,
    syncNotificationPermission,
    requestPermission,
    notify,
  }
}
