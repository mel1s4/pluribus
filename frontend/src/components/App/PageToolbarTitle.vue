<script setup>
import FavoriteButton from './FavoriteButton.vue'

defineProps({
  /** Sidebar nav key (see router meta.sidebarKey). */
  routeKey: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <div class="page-toolbar-title">
    <div class="page-toolbar-title__main">
      <slot />
    </div>
    <FavoriteButton
      v-if="routeKey"
      class="page-toolbar-title__favorite"
      :route-key="routeKey"
      size="md"
    />
  </div>
</template>

<style lang="scss" scoped>
.page-toolbar-title {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  width: 100%;
}

.page-toolbar-title__main {
  flex: 1;
  min-width: 0;
}

.page-toolbar-title__favorite {
  margin-top: 0.15rem;
}
</style>
