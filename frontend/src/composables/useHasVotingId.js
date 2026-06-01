import { computed } from 'vue'
import { sessionUser } from './useSession'

export function useHasVotingId() {
  return computed(() => /^[0-9]{6}$/.test(String(sessionUser.value?.voting_id ?? '')))
}
