<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'
import { fetchMapDiscovery } from '../../services/contentApi'
import { useCommunityPlacesMap } from '../../composables/useCommunityPlacesMap.js'

const mapContainer = ref(null)
const mapEntities = ref([])
const selectedEntityId = ref('')
const loading = ref(false)
const error = ref('')
const filterEntity = ref('both')
const filterPostType = ref('')
const tagInput = ref('')

const selectedEntity = computed(() => {
  const id = String(selectedEntityId.value || '')
  if (!id) return null
  return mapEntities.value.find((p) => String(p.id) === id) || null
})

const mapApi = useCommunityPlacesMap(mapContainer, {
  getPlaces: () => mapEntities.value,
  getSelectedPlaceId: () => selectedEntityId.value,
  getSelectedPlace: () => selectedEntity.value,
  onSelectPlace: (id) => {
    selectedEntityId.value = String(id)
  },
})

async function loadPlaces() {
  loading.value = true
  error.value = ''
  const tags = tagInput.value
    .split(',')
    .map((v) => v.trim())
    .filter(Boolean)
  const { ok, status, data } = await fetchMapDiscovery({
    entity: filterEntity.value,
    post_type: filterPostType.value || undefined,
    tags,
  })
  loading.value = false
  if (!ok) {
    error.value = t('map.loadError').replace('{status}', String(status || 500))
    mapEntities.value = []
    return
  }
  const places = Array.isArray(data?.places) ? data.places : []
  const posts = Array.isArray(data?.posts?.data) ? data.posts.data : (Array.isArray(data?.posts) ? data.posts : [])
  mapEntities.value = [
    ...places.map((place) => ({
      ...place,
      id: `place-${place.id}`,
      entity_kind: 'place',
      display_title: place.name,
      service_area_type: place.service_area_type,
      radius_meters: place.radius_meters,
      area_geojson: place.area_geojson,
    })),
    ...posts.map((post) => ({
      ...post,
      id: `post-${post.id}`,
      entity_kind: 'post',
      display_title: post.title,
      service_area_type: post.influence_area_type,
      radius_meters: post.influence_radius_meters,
      area_geojson: post.influence_area_geojson,
    })),
  ]
}

function closeSidebar() {
  selectedEntityId.value = ''
}

onMounted(async () => {
  mapApi.initMap()
  await loadPlaces()
})

watch(selectedEntityId, async () => {
  await nextTick()
  mapApi.invalidateSize()
})

onBeforeUnmount(() => {
  mapApi.destroyMap()
})
</script>

<template>
  <section class="page page--map">
    <div class="map-view__head">
      <Title tag="h1">{{ t('map.title') }}</Title>
      <p class="page__muted">{{ t('map.discoveryIntro') }}</p>
      <div class="map-view__filters">
        <select v-model="filterEntity" @change="loadPlaces">
          <option value="both">{{ t('map.filterBoth') }}</option>
          <option value="places">{{ t('map.filterPlaces') }}</option>
          <option value="posts">{{ t('map.filterPosts') }}</option>
        </select>
        <select v-model="filterPostType" @change="loadPlaces">
          <option value="">{{ t('map.filterAnyPostType') }}</option>
          <option value="task">{{ t('map.postTypeTask') }}</option>
          <option value="event">{{ t('map.postTypeEvent') }}</option>
          <option value="announcement">{{ t('map.postTypeAnnouncement') }}</option>
          <option value="info">{{ t('map.postTypeInfo') }}</option>
        </select>
        <input
          v-model="tagInput"
          :placeholder="t('map.filterTagsPlaceholder')"
          @keyup.enter="loadPlaces"
        />
        <button class="btn btn--secondary btn--sm" @click="loadPlaces">{{ t('map.applyFilters') }}</button>
      </div>
      <p v-if="loading" class="page__muted">{{ t('myPlaces.loading') }}</p>
      <p v-if="error" class="map-view__error">{{ error }}</p>
    </div>
    <div class="map-view__layout">
      <div ref="mapContainer" class="map-view__map" />
      <aside class="map-view__details">
        <button v-if="selectedEntity" class="btn btn--ghost btn--sm map-view__close" @click="closeSidebar">×</button>
        <template v-if="selectedEntity">
          <h3>{{ selectedEntity.display_title }}</h3>
          <p class="page__muted">{{ selectedEntity.entity_kind === 'post' ? t('map.kindPost') : t('map.kindPlace') }}</p>
          <p v-if="selectedEntity.description">{{ selectedEntity.description }}</p>
          <p v-if="selectedEntity.entity_kind === 'post' && selectedEntity.influence_area_type && selectedEntity.influence_area_type !== 'none'" class="page__muted">
            {{ t('map.influenceVisible') }}
          </p>
        </template>
        <p v-else class="page__muted">{{ t('map.selectPlaceHint') }}</p>
      </aside>
    </div>
  </section>
</template>

<style scoped lang="scss">
.page--map {
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-height: 0;
  height: 100%;
}

.page__muted {
  margin: 0;
  opacity: 0.8;
}

.map-view__head {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.map-view__layout {
  display: flex;
  border: 1px solid var(--border);
  border-radius: 8px;
  overflow: hidden;
  min-height: 65vh;
}

.map-view__filters {
  display: grid;
  grid-template-columns: 170px 170px 1fr auto;
  gap: 0.5rem;
}

.map-view__filters select,
.map-view__filters input {
  padding: 0.45rem 0.55rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
}

.map-view__map {
  flex: 1 1 auto;
  min-height: 65vh;
}

.map-view__details {
  width: 320px;
  border-left: 1px solid var(--border);
  padding: 0.8rem;
  background: var(--bg);
}

.map-view__close {
  float: right;
}

.map-view__error {
  margin: 0;
  color: var(--danger, #b00020);
}

@media (max-width: 960px) {
  .map-view__layout {
    flex-direction: column;
    min-height: 70vh;
  }

  .map-view__filters {
    grid-template-columns: 1fr;
  }

  .map-view__map {
    min-height: 50vh;
  }

  .map-view__details {
    width: auto;
    border-left: none;
    border-top: 1px solid var(--border);
  }
}
</style>