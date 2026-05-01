<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Input from '../../atoms/Input.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { t } from '../../i18n/i18n'
import {
  addCommunityMember,
  fetchCommunityMembershipsPage,
  removeCommunityMember,
  updateCommunityMembershipRole,
} from '../../services/communityMembershipsApi.js'
import { searchUsers } from '../../services/usersApi.js'
import { communitySlugRequestOptions } from '../../utils/communityScopedRequest'

const props = defineProps({
  communitySlug: {
    type: String,
    required: true,
  },
})

const canManage = computed(() => hasCapability('community.memberships.manage'))
const scopedRequestOptions = computed(() => communitySlugRequestOptions(props.communitySlug))

const rows = ref([])
const meta = ref(null)
const listError = ref('')
const listLoading = ref(false)
const page = ref(1)
const rowActionId = ref(null)
const actionError = ref('')

const memberSearch = ref('')
const searchResults = ref([])
const searching = ref(false)
const selectedUser = ref(null)
const newMemberRole = ref('admin')
const addingMember = ref(false)
const addMemberError = ref('')

let searchTimer = null

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload.data)) return payload.data
  return []
}

async function fetchPage(nextPage) {
  const slug = props.communitySlug.trim()
  if (!slug || !canManage.value) return
  listError.value = ''
  listLoading.value = true
  const { ok, status, data } = await fetchCommunityMembershipsPage(
    nextPage,
    20,
    scopedRequestOptions.value,
  )
  listLoading.value = false
  if (!ok) {
    listError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('communityMemberships.loadError').replace('{status}', String(status))
    rows.value = []
    meta.value = null
    return
  }
  rows.value = unwrapList(data)
  meta.value = data?.meta ?? null
  page.value = nextPage
}

function goPrev() {
  const p = meta.value?.current_page
  if (typeof p === 'number' && p > 1) {
    fetchPage(p - 1)
  }
}

function goNext() {
  const p = meta.value?.current_page
  const last = meta.value?.last_page
  if (typeof p === 'number' && typeof last === 'number' && p < last) {
    fetchPage(p + 1)
  }
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
    const memberIds = new Set(rows.value.map((m) => Number(m.id)))
    searchResults.value = all.filter((u) => !memberIds.has(Number(u.id)))
  }
}

watch(memberSearch, (val) => {
  clearTimeout(searchTimer)
  selectedUser.value = null
  searchTimer = setTimeout(() => doSearch(val), 300)
})

watch(
  () => props.communitySlug,
  () => {
    void fetchPage(1)
    memberSearch.value = ''
    searchResults.value = []
    selectedUser.value = null
  },
)

function selectUser(user) {
  selectedUser.value = user
  memberSearch.value = user.name
  searchResults.value = []
}

async function onAddMember() {
  if (!selectedUser.value) return
  addingMember.value = true
  addMemberError.value = ''
  const { ok, status, data } = await addCommunityMember(
    { user_id: Number(selectedUser.value.id), role: newMemberRole.value },
    scopedRequestOptions.value,
  )
  addingMember.value = false
  if (!ok) {
    addMemberError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('communities.adminsAddError').replace('{status}', String(status))
    return
  }
  memberSearch.value = ''
  selectedUser.value = null
  newMemberRole.value = 'admin'
  await fetchPage(page.value)
}

async function onPromote(row) {
  await patchRole(row, 'admin')
}

async function onDemote(row) {
  await patchRole(row, 'member')
}

async function patchRole(row, role) {
  actionError.value = ''
  rowActionId.value = row.id
  const { ok, status, data } = await updateCommunityMembershipRole(
    row.id,
    role,
    scopedRequestOptions.value,
  )
  rowActionId.value = null
  if (!ok) {
    actionError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('communityMemberships.roleError').replace('{status}', String(status))
    return
  }
  await fetchPage(page.value)
}

async function onRemove(row) {
  if (!window.confirm(t('communityMemberships.removeConfirm').replace('{name}', row.name))) {
    return
  }
  actionError.value = ''
  rowActionId.value = row.id
  const { ok, status, data } = await removeCommunityMember(row.id, scopedRequestOptions.value)
  rowActionId.value = null
  if (!ok) {
    actionError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('communityMemberships.removeError').replace('{status}', String(status))
    return
  }
  await fetchPage(page.value)
}

onMounted(() => {
  if (canManage.value) {
    fetchPage(1)
  }
})
</script>

<template>
  <section class="community-admins-panel">
    <h2 class="community-admins-panel__title">{{ t('communities.adminsSectionTitle') }}</h2>
    <p class="community-admins-panel__intro">{{ t('communities.adminsSectionIntro') }}</p>

    <p v-if="!canManage" class="community-admins-panel__muted">
      {{ t('communities.adminsNoPermission') }}
    </p>

    <template v-else>
      <p class="community-admins-panel__muted">{{ t('communities.adminsFullHubHint') }}</p>
      <p class="community-admins-panel__hub">
        <RouterLink class="community-admins-panel__link" :to="`/community/${communitySlug}/members`">
          {{ t('communities.adminsOpenMembershipHub') }}
        </RouterLink>
      </p>

      <h3 class="community-admins-panel__h3">{{ t('communities.adminsAddHeading') }}</h3>
      <p class="community-admins-panel__muted">{{ t('communities.adminsAddIntro') }}</p>
      <p v-if="addMemberError" class="community-admins-panel__error" role="alert">{{ addMemberError }}</p>
      <div class="community-admins-panel__addRow">
        <Input
          v-model="memberSearch"
          :label="t('communities.adminsSearchLabel')"
          autocomplete="off"
        />
        <label class="community-admins-panel__label">
          <span>{{ t('communities.adminsRoleLabel') }}</span>
          <select v-model="newMemberRole" class="community-admins-panel__select">
            <option value="admin">{{ t('communities.adminsRoleAdmin') }}</option>
            <option value="member">{{ t('communities.adminsRoleMember') }}</option>
          </select>
        </label>
        <Button
          type="button"
          :disabled="!selectedUser || addingMember"
          @click="onAddMember"
        >
          {{ addingMember ? t('communities.adminsAdding') : t('communities.adminsAddButton') }}
        </Button>
      </div>
      <ul v-if="searchResults.length" class="community-admins-panel__searchList">
        <li v-for="u in searchResults" :key="u.id">
          <button type="button" class="community-admins-panel__searchHit" @click="selectUser(u)">
            {{ u.name }} <span class="community-admins-panel__email">({{ u.email }})</span>
          </button>
        </li>
      </ul>
      <p v-if="searching" class="community-admins-panel__muted">{{ t('communities.adminsSearching') }}</p>

      <h3 class="community-admins-panel__h3">{{ t('communities.adminsListHeading') }}</h3>
      <p v-if="listError" class="community-admins-panel__error" role="alert">{{ listError }}</p>
      <p v-if="actionError" class="community-admins-panel__error" role="alert">{{ actionError }}</p>
      <p v-if="listLoading" class="community-admins-panel__muted">{{ t('communityMemberships.membersLoading') }}</p>
      <p
        v-else-if="!listError && rows.length === 0"
        class="community-admins-panel__muted"
      >
        {{ t('communityMemberships.membersEmpty') }}
      </p>
      <ul v-else-if="rows.length" class="community-admins-panel__list">
        <li v-for="row in rows" :key="row.id" class="community-admins-panel__row">
          <div class="community-admins-panel__rowMain">
            <span class="community-admins-panel__name">{{ row.name }}</span>
            <span class="community-admins-panel__role">{{ row.membership_role }}</span>
          </div>
          <div class="community-admins-panel__rowActions">
            <Button
              v-if="row.membership_role === 'member'"
              type="button"
              variant="secondary"
              size="sm"
              :disabled="rowActionId === row.id"
              @click="onPromote(row)"
            >
              {{ t('communityMemberships.promoteAdmin') }}
            </Button>
            <Button
              v-if="row.membership_role === 'admin'"
              type="button"
              variant="secondary"
              size="sm"
              :disabled="rowActionId === row.id"
              @click="onDemote(row)"
            >
              {{ t('communityMemberships.demoteMember') }}
            </Button>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              :disabled="rowActionId === row.id"
              @click="onRemove(row)"
            >
              {{ t('communityMemberships.removeMember') }}
            </Button>
          </div>
        </li>
      </ul>

      <div v-if="meta && meta.last_page > 1" class="community-admins-panel__pager">
        <Button
          type="button"
          variant="secondary"
          size="sm"
          :disabled="meta.current_page <= 1 || listLoading"
          @click="goPrev"
        >
          {{ t('users.prev') }}
        </Button>
        <span class="community-admins-panel__pageInfo">
          {{
            t('users.pageInfo')
              .replace('{current}', String(meta.current_page))
              .replace('{last}', String(meta.last_page))
          }}
        </span>
        <Button
          type="button"
          variant="secondary"
          size="sm"
          :disabled="meta.current_page >= meta.last_page || listLoading"
          @click="goNext"
        >
          {{ t('users.next') }}
        </Button>
      </div>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.community-admins-panel {
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--border);
}

.community-admins-panel__title {
  font-size: 1.125rem;
  margin: 0 0 0.35rem;
}

.community-admins-panel__h3 {
  font-size: 1rem;
  margin: 1.25rem 0 0.35rem;
}

.community-admins-panel__intro,
.community-admins-panel__muted {
  color: var(--muted, #6b7280);
  margin: 0 0 0.75rem;
}

.community-admins-panel__hub {
  margin: 0 0 1rem;
}

.community-admins-panel__link {
  color: var(--link, #2563eb);
}

.community-admins-panel__error {
  color: #b91c1c;
  margin: 0 0 0.5rem;
}

.community-admins-panel__addRow {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: flex-end;
  margin-bottom: 0.5rem;
}

.community-admins-panel__label {
  display: grid;
  gap: 0.35rem;
  font-size: 0.875rem;
  font-weight: 600;
}

.community-admins-panel__select {
  min-width: 10rem;
  padding: 0.45rem 0.5rem;
  border-radius: 0.375rem;
  border: 1px solid var(--border);
}

.community-admins-panel__searchList {
  list-style: none;
  padding: 0;
  margin: 0 0 0.75rem;
  border: 1px solid var(--border);
  border-radius: 0.375rem;
  max-height: 12rem;
  overflow: auto;
}

.community-admins-panel__searchHit {
  display: block;
  width: 100%;
  text-align: left;
  padding: 0.5rem 0.65rem;
  border: 0;
  border-bottom: 1px solid var(--border);
  background: transparent;
  cursor: pointer;
}

.community-admins-panel__searchHit:hover {
  background: var(--table-head, rgba(0, 0, 0, 0.04));
}

.community-admins-panel__email {
  color: var(--muted, #6b7280);
  font-weight: 400;
}

.community-admins-panel__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 0.65rem;
}

.community-admins-panel__row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.65rem 0.75rem;
  border: 1px solid var(--border);
  border-radius: 0.375rem;
}

.community-admins-panel__rowMain {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
}

.community-admins-panel__name {
  font-weight: 600;
}

.community-admins-panel__role {
  font-size: 0.875rem;
  color: var(--muted, #6b7280);
}

.community-admins-panel__rowActions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.community-admins-panel__pager {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 1rem;
}

.community-admins-panel__pageInfo {
  font-size: 0.875rem;
  color: var(--muted, #6b7280);
}
</style>
