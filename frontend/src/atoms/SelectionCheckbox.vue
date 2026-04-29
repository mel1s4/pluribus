<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  indeterminate: {
    type: Boolean,
    default: false,
  },
  ariaLabel: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue'])
const inputRef = ref(null)

watch(
  () => props.indeterminate,
  (v) => {
    if (inputRef.value) inputRef.value.indeterminate = Boolean(v)
  },
  { immediate: true },
)
</script>

<template>
  <input
    ref="inputRef"
    type="checkbox"
    class="selection-checkbox"
    :checked="modelValue"
    :aria-label="ariaLabel || undefined"
    @change="emit('update:modelValue', ($event.target).checked)"
  >
</template>

<style scoped lang="scss">
.selection-checkbox {
  width: 1.1rem;
  height: 1.1rem;
  accent-color: var(--accent, #2563eb);
  cursor: pointer;
}
</style>
