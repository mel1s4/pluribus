<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Input from '../../atoms/Input.vue'
import Title from '../../atoms/Title.vue'
import ProfileAvatarBlock from '../../molecules/ProfileAvatarBlock.vue'
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
})

const loadError = ref('')
const loading = ref(true)
const saveError = ref('')
const saveLoading = ref(false)
const saveOk = ref(false)
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
}

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

async function onSaveProfile() {
  if (!canEdit.value) {
    return
  }
  saveError.value = ''
  saveOk.value = false
  saveLoading.value = true
  const body = {
    name: form.name.trim(),
    email: form.email.trim(),
    username: form.username.trim() === '' ? null : form.username.trim(),
  }
  if (form.password.trim()) {
    body.password = form.password
    body.password_confirmation = form.password_confirmation
    body.current_password = form.current_password
  }
  const { ok, status, data } = await updateProfile(body)
  saveLoading.value = false
  if (!ok) {
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
    <Title tag="h1">{{ t('profile.title') }}</Title>
    <p class="profile-page__intro">{{ t('profile.intro') }}</p>

    <p v-if="loadError" class="profile-page__error" role="alert">
      {{ loadError }}
    </p>
    <p v-else-if="loading" class="profile-page__muted">{{ t('profile.loading') }}</p>

    <template v-else>
      <p v-if="!canEdit" class="profile-page__muted profile-page__readOnly">
        {{ t('profile.readOnly') }}
      </p>

      <Card class="profile-page__card">
        <h2 class="profile-page__cardTitle">{{ t('profile.accountHeading') }}</h2>
        <p class="profile-page__muted profile-page__roleRow">
          <span class="profile-page__roleLabel">{{ t('profile.roleLabel') }}</span>
          {{ roleLabel }}
        </p>
        <form class="profile-page__form" @submit.prevent="onSaveProfile">
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
        </form>
      </Card>

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

.profile-page__form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  align-items: flex-start;
}

.profile-page__fields {
  display: grid;
  gap: 0.75rem;
  width: 100%;
  max-width: 28rem;
}

.profile-page__hint {
  margin: -0.25rem 0 0;
  font-size: 0.85rem;
  color: var(--muted, #6b7280);
  max-width: 28rem;
}

.profile-page__error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.9rem;
}

.profile-page__success {
  margin: 0;
  color: #047857;
  font-size: 0.9rem;
}
</style>
