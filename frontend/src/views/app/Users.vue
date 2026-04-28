<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import UsersInvitationsToolbar from '../../components/App/UsersInvitationsToolbar.vue'
import UsersMembersToolbar from '../../components/App/UsersMembersToolbar.vue'
import UserCard from '../../components/App/UserCard.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { useAppShell } from '../../composables/useAppShell'
import { sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { invalidateCache } from '../../services/cachedApi.js'
import { apiJson, ensureCsrfCookie } from '../../services/api'
import { fetchInvitations as fetchInvitationsApi, fetchUsersPage } from '../../services/usersApi.js'

const route = useRoute()
const router = useRouter()
const { setHeaderActions, clearHeaderActions } = useAppShell()

const activeTab = computed(() => {
  const raw = route.params.tab
  if (raw === 'invitations') return 'invitations'
  if (raw === 'members' || raw === undefined) return 'members'
  return 'members'
})

const rows = ref([])
const meta = ref(null)
const listError = ref('')
const listLoading = ref(false)
const page = ref(1)

const deletingId = ref(null)
const deleteError = ref('')

const invitations = ref([])
const invitationsLoading = ref(false)
const invitationsError = ref('')
const deletingInvitationId = ref(null)
const invitationDeleteError = ref('')

const canCreate = computed(() => hasCapability('users.create'))
const canDelete = computed(() => hasCapability('users.delete'))
const canEdit = computed(() => hasCapability('users.update'))
const canManageInvitations = computed(() => hasCapability('invitations.manage'))
const showRowActions = computed(() => canDelete.value || canEdit.value)
const showTabs = computed(() => canManageInvitations.value)

function setTab(id) {
  if (id === 'members') {
    router.push({ name: 'users' })
  } else {
    router.push({ name: 'users', params: { tab: id } })
  }
}

watch(
  () => [showTabs.value, activeTab.value],
  ([visible, tabId]) => {
    if (!visible && tabId === 'invitations') {
      router.replace({ name: 'users' })
    }
  },
)

watch(
  () => route.params.tab,
  (t) => {
    if (t != null && t !== 'members' && t !== 'invitations') {
      router.replace({ name: 'users' })
    }
  },
  { immediate: true },
)

watch(
  activeTab,
  (tabId) => {
    if (tabId === 'invitations') {
      fetchInvitations()
    }
  },
  { immediate: true },
)

async function fetchPage(nextPage) {
  listError.value = ''
  listLoading.value = true
  const { ok, status, data } = await fetchUsersPage(nextPage, 20)
  listLoading.value = false
  if (!ok) {
    listError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('users.loadError').replace('{status}', String(status))
    return
  }
  rows.value = Array.isArray(data?.data) ? data.data : []
  meta.value = data?.meta ?? null
  page.value = nextPage
}

async function fetchInvitations() {
  invitationsError.value = ''
  invitationsLoading.value = true
  const { ok, status, data } = await fetchInvitationsApi()
  invitationsLoading.value = false
  if (!ok) {
    invitations.value = []
    invitationsError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('users.invitationsLoadError').replace('{status}', String(status))
    return
  }
  invitations.value = Array.isArray(data?.data) ? data.data : []
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

function rowDeleteDisabled(target) {
  const me = sessionUser.value
  if (!me) return true
  if (me.id === target.id) return true
  if (target.is_root && !me.is_root) return true
  return false
}

function rowEditDisabled(target) {
  const me = sessionUser.value
  if (!me) return true
  if (target.is_root && !me.is_root) return true
  return false
}

function editToFor(target) {
  return { name: 'userEdit', params: { userId: String(target.id) } }
}

function memberProfileToFor(target) {
  const slug = target.username || target.profile_slug
  if (!slug) {
    return null
  }
  return { name: 'memberProfile', params: { userSlug: String(slug) } }
}

async function onDeleteUser(target) {
  if (!window.confirm(t('users.deleteConfirm').replace('{name}', target.name))) {
    return
  }
  deleteError.value = ''
  deletingId.value = target.id
  await ensureCsrfCookie()
  const { ok, status, data } = await apiJson('DELETE', `/api/users/${target.id}`)
  deletingId.value = null
  if (!ok) {
    deleteError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('users.deleteError').replace('{status}', String(status))
    return
  }
  invalidateCache(/^\/api\/users/)
  await fetchPage(page.value)
}

function invitationKindLabel(row) {
  return row.kind === 'email' ? t('users.invitationsKindEmail') : t('users.invitationsKindLink')
}

function invitationStatusLabel(row) {
  if (row.is_usable) {
    return t('users.invitationsStatusActive')
  }
  const reason = row.failure_reason
  if (reason === 'expired') return t('users.invitationsStatusExpired')
  if (reason === 'revoked') return t('users.invitationsStatusRevoked')
  if (reason === 'exhausted') return t('users.invitationsStatusExhausted')
  return t('users.invitationsStatusInactive')
}

function formatExpires(iso) {
  if (!iso) return '-'
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return iso
  }
}

function formatUses(row) {
  const n = row.uses_count
  if (row.max_uses == null) {
    return t('users.invitationsUsesUnlimited').replace('{n}', String(n))
  }
  return t('users.invitationsUsesOf').replace('{current}', String(n)).replace('{max}', String(row.max_uses))
}

async function onDeleteInvitation(row) {
  if (!window.confirm(t('users.invitationsDeleteConfirm'))) {
    return
  }
  invitationDeleteError.value = ''
  deletingInvitationId.value = row.id
  await ensureCsrfCookie()
  const { ok, status, data } = await apiJson('DELETE', `/api/invitations/${row.id}`)
  deletingInvitationId.value = null
  if (!ok) {
    invitationDeleteError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('users.invitationsDeleteError').replace('{status}', String(status))
    return
  }
  invalidateCache(/^\/api\/invitations/)
  await fetchInvitations()
}

onMounted(() => {
  setHeaderActions([
    {
      id: 'to-dashboard',
      label: t('nav.dashboard'),
      variant: 'secondary',
      onClick: () => router.push('/dashboard'),
    },
  ])
  fetchPage(1)
})

onUnmounted(() => {
  clearHeaderActions()
})
</script>

<template>
  <section class="page page--users">
    <Title tag="h1">{{ t('users.title') }}</Title>
    <p class="page--users__intro">{{ t('users.intro') }}</p>

    <div
      v-if="showTabs"
      class="page--users__tabs"
      role="tablist"
      :aria-label="t('users.tabsAria')"
    >
      <button
        type="button"
        role="tab"
        class="page--users__tab"
        :class="{ 'page--users__tab--active': activeTab === 'members' }"
        :aria-selected="activeTab === 'members'"
        @click="setTab('members')"
      >
        {{ t('users.tabMembers') }}
      </button>
      <button
        type="button"
        role="tab"
        class="page--users__tab"
        :class="{ 'page--users__tab--active': activeTab === 'invitations' }"
        :aria-selected="activeTab === 'invitations'"
        @click="setTab('invitations')"
      >
        {{ t('users.tabInvitations') }}
      </button>
    </div>

    <template v-if="activeTab === 'members'">
      <UsersMembersToolbar v-if="canCreate" />

      <h2 class="users-list__title">{{ t('users.listHeading') }}</h2>
      <p v-if="listError" class="users-list__error" role="alert">
        {{ listError }}
      </p>
      <p v-if="deleteError" class="users-list__error" role="alert">
        {{ deleteError }}
      </p>
      <p v-if="!listLoading && !listError && rows.length === 0" class="users-list__empty">
        {{ t('users.empty') }}
      </p>

      <div v-if="listLoading" class="users-list__loading">
        {{ t('users.loading') }}
      </div>

      <div v-else-if="rows.length" class="users-list__grid">
        <UserCard
          v-for="u in rows"
          :key="u.id"
          :user="u"
          :member-profile-to="memberProfileToFor(u)"
          :show-actions="showRowActions"
          :show-edit="canEdit"
          :edit-to="editToFor(u)"
          :edit-disabled="rowEditDisabled(u)"
          :edit-label="t('users.edit')"
          :show-delete="canDelete"
          :delete-disabled="rowDeleteDisabled(u)"
          :delete-loading="deletingId === u.id"
          :delete-label="t('users.delete')"
          @delete="onDeleteUser"
        />
      </div>

      <div v-if="meta && meta.last_page > 1" class="users-list__pager">
        <Button
          type="button"
          variant="secondary"
          size="sm"
          :disabled="meta.current_page <= 1 || listLoading"
          @click="goPrev"
        >
          {{ t('users.prev') }}
        </Button>
        <span class="users-list__pageInfo">
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

    <template v-else-if="activeTab === 'invitations'">
      <UsersInvitationsToolbar v-if="canManageInvitations" />
      <h2 class="users-list__title">{{ t('users.invitationsHeading') }}</h2>
      <p class="page--users__panelIntro">{{ t('users.invitationsIntro') }}</p>
      <p v-if="invitationsError" class="users-list__error" role="alert">
        {{ invitationsError }}
      </p>
      <p v-if="invitationDeleteError" class="users-list__error" role="alert">
        {{ invitationDeleteError }}
      </p>
      <p v-if="!invitationsLoading && !invitationsError && invitations.length === 0" class="users-list__empty">
        {{ t('users.invitationsEmpty') }}
      </p>
      <div v-if="invitationsLoading" class="users-list__loading">
        {{ t('users.invitationsLoading') }}
      </div>
      <div v-else-if="invitations.length" class="users-list__tableWrap">
        <table class="users-list__table">
          <thead>
            <tr>
              <th class="users-list__th">{{ t('users.invitationsColKind') }}</th>
              <th class="users-list__th">{{ t('users.invitationsColEmail') }}</th>
              <th class="users-list__th">{{ t('users.invitationsColUsed') }}</th>
              <th class="users-list__th">{{ t('users.invitationsColUses') }}</th>
              <th class="users-list__th">{{ t('users.invitationsColStatus') }}</th>
              <th class="users-list__th">{{ t('users.invitationsColExpires') }}</th>
              <th class="users-list__th users-list__th--actions">{{ t('users.colActions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="inv in invitations" :key="inv.id">
              <td class="users-list__td">{{ invitationKindLabel(inv) }}</td>
              <td class="users-list__td">
                <span v-if="inv.email">{{ inv.email }}</span>
                <span v-else class="users-list__muted">-</span>
              </td>
              <td class="users-list__td">
                {{ inv.has_been_used ? t('users.invitationsUsedYes') : t('users.invitationsUsedNo') }}
              </td>
              <td class="users-list__td">{{ formatUses(inv) }}</td>
              <td class="users-list__td">{{ invitationStatusLabel(inv) }}</td>
              <td class="users-list__td">{{ formatExpires(inv.expires_at) }}</td>
              <td class="users-list__td users-list__td--actions">
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  :disabled="deletingInvitationId === inv.id"
                  @click="onDeleteInvitation(inv)"
                >
                  {{ deletingInvitationId === inv.id ? t('users.invitationsDeleting') : t('users.invitationsDelete') }}
                </Button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.page--users {
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
}

.page--users__intro {
  margin: 0 0 1.5rem;
  color: var(--muted, #4b5563);
}

.page--users__panelIntro {
  margin: 0 0 1rem;
  color: var(--muted, #6b7280);
  font-size: 0.95rem;
}

.page--users__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin: 0 0 1.25rem;
}

.page--users__tab {
  cursor: pointer;
  padding: 0.45rem 0.85rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  background: var(--bg);
  font: inherit;
  font-size: 0.9rem;
  color: inherit;
}

.page--users__tab--active {
  font-weight: 600;
  border-color: color-mix(in srgb, var(--border) 70%, #1d4ed8);
  background: color-mix(in srgb, #1d4ed8 8%, var(--bg));
}

.users-list__title {
  margin: 0 0 0.75rem;
  font-size: 1.1rem;
}

.users-list__error {
  color: #b91c1c;
  margin: 0 0 0.75rem;
}

.users-list__empty,
.users-list__loading {
  margin: 0;
  color: var(--muted, #6b7280);
}

.users-list__grid {
  display: grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap: 1rem;
}

@media (min-width: 640px) {
  .users-list__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1024px) {
  .users-list__grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (min-width: 1280px) {
  .users-list__grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

.users-list__tableWrap {
  overflow-x: auto;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
}

.users-list__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.95rem;
}

.users-list__th {
  text-align: left;
  padding: 0.65rem 0.75rem;
  border-bottom: 1px solid var(--border);
  background: var(--table-head, rgba(0, 0, 0, 0.03));
  font-weight: 600;
}

.users-list__th--actions {
  width: 1%;
  text-align: right;
}

.users-list__td {
  padding: 0.55rem 0.75rem;
  border-bottom: 1px solid var(--border);
  vertical-align: top;
}

.users-list__td--actions {
  text-align: right;
  white-space: nowrap;
}

.users-list__muted {
  color: var(--muted, #9ca3af);
}

.users-list__pager {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-top: 1rem;
}

.users-list__pageInfo {
  font-size: 0.9rem;
  color: var(--muted, #4b5563);
}
</style>
