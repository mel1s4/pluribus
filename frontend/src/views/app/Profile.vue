<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Input from '../../atoms/Input.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import ProfileAvatarBlock from '../../molecules/ProfileAvatarBlock.vue'
import ProfileExternalLinksEditor from '../../molecules/ProfileExternalLinksEditor.vue'
import ProfileStringListEditor from '../../molecules/ProfileStringListEditor.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { resolveSession, sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import {
  deleteProfileAvatar,
  profileApiErrorMessage,
  updateProfile,
  uploadProfileAvatar,
} from '../../services/profileApi.js'

const canEdit = computed(() => hasCapability('profile.update'))

const form = reactive({
  name: '',
  email: '',
  username: '',
  password: '',
  password_confirmation: '',
  current_password: '',
  phone_numbers: [],
  contact_emails: [],
  aliases: [],
  external_links: [],
})

const loadError = ref('')
const loading = ref(true)
const saveError = ref('')
const saveLoading = ref(false)
const saveOk = ref(false)
const usernameFieldError = ref('')
const avatarUploading = ref(false)
const avatarRemoving = ref(false)
const avatarError = ref('')

const avatarUrl = computed(() => sessionUser.value?.avatar_url ?? null)

const roleLabel = computed(() => {
  const u = sessionUser.value
  if (!u) {
    return ''
  }
  if (u.is_root) {
    return t('users.typeRootLocked')
  }
  if (u.user_type === 'admin') {
    return t('users.typeAdmin')
  }
  if (u.user_type === 'developer') {
    return t('users.typeDeveloper')
  }
  return t('users.typeMember')
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

function populateForm() {
  const u = sessionUser.value
  if (!u) {
    return
  }
  form.name = u.name ?? ''
  form.email = u.email ?? ''
  form.username = u.username ?? ''
  form.password = ''
  form.password_confirmation = ''
  form.current_password = ''
  form.phone_numbers = cloneStringList(u.phone_numbers)
  form.contact_emails = cloneStringList(u.contact_emails)
  form.aliases = cloneStringList(u.aliases)
  form.external_links = cloneExternalLinks(u.external_links)
}

watch(
  () => form.username,
  () => {
    usernameFieldError.value = ''
  },
)

onMounted(async () => {
  loadError.value = ''
  loading.value = true
  try {
    await resolveSession()
    if (!sessionUser.value) {
      loadError.value = t('profile.loadError')
    } else {
      populateForm()
    }
  } catch {
    loadError.value = t('profile.loadError')
  } finally {
    loading.value = false
  }
})

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

async function onSaveProfile() {
  if (!canEdit.value) {
    return
  }
  saveError.value = ''
  usernameFieldError.value = ''
  saveOk.value = false
  saveLoading.value = true
  const body = {
    name: form.name.trim(),
    email: form.email.trim(),
    username: form.username.trim() === '' ? null : form.username.trim(),
    phone_numbers: form.phone_numbers,
    contact_emails: form.contact_emails,
    aliases: form.aliases,
    external_links: form.external_links,
  }
  if (form.password.trim()) {
    body.password = form.password
    body.password_confirmation = form.password_confirmation
    body.current_password = form.current_password
  }
  const { ok, status, data } = await updateProfile(body)
  saveLoading.value = false
  if (!ok) {
    setUsernameFieldErrorFromResponse(data, status)
    saveError.value = profileApiErrorMessage(data, status, t('profile.saveError'))
    return
  }
  form.password = ''
  form.password_confirmation = ''
  form.current_password = ''
  saveOk.value = true
  window.setTimeout(() => {
    saveOk.value = false
  }, 4000)
  await resolveSession()
  populateForm()
}

async function onAvatarUpload(file) {
  avatarError.value = ''
  avatarUploading.value = true
  const { ok, status, data } = await uploadProfileAvatar(file)
  avatarUploading.value = false
  if (!ok) {
    avatarError.value = profileApiErrorMessage(data, status, t('profile.avatarError'))
    return
  }
  await resolveSession()
}

async function onAvatarRemove() {
  avatarError.value = ''
  avatarRemoving.value = true
  const { ok, status, data } = await deleteProfileAvatar()
  avatarRemoving.value = false
  if (!ok) {
    avatarError.value = profileApiErrorMessage(data, status, t('profile.avatarError'))
    return
  }
  await resolveSession()
}
</script>

<template>
  <section class="profile-page">
    <PageToolbarTitle route-key="profile">
      <Title tag="h1">{{ t('profile.title') }}</Title>
    </PageToolbarTitle>
    <p class="profile-page__intro">{{ t('profile.intro') }}</p>

    <p v-if="loadError" class="profile-page__error" role="alert">
      {{ loadError }}
    </p>
    <p v-else-if="loading" class="profile-page__muted">{{ t('profile.loading') }}</p>

    <template v-else>
      <p v-if="!canEdit" class="profile-page__muted profile-page__readOnly">
        {{ t('profile.readOnly') }}
      </p>

      <form class="profile-page__saveForm" @submit.prevent="onSaveProfile">
        <Card class="profile-page__card">
          <h2 class="profile-page__cardTitle">{{ t('profile.accountHeading') }}</h2>
          <p class="profile-page__muted profile-page__roleRow">
            <span class="profile-page__roleLabel">{{ t('profile.roleLabel') }}</span>
            {{ roleLabel }}
          </p>
          <div class="profile-page__fields">
            <Input
              v-model="form.name"
              name="profile-name"
              type="text"
              :label="t('users.fieldName')"
              autocomplete="name"
              :disabled="!canEdit"
              required
            />
            <Input
              v-model="form.email"
              name="profile-email"
              type="email"
              :label="t('users.fieldEmail')"
              autocomplete="email"
              :disabled="!canEdit"
              required
            />
            <Input
              v-model="form.username"
              name="profile-username"
              type="text"
              :label="t('users.fieldUsername')"
              autocomplete="username"
              :disabled="!canEdit"
            />
            <p v-if="usernameFieldError" class="profile-page__fieldError" role="alert">
              {{ usernameFieldError }}
            </p>
          </div>

          <h2 class="profile-page__cardTitle profile-page__cardTitle--spaced">
            {{ t('profile.contactHeading') }}
          </h2>
          <p class="profile-page__hint profile-page__hint--block">{{ t('profile.contactIntro') }}</p>
          <div class="profile-page__contactBlock">
            <ProfileStringListEditor
              v-model="form.phone_numbers"
              :label="t('profile.fieldPhones')"
              input-type="text"
              :add-label="t('profile.listAdd')"
              :remove-label="t('profile.listRemove')"
              :disabled="!canEdit"
            />
            <ProfileStringListEditor
              v-model="form.contact_emails"
              :label="t('profile.fieldContactEmails')"
              input-type="email"
              :add-label="t('profile.listAdd')"
              :remove-label="t('profile.listRemove')"
              :disabled="!canEdit"
            />
            <ProfileStringListEditor
              v-model="form.aliases"
              :label="t('profile.fieldAliases')"
              input-type="text"
              :add-label="t('profile.listAdd')"
              :remove-label="t('profile.listRemove')"
              :disabled="!canEdit"
            />
            <ProfileExternalLinksEditor
              v-model="form.external_links"
              :heading="t('profile.fieldExternalLinks')"
              :title-label="t('profile.fieldLinkTitle')"
              :url-label="t('profile.fieldLinkUrl')"
              :add-label="t('profile.linkAdd')"
              :remove-label="t('profile.listRemove')"
              :disabled="!canEdit"
            />
          </div>

          <h2 class="profile-page__cardTitle profile-page__cardTitle--spaced">
            {{ t('profile.passwordHeading') }}
          </h2>
          <p class="profile-page__hint">{{ t('profile.passwordHint') }}</p>
          <div class="profile-page__fields">
            <Input
              v-model="form.password"
              name="profile-password-new"
              type="password"
              :label="t('profile.fieldPasswordNew')"
              autocomplete="new-password"
              :disabled="!canEdit"
            />
            <Input
              v-model="form.password_confirmation"
              name="profile-password-confirm"
              type="password"
              :label="t('profile.fieldPasswordConfirm')"
              autocomplete="new-password"
              :disabled="!canEdit"
            />
            <Input
              v-model="form.current_password"
              name="profile-password-current"
              type="password"
              :label="t('profile.fieldCurrentPassword')"
              autocomplete="current-password"
              :disabled="!canEdit"
            />
          </div>

          <p v-if="saveError" class="profile-page__error" role="alert">
            {{ saveError }}
          </p>
          <p v-if="saveOk" class="profile-page__success" role="status">
            {{ t('profile.saveSuccess') }}
          </p>
          <Button v-if="canEdit" type="submit" variant="primary" :loading="saveLoading">
            {{ t('profile.saveProfile') }}
          </Button>
        </Card>
      </form>

      <Card class="profile-page__card">
        <h2 class="profile-page__cardTitle">{{ t('profile.avatarHeading') }}</h2>
        <ProfileAvatarBlock
          :avatar-url="avatarUrl"
          :disabled="!canEdit"
          :uploading="avatarUploading"
          :removing="avatarRemoving"
          :error="avatarError"
          :upload-label="t('profile.avatarUpload')"
          :remove-label="t('profile.avatarRemove')"
          :hint="t('profile.avatarHint')"
          @upload="onAvatarUpload"
          @remove="onAvatarRemove"
        />
      </Card>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.profile-page {
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 52rem;
  margin: 0 auto;
}

.profile-page__saveForm {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.profile-page__intro {
  margin: 0;
  opacity: 0.85;
}

.profile-page__muted {
  margin: 0;
  opacity: 0.8;
}

.profile-page__readOnly {
  margin-top: 0.25rem;
}

.profile-page__card {
  margin-top: 0.25rem;
}

.profile-page__cardTitle {
  margin: 0 0 0.5rem;
  font-size: 1.05rem;
  font-weight: 600;
}

.profile-page__cardTitle--spaced {
  margin-top: 1.25rem;
}

.profile-page__roleRow {
  margin: 0 0 1rem;
}

.profile-page__roleLabel {
  font-weight: 600;
  margin-right: 0.35rem;
}

.profile-page__fields {
  display: grid;
  gap: 0.75rem;
  width: 100%;
  max-width: 28rem;
}

.profile-page__contactBlock {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  width: 100%;
  max-width: 36rem;
}

.profile-page__hint {
  margin: -0.25rem 0 0;
  font-size: 0.85rem;
  color: var(--muted, #6b7280);
  max-width: 36rem;
}

.profile-page__hint--block {
  margin: 0 0 0.5rem;
}

.profile-page__error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.9rem;
}

.profile-page__fieldError {
  margin: -0.35rem 0 0;
  color: #b91c1c;
  font-size: 0.85rem;
}

.profile-page__success {
  margin: 0;
  color: #047857;
  font-size: 0.9rem;
}
</style>
