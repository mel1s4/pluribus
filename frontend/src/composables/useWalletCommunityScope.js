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
    const list = memberships.value
    if (!list.length) {
      return ''
    }
    const active = Number(sessionUser.value?.active_community_id || 0)
    if (active > 0 && list.some((c) => c && Number(c.id) === active)) {
      return String(active)
    }
    const first = list[0]
    return first && first.id != null ? String(first.id) : ''
  }

  watch(
    sessionUser,
    () => {
      if (!memberships.value.length) {
        communityScope.value = ''
        return
      }
      const validIds = new Set(
        memberships.value
          .map((c) => (c && c.id != null ? Number(c.id) : 0))
          .filter((id) => id > 0),
      )
      const current = Number(communityScope.value || 0)
      if (!communityScope.value || !validIds.has(current)) {
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
