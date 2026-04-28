<script setup>
import { computed } from 'vue'
import Icon from '../../atoms/Icon.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { useCommunity } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'

const { displayName } = useCommunity()

defineProps({
  open: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close'])

const sidebarId = 'app-sidebar'

const links = computed(() => {
  const all = [
    { key: 'dashboard', to: '/dashboard', label: t('nav.dashboard'), icon: 'gauge-high', capability: null },
    {
      key: 'users',
      to: '/users',
      label: t('nav.users'),
      icon: 'users',
      capability: null,
    },
    {
      key: 'community-settings',
      to: '/community',
      label: t('nav.community'),
      icon: 'people-roof',
      capability: null,
    },
    { key: 'api-test', to: '/api-test', label: t('nav.apiTest'), icon: 'plug', capability: null },
    { key: 'chats', to: '/chats', label: t('quickNav.chats'), icon: 'comments', capability: null },
    { key: 'tasks', to: '/tasks', label: t('tasks.title'), icon: 'list-check', capability: null },
    { key: 'calendar', to: '/calendar', label: t('calendar.title'), icon: 'calendar-days', capability: null },
    { key: 'posts', to: '/posts', label: t('posts.title'), icon: 'newspaper', capability: null },
    { key: 'my-groups', to: '/my-groups', label: t('groups.title'), icon: 'people-group', capability: null },
    { key: 'my-places', to: '/my-places', label: t('nav.myPlaces'), icon: 'store', capability: null },
    { key: 'map', to: '/map', label: t('quickNav.map'), icon: 'map-location-dot', capability: null },
    {
      key: 'notifications',
      to: '/notifications',
      label: t('quickNav.notifications'),
      icon: 'bell',
      capability: null,
    },
    { key: 'profile', to: '/profile', label: t('quickNav.profile'), icon: 'user', capability: null },
    { key: 'settings', to: '/settings', label: t('nav.settings'), icon: 'gear', capability: null },
  ]
  return all.filter((item) => !item.capability || hasCapability(item.capability))
})

function maybeCloseMobile() {
  if (typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches) {
    emit('close')
  }
}
</script>

<template>
  <div :id="sidebarId" class="app-sidebar" role="navigation" :aria-hidden="!open">
    <div class="app-sidebar__header">
      <span class="app-sidebar__brand">{{ displayName }}</span>
      <button
        type="button"
        class="app-sidebar__close app-sidebar__close--mobile"
        :aria-label="t('nav.closeNavigation')"
        @click="emit('close')"
      >
        <Icon class="app-sidebar__icon" name="xmark" aria-hidden="true" />
      </button>
    </div>

    <nav class="app-sidebar__nav" aria-label="Main">
      <RouterLink
        v-for="link in links"
        :key="link.key"
        :to="link.to"
        class="app-sidebar__link"
        active-class="is-active"
        @click="maybeCloseMobile"
      >
        <Icon class="app-sidebar__icon" :name="link.icon" aria-hidden="true" />
        <span>{{ link.label }}</span>
      </RouterLink>
    </nav>
  </div>
</template>

<style lang="scss" scoped>
.app-sidebar {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  background: var(--bg);
}

.app-sidebar__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}

.app-sidebar__brand {
  font-weight: 700;
  letter-spacing: 0.02em;
}

.app-sidebar__close {
  display: none;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border: none;
  border-radius: 0.5rem;
  background: transparent;
  cursor: pointer;
  color: inherit;
}

.app-sidebar__close--mobile {
  @media (max-width: 1023px) {
    display: inline-flex;
  }
}

.app-sidebar__nav {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0.65rem 0.5rem 1rem;
  overflow: auto;
  min-height: 0;
}

.app-sidebar__link {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.55rem 0.65rem;
  border-radius: 0.5rem;
  color: inherit;
  text-decoration: none;
  font-size: 0.92rem;
  border: 1px solid transparent;

  &:hover {
    background: color-mix(in srgb, var(--border) 45%, transparent);
  }

  &.is-active {
    border-color: var(--border);
    background: color-mix(in srgb, var(--border) 55%, transparent);
    font-weight: 600;
  }
}

.app-sidebar__icon {
  font-size: 1.05rem;
  flex-shrink: 0;
}
</style>
