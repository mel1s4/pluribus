<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import CommunityHubTabs from '../../components/App/CommunityHubTabs.vue'
import ProjectBudgetFields from '../../molecules/ProjectBudgetFields.vue'
import ProjectLocationFields from '../../molecules/ProjectLocationFields.vue'
import { fetchCommunityBranding } from '../../composables/useCommunity'
import { sessionStatus } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { fetchCommunityMicrosite } from '../../services/communityApi'
import { createCommunityProject, fetchCommunityProjects } from '../../services/projectsApi.js'

const route = useRoute()
const router = useRouter()

const slug = computed(() => {
  const raw = route.params.slug
  return typeof raw === 'string' ? raw.trim() : ''
})

const loading = ref(true)
const loadError = ref('')
const list = ref([])
const isMember = ref(false)
const showCreate = ref(false)
const createBusy = ref(false)
const createError = ref('')
const pickerKey = ref(0)
const form = ref({
  title: '',
  description: '',
  thesis_title: '',
  thesis_body: '',
})
const locationDraft = ref({
  latitude: null,
  longitude: null,
  location_type: 'none',
  service_area_type: 'none',
  radius_meters: null,
  area_geojson: null,
})
const budgetRows = ref([])

watch(showCreate, (open) => {
  if (open) {
    locationDraft.value = {
      latitude: null,
      longitude: null,
      location_type: 'none',
      service_area_type: 'none',
      radius_meters: null,
      area_geojson: null,
    }
    budgetRows.value = []
    pickerKey.value += 1
  }
})

async function loadMicrositeFlags() {
  if (!slug.value) return
  const { ok, data } = await fetchCommunityMicrosite(slug.value)
  if (ok && data && typeof data === 'object') {
    isMember.value = Boolean(data.is_member)
  }
}

async function loadList() {
  if (!slug.value) return
  loading.value = true
  loadError.value = ''
  const { ok, status, data } = await fetchCommunityProjects(slug.value, { per_page: 50 })
  loading.value = false
  if (!ok) {
    if (status === 401) {
      loadError.value = t('communityProjects.loginToView')
    } else if (status === 403) {
      loadError.value = t('communityProjects.membersOnly')
    } else {
      loadError.value = t('communityProjects.loadError').replace('{status}', String(status))
    }
    list.value = []
    return
  }
  const rows = Array.isArray(data?.data) ? data.data : []
  list.value = rows
}

async function load() {
  await loadMicrositeFlags()
  await fetchCommunityBranding(slug.value)
  await loadList()
}

onMounted(load)
watch(slug, () => {
  void load()
})

function goLogin() {
  router.push({ name: 'login', query: { redirect: route.fullPath } })
}

function buildBudgetPayload() {
  return budgetRows.value
    .filter((r) => typeof r.name === 'string' && r.name.trim() !== '')
    .map((r, i) => ({
      name: r.name.trim(),
      description: typeof r.description === 'string' && r.description.trim() !== '' ? r.description.trim() : undefined,
      cost: String(r.cost ?? '0').trim() || '0',
      sort_order: i,
    }))
}

async function submitCreate() {
  createError.value = ''
  createBusy.value = true
  const loc = locationDraft.value
  const payload = {
    title: form.value.title.trim(),
    description: form.value.description.trim() || undefined,
    thesis_title: form.value.thesis_title.trim(),
    thesis_body: form.value.thesis_body.trim() || undefined,
    latitude: loc.latitude,
    longitude: loc.longitude,
    location_type: loc.location_type || 'none',
    service_area_type: loc.service_area_type || 'none',
    radius_meters: loc.radius_meters,
    area_geojson: loc.area_geojson,
    budget_items: buildBudgetPayload(),
  }
  const { ok, status, data } = await createCommunityProject(slug.value, payload)
  createBusy.value = false
  if (!ok) {
    const msg =
      data && typeof data === 'object' && typeof data.message === 'string' ? data.message : String(status)
    createError.value = msg
    return
  }
  const project = data && typeof data === 'object' ? data.project : null
  const id = project && typeof project.id === 'number' ? project.id : null
  showCreate.value = false
  form.value = { title: '', description: '', thesis_title: '', thesis_body: '' }
  if (id != null) {
    router.push({ name: 'communityProjectDetail', params: { slug: slug.value, projectId: String(id) } })
  } else {
    void loadList()
  }
}
</script>

<template>
  <section class="community-projects-page">
    <PageToolbarTitle route-key="community-projects">
      <Title tag="h1">{{ t('communityProjects.listHeading') }}</Title>
    </PageToolbarTitle>

    <p v-if="slug" class="community-projects-page__back">
      <RouterLink class="community-projects-page__link" :to="{ name: 'communityMicrosite', params: { slug } }">
        {{ t('communityProjects.backToHub') }}
      </RouterLink>
    </p>

    <CommunityHubTabs v-if="slug && !loading" :slug="slug" :is-member="isMember" />

    <p v-if="loadError" class="community-projects-page__error" role="alert">{{ loadError }}</p>
    <p v-else-if="loading" class="community-projects-page__muted">{{ t('communityProjects.loading') }}</p>
    <template v-else>
      <p v-if="sessionStatus === 'authenticated' && isMember" class="community-projects-page__actions">
        <Button type="button" @click="showCreate = true">{{ t('communityProjects.newProject') }}</Button>
      </p>
      <p v-else-if="sessionStatus !== 'authenticated'" class="community-projects-page__cta">
        {{ t('communityProjects.loginToView') }}
        <Button type="button" @click="goLogin">{{ t('communityCredits.logIn') }}</Button>
      </p>
      <p v-else class="community-projects-page__muted">{{ t('communityProjects.membersOnly') }}</p>

      <ul v-if="isMember" class="community-projects-page__list" role="list">
        <li v-for="row in list" :key="row.id" class="community-projects-page__item">
          <RouterLink
            class="community-projects-page__item-link"
            :to="{ name: 'communityProjectDetail', params: { slug, projectId: String(row.id) } }"
          >
            <span class="community-projects-page__item-title">{{ row.title }}</span>
            <span class="community-projects-page__item-thesis">{{ row.thesis_title }}</span>
            <span class="community-projects-page__item-meta">
              <span v-if="row.location_type === 'point' && row.latitude != null && row.longitude != null" class="community-projects-page__badge">{{
                t('communityProjects.listHasLocation')
              }}</span>
              <span v-if="parseFloat(String(row.budget_sum || '0')) > 0" class="community-projects-page__badge community-projects-page__badge--budget">
                {{ t('communityProjects.listBudgetTotal').replace('{amount}', String(row.budget_sum)) }}
              </span>
            </span>
          </RouterLink>
        </li>
      </ul>
      <p v-if="isMember && !list.length" class="community-projects-page__muted">{{ t('communityProjects.empty') }}</p>
    </template>

    <div v-if="showCreate" class="community-projects-page__dialog-backdrop" role="presentation" @click.self="showCreate = false">
      <Card class="community-projects-page__dialog">
        <Title tag="h2">{{ t('communityProjects.createTitle') }}</Title>
        <p v-if="createError" class="community-projects-page__error">{{ createError }}</p>
        <label class="community-projects-page__field">
          <span>{{ t('communityProjects.fieldTitle') }}</span>
          <input v-model="form.title" type="text" class="community-projects-page__input" maxlength="255" />
        </label>
        <label class="community-projects-page__field">
          <span>{{ t('communityProjects.fieldDescription') }}</span>
          <textarea v-model="form.description" class="community-projects-page__textarea" rows="2" />
        </label>
        <label class="community-projects-page__field">
          <span>{{ t('communityProjects.fieldThesis') }}</span>
          <input v-model="form.thesis_title" type="text" class="community-projects-page__input" maxlength="500" />
        </label>
        <label class="community-projects-page__field">
          <span>{{ t('communityProjects.fieldThesisBody') }}</span>
          <textarea v-model="form.thesis_body" class="community-projects-page__textarea" rows="3" />
        </label>
        <div class="community-projects-page__field community-projects-page__field--full">
          <span>{{ t('communityProjects.locationBlockTitle') }}</span>
          <ProjectLocationFields :key="pickerKey" v-model="locationDraft" />
        </div>
        <ProjectBudgetFields v-model="budgetRows" />
        <div class="community-projects-page__dialog-actions">
          <Button type="button" :disabled="createBusy" @click="submitCreate">{{ t('communityProjects.createSubmit') }}</Button>
          <Button type="button" :disabled="createBusy" @click="showCreate = false">{{ t('communityProjects.cancel') }}</Button>
        </div>
      </Card>
    </div>
  </section>
</template>

<style lang="scss" scoped>
.community-projects-page {
  max-width: 44rem;
  margin: 0 auto;
  padding: 1.5rem 1rem 2rem;
}
.community-projects-page__back {
  margin: 0 0 0.5rem;
}
.community-projects-page__link {
  color: var(--link, #2563eb);
  text-decoration: none;
  &:hover {
    text-decoration: underline;
  }
}
.community-projects-page__actions {
  margin: 0.5rem 0 1rem;
}
.community-projects-page__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.community-projects-page__item {
  margin: 0;
}
.community-projects-page__item-link {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.65rem 0.75rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
  text-decoration: none;
  color: inherit;
  &:hover {
    border-color: color-mix(in srgb, var(--border) 70%, #1d4ed8);
  }
}
.community-projects-page__item-title {
  font-weight: 600;
}
.community-projects-page__item-thesis {
  font-size: 0.88rem;
  opacity: 0.8;
}
.community-projects-page__item-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 0.25rem;
}
.community-projects-page__badge {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0.1rem 0.35rem;
  border-radius: 0.25rem;
  background: color-mix(in srgb, #1d4ed8 12%, var(--bg));
  color: #1e3a8a;
}
.community-projects-page__badge--budget {
  background: color-mix(in srgb, #15803d 14%, var(--bg));
  color: #14532d;
}
.community-projects-page__muted {
  color: var(--muted, #6b7280);
}
.community-projects-page__error {
  color: #b91c1c;
}
.community-projects-page__cta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}
.community-projects-page__dialog-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 2rem 1rem;
  z-index: 50;
}
.community-projects-page__dialog {
  width: 100%;
  max-width: 40rem;
  max-height: calc(100vh - 4rem);
  overflow: auto;
  padding: 1rem 1.15rem;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}
.community-projects-page__field--full {
  min-width: 0;
}
.community-projects-page__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.88rem;
}
.community-projects-page__input,
.community-projects-page__textarea {
  font: inherit;
  padding: 0.4rem 0.5rem;
  border-radius: 0.35rem;
  border: 1px solid var(--border);
}
.community-projects-page__dialog-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.35rem;
}
</style>
