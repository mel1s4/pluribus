<script setup>
import { computed, ref, watch } from 'vue'
import Card from '../../atoms/Card.vue'
import PlaceOffersPublicList from '../../molecules/PlaceOffersPublicList.vue'
import PlaceRequirementsPublicList from '../../molecules/PlaceRequirementsPublicList.vue'
import PlaceServiceScheduleDisplay from '../../molecules/PlaceServiceScheduleDisplay.vue'
import { t } from '../../i18n/i18n'

const props = defineProps({
  place: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['close'])
const activeTab = ref('overview')
const tabs = ['overview', 'offers', 'requirements']

const hasPlace = computed(() => Boolean(props.place))

watch(
  () => props.place?.id,
  () => {
    activeTab.value = 'overview'
  },
)
</script>

<template>
  <aside class="place-map-sidebar" :aria-hidden="!hasPlace">
    <div v-if="!hasPlace" class="place-map-sidebar__empty">
      <p class="place-map-sidebar__hint">{{ t('map.selectPlaceHint') }}</p>
    </div>
    <div v-else class="place-map-sidebar__content">
      <div class="place-map-sidebar__header">
        <h2 class="place-map-sidebar__title">{{ place.name }}</h2>
        <button type="button" class="place-map-sidebar__close" @click="emit('close')">
          {{ t('myPlaces.cancel') }}
        </button>
      </div>
      <p v-if="place.description" class="place-map-sidebar__description">{{ place.description }}</p>

      <div class="place-map-sidebar__tabs" role="tablist" aria-label="Place details tabs">
        <button
          v-for="tab in tabs"
          :key="tab"
          type="button"
          role="tab"
          class="place-map-sidebar__tab"
          :class="{ 'place-map-sidebar__tab--active': activeTab === tab }"
          :aria-selected="activeTab === tab"
          @click="activeTab = tab"
        >
          {{
            tab === 'overview'
              ? t('places.viewOverviewSection')
              : tab === 'offers'
                ? t('places.viewOffersSection')
                : t('places.viewRequirementsSection')
          }}
        </button>
      </div>

      <div v-if="activeTab === 'overview'" class="place-map-sidebar__panel">
        <Card class="place-map-sidebar__card">
          <h3 class="place-map-sidebar__card-title">{{ t('places.viewLocationSection') }}</h3>
          <p class="place-map-sidebar__meta">
            {{ place.location_type }} / {{ place.service_area_type || 'none' }}
          </p>
          <p v-if="Array.isArray(place.tags) && place.tags.length" class="place-map-sidebar__meta">
            {{ place.tags.join(', ') }}
          </p>
        </Card>
        <Card class="place-map-sidebar__card">
          <h3 class="place-map-sidebar__card-title">{{ t('places.viewScheduleSection') }}</h3>
          <PlaceServiceScheduleDisplay :schedule="place.service_schedule" />
        </Card>
      </div>

      <div v-if="activeTab === 'offers'" class="place-map-sidebar__panel">
        <PlaceOffersPublicList :offers="place.offers || []" />
      </div>

      <div v-if="activeTab === 'requirements'" class="place-map-sidebar__panel">
        <PlaceRequirementsPublicList
          :place-id="place.id"
          :can-manage-place="false"
          :requirements="place.requirements || []"
        />
      </div>
    </div>
  </aside>
</template>

<style scoped lang="scss">
.place-map-sidebar {
  display: flex;
  flex-direction: column;
  border-left: 1px solid var(--border);
  background: var(--bg);
  min-width: 22rem;
  max-width: 30rem;
  width: min(36vw, 30rem);
}

.place-map-sidebar__empty {
  padding: 1rem;
}

.place-map-sidebar__hint {
  margin: 0;
  opacity: 0.8;
}

.place-map-sidebar__content {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem;
  min-height: 0;
}

.place-map-sidebar__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
}

.place-map-sidebar__title {
  margin: 0;
  font-size: 1.1rem;
}

.place-map-sidebar__close {
  border: 1px solid var(--border);
  background: var(--bg);
  border-radius: 6px;
  padding: 0.25rem 0.5rem;
  cursor: pointer;
  font: inherit;
}

.place-map-sidebar__description {
  margin: 0;
  white-space: pre-wrap;
}

.place-map-sidebar__tabs {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.place-map-sidebar__tab {
  border: 1px solid var(--border);
  background: var(--bg);
  border-radius: 999px;
  padding: 0.3rem 0.65rem;
  cursor: pointer;
  font: inherit;
  font-size: 0.85rem;
}

.place-map-sidebar__tab--active {
  border-color: var(--accent, #3b5bdb);
  color: var(--accent, #3b5bdb);
}

.place-map-sidebar__panel {
  overflow: auto;
  min-height: 0;
}

.place-map-sidebar__card {
  margin-bottom: 0.75rem;
}

.place-map-sidebar__card-title {
  margin: 0 0 0.5rem;
  font-size: 0.95rem;
}

.place-map-sidebar__meta {
  margin: 0;
  font-size: 0.85rem;
  opacity: 0.85;
}

@media (max-width: 960px) {
  .place-map-sidebar {
    max-width: none;
    width: 100%;
    min-width: 0;
    border-left: none;
    border-top: 1px solid var(--border);
  }
}
</style>
