<template>
  <label class="checkbox">
    <input
      class="checkbox__input"
      type="checkbox"
      :checked="modelValue"
      :disabled="disabled"
      @change="onChange"
    />
    <span class="checkbox__label">{{ label }}</span>
  </label>
</template>

<script setup>
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  label: {
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
  emit('update:modelValue', e.target.checked)
}
</script>

<style lang="scss" scoped>
.checkbox {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  cursor: pointer;
  user-select: none;
  width: fit-content;
}

.checkbox__input {
  width: var(--text-md);
  height: var(--text-md);
  margin: 0;
  accent-color: var(--color-primary);
}

.checkbox__label {
  font-size: var(--text-sm);
  color: var(--text);
}
</style>

