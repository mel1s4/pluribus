<script setup>
const props = defineProps({
  modelValue: {
    type: String,
    default: '#2563eb',
  },
})

const emit = defineEmits(['update:modelValue'])

const colors = [
  '#2563eb', '#7c3aed', '#0ea5e9', '#16a34a', '#eab308',
  '#ea580c', '#dc2626', '#db2777', '#334155', '#64748b',
]

function selectColor(color) {
  emit('update:modelValue', color)
}
</script>

<template>
  <div class="chat-color-picker">
    <button
      v-for="color in colors"
      :key="color"
      type="button"
      class="chat-color-picker__swatch"
      :class="{ 'chat-color-picker__swatch--active': props.modelValue === color }"
      :style="{ backgroundColor: color }"
      @click="selectColor(color)"
    />
  </div>
</template>

<style scoped lang="scss">
.chat-color-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.chat-color-picker__swatch {
  width: 1.7rem;
  height: 1.7rem;
  border: 2px solid transparent;
  border-radius: 999px;
  cursor: pointer;
}

.chat-color-picker__swatch--active {
  border-color: #ffffff;
  box-shadow: 0 0 0 2px var(--link);
}
</style>
