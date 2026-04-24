<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import PlaceLocationPicker from '../../organisms/PlaceLocationPicker.vue'
import PlaceOffersPublicList from '../../molecules/PlaceOffersPublicList.vue'
import PlaceRequirementsPublicList from '../../molecules/PlaceRequirementsPublicList.vue'
import PlaceServiceScheduleDisplay from '../../molecules/PlaceServiceScheduleDisplay.vue'
import { t } from '../../i18n/i18n'
import { fetchPlace } from '../../services/placesApi.js'
import { placeApiErrorMessage } from '../../utils/placeForm.js'

const route = useRoute()
const router = useRouter()

const place = ref(null)
const loadError = ref('')
const loading = ref(true)

const placeId = computed(() => {
  const raw = route.params.placeId
  return typeof raw === 'string' ? raw : ''
})

const locationModel = computed(() => {
  const p = place.value
  if (!p) {
    return {
      latitude: null,
      longitude: null,
      location_type: 'none',
      service_area_type: 'none',
      radius_meters: null,
      area_geojson: null,
    }
  }
  const loc = p.location && typeof p.location === 'object' ? p.location : {}
  return {
    latitude: loc.latitude ?? p.latitude ?? null,
    longitude: loc.longitude ?? p.longitude ?? null,
    location_type: p.location_type || 'none',
    service_area_type: p.service_area_type || 'none',
    radius_meters: p.radius_meters ?? null,
    area_geojson: p.area_geojson ?? null,
  }
})

const canManage = computed(() => Boolean(place.value?.viewer_place_role))

const memberBackId = computed(() => {
  const q = route.query.member
  return typeof q === 'string' && q.length > 0 ? q : ''
})

async function load() {
  const id = placeId.value
  if (!id) return
  loadError.value = ''
  loading.value = true
  place.value = null
  const { ok, status, data } = await fetchPlace(id)
  loading.value = false
  if (!ok) {
    loadError.value = placeApiErrorMessage(data, status, t('places.viewLoadError'))
    if (status === 403 || status === 404) {
      window.setTimeout(() => router.replace({ name: 'myPlaces' }), 1600)
    }
    return
  }
  const p = data?.place
  if (!p) {
    loadError.value = t('places.viewLoadError').replace('{status}', String(status))
    window.setTimeout(() => router.replace({ name: 'myPlaces' }), 1600)
    return
  }
  place.value = p
}

watch(placeId, () => {
  load()
})

function goBack() {
  const mid = memberBackId.value
  if (mid) {
    router.push({ name: 'memberProfile', params: { userSlug: mid } })
    return
  }
  router.push({ name: 'myPlaces' })
}

function goEdit() {
  const id = placeId.value
  if (!id) return
  router.push({ name: 'placeEdit', params: { placeId: id } })
}

load()
</script>

<template>
  <div class="place-view-page">
    <div class="place-view-page__toolbar">
      <button type="button" class="place-view-page__back" @click="goBack">
        {{ memberBackId ? t('places.viewBackToMember') : t('places.viewBackToPlaces') }}
      </button>
      <Button v-if="canManage" type="button" variant="primary" @click="goEdit">
        {{ t('places.viewManage') }}
      </Button>
    </div>

    <p v-if="loading" class="place-view-page__muted">{{ t('places.viewLoading') }}</p>
    <div v-else-if="place" class="place-view-page__content">
      <div class="place-view-page__hero">
        <img
          v-if="place.logo_url"
          :src="place.logo_url"
          alt=""
          class="place-view-page__logo"
          loading="lazy"
        />
        <div>
          <h1 class="place-view-page__title">{{ place.name }}</h1>
          <p v-if="place.description" class="place-view-page__desc">{{ place.description }}</p>
          <ul
            v-if="Array.isArray(place.tags) && place.tags.length"
            class="place-view-page__tags"
            aria-label="Tags"
          >
            <li v-for="(tag, i) in place.tags" :key="i" class="place-view-page__tag">{{ tag }}</li>
          </ul>
        </div>
      </div>

      <Card class="place-view-page__card">
        <h2 class="place-view-page__cardTitle">{{ t('places.viewLocationSection') }}</h2>
        <PlaceLocationPicker :model-value="locationModel" read-only />
      </Card>

      <Card class="place-view-page__card">
        <h2 class="place-view-page__cardTitle">{{ t('places.viewScheduleSection') }}</h2>
        <p class="place-view-page__scheduleIntro">{{ t('places.viewScheduleIntro') }}</p>
        <PlaceServiceScheduleDisplay :schedule="place.service_schedule" />
      </Card>

      <Card class="place-view-page__card">
        <h2 class="place-view-page__cardTitle">{{ t('places.viewOffersSection') }}</h2>
        <PlaceOffersPublicList :offers="place.offers || []" />
      </Card>

      <Card class="place-view-page__card">
        <h2 class="place-view-page__cardTitle">{{ t('places.viewRequirementsSection') }}</h2>
        <PlaceRequirementsPublicList
          :place-id="place.id"
          :can-manage-place="canManage"
          :requirements="place.requirements || []"
          @updated="load"
        />
      </Card>
    </div>
    <div v-else class="place-view-page__errorWrap">
      <p class="place-view-page__error" role="alert">{{ loadError }}</p>
      <Button variant="secondary" @click="router.push({ name: 'myPlaces' })">
        {{ t('places.viewBackToPlaces') }}
      </Button>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.place-view-page {
  max-width: 52rem;
  margin: 0 auto;
  padding: 1rem 1rem 2rem;
}

.place-view-page__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 1rem;
}

.place-view-page__back {
  cursor: pointer;
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
  font: inherit;
}

.place-view-page__muted {
  margin: 0;
  color: var(--text-muted, #64748b);
}

.place-view-page__hero {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.25rem;
  align-items: flex-start;
}

.place-view-page__logo {
  width: 5rem;
  height: 5rem;
  object-fit: cover;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
}

.place-view-page__title {
  margin: 0 0 0.35rem;
  font-size: 1.5rem;
}

.place-view-page__desc {
  margin: 0 0 0.5rem;
  white-space: pre-wrap;
}

.place-view-page__tags {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.place-view-page__tag {
  font-size: 0.8rem;
  padding: 0.15rem 0.45rem;
  border-radius: 0.35rem;
  background: rgba(37, 99, 235, 0.1);
  color: #1d4ed8;
}

.place-view-page__card {
  margin-bottom: 1rem;
}

.place-view-page__cardTitle {
  margin: 0 0 0.75rem;
  font-size: 1.05rem;
  font-weight: 600;
}

.place-view-page__scheduleIntro {
  margin: 0 0 0.75rem;
  font-size: 0.9rem;
  color: var(--text-muted, #64748b);
}

.place-view-page__error {
  color: #b91c1c;
  margin: 0 0 1rem;
}

.place-view-page__errorWrap {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.75rem;
}
</style>
