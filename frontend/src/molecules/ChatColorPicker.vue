<script setup>
import { computed, ref, watch } from 'vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  modelValue: {
    type: String,
    default: '#2563eb',
  },
})

const emit = defineEmits(['update:modelValue'])

const hexPattern = /^#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})$/

function normalizeHex(v) {
  if (v == null || typeof v !== 'string') {
    return '#2563eb'
  }
  const t = v.trim()
  if (hexPattern.test(t)) {
    if (t.length === 4) {
      return `#${t[1]}${t[1]}${t[2]}${t[2]}${t[3]}${t[3]}`
    }
    return t.toLowerCase()
  }
  return '#2563eb'
}

const colorValue = ref(normalizeHex(props.modelValue))
const textInput = ref(colorValue.value)

watch(
  () => props.modelValue,
  (v) => {
    const n = normalizeHex(v)
    colorValue.value = n
    textInput.value = n
  },
)

const colorForPicker = computed({
  get: () => colorValue.value,
  set: (v) => {
    const n = normalizeHex(v)
    colorValue.value = n
    textInput.value = n
    emit('update:modelValue', n)
  },
})

function onTextCommit() {
  const raw = textInput.value?.trim() || ''
  const withHash = raw.startsWith('#') ? raw : `#${raw}`
  if (!hexPattern.test(withHash)) {
    textInput.value = colorValue.value
    return
  }
  const n = normalizeHex(withHash)
  colorValue.value = n
  textInput.value = n
  emit('update:modelValue', n)
}
</script>

<template>
  <div class="chat-color-picker">
    <label class="chat-color-picker__row">
      <span class="sr-only">{{ t('chats.info.editColor') }}</span>
      <input
        v-model="colorForPicker"
        class="chat-color-picker__native"
        type="color"
        :aria-label="t('chats.info.colorPickerNative')"
      >
      <input
        v-model="textInput"
        class="chat-color-picker__hex"
        type="text"
        spellcheck="false"
        maxlength="7"
        :aria-label="t('chats.info.colorHexInput')"
        @blur="onTextCommit"
        @keydown.enter.prevent="onTextCommit"
      >
    </label>
  </div>
</template>

<style scoped lang="scss">
.chat-color-picker {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.chat-color-picker__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.chat-color-picker__native {
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 0.35rem;
  cursor: pointer;
  background: transparent;
}
.chat-color-picker__native::-webkit-color-swatch-wrapper { padding: 2px; }
.chat-color-picker__native::-webkit-color-swatch { border: none; border-radius: 0.2rem; }

.chat-color-picker__hex {
  font-family: ui-monospace, monospace;
  font-size: 0.9rem;
  max-width: 7.5rem;
  padding: 0.35rem 0.5rem;
  border: 1px solid var(--border);
  border-radius: 0.35rem;
  background: var(--surface);
  color: inherit;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
