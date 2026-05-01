<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Input from '../../atoms/Input.vue'
import Title from '../../atoms/Title.vue'
import ProfileExternalLinksEditor from '../../molecules/ProfileExternalLinksEditor.vue'
import ProfileStringListEditor from '../../molecules/ProfileStringListEditor.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { resolveSession, sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { fetchUser, fetchVotingIdAudits, updateUser, userApiErrorMessage } from '../../services/usersApi.js'

const ASSIGNABLE_USER_TYPES = ['admin', 'member', 'developer']

function userTypeLabel(key) {
  if (key === 'admin') return t('users.typeAdmin')
  if (key === 'developer') return t('users.typeDeveloper')
  return t('users.typeMember')
}

const route = useRoute()
const router = useRouter()

const form = reactive({
  name: '',
  email: '',
  username: '',
  password: '',
  user_type: 'member',
  is_root: false,
  phone_numbers: [],
  contact_emails: [],
  aliases: [],
  external_links: [],
  voting_id: '',
})

const loadError = ref('')
const loading = ref(true)
const saveError = ref('')
const saveLoading = ref(false)
const usernameFieldError = ref('')
const votingIdFieldError = ref('')

const assignTypes = computed(() => hasCapability('users.assign_types'))

const auditRows = ref([])
const auditMeta = ref(null)
const auditsLoading = ref(false)
const auditsError = ref('')
const auditPage = ref(1)

const userId = computed(() => {
  const raw = route.params.userId
  return typeof raw === 'string' ? raw : ''
})

function cloneStringList(value) {
  if (!Array.isArray(value)) {
    return []
  }
  return value.map((x) => (typeof x === 'string' || typeof x === 'number' ? String(x) : ''))
}

function cloneExternalLinks(value) {
  if (!Array.isArray(value)) {
    return []
  }
  return value.map((row) => {
    if (!row || typeof row !== 'object') {
      return { title: '', url: '' }
    }
    return {
      title: typeof row.title === 'string' ? row.title : '',
      url: typeof row.url === 'string' ? row.url : '',
    }
  })
}

async function load() {
  const id = userId.value
  if (!id) return
  loadError.value = ''
  loading.value = true
  const { ok, status, data } = await fetchUser(id)
  loading.value = false
  if (!ok) {
    loadError.value = userApiErrorMessage(data, status, t('users.loadOneError'))
    if (status === 403 || status === 404) {
      setTimeout(() => router.replace({ name: 'users' }), 1600)
    }
    return
  }
  const u = data?.user
  if (!u) {
    loadError.value = t('users.loadOneError').replace('{status}', String(status))
    setTimeout(() => router.replace({ name: 'users' }), 1600)
    return
  }
  form.name = u.name ?? ''
  form.email = u.email ?? ''
  form.username = u.username ?? ''
  form.password = ''
  form.user_type = u.user_type ?? 'member'
  form.is_root = Boolean(u.is_root)
  form.phone_numbers = cloneStringList(u.phone_numbers)
  form.contact_emails = cloneStringList(u.contact_emails)
  form.aliases = cloneStringList(u.aliases)
  form.external_links = cloneExternalLinks(u.external_links)
  form.voting_id = u.voting_id != null && u.voting_id !== undefined ? String(u.voting_id) : ''
  auditPage.value = 1
  await loadAudits()
}

async function loadAudits() {
  const id = userId.value
  if (!id) return
  auditsLoading.value = true
  auditsError.value = ''
  const { ok, status, data } = await fetchVotingIdAudits(id, auditPage.value, 20)
  auditsLoading.value = false
  if (!ok) {
    auditRows.value = []
    auditMeta.value = null
    auditsError.value = t('users.votingIdHistoryLoadError').replace('{status}', String(status))
    return
  }
  auditRows.value = Array.isArray(data?.data) ? data.data : []
  auditMeta.value = data?.meta && typeof data.meta === 'object' ? data.meta : null
}

function formatAuditWhen(iso) {
  if (typeof iso !== 'string' || !iso) return t('users.votingIdDash')
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return t('users.votingIdDash')
  return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
}

function auditOldNew(val) {
  if (val == null || val === '') return t('users.votingIdDash')
  return String(val)
}

function auditActorName(row) {
  const by = row?.changed_by
  if (by && typeof by === 'object' && typeof by.name === 'string' && by.name.trim()) {
    return by.name.trim()
  }
  return t('users.votingIdDash')
}

async function goAuditPrev() {
  if (auditMeta.value && auditPage.value > 1) {
    auditPage.value -= 1
    await loadAudits()
  }
}

async function goAuditNext() {
  const m = auditMeta.value
  if (m && typeof m.last_page === 'number' && auditPage.value < m.last_page) {
    auditPage.value += 1
    await loadAudits()
  }
}

watch(userId, () => {
  load()
})

watch(
  () => form.username,
  () => {
    usernameFieldError.value = ''
  },
)

watch(
  () => form.voting_id,
  () => {
    votingIdFieldError.value = ''
  },
)

watch(
  () => form.is_root,
  (root) => {
    if (!root && form.user_type === 'root') {
      form.user_type = 'member'
    }
  },
)

load()

function setUsernameFieldErrorFromResponse(data, status) {
  usernameFieldError.value = ''
  if (status !== 422 || !data || typeof data !== 'object') {
    return
  }
  const errors = data.errors
  if (!errors || typeof errors !== 'object') {
    return
  }
  const list = errors.username
  if (Array.isArray(list) && list.length > 0 && typeof list[0] === 'string') {
    usernameFieldError.value = t('profile.usernameTaken')
  }
}

function setVotingIdFieldErrorFromResponse(data, status) {
  votingIdFieldError.value = ''
  if (status !== 422 || !data || typeof data !== 'object') {
    return
  }
  const errors = data.errors
  if (!errors || typeof errors !== 'object') {
    return
  }
  const list = errors.voting_id
  if (Array.isArray(list) && list.length > 0) {
    votingIdFieldError.value = list.map(String).join(' ')
  }
}

async function onSubmit() {
  const id = userId.value
  if (!id) return
  saveError.value = ''
  usernameFieldError.value = ''
  votingIdFieldError.value = ''
  const vid = String(form.voting_id ?? '').trim()
  if (vid.length > 0 && vid.length !== 6) {
    votingIdFieldError.value = t('users.votingIdInvalid')
    return
  }
  if (vid.length > 0 && !/^[0-9]{6}$/.test(vid)) {
    votingIdFieldError.value = t('users.votingIdInvalid')
    return
  }
  saveLoading.value = true
  const body = {
    name: form.name.trim(),
    email: form.email.trim(),
    username: form.username.trim() === '' ? null : form.username.trim(),
    phone_numbers: form.phone_numbers,
    contact_emails: form.contact_emails,
    aliases: form.aliases,
    external_links: form.external_links,
    voting_id: vid === '' ? null : vid,
  }
  if (form.password.trim()) {
    body.password = form.password
  }
  if (assignTypes.value) {
    body.is_root = form.is_root
    if (form.is_root) {
      body.user_type = 'root'
    } else {
      body.user_type = form.user_type
    }
  }
  const { ok, status, data } = await updateUser(id, body)
  saveLoading.value = false
  if (!ok) {
    setUsernameFieldErrorFromResponse(data, status)
    setVotingIdFieldErrorFromResponse(data, status)
    saveError.value = userApiErrorMessage(data, status, t('users.saveError'))
    return
  }
  form.password = ''
  const me = sessionUser.value
  if (me && String(me.id) === String(id)) {
    await resolveSession()
  }
  await load()
}

function goBack() {
  router.push({ name: 'users' })
}
</script>

<template>
  <section class="user-edit-page">
    <Title tag="h1">{{ t('users.editPageTitle') }}</Title>
    <p class="user-edit-page__muted">{{ t('users.editPageIntro') }}</p>

    <div class="user-edit-page__toolbar">
      <button type="button" class="user-edit-page__btn" @click="goBack">
        {{ t('users.backToList') }}
      </button>
    </div>

    <p v-if="loadError" class="user-edit-page__error" role="alert">
      {{ loadError }}
    </p>
    <p v-else-if="loading" class="user-edit-page__muted">{{ t('users.loadingOne') }}</p>

    <template v-else>
      <Card class="user-edit-page__panel">
        <form class="user-edit-page__form" @submit.prevent="onSubmit">
          <div class="user-edit-page__fields">
            <Input
              v-model="form.name"
              name="edit-name"
              type="text"
              :label="t('users.fieldName')"
              autocomplete="name"
              required
            />
            <Input
              v-model="form.email"
              name="edit-email"
              type="email"
              :label="t('users.fieldEmail')"
              autocomplete="email"
              required
            />
            <Input
              v-model="form.username"
              name="edit-username"
              type="text"
              :label="t('users.fieldUsername')"
              autocomplete="username"
            />
            <p v-if="usernameFieldError" class="user-edit-page__fieldError" role="alert">
              {{ usernameFieldError }}
            </p>
            <Input
              v-model="form.voting_id"
              name="edit-voting-id"
              type="text"
              autocomplete="off"
              :label="t('users.fieldVotingId')"
            />
            <p class="user-edit-page__hint">{{ t('users.fieldVotingIdHint') }}</p>
            <p v-if="votingIdFieldError" class="user-edit-page__fieldError" role="alert">
              {{ votingIdFieldError }}
            </p>
            <Input
              v-model="form.password"
              name="edit-password"
              type="password"
              :label="t('users.fieldPasswordNew')"
              autocomplete="new-password"
            />
            <p class="user-edit-page__hint">{{ t('users.passwordOptionalHint') }}</p>

            <template v-if="assignTypes">
              <label class="user-edit-page__selectLabel" for="edit-user-type">{{ t('users.fieldUserType') }}</label>
              <select
                v-if="!form.is_root"
                id="edit-user-type"
                v-model="form.user_type"
                class="user-edit-page__select"
                name="edit-user-type"
              >
                <option v-for="opt in ASSIGNABLE_USER_TYPES" :key="opt" :value="opt">
                  {{ userTypeLabel(opt) }}
                </option>
              </select>
              <p v-else class="user-edit-page__muted">{{ t('users.typeRootLocked') }}</p>
              <label class="user-edit-page__checkLabel">
                <input v-model="form.is_root" type="checkbox" name="edit-is-root" />
                {{ t('users.fieldIsRoot') }}
              </label>
            </template>
          </div>

          <h2 class="user-edit-page__subheading">{{ t('profile.contactHeading') }}</h2>
          <p class="user-edit-page__hint user-edit-page__hint--spaced">{{ t('profile.contactIntro') }}</p>
          <div class="user-edit-page__contactBlock">
            <ProfileStringListEditor
              v-model="form.phone_numbers"
              :label="t('profile.fieldPhones')"
              input-type="text"
              :add-label="t('profile.listAdd')"
              :remove-label="t('profile.listRemove')"
            />
            <ProfileStringListEditor
              v-model="form.contact_emails"
              :label="t('profile.fieldContactEmails')"
              input-type="email"
              :add-label="t('profile.listAdd')"
              :remove-label="t('profile.listRemove')"
            />
            <ProfileStringListEditor
              v-model="form.aliases"
              :label="t('profile.fieldAliases')"
              input-type="text"
              :add-label="t('profile.listAdd')"
              :remove-label="t('profile.listRemove')"
            />
            <ProfileExternalLinksEditor
              v-model="form.external_links"
              :heading="t('profile.fieldExternalLinks')"
              :title-label="t('profile.fieldLinkTitle')"
              :url-label="t('profile.fieldLinkUrl')"
              :add-label="t('profile.linkAdd')"
              :remove-label="t('profile.listRemove')"
            />
          </div>

          <p v-if="saveError" class="user-edit-page__error" role="alert">
            {{ saveError }}
          </p>
          <Button type="submit" variant="primary" :loading="saveLoading">
            {{ t('users.saveUser') }}
          </Button>
        </form>
      </Card>

      <Card v-if="!loading && !loadError" class="user-edit-page__panel user-edit-page__panel--history">
        <h2 class="user-edit-page__subheading">{{ t('users.votingIdHistoryHeading') }}</h2>
        <p v-if="auditsLoading" class="user-edit-page__muted">{{ t('users.loadingAudits') }}</p>
        <p v-else-if="auditsError" class="user-edit-page__error" role="alert">{{ auditsError }}</p>
        <template v-else>
          <p v-if="!auditRows.length" class="user-edit-page__muted">{{ t('users.votingIdHistoryEmpty') }}</p>
          <div v-else class="user-edit-page__auditTableWrap">
            <table class="user-edit-page__auditTable">
              <thead>
                <tr>
                  <th scope="col">{{ t('users.votingIdColWhen') }}</th>
                  <th scope="col">{{ t('users.votingIdColFrom') }}</th>
                  <th scope="col">{{ t('users.votingIdColTo') }}</th>
                  <th scope="col">{{ t('users.votingIdColBy') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in auditRows" :key="row.id">
                  <td>{{ formatAuditWhen(row.created_at) }}</td>
                  <td>{{ auditOldNew(row.old_voting_id) }}</td>
                  <td>{{ auditOldNew(row.new_voting_id) }}</td>
                  <td>{{ auditActorName(row) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div
            v-if="auditMeta && auditMeta.last_page > 1"
            class="user-edit-page__auditPager"
          >
            <Button
              type="button"
              variant="secondary"
              size="sm"
              :disabled="auditPage <= 1 || auditsLoading"
              @click="goAuditPrev"
            >
              {{ t('users.prev') }}
            </Button>
            <span class="user-edit-page__auditPageInfo">
              {{
                t('users.pageInfo')
                  .replace('{current}', String(auditMeta.current_page))
                  .replace('{last}', String(auditMeta.last_page))
              }}
            </span>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              :disabled="auditPage >= auditMeta.last_page || auditsLoading"
              @click="goAuditNext"
            >
              {{ t('users.next') }}
            </Button>
          </div>
        </template>
      </Card>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.user-edit-page {
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 52rem;
  margin: 0 auto;
}

.user-edit-page__muted {
  opacity: 0.8;
  margin: 0;
}

.user-edit-page__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.user-edit-page__btn {
  cursor: pointer;
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
}

.user-edit-page__panel {
  margin-top: 0.25rem;
}

.user-edit-page__form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  align-items: flex-start;
}

.user-edit-page__fields {
  display: grid;
  gap: 0.75rem;
  width: 100%;
  max-width: 28rem;
}

.user-edit-page__contactBlock {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  width: 100%;
  max-width: 36rem;
}

.user-edit-page__subheading {
  margin: 0.5rem 0 0;
  font-size: 1.05rem;
  font-weight: 600;
}

.user-edit-page__hint {
  margin: -0.25rem 0 0;
  font-size: 0.85rem;
  color: var(--muted, #6b7280);
}

.user-edit-page__hint--spaced {
  margin: 0 0 0.35rem;
  max-width: 36rem;
}

.user-edit-page__selectLabel {
  font-weight: 600;
  font-size: 0.9rem;
}

.user-edit-page__select {
  padding: 0.45rem 0.5rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  max-width: 28rem;
  width: 100%;
}

.user-edit-page__checkLabel {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.95rem;
}

.user-edit-page__error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.9rem;
}

.user-edit-page__fieldError {
  margin: -0.35rem 0 0;
  color: #b91c1c;
  font-size: 0.85rem;
}

.user-edit-page__panel--history {
  margin-top: 0.5rem;
}

.user-edit-page__auditTableWrap {
  overflow-x: auto;
  margin-top: 0.5rem;
}

.user-edit-page__auditTable {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
}

.user-edit-page__auditTable th,
.user-edit-page__auditTable td {
  border: 1px solid var(--border);
  padding: 0.45rem 0.5rem;
  text-align: left;
  vertical-align: top;
}

.user-edit-page__auditTable th {
  background: color-mix(in srgb, var(--border) 35%, transparent);
  font-weight: 600;
}

.user-edit-page__auditPager {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  margin-top: 0.75rem;
}

.user-edit-page__auditPageInfo {
  font-size: 0.88rem;
  opacity: 0.85;
}
</style>
