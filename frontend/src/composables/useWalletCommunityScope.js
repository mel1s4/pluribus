import { computed, ref, watch } from 'vue'
import { sessionUser } from './useSession'

/**
 * Active community for wallet flows (matches session + multi-community picker).
 */
export function useWalletCommunityScope() {
  const memberships = computed(() =>
    Array.isArray(sessionUser.value?.communities) ? sessionUser.value.communities : [],
  )
  const showCommunityPicker = computed(() => memberships.value.length > 1)

  const communityScope = ref('')

  function defaultCommunityId() {
    const active = Number(sessionUser.value?.active_community_id || 0)
    if (active > 0) return String(active)
    const first = memberships.value[0]
    return first && first.id != null ? String(first.id) : ''
  }

  watch(
    sessionUser,
    () => {
      if (!communityScope.value && memberships.value.length) {
        communityScope.value = defaultCommunityId()
      }
    },
    { immediate: true },
  )

  const communityIdNum = computed(() => Number(communityScope.value || 0))

  return {
    memberships,
    showCommunityPicker,
    communityScope,
    communityIdNum,
  }
}
