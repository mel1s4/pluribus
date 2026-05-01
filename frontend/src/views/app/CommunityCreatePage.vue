<script setup>
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import Input from '../../atoms/Input.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { t } from '../../i18n/i18n'
import { createCommunity } from '../../services/communityApi'

const route = useRoute()
const router = useRouter()

const form = ref({
  name: '',
  slug: '',
  description: '',
})
const saveError = ref('')
const saving = ref(false)

const canManage = computed(() => hasCapability('communities.manage'))

const listRoute = computed(() => {
  const s = route.params.communitySlug
  if (typeof s === 'string' && s.trim() !== '') {
    return { name: 'communitiesScoped', params: { communitySlug: s.trim() } }
  }
  return { name: 'communities' }
})

function editRouteFor(community) {
  const id = String(community.id)
  const s = route.params.communitySlug
  if (typeof s === 'string' && s.trim() !== '') {
    return { name: 'communityEditScoped', params: { communitySlug: s.trim(), communityId: id } }
  }
  return { name: 'communityEdit', params: { communityId: id } }
}

async function onSubmit() {
  if (!canManage.value) return
  saveError.value = ''
  saving.value = true
  const response = await createCommunity({
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
  const community =
    data && typeof data === 'object' && data.community && typeof data.community === 'object'
      ? data.community
      : null
  if (community && community.id != null) {
    await router.replace(editRouteFor(community))
    return
  }
  saveError.value = t('communities.createMissingCommunityPayload')
}
</script>

<template>
  <section class="community-create-page">
    <PageToolbarTitle route-key="communities">
      <Title tag="h1">{{ t('communities.createPageTitle') }}</Title>
    </PageToolbarTitle>
    <p class="community-create-page__back">
      <RouterLink :to="listRoute">{{ t('communities.backToList') }}</RouterLink>
    </p>
    <p class="community-create-page__intro">{{ t('communities.createPageIntro') }}</p>

    <template v-if="canManage">
      <p v-if="saveError" class="community-create-page__error" role="alert">{{ saveError }}</p>
      <form class="community-create-page__form" @submit.prevent="onSubmit">
        <p class="community-create-page__hint">{{ t('communities.formHint') }}</p>
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
        <label class="community-create-page__label">
          <span>{{ t('communities.fieldDescription') }}</span>
          <textarea v-model="form.description" rows="4" />
        </label>
        <div class="community-create-page__buttons">
          <Button type="submit" :disabled="saving">
            {{ saving ? t('communities.saving') : t('communities.create') }}
          </Button>
          <Button
            type="button"
            variant="secondary"
            :disabled="saving"
            @click="router.push(listRoute)"
          >
            {{ t('communities.cancel') }}
          </Button>
        </div>
      </form>
    </template>
    <p v-else class="community-create-page__muted">{{ t('communities.createNoPermission') }}</p>
  </section>
</template>

<style lang="scss" scoped>
.community-create-page {
  padding: 2rem;
  max-width: 720px;
  margin: 0 auto;
}

.community-create-page__back {
  margin: 0 0 0.5rem;
}

.community-create-page__intro,
.community-create-page__hint,
.community-create-page__muted {
  color: var(--muted, #6b7280);
}

.community-create-page__error {
  color: #b91c1c;
}

.community-create-page__form {
  margin-top: 1rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  padding: 1rem;
  display: grid;
  gap: 0.75rem;
}

.community-create-page__label {
  display: grid;
  gap: 0.4rem;
  font-size: 0.875rem;
  font-weight: 600;
}

.community-create-page__buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
}
</style>
