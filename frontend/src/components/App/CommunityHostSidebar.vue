<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import Icon from '../../atoms/Icon.vue'
import { useCommunity } from '../../composables/useCommunity'
import { communityHostSlug } from '../../composables/useCommunityHost'
import { communityHostNavDefs } from '../../navigation/communityHostNav'
import { t } from '../../i18n/i18n'

defineProps({
  open: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close'])

const { displayName } = useCommunity()

const slug = computed(() => {
  const s = communityHostSlug.value
  return typeof s === 'string' ? s.trim() : ''
})

const links = computed(() => {
  const s = slug.value
  if (!s) return []
  return communityHostNavDefs(s).map((item) => ({
    key: item.key,
    to: item.to,
    icon: item.icon,
    label: t(item.labelKey),
  }))
})

function maybeCloseMobile() {
  if (typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches) {
    emit('close')
  }
}
</script>

<template>
  <div class="community-host-sidebar" role="navigation" :aria-hidden="!open">
    <div class="community-host-sidebar__header">
      <RouterLink
        class="community-host-sidebar__brand"
        :to="{ name: 'communityMicrosite', params: { slug } }"
        @click="maybeCloseMobile"
      >
        {{ displayName }}
      </RouterLink>
      <button
        type="button"
        class="community-host-sidebar__close"
        :aria-label="t('nav.closeNavigation')"
        @click="emit('close')"
      >
        <Icon name="xmark" aria-hidden="true" />
      </button>
    </div>

    <nav class="community-host-sidebar__nav" :aria-label="t('communityHostNav.aria')">
      <RouterLink
        v-for="link in links"
        :key="link.key"
        :to="link.to"
        class="community-host-sidebar__link"
        active-class="is-active"
        @click="maybeCloseMobile"
      >
        <Icon class="community-host-sidebar__icon" :name="link.icon" aria-hidden="true" />
        <span>{{ link.label }}</span>
      </RouterLink>
    </nav>
  </div>
</template>

<style lang="scss" scoped>
.community-host-sidebar {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  background: var(--bg);
}

.community-host-sidebar__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid var(--border);
}

.community-host-sidebar__brand {
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.25;
  color: inherit;
  text-decoration: none;
  min-width: 0;
}

.community-host-sidebar__brand:hover {
  text-decoration: underline;
}

.community-host-sidebar__close {
  display: inline-flex;
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

@media (min-width: 1024px) {
  .community-host-sidebar__close {
    display: none;
  }
}

.community-host-sidebar__nav {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0.65rem 0.5rem 1rem;
  overflow: auto;
  min-height: 0;
}

.community-host-sidebar__link {
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

.community-host-sidebar__icon {
  font-size: 1.05rem;
  flex-shrink: 0;
}
</style>
