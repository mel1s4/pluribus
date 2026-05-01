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
}

async function onSubmit() {
  if (!canManage.value || communityId.value <= 0) return
  saveError.value = ''
  saving.value = true
  const response = await patchCommunity(communityId.value, {
    name: form.value.name,
    slug: form.value.slug || undefined,
    description: form.value.description || null,
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
</style>
