import { onBeforeUnmount, reactive, ref, watch } from 'vue'
import { fetchGroups, fetchGroupMembers } from '../services/contentApi.js'
import { searchUsers } from '../services/usersApi.js'

const MANUAL = 'manual'

function groupTag(groupId) {
  return `g:${Number(groupId)}`
}

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

/**
 * Member search + selection for new chat dialogs, including "add everyone from these groups".
 *
 * @param {{ unknownUserLabel?: string }} [options]
 */
export function useChatMemberPicker(options = {}) {
  const unknownUserLabel = options.unknownUserLabel || 'Member'

  const memberSearchQuery = ref('')
  const memberSearchResults = ref([])
  const memberSearchLoading = ref(false)
  const selectedMembers = ref([])
  /** @type {Record<number, string[]>} */
  const memberSources = reactive({})
  const selectedGroupIds = ref([])
  const groups = ref([])
  const groupsLoading = ref(false)
  const groupMembersLoadingId = ref(null)

  let memberSearchTimer = null

  function clearMemberSources() {
    for (const k of Object.keys(memberSources)) {
      delete memberSources[k]
    }
  }

  function reset() {
    clearMemberSearchTimer()
    memberSearchQuery.value = ''
    memberSearchResults.value = []
    memberSearchLoading.value = false
    selectedMembers.value = []
    clearMemberSources()
    selectedGroupIds.value = []
    groupMembersLoadingId.value = null
  }

  function syncGroupIdsFromSources() {
    const gids = new Set()
    for (const uid of Object.keys(memberSources)) {
      const tags = memberSources[Number(uid)]
      if (!Array.isArray(tags)) continue
      for (const tag of tags) {
        if (typeof tag === 'string' && tag.startsWith('g:')) {
          const n = Number(tag.slice(2))
          if (Number.isFinite(n)) gids.add(n)
        }
      }
    }
    selectedGroupIds.value = [...gids]
  }

  function ensureSources(userId) {
    const id = Number(userId)
    if (!memberSources[id]) memberSources[id] = []
    return memberSources[id]
  }

  function addSource(userId, tag) {
    const arr = ensureSources(userId)
    if (!arr.includes(tag)) arr.push(tag)
  }

  function isSelectedMember(memberId) {
    return selectedMembers.value.some((member) => Number(member.id) === Number(memberId))
  }

  function toggleMember(member) {
    const id = Number(member.id)
    if (!Number.isFinite(id)) return
    if (isSelectedMember(id)) {
      selectedMembers.value = selectedMembers.value.filter((item) => Number(item.id) !== id)
      delete memberSources[id]
      syncGroupIdsFromSources()
      return
    }
    addSource(id, MANUAL)
    selectedMembers.value = [
      ...selectedMembers.value,
      {
        id,
        name: member.name || unknownUserLabel,
        email: member.email || '',
      },
    ]
  }

  function removeSelectedMember(memberId) {
    const id = Number(memberId)
    selectedMembers.value = selectedMembers.value.filter((item) => Number(item.id) !== id)
    delete memberSources[id]
    syncGroupIdsFromSources()
  }

  async function loadGroups() {
    groupsLoading.value = true
    const res = await fetchGroups()
    groupsLoading.value = false
    if (!res.ok) {
      groups.value = []
      return
    }
    groups.value = unwrapList(res.data)
  }

  async function onGroupCheckboxChange(group, checked) {
    const gid = Number(group.id)
    if (!Number.isFinite(gid)) return
    const tag = groupTag(gid)

    if (checked) {
      if (selectedGroupIds.value.includes(gid)) return
      selectedGroupIds.value = [...selectedGroupIds.value, gid]
      groupMembersLoadingId.value = gid
      const res = await fetchGroupMembers(gid, { skipCache: true })
      groupMembersLoadingId.value = null
      if (!res.ok) {
        selectedGroupIds.value = selectedGroupIds.value.filter((x) => x !== gid)
        return
      }
      if (!selectedGroupIds.value.includes(gid)) {
        return
      }
      const items = unwrapList(res.data)
      for (const item of items) {
        const id = Number(item.id)
        if (!Number.isFinite(id)) continue
        addSource(id, tag)
        if (!isSelectedMember(id)) {
          selectedMembers.value = [
            ...selectedMembers.value,
            {
              id,
              name: item.name || unknownUserLabel,
              email: item.email || '',
            },
          ]
        }
      }
      return
    }

    selectedGroupIds.value = selectedGroupIds.value.filter((x) => x !== gid)
    const keep = []
    for (const m of selectedMembers.value) {
      const id = Number(m.id)
      const arr = memberSources[id] || []
      const next = arr.filter((t) => t !== tag)
      if (next.length === 0) {
        delete memberSources[id]
        continue
      }
      memberSources[id] = next
      keep.push(m)
    }
    selectedMembers.value = keep
  }

  function isGroupChecked(groupId) {
    return selectedGroupIds.value.includes(Number(groupId))
  }

  function clearMemberSearchTimer() {
    if (memberSearchTimer) {
      clearTimeout(memberSearchTimer)
      memberSearchTimer = null
    }
  }

  watch(memberSearchQuery, (next) => {
    clearMemberSearchTimer()
    const q = String(next || '').trim()
    if (q.length < 2) {
      memberSearchResults.value = []
      memberSearchLoading.value = false
      return
    }
    memberSearchTimer = setTimeout(async () => {
      memberSearchLoading.value = true
      const res = await searchUsers(q, 10)
      memberSearchLoading.value = false
      if (!res.ok) {
        memberSearchResults.value = []
        return
      }
      const items = Array.isArray(res.data?.data) ? res.data.data : []
      memberSearchResults.value = items.map((item) => ({
        id: item.id,
        name: item.name || unknownUserLabel,
        email: item.email || '',
      }))
    }, 300)
  })

  onBeforeUnmount(() => {
    clearMemberSearchTimer()
  })

  return {
    memberSearchQuery,
    memberSearchResults,
    memberSearchLoading,
    selectedMembers,
    groups,
    groupsLoading,
    groupMembersLoadingId,
    reset,
    loadGroups,
    isSelectedMember,
    toggleMember,
    removeSelectedMember,
    onGroupCheckboxChange,
    isGroupChecked,
  }
}
