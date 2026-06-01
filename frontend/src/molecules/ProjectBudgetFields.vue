<script setup>
import { computed } from 'vue'
import Button from '../atoms/Button.vue'
import { t } from '../i18n/i18n'
import { budgetTotalFromRows, lineSubtotal } from '../utils/communityProjectForm.js'

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

const total = computed(() => budgetTotalFromRows(rows()))

function patch(next) {
  emit('update:modelValue', next)
}

function addRow() {
  patch([...rows(), { name: '', description: '', unit_cost: '', units: '1' }])
}

function removeRow(index) {
  patch(rows().filter((_, i) => i !== index))
}

function updateRow(index, key, value) {
  patch(rows().map((r, i) => (i === index ? { ...r, [key]: value } : r)))
}

function rowSubtotal(row) {
  return lineSubtotal(row.unit_cost, row.units)
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
      <label class="project-budget-fields__field project-budget-fields__field--num">
        <span>{{ t('communityProjects.budgetUnitCost') }}</span>
        <input
          :value="row.unit_cost"
          type="text"
          class="project-budget-fields__input"
          inputmode="decimal"
          @input="updateRow(index, 'unit_cost', $event.target.value)"
        />
      </label>
      <label class="project-budget-fields__field project-budget-fields__field--num">
        <span>{{ t('communityProjects.budgetUnits') }}</span>
        <input
          :value="row.units"
          type="text"
          class="project-budget-fields__input"
          inputmode="decimal"
          @input="updateRow(index, 'units', $event.target.value)"
        />
      </label>
      <div class="project-budget-fields__subtotal">
        <span class="project-budget-fields__subtotal-label">{{ t('communityProjects.budgetSubtotal') }}</span>
        <span class="project-budget-fields__subtotal-value">{{ rowSubtotal(row) }}</span>
      </div>
      <Button type="button" class="project-budget-fields__remove" @click="removeRow(index)">{{ t('communityProjects.budgetRemove') }}</Button>
    </div>
    <p v-if="rows().length" class="project-budget-fields__total">
      <strong>{{ t('communityProjects.budgetTotal') }}: {{ total }}</strong>
    </p>
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
  grid-template-columns: 1fr 1fr 6rem 5rem 5rem auto;
  gap: 0.35rem 0.5rem;
  align-items: end;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--border);
}
@media (max-width: 720px) {
  .project-budget-fields__row {
    grid-template-columns: 1fr 1fr;
  }
}
.project-budget-fields__field {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.82rem;
}
.project-budget-fields__field--num {
  max-width: 7rem;
}
.project-budget-fields__input {
  font: inherit;
  padding: 0.35rem 0.45rem;
  border-radius: 0.35rem;
  border: 1px solid var(--border);
}
.project-budget-fields__subtotal {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.82rem;
}
.project-budget-fields__subtotal-value {
  font-weight: 600;
  padding: 0.35rem 0;
}
.project-budget-fields__total {
  margin: 0.25rem 0 0;
  font-size: 0.92rem;
}
.project-budget-fields__add,
.project-budget-fields__remove {
  font-size: 0.85rem;
}
</style>
