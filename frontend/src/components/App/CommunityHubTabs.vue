<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { t } from '../../i18n/i18n'

const props = defineProps({
  slug: {
    type: String,
    required: true,
  },
  isMember: {
    type: Boolean,
    default: false,
  },
})

const route = useRoute()

const hubTo = computed(() => ({
  name: 'communityMicrosite',
  params: { slug: props.slug },
}))

const projectsTo = computed(() => ({
  name: 'communityProjects',
  params: { slug: props.slug },
}))

const hubActive = computed(() => route.name === 'communityMicrosite')
const projectsActive = computed(() => route.name === 'communityProjects' || route.name === 'communityProjectDetail')
</script>

<template>
  <div class="community-hub-tabs" role="tablist" :aria-label="t('communityProjects.hubTabsAria')">
    <RouterLink
      class="community-hub-tabs__tab"
      :class="{ 'community-hub-tabs__tab--active': hubActive }"
      role="tab"
      :aria-selected="hubActive"
      :to="hubTo"
    >
      {{ t('communityProjects.tabHub') }}
    </RouterLink>
    <RouterLink
      v-if="isMember"
      class="community-hub-tabs__tab"
      :class="{ 'community-hub-tabs__tab--active': projectsActive }"
      role="tab"
      :aria-selected="projectsActive"
      :to="projectsTo"
    >
      {{ t('communityProjects.tabProjects') }}
    </RouterLink>
  </div>
</template>

<style lang="scss" scoped>
.community-hub-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin: 0 0 1rem;
}

.community-hub-tabs__tab {
  cursor: pointer;
  padding: 0.45rem 0.85rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  background: var(--bg);
  font: inherit;
  font-size: 0.9rem;
  color: inherit;
  text-decoration: none;
}

.community-hub-tabs__tab--active {
  font-weight: 600;
  border-color: color-mix(in srgb, var(--border) 70%, #1d4ed8);
  background: color-mix(in srgb, #1d4ed8 8%, var(--bg));
}
</style>
