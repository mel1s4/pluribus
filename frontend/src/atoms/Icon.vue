<script setup>
import { computed } from 'vue'

const props = defineProps({
  name: {
    type: String,
    required: true,
  },
  label: {
    type: String,
    default: undefined,
  },
})

const spriteBase = computed(() => {
  const base = import.meta.env.BASE_URL || '/'
  return base.endsWith('/') ? base : `${base}/`
})

const href = computed(() => `${spriteBase.value}icons.svg#${props.name}`)
</script>

<template>
  <svg
    class="icon"
    :class="'icon--' + name"
    role="img"
    :aria-hidden="label ? undefined : 'true'"
    :aria-label="label || undefined"
    focusable="false"
  >
    <use :href="href" />
  </svg>
</template>

<style scoped lang="scss">
.icon {
  display: inline-block;
  width: 1em;
  height: 1em;
  vertical-align: -0.125em;
  flex-shrink: 0;
}
</style>
