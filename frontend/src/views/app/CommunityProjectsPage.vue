<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import CommunityHubTabs from '../../components/App/CommunityHubTabs.vue'
import ProjectBudgetFields from '../../molecules/ProjectBudgetFields.vue'
import ProjectJobPositionFields from '../../molecules/ProjectJobPositionFields.vue'
import ProjectLocationFields from '../../molecules/ProjectLocationFields.vue'
import { fetchCommunityBranding } from '../../composables/useCommunity'
import { sessionStatus } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { fetchCommunityMicrosite } from '../../services/communityApi'
import { createCommunityProject, fetchCommunityProjects } from '../../services/projectsApi.js'
import {
  PROJECT_STATUSES,
  buildBudgetPayload,
  buildJobPositionsPayload,
  deadlineFromInputValue,
  formatDeadline,
} from '../../utils/communityProjectForm.js'

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

const filterStatus = ref('')
const filterQ = ref('')
const filterHasBudget = ref(false)
const filterHasJobPositions = ref(false)
const filterHasDeadline = ref(false)

const form = ref({
  title: '',
  description: '',
  status: 'draft',
  deadlineInput: '',
  has_budget: false,
  has_job_positions: false,
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
const jobPositions = ref([])

watch(showCreate, (open) => {
  if (open) {
    form.value = {
      title: '',
      description: '',
      status: 'draft',
      deadlineInput: '',
      has_budget: false,
      has_job_positions: false,
    }
    locationDraft.value = {
      latitude: null,
      longitude: null,
      location_type: 'none',
      service_area_type: 'none',
      radius_meters: null,
      area_geojson: null,
    }
    budgetRows.value = []
    jobPositions.value = []
    pickerKey.value += 1
  }
})

function statusLabel(status) {
  const key = `communityProjects.status.${status}`
  const label = t(key)
  return label === key ? status : label
}

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
  const opts = { per_page: 50 }
  if (filterStatus.value) opts.status = filterStatus.value
  if (filterQ.value.trim()) opts.q = filterQ.value.trim()
  if (filterHasBudget.value) opts.has_budget = true
  if (filterHasJobPositions.value) opts.has_job_positions = true
  if (filterHasDeadline.value) opts.has_deadline = true
  const { ok, status, data } = await fetchCommunityProjects(slug.value, opts)
  loading.value = false
  if (!ok) {
    if (status === 401) loadError.value = t('communityProjects.loginToView')
    else if (status === 403) loadError.value = t('communityProjects.membersOnly')
    else loadError.value = t('communityProjects.loadError').replace('{status}', String(status))
    list.value = []
    return
  }
  list.value = Array.isArray(data?.data) ? data.data : []
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

function applyFilters() {
  void loadList()
}

async function submitCreate() {
  createError.value = ''
  createBusy.value = true
  const loc = locationDraft.value
  const f = form.value
  const payload = {
    title: f.title.trim(),
    description: f.description.trim() || undefined,
    status: f.status,
    deadline: f.deadlineInput ? deadlineFromInputValue(f.deadlineInput) : undefined,
    has_budget: f.has_budget,
    has_job_positions: f.has_job_positions,
    latitude: loc.latitude,
    longitude: loc.longitude,
    location_type: loc.location_type || 'none',
    service_area_type: loc.service_area_type || 'none',
    radius_meters: loc.radius_meters,
    area_geojson: loc.area_geojson,
  }
  if (f.has_budget) payload.budget_items = buildBudgetPayload(budgetRows.value)
  if (f.has_job_positions) payload.job_positions = buildJobPositionsPayload(jobPositions.value)
  const { ok, status, data } = await createCommunityProject(slug.value, payload)
  createBusy.value = false
  if (!ok) {
    createError.value =
      data && typeof data === 'object' && typeof data.message === 'string' ? data.message : String(status)
    return
  }
  const project = data && typeof data === 'object' ? data.project : null
  const id = project && typeof project.id === 'number' ? project.id : null
  showCreate.value = false
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

      <form v-if="isMember" class="community-projects-page__filters" @submit.prevent="applyFilters">
        <label class="community-projects-page__filter">
          <span>{{ t('communityProjects.filterStatus') }}</span>
          <select v-model="filterStatus" class="community-projects-page__input">
            <option value="">{{ t('communityProjects.filterAll') }}</option>
            <option v-for="s in PROJECT_STATUSES" :key="s" :value="s">{{ statusLabel(s) }}</option>
          </select>
        </label>
        <label class="community-projects-page__filter">
          <span>{{ t('communityProjects.filterSearch') }}</span>
          <input v-model="filterQ" type="search" class="community-projects-page__input" />
        </label>
        <label class="community-projects-page__filter-check">
          <input v-model="filterHasBudget" type="checkbox" />
          <span>{{ t('communityProjects.filterHasBudget') }}</span>
        </label>
        <label class="community-projects-page__filter-check">
          <input v-model="filterHasJobPositions" type="checkbox" />
          <span>{{ t('communityProjects.filterHasJobPositions') }}</span>
        </label>
        <label class="community-projects-page__filter-check">
          <input v-model="filterHasDeadline" type="checkbox" />
          <span>{{ t('communityProjects.filterHasDeadline') }}</span>
        </label>
        <Button type="submit">{{ t('communityProjects.filterApply') }}</Button>
      </form>

      <ul v-if="isMember" class="community-projects-page__list" role="list">
        <li v-for="row in list" :key="row.id" class="community-projects-page__item">
          <RouterLink
            class="community-projects-page__item-link"
            :to="{ name: 'communityProjectDetail', params: { slug, projectId: String(row.id) } }"
          >
            <span class="community-projects-page__item-title">{{ row.title }}</span>
            <span class="community-projects-page__item-meta">
              <span class="community-projects-page__badge">{{ statusLabel(row.status) }}</span>
              <span v-if="row.deadline" class="community-projects-page__badge community-projects-page__badge--deadline">
                {{ formatDeadline(row.deadline) }}
              </span>
              <span v-if="row.location_type === 'point' && row.latitude != null" class="community-projects-page__badge">
                {{ t('communityProjects.listHasLocation') }}
              </span>
              <span v-if="row.has_budget && parseFloat(String(row.budget_total || '0')) > 0" class="community-projects-page__badge community-projects-page__badge--budget">
                {{ t('communityProjects.listBudgetTotal').replace('{amount}', String(row.budget_total)) }}
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
          <span>{{ t('communityProjects.fieldStatus') }}</span>
          <select v-model="form.status" class="community-projects-page__input">
            <option v-for="s in PROJECT_STATUSES" :key="s" :value="s">{{ statusLabel(s) }}</option>
          </select>
        </label>
        <label class="community-projects-page__field">
          <span>{{ t('communityProjects.fieldDeadline') }}</span>
          <input v-model="form.deadlineInput" type="datetime-local" class="community-projects-page__input" />
        </label>
        <label class="community-projects-page__toggle">
          <input v-model="form.has_budget" type="checkbox" />
          <span>{{ t('communityProjects.hasBudget') }}</span>
        </label>
        <ProjectBudgetFields v-if="form.has_budget" v-model="budgetRows" />
        <label class="community-projects-page__toggle">
          <input v-model="form.has_job_positions" type="checkbox" />
          <span>{{ t('communityProjects.hasJobPositions') }}</span>
        </label>
        <ProjectJobPositionFields v-if="form.has_job_positions" v-model="jobPositions" />
        <div class="community-projects-page__field community-projects-page__field--full">
          <span>{{ t('communityProjects.locationBlockTitle') }}</span>
          <ProjectLocationFields :key="pickerKey" v-model="locationDraft" />
        </div>
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
.community-projects-page__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 0.75rem;
  align-items: flex-end;
  margin-bottom: 1rem;
  padding: 0.65rem 0.75rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
}
.community-projects-page__filter {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.82rem;
  min-width: 8rem;
}
.community-projects-page__filter-check,
.community-projects-page__toggle {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.85rem;
}
.community-projects-page__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
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
.community-projects-page__badge--deadline {
  background: color-mix(in srgb, #b45309 14%, var(--bg));
  color: #78350f;
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
