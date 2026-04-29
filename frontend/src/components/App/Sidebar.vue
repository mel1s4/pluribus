<script setup>
import { computed } from 'vue'
import Icon from '../../atoms/Icon.vue'
import FavoritesList from './FavoritesList.vue'
import { useCommunity } from '../../composables/useCommunity'
import { SIDEBAR_LINK_DEFS, isSidebarLinkDefAccessible } from '../../navigation/sidebarLinks'
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

const links = computed(() =>
  SIDEBAR_LINK_DEFS.filter((item) => isSidebarLinkDefAccessible(item)).map((item) => ({
    key: item.key,
    to: item.to,
    label: t(item.labelKey),
    icon: item.icon,
    capability: item.capability,
  })),
)

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

    <FavoritesList class="app-sidebar__favorites" @navigate="maybeCloseMobile" />

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

.app-sidebar__favorites {
  flex-shrink: 0;
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
