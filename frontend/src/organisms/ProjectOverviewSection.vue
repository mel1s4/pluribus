<script setup>
import { ref, watch } from 'vue'
import Button from '../atoms/Button.vue'
import Card from '../atoms/Card.vue'
import Title from '../atoms/Title.vue'
import ProjectBudgetFields from '../molecules/ProjectBudgetFields.vue'
import ProjectJobPositionFields from '../molecules/ProjectJobPositionFields.vue'
import ProjectLocationFields from '../molecules/ProjectLocationFields.vue'
import { t } from '../i18n/i18n'
import { deleteCommunityProject, updateCommunityProject } from '../services/projectsApi.js'
import {
  PROJECT_STATUSES,
  budgetRowsFromProject,
  buildBudgetPayload,
  buildJobPositionsPayload,
  deadlineFromInputValue,
  deadlineToInputValue,
  formatDeadline,
  jobPositionsFromProject,
} from '../utils/communityProjectForm.js'

const props = defineProps({
  slug: { type: String, required: true },
  projectId: { type: Number, required: true },
  project: { type: Object, required: true },
})

const emit = defineEmits(['updated', 'deleted'])

const editing = ref(false)
const saveBusy = ref(false)
const deleteBusy = ref(false)
const saveError = ref('')
const meta = ref(emptyMeta(props.project))
const locationDraft = ref(emptyLocation(props.project))
const budgetRows = ref(budgetRowsFromProject(props.project))
const jobPositions = ref(jobPositionsFromProject(props.project))
const pickerKey = ref(0)

function emptyLocation(p) {
  return {
    latitude: p?.latitude ?? null,
    longitude: p?.longitude ?? null,
    location_type: p?.location_type || 'none',
    service_area_type: p?.service_area_type || 'none',
    radius_meters: p?.radius_meters ?? null,
    area_geojson: p?.area_geojson ?? null,
  }
}

function emptyMeta(p) {
  return {
    title: p?.title || '',
    description: p?.description || '',
    status: p?.status || 'draft',
    deadlineInput: deadlineToInputValue(p?.deadline),
    has_budget: Boolean(p?.has_budget),
    has_job_positions: Boolean(p?.has_job_positions),
  }
}

function resetFromProject(p) {
  meta.value = emptyMeta(p)
  locationDraft.value = emptyLocation(p)
  budgetRows.value = budgetRowsFromProject(p)
  jobPositions.value = jobPositionsFromProject(p)
  pickerKey.value += 1
}

watch(
  () => props.project,
  (p) => {
    if (!p || editing.value) return
    resetFromProject(p)
  },
  { deep: true },
)

function statusLabel(status) {
  const key = `communityProjects.status.${status}`
  const label = t(key)
  return label === key ? status : label
}

function hasMapPin() {
  const p = props.project
  return p?.location_type === 'point' && p.latitude != null && p.longitude != null
}

function osmUrl() {
  const p = props.project
  if (!hasMapPin()) return ''
  return `https://www.openstreetmap.org/?mlat=${encodeURIComponent(p.latitude)}&mlon=${encodeURIComponent(p.longitude)}#map=14/${p.latitude}/${p.longitude}`
}

function areaSummary() {
  const p = props.project
  const sat = p?.service_area_type || 'none'
  if (sat === 'radius' && p?.radius_meters) {
    return t('communityProjects.areaRadiusSummary').replace('{m}', String(p.radius_meters))
  }
  if (sat === 'polygon') return t('communityProjects.areaPolygonSummary')
  return ''
}

function buildPayload() {
  const m = meta.value
  const loc = locationDraft.value
  const payload = {
    title: m.title.trim(),
    description: m.description.trim() || null,
    status: m.status,
    deadline: m.deadlineInput ? deadlineFromInputValue(m.deadlineInput) : null,
    has_budget: m.has_budget,
    has_job_positions: m.has_job_positions,
    latitude: loc.latitude,
    longitude: loc.longitude,
    location_type: loc.location_type || 'none',
    service_area_type: loc.service_area_type || 'none',
    radius_meters: loc.radius_meters,
    area_geojson: loc.area_geojson,
  }
  if (m.has_budget) payload.budget_items = buildBudgetPayload(budgetRows.value)
  if (m.has_job_positions) payload.job_positions = buildJobPositionsPayload(jobPositions.value)
  return payload
}

async function saveMeta() {
  saveError.value = ''
  saveBusy.value = true
  const { ok, status, data } = await updateCommunityProject(props.slug, props.projectId, buildPayload())
  saveBusy.value = false
  if (!ok) {
    saveError.value =
      (data && typeof data === 'object' && typeof data.message === 'string' && data.message) || String(status)
    return
  }
  editing.value = false
  emit('updated')
}

async function onDelete() {
  if (!window.confirm(t('communityProjects.deleteProjectConfirm'))) return
  deleteBusy.value = true
  const { ok } = await deleteCommunityProject(props.slug, props.projectId)
  deleteBusy.value = false
  if (ok) emit('deleted')
}

function startEdit() {
  resetFromProject(props.project)
  editing.value = true
}
</script>

<template>
  <Card class="project-overview-section">
    <div class="project-overview-section__head">
      <Title tag="h2">{{ t('communityProjects.overviewTitle') }}</Title>
      <div v-if="project.can_update" class="project-overview-section__head-actions">
        <Button v-if="!editing" type="button" @click="startEdit">{{ t('communityProjects.editMeta') }}</Button>
        <Button v-if="project.can_delete && !editing" type="button" :disabled="deleteBusy" @click="onDelete">
          {{ t('communityProjects.deleteProject') }}
        </Button>
      </div>
    </div>

    <template v-if="!editing">
      <p v-if="project.description" class="project-overview-section__desc">{{ project.description }}</p>
      <p class="project-overview-section__row">
        <span class="project-overview-section__label">{{ t('communityProjects.fieldStatus') }}</span>
        <span>{{ statusLabel(project.status) }}</span>
      </p>
      <p v-if="project.community?.name" class="project-overview-section__row">
        <span class="project-overview-section__label">{{ t('communityProjects.fieldCommunity') }}</span>
        <span>{{ project.community.name }}</span>
      </p>
      <p v-if="project.deadline" class="project-overview-section__row">
        <span class="project-overview-section__label">{{ t('communityProjects.fieldDeadline') }}</span>
        <span>{{ formatDeadline(project.deadline) }}</span>
      </p>

      <p v-if="hasMapPin()" class="project-overview-section__row">
        <span class="project-overview-section__label">{{ t('communityProjects.locationLabel') }}</span>
        <a v-if="osmUrl()" class="project-overview-section__link" :href="osmUrl()" target="_blank" rel="noopener noreferrer">
          {{ project.latitude?.toFixed?.(5) ?? project.latitude }}, {{ project.longitude?.toFixed?.(5) ?? project.longitude }}
        </a>
      </p>
      <p v-if="areaSummary()" class="project-overview-section__row">
        <span class="project-overview-section__label">{{ t('communityProjects.areaLabel') }}</span>
        <span>{{ areaSummary() }}</span>
      </p>
      <p v-if="!hasMapPin() && !areaSummary()" class="project-overview-section__muted">{{ t('communityProjects.noLocation') }}</p>

      <div v-if="project.has_budget && project.budget_items?.length" class="project-overview-section__budget">
        <div class="project-overview-section__budget-head">
          <span>{{ t('communityProjects.budgetHeading') }}</span>
          <strong>{{ t('communityProjects.budgetTotal') }}: {{ project.budget_total }}</strong>
        </div>
        <table class="project-overview-section__table">
          <thead>
            <tr>
              <th>{{ t('communityProjects.budgetName') }}</th>
              <th>{{ t('communityProjects.budgetDescription') }}</th>
              <th>{{ t('communityProjects.budgetUnitCost') }}</th>
              <th>{{ t('communityProjects.budgetUnits') }}</th>
              <th>{{ t('communityProjects.budgetSubtotal') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in project.budget_items" :key="row.id">
              <td>{{ row.name }}</td>
              <td>{{ row.description }}</td>
              <td>{{ row.unit_cost }}</td>
              <td>{{ row.units }}</td>
              <td>{{ row.subtotal }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else-if="!project.has_budget" class="project-overview-section__muted">{{ t('communityProjects.noBudget') }}</p>

      <div v-if="project.has_job_positions && project.job_positions?.length" class="project-overview-section__jobs">
        <h3 class="project-overview-section__jobs-title">{{ t('communityProjects.jobHeading') }}</h3>
        <div v-for="pos in project.job_positions" :key="pos.id" class="project-overview-section__job">
          <strong>{{ pos.title }}</strong>
          <ul v-if="pos.tasks?.length" role="list">
            <li v-for="task in pos.tasks" :key="task.id">{{ task.body }}</li>
          </ul>
        </div>
      </div>
      <p v-else-if="!project.has_job_positions" class="project-overview-section__muted">{{ t('communityProjects.noJobPositions') }}</p>
    </template>

    <template v-else>
      <p v-if="saveError" class="project-overview-section__error">{{ saveError }}</p>
      <label class="project-overview-section__field">
        <span>{{ t('communityProjects.fieldTitle') }}</span>
        <input v-model="meta.title" type="text" class="project-overview-section__input" maxlength="255" />
      </label>
      <label class="project-overview-section__field">
        <span>{{ t('communityProjects.fieldDescription') }}</span>
        <textarea v-model="meta.description" class="project-overview-section__textarea" rows="2" />
      </label>
      <label class="project-overview-section__field">
        <span>{{ t('communityProjects.fieldStatus') }}</span>
        <select v-model="meta.status" class="project-overview-section__input">
          <option v-for="s in PROJECT_STATUSES" :key="s" :value="s">{{ statusLabel(s) }}</option>
        </select>
      </label>
      <label class="project-overview-section__field">
        <span>{{ t('communityProjects.fieldDeadline') }}</span>
        <input v-model="meta.deadlineInput" type="datetime-local" class="project-overview-section__input" />
      </label>
      <label class="project-overview-section__toggle">
        <input v-model="meta.has_budget" type="checkbox" />
        <span>{{ t('communityProjects.hasBudget') }}</span>
      </label>
      <ProjectBudgetFields v-if="meta.has_budget" v-model="budgetRows" />
      <label class="project-overview-section__toggle">
        <input v-model="meta.has_job_positions" type="checkbox" />
        <span>{{ t('communityProjects.hasJobPositions') }}</span>
      </label>
      <ProjectJobPositionFields v-if="meta.has_job_positions" v-model="jobPositions" />
      <div class="project-overview-section__field">
        <span>{{ t('communityProjects.locationBlockTitle') }}</span>
        <ProjectLocationFields :key="pickerKey" v-model="locationDraft" />
      </div>
      <div class="project-overview-section__actions">
        <Button type="button" :disabled="saveBusy" @click="saveMeta">{{ t('communityProjects.saveMeta') }}</Button>
        <Button type="button" :disabled="saveBusy" @click="editing = false">{{ t('communityProjects.cancel') }}</Button>
      </div>
    </template>
  </Card>
</template>

<style lang="scss" scoped>
.project-overview-section {
  margin-bottom: 1.25rem;
  padding: 1rem 1.1rem;
}
.project-overview-section__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.65rem;
}
.project-overview-section__head-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.project-overview-section__desc {
  margin: 0 0 0.5rem;
  line-height: 1.45;
}
.project-overview-section__row {
  margin: 0.35rem 0;
  font-size: 0.92rem;
}
.project-overview-section__label {
  font-weight: 600;
  margin-right: 0.35rem;
}
.project-overview-section__link {
  color: var(--link, #2563eb);
}
.project-overview-section__muted {
  margin: 0.35rem 0;
  font-size: 0.88rem;
  opacity: 0.8;
}
.project-overview-section__budget,
.project-overview-section__jobs {
  margin-top: 0.75rem;
}
.project-overview-section__budget-head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.35rem;
  font-size: 0.9rem;
}
.project-overview-section__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.project-overview-section__table th,
.project-overview-section__table td {
  border: 1px solid var(--border);
  padding: 0.35rem 0.45rem;
  text-align: left;
  vertical-align: top;
}
.project-overview-section__jobs-title {
  margin: 0 0 0.5rem;
  font-size: 1rem;
}
.project-overview-section__job {
  margin-bottom: 0.65rem;
  font-size: 0.9rem;
}
.project-overview-section__field,
.project-overview-section__toggle {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.88rem;
  margin-bottom: 0.5rem;
}
.project-overview-section__toggle {
  flex-direction: row;
  align-items: center;
}
.project-overview-section__input,
.project-overview-section__textarea {
  font: inherit;
  padding: 0.4rem 0.5rem;
  border-radius: 0.35rem;
  border: 1px solid var(--border);
}
.project-overview-section__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.65rem;
}
.project-overview-section__error {
  color: #b91c1c;
  margin: 0 0 0.35rem;
}
</style>
