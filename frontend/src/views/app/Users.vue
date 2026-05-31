<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import UsersMembersToolbar from '../../components/App/UsersMembersToolbar.vue'
import UserCard from '../../components/App/UserCard.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { useAppShell } from '../../composables/useAppShell'
import { sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { invalidateCache } from '../../services/cachedApi.js'
import { apiJson, ensureCsrfCookie } from '../../services/api'
import { fetchUsersPage } from '../../services/usersApi.js'

const router = useRouter()
const { setHeaderActions, clearHeaderActions } = useAppShell()

const PER_PAGE_OPTIONS = [10, 20, 50, 100]

const rows = ref([])
const meta = ref(null)
const listError = ref('')
const listLoading = ref(false)
const page = ref(1)
const perPage = ref(20)

const deletingId = ref(null)
const deleteError = ref('')

const canCreate = computed(() => hasCapability('users.create'))
const canDelete = computed(() => hasCapability('users.delete'))
const canEdit = computed(() => hasCapability('users.update'))
const canPersonify = computed(() => hasCapability('users.personify'))
const showRowActions = computed(() => canDelete.value || canEdit.value || canPersonify.value)

const listSummaryLabel = computed(() => {
  const m = meta.value
  if (!m || typeof m.total !== 'number') {
    return ''
  }
  const total = m.total
  if (total === 0) {
    return t('users.listTotal').replace('{total}', '0')
  }
  const from = m.from
  const to = m.to
  if (typeof from === 'number' && typeof to === 'number') {
    return t('users.listRange')
      .replace('{from}', String(from))
      .replace('{to}', String(to))
      .replace('{total}', String(total))
  }
  return t('users.listTotal').replace('{total}', String(total))
})

async function fetchPage(nextPage) {
  listError.value = ''
  listLoading.value = true
  const { ok, status, data } = await fetchUsersPage(nextPage, perPage.value)
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

function onPerPageChange() {
  void fetchPage(1)
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

function isUserCommunityAdmin(u) {
  return Boolean(u?.is_root || u?.user_type === 'admin')
}

function rowPersonifyDisabled(target) {
  const me = sessionUser.value
  if (!me) return true
  if (target.is_root) return true
  if (me.id === target.id) return true
  if (!me.is_root && me.user_type === 'developer' && isUserCommunityAdmin(target)) {
    return true
  }
  return false
}

function personifyToFor(target) {
  return { name: 'supportPersonification', query: { userId: String(target.id) } }
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
    <PageToolbarTitle route-key="users">
      <Title tag="h1">{{ t('users.title') }}</Title>
    </PageToolbarTitle>
    <p class="page--users__intro">{{ t('users.intro') }}</p>

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

    <div v-if="meta && !listError" class="users-list__metaRow">
      <span v-if="listSummaryLabel" class="users-list__summary">{{ listSummaryLabel }}</span>
      <label class="users-list__perPage">
        <span class="users-list__perPageLabel">{{ t('users.perPageLabel') }}</span>
        <select
          v-model.number="perPage"
          class="users-list__perPageSelect"
          :disabled="listLoading"
          @change="onPerPageChange"
        >
          <option v-for="n in PER_PAGE_OPTIONS" :key="n" :value="n">
            {{ n }}
          </option>
        </select>
      </label>
    </div>

    <div v-if="!listLoading && rows.length" class="users-list__grid">
      <UserCard
        v-for="u in rows"
        :key="u.id"
        :user="u"
        :member-profile-to="memberProfileToFor(u)"
        :show-actions="showRowActions"
        :show-personify="canPersonify"
        :personify-to="personifyToFor(u)"
        :personify-disabled="rowPersonifyDisabled(u)"
        :personify-label="t('users.personifyCard')"
        :show-edit="canEdit"
        :edit-to="editToFor(u)"
        :edit-disabled="rowEditDisabled(u)"
        :edit-label="t('users.editPageTitle')"
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

.users-list__metaRow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem 1rem;
  margin: 0 0 1rem;
  font-size: 0.9rem;
  color: var(--muted, #4b5563);
}

.users-list__summary {
  margin: 0;
}

.users-list__perPage {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.users-list__perPageLabel {
  white-space: nowrap;
}

.users-list__perPageSelect {
  min-width: 4.5rem;
  padding: 0.35rem 0.5rem;
  font-size: inherit;
  border: 1px solid var(--border, #d1d5db);
  border-radius: 0.375rem;
  background: var(--surface, #fff);
  color: inherit;
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
