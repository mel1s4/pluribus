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

export function isVisitorUser() {
  const user = sessionUser.value
  return Boolean(user && user.user_type === 'visitor' && !user.is_root)
}
