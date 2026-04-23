<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Input from '../../atoms/Input.vue'
import Title from '../../atoms/Title.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { resolveSession, sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { fetchUser, updateUser, userApiErrorMessage } from '../../services/usersApi.js'

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
})

const loadError = ref('')
const loading = ref(true)
const saveError = ref('')
const saveLoading = ref(false)

const assignTypes = computed(() => hasCapability('users.assign_types'))

const userId = computed(() => {
  const raw = route.params.userId
  return typeof raw === 'string' ? raw : ''
})

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
}

watch(userId, () => {
  load()
})

watch(
  () => form.is_root,
  (root) => {
    if (!root && form.user_type === 'root') {
      form.user_type = 'member'
    }
  },
)

load()

async function onSubmit() {
  const id = userId.value
  if (!id) return
  saveError.value = ''
  saveLoading.value = true
  const body = {
    name: form.name.trim(),
    email: form.email.trim(),
    username: form.username.trim() === '' ? null : form.username.trim(),
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

    <Card v-else class="user-edit-page__panel">
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
        <p v-if="saveError" class="user-edit-page__error" role="alert">
          {{ saveError }}
        </p>
        <Button type="submit" variant="primary" :loading="saveLoading">
          {{ t('users.saveUser') }}
        </Button>
      </form>
    </Card>
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

.user-edit-page__hint {
  margin: -0.25rem 0 0;
  font-size: 0.85rem;
  color: var(--muted, #6b7280);
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
</style>
