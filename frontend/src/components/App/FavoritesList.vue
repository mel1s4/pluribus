<script setup>
import { ref, watch } from 'vue'
import { VueDraggableNext } from 'vue-draggable-next'
import Button from '../../atoms/Button.vue'
import Icon from '../../atoms/Icon.vue'
import { useFavorites } from '../../composables/useFavorites'
import { useSession } from '../../composables/useSession'
import { t } from '../../i18n/i18n'

const emit = defineEmits(['navigate'])

const { status } = useSession()
const { favoriteNavItems, loading, saveOrder } = useFavorites()

const reorderMode = ref(false)
const draftItems = ref([])
const saveBusy = ref(false)
const saveError = ref('')

watch(
  favoriteNavItems,
  (items) => {
    if (!reorderMode.value) return
    draftItems.value = items.map((i) => ({ ...i }))
  },
  { deep: true },
)

function enterReorder() {
  saveError.value = ''
  reorderMode.value = true
  draftItems.value = favoriteNavItems.value.map((i) => ({ ...i }))
}

function exitReorder() {
  reorderMode.value = false
  draftItems.value = []
  saveError.value = ''
}

async function onSaveOrder() {
  saveError.value = ''
  saveBusy.value = true
  const keys = draftItems.value.map((i) => i.key)
  const { ok } = await saveOrder(keys)
  saveBusy.value = false
  if (!ok) {
    saveError.value = t('favorites.errorReorder')
    return
  }
  exitReorder()
}

function onRowClick() {
  emit('navigate')
}
</script>

<template>
  <section
    v-if="status === 'authenticated'"
    class="favorites-list"
    :aria-busy="loading ? 'true' : 'false'"
  >
    <div class="favorites-list__head">
      <h2 class="favorites-list__title">{{ t('favorites.title') }}</h2>
      <div class="favorites-list__headActions">
        <Button
          v-if="favoriteNavItems.length > 0 && !reorderMode"
          type="button"
          variant="ghost"
          size="sm"
          @click="enterReorder"
        >
          {{ t('favorites.reorder') }}
        </Button>
        <template v-if="reorderMode">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            :loading="saveBusy"
            @click="onSaveOrder"
          >
            {{ t('favorites.save') }}
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            :disabled="saveBusy"
            @click="exitReorder"
          >
            {{ t('favorites.cancel') }}
          </Button>
        </template>
      </div>
    </div>

    <p v-if="saveError" class="favorites-list__error" role="alert">{{ saveError }}</p>

    <p v-if="loading && favoriteNavItems.length === 0" class="favorites-list__hint">
      {{ t('favorites.loading') }}
    </p>
    <p v-else-if="favoriteNavItems.length === 0" class="favorites-list__hint">{{ t('favorites.empty') }}</p>

    <ul v-else-if="!reorderMode" class="favorites-list__list">
      <li v-for="element in favoriteNavItems" :key="element.key" class="favorites-list__item">
        <RouterLink
          :to="element.to"
          class="favorites-list__link"
          active-class="is-active"
          @click="onRowClick"
        >
          <Icon class="favorites-list__icon" :name="element.icon" aria-hidden="true" />
          <span>{{ element.label }}</span>
        </RouterLink>
      </li>
    </ul>

    <VueDraggableNext
      v-else
      v-model="draftItems"
      class="favorites-list__list"
      item-key="key"
      handle=".favorites-list__handle"
      tag="ul"
    >
      <template #item="{ element }">
        <li :key="element.key" class="favorites-list__item">
          <button
            type="button"
            class="favorites-list__handle"
            :aria-label="t('favorites.dragHandle')"
            tabindex="-1"
          >
            <Icon class="favorites-list__handleIcon" name="grip-dots-vertical" aria-hidden="true" />
          </button>
          <div class="favorites-list__rowStatic">
            <Icon class="favorites-list__icon" :name="element.icon" aria-hidden="true" />
            <span>{{ element.label }}</span>
          </div>
        </li>
      </template>
    </VueDraggableNext>
  </section>
</template>

<style lang="scss" scoped>
.favorites-list {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 0.5rem 0.5rem 0.65rem;
  border-bottom: 1px solid var(--border);
}

.favorites-list__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.favorites-list__title {
  margin: 0;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.75;
}

.favorites-list__headActions {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.favorites-list__error {
  margin: 0;
  font-size: 0.85rem;
  color: var(--danger, #b91c1c);
}

.favorites-list__hint {
  margin: 0;
  font-size: 0.85rem;
  opacity: 0.8;
  padding: 0.15rem 0.35rem 0.35rem;
}

.favorites-list__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  max-height: 40vh;
  overflow: auto;
  min-height: 0;
}

.favorites-list__item {
  display: flex;
  align-items: stretch;
  gap: 0.25rem;
}

.favorites-list__handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  flex-shrink: 0;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--btn-bg);
  color: inherit;
  cursor: grab;
  padding: 0;

  &:active {
    cursor: grabbing;
  }
}

.favorites-list__handleIcon {
  font-size: 1rem;
  opacity: 0.75;
}

.favorites-list__link,
.favorites-list__rowStatic {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  flex: 1;
  min-width: 0;
  padding: 0.55rem 0.65rem;
  border-radius: 0.5rem;
  font-size: 0.92rem;
  border: 1px solid transparent;
}

.favorites-list__link {
  color: inherit;
  text-decoration: none;

  &:hover {
    background: color-mix(in srgb, var(--border) 45%, transparent);
  }

  &.is-active {
    border-color: var(--border);
    background: color-mix(in srgb, var(--border) 55%, transparent);
    font-weight: 600;
  }
}

.favorites-list__rowStatic {
  background: color-mix(in srgb, var(--border) 25%, transparent);
}

.favorites-list__icon {
  font-size: 1.05rem;
  flex-shrink: 0;
}
</style>
