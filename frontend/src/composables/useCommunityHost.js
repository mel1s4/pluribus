import { computed, ref } from 'vue'
import { apiJson } from '../services/api.js'

/** @type {import('vue').Ref<'unknown' | 'platform' | 'community'>} */
export const communityHostMode = ref('unknown')

/** @type {import('vue').Ref<string | null>} */
export const communityHostSlug = ref(null)

/** @type {import('vue').Ref<string | null>} */
export const communityHostName = ref(null)

export const isCommunityHostSite = computed(() => communityHostMode.value === 'community')

const PLATFORM_HOSTS = new Set([
  'localhost',
  '127.0.0.1',
  'pluribus.vzs.mx',
  'www.pluribus.vzs.mx',
  'chante.vzs.mx',
  'www.chante.vzs.mx',
])

function normalizeHost(hostname) {
  if (typeof hostname !== 'string') {
    return ''
  }
  let host = hostname.trim().toLowerCase()
  if (host.startsWith('www.')) {
    host = host.slice(4)
  }
  return host
}

function isLikelyPlatformHost(hostname) {
  const host = normalizeHost(hostname)
  if (!host) {
    return true
  }
  if (PLATFORM_HOSTS.has(host)) {
    return true
  }
  return host.endsWith('.localhost')
}

/**
 * Resolve whether this SPA host is a community custom domain.
 * @returns {Promise<void>}
 */
export async function resolveCommunityHost() {
  if (typeof window === 'undefined') {
    communityHostMode.value = 'platform'
    communityHostSlug.value = null
    communityHostName.value = null
    return
  }

  const pageHost = normalizeHost(window.location.hostname)
  if (isLikelyPlatformHost(pageHost)) {
    communityHostMode.value = 'platform'
    communityHostSlug.value = null
    communityHostName.value = null
    return
  }

  const { ok, status, data } = await apiJson('GET', '/api/community/resolve-host')
  if (!ok) {
    communityHostMode.value = status === 404 ? 'platform' : 'platform'
    communityHostSlug.value = null
    communityHostName.value = null
    if (status === 404) {
      communityHostMode.value = 'platform'
    }
    return
  }

  if (data?.mode === 'community' && data.community && typeof data.community === 'object') {
    const slug = typeof data.community.slug === 'string' ? data.community.slug.trim() : ''
    const name = typeof data.community.name === 'string' ? data.community.name.trim() : ''
    communityHostMode.value = 'community'
    communityHostSlug.value = slug || null
    communityHostName.value = name || null
    return
  }

  communityHostMode.value = 'platform'
  communityHostSlug.value = null
  communityHostName.value = null
}

export function communityHostRequestHeaders() {
  const slug = communityHostSlug.value
  if (typeof slug === 'string' && slug.trim() !== '') {
    return { 'X-Community-Slug': slug.trim() }
  }
  return {}
}
