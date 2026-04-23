<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  label: {
    type: String,
    default: '',
  },
  hint: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue'])

const localText = ref('')

watch(
  () => props.modelValue,
  (v) => {
    localText.value = Array.isArray(v) ? v.join(', ') : ''
  },
  { immediate: true, deep: true },
)

function commit() {
  const next = localText.value
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean)
    .slice(0, 50)
  emit('update:modelValue', next)
}

defineExpose({ commit })
</script>

<template>
  <div class="place-tags-field">
    <label v-if="label" class="place-tags-field__label">{{ label }}</label>
    <input
      v-model="localText"
      class="place-tags-field__input"
      type="text"
      :disabled="disabled"
      maxlength="2000"
      @blur="commit"
      @keydown.enter.prevent="commit"
    />
    <p v-if="hint" class="place-tags-field__hint">{{ hint }}</p>
  </div>
</template>

<style lang="scss" scoped>
.place-tags-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.place-tags-field__label {
  font-size: 0.85rem;
}

.place-tags-field__input {
  max-width: 28rem;
  width: 100%;
  box-sizing: border-box;
}

.place-tags-field__hint {
  margin: 0;
  font-size: 0.8rem;
  opacity: 0.75;
}
</style>
