<script setup>
import { computed, onMounted } from 'vue'
import Icon from '../../atoms/Icon.vue'
import { useFavorites } from '../../composables/useFavorites'
import { useChatUnread } from '../../composables/useChatUnread.js'
import { t } from '../../i18n/i18n'

defineProps({
  placement: {
    type: String,
    default: 'bottom',
    validator: (v) => ['bottom'].includes(v),
  },
})

const { quickNavFavoriteItems } = useFavorites()
const { totalUnread, initializeChatUnread } = useChatUnread()

const defaultItems = computed(() => [
  { to: '/chats', icon: 'comments', label: t('quickNav.chats') },
  { to: '/my-places', icon: 'store', label: t('nav.myPlaces') },
  { to: '/map', icon: 'map-location-dot', label: t('quickNav.map') },
  { to: '/notifications', icon: 'bell', label: t('quickNav.notifications') },
  { to: '/profile', icon: 'user', label: t('quickNav.profile') },
])

const items = computed(() => {
  const fav = quickNavFavoriteItems.value
  if (fav.length > 0) {
    return fav.map((item) => ({
      to: item.to,
      icon: item.icon,
      label: item.label,
      unread: item.to === '/chats' || item.to === '/notifications' ? totalUnread.value : 0,
    }))
  }
  return defaultItems.value.map((item) => ({
    ...item,
    unread: item.to === '/chats' || item.to === '/notifications' ? totalUnread.value : 0,
  }))
})

onMounted(() => {
  void initializeChatUnread()
})
</script>

<template>
  <nav v-if="placement === 'bottom'" class="quick-nav" aria-label="Quick navigation">
    <RouterLink
      v-for="item in items"
      :key="item.to + item.label"
      :to="item.to"
      class="quick-nav__link"
      active-class="is-active"
      :aria-label="item.label"
      :title="item.label"
    >
      <Icon class="quick-nav__icon" :name="item.icon" aria-hidden="true" />
      <span v-if="item.unread > 0" class="quick-nav__badge">
        {{ item.unread > 99 ? '99+' : item.unread }}
      </span>
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
  position: relative;
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

.quick-nav__badge {
  position: absolute;
  top: 0.25rem;
  right: 0.45rem;
  min-width: 1rem;
  height: 1rem;
  border-radius: 999px;
  padding: 0 0.2rem;
  background: #ef4444;
  color: #fff;
  font-size: 0.62rem;
  font-weight: 700;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.quick-nav__icon {
  font-size: 1.2rem;
}
</style>
