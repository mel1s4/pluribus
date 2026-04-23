<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import PlaceAdministratorsSection from '../../components/App/PlaceAdministratorsSection.vue'
import PlaceAudiencesSection from '../../components/App/PlaceAudiencesSection.vue'
import PlaceOffersSection from '../../components/App/PlaceOffersSection.vue'
import PlaceBasicsForm from '../../organisms/PlaceBasicsForm.vue'
import { t } from '../../i18n/i18n'
import { deletePlace, fetchPlace, updatePlace } from '../../services/placesApi.js'
import { placeApiErrorMessage, placeToFormData } from '../../utils/placeForm.js'
import { normalizeServiceSchedule } from '../../utils/placeSchedule.js'

const route = useRoute()
const router = useRouter()

const place = ref(null)
const draft = ref(null)
const loadError = ref('')
const loading = ref(true)
const saveError = ref('')
const saveLoading = ref(false)
const pickerKey = ref(0)
const tab = ref('edit')

const canManageAdmins = computed(() => Boolean(place.value?.can_manage_admins))

const placeId = computed(() => {
  const raw = route.params.placeId
  return typeof raw === 'string' ? raw : ''
})

function draftFromPlace(p) {
  const loc = p.location && typeof p.location === 'object' ? p.location : {}
  return {
    id: p.id,
    name: p.name,
    description: p.description ?? '',
    tags: Array.isArray(p.tags) ? [...p.tags] : [],
    latitude: loc.latitude ?? p.latitude ?? null,
    longitude: loc.longitude ?? p.longitude ?? null,
    location_type: p.location_type || 'none',
    service_area_type: p.service_area_type,
    radius_meters: p.radius_meters,
    area_geojson: p.area_geojson,
    logo_url: p.logo_url ?? null,
    logoFile: null,
    removeLogo: false,
    service_schedule: normalizeServiceSchedule(p.service_schedule),
  }
}

async function load() {
  const id = placeId.value
  if (!id) return
  loadError.value = ''
  loading.value = true
  place.value = null
  draft.value = null
  const { ok, status, data } = await fetchPlace(id)
  loading.value = false
  if (!ok) {
    loadError.value = placeApiErrorMessage(data, status, t('myPlaces.loadOneError'))
    if (status === 403 || status === 404) {
      setTimeout(() => router.replace({ name: 'myPlaces' }), 1600)
    }
    return
  }
  const p = data?.place
  if (!p) {
    loadError.value = t('myPlaces.loadOneError').replace('{status}', String(status))
    setTimeout(() => router.replace({ name: 'myPlaces' }), 1600)
    return
  }
  place.value = p
  draft.value = draftFromPlace(p)
  pickerKey.value += 1
  tab.value = 'edit'
}

watch([canManageAdmins, tab], () => {
  if (tab.value === 'administrators' && place.value && !canManageAdmins.value) {
    tab.value = 'edit'
  }
})

watch(placeId, () => {
  load()
})

async function onSubmit() {
  if (!draft.value?.id) return
  saveError.value = ''
  saveLoading.value = true
  const d = draft.value
  const useMultipart = d.logoFile instanceof File || d.removeLogo
  const payload = useMultipart
    ? placeToFormData(d)
    : {
        name: d.name.trim(),
        description: d.description?.trim() || null,
        tags: Array.isArray(d.tags) ? d.tags : [],
        latitude: d.latitude,
        longitude: d.longitude,
        location_type: d.location_type || 'none',
        service_area_type: d.service_area_type,
        radius_meters: d.radius_meters,
        area_geojson: d.area_geojson,
        service_schedule: normalizeServiceSchedule(d.service_schedule),
      }
  const { ok, status, data } = await updatePlace(d.id, payload)
  saveLoading.value = false
  if (!ok) {
    saveError.value = placeApiErrorMessage(data, status, t('myPlaces.saveError'))
    return
  }
  await load()
}

async function onDelete() {
  if (!draft.value?.id) return
  if (!window.confirm(t('myPlaces.deletePlaceConfirm'))) return
  saveLoading.value = true
  const { ok, status, data } = await deletePlace(draft.value.id)
  saveLoading.value = false
  if (!ok) {
    saveError.value = placeApiErrorMessage(data, status, t('myPlaces.deleteError'))
    return
  }
  await router.push({ name: 'myPlaces' })
}

function goBack() {
  router.push({ name: 'myPlaces' })
}

function onTabClick(next) {
  tab.value = next
}

function setDraft(next) {
  draft.value = next
}

load()
</script>

<template>
  <div class="place-edit-page">
    <div class="place-edit-page__head">
      <button type="button" class="place-edit-page__back" @click="goBack">
        {{ t('myPlaces.backToPlaces') }}
      </button>
    </div>

    <p v-if="loading" class="place-edit-page__muted">
      {{ t('myPlaces.loading') }}
    </p>

    <template v-else-if="place && draft">
      <Title tag="h1" class="place-edit-page__title">{{ place.name }}</Title>

      <div
        class="place-edit-page__tabs"
        role="tablist"
      >
        <button
          type="button"
          class="place-edit-page__tab"
          :class="{ 'is-active': tab === 'edit' }"
          role="tab"
          :aria-selected="tab === 'edit'"
          @click="onTabClick('edit')"
        >
          {{ t('myPlaces.tabEdit') }}
        </button>
        <button
          type="button"
          class="place-edit-page__tab"
          :class="{ 'is-active': tab === 'offers' }"
          role="tab"
          :aria-selected="tab === 'offers'"
          @click="onTabClick('offers')"
        >
          {{ t('myPlaces.tabOffers') }}
        </button>
        <button
          type="button"
          class="place-edit-page__tab"
          :class="{ 'is-active': tab === 'audiences' }"
          role="tab"
          :aria-selected="tab === 'audiences'"
          @click="onTabClick('audiences')"
        >
          {{ t('myPlaces.tabAudiences') }}
        </button>
        <button
          v-if="canManageAdmins"
          type="button"
          class="place-edit-page__tab"
          :class="{ 'is-active': tab === 'administrators' }"
          role="tab"
          :aria-selected="tab === 'administrators'"
          @click="onTabClick('administrators')"
        >
          {{ t('myPlaces.tabAdministrators') }}
        </button>
      </div>

      <div class="place-edit-page__panel">
        <Card v-if="tab === 'edit'" class="place-edit-page__card">
          <PlaceBasicsForm
            mode="edit"
            :model-value="draft"
            :save-error="saveError"
            :save-loading="saveLoading"
            :picker-key="pickerKey"
            @update:model-value="setDraft"
            @submit="onSubmit"
            @delete="onDelete"
          />
        </Card>
        <PlaceOffersSection
          v-else-if="tab === 'offers'"
          :place-id="place.id"
          @changed="load"
        />
        <PlaceAudiencesSection
          v-else-if="tab === 'audiences'"
          :place-id="place.id"
        />
        <PlaceAdministratorsSection
          v-else-if="tab === 'administrators'"
          :place-id="place.id"
          :owner-user-id="Number(place.user_id)"
        />
      </div>
    </template>

    <div v-else class="place-edit-page__errorWrap">
      <p class="place-edit-page__error" role="alert">
        {{ loadError }}
      </p>
      <Button variant="secondary" @click="router.push({ name: 'myPlaces' })">
        {{ t('myPlaces.backToPlaces') }}
      </Button>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.place-edit-page {
  max-width: 52rem;
  margin: 0 auto;
  padding: 1rem 1rem 2rem;
}

.place-edit-page__head {
  margin-bottom: 0.75rem;
}

.place-edit-page__back {
  cursor: pointer;
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
  font: inherit;
}

.place-edit-page__title {
  margin: 0 0 1rem;
}

.place-edit-page__muted {
  margin: 0;
  color: var(--text-muted, #64748b);
}

.place-edit-page__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-bottom: 1rem;
  border-bottom: 1px solid var(--border);
}

.place-edit-page__tab {
  padding: 0.5rem 0.85rem;
  border: none;
  border-radius: 0.5rem 0.5rem 0 0;
  background: transparent;
  color: var(--text);
  font-weight: 600;
  cursor: pointer;
  opacity: 0.75;
}

.place-edit-page__tab:hover {
  opacity: 1;
  background: var(--btn-bg);
}

.place-edit-page__tab.is-active {
  opacity: 1;
  color: var(--link);
  border: 1px solid var(--border);
  border-bottom-color: var(--bg);
  margin-bottom: -1px;
}

.place-edit-page__panel {
  padding-top: 0.25rem;
}

.place-edit-page__error {
  color: #b91c1c;
  margin: 0 0 1rem;
}

.place-edit-page__errorWrap {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.75rem;
}
</style>
