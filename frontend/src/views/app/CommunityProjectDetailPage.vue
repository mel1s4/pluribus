<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import CommunityHubTabs from '../../components/App/CommunityHubTabs.vue'
import ProjectOverviewSection from '../../organisms/ProjectOverviewSection.vue'
import ProjectArgumentTree from '../../organisms/ProjectArgumentTree.vue'
import { fetchCommunityBranding } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'
import { fetchCommunityMicrosite } from '../../services/communityApi'
import {
  createProjectArgument,
  deleteProjectArgument,
  fetchCommunityProject,
  updateProjectArgument,
} from '../../services/projectsApi.js'

const route = useRoute()

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

const dialogOpen = ref(false)
const dialogMode = ref('create')
const dialogParentId = ref(null)
const dialogStance = ref('pro')
const dialogTitle = ref('')
const dialogBody = ref('')
const dialogArgumentId = ref(null)
const dialogBusy = ref(false)
const dialogError = ref('')

const args = computed(() => {
  const p = project.value
  if (!p || !Array.isArray(p.arguments)) return []
  return p.arguments
})

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

function openAdd({ parentId, stance }) {
  dialogMode.value = 'create'
  dialogParentId.value = parentId
  dialogStance.value = stance
  dialogTitle.value = ''
  dialogBody.value = ''
  dialogArgumentId.value = null
  dialogError.value = ''
  dialogOpen.value = true
}

function openEdit(node) {
  dialogMode.value = 'edit'
  dialogParentId.value = node.parent_id
  dialogStance.value = node.stance
  dialogTitle.value = node.title || ''
  dialogBody.value = node.body || ''
  dialogArgumentId.value = node.id
  dialogError.value = ''
  dialogOpen.value = true
}

async function submitDialog() {
  dialogError.value = ''
  dialogBusy.value = true
  if (dialogMode.value === 'create') {
    const { ok, status, data } = await createProjectArgument(slug.value, projectId.value, {
      parent_id: dialogParentId.value,
      stance: dialogStance.value,
      title: dialogTitle.value.trim(),
      body: dialogBody.value.trim() || undefined,
    })
    dialogBusy.value = false
    if (!ok) {
      dialogError.value =
        (data && typeof data === 'object' && typeof data.message === 'string' && data.message) ||
        String(status)
      return
    }
  } else if (dialogArgumentId.value != null) {
    const { ok, status, data } = await updateProjectArgument(
      slug.value,
      projectId.value,
      dialogArgumentId.value,
      {
        stance: dialogStance.value,
        title: dialogTitle.value.trim(),
        body: dialogBody.value.trim() || undefined,
      },
    )
    dialogBusy.value = false
    if (!ok) {
      dialogError.value =
        (data && typeof data === 'object' && typeof data.message === 'string' && data.message) ||
        String(status)
      return
    }
  }
  dialogOpen.value = false
  await loadProject()
}

async function onDelete(node) {
  if (!window.confirm(t('communityProjects.deleteConfirm'))) return
  const { ok } = await deleteProjectArgument(slug.value, projectId.value, node.id)
  if (ok) await loadProject()
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
    <template v-else-if="project">
      <ProjectOverviewSection
        :slug="slug"
        :project-id="projectId"
        :project="project"
        @updated="loadProject"
      />
      <ProjectArgumentTree
        :slug="slug"
        :project-id="projectId"
        :thesis-title="project.thesis_title"
        :thesis-body="project.thesis_body || ''"
        :arguments-list="args"
        @add-child="openAdd"
        @edit="openEdit"
        @delete="onDelete"
      />
    </template>

    <div
      v-if="dialogOpen"
      class="community-project-detail-page__dialog-backdrop"
      role="presentation"
      @click.self="dialogOpen = false"
    >
      <Card class="community-project-detail-page__dialog">
        <Title tag="h2">{{ dialogMode === 'create' ? t('communityProjects.argCreate') : t('communityProjects.argEdit') }}</Title>
        <p class="community-project-detail-page__muted">
          {{ dialogStance === 'con' ? t('communityProjects.stanceCon') : t('communityProjects.stancePro') }}
        </p>
        <p v-if="dialogError" class="community-project-detail-page__error">{{ dialogError }}</p>
        <label class="community-project-detail-page__field">
          <span>{{ t('communityProjects.argTitle') }}</span>
          <input v-model="dialogTitle" type="text" class="community-project-detail-page__input" maxlength="500" />
        </label>
        <label class="community-project-detail-page__field">
          <span>{{ t('communityProjects.argBody') }}</span>
          <textarea v-model="dialogBody" class="community-project-detail-page__textarea" rows="3" />
        </label>
        <label v-if="dialogMode === 'edit'" class="community-project-detail-page__field">
          <span>{{ t('communityProjects.argStance') }}</span>
          <select v-model="dialogStance" class="community-project-detail-page__input">
            <option value="pro">{{ t('communityProjects.stancePro') }}</option>
            <option value="con">{{ t('communityProjects.stanceCon') }}</option>
          </select>
        </label>
        <div class="community-project-detail-page__dialog-actions">
          <Button type="button" :disabled="dialogBusy" @click="submitDialog">{{ t('communityProjects.save') }}</Button>
          <Button type="button" :disabled="dialogBusy" @click="dialogOpen = false">{{ t('communityProjects.cancel') }}</Button>
        </div>
      </Card>
    </div>
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
.community-project-detail-page__dialog-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 2rem 1rem;
  z-index: 50;
}
.community-project-detail-page__dialog {
  width: 100%;
  max-width: 26rem;
  padding: 1rem 1.15rem;
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}
.community-project-detail-page__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.88rem;
}
.community-project-detail-page__input,
.community-project-detail-page__textarea {
  font: inherit;
  padding: 0.4rem 0.5rem;
  border-radius: 0.35rem;
  border: 1px solid var(--border);
}
.community-project-detail-page__dialog-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.35rem;
}
</style>
