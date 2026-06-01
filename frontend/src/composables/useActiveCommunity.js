import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { communityHostSlug, isCommunityHostSite } from './useCommunityHost'
import { withCommunityPath as buildCommunityPath } from '../utils/communityPath'

export function useActiveCommunity() {
  const route = useRoute()

  const activeCommunitySlug = computed(() => {
    if (isCommunityHostSite.value) {
      const hostSlug = communityHostSlug.value
      if (typeof hostSlug === 'string' && hostSlug.trim() !== '') {
        return hostSlug.trim()
      }
    }
    const value = route.params.communitySlug
    if (typeof value === 'string' && value.trim() !== '') {
      return value.trim()
    }
    if (String(route.name || '') === 'communitySettingsBySlug') {
      const slug = route.params.slug
      if (typeof slug === 'string' && slug.trim() !== '') {
        return slug.trim()
      }
    }
    return null
  })

  function withCommunityPath(path) {
    return buildCommunityPath(path, activeCommunitySlug.value)
  }

  return {
    activeCommunitySlug,
    withCommunityPath,
  }
}
