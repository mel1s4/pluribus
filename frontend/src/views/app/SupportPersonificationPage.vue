<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { apiJson, ensureCsrfCookie } from '../../services/api'
import { resolveSession, sessionPersonification, sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'

const route = useRoute()
const router = useRouter()

/** Effective session user lacks `users.personify` while personifying a member — hide staff tools in UI. */
const showPersonificationTools = computed(() => hasCapability('users.personify'))

const resolveEmail = ref('')
const resolveUsername = ref('')
const resolving = ref(false)
const resolveError = ref('')
const resolvedUser = ref(null)

const targetUserId = ref('')
const reason = ref('')
const ticketRef = ref('')
const password = ref('')
const starting = ref(false)
const startError = ref('')

const prefillId = computed(() => {
  const q = route.query.userId
  return typeof q === 'string' && /^\d+$/.test(q) ? q : ''
})

watch(
  prefillId,
  (id) => {
    if (id) {
      targetUserId.value = id
    }
  },
  { immediate: true },
)

onMounted(() => {
  if (prefillId.value) {
    targetUserId.value = prefillId.value
  }
})

async function onResolve() {
  resolveError.value = ''
  resolvedUser.value = null
  const email = resolveEmail.value.trim()
  const username = resolveUsername.value.trim()
  if (!email && !username) {
    resolveError.value = t('personification.resolveNeedOne')
    return
  }
  resolving.value = true
  await ensureCsrfCookie()
  const body = {}
  if (email) {
    body.email = email
  } else {
    body.username = username
  }
  const { ok, status, data } = await apiJson('POST', '/api/personification/resolve', body)
  resolving.value = false
  if (!ok) {
    resolveError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('personification.resolveError').replace('{status}', String(status))
    return
  }
  if (data?.user?.id) {
    resolvedUser.value = data.user
    targetUserId.value = String(data.user.id)
  }
}

async function onStart() {
  startError.value = ''
  const id = parseInt(String(targetUserId.value).trim(), 10)
  if (!Number.isFinite(id) || id < 1) {
    startError.value = t('personification.invalidTargetId')
    return
  }
  if (!password.value) {
    startError.value = t('personification.passwordRequired')
    return
  }
  starting.value = true
  await ensureCsrfCookie()
  const { ok, status, data } = await apiJson('POST', '/api/personification/start', {
    target_user_id: id,
    password: password.value,
    reason: reason.value,
    ticket_reference: ticketRef.value.trim() || undefined,
  })
  starting.value = false
  if (!ok) {
    if (status === 422 && data?.errors && typeof data.errors === 'object') {
      const parts = []
      for (const k of Object.keys(data.errors)) {
        const arr = data.errors[k]
        if (Array.isArray(arr) && arr[0]) {
          parts.push(String(arr[0]))
        }
      }
      startError.value = parts.join(' ') || t('personification.startError').replace('{status}', String(status))
      return
    }
    startError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('personification.startError').replace('{status}', String(status))
    return
  }
  password.value = ''
  await resolveSession()
  await router.replace({ name: 'dashboard' })
}
</script>

<template>
  <section class="support-personification">
    <PageToolbarTitle route-key="support-personification">
      <Title tag="h1">{{ t('personification.pageTitle') }}</Title>
    </PageToolbarTitle>
    <p v-if="showPersonificationTools" class="support-personification__intro">
      {{ t('personification.pageIntro') }}
    </p>
    <p
      v-if="sessionUser && sessionPersonification?.active && sessionPersonification.actor"
      class="support-personification__signedIn support-personification__signedIn--split"
    >
      <span class="support-personification__identityRow">
        {{ t('personification.staffAccountLabel') }}
        <strong>{{ sessionPersonification.actor.name }}</strong>
      </span>
      <span class="support-personification__identityRow">
        {{ t('personification.viewingAsLabel') }}
        <strong>{{ sessionUser.name }}</strong>
      </span>
    </p>
    <p v-else-if="sessionUser" class="support-personification__signedIn">
      {{ t('personification.signedInAs') }} <strong>{{ sessionUser.name }}</strong>
    </p>

    <p
      v-if="sessionPersonification?.active && !showPersonificationTools"
      class="support-personification__personifiedHint"
      role="status"
    >
      {{ t('personification.personifiedModeHint') }}
    </p>

    <div v-if="showPersonificationTools" class="support-personification__panel">
      <h2 class="support-personification__h2">{{ t('personification.resolveHeading') }}</h2>
      <p class="support-personification__hint">{{ t('personification.resolveHint') }}</p>
      <div class="support-personification__row">
        <label class="support-personification__label" for="pf-email">{{ t('personification.fieldEmail') }}</label>
        <input
          id="pf-email"
          v-model="resolveEmail"
          type="email"
          class="support-personification__input"
          autocomplete="off"
        >
      </div>
      <div class="support-personification__row">
        <label class="support-personification__label" for="pf-username">{{ t('personification.fieldUsername') }}</label>
        <input
          id="pf-username"
          v-model="resolveUsername"
          type="text"
          class="support-personification__input"
          autocomplete="off"
        >
      </div>
      <p v-if="resolveError" class="support-personification__error" role="alert">
        {{ resolveError }}
      </p>
      <Button type="button" variant="secondary" :loading="resolving" @click="onResolve">
        {{ t('personification.resolveButton') }}
      </Button>
      <p v-if="resolvedUser" class="support-personification__resolved">
        {{ t('personification.resolvedAs') }}
        <strong>{{ resolvedUser.name }}</strong>
        ({{ resolvedUser.email_obfuscated }})
      </p>
    </div>

    <div v-if="showPersonificationTools" class="support-personification__panel">
      <h2 class="support-personification__h2">{{ t('personification.startHeading') }}</h2>
      <div class="support-personification__row">
        <label class="support-personification__label" for="pf-target">{{ t('personification.fieldTargetId') }}</label>
        <input
          id="pf-target"
          v-model="targetUserId"
          type="text"
          inputmode="numeric"
          class="support-personification__input"
          autocomplete="off"
        >
      </div>
      <div class="support-personification__row">
        <label class="support-personification__label" for="pf-reason">{{ t('personification.fieldReason') }}</label>
        <textarea
          id="pf-reason"
          v-model="reason"
          class="support-personification__textarea"
          rows="3"
          maxlength="2000"
        />
      </div>
      <div class="support-personification__row">
        <label class="support-personification__label" for="pf-ticket">{{ t('personification.fieldTicket') }}</label>
        <input
          id="pf-ticket"
          v-model="ticketRef"
          type="text"
          class="support-personification__input"
          maxlength="255"
          autocomplete="off"
        >
      </div>
      <div class="support-personification__row">
        <label class="support-personification__label" for="pf-password">{{ t('personification.fieldYourPassword') }}</label>
        <input
          id="pf-password"
          v-model="password"
          type="password"
          class="support-personification__input"
          autocomplete="current-password"
        >
      </div>
      <p v-if="startError" class="support-personification__error" role="alert">
        {{ startError }}
      </p>
      <Button type="button" variant="primary" :loading="starting" @click="onStart">
        {{ t('personification.startButton') }}
      </Button>
    </div>
  </section>
</template>

<style scoped lang="scss">
.support-personification {
  padding: 1rem;
  max-width: 40rem;
}

.support-personification__intro,
.support-personification__signedIn {
  margin: 0 0 1rem;
  line-height: 1.45;
}

.support-personification__signedIn--split {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.support-personification__identityRow {
  display: block;
}

.support-personification__personifiedHint {
  margin: 0 0 1.25rem;
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  background: color-mix(in srgb, var(--link, #1d4ed8) 8%, var(--bg));
  font-size: 0.9rem;
  line-height: 1.45;
}

.support-personification__panel {
  border: 1px solid var(--border);
  border-radius: 0.6rem;
  padding: 1rem;
  margin-bottom: 1.25rem;
  background: var(--bg);
}

.support-personification__h2 {
  margin: 0 0 0.5rem;
  font-size: 1.05rem;
}

.support-personification__hint {
  margin: 0 0 0.75rem;
  font-size: 0.9rem;
  opacity: 0.9;
}

.support-personification__row {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  margin-bottom: 0.75rem;
}

.support-personification__label {
  font-size: 0.85rem;
  font-weight: 600;
}

.support-personification__input,
.support-personification__textarea {
  font: inherit;
  padding: 0.45rem 0.55rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
  background: var(--bg);
  color: inherit;
}

.support-personification__textarea {
  resize: vertical;
  min-height: 4rem;
}

.support-personification__error {
  margin: 0 0 0.75rem;
  color: var(--danger, #b91c1c);
  font-size: 0.9rem;
}

.support-personification__resolved {
  margin: 0.75rem 0 0;
  font-size: 0.9rem;
}
</style>
