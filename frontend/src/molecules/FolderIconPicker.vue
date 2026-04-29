<script setup>
import { computed } from 'vue'

const props = defineProps({
  emoji: {
    type: String,
    default: '📁',
  },
  bgColor: {
    type: String,
    default: '#6366f1',
  },
})

const emit = defineEmits(['update:emoji', 'update:bgColor'])

const swatches = [
  '#6366f1',
  '#2563eb',
  '#0d9488',
  '#16a34a',
  '#ca8a04',
  '#ea580c',
  '#dc2626',
  '#9333ea',
  '#64748b',
]

const emojiModel = computed({
  get: () => props.emoji,
  set: (v) => emit('update:emoji', v),
})

const colorModel = computed({
  get: () => props.bgColor,
  set: (v) => emit('update:bgColor', v),
})
</script>

<template>
  <div class="folder-icon-picker">
    <label class="folder-icon-picker__label">
      <span class="folder-icon-picker__preview" :style="{ background: colorModel }">{{ emojiModel }}</span>
      <input v-model="emojiModel" type="text" maxlength="8" class="folder-icon-picker__emoji" aria-label="Emoji">
    </label>
    <div class="folder-icon-picker__swatches" role="list">
      <button
        v-for="c in swatches"
        :key="c"
        type="button"
        class="folder-icon-picker__swatch"
        :class="{ 'is-active': colorModel === c }"
        :style="{ background: c }"
        :aria-pressed="colorModel === c"
        @click="colorModel = c"
      />
    </div>
  </div>
</template>

<style scoped lang="scss">
.folder-icon-picker {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.folder-icon-picker__label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.folder-icon-picker__preview {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  flex-shrink: 0;
}

.folder-icon-picker__emoji {
  flex: 1;
  min-width: 0;
}

.folder-icon-picker__swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.folder-icon-picker__swatch {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 0.35rem;
  border: 2px solid transparent;
  cursor: pointer;
  padding: 0;

  &.is-active {
    border-color: var(--text, #111);
    box-shadow: 0 0 0 1px var(--bg, #fff);
  }
}
</style>
