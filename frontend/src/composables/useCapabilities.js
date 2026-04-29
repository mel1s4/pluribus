import { sessionUser } from './useSession'

/**
 * Community admins and root may access administrator-only navigation (e.g. Members list).
 * @returns {boolean}
 */
export function isCommunityAdministrator() {
  const user = sessionUser.value
  if (!user) {
    return false
  }
  if (user.is_root) {
    return true
  }
  return user.user_type === 'admin'
}

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

export function isVisitorUser() {
  const user = sessionUser.value
  return Boolean(user && user.user_type === 'visitor' && !user.is_root)
}
