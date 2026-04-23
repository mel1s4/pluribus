import { computed, ref } from 'vue'
import { apiJson } from '../services/api'
import { language, t } from '../i18n/i18n'

export const communityName = ref(null)
export const communityLogoUrl = ref(null)

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
    displayName,
    fetchCommunityBranding,
  }
}

export async function fetchCommunityBranding() {
  const { ok, data } = await apiJson('GET', '/api/community/branding')
  if (!ok || !data || typeof data !== 'object' || !data.community) {
    communityName.value = null
    communityLogoUrl.value = null
    return
  }
  const c = data.community
  const name = typeof c.name === 'string' ? c.name.trim() : ''
  communityName.value = name.length ? name : null
  const logo = c.logo_url
  communityLogoUrl.value = typeof logo === 'string' && logo.length ? logo : null
}
