<script setup>
import { computed } from 'vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  offers: {
    type: Array,
    default: () => [],
  },
})

const list = computed(() => (Array.isArray(props.offers) ? props.offers : []))
</script>

<template>
  <div class="place-offers-public">
    <p v-if="list.length === 0" class="place-offers-public__empty">
      {{ t('places.viewOffersEmpty') }}
    </p>
    <ul v-else class="place-offers-public__list" role="list">
      <li v-for="o in list" :key="o.id" class="place-offers-public__card">
        <div v-if="o.photo_url" class="place-offers-public__photoWrap">
          <img
            :src="o.photo_url"
            :alt="o.title || ''"
            class="place-offers-public__photo"
            loading="lazy"
          />
        </div>
        <div class="place-offers-public__body">
          <h3 class="place-offers-public__title">{{ o.title }}</h3>
          <p v-if="o.description" class="place-offers-public__desc">{{ o.description }}</p>
          <p class="place-offers-public__price">{{ o.price }}</p>
          <ul
            v-if="Array.isArray(o.tags) && o.tags.length"
            class="place-offers-public__tags"
            aria-label="Tags"
          >
            <li v-for="(tag, i) in o.tags" :key="i" class="place-offers-public__tag">
              {{ tag }}
            </li>
          </ul>
        </div>
      </li>
    </ul>
  </div>
</template>

<style lang="scss" scoped>
.place-offers-public__empty {
  margin: 0;
  font-size: 0.9rem;
  opacity: 0.8;
}

.place-offers-public__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.place-offers-public__card {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--card-bg, transparent);
}

.place-offers-public__photoWrap {
  flex: 0 0 8rem;
  max-width: 100%;
}

.place-offers-public__photo {
  width: 100%;
  height: auto;
  border-radius: 0.35rem;
  display: block;
}

.place-offers-public__body {
  flex: 1 1 12rem;
  min-width: 0;
}

.place-offers-public__title {
  margin: 0 0 0.35rem;
  font-size: 1.05rem;
}

.place-offers-public__desc {
  margin: 0 0 0.5rem;
  font-size: 0.9rem;
  opacity: 0.9;
  white-space: pre-wrap;
}

.place-offers-public__price {
  margin: 0 0 0.35rem;
  font-weight: 600;
}

.place-offers-public__tags {
  list-style: none;
  margin: 0.35rem 0 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.place-offers-public__tag {
  font-size: 0.75rem;
  padding: 0.15rem 0.45rem;
  border-radius: 0.35rem;
  background: rgba(37, 99, 235, 0.1);
  color: #1d4ed8;
}
</style>
