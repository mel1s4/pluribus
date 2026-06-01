<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '../../atoms/Icon.vue'
import { useActiveCommunity } from '../../composables/useActiveCommunity'
import { communityHostSlug, isCommunityHostSite } from '../../composables/useCommunityHost'
import { sessionStatus, sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'

const emit = defineEmits(['navigate'])

const router = useRouter()
const { activeCommunitySlug } = useActiveCommunity()

const memberships = computed(() => {
  const list = sessionUser.value?.communities
  return Array.isArray(list) ? list.filter((row) => row && row.slug) : []
})

const visible = computed(
  () =>
    !isCommunityHostSite.value
    && sessionStatus.value === 'authenticated'
    && memberships.value.length > 0,
)

const selectedSlug = computed(() => {
  const fromRoute = activeCommunitySlug.value
  if (typeof fromRoute === 'string' && fromRoute.trim() !== '') {
    return fromRoute.trim()
  }
  if (isCommunityHostSite.value && communityHostSlug.value) {
    return communityHostSlug.value.trim()
  }
  const activeId = Number(sessionUser.value?.active_community_id || 0)
  if (activeId > 0) {
    const match = memberships.value.find((row) => Number(row.id) === activeId)
    if (match?.slug) return String(match.slug).trim()
  }
  const first = memberships.value[0]
  return first?.slug ? String(first.slug).trim() : ''
})

function onChange(event) {
  const slug = String(event.target?.value || '').trim()
  if (!slug || slug === selectedSlug.value) return
  router.push({
    name: 'dashboardScoped',
    params: { communitySlug: slug },
  })
  emit('navigate')
}
</script>

<template>
  <label v-if="visible" class="sidebar-community-switcher">
    <span class="sidebar-community-switcher__label">{{ t('nav.communitySwitcher') }}</span>
    <span class="sidebar-community-switcher__control">
      <Icon class="sidebar-community-switcher__icon" name="people-roof" aria-hidden="true" />
      <select
        class="sidebar-community-switcher__select"
        :value="selectedSlug"
        :aria-label="t('nav.communitySwitcher')"
        @change="onChange"
      >
        <option
          v-for="row in memberships"
          :key="row.id"
          :value="String(row.slug).trim()"
        >
          {{ row.name }}
        </option>
      </select>
      <Icon class="sidebar-community-switcher__chev" name="chevron-down" aria-hidden="true" />
    </span>
  </label>
</template>

<style scoped lang="scss">
.sidebar-community-switcher {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  margin-top: 0.55rem;
  min-width: 0;
}

.sidebar-community-switcher__label {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--muted, #6b7280);
}

.sidebar-community-switcher__control {
  position: relative;
  display: flex;
  align-items: center;
  min-width: 0;
}

.sidebar-community-switcher__icon {
  position: absolute;
  left: 0.55rem;
  font-size: 0.9rem;
  opacity: 0.7;
  pointer-events: none;
}

.sidebar-community-switcher__chev {
  position: absolute;
  right: 0.55rem;
  font-size: 0.75rem;
  opacity: 0.55;
  pointer-events: none;
}

.sidebar-community-switcher__select {
  width: 100%;
  min-width: 0;
  appearance: none;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
  background: var(--bg);
  color: inherit;
  font: inherit;
  font-size: 0.88rem;
  font-weight: 600;
  padding: 0.45rem 1.75rem 0.45rem 2rem;
  cursor: pointer;
}

.sidebar-community-switcher__select:hover {
  background: color-mix(in srgb, var(--border) 35%, var(--bg));
}

.sidebar-community-switcher__select:focus-visible {
  outline: 2px solid color-mix(in srgb, #2563eb 55%, transparent);
  outline-offset: 1px;
}
</style>
