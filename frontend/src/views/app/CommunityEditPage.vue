<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import Input from '../../atoms/Input.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import CommunityAdminsPanel from '../../components/App/CommunityAdminsPanel.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { t } from '../../i18n/i18n'
import { fetchCommunityById, patchCommunity } from '../../services/communityApi'

const route = useRoute()
const router = useRouter()

const communityId = computed(() => {
  const raw = route.params.communityId
  const n = typeof raw === 'string' ? Number(raw) : Number(raw)
  return Number.isFinite(n) && n > 0 ? n : 0
})

const community = ref(null)
const loadError = ref('')
const loading = ref(true)
const saveError = ref('')
const saving = ref(false)

const form = ref({
  name: '',
  slug: '',
  description: '',
})

/** @type {import('vue').Ref<Array<{ host: string, is_primary: boolean }>>} */
const domainRows = ref([])

const canManage = computed(() => hasCapability('communities.manage'))

const listRoute = computed(() => {
  const s = route.params.communitySlug
  if (typeof s === 'string' && s.trim() !== '') {
    return { name: 'communitiesScoped', params: { communitySlug: s.trim() } }
  }
  return { name: 'communities' }
})

async function loadCommunity() {
  if (communityId.value <= 0) {
    loadError.value = t('communities.editInvalidId')
    loading.value = false
    community.value = null
    return
  }
  loading.value = true
  loadError.value = ''
  const { ok, status, data } = await fetchCommunityById(communityId.value)
  loading.value = false
  if (!ok) {
    loadError.value =
      (data && typeof data === 'object' && data.message && String(data.message))
      || t('communities.editLoadError').replace('{status}', String(status))
    community.value = null
    if (status === 404 || status === 403) {
      void router.replace(listRoute.value)
    }
    return
  }
  const row = data && typeof data === 'object' && data.data && typeof data.data === 'object' ? data.data : null
  if (!row || row.id == null) {
    loadError.value = t('communities.editLoadError').replace('{status}', String(status))
    community.value = null
    return
  }
  community.value = row
  form.value = {
    name: row.name ?? '',
    slug: row.slug ?? '',
    description: row.description ?? '',
  }
  const domains = Array.isArray(row.domains) ? row.domains : []
  domainRows.value = domains.map((d) => ({
    host: typeof d?.host === 'string' ? d.host : '',
    is_primary: Boolean(d?.is_primary),
  }))
}

function addDomainRow() {
  domainRows.value.push({ host: '', is_primary: domainRows.value.length === 0 })
}

function removeDomainRow(index) {
  domainRows.value.splice(index, 1)
  if (domainRows.value.length > 0 && !domainRows.value.some((r) => r.is_primary)) {
    domainRows.value[0].is_primary = true
  }
}

function setPrimaryDomain(index) {
  domainRows.value = domainRows.value.map((row, i) => ({
    ...row,
    is_primary: i === index,
  }))
}

function domainsPayload() {
  return domainRows.value
    .map((row) => ({
      host: String(row.host || '').trim(),
      is_primary: Boolean(row.is_primary),
    }))
    .filter((row) => row.host !== '')
}

async function onSubmit() {
  if (!canManage.value || communityId.value <= 0) return
  saveError.value = ''
  saving.value = true
  const response = await patchCommunity(communityId.value, {
    name: form.value.name,
    slug: form.value.slug || undefined,
    description: form.value.description || null,
    domains: domainsPayload(),
  })
  saving.value = false
  if (!response.ok) {
    const data = response.data
    saveError.value =
      (data && typeof data === 'object' && data.message && String(data.message))
      || t('communities.saveError').replace('{status}', String(response.status))
    return
  }
  const data = response.data
  const next =
    data && typeof data === 'object' && data.community && typeof data.community === 'object'
      ? data.community
      : null
  if (next) {
    community.value = next
    form.value = {
      name: next.name ?? '',
      slug: next.slug ?? '',
      description: next.description ?? '',
    }
    const domains = Array.isArray(next.domains) ? next.domains : []
    domainRows.value = domains.map((d) => ({
      host: typeof d?.host === 'string' ? d.host : '',
      is_primary: Boolean(d?.is_primary),
    }))
  }
}

watch(communityId, () => {
  void loadCommunity()
})

onMounted(() => {
  void loadCommunity()
})
</script>

<template>
  <section class="community-edit-page">
    <PageToolbarTitle route-key="communities">
      <Title tag="h1">{{ t('communities.editPageTitle') }}</Title>
    </PageToolbarTitle>
    <p class="community-edit-page__back">
      <RouterLink :to="listRoute">{{ t('communities.backToList') }}</RouterLink>
    </p>

    <p v-if="loading" class="community-edit-page__muted">{{ t('communities.loading') }}</p>
    <p v-else-if="loadError" class="community-edit-page__error" role="alert">{{ loadError }}</p>

    <template v-else-if="community">
      <p class="community-edit-page__intro">{{ t('communities.editPageIntro') }}</p>

      <template v-if="canManage">
        <p v-if="saveError" class="community-edit-page__error" role="alert">{{ saveError }}</p>
        <form class="community-edit-page__form" @submit.prevent="onSubmit">
          <p class="community-edit-page__hint">{{ t('communities.formHint') }}</p>
          <Input
            v-model="form.name"
            :label="t('communities.fieldName')"
            autocomplete="off"
            required
          />
          <Input
            v-model="form.slug"
            :label="t('communities.fieldSlug')"
            :placeholder="t('communities.slugPlaceholder')"
            autocomplete="off"
          />
          <label class="community-edit-page__label">
            <span>{{ t('communities.fieldDescription') }}</span>
            <textarea v-model="form.description" rows="4" />
          </label>
          <div class="community-edit-page__domains">
            <span class="community-edit-page__domains-title">{{ t('communities.fieldDomains') }}</span>
            <p class="community-edit-page__hint">{{ t('communities.domainsHint') }}</p>
            <p v-if="domainRows.length === 0" class="community-edit-page__muted">
              {{ t('communities.noDomains') }}
            </p>
            <div
              v-for="(row, index) in domainRows"
              :key="index"
              class="community-edit-page__domain-row"
            >
              <Input
                v-model="row.host"
                :label="t('communities.fieldDomains')"
                :placeholder="t('communities.domainHostPlaceholder')"
                autocomplete="off"
              />
              <label class="community-edit-page__primary">
                <input
                  type="radio"
                  name="primary-domain"
                  :checked="row.is_primary"
                  @change="setPrimaryDomain(index)"
                />
                {{ t('communities.primaryDomain') }}
              </label>
              <Button type="button" variant="secondary" size="sm" @click="removeDomainRow(index)">
                ×
              </Button>
            </div>
            <Button type="button" variant="secondary" size="sm" @click="addDomainRow">
              {{ t('communities.addDomain') }}
            </Button>
          </div>
          <div class="community-edit-page__buttons">
            <Button type="submit" :disabled="saving">
              {{ saving ? t('communities.saving') : t('communities.save') }}
            </Button>
          </div>
        </form>
      </template>
      <p v-else class="community-edit-page__muted">{{ t('communities.editNoPermission') }}</p>

      <CommunityAdminsPanel
        v-if="typeof community.slug === 'string' && community.slug.trim() !== ''"
        :community-slug="String(community.slug).trim()"
      />
    </template>
  </section>
</template>

<style lang="scss" scoped>
.community-edit-page {
  padding: 2rem;
  max-width: 900px;
  margin: 0 auto;
}

.community-edit-page__back {
  margin: 0 0 0.5rem;
}

.community-edit-page__intro,
.community-edit-page__hint,
.community-edit-page__muted {
  color: var(--muted, #6b7280);
}

.community-edit-page__error {
  color: #b91c1c;
}

.community-edit-page__form {
  margin-top: 1rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  padding: 1rem;
  display: grid;
  gap: 0.75rem;
}

.community-edit-page__label {
  display: grid;
  gap: 0.4rem;
  font-size: 0.875rem;
  font-weight: 600;
}

.community-edit-page__buttons {
  display: flex;
  gap: 0.75rem;
}

.community-edit-page__domains {
  display: grid;
  gap: 0.65rem;
  margin-top: 0.5rem;
}

.community-edit-page__domains-title {
  font-weight: 600;
  font-size: 0.875rem;
}

.community-edit-page__domain-row {
  display: grid;
  gap: 0.5rem;
  padding: 0.65rem;
  border: 1px dashed var(--border, #e5e7eb);
  border-radius: 0.375rem;
}

.community-edit-page__primary {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.8125rem;
}
</style>
