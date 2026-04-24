<script setup>
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Icon from '../../atoms/Icon.vue'
import { useAppShell } from '../../composables/useAppShell'
import { t } from '../../i18n/i18n'

const route = useRoute()
const { sidebarOpen, toggleSidebar, headerActions } = useAppShell()

const searchQuery = ref('')
const sidebarControlsId = 'app-sidebar'

const titleText = computed(() => {
  const key = route.meta?.headerTitleKey
  if (typeof key !== 'string' || !key.length) return ''
  return t(key)
})

const quickItems = computed(() => [
  { to: '/chats', icon: 'comments', label: t('quickNav.chats') },
  { to: '/map', icon: 'map-location-dot', label: t('quickNav.map') },
  { to: '/notifications', icon: 'bell', label: t('quickNav.notifications') },
  { to: '/profile', icon: 'user', label: t('quickNav.profile') },
])
</script>

<template>
  <header class="app-header">
    <!-- Mobile: menu + title + route-driven actions -->
    <div class="app-header__mobile">
      <button
        type="button"
        class="app-header__iconBtn"
        :aria-expanded="sidebarOpen"
        :aria-controls="sidebarControlsId"
        :aria-label="t('nav.openNavigation')"
        @click="toggleSidebar"
      >
        <Icon class="app-header__iconGlyph" name="bars" aria-hidden="true" />
      </button>

      <h1 v-if="titleText" class="app-header__title">{{ titleText }}</h1>
      <div v-else class="app-header__titleSpacer" />

      <div class="app-header__actions">
        <Button
          v-for="action in headerActions"
          :key="action.id"
          type="button"
          :variant="action.variant ?? 'secondary'"
          size="sm"
          @click="action.onClick"
        >
          {{ action.label }}
        </Button>
      </div>
    </div>

    <!-- Desktop: nav + search | quick icon links -->
    <div class="app-header__desktop">
      <div class="app-header__left">
        <button
          type="button"
          class="app-header__iconBtn"
          :aria-expanded="sidebarOpen"
          :aria-controls="sidebarControlsId"
          :aria-label="t('nav.openNavigation')"
          @click="toggleSidebar"
        >
          <Icon class="app-header__iconGlyph" name="bars" aria-hidden="true" />
        </button>

        <div class="app-header__search">
          <Icon
            class="app-header__searchIcon"
            name="magnifying-glass"
            aria-hidden="true"
          />
          <input
            v-model="searchQuery"
            class="app-header__searchInput"
            type="search"
            name="q"
            :placeholder="t('header.searchPlaceholder')"
            :aria-label="t('header.searchPlaceholder')"
            autocomplete="off"
          />
        </div>
      </div>

      <nav class="app-header__quick" aria-label="Quick actions">
        <RouterLink
          v-for="item in quickItems"
          :key="item.to"
          :to="item.to"
          class="app-header__quickLink"
          active-class="is-active"
          :title="item.label"
          :aria-label="item.label"
        >
          <Icon class="app-header__iconGlyph" :name="item.icon" aria-hidden="true" />
        </RouterLink>
      </nav>
    </div>
  </header>
</template>

<style lang="scss" scoped>
.app-header {
  border-bottom: 1px solid var(--border);
  background: var(--bg);
}

.app-header__mobile {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 57px;
  padding: 0 0.75rem;
  box-sizing: border-box;

  @media (min-width: 1024px) {
    display: none;
  }
}

.app-header__desktop {
  display: none;

  @media (min-width: 1024px) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    min-height: 57px;
    padding: 0 1rem;
    box-sizing: border-box;
  }
}

.app-header__left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex: 1;
  min-width: 0;
}

.app-header__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.2;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.app-header__titleSpacer {
  flex: 1;
}

.app-header__actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.app-header__iconBtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  background: var(--btn-bg);
  color: var(--btn-text);
  cursor: pointer;
  flex-shrink: 0;

  &:hover {
    background: var(--btn-bg-hover);
  }
}

.app-header__iconGlyph {
  font-size: 1.1rem;
}

.app-header__search {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
  max-width: 28rem;
  min-width: 0;
  padding: 0 0.65rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--bg);
}

.app-header__searchIcon {
  color: var(--text);
  opacity: 0.55;
  font-size: 0.95rem;
  flex-shrink: 0;
}

.app-header__searchInput {
  flex: 1;
  min-width: 0;
  border: none;
  padding: 0.5rem 0.25rem 0.5rem 0;
  background: transparent;

  &:focus {
    border-color: transparent;
    outline: none;
  }
}

.app-header__quick {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  flex-shrink: 0;
}

.app-header__quickLink {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.5rem;
  color: var(--text);
  text-decoration: none;
  opacity: 0.75;
  transition: opacity 140ms ease, background-color 140ms ease;

  &:hover {
    opacity: 1;
    background: var(--btn-bg);
  }

  &.is-active {
    opacity: 1;
    color: var(--link);
    background: rgba(37, 99, 235, 0.1);

    html[data-theme='dark'] & {
      background: rgba(96, 165, 250, 0.12);
    }
  }
}
</style>
