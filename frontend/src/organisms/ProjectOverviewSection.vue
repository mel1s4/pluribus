<script setup>
import { ref, watch } from 'vue'
import Button from '../atoms/Button.vue'
import Card from '../atoms/Card.vue'
import Title from '../atoms/Title.vue'
import ProjectBudgetFields from '../molecules/ProjectBudgetFields.vue'
import ProjectLocationFields from '../molecules/ProjectLocationFields.vue'
import { t } from '../i18n/i18n'
import { updateCommunityProject } from '../services/projectsApi.js'

const props = defineProps({
  slug: { type: String, required: true },
  projectId: { type: Number, required: true },
  project: { type: Object, required: true },
})

const emit = defineEmits(['updated'])

const editing = ref(false)
const saveBusy = ref(false)
const saveError = ref('')
const locationDraft = ref(emptyLocation(props.project))
const budgetRows = ref(budgetFromProject(props.project))
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

function budgetFromProject(p) {
  const items = Array.isArray(p?.budget_items) ? p.budget_items : []
  return items.map((r) => ({
    name: r.name || '',
    description: r.description || '',
    cost: r.cost != null ? String(r.cost) : '',
  }))
}

watch(
  () => props.project,
  (p) => {
    if (!p || editing.value) return
    locationDraft.value = emptyLocation(p)
    budgetRows.value = budgetFromProject(p)
    pickerKey.value += 1
  },
  { deep: true },
)

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
  if (sat === 'polygon') {
    return t('communityProjects.areaPolygonSummary')
  }
  return ''
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

async function saveMeta() {
  saveError.value = ''
  saveBusy.value = true
  const loc = locationDraft.value
  const payload = {
    latitude: loc.latitude,
    longitude: loc.longitude,
    location_type: loc.location_type || 'none',
    service_area_type: loc.service_area_type || 'none',
    radius_meters: loc.radius_meters,
    area_geojson: loc.area_geojson,
    budget_items: buildBudgetPayload(),
  }
  const { ok, status, data } = await updateCommunityProject(props.slug, props.projectId, payload)
  saveBusy.value = false
  if (!ok) {
    saveError.value =
      (data && typeof data === 'object' && typeof data.message === 'string' && data.message) || String(status)
    return
  }
  editing.value = false
  emit('updated')
}

function startEdit() {
  locationDraft.value = emptyLocation(props.project)
  budgetRows.value = budgetFromProject(props.project)
  pickerKey.value += 1
  editing.value = true
}
</script>

<template>
  <Card class="project-overview-section">
    <div class="project-overview-section__head">
      <Title tag="h2">{{ t('communityProjects.overviewTitle') }}</Title>
      <Button v-if="project.can_update && !editing" type="button" @click="startEdit">{{ t('communityProjects.editMeta') }}</Button>
    </div>

    <template v-if="!editing">
      <p v-if="hasMapPin()" class="project-overview-section__row">
        <span class="project-overview-section__label">{{ t('communityProjects.locationLabel') }}</span>
        <a
          v-if="osmUrl()"
          class="project-overview-section__link"
          :href="osmUrl()"
          target="_blank"
          rel="noopener noreferrer"
        >
          {{ project.latitude?.toFixed?.(5) ?? project.latitude }}, {{ project.longitude?.toFixed?.(5) ?? project.longitude }}
        </a>
        <span v-else>{{ project.latitude }}, {{ project.longitude }}</span>
      </p>
      <p v-if="areaSummary()" class="project-overview-section__row">
        <span class="project-overview-section__label">{{ t('communityProjects.areaLabel') }}</span>
        <span>{{ areaSummary() }}</span>
      </p>
      <p v-if="!hasMapPin() && !areaSummary()" class="project-overview-section__muted">{{ t('communityProjects.noLocation') }}</p>

      <div v-if="Array.isArray(project.budget_items) && project.budget_items.length" class="project-overview-section__budget">
        <div class="project-overview-section__budget-head">
          <span>{{ t('communityProjects.budgetHeading') }}</span>
          <strong class="project-overview-section__total">{{ t('communityProjects.budgetTotal') }}: {{ project.budget_sum }}</strong>
        </div>
        <table class="project-overview-section__table">
          <thead>
            <tr>
              <th>{{ t('communityProjects.budgetName') }}</th>
              <th>{{ t('communityProjects.budgetDescription') }}</th>
              <th>{{ t('communityProjects.budgetCost') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in project.budget_items" :key="row.id">
              <td>{{ row.name }}</td>
              <td>{{ row.description }}</td>
              <td>{{ row.cost }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="project-overview-section__muted">{{ t('communityProjects.noBudget') }}</p>
    </template>

    <template v-else>
      <p v-if="saveError" class="project-overview-section__error">{{ saveError }}</p>
      <ProjectLocationFields :key="pickerKey" v-model="locationDraft" />
      <ProjectBudgetFields v-model="budgetRows" />
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
.project-overview-section__budget {
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
.project-overview-section__total {
  font-weight: 700;
}
.project-overview-section__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
}
.project-overview-section__table th,
.project-overview-section__table td {
  border: 1px solid var(--border);
  padding: 0.35rem 0.45rem;
  text-align: left;
  vertical-align: top;
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
