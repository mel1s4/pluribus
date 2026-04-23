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
