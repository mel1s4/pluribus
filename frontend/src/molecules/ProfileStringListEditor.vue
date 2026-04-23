<script setup>
import Button from '../atoms/Button.vue'
import Input from '../atoms/Input.vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  label: {
    type: String,
    default: '',
  },
  inputType: {
    type: String,
    default: 'text',
    validator: (v) => ['text', 'email', 'password', 'number', 'search'].includes(v),
  },
  addLabel: {
    type: String,
    default: '',
  },
  removeLabel: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

function addRow() {
  const next = [...(props.modelValue || []), '']
  emit('update:modelValue', next)
}

function removeRow(index) {
  const list = props.modelValue || []
  emit(
    'update:modelValue',
    list.filter((_, i) => i !== index),
  )
}

function updateRow(index, value) {
  const list = [...(props.modelValue || [])]
  list[index] = value
  emit('update:modelValue', list)
}
</script>

<template>
  <div class="profile-string-list-editor">
    <p v-if="label" class="profile-string-list-editor__label">{{ label }}</p>
    <ul class="profile-string-list-editor__list" role="list">
      <li
        v-for="(_, index) in modelValue || []"
        :key="index"
        class="profile-string-list-editor__row"
      >
        <Input
          :model-value="modelValue[index]"
          :name="`string-list-${index}`"
          :type="inputType"
          :disabled="disabled"
          @update:model-value="(v) => updateRow(index, v)"
        />
        <Button
          type="button"
          variant="secondary"
          class="profile-string-list-editor__remove"
          :disabled="disabled"
          @click="removeRow(index)"
        >
          {{ removeLabel }}
        </Button>
      </li>
    </ul>
    <Button type="button" variant="secondary" :disabled="disabled" @click="addRow">
      {{ addLabel }}
    </Button>
  </div>
</template>

<style lang="scss" scoped>
.profile-string-list-editor {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  width: 100%;
}

.profile-string-list-editor__label {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 600;
}

.profile-string-list-editor__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.profile-string-list-editor__row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: flex-start;
}

.profile-string-list-editor__row :deep(.field) {
  flex: 1 1 12rem;
  min-width: 0;
}

.profile-string-list-editor__remove {
  flex: 0 0 auto;
  margin-top: 0.15rem;
}
</style>
