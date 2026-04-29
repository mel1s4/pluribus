<script setup>
import { computed } from 'vue'
import { t } from '../i18n/i18n'
import { brandIconSymbol, normalizeBrandLinks, PLACE_BRAND_ICON_OPTIONS } from '../utils/placeBrandLinks.js'

const props = defineProps({
  links: {
    type: Array,
    default: () => [],
  },
  compact: {
    type: Boolean,
    default: false,
  },
})

const normalized = computed(() => normalizeBrandLinks(props.links))

function iconLabel(key) {
  return t(PLACE_BRAND_ICON_OPTIONS.find((item) => item.value === key)?.labelKey || 'myPlaces.brandIconWebsite')
}
</script>

<template>
  <ul
    v-if="normalized.length"
    class="place-brand-links-list"
    :class="{ 'place-brand-links-list--compact': compact }"
  >
    <li v-for="(link, index) in normalized" :key="`${link.url}-${index}`" class="place-brand-links-list__item">
      <a
        :href="link.url"
        class="place-brand-links-list__anchor"
        target="_blank"
        rel="noopener noreferrer"
      >
        <span class="place-brand-links-list__icon" :aria-label="iconLabel(link.icon)">
          {{ brandIconSymbol(link.icon) }}
        </span>
        <span class="place-brand-links-list__text">{{ link.title }}</span>
      </a>
    </li>
  </ul>
</template>

<style scoped lang="scss">
.place-brand-links-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.45rem;
}

.place-brand-links-list__anchor {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  text-decoration: none;
  color: var(--link, #2563eb);
}

.place-brand-links-list__icon {
  min-width: 1.65rem;
  height: 1.65rem;
  border-radius: 999px;
  border: 1px solid var(--border);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--text);
  background: var(--bg-muted, rgba(148, 163, 184, 0.1));
  font-size: 0.68rem;
  font-weight: 700;
}

.place-brand-links-list__text {
  color: inherit;
  text-decoration: underline;
  text-underline-offset: 2px;
}

.place-brand-links-list--compact .place-brand-links-list__icon {
  min-width: 1.4rem;
  height: 1.4rem;
  font-size: 0.62rem;
}

.place-brand-links-list--compact .place-brand-links-list__anchor {
  font-size: 0.88rem;
}
</style>
