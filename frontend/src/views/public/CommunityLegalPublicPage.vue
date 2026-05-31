<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import Title from '../../atoms/Title.vue'
import { t } from '../../i18n/i18n'
import { fetchCommunityLegalDocumentsPublic } from '../../services/communityApi.js'

const route = useRoute()

const slug = computed(() => {
  const raw = route.params.communitySlug
  return typeof raw === 'string' ? raw.trim() : ''
})

const document = computed(() => {
  const raw = route.params.document
  return raw === 'terms' || raw === 'privacy' ? raw : 'terms'
})

const loading = ref(true)
const loadError = ref('')
const communityName = ref('')
const bodyText = ref('')

function userApiErrorMessage(data, status, fallback) {
  if (data && typeof data === 'object' && typeof data.message === 'string' && data.message.length) {
    return data.message
  }
  return fallback.replace('{status}', String(status))
}

async function load() {
  loading.value = true
  loadError.value = ''
  communityName.value = ''
  bodyText.value = ''
  if (!slug.value) {
    loading.value = false
    loadError.value = t('communityLegalPublic.notFound')
    return
  }
  const { ok, status, data } = await fetchCommunityLegalDocumentsPublic(slug.value)
  loading.value = false
  if (status === 404) {
    loadError.value = t('communityLegalPublic.notFound')
    return
  }
  if (!ok) {
    loadError.value = userApiErrorMessage(data, status, t('communityLegalPublic.loadError'))
    return
  }
  const c = data?.community
  if (!c || typeof c !== 'object') {
    loadError.value = t('communityLegalPublic.loadError').replace('{status}', String(status))
    return
  }
  communityName.value = typeof c.name === 'string' ? c.name : ''
  const raw =
    document.value === 'terms'
      ? c.terms_markdown
      : c.privacy_policy_markdown
  bodyText.value = typeof raw === 'string' ? raw : ''
}

watch(
  () => [slug.value, document.value],
  () => {
    void load()
  },
  { immediate: true },
)

const pageTitle = computed(() =>
  document.value === 'terms'
    ? t('communityLegalPublic.titleTerms')
    : t('communityLegalPublic.titlePrivacy'),
)
</script>

<template>
  <div class="page page--community-legal-public">
    <Title tag="h1">{{ pageTitle }}</Title>
    <p v-if="communityName" class="page--community-legal-public__sub">{{ communityName }}</p>

    <p v-if="loading" class="page--community-legal-public__muted">{{ t('communitySettings.loading') }}</p>
    <p v-else-if="loadError" class="page--community-legal-public__error" role="alert">{{ loadError }}</p>
    <template v-else>
      <pre v-if="bodyText.trim() !== ''" class="page--community-legal-public__body">{{ bodyText }}</pre>
      <p v-else class="page--community-legal-public__muted">{{ t('communityLegalPublic.empty') }}</p>
    </template>

    <p class="page--community-legal-public__hint">{{ t('communityLegalPublic.appLegalHint') }}</p>
    <p class="page--community-legal-public__footer-link">
      <RouterLink to="/legal">{{ t('communityLegalPublic.linkAppLegal') }}</RouterLink>
    </p>
  </div>
</template>

<style lang="scss" scoped>
.page--community-legal-public {
  padding: 2rem;
  max-width: 42rem;
  margin: 0 auto;
}

.page--community-legal-public__sub {
  margin: 0.25rem 0 1rem;
  font-size: 0.95rem;
  color: var(--muted, #4b5563);
}

.page--community-legal-public__muted {
  margin: 0 0 1rem;
  line-height: 1.5;
  color: var(--muted, #4b5563);
}

.page--community-legal-public__error {
  margin: 0 0 1rem;
  color: #b91c1c;
  line-height: 1.45;
}

.page--community-legal-public__body {
  margin: 0 0 1.5rem;
  padding: 0.85rem 1rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  background: var(--surface-2, #f9fafb);
  font-family: ui-monospace, monospace;
  font-size: 0.88rem;
  white-space: pre-wrap;
  word-break: break-word;
  line-height: 1.45;
}

.page--community-legal-public__hint {
  margin: 1.5rem 0 0.35rem;
  font-size: 0.88rem;
  line-height: 1.45;
  color: var(--muted, #4b5563);
}

.page--community-legal-public__footer-link {
  margin: 0;
  font-size: 0.92rem;
}

.page--community-legal-public__footer-link a {
  color: var(--link, #2563eb);
  font-weight: 600;
}
</style>
