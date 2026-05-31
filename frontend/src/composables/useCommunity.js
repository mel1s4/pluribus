import { computed, ref } from 'vue'
import { cachedGet } from '../services/cachedApi.js'
import { language, t } from '../i18n/i18n'

export const communityName = ref(null)
export const communityLogoUrl = ref(null)
export const communityDefaultLanguage = ref(null)
/** @type {import('vue').Ref<string | null>} */
export const communityCurrencyCode = ref(null)
/** @type {import('vue').Ref<string | null>} */
export const communityCurrencyName = ref(null)
/** @type {import('vue').Ref<string | null>} */
export const communityLocalCurrencyCode = ref(null)
/** @type {import('vue').Ref<number | null>} */
export const communityLatitude = ref(null)
/** @type {import('vue').Ref<number | null>} */
export const communityLongitude = ref(null)

export function useCommunity() {
  const displayName = computed(() => {
    void language.value
    const n = communityName.value
    if (typeof n === 'string' && n.trim().length) {
      return n.trim()
    }
    return t('nav.logo')
  })

  return {
    communityName,
    communityLogoUrl,
    communityDefaultLanguage,
    communityCurrencyCode,
    communityCurrencyName,
    communityLocalCurrencyCode,
    communityLatitude,
    communityLongitude,
    displayName,
    fetchCommunityBranding,
  }
}

/**
 * @param {string | null | undefined} activeCommunitySlug Optional slug from scoped route or microsite (X-Community-Slug).
 */
export async function fetchCommunityBranding(activeCommunitySlug) {
  const slug =
    typeof activeCommunitySlug === 'string' && activeCommunitySlug.trim() !== ''
      ? activeCommunitySlug.trim()
      : ''
  const requestOpts = slug !== '' ? { headers: { 'X-Community-Slug': slug } } : {}
  const { ok, data } = await cachedGet('/api/community/branding', requestOpts)
  if (!ok || !data || typeof data !== 'object' || !data.community) {
    communityName.value = null
    communityLogoUrl.value = null
    communityDefaultLanguage.value = null
    communityCurrencyCode.value = null
    communityCurrencyName.value = null
    communityLocalCurrencyCode.value = null
    communityLatitude.value = null
    communityLongitude.value = null
    return
  }
  const c = data.community
  const name = typeof c.name === 'string' ? c.name.trim() : ''
  communityName.value = name.length ? name : null
  const logo = c.logo_url
  communityLogoUrl.value = typeof logo === 'string' && logo.length ? logo : null
  const defaultLanguage = c.default_language
  communityDefaultLanguage.value = typeof defaultLanguage === 'string' ? defaultLanguage : null
  const cur = c.currency_code
  communityCurrencyCode.value = typeof cur === 'string' && cur.trim().length ? cur.trim() : null
  const cname = c.currency_name
  communityCurrencyName.value = typeof cname === 'string' && cname.trim().length ? cname.trim() : null
  const localCur = c.local_currency_code
  communityLocalCurrencyCode.value = typeof localCur === 'string' && localCur.trim().length ? localCur.trim() : null
  const lat = c.latitude
  const lng = c.longitude
  communityLatitude.value = typeof lat === 'number' && Number.isFinite(lat) ? lat : null
  communityLongitude.value = typeof lng === 'number' && Number.isFinite(lng) ? lng : null
}
