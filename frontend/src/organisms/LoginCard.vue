<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { t } from '../i18n/i18n'
import Card from '../atoms/Card.vue'
import Title from '../atoms/Title.vue'
import LoginForm from '../molecules/LoginForm.vue'
import { loginRequest, requestVisitorLoginLink, setSessionFromLoginUser } from '../composables/useSession'

const route = useRoute()
const router = useRouter()

const submitting = ref(false)
const emailError = ref('')
const formError = ref('')
const visitorLinkSent = ref('')

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

async function onSubmit(payload) {
  if (submitting.value) {
    return
  }

  emailError.value = ''
  formError.value = ''
  visitorLinkSent.value = ''
  submitting.value = true

  try {
    const { ok, status, data } = await loginRequest(payload)
    if (ok && data?.user) {
      setSessionFromLoginUser(data.user)
      const target =
        typeof route.query.redirect === 'string' && route.query.redirect
          ? route.query.redirect
          : '/dashboard'
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
  const { ok } = await requestVisitorLoginLink({ email })
  if (ok) {
    visitorLinkSent.value = 'Visitor login link sent to your email.'
  } else {
    formError.value = t('login.errorGeneric')
  }
}
</script>

<template>
  <Card>
    <Title tag="h1">{{ t('login.title') }}</Title>
    <p v-if="formError" class="login-card__formError">{{ formError }}</p>
    <LoginForm
      :submitting="submitting"
      :email-error="emailError"
      @submit="onSubmit"
      @visitor-link="onRequestVisitorLink"
    />
    <p v-if="visitorLinkSent" class="login-card__visitorLinkSent">{{ visitorLinkSent }}</p>
  </Card>
</template>

<style lang="scss" scoped>
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
