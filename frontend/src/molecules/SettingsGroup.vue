<template>
  <div class="settings-group">
    <h2 class="settings-group__title">{{ title }}</h2>

    <div class="settings-options">
      <RadioGroup
        :modelValue="modelValue"
        :name="name"
        :options="options"
        :disabled="disabled"
        @update:modelValue="emitUpdate"
      />
    </div>
  </div>
</template>

<script setup>
import RadioGroup from './RadioGroup.vue'

const props = defineProps({
  title: {
    type: String,
    default: '',
  },
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
.settings-group {
  margin-top: 1.5rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: 0.75rem;
}

.settings-group__title {
  margin: 0;
  font-size: 1.1rem;
}

.settings-options {
  margin-top: 0.75rem;
}
</style>

