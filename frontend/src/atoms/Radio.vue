<template>
  <label class="radio">
    <input
      class="radio__input"
      type="radio"
      :name="name"
      :value="value"
      :checked="modelValue === value"
      :disabled="disabled"
      @change="onChange"
    />
    <span class="radio__label">{{ label }}</span>
  </label>
</template>

<script setup>
const props = defineProps({
  modelValue: {
    type: [String, Number],
    required: true,
  },
  value: {
    type: [String, Number],
    required: true,
  },
  label: {
    type: String,
    default: '',
  },
  name: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

function onChange(e) {
  if (props.disabled) return
  if (e.target.checked) emit('update:modelValue', props.value)
}
</script>

<style lang="scss" scoped>
.radio {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  cursor: pointer;
  user-select: none;
}

.radio__input {
  margin: 0;
  accent-color: var(--link);
}

.radio__label {
  font-size: 0.95rem;
}
</style>

