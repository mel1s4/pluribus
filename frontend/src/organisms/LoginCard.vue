<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { t } from '../i18n/i18n'
import { ApiTimeoutError } from '../services/api'
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
  // #region agent log
  fetch('http://127.0.0.1:7800/ingest/b3c811d3-7ec8-4727-aae6-1a8e45b40a1e', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': '808933' },
    body: JSON.stringify({
      sessionId: '808933',
      runId: 'login-hang-v1',
      hypothesisId: 'H1',
      location: 'LoginCard.vue:onSubmit:entry',
      message: 'login submit start',
      data: {
        hasRedirectQuery: typeof route.query.redirect === 'string' && route.query.redirect.length > 0,
      },
      timestamp: Date.now(),
    }),
  }).catch(() => {})
  // #endregion
  if (submitting.value) {
    return
  }

  emailError.value = ''
  formError.value = ''
  visitorLinkSent.value = ''
  submitting.value = true

  try {
    const { ok, status, data } = await loginRequest(payload)
    // #region agent log
    fetch('http://127.0.0.1:7800/ingest/b3c811d3-7ec8-4727-aae6-1a8e45b40a1e', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': '808933' },
      body: JSON.stringify({
        sessionId: '808933',
        runId: 'login-hang-v1',
        hypothesisId: 'H1',
        location: 'LoginCard.vue:onSubmit:response',
        message: 'login response received',
        data: {
          ok,
          status,
          hasUser: Boolean(data && data.user),
          hasPersonification: Boolean(data && data.personification),
        },
        timestamp: Date.now(),
      }),
    }).catch(() => {})
    // #endregion
    if (ok && data?.user) {
      setSessionFromLoginUser(data.user, data.personification)
      const target =
        typeof route.query.redirect === 'string' && route.query.redirect
          ? route.query.redirect
          : '/dashboard'
      await router.replace(target)
      // #region agent log
      fetch('http://127.0.0.1:7800/ingest/b3c811d3-7ec8-4727-aae6-1a8e45b40a1e', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': '808933' },
        body: JSON.stringify({
          sessionId: '808933',
          runId: 'login-hang-v1',
          hypothesisId: 'H5',
          location: 'LoginCard.vue:onSubmit:postReplace',
          message: 'router replace completed after login',
          data: {
            target,
          },
          timestamp: Date.now(),
        }),
      }).catch(() => {})
      // #endregion
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
