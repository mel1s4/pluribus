<script setup>
import { computed } from 'vue'
import { t } from '../../i18n/i18n'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
})

const places = computed(() => (
  Array.isArray(props.items)
    ? props.items.slice(0, 5)
    : []
))

function placeName(place) {
  const name = String(place?.name || '').trim()
  return name || t('myPlaces.title')
}

function formatPlaceTimestamp(place) {
  const source = place?.updated_at || place?.created_at || null
  if (!source) return ''
  const parsed = new Date(source)
  if (Number.isNaN(parsed.getTime())) return ''
  return parsed.toLocaleDateString()
}
</script>

<template>
  <article class="dashboard-widget">
    <h2 class="dashboard-widget__title">{{ t('dashboard.myPlaces.title') }}</h2>
    <p class="dashboard-widget__body">{{ t('dashboard.myPlaces.body') }}</p>

    <ul v-if="places.length" class="dashboard-widget__list">
      <li v-for="place in places" :key="place.id">
        <RouterLink :to="`/my-places/${place.id}`" class="dashboard-widget__itemLink">
          <span class="dashboard-widget__itemTitle">{{ placeName(place) }}</span>
          <span v-if="formatPlaceTimestamp(place)" class="dashboard-widget__itemMeta">
            {{ t('dashboard.myPlaces.updatedLabel').replace('{date}', formatPlaceTimestamp(place)) }}
          </span>
        </RouterLink>
      </li>
    </ul>
    <p v-else class="dashboard-widget__empty">{{ t('dashboard.myPlaces.empty') }}</p>
  </article>
</template>

<style scoped lang="scss">
.dashboard-widget {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--bg);
  padding: 1rem;
}

.dashboard-widget__title {
  margin: 0;
  font-size: 1rem;
}

.dashboard-widget__body {
  margin: 0;
  font-size: 0.92rem;
}

.dashboard-widget__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.dashboard-widget__itemLink {
  display: flex;
  flex-direction: column;
  gap: 0.12rem;
  text-decoration: none;
  color: inherit;
}

.dashboard-widget__itemTitle {
  font-size: 0.92rem;
  font-weight: 600;
}

.dashboard-widget__itemMeta,
.dashboard-widget__empty {
  margin: 0;
  font-size: 0.82rem;
  opacity: 0.82;
}
</style>
