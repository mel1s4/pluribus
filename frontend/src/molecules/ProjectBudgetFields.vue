<script setup>
import Button from '../atoms/Button.vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  modelValue: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits(['update:modelValue'])

function rows() {
  return Array.isArray(props.modelValue) ? props.modelValue : []
}

function patch(next) {
  emit('update:modelValue', next)
}

function addRow() {
  patch([...rows(), { name: '', description: '', cost: '' }])
}

function removeRow(index) {
  const next = rows().filter((_, i) => i !== index)
  patch(next)
}

function updateRow(index, key, value) {
  const next = rows().map((r, i) => (i === index ? { ...r, [key]: value } : r))
  patch(next)
}
</script>

<template>
  <div class="project-budget-fields">
    <div class="project-budget-fields__head">
      <span class="project-budget-fields__label">{{ t('communityProjects.budgetHeading') }}</span>
      <Button type="button" class="project-budget-fields__add" @click="addRow">{{ t('communityProjects.budgetAddLine') }}</Button>
    </div>
    <p class="project-budget-fields__hint">{{ t('communityProjects.budgetHint') }}</p>
    <div v-for="(row, index) in rows()" :key="index" class="project-budget-fields__row">
      <label class="project-budget-fields__field">
        <span>{{ t('communityProjects.budgetName') }}</span>
        <input
          :value="row.name"
          type="text"
          class="project-budget-fields__input"
          maxlength="255"
          @input="updateRow(index, 'name', $event.target.value)"
        />
      </label>
      <label class="project-budget-fields__field">
        <span>{{ t('communityProjects.budgetDescription') }}</span>
        <input
          :value="row.description || ''"
          type="text"
          class="project-budget-fields__input"
          @input="updateRow(index, 'description', $event.target.value)"
        />
      </label>
      <label class="project-budget-fields__field project-budget-fields__field--cost">
        <span>{{ t('communityProjects.budgetCost') }}</span>
        <input
          :value="row.cost"
          type="text"
          class="project-budget-fields__input"
          inputmode="decimal"
          @input="updateRow(index, 'cost', $event.target.value)"
        />
      </label>
      <Button type="button" class="project-budget-fields__remove" @click="removeRow(index)">{{ t('communityProjects.budgetRemove') }}</Button>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.project-budget-fields {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.project-budget-fields__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}
.project-budget-fields__label {
  font-weight: 600;
}
.project-budget-fields__hint {
  margin: 0;
  font-size: 0.85rem;
  opacity: 0.8;
}
.project-budget-fields__row {
  display: grid;
  grid-template-columns: 1fr 1fr 7rem auto;
  gap: 0.35rem 0.5rem;
  align-items: end;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--border);
}
@media (max-width: 640px) {
  .project-budget-fields__row {
    grid-template-columns: 1fr;
  }
}
.project-budget-fields__field {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.82rem;
}
.project-budget-fields__field--cost {
  max-width: 8rem;
}
.project-budget-fields__input {
  font: inherit;
  padding: 0.35rem 0.45rem;
  border-radius: 0.35rem;
  border: 1px solid var(--border);
}
.project-budget-fields__add,
.project-budget-fields__remove {
  font-size: 0.85rem;
}
</style>
