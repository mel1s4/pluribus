<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import CommunityHubTabs from '../../components/App/CommunityHubTabs.vue'
import ProjectOverviewSection from '../../organisms/ProjectOverviewSection.vue'
import { fetchCommunityBranding } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'
import { fetchCommunityMicrosite } from '../../services/communityApi'
import { fetchCommunityProject } from '../../services/projectsApi.js'

const route = useRoute()
const router = useRouter()

const slug = computed(() => {
  const raw = route.params.slug
  return typeof raw === 'string' ? raw.trim() : ''
})

const projectId = computed(() => {
  const raw = route.params.projectId
  const n = typeof raw === 'string' ? parseInt(raw, 10) : NaN
  return Number.isFinite(n) ? n : NaN
})

const loading = ref(true)
const loadError = ref('')
const project = ref(null)
const isMember = ref(false)

async function loadMember() {
  if (!slug.value) return
  const { ok, data } = await fetchCommunityMicrosite(slug.value)
  if (ok && data && typeof data === 'object') {
    isMember.value = Boolean(data.is_member)
  }
}

async function loadProject() {
  if (!slug.value || !Number.isFinite(projectId.value)) return
  loading.value = true
  loadError.value = ''
  const { ok, status, data } = await fetchCommunityProject(slug.value, projectId.value)
  loading.value = false
  if (!ok) {
    loadError.value =
      (data && typeof data === 'object' && typeof data.message === 'string' && data.message) ||
      t('communityProjects.loadError').replace('{status}', String(status))
    project.value = null
    return
  }
  const p = data && typeof data === 'object' ? data.project : null
  project.value = p && typeof p === 'object' ? p : null
}

async function load() {
  await loadMember()
  await fetchCommunityBranding(slug.value)
  await loadProject()
}

onMounted(load)
watch([slug, projectId], () => {
  void load()
})

function onDeleted() {
  router.push({ name: 'communityProjects', params: { slug: slug.value } })
}
</script>

<template>
  <section class="community-project-detail-page">
    <PageToolbarTitle route-key="community-project-detail">
      <Title tag="h1">{{ project?.title || t('communityProjects.detailHeading') }}</Title>
    </PageToolbarTitle>

    <p v-if="slug" class="community-project-detail-page__back">
      <RouterLink class="community-project-detail-page__link" :to="{ name: 'communityProjects', params: { slug } }">
        {{ t('communityProjects.backToList') }}
      </RouterLink>
    </p>

    <CommunityHubTabs v-if="slug && !loading" :slug="slug" :is-member="isMember" />

    <p v-if="loadError" class="community-project-detail-page__error" role="alert">{{ loadError }}</p>
    <p v-else-if="loading" class="community-project-detail-page__muted">{{ t('communityProjects.loading') }}</p>
    <ProjectOverviewSection
      v-else-if="project"
      :slug="slug"
      :project-id="projectId"
      :project="project"
      @updated="loadProject"
      @deleted="onDeleted"
    />
  </section>
</template>

<style lang="scss" scoped>
.community-project-detail-page {
  max-width: 48rem;
  margin: 0 auto;
  padding: 1.5rem 1rem 2.5rem;
}
.community-project-detail-page__back {
  margin: 0 0 0.5rem;
}
.community-project-detail-page__link {
  color: var(--link, #2563eb);
  text-decoration: none;
  &:hover {
    text-decoration: underline;
  }
}
.community-project-detail-page__muted {
  color: var(--muted, #6b7280);
}
.community-project-detail-page__error {
  color: #b91c1c;
}
</style>
