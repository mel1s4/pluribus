<template>
  <div class="public-layout">
    <PublicNav />
    <main class="public-layout__main">
      <slot />
    </main>
  </div>
</template>

<script setup>
import PublicNav from '../components/public/PublicNav.vue'
</script>

<style lang="scss" scoped>
.public-layout {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
}

.public-layout__main {
  flex: 1 1 auto;
  min-width: 0;
}
</style>
