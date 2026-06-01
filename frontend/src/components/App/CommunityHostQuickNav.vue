<script setup>
import { computed } from 'vue'
import Icon from '../../atoms/Icon.vue'
import { communityHostSlug } from '../../composables/useCommunityHost'
import { communityHostNavDefs } from '../../navigation/communityHostNav'
import { t } from '../../i18n/i18n'

const slug = computed(() => {
  const s = communityHostSlug.value
  return typeof s === 'string' ? s.trim() : ''
})

const items = computed(() => {
  const s = slug.value
  if (!s) return []
  const preferred = ['hub', 'dashboard', 'projects', 'credits', 'profile']
  const defs = communityHostNavDefs(s)
  const byKey = Object.fromEntries(defs.map((d) => [d.key, d]))
  return preferred
    .map((key) => byKey[key])
    .filter(Boolean)
    .slice(0, 5)
    .map((d) => ({
      to: d.to,
      icon: d.icon,
      label: t(d.labelKey),
    }))
})
</script>

<template>
  <nav class="community-host-quick-nav" :aria-label="t('communityHostNav.aria')">
    <RouterLink
      v-for="item in items"
      :key="item.label"
      :to="item.to"
      class="community-host-quick-nav__link"
      active-class="is-active"
      :aria-label="item.label"
      :title="item.label"
    >
      <Icon class="community-host-quick-nav__icon" :name="item.icon" aria-hidden="true" />
    </RouterLink>
  </nav>
</template>

<style lang="scss" scoped>
.community-host-quick-nav {
  display: flex;
  align-items: center;
  justify-content: space-around;
  gap: 0.25rem;
  min-height: 3.25rem;
  padding: 0 0.5rem env(safe-area-inset-bottom, 0);
  border-top: 1px solid var(--border);
  background: var(--bg);
}

.community-host-quick-nav__link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  max-width: 4.5rem;
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

.community-host-quick-nav__icon {
  font-size: 1.15rem;
}
</style>
