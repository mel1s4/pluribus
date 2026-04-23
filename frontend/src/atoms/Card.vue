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
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  padding: 2rem;
  background: transparent;
}
</style>