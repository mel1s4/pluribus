<template>
  <div class="radio-group">
    <Radio
      v-for="opt in options"
      :key="opt.value"
      :modelValue="modelValue"
      :value="opt.value"
      :label="opt.label"
      :name="name"
      :disabled="disabled"
      @update:modelValue="emitUpdate"
    />
  </div>
</template>

<script setup>
import Radio from '../atoms/Radio.vue'

const props = defineProps({
  modelValue: {
    type: [String, Number],
    required: true,
  },
  name: {
    type: String,
    default: '',
  },
  options: {
    type: Array,
    default: () => [],
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

function emitUpdate(next) {
  emit('update:modelValue', next)
}
</script>

<style lang="scss" scoped>
.radio-group {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}
</style>

