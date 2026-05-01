<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import UsersInvitationsToolbar from '../../components/App/UsersInvitationsToolbar.vue'
import WalletGrantPanel from '../../components/Wallet/WalletGrantPanel.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { useAppShell } from '../../composables/useAppShell'
import { sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { invalidateCache } from '../../services/cachedApi.js'
import {
  fetchCommunityMembershipsPage,
  removeCommunityMember,
  updateCommunityMembershipRole,
} from '../../services/communityMembershipsApi.js'
import { fetchInvitations as fetchInvitationsApi } from '../../services/usersApi.js'
import { apiJson, ensureCsrfCookie } from '../../services/api'
import { communitySlugRequestOptions } from '../../utils/communityScopedRequest'

const route = useRoute()
const router = useRouter()
const { setHeaderActions, clearHeaderActions } = useAppShell()

const slug = computed(() => {
  const raw = route.params.slug
  return typeof raw === 'string' ? raw.trim() : ''
})

const scopedRequestOptions = computed(() => communitySlugRequestOptions(slug.value))

const rows = ref([])
const meta = ref(null)
const listError = ref('')
const listLoading = ref(false)
const page = ref(1)
const rowActionId = ref(null)
const actionError = ref('')

const invitations = ref([])
const invitationsLoading = ref(false)
const invitationsError = ref('')
const deletingInvitationId = ref(null)
const invitationDeleteError = ref('')

const grantDialogEl = ref(null)
/** @type {import('vue').Ref<{ id: number, name: string } | null>} */
const grantTarget = ref(null)
/** Set from memberships API so root (and others) have a stable community id for wallet grants. */
const communityIdFromApi = ref(0)

const communityIdNum = computed(() => {
  const fromApi = Number(communityIdFromApi.value)
  if (Number.isFinite(fromApi) && fromApi > 0) {
    return fromApi
  }
  const s = slug.value
  const u = sessionUser.value
  const list = Array.isArray(u?.communities) ? u.communities : []
  const row = list.find((c) => c && String(c.slug || '').trim() === s)
  const id = row && row.id != null ? Number(row.id) : 0
  return Number.isFinite(id) && id > 0 ? id : 0
})

const canGrantCredits = computed(() => hasCapability('wallet.grant') && communityIdNum.value > 0)

const canManageInvitations = computed(() => hasCapability('invitations.manage'))

async function fetchPage(nextPage) {
  if (!slug.value) return
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
  rows.value = Array.isArray(data?.data) ? data.data : []
  meta.value = data?.meta ?? null
  page.value = nextPage
  const cid = data && typeof data === 'object' && data.community_id != null ? Number(data.community_id) : 0
  if (Number.isFinite(cid) && cid > 0) {
    communityIdFromApi.value = cid
  }
}

async function loadInvitations() {
  invitationsError.value = ''
  invitationsLoading.value = true
  const { ok, status, data } = await fetchInvitationsApi(scopedRequestOptions.value)
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

function memberProfileToFor(row) {
  const s = row.username || row.profile_slug
  if (!s) return null
  return { name: 'memberProfile', params: { userSlug: String(s) } }
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

async function onPromote(row) {
  await patchRole(row, 'admin')
}

async function onDemote(row) {
  await patchRole(row, 'member')
}

async function patchRole(row, role) {
  actionError.value = ''
  rowActionId.value = row.id
  await ensureCsrfCookie()
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
  await ensureCsrfCookie()
  const { ok, status, data } = await removeCommunityMember(row.id, scopedRequestOptions.value)
  rowActionId.value = null
  if (!ok) {
    actionError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('communityMemberships.removeError').replace('{status}', String(status))
    return
  }
  invalidateCache(/^\/api\/invitations/)
  await fetchPage(page.value)
}

async function onDeleteInvitation(inv) {
  if (!window.confirm(t('users.invitationsDeleteConfirm'))) {
    return
  }
  invitationDeleteError.value = ''
  deletingInvitationId.value = inv.id
  await ensureCsrfCookie()
  const { ok, status, data } = await apiJson(
    'DELETE',
    `/api/invitations/${inv.id}`,
    undefined,
    scopedRequestOptions.value,
  )
  deletingInvitationId.value = null
  if (!ok) {
    invitationDeleteError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('users.invitationsDeleteError').replace('{status}', String(status))
    return
  }
  invalidateCache(/^\/api\/invitations/)
  await loadInvitations()
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
  return t('users.invitationsUsesOf')
    .replace('{current}', String(n))
    .replace('{max}', String(row.max_uses))
}

async function openGrantDialog(row) {
  grantTarget.value = { id: Number(row.id), name: String(row.name || '') }
  await nextTick()
  grantDialogEl.value?.showModal()
}

function closeGrantDialog() {
  grantDialogEl.value?.close()
  grantTarget.value = null
}

function onGrantSubmitted() {
  closeGrantDialog()
}

watch(slug, () => {
  void fetchPage(1)
  void loadInvitations()
})

onMounted(() => {
  setHeaderActions([
    {
      id: 'to-microsite',
      label: t('communityMemberships.backToCommunity'),
      variant: 'secondary',
      onClick: () => router.push({ name: 'communityMicrosite', params: { slug: slug.value } }),
    },
  ])
  fetchPage(1)
  loadInvitations()
})

onUnmounted(() => {
  clearHeaderActions()
})
</script>

<template>
  <section class="community-memberships-page">
    <Title tag="h1">{{ t('communityMemberships.title') }}</Title>
    <p class="community-memberships-page__intro">{{ t('communityMemberships.intro') }}</p>

    <h2 class="community-memberships-page__h2">{{ t('communityMemberships.membersHeading') }}</h2>
    <p v-if="listError" class="community-memberships-page__error" role="alert">{{ listError }}</p>
    <p v-if="actionError" class="community-memberships-page__error" role="alert">{{ actionError }}</p>
    <p v-if="!listLoading && !listError && rows.length === 0" class="community-memberships-page__muted">
      {{ t('communityMemberships.membersEmpty') }}
    </p>
    <p v-if="listLoading" class="community-memberships-page__muted">{{ t('communityMemberships.membersLoading') }}</p>

    <ul v-else-if="rows.length" class="community-memberships-page__list">
      <li v-for="row in rows" :key="row.id" class="community-memberships-page__row">
        <div class="community-memberships-page__rowMain">
          <span class="community-memberships-page__name">{{ row.name }}</span>
          <span class="community-memberships-page__role">{{ row.membership_role }}</span>
          <RouterLink
            v-if="memberProfileToFor(row)"
            class="community-memberships-page__profileLink"
            :to="memberProfileToFor(row)"
          >
            {{ t('communityMemberships.viewProfile') }}
          </RouterLink>
        </div>
        <div class="community-memberships-page__rowActions">
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
          <Button
            v-if="canGrantCredits"
            type="button"
            variant="secondary"
            size="sm"
            :disabled="rowActionId === row.id"
            @click="openGrantDialog(row)"
          >
            {{ t('communityMemberships.grantCredits') }}
          </Button>
        </div>
      </li>
    </ul>

    <div v-if="meta && meta.last_page > 1" class="community-memberships-page__pager">
      <Button
        type="button"
        variant="secondary"
        size="sm"
        :disabled="meta.current_page <= 1 || listLoading"
        @click="goPrev"
      >
        {{ t('users.prev') }}
      </Button>
      <span class="community-memberships-page__pageInfo">
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

    <template v-if="canManageInvitations">
      <h2 class="community-memberships-page__h2">{{ t('communityMemberships.invitationsHeading') }}</h2>
      <p class="community-memberships-page__intro">{{ t('communityMemberships.invitationsIntro') }}</p>
      <UsersInvitationsToolbar :request-options="scopedRequestOptions" @invitations-changed="loadInvitations" />

      <p v-if="invitationsError" class="community-memberships-page__error" role="alert">{{ invitationsError }}</p>
      <p v-if="invitationDeleteError" class="community-memberships-page__error" role="alert">
        {{ invitationDeleteError }}
      </p>
      <p v-if="!invitationsLoading && !invitationsError && invitations.length === 0" class="community-memberships-page__muted">
        {{ t('users.invitationsEmpty') }}
      </p>
      <p v-if="invitationsLoading" class="community-memberships-page__muted">{{ t('users.invitationsLoading') }}</p>
      <div v-else-if="invitations.length" class="community-memberships-page__tableWrap">
        <table class="community-memberships-page__table">
          <thead>
            <tr>
              <th class="community-memberships-page__th">{{ t('users.invitationsColKind') }}</th>
              <th class="community-memberships-page__th">{{ t('users.invitationsColEmail') }}</th>
              <th class="community-memberships-page__th">{{ t('users.invitationsColUsed') }}</th>
              <th class="community-memberships-page__th">{{ t('users.invitationsColUses') }}</th>
              <th class="community-memberships-page__th">{{ t('users.invitationsColStatus') }}</th>
              <th class="community-memberships-page__th">{{ t('users.invitationsColExpires') }}</th>
              <th class="community-memberships-page__th community-memberships-page__th--actions">
                {{ t('users.colActions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="inv in invitations" :key="inv.id">
              <td class="community-memberships-page__td">{{ invitationKindLabel(inv) }}</td>
              <td class="community-memberships-page__td">
                <span v-if="inv.email">{{ inv.email }}</span>
                <span v-else class="community-memberships-page__tdMuted">-</span>
              </td>
              <td class="community-memberships-page__td">
                {{ inv.has_been_used ? t('users.invitationsUsedYes') : t('users.invitationsUsedNo') }}
              </td>
              <td class="community-memberships-page__td">{{ formatUses(inv) }}</td>
              <td class="community-memberships-page__td">{{ invitationStatusLabel(inv) }}</td>
              <td class="community-memberships-page__td">{{ formatExpires(inv.expires_at) }}</td>
              <td class="community-memberships-page__td community-memberships-page__td--actions">
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  :disabled="deletingInvitationId === inv.id"
                  @click="onDeleteInvitation(inv)"
                >
                  {{
                    deletingInvitationId === inv.id ? t('users.invitationsDeleting') : t('users.invitationsDelete')
                  }}
                </Button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <dialog
      ref="grantDialogEl"
      class="community-memberships-page__dialog"
      @cancel="closeGrantDialog"
    >
      <div class="community-memberships-page__dialogInner">
        <div class="community-memberships-page__dialogHeader">
          <Button type="button" variant="secondary" size="sm" @click="closeGrantDialog">
            {{ t('communityMemberships.grantDialogClose') }}
          </Button>
        </div>
        <WalletGrantPanel
          v-if="grantTarget && communityIdNum > 0"
          :community-id="communityIdNum"
          :recipient-user-id="grantTarget.id"
          :recipient-name="grantTarget.name"
          @granted="onGrantSubmitted"
        />
      </div>
    </dialog>
  </section>
</template>

<style lang="scss" scoped>
.community-memberships-page {
  padding: 2rem;
  max-width: 960px;
  margin: 0 auto;
}
.community-memberships-page__intro {
  margin: 0 0 1.25rem;
  color: var(--muted, #4b5563);
  font-size: 0.95rem;
}
.community-memberships-page__h2 {
  margin: 1.75rem 0 0.65rem;
  font-size: 1.1rem;
}
.community-memberships-page__error {
  color: #b91c1c;
  margin: 0 0 0.75rem;
}
.community-memberships-page__muted {
  margin: 0 0 0.75rem;
  color: var(--muted, #6b7280);
}
.community-memberships-page__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 0.75rem;
}
.community-memberships-page__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.85rem 1rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
}
.community-memberships-page__rowMain {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}
.community-memberships-page__name {
  font-weight: 600;
}
.community-memberships-page__role {
  font-size: 0.85rem;
  color: var(--muted, #6b7280);
  text-transform: capitalize;
}
.community-memberships-page__profileLink {
  font-size: 0.88rem;
  color: var(--link, #2563eb);
  text-decoration: none;
  &:hover {
    text-decoration: underline;
  }
}
.community-memberships-page__rowActions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.community-memberships-page__pager {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-top: 1rem;
}
.community-memberships-page__pageInfo {
  font-size: 0.9rem;
  color: var(--muted, #4b5563);
}
.community-memberships-page__tableWrap {
  overflow-x: auto;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
}
.community-memberships-page__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.95rem;
}
.community-memberships-page__th {
  text-align: left;
  padding: 0.65rem 0.75rem;
  border-bottom: 1px solid var(--border);
  background: var(--table-head, rgba(0, 0, 0, 0.03));
  font-weight: 600;
}
.community-memberships-page__th--actions {
  width: 1%;
  text-align: right;
}
.community-memberships-page__td {
  padding: 0.55rem 0.75rem;
  border-bottom: 1px solid var(--border);
  vertical-align: top;
}
.community-memberships-page__td--actions {
  text-align: right;
  white-space: nowrap;
}
.community-memberships-page__tdMuted {
  color: var(--muted, #9ca3af);
}

.community-memberships-page__dialog {
  max-width: min(32rem, 100vw - 2rem);
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 0.65rem;
}
.community-memberships-page__dialog::backdrop {
  background: rgba(0, 0, 0, 0.35);
}
.community-memberships-page__dialogInner {
  padding: 1rem 1.15rem 1.25rem;
}
.community-memberships-page__dialogHeader {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 0.5rem;
}
</style>
