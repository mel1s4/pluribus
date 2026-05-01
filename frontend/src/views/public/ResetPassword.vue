<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { t } from '../../i18n/i18n'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import ResetPasswordForm from '../../molecules/ResetPasswordForm.vue'
import { resetPassword } from '../../composables/useSession'

const route = useRoute()
const router = useRouter()

const submitting = ref(false)
const formError = ref('')
const passwordError = ref('')
const emailError = ref('')

const token = computed(() => {
  const value = route.params.token
  return typeof value === 'string' ? value : ''
})

function pickFieldError(data, field) {
  if (!data || typeof data !== 'object') {
    return ''
  }
  if ('errors' in data && data.errors && typeof data.errors === 'object') {
    const arr = data.errors[field]
    if (Array.isArray(arr) && arr.length) {
      return arr[0]
    }
  }
  return ''
}

async function onSubmit(payload) {
  if (submitting.value) {
    return
  }
  if (!token.value) {
    formError.value = t('passwordReset.errorInvalidLink')
    return
  }

  submitting.value = true
  formError.value = ''
  passwordError.value = ''
  emailError.value = ''

  try {
    const { ok, status, data } = await resetPassword({
      token: token.value,
      email: payload.email,
      password: payload.password,
      password_confirmation: payload.password_confirmation,
    })

    if (ok) {
      await router.replace({ name: 'login', query: { reset: '1' } })
      return
    }

    if (status === 429) {
      formError.value = t('passwordReset.errorRateLimit')
      return
    }

    if (status === 422) {
      const tokenErr = pickFieldError(data, 'token')
      const emailErr = pickFieldError(data, 'email')
      const passwordErr = pickFieldError(data, 'password')

      if (tokenErr) {
        formError.value = t('passwordReset.errorInvalidLink')
      } else if (emailErr) {
        emailError.value = emailErr
      }
      if (passwordErr) {
        passwordError.value = passwordErr
      } else if (!tokenErr && !emailErr) {
        formError.value = t('passwordReset.errorGeneric')
      }
      return
    }

    formError.value = t('passwordReset.errorGeneric')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <section class="page page--reset">
    <div class="page--reset__inner">
      <Card>
        <Title tag="h1">{{ t('passwordReset.resetTitle') }}</Title>

        <p class="page--reset__hint">{{ t('passwordReset.resetHint') }}</p>

        <p v-if="formError" class="page--reset__error" role="alert">
          {{ formError }}
        </p>

        <ResetPasswordForm
          :submitting="submitting"
          :password-error="passwordError"
          :email-error="emailError"
          @submit="onSubmit"
        />

        <p class="page--reset__back">
          <router-link to="/login">{{ t('passwordReset.backToLogin') }}</router-link>
        </p>
      </Card>
    </div>
  </section>
</template>

<style scoped lang="scss">
.page--reset {
  min-height: calc(100vh - 4rem);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.page--reset__inner {
  width: 100%;
  max-width: 480px;
}

.page--reset__hint {
  font-size: 0.875rem;
  color: var(--color-text-muted, inherit);
  margin: 0 0 1rem;
}

.page--reset__error {
  color: var(--color-danger, #b91c1c);
  font-size: 0.875rem;
  margin: 0 0 0.75rem;
}

.page--reset__back {
  margin: 1rem 0 0;
  font-size: 0.875rem;
}
</style>
