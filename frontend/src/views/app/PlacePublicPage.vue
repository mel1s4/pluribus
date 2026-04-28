<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import Card from '../../atoms/Card.vue'
import PlaceLocationPicker from '../../organisms/PlaceLocationPicker.vue'
import PlaceOffersPublicList from '../../molecules/PlaceOffersPublicList.vue'
import PlaceRequirementsPublicList from '../../molecules/PlaceRequirementsPublicList.vue'
import PlaceServiceScheduleDisplay from '../../molecules/PlaceServiceScheduleDisplay.vue'
import { t } from '../../i18n/i18n'
import { fetchPlaceBySlug } from '../../services/placesApi.js'
import { placeApiErrorMessage } from '../../utils/placeForm.js'

const route = useRoute()
const router = useRouter()

const place = ref(null)
const loadError = ref('')
const loading = ref(true)

const slug = computed(() => {
  const raw = route.params.slug
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

const heroStyle = computed(() => {
  const c = place.value?.logo_background_color
  if (typeof c === 'string' && /^#[0-9A-Fa-f]{6}$/.test(c.trim())) {
    return { background: c.trim() }
  }
  return { background: 'linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%)' }
})

async function load() {
  const s = slug.value
  if (!s) {
    return
  }
  loadError.value = ''
  loading.value = true
  place.value = null
  const { ok, status, data } = await fetchPlaceBySlug(s)
  loading.value = false
  if (!ok) {
    loadError.value = placeApiErrorMessage(data, status, t('places.viewLoadError'))
    if (status === 403 || status === 404) {
      window.setTimeout(() => router.replace({ name: 'map' }), 1600)
    }
    return
  }
  const p = data?.place
  if (!p) {
    loadError.value = t('places.viewLoadError').replace('{status}', String(status))
    window.setTimeout(() => router.replace({ name: 'map' }), 1600)
    return
  }
  place.value = p
}

watch(slug, () => {
  void load()
})

function goBack() {
  if (window.history.length > 1) {
    router.back()
    return
  }
  router.push({ name: 'map' })
}

function goEdit() {
  const p = place.value
  if (!p?.id) {
    return
  }
  router.push({ name: 'placeEdit', params: { placeId: String(p.id) } })
}

load()
</script>

<template>
  <div class="place-public-page">
    <p v-if="loading" class="place-public-page__muted">{{ t('places.viewLoading') }}</p>
    <div v-else-if="place" class="place-public-page__content">
      <div class="place-public-page__topbar">
        <button type="button" class="place-public-page__back" @click="goBack">
          {{ t('places.storefrontBack') }}
        </button>
        <button
          v-if="canManage"
          type="button"
          class="place-public-page__manage btn btn--primary btn--sm"
          @click="goEdit"
        >
          {{ t('places.viewManage') }}
        </button>
      </div>

      <div class="place-public-page__hero" :style="heroStyle">
        <div class="place-public-page__heroInner">
          <div
            class="place-public-page__logoBox"
            :class="{ 'place-public-page__logoBox--text': !place.logo_url }"
          >
            <img
              v-if="place.logo_url"
              :src="place.logo_url"
              alt=""
              class="place-public-page__logo"
              loading="lazy"
            />
            <span v-else class="place-public-page__logoInitial" aria-hidden="true">
              {{ (place.name || '?').trim().charAt(0).toUpperCase() }}
            </span>
          </div>
          <div class="place-public-page__heroText">
            <h1 class="place-public-page__title">{{ place.name }}</h1>
            <p v-if="place.description" class="place-public-page__desc">{{ place.description }}</p>
            <ul
              v-if="Array.isArray(place.tags) && place.tags.length"
              class="place-public-page__tags"
              :aria-label="t('myPlaces.fieldTags')"
            >
              <li v-for="(tag, i) in place.tags" :key="i" class="place-public-page__tag">{{ tag }}</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="place-public-page__keyInfo">
        <h2 class="place-public-page__sectionTitle">{{ t('places.storefrontKeyInfo') }}</h2>
        <div class="place-public-page__keyGrid">
          <Card class="place-public-page__keyCard">
            <h3 class="place-public-page__cardTitle">{{ t('places.viewLocationSection') }}</h3>
            <PlaceLocationPicker :model-value="locationModel" read-only />
          </Card>
          <Card class="place-public-page__keyCard">
            <h3 class="place-public-page__cardTitle">{{ t('places.viewScheduleSection') }}</h3>
            <PlaceServiceScheduleDisplay :schedule="place.service_schedule" />
          </Card>
        </div>
      </div>

      <section class="place-public-page__section place-public-page__offers">
        <h2 class="place-public-page__sectionTitle place-public-page__sectionTitle--em">
          {{ t('places.storefrontOffersHeading') }}
        </h2>
        <div class="place-public-page__offersGrid">
          <PlaceOffersPublicList :offers="place.offers || []" />
        </div>
      </section>

      <section class="place-public-page__section">
        <h2 class="place-public-page__sectionTitle place-public-page__sectionTitle--em">
          {{ t('places.storefrontRequirementsHeading') }}
        </h2>
        <Card class="place-public-page__reqCard">
          <PlaceRequirementsPublicList
            :place-id="place.id"
            :can-manage-place="canManage"
            :requirements="place.requirements || []"
            @updated="load"
          />
        </Card>
      </section>
    </div>
    <div v-else class="place-public-page__errorWrap">
      <p class="place-public-page__error" role="alert">{{ loadError }}</p>
      <RouterLink class="place-public-page__back" :to="{ name: 'map' }">{{ t('map.title') }}</RouterLink>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.place-public-page {
  max-width: 70rem;
  margin: 0 auto;
  padding: 0 0 2.5rem;
}

.place-public-page__topbar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
}

.place-public-page__back {
  cursor: pointer;
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
  font: inherit;
  text-decoration: none;
  color: inherit;
  display: inline-block;
}

.place-public-page__hero {
  color: #fff;
  padding: 2.5rem 1.25rem;
  border-radius: 0 0 1rem 1rem;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
}

.place-public-page__heroInner {
  max-width: 56rem;
  margin: 0 auto;
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
  align-items: center;
}

.place-public-page__logoBox {
  width: 7.5rem;
  height: 7.5rem;
  border-radius: 0.75rem;
  background: rgba(255, 255, 255, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
}

.place-public-page__logoBox--text {
  background: rgba(255, 255, 255, 0.2);
  border: 2px solid rgba(255, 255, 255, 0.5);
}

.place-public-page__logo {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.place-public-page__logoInitial {
  font-size: 2.5rem;
  font-weight: 700;
  color: #fff;
}

.place-public-page__heroText {
  flex: 1;
  min-width: min(100%, 16rem);
}

.place-public-page__title {
  margin: 0 0 0.5rem;
  font-size: clamp(1.5rem, 3vw, 2.25rem);
  line-height: 1.15;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.place-public-page__desc {
  margin: 0 0 0.75rem;
  white-space: pre-wrap;
  opacity: 0.95;
  line-height: 1.45;
  font-size: 1.02rem;
}

.place-public-page__tags {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.place-public-page__tag {
  font-size: 0.78rem;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.35);
}

.place-public-page__keyInfo {
  padding: 1.5rem 1rem 0;
}

.place-public-page__keyGrid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

@media (min-width: 768px) {
  .place-public-page__keyGrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.place-public-page__keyCard {
  margin: 0;
}

.place-public-page__section {
  padding: 1.5rem 1rem 0;
}

.place-public-page__sectionTitle {
  margin: 0 0 0.75rem;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text, inherit);
}

.place-public-page__sectionTitle--em {
  font-size: 1.25rem;
  letter-spacing: -0.01em;
}

.place-public-page__offers :deep(.place-offers-public__list) {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

@media (min-width: 640px) {
  .place-public-page__offers :deep(.place-offers-public__list) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1024px) {
  .place-public-page__offers :deep(.place-offers-public__list) {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.place-public-page__offers :deep(.place-offers-public__card) {
  flex-direction: column;
  height: 100%;
}

.place-public-page__cardTitle {
  margin: 0 0 0.5rem;
  font-size: 0.95rem;
  font-weight: 600;
}

.place-public-page__reqCard {
  margin: 0;
}

.place-public-page__muted {
  margin: 1rem;
  color: var(--text-muted, #64748b);
}

.place-public-page__error {
  color: #b91c1c;
  margin: 0 0 1rem;
}

.place-public-page__errorWrap {
  padding: 2rem 1rem;
}
</style>
