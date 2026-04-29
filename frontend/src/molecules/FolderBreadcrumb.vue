<script setup>
import { computed } from 'vue'
import Icon from '../atoms/Icon.vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  /** @type {Array<{ id: number, name?: string }>} */
  ancestors: {
    type: Array,
    default: () => [],
  },
  rootLabel: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['navigate'])

const root = computed(() => props.rootLabel || t('folders.breadcrumbRoot'))

function goRoot() {
  emit('navigate', null)
}

function goFolder(id) {
  emit('navigate', id)
}
</script>

<template>
  <nav class="folder-breadcrumb" aria-label="Breadcrumb">
    <ol class="folder-breadcrumb__list">
      <li class="folder-breadcrumb__item">
        <button type="button" class="folder-breadcrumb__link" @click="goRoot">
          <Icon name="folder" class="folder-breadcrumb__icon" aria-hidden="true" />
          {{ root }}
        </button>
      </li>
      <li
        v-for="(a, idx) in ancestors"
        :key="a.id"
        class="folder-breadcrumb__item"
      >
        <Icon name="chevron-right" class="folder-breadcrumb__sep" aria-hidden="true" />
        <button
          v-if="idx < ancestors.length - 1"
          type="button"
          class="folder-breadcrumb__link"
          @click="goFolder(a.id)"
        >
          {{ a.name || t('folders.unnamed') }}
        </button>
        <span
          v-else
          class="folder-breadcrumb__current"
          aria-current="page"
        >
          {{ a.name || t('folders.unnamed') }}
        </span>
      </li>
    </ol>
  </nav>
</template>

<style scoped lang="scss">
.folder-breadcrumb__list {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.15rem 0.25rem;
  list-style: none;
  margin: 0;
  padding: 0;
  font-size: 0.9rem;
}

.folder-breadcrumb__item {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.folder-breadcrumb__sep {
  width: 0.85em;
  height: 0.85em;
  opacity: 0.6;
}

.folder-breadcrumb__link {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  border: none;
  background: none;
  color: var(--link, #2563eb);
  cursor: pointer;
  padding: 0.15rem 0.2rem;
  border-radius: 0.25rem;
  font: inherit;

  &:hover {
    text-decoration: underline;
  }
}

.folder-breadcrumb__icon {
  width: 1em;
  height: 1em;
}

.folder-breadcrumb__current {
  font-weight: 600;
  padding: 0.15rem 0.2rem;
}
</style>
