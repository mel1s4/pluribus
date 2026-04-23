<template>
  <component :is="tag" class="title">
    <slot />
  </component>
</template>

<script setup>
const props = defineProps({
  tag: {
    type: String,
    default: 'h1',
    validator: (v) => ['h1', 'h2', 'h3', 'h4'].includes(v),
  },
})
</script>

<style lang="scss" scoped>
.title {
  margin: 0 0 1.25rem 0;
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.2;
}
</style>