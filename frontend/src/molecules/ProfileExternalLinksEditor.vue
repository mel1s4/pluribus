<script setup>
import Button from '../atoms/Button.vue'
import Input from '../atoms/Input.vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  heading: {
    type: String,
    default: '',
  },
  titleLabel: {
    type: String,
    default: '',
  },
  urlLabel: {
    type: String,
    default: '',
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

function rowTitle(index) {
  const row = props.modelValue?.[index]
  return row && typeof row === 'object' ? String(row.title ?? '') : ''
}

function rowUrl(index) {
  const row = props.modelValue?.[index]
  return row && typeof row === 'object' ? String(row.url ?? '') : ''
}

function setRow(index, patch) {
  const list = [...(props.modelValue || [])]
  const prev = list[index] && typeof list[index] === 'object' ? list[index] : { title: '', url: '' }
  list[index] = { ...prev, ...patch }
  emit('update:modelValue', list)
}

function addRow() {
  const next = [...(props.modelValue || []), { title: '', url: '' }]
  emit('update:modelValue', next)
}

function removeRow(index) {
  const list = props.modelValue || []
  emit(
    'update:modelValue',
    list.filter((_, i) => i !== index),
  )
}
</script>

<template>
  <div class="profile-external-links-editor">
    <p v-if="heading" class="profile-external-links-editor__heading">{{ heading }}</p>
    <ul class="profile-external-links-editor__list" role="list">
      <li
        v-for="(_, index) in modelValue || []"
        :key="index"
        class="profile-external-links-editor__row"
      >
        <div class="profile-external-links-editor__pair">
          <Input
            :model-value="rowTitle(index)"
            :name="`ext-link-title-${index}`"
            type="text"
            :label="titleLabel"
            :disabled="disabled"
            @update:model-value="(v) => setRow(index, { title: v })"
          />
          <Input
            :model-value="rowUrl(index)"
            :name="`ext-link-url-${index}`"
            type="url"
            :label="urlLabel"
            :disabled="disabled"
            autocomplete="url"
            @update:model-value="(v) => setRow(index, { url: v })"
          />
        </div>
        <Button
          type="button"
          variant="secondary"
          class="profile-external-links-editor__remove"
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
.profile-external-links-editor {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  width: 100%;
}

.profile-external-links-editor__heading {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 600;
}

.profile-external-links-editor__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.profile-external-links-editor__row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: flex-start;
}

.profile-external-links-editor__pair {
  flex: 1 1 16rem;
  min-width: 0;
  display: grid;
  gap: 0.5rem;
}

.profile-external-links-editor__remove {
  flex: 0 0 auto;
  margin-top: 1.65rem;
}

@media (max-width: 36rem) {
  .profile-external-links-editor__remove {
    margin-top: 0;
  }
}
</style>
