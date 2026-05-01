import { sessionUser } from './useSession'

/**
 * @param {string} capabilityId
 * @returns {boolean}
 */
export function hasCapability(capabilityId) {
  const user = sessionUser.value
  if (user?.is_root) {
    return true
  }
  const caps = user?.capabilities
  if (!Array.isArray(caps)) {
    return false
  }
  return caps.includes(capabilityId)
}

/**
 * Who may open the member directory and related admin flows (aligned with `users.view` on the server).
 * @returns {boolean}
 */
export function isCommunityAdministrator() {
  return hasCapability('users.view')
}

/**
 * Where the member profile "back" action should go: global users list, membership hub, or dashboard.
 * @returns {{ name: string, params?: Record<string, string> }}
 */
export function memberProfileBackRoute() {
  const user = sessionUser.value
  if (!user) {
    return { name: 'dashboard' }
  }
  if (hasCapability('users.view')) {
    return { name: 'users' }
  }
  if (hasCapability('community.memberships.manage')) {
    const list = Array.isArray(user.communities) ? user.communities : []
    const adminRow = list.find(
      (c) => c && c.role === 'admin' && typeof c.slug === 'string' && c.slug.trim() !== '',
    )
    if (adminRow) {
      return { name: 'communityMemberships', params: { slug: String(adminRow.slug).trim() } }
    }
  }
  return { name: 'dashboard' }
}

export function isVisitorUser() {
  const user = sessionUser.value
  if (!user || user.is_root) return false
  const activeId = user.active_community_id
  const memberships = Array.isArray(user.communities) ? user.communities : []
  const active = memberships.find((row) => Number(row?.id) === Number(activeId))
  if (active && typeof active.role === 'string') {
    return active.role === 'visitor'
  }
  return Boolean(user.user_type === 'visitor')
}
