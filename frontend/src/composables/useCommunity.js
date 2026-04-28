import { computed, ref } from 'vue'
import { cachedGet } from '../services/cachedApi.js'
import { language, t } from '../i18n/i18n'

export const communityName = ref(null)
export const communityLogoUrl = ref(null)
export const communityDefaultLanguage = ref(null)
/** @type {import('vue').Ref<string | null>} */
export const communityCurrencyCode = ref(null)

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
    displayName,
    fetchCommunityBranding,
  }
}

export async function fetchCommunityBranding() {
  const { ok, data } = await cachedGet('/api/community/branding')
  if (!ok || !data || typeof data !== 'object' || !data.community) {
    communityName.value = null
    communityLogoUrl.value = null
    communityDefaultLanguage.value = null
    communityCurrencyCode.value = null
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
}
