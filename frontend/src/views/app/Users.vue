<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import UsersMembersToolbar from '../../components/App/UsersMembersToolbar.vue'
import UsersTableRow from '../../components/App/UsersTableRow.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { useAppShell } from '../../composables/useAppShell'
import { sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { apiJson, ensureCsrfCookie } from '../../services/api'

const router = useRouter()
const { setHeaderActions, clearHeaderActions } = useAppShell()

const rows = ref([])
const meta = ref(null)
const listError = ref('')
const listLoading = ref(false)
const page = ref(1)

const deletingId = ref(null)
const deleteError = ref('')

const canCreate = computed(() => hasCapability('users.create'))
const canDelete = computed(() => hasCapability('users.delete'))
const canEdit = computed(() => hasCapability('users.update'))
const showRowActions = computed(() => canDelete.value || canEdit.value)

async function fetchPage(nextPage) {
  listError.value = ''
  listLoading.value = true
  const { ok, status, data } = await apiJson('GET', `/api/users?page=${nextPage}&per_page=20`)
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
  return { name: 'memberProfile', params: { userId: String(target.id) } }
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
    <Title tag="h1">{{ t('users.title') }}</Title>
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

    <div v-else-if="rows.length" class="users-list__tableWrap">
      <table class="users-list__table">
        <thead>
          <tr>
            <th class="users-list__th">{{ t('users.colName') }}</th>
            <th class="users-list__th">{{ t('users.colEmail') }}</th>
            <th class="users-list__th">{{ t('users.colUsername') }}</th>
            <th class="users-list__th">{{ t('users.colType') }}</th>
            <th class="users-list__th">{{ t('users.colRoot') }}</th>
            <th v-if="showRowActions" class="users-list__th users-list__th--actions">
              {{ t('users.colActions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <UsersTableRow
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
        </tbody>
      </table>
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
  max-width: 960px;
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
