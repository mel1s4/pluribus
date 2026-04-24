<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Input from '../../atoms/Input.vue'
import Title from '../../atoms/Title.vue'
import { communityDefaultLanguage } from '../../composables/useCommunity'
import { setSessionFromLoginUser } from '../../composables/useSession'
import {
  applyJoinInvitationPageLanguage,
  clearJoinInvitationPageLanguage,
  t,
} from '../../i18n/i18n'
import { DEFAULT_LANGUAGE, isSupportedLanguage } from '../../i18n/locales'
import { apiJson, ensureCsrfCookie } from '../../services/api'
import { userApiErrorMessage } from '../../services/usersApi.js'

const route = useRoute()
const router = useRouter()

const token = computed(() => (typeof route.params.token === 'string' ? route.params.token : ''))

const previewLoading = ref(true)
const previewError = ref('')
const preview = ref(null)

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})
const registerError = ref('')
const registerLoading = ref(false)

const tokenPreview = computed(() => {
  const raw = token.value
  if (raw.length <= 10) {
    return raw
  }
  return `${raw.slice(0, 6)}…${raw.slice(-4)}`
})

const reasonMessage = computed(() => {
  const r = preview.value?.reason
  if (typeof r !== 'string' || !r.length) {
    return ''
  }
  const key = `joinInvitation.reason.${r}`
  const msg = t(key)
  return msg === key ? t('joinInvitation.reason.unknown') : msg
})

const canRegister = computed(() => preview.value?.valid === true)

function pickLanguageForInvitePage(data) {
  const fromApi = data?.default_language
  if (typeof fromApi === 'string' && isSupportedLanguage(fromApi)) {
    return fromApi
  }
  const fromBranding = communityDefaultLanguage.value
  if (typeof fromBranding === 'string' && isSupportedLanguage(fromBranding)) {
    return fromBranding
  }
  return DEFAULT_LANGUAGE
}

function applyPreview(data) {
  preview.value = data
  if (data?.valid === true && data.locked_email === true && typeof data.email === 'string') {
    form.email = data.email
  }
}

async function loadPreview() {
  previewLoading.value = true
  previewError.value = ''
  preview.value = null
  if (!token.value) {
    previewLoading.value = false
    previewError.value = t('joinInvitation.errorNoToken')
    return
  }
  const path = `/api/join-invitations/${encodeURIComponent(token.value)}`
  const { ok, status, data } = await apiJson('GET', path)
  previewLoading.value = false
  if (!ok) {
    previewError.value = userApiErrorMessage(data, status, t('joinInvitation.loadError'))
    return
  }
  applyPreview(data)
}

async function onRegister() {
  registerError.value = ''
  registerLoading.value = true
  await ensureCsrfCookie()
  const path = `/api/join-invitations/${encodeURIComponent(token.value)}/register`
  const body = {
    name: form.name.trim(),
    email: form.email.trim(),
    password: form.password,
    password_confirmation: form.password_confirmation,
  }
  const { ok, status, data } = await apiJson('POST', path, body)
  registerLoading.value = false
  if (ok && data?.user) {
    setSessionFromLoginUser(data.user)
    await router.replace('/dashboard')
    return
  }
  registerError.value = userApiErrorMessage(
    data,
    status,
    t('joinInvitation.registerError'),
  )
}

onMounted(async () => {
  await applyJoinInvitationPageLanguage(pickLanguageForInvitePage(null))
  loadPreview()
})

watch(preview, async (p) => {
  if (p !== null) {
    await applyJoinInvitationPageLanguage(pickLanguageForInvitePage(p))
  }
})

onUnmounted(() => {
  clearJoinInvitationPageLanguage()
})
</script>

<template>
  <section class="page page--join-invitation">
    <Title tag="h1">{{ t('joinInvitation.title') }}</Title>
    <p class="page--join-invitation__lead">{{ t('joinInvitation.lead') }}</p>
    <p v-if="tokenPreview" class="page--join-invitation__token">
      {{ t('joinInvitation.tokenLabel') }} <code>{{ tokenPreview }}</code>
    </p>

    <p v-if="previewLoading" class="page--join-invitation__muted">{{ t('joinInvitation.loading') }}</p>
    <p v-else-if="previewError" class="page--join-invitation__error" role="alert">
      {{ previewError }}
    </p>
    <template v-else-if="preview && !preview.valid">
      <p class="page--join-invitation__error" role="alert">{{ reasonMessage }}</p>
      <p class="page--join-invitation__muted">{{ t('joinInvitation.invalidHelp') }}</p>
    </template>

    <Card v-else-if="canRegister" class="page--join-invitation__card">
      <p v-if="preview.community_name" class="page--join-invitation__community">
        {{ t('joinInvitation.communityLabel') }} <strong>{{ preview.community_name }}</strong>
      </p>
      <p v-if="preview.uses_remaining === null" class="page--join-invitation__uses">
        {{ t('joinInvitation.usesUnlimited') }}
      </p>
      <p v-else class="page--join-invitation__uses">
        {{
          t('joinInvitation.usesRemaining').replace('{n}', String(preview.uses_remaining))
        }}
      </p>

      <form class="page--join-invitation__form" @submit.prevent="onRegister">
        <Input
          v-model="form.name"
          name="join-name"
          type="text"
          :label="t('users.fieldName')"
          autocomplete="name"
          required
          :disabled="registerLoading"
        />
        <Input
          v-model="form.email"
          name="join-email"
          type="email"
          :label="t('users.fieldEmail')"
          autocomplete="email"
          required
          :disabled="registerLoading || preview.locked_email === true"
        />
        <Input
          v-model="form.password"
          name="join-password"
          type="password"
          :label="t('users.fieldPassword')"
          autocomplete="new-password"
          required
          :disabled="registerLoading"
        />
        <Input
          v-model="form.password_confirmation"
          name="join-password-confirm"
          type="password"
          :label="t('joinInvitation.fieldPasswordConfirm')"
          autocomplete="new-password"
          required
          :disabled="registerLoading"
        />
        <p v-if="registerError" class="page--join-invitation__error" role="alert">
          {{ registerError }}
        </p>
        <Button type="submit" variant="primary" :loading="registerLoading">
          {{ t('joinInvitation.createAccount') }}
        </Button>
      </form>
    </Card>
  </section>
</template>

<style lang="scss" scoped>
.page--join-invitation {
  padding: 2rem;
  max-width: 28rem;
  margin: 0 auto;
}

.page--join-invitation__lead {
  margin: 0 0 0.75rem;
  line-height: 1.5;
}

.page--join-invitation__token {
  margin: 0 0 1rem;
  font-size: 0.95rem;
}

.page--join-invitation__token code {
  font-size: 0.85rem;
  padding: 0.15rem 0.35rem;
  border-radius: 0.25rem;
  background: var(--table-head, rgba(0, 0, 0, 0.05));
}

.page--join-invitation__muted {
  margin: 0 0 1rem;
  color: var(--muted, #6b7280);
  font-size: 0.95rem;
}

.page--join-invitation__error {
  margin: 0 0 1rem;
  color: #b91c1c;
  font-size: 0.95rem;
}

.page--join-invitation__card {
  margin-top: 0.5rem;
}

.page--join-invitation__community {
  margin: 0 0 0.5rem;
  font-size: 0.95rem;
}

.page--join-invitation__uses {
  margin: 0 0 1rem;
  font-size: 0.9rem;
  color: var(--muted, #4b5563);
}

.page--join-invitation__form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
</style>
