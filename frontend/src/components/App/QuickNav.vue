<script setup>
import { computed } from 'vue'
import Icon from '../../atoms/Icon.vue'
import { t } from '../../i18n/i18n'

defineProps({
  placement: {
    type: String,
    default: 'bottom',
    validator: (v) => ['bottom'].includes(v),
  },
})

const items = computed(() => [
  { to: '/chats', icon: 'comments', label: t('quickNav.chats') },
  { to: '/my-places', icon: 'store', label: t('nav.myPlaces') },
  { to: '/map', icon: 'map-location-dot', label: t('quickNav.map') },
  { to: '/notifications', icon: 'bell', label: t('quickNav.notifications') },
  { to: '/profile', icon: 'user', label: t('quickNav.profile') },
])
</script>

<template>
  <nav v-if="placement === 'bottom'" class="quick-nav" aria-label="Quick navigation">
    <RouterLink
      v-for="item in items"
      :key="item.to"
      :to="item.to"
      class="quick-nav__link"
      active-class="is-active"
      :aria-label="item.label"
      :title="item.label"
    >
      <Icon class="quick-nav__icon" :name="item.icon" aria-hidden="true" />
    </RouterLink>
  </nav>
</template>

<style lang="scss" scoped>
.quick-nav {
  display: flex;
  align-items: center;
  justify-content: space-around;
  gap: 0.25rem;
  min-height: 3.25rem;
  padding: 0 0.5rem env(safe-area-inset-bottom, 0);
  border-top: 1px solid var(--border);
  background: var(--bg);
}

.quick-nav__link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  max-width: 4rem;
  height: 2.75rem;
  border-radius: 0.5rem;
  color: var(--text);
  text-decoration: none;
  opacity: 0.7;

  &:hover {
    opacity: 1;
    background: var(--btn-bg);
  }

  &.is-active {
    opacity: 1;
    color: var(--link);
  }
}

.quick-nav__icon {
  font-size: 1.2rem;
}
</style>
