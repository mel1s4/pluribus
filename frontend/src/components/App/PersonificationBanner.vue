<script setup>
import { ref } from 'vue'
import Button from '../../atoms/Button.vue'
import { apiJson, ensureCsrfCookie } from '../../services/api'
import { resolveSession, sessionPersonification, sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'

const stopping = ref(false)
const stopError = ref('')

async function onStop() {
  if (stopping.value) {
    return
  }
  stopError.value = ''
  stopping.value = true
  await ensureCsrfCookie()
  const { ok, status, data } = await apiJson('POST', '/api/personification/stop')
  stopping.value = false
  if (!ok) {
    stopError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('personification.stopError').replace('{status}', String(status))
    return
  }
  await resolveSession()
}
</script>

<template>
  <div
    v-if="sessionPersonification?.active"
    class="personification-banner"
    role="status"
  >
    <div class="personification-banner__inner">
      <p class="personification-banner__text">
        {{
          t('personification.bannerText')
            .replace('{target}', String(sessionUser?.name || ''))
            .replace('{actor}', String(sessionPersonification.actor?.name || ''))
        }}
        <span v-if="sessionPersonification.ends_at" class="personification-banner__until">
          {{ t('personification.until') }}
          {{ new Date(sessionPersonification.ends_at).toLocaleString() }}
        </span>
      </p>
      <div class="personification-banner__actions">
        <p v-if="stopError" class="personification-banner__error" role="alert">
          {{ stopError }}
        </p>
        <Button
          type="button"
          variant="secondary"
          size="sm"
          :loading="stopping"
          @click="onStop"
        >
          {{ t('personification.stop') }}
        </Button>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.personification-banner {
  flex-shrink: 0;
  background: color-mix(in srgb, #b45309 18%, var(--bg));
  border-bottom: 1px solid color-mix(in srgb, #b45309 35%, var(--border));
  color: inherit;
}

.personification-banner__inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0.5rem 1rem;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.personification-banner__text {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.35;
}

.personification-banner__actor {
  font-weight: 700;
}

.personification-banner__until {
  display: block;
  margin-top: 0.2rem;
  font-size: 0.82rem;
  opacity: 0.9;
}

.personification-banner__actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.35rem;
}

.personification-banner__error {
  margin: 0;
  font-size: 0.82rem;
  color: var(--danger, #b91c1c);
}
</style>
