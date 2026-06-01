<template>
  <component :is="tag" class="title" :class="`title--${tag}`" :id="id || undefined">
    <slot />
  </component>
</template>

<script setup>
defineProps({
  tag: {
    type: String,
    default: 'h1',
    validator: (v) => ['h1', 'h2', 'h3', 'h4'].includes(v),
  },
  id: {
    type: String,
    default: '',
  },
})
</script>

<style lang="scss" scoped>
.title {
  margin: 0 0 var(--space-5) 0;
  font-weight: var(--font-weight-extrabold);
  letter-spacing: var(--letter-spacing-tight);
  line-height: var(--line-height-tight);
  color: var(--text);
}

.title--h1 {
  font-size: var(--text-2xl);
}

.title--h2 {
  font-size: var(--text-xl);
  font-weight: var(--font-weight-bold);
}

.title--h3 {
  font-size: var(--text-lg);
  font-weight: var(--font-weight-bold);
}

.title--h4 {
  font-size: var(--text-md);
  font-weight: var(--font-weight-semibold);
  letter-spacing: 0;
}
</style>
