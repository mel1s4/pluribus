import { hasCapability, isCommunityAdministrator } from '../composables/useCapabilities'

/**
 * Single source for authenticated app sidebar destinations (keys match backend UserFavoriteController).
 * @type {Array<{ key: string, to: string, labelKey: string, icon: string, capability: string | null, requiresCommunityAdmin?: boolean }>}
 */
export const SIDEBAR_LINK_DEFS = [
  { key: 'dashboard', to: '/dashboard', labelKey: 'nav.dashboard', icon: 'gauge-high', capability: null },
  {
    key: 'users',
    to: '/users',
    labelKey: 'nav.users',
    icon: 'users',
    capability: null,
    requiresCommunityAdmin: true,
  },
  { key: 'community-settings', to: '/community', labelKey: 'nav.community', icon: 'people-roof', capability: null },
  { key: 'chats', to: '/chats', labelKey: 'quickNav.chats', icon: 'comments', capability: null },
  { key: 'folders', to: '/folders', labelKey: 'folders.title', icon: 'folder-open', capability: null },
  { key: 'tasks', to: '/tasks', labelKey: 'tasks.title', icon: 'list-check', capability: null },
  { key: 'calendar', to: '/calendar', labelKey: 'calendar.title', icon: 'calendar-days', capability: null },
  { key: 'posts', to: '/posts', labelKey: 'posts.title', icon: 'newspaper', capability: null },
  { key: 'my-groups', to: '/my-groups', labelKey: 'groups.title', icon: 'people-group', capability: null },
  { key: 'orders', to: '/orders', labelKey: 'nav.orders', icon: 'file-lines', capability: null },
  { key: 'my-places', to: '/my-places', labelKey: 'nav.myPlaces', icon: 'store', capability: null },
  { key: 'map', to: '/map', labelKey: 'quickNav.map', icon: 'map-location-dot', capability: null },
  { key: 'notifications', to: '/notifications', labelKey: 'quickNav.notifications', icon: 'bell', capability: null },
  { key: 'profile', to: '/profile', labelKey: 'quickNav.profile', icon: 'user', capability: null },
  { key: 'settings', to: '/settings', labelKey: 'nav.settings', icon: 'gear', capability: null },
]

/** @param {string} key */
export function isSidebarNavKey(key) {
  return SIDEBAR_LINK_DEFS.some((l) => l.key === key)
}

/** @param {string} key */
export function isSidebarRouteKeyAccessible(key) {
  const def = sidebarDefByKey(key)
  return def != null && isSidebarLinkDefAccessible(def)
}

/** @param {string} key */
export function sidebarDefByKey(key) {
  return SIDEBAR_LINK_DEFS.find((l) => l.key === key)
}

/**
 * @param {typeof SIDEBAR_LINK_DEFS[number]} def
 */
export function isSidebarLinkDefAccessible(def) {
  if (def.requiresCommunityAdmin && !isCommunityAdministrator()) {
    return false
  }
  if (def.capability && !hasCapability(def.capability)) {
    return false
  }
  return true
}
