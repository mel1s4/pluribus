import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { withCommunityPath as buildCommunityPath } from '../utils/communityPath'

export function useActiveCommunity() {
  const route = useRoute()

  const activeCommunitySlug = computed(() => {
    const value = route.params.communitySlug
    if (typeof value === 'string' && value.trim() !== '') {
      return value.trim()
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
