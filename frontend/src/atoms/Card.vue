<template>
  <div class="card">
    <slot />
  </div>
</template>

<script setup>
// Presentational wrapper only.
</script>

<style lang="scss" scoped>
.card {
  width: 100%;
  border: 1px solid var(--color-outline);
  border-radius: var(--radius-lg);
  padding: var(--space-6);
  background: var(--color-surface);
  box-shadow: var(--shadow-1);
}
</style>
