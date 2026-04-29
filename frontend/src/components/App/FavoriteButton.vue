<script setup>
import { ref } from 'vue'
import Icon from '../../atoms/Icon.vue'
import { useFavorites } from '../../composables/useFavorites'
import { useSession } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { isSidebarNavKey, isSidebarRouteKeyAccessible } from '../../navigation/sidebarLinks'

const props = defineProps({
  routeKey: {
    type: String,
    required: true,
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md'].includes(v),
  },
})

const { status } = useSession()
const { isFavorite, toggleFavorite } = useFavorites()
const busy = ref(false)

const show = () =>
  status.value === 'authenticated'
  && isSidebarNavKey(props.routeKey)
  && isSidebarRouteKeyAccessible(props.routeKey)

async function onClick(event) {
  event.stopPropagation()
  event.preventDefault()
  if (!show() || busy.value) return
  busy.value = true
  try {
    await toggleFavorite(props.routeKey)
  } finally {
    busy.value = false
  }
}

function label() {
  return isFavorite(props.routeKey) ? t('favorites.remove') : t('favorites.add')
}
</script>

<template>
  <button
    v-if="show()"
    type="button"
    class="favorite-btn"
    :class="[
      `favorite-btn--${size}`,
      { 'favorite-btn--active': isFavorite(routeKey), 'favorite-btn--busy': busy },
    ]"
    :aria-pressed="isFavorite(routeKey) ? 'true' : 'false'"
    :aria-label="label()"
    :title="label()"
    :disabled="busy"
    @click="onClick"
  >
    <Icon
      class="favorite-btn__icon"
      :name="isFavorite(routeKey) ? 'star-filled' : 'star-outline'"
      aria-hidden="true"
    />
  </button>
</template>

<style lang="scss" scoped>
.favorite-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--btn-bg);
  color: var(--btn-text);
  cursor: pointer;

  &:hover:not(:disabled) {
    background: var(--btn-bg-hover);
  }

  &:disabled {
    opacity: 0.6;
    cursor: default;
  }
}

.favorite-btn--sm {
  width: 2.25rem;
  height: 2.25rem;
}

.favorite-btn--md {
  width: 2.5rem;
  height: 2.5rem;
}

.favorite-btn__icon {
  font-size: 1.1rem;
}

.favorite-btn--active {
  color: var(--link);
  border-color: color-mix(in srgb, var(--link) 35%, var(--border));
  background: color-mix(in srgb, var(--link) 12%, var(--btn-bg));
}
</style>
