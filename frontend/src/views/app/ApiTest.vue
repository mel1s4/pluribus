<template>
  <section class="page page--api-test">
    <Title tag="h1">{{ t('apiTest.title') }}</Title>
    <p class="page--api-test__intro">{{ t('apiTest.intro') }}</p>
    <p class="page--api-test__url">
      <code>{{ healthUrl }}</code>
    </p>
    <div class="page--api-test__actions">
      <button type="button" class="page--api-test__btn" :disabled="loading" @click="runCheck">
        {{ loading ? t('apiTest.loading') : t('apiTest.run') }}
      </button>
    </div>
    <p v-if="errorMsg" class="page--api-test__error" role="alert">{{ errorMsg }}</p>
    <pre v-if="jsonPreview" class="page--api-test__pre">{{ jsonPreview }}</pre>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { getHealth } from '../../services/api'
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'

const loading = ref(false)
const result = ref(null)

const healthUrl = computed(() => {
  const base =
    typeof import.meta.env.VITE_API_BASE_URL === 'string' &&
    import.meta.env.VITE_API_BASE_URL.length > 0
      ? import.meta.env.VITE_API_BASE_URL.replace(/\/$/, '')
      : 'http://localhost:9122'
  return `${base}/api/health`
})

const errorMsg = computed(() => {
  if (!result.value) return ''
  if (result.value.ok) return ''
  return result.value.error || t('apiTest.errorUnknown')
})

const jsonPreview = computed(() => {
  if (!result.value?.ok || result.value.data == null) return ''
  return JSON.stringify(result.value.data, null, 2)
})

async function runCheck() {
  loading.value = true
  result.value = null
  try {
    result.value = await getHealth()
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  runCheck()
})
</script>

<style lang="scss" scoped>
.page--api-test {
  padding: 2rem;
  max-width: 42rem;
}

.page--api-test__intro {
  margin-top: 0.75rem;
  opacity: 0.9;
}

.page--api-test__url {
  margin-top: 1rem;
  word-break: break-all;
}

.page--api-test__actions {
  margin-top: 1.25rem;
}

.page--api-test__btn {
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  background: var(--btn-bg);
  color: var(--btn-text);
  font-weight: 600;
  cursor: pointer;

  &:hover:not(:disabled) {
    background: var(--btn-bg-hover);
  }

  &:disabled {
    opacity: 0.65;
    cursor: not-allowed;
  }
}

.page--api-test__error {
  margin-top: 1rem;
  color: #b91c1c;

  html[data-theme='dark'] & {
    color: #fca5a5;
  }
}

.page--api-test__pre {
  margin-top: 1rem;
  padding: 1rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  background: var(--btn-bg);
  overflow-x: auto;
  font-size: 0.875rem;
}
</style>
