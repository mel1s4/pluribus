<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { t } from '../i18n/i18n'
import { ApiTimeoutError } from '../services/api'
import Card from '../atoms/Card.vue'
import Title from '../atoms/Title.vue'
import LoginForm from '../molecules/LoginForm.vue'
import { isCommunityHostSite } from '../composables/useCommunityHost'
import { useCommunity } from '../composables/useCommunity'
import { loginRequest, requestVisitorLoginLink, setSessionFromLoginUser } from '../composables/useSession'

const route = useRoute()
const router = useRouter()
const { displayName } = useCommunity()

const submitting = ref(false)
const emailError = ref('')
const formError = ref('')
const visitorLinkSent = ref('')

function defaultPostLoginPath(user) {
  if (isCommunityHostSite.value === true) {
    return typeof route.query.redirect === 'string' && route.query.redirect
      ? route.query.redirect
      : '/'
  }
  const communityCount = Number(user?.community_count || 0)
  if (communityCount <= 0) return '/my-communities'
  const first = Array.isArray(user?.communities) ? user.communities[0] : null
  if (first?.slug) return `/community/${first.slug}/dashboard`
  return '/dashboard'
}

function pickEmailError(data) {
  if (!data || typeof data !== 'object') {
    return ''
  }
  if ('errors' in data && data.errors && typeof data.errors === 'object') {
    const e = data.errors.email
    if (Array.isArray(e) && e.length) {
      return e[0]
    }
  }
  if ('message' in data && typeof data.message === 'string') {
    return data.message
  }
  return ''
}

async function onSubmit(payload, intent = 'member') {
  if (submitting.value) {
    return
  }

  emailError.value = ''
  formError.value = ''
  visitorLinkSent.value = ''
  submitting.value = true

  try {
    const { ok, status, data } = await loginRequest({
      ...payload,
      intent: isCommunityHostSite.value ? intent : undefined,
    })
    if (ok && data?.user) {
      setSessionFromLoginUser(data.user, data.personification)
      const target =
        typeof route.query.redirect === 'string' && route.query.redirect
          ? route.query.redirect
          : defaultPostLoginPath(data.user)
      await router.replace(target)
      return
    }
    if (status === 422) {
      emailError.value = pickEmailError(data) || t('login.errorInvalid')
      return
    }
    if (status === 429) {
      formError.value = t('login.errorRateLimit')
      return
    }
    formError.value = t('login.errorGeneric')
  } catch (error) {
    if (error instanceof ApiTimeoutError) {
      formError.value = t('login.errorTimeout')
      return
    }
    formError.value = t('login.errorGeneric')
  } finally {
    submitting.value = false
  }
}

async function onRequestVisitorLink(payload) {
  const email = typeof payload?.email === 'string' ? payload.email.trim() : ''
  if (!email) {
    emailError.value = t('login.emailPlaceholder')
    return
  }
  visitorLinkSent.value = ''
  formError.value = ''
  submitting.value = true
  const { ok } = await requestVisitorLoginLink({ email })
  submitting.value = false
  if (ok) {
    visitorLinkSent.value = t('login.visitorLinkSent')
  } else {
    formError.value = t('login.errorGeneric')
  }
}

async function onGuestLink(payload) {
  await onRequestVisitorLink(payload)
}
</script>

<template>
  <Card>
    <Title tag="h1">{{ isCommunityHostSite ? displayName : t('login.title') }}</Title>
    <p v-if="isCommunityHostSite" class="login-card__hostHint">{{ t('login.communityHostHint') }}</p>
    <p v-if="formError" class="login-card__formError">{{ formError }}</p>
    <LoginForm
      :submitting="submitting"
      :email-error="emailError"
      :show-guest-access="isCommunityHostSite"
      @submit="(payload) => onSubmit(payload, 'member')"
      @visitor-link="onRequestVisitorLink"
      @guest-link="onGuestLink"
    />
    <p v-if="visitorLinkSent" class="login-card__visitorLinkSent">{{ visitorLinkSent }}</p>
  </Card>
</template>

<style lang="scss" scoped>
.login-card__hostHint {
  color: var(--color-text-muted, #6b7280);
  font-size: 0.875rem;
  margin: 0 0 0.75rem;
  line-height: 1.45;
}

.login-card__formError {
  color: var(--color-danger, #b91c1c);
  font-size: 0.875rem;
  margin: 0 0 0.75rem;
}

.login-card__visitorLinkSent {
  color: #0f766e;
  font-size: 0.875rem;
  margin: 0.75rem 0 0;
}
</style>
