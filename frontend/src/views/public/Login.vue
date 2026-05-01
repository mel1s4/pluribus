<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { t } from '../../i18n/i18n'
import LoginCard from '../../organisms/LoginCard.vue'
import { clearHadAuthenticatedSession } from '../../composables/useSession'

const route = useRoute()
const router = useRouter()
const sessionEndedOpen = ref(false)
const passwordResetOpen = ref(false)

onMounted(() => {
  const nextQuery = { ...route.query }
  let mutated = false

  if (route.query.sessionEnded === '1') {
    sessionEndedOpen.value = true
    clearHadAuthenticatedSession()
    delete nextQuery.sessionEnded
    mutated = true
  }

  if (route.query.reset === '1') {
    passwordResetOpen.value = true
    delete nextQuery.reset
    mutated = true
  }

  if (mutated) {
    router.replace({ path: route.path, query: nextQuery })
  }
})
</script>

<template>
  <section class="page page--login">
    <div class="page--login__inner">
      <div
        v-if="sessionEndedOpen"
        class="page--login__notice"
        role="alert"
      >
        <p class="page--login__notice-title">
          {{ t('login.sessionEndedTitle') }}
        </p>
        <p class="page--login__notice-body">
          {{ t('login.sessionEndedBody') }}
        </p>
        <button
          type="button"
          class="page--login__notice-dismiss"
          @click="sessionEndedOpen = false"
        >
          {{ t('login.sessionEndedDismiss') }}
        </button>
      </div>
      <div
        v-if="passwordResetOpen"
        class="page--login__notice page--login__notice--success"
        role="status"
      >
        <p class="page--login__notice-title">
          {{ t('login.passwordChangedTitle') }}
        </p>
        <p class="page--login__notice-body">
          {{ t('login.passwordChangedBody') }}
        </p>
        <button
          type="button"
          class="page--login__notice-dismiss"
          @click="passwordResetOpen = false"
        >
          {{ t('login.sessionEndedDismiss') }}
        </button>
      </div>
      <LoginCard />
    </div>
  </section>
</template>

<style lang="scss" scoped>
.page--login {
  min-height: calc(100vh - 4rem);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.page--login__inner {
  width: 100%;
  max-width: 440px;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.page--login__notice {
  border-radius: 0.5rem;
  padding: 1rem 1.25rem;
  background: var(--color-surface-elevated, rgba(0, 0, 0, 0.04));
  border: 1px solid var(--color-border, rgba(0, 0, 0, 0.12));
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
}

.page--login__notice--success {
  border-color: rgba(21, 128, 61, 0.4);
  background: rgba(21, 128, 61, 0.08);
}

.page--login__notice-title {
  margin: 0 0 0.35rem;
  font-size: 1rem;
  font-weight: 600;
}

.page--login__notice-body {
  margin: 0 0 0.75rem;
  font-size: 0.875rem;
  line-height: 1.45;
  color: var(--color-text-muted, inherit);
}

.page--login__notice-dismiss {
  font: inherit;
  font-size: 0.8125rem;
  padding: 0.35rem 0.75rem;
  border-radius: 0.375rem;
  border: 1px solid var(--color-border, rgba(0, 0, 0, 0.15));
  background: var(--color-surface, transparent);
  cursor: pointer;
}

.page--login__notice-dismiss:hover {
  background: var(--color-surface-hover, rgba(0, 0, 0, 0.04));
}
</style>
