import { hasCapability, isVisitorUser } from '../composables/useCapabilities'
import { sessionStatus, sessionUser } from '../composables/useSession'

/**
 * @param {string} slug
 * @returns {{ role: string } | null}
 */
export function membershipForHostSlug(slug) {
  const s = typeof slug === 'string' ? slug.trim() : ''
  if (!s) return null
  const u = sessionUser.value
  const list = Array.isArray(u?.communities) ? u.communities : []
  const row = list.find((c) => c && String(c.slug || '').trim() === s)
  if (!row) return null
  return { role: String(row.role || 'member') }
}

/**
 * @param {string} slug
 */
export function isHostCommunityMember(slug) {
  const row = membershipForHostSlug(slug)
  if (!row) return false
  return row.role !== 'visitor'
}

/**
 * @param {string} slug
 */
export function canManageHostCommunity(slug) {
  const u = sessionUser.value
  if (u?.is_root) return true
  if (hasCapability('community.memberships.manage')) {
    const row = membershipForHostSlug(slug)
    return row?.role === 'admin'
  }
  return false
}

/**
 * Nav items for SPA served on a community custom domain.
 * @param {string} slug
 * @returns {Array<{ key: string, to: import('vue-router').RouteLocationRaw, icon: string, labelKey: string }>}
 */
export function communityHostNavDefs(slug) {
  const s = typeof slug === 'string' ? slug.trim() : ''
  if (!s) return []

  const authenticated = sessionStatus.value === 'authenticated'
  const member = isHostCommunityMember(s)
  const visitor = isVisitorUser()
  const admin = canManageHostCommunity(s)

  /** @type {Array<{ key: string, to: import('vue-router').RouteLocationRaw, icon: string, labelKey: string, show?: boolean }>} */
  const defs = [
    {
      key: 'hub',
      to: { name: 'communityMicrosite', params: { slug: s } },
      icon: 'people-roof',
      labelKey: 'communityHostNav.hub',
      show: true,
    },
    {
      key: 'dashboard',
      to: { name: 'dashboardScoped', params: { communitySlug: s } },
      icon: 'gauge-high',
      labelKey: 'nav.dashboard',
      show: authenticated && (member || visitor),
    },
    {
      key: 'projects',
      to: { name: 'communityProjects', params: { slug: s } },
      icon: 'list-check',
      labelKey: 'communityProjects.tabProjects',
      show: member,
    },
    {
      key: 'credits',
      to: { name: 'communityCredits', params: { slug: s } },
      icon: 'file-lines',
      labelKey: 'communityMicrosite.creditsTitle',
      show: true,
    },
    {
      key: 'wallet',
      to: { name: 'walletScoped', params: { communitySlug: s } },
      icon: 'arrow-right-arrow-left',
      labelKey: 'nav.myWallet',
      show: member && hasCapability('wallet.view'),
    },
    {
      key: 'memberships',
      to: { name: 'communityMemberships', params: { slug: s } },
      icon: 'users',
      labelKey: 'communityMicrosite.manageMemberships',
      show: admin,
    },
    {
      key: 'community-settings',
      to: { name: 'communitySettingsBySlug', params: { slug: s } },
      icon: 'gear',
      labelKey: 'nav.community',
      show: admin || sessionUser.value?.is_root,
    },
    {
      key: 'orders',
      to: { name: 'orders' },
      icon: 'file-lines',
      labelKey: 'nav.orders',
      show: authenticated && (visitor || member),
    },
    {
      key: 'cart',
      to: { name: 'cart' },
      icon: 'cart-shopping',
      labelKey: 'nav.myCart',
      show: authenticated && (visitor || member),
    },
    {
      key: 'profile',
      to: { name: 'profile' },
      icon: 'user',
      labelKey: 'quickNav.profile',
      show: authenticated,
    },
    {
      key: 'settings',
      to: { name: 'settings' },
      icon: 'gear',
      labelKey: 'nav.settings',
      show: authenticated,
    },
  ]

  return defs.filter((d) => d.show !== false)
}

/** Route names reachable on a community custom domain. */
export const COMMUNITY_HOST_ALLOWED_ROUTE_NAMES = new Set([
  'communityMicrosite',
  'dashboardScoped',
  'dashboardScopedLegacy',
  'communityProjects',
  'communityProjectDetail',
  'communityCredits',
  'communityMemberships',
  'communitySettings',
  'communitySettingsScoped',
  'communitySettingsBySlug',
  'walletScoped',
  'walletSendScoped',
  'walletMovementScoped',
  'placePublic',
  'placeView',
  'placeOfferCreate',
  'placeOfferEdit',
  'profile',
  'settings',
  'login',
  'visitorAuthConsume',
  'forgotPassword',
  'resetPassword',
  'cart',
  'orders',
  'orderDetail',
  'communityLegalPublic',
  'joinInvitation',
  'joinInvitationVerify',
  'tableAccess',
])
