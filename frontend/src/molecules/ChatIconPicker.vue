<script setup>
const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue'])

const emojis = ['💬', '🧠', '📚', '✅', '🎯', '🌱', '⚡', '🛠️', '📌', '🧩', '🎉', '🤝']

function pick(emoji) {
  emit('update:modelValue', emoji)
}
</script>

<template>
  <div class="chat-icon-picker">
    <button
      v-for="emoji in emojis"
      :key="emoji"
      type="button"
      class="chat-icon-picker__item"
      :class="{ 'chat-icon-picker__item--active': props.modelValue === emoji }"
      @click="pick(emoji)"
    >
      {{ emoji }}
    </button>
  </div>
</template>

<style scoped lang="scss">
.chat-icon-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.chat-icon-picker__item {
  width: 2rem;
  height: 2rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--bg);
  cursor: pointer;
}

.chat-icon-picker__item--active {
  border-color: var(--link);
}
</style>
