<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import { t } from '../../i18n/i18n'
import {
  addGroupMember,
  fetchGroup,
  fetchGroupMembers,
  removeGroupMember,
  updateGroupMember,
} from '../../services/contentApi'
import { searchUsers } from '../../services/usersApi'
import { sessionUser } from '../../composables/useSession'

const route = useRoute()
const router = useRouter()

const groupId = computed(() => route.params.groupId)

const group = ref(null)
const members = ref([])
const loadingGroup = ref(false)
const loadingMembers = ref(false)
const groupError = ref('')
const membersError = ref('')
const actionError = ref('')

const currentUserId = computed(() => Number(sessionUser.value?.id || 0))
const isOwner = computed(() => group.value && Number(group.value.owner_id) === currentUserId.value)
const isAdmin = computed(() => {
  if (isOwner.value) return true
  const me = members.value.find((m) => Number(m.id) === currentUserId.value)
  return me?.role === 'admin'
})
const canManage = computed(() => isOwner.value || isAdmin.value)

// Add member form
const memberSearch = ref('')
const searchResults = ref([])
const searching = ref(false)
const selectedUser = ref(null)
const newRole = ref('member')
const addingMember = ref(false)

let searchTimer = null

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

function unwrapSingle(payload, key) {
  if (!payload || typeof payload !== 'object') return null
  if (payload[key]) return payload[key]
  return payload
}

async function loadGroup() {
  loadingGroup.value = true
  groupError.value = ''
  const res = await fetchGroup(groupId.value)
  loadingGroup.value = false
  if (!res.ok) {
    groupError.value = t('groups.detail.loadError').replace('{status}', String(res.status))
    return
  }
  group.value = unwrapSingle(res.data, 'group')
}

async function loadMembers() {
  loadingMembers.value = true
  membersError.value = ''
  const res = await fetchGroupMembers(groupId.value)
  loadingMembers.value = false
  if (!res.ok) {
    membersError.value = t('groups.detail.membersLoadError').replace('{status}', String(res.status))
    return
  }
  members.value = unwrapList(res.data)
}

async function load() {
  await Promise.all([loadGroup(), loadMembers()])
}

function roleLabel(role) {
  if (role === 'owner') return t('groups.detail.roleOwner')
  if (role === 'admin') return t('groups.detail.roleAdmin')
  return t('groups.detail.roleMember')
}

async function doSearch(query) {
  if (!query || query.trim().length < 1) {
    searchResults.value = []
    return
  }
  searching.value = true
  const res = await searchUsers(query.trim())
  searching.value = false
  if (res.ok) {
    const all = unwrapList(res.data)
    const memberIds = new Set(members.value.map((m) => Number(m.id)))
    searchResults.value = all.filter((u) => !memberIds.has(Number(u.id)))
  }
}

watch(memberSearch, (val) => {
  clearTimeout(searchTimer)
  selectedUser.value = null
  searchTimer = setTimeout(() => doSearch(val), 300)
})

function selectUser(user) {
  selectedUser.value = user
  memberSearch.value = user.name
  searchResults.value = []
}

async function addMember() {
  if (!selectedUser.value) return
  addingMember.value = true
  actionError.value = ''
  const res = await addGroupMember(groupId.value, {
    user_id: selectedUser.value.id,
    role: newRole.value,
  })
  addingMember.value = false
  if (!res.ok) {
    actionError.value = t('groups.detail.addMemberError').replace('{status}', String(res.status))
    return
  }
  memberSearch.value = ''
  selectedUser.value = null
  newRole.value = 'member'
  await loadMembers()
}

async function changeRole(member, role) {
  actionError.value = ''
  const res = await updateGroupMember(groupId.value, member.id, { role })
  if (!res.ok) {
    actionError.value = t('groups.detail.updateRoleError').replace('{status}', String(res.status))
    return
  }
  await loadMembers()
}

async function removeMember(member) {
  if (!confirm(t('groups.detail.removeMemberConfirm').replace('{name}', member.name))) return
  actionError.value = ''
  const res = await removeGroupMember(groupId.value, member.id)
  if (!res.ok) {
    actionError.value = t('groups.detail.removeMemberError').replace('{status}', String(res.status))
    return
  }
  await loadMembers()
}

onMounted(load)
</script>

<template>
  <section class="page page--group-detail">
    <header class="page__header">
      <PageToolbarTitle route-key="my-groups">
        <Title tag="h1">{{ group ? group.name : t('groups.detail.loading') }}</Title>
      </PageToolbarTitle>
      <button class="btn btn--ghost back-btn" @click="router.push({ name: 'myGroups' })">
        ← {{ t('groups.backToGroups') }}
      </button>
    </header>

    <p v-if="loadingGroup" class="page__muted">{{ t('groups.detail.loading') }}</p>
    <p v-if="groupError" class="page__error">{{ groupError }}</p>

    <template v-if="group">
      <p v-if="group.description" class="group-description page__muted">{{ group.description }}</p>

      <!-- Members list -->
      <section class="members-section">
        <h2 class="section-heading">{{ t('groups.detail.membersHeading') }}</h2>

        <p v-if="loadingMembers" class="page__muted">{{ t('groups.detail.membersLoading') }}</p>
        <p v-else-if="membersError" class="page__error">{{ membersError }}</p>
        <p v-else-if="members.length === 0" class="page__muted">{{ t('groups.detail.membersEmpty') }}</p>

        <ul v-else class="members-list">
          <li
            v-for="member in members"
            :key="member.id"
            class="members-list__item"
          >
            <div class="member-info">
              <img
                v-if="member.avatar_url"
                :src="member.avatar_url"
                :alt="member.name"
                class="member-avatar"
              />
              <span v-else class="member-avatar member-avatar--placeholder">
                {{ member.name?.charAt(0)?.toUpperCase() }}
              </span>
              <div>
                <strong>{{ member.name }}</strong>
                <p class="page__muted member-email">{{ member.email }}</p>
              </div>
            </div>

            <div class="member-actions">
              <!-- Role selector (owner/admin only, not for the group owner themselves) -->
              <select
                v-if="canManage && Number(member.id) !== Number(group.owner_id)"
                :value="member.role || 'member'"
                class="role-select"
                @change="changeRole(member, $event.target.value)"
              >
                <option value="admin">{{ t('groups.detail.roleAdmin') }}</option>
                <option value="member">{{ t('groups.detail.roleMember') }}</option>
              </select>
              <span v-else class="role-badge" :class="`role-badge--${member.role || 'member'}`">
                {{ roleLabel(member.role) }}
              </span>

              <!-- Remove button (owner/admin can remove others; cannot remove the group owner) -->
              <button
                v-if="canManage && Number(member.id) !== Number(group.owner_id) && Number(member.id) !== currentUserId"
                class="btn btn--ghost btn--sm btn--danger"
                @click="removeMember(member)"
              >
                {{ t('groups.detail.removeMember') }}
              </button>
            </div>
          </li>
        </ul>
      </section>

      <!-- Add member (owner/admin only) -->
      <section v-if="canManage" class="add-member-section">
        <h2 class="section-heading">{{ t('groups.detail.addMemberHeading') }}</h2>

        <div class="add-member-form">
          <div class="search-wrapper">
            <input
              v-model="memberSearch"
              class="search-input"
              :placeholder="t('groups.detail.addMemberSearch')"
              autocomplete="off"
            />
            <p v-if="searching" class="page__muted search-hint">{{ t('groups.detail.addMemberSearching') }}</p>
            <p v-else-if="memberSearch && !selectedUser && searchResults.length === 0 && !searching" class="page__muted search-hint">
              {{ t('groups.detail.addMemberEmpty') }}
            </p>
            <ul v-if="searchResults.length > 0" class="search-dropdown">
              <li
                v-for="user in searchResults"
                :key="user.id"
                class="search-dropdown__item"
                @click="selectUser(user)"
              >
                <strong>{{ user.name }}</strong>
                <span class="page__muted"> — {{ user.email }}</span>
              </li>
            </ul>
          </div>

          <select v-model="newRole" class="role-select">
            <option value="admin">{{ t('groups.detail.roleAdmin') }}</option>
            <option value="member">{{ t('groups.detail.roleMember') }}</option>
          </select>

          <button
            class="btn btn--primary"
            :disabled="!selectedUser || addingMember"
            @click="addMember"
          >
            {{ t('groups.detail.addMemberSubmit') }}
          </button>
        </div>
      </section>

      <p v-if="actionError" class="page__error">{{ actionError }}</p>
    </template>
  </section>
</template>

<style scoped lang="scss">
.page--group-detail {
  padding: 1rem;
  display: grid;
  gap: 1.2rem;
}

.page__header {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.back-btn {
  align-self: flex-start;
  font-size: 0.9rem;
}

.group-description {
  margin: 0;
}

.section-heading {
  font-size: 1rem;
  font-weight: 600;
  margin: 0 0 0.6rem;
}

.members-section,
.add-member-section {
  display: grid;
  gap: 0.5rem;
}

.members-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.5rem;
}

.members-list__item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  border: 1px solid var(--border);
  border-radius: 0.6rem;
  padding: 0.6rem 0.8rem;
}

.member-info {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-width: 0;
}

.member-avatar {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

.member-avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--border);
  font-weight: 600;
  font-size: 0.85rem;
}

.member-email {
  margin: 0;
  font-size: 0.8rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.member-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.role-select {
  padding: 0.3rem 0.4rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--surface, #fff);
  font-size: 0.85rem;
  cursor: pointer;
}

.role-badge {
  font-size: 0.8rem;
  padding: 0.2rem 0.5rem;
  border-radius: 1rem;
  background: var(--border);
  white-space: nowrap;

  &--owner { background: #dbeafe; color: #1d4ed8; }
  &--admin { background: #fef3c7; color: #92400e; }
  &--member { background: var(--border); }
}

.btn--sm { padding: 0.25rem 0.6rem; font-size: 0.8rem; }
.btn--danger { color: #b00020; }
.btn--danger:hover { background: #fef2f2; }

.add-member-form {
  display: flex;
  gap: 0.5rem;
  align-items: flex-start;
  flex-wrap: wrap;
}

.search-wrapper {
  flex: 1 1 14rem;
  position: relative;
}

.search-input {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  box-sizing: border-box;
}

.search-hint {
  margin: 0.25rem 0 0;
  font-size: 0.8rem;
}

.search-dropdown {
  position: absolute;
  z-index: 10;
  top: calc(100% + 2px);
  left: 0;
  right: 0;
  list-style: none;
  margin: 0;
  padding: 0.25rem 0;
  background: var(--surface, #fff);
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  max-height: 12rem;
  overflow-y: auto;
}

.search-dropdown__item {
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  font-size: 0.9rem;

  &:hover { background: var(--border); }
}

.page__muted { margin: 0; opacity: 0.8; }
.page__error { color: #b00020; margin: 0; }
</style>
