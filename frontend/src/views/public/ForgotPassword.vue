<script setup>
import { ref } from 'vue'
import { t } from '../../i18n/i18n'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import ForgotPasswordForm from '../../molecules/ForgotPasswordForm.vue'
import { requestPasswordReset } from '../../composables/useSession'

const submitting = ref(false)
const submitted = ref(false)
const formError = ref('')

async function onSubmit({ email }) {
  if (submitting.value) {
    return
  }

  formError.value = ''
  submitting.value = true

  try {
    const { ok, status } = await requestPasswordReset({ email })
    if (status === 429) {
      formError.value = t('passwordReset.errorRateLimit')
      return
    }
    if (!ok) {
      // We never reveal whether the address was recognized; treat anything
      // unexpected as a generic error rather than success.
      formError.value = t('passwordReset.errorGeneric')
      return
    }
    submitted.value = true
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <section class="page page--forgot">
    <div class="page--forgot__inner">
      <Card>
        <Title tag="h1">{{ t('passwordReset.requestTitle') }}</Title>

        <p v-if="formError" class="page--forgot__error">{{ formError }}</p>

        <p v-if="!submitted" class="page--forgot__hint">
          {{ t('passwordReset.requestHint') }}
        </p>

        <ForgotPasswordForm
          v-if="!submitted"
          :submitting="submitting"
          @submit="onSubmit"
        />

        <div v-else class="page--forgot__success">
          <p>{{ t('passwordReset.requestSent') }}</p>
          <p class="page--forgot__success-tip">{{ t('passwordReset.requestSentTip') }}</p>
          <router-link class="page--forgot__back" to="/login">
            {{ t('passwordReset.backToLogin') }}
          </router-link>
        </div>
      </Card>
    </div>
  </section>
</template>

<style scoped lang="scss">
.page--forgot {
  min-height: calc(100vh - 4rem);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.page--forgot__inner {
  width: 100%;
  max-width: 440px;
}

.page--forgot__error {
  color: var(--color-danger, #b91c1c);
  font-size: 0.875rem;
  margin: 0 0 0.75rem;
}

.page--forgot__hint {
  font-size: 0.875rem;
  color: var(--color-text-muted, inherit);
  margin: 0 0 1rem;
}

.page--forgot__success {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.page--forgot__success-tip {
  font-size: 0.8125rem;
  color: var(--color-text-muted, inherit);
  margin: 0;
}

.page--forgot__back {
  margin-top: 1rem;
  font-size: 0.875rem;
  align-self: flex-start;
}
</style>
