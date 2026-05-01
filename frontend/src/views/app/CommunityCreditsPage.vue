<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import { t } from '../../i18n/i18n'
import { fetchCommunityBranding } from '../../composables/useCommunity'
import { sessionStatus } from '../../composables/useSession'
import {
  downloadCommunityLedgerExport,
  fetchCommunityCredits,
} from '../../services/communityApi'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const loadError = ref('')
const payload = ref(null)
const downloadBusy = ref(false)
const downloadError = ref('')

const slug = computed(() => {
  const raw = route.params.slug
  return typeof raw === 'string' ? raw.trim() : ''
})

const community = computed(() => {
  const c = payload.value?.community
  return c && typeof c === 'object' ? c : null
})

const isMember = computed(() => Boolean(payload.value?.is_member))

const creditsTotal = computed(() => {
  const raw = payload.value?.credits_granted_total
  return typeof raw === 'string' || typeof raw === 'number' ? String(raw) : '0'
})

const currencyLabel = computed(() => {
  const code = community.value?.currency_code
  return typeof code === 'string' && code.trim() ? code.trim().toUpperCase() : ''
})

async function load() {
  if (!slug.value) return
  loading.value = true
  loadError.value = ''
  const { ok, status, data } = await fetchCommunityCredits(slug.value)
  loading.value = false
  if (!ok) {
    loadError.value =
      (data && typeof data === 'object' && typeof data.message === 'string' && data.message) ||
      t('communityCredits.loadError').replace('{status}', String(status))
    payload.value = null
    return
  }
  payload.value = data && typeof data === 'object' ? data : null
  await fetchCommunityBranding(slug.value)
}

onMounted(load)
watch(slug, () => {
  void load()
})

function goLogin() {
  router.push({ name: 'login', query: { redirect: route.fullPath } })
}

async function onDownload() {
  downloadError.value = ''
  if (sessionStatus.value !== 'authenticated') {
    goLogin()
    return
  }
  downloadBusy.value = true
  const { ok, status } = await downloadCommunityLedgerExport(slug.value)
  downloadBusy.value = false
  if (!ok) {
    downloadError.value = t('communityCredits.downloadError').replace('{status}', String(status))
  }
}
</script>

<template>
  <section class="community-credits">
    <p v-if="loadError" class="community-credits__error" role="alert">{{ loadError }}</p>
    <p v-else-if="loading" class="community-credits__muted">{{ t('communityCredits.loading') }}</p>
    <template v-else-if="community">
      <header class="community-credits__hero">
        <Title tag="h1" class="community-credits__title">{{ community.name }}</Title>
        <p class="community-credits__intro">{{ t('communityCredits.intro') }}</p>
        <p class="community-credits__nav">
          <RouterLink class="community-credits__link" :to="{ name: 'communityMicrosite', params: { slug } }">
            {{ t('communityCredits.backToMicrosite') }}
          </RouterLink>
        </p>
      </header>

      <Card class="community-credits__card">
        <h2 class="community-credits__h2">{{ t('communityCredits.totalTitle') }}</h2>
        <p class="community-credits__stat">
          {{ creditsTotal }}
          <span v-if="currencyLabel" class="community-credits__currency">{{ currencyLabel }}</span>
        </p>
        <p class="community-credits__hint">{{ t('communityCredits.totalHint') }}</p>

        <div v-if="isMember" class="community-credits__actions">
          <Button type="button" :disabled="downloadBusy" @click="onDownload">
            {{ downloadBusy ? t('communityCredits.downloading') : t('communityCredits.download') }}
          </Button>
          <p v-if="downloadError" class="community-credits__error community-credits__error--inline" role="alert">
            {{ downloadError }}
          </p>
        </div>
        <div v-else class="community-credits__actions">
          <p class="community-credits__hint">{{ t('communityCredits.downloadMembersOnly') }}</p>
          <Button v-if="sessionStatus !== 'authenticated'" type="button" @click="goLogin">
            {{ t('communityCredits.logIn') }}
          </Button>
        </div>
      </Card>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.community-credits {
  max-width: 720px;
  margin: 0 auto;
  padding: 1.5rem 1rem 2.5rem;
}
.community-credits__hero {
  margin-bottom: 1.5rem;
}
.community-credits__title {
  margin: 0 0 0.5rem;
}
.community-credits__intro {
  color: var(--muted, #6b7280);
  margin: 0 0 0.75rem;
  line-height: 1.5;
}
.community-credits__nav {
  margin: 0;
}
.community-credits__link {
  color: var(--link, #2563eb);
  text-decoration: underline;
}
.community-credits__card {
  margin-top: 0.5rem;
}
.community-credits__h2 {
  margin: 0 0 0.5rem;
  font-size: 1.125rem;
}
.community-credits__stat {
  font-size: 1.75rem;
  font-weight: 600;
  margin: 0 0 0.5rem;
}
.community-credits__currency {
  font-size: 1rem;
  font-weight: 500;
  color: var(--muted, #6b7280);
  margin-left: 0.35rem;
}
.community-credits__hint {
  color: var(--muted, #6b7280);
  margin: 0 0 1rem;
  line-height: 1.5;
  font-size: 0.9375rem;
}
.community-credits__actions {
  margin-top: 0.5rem;
}
.community-credits__muted {
  color: var(--muted, #6b7280);
}
.community-credits__error {
  color: var(--danger, #b91c1c);
  margin: 0 0 1rem;
}
.community-credits__error--inline {
  margin: 0.75rem 0 0;
}
</style>
