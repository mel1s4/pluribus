<script setup>
import { computed } from 'vue'
import Button from '../../atoms/Button.vue'
import { t } from '../../i18n/i18n'
import { PLACE_BRAND_ICON_OPTIONS, normalizeBrandLinks } from '../../utils/placeBrandLinks.js'

const props = defineProps({
  modelValue: {
    type: Object,
    required: true,
  },
  saveError: {
    type: String,
    default: '',
  },
  saveLoading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'save'])

const rows = computed(() => (Array.isArray(props.modelValue.brand_links) ? props.modelValue.brand_links : []))
const canAdd = computed(() => rows.value.length < 20)

function updateRows(nextRows) {
  emit('update:modelValue', {
    ...props.modelValue,
    brand_links: nextRows,
  })
}

function addRow() {
  if (!canAdd.value) return
  updateRows([
    ...rows.value,
    { title: '', url: '', icon: 'website' },
  ])
}

function removeRow(index) {
  updateRows(rows.value.filter((_, i) => i !== index))
}

function updateField(index, key, value) {
  const next = rows.value.map((entry, i) => (i === index ? { ...entry, [key]: value } : entry))
  updateRows(next)
}

function onSave() {
  updateRows(normalizeBrandLinks(rows.value))
  emit('save')
}
</script>

<template>
  <section class="place-brand-links">
    <div class="place-brand-links__head">
      <h2 class="place-brand-links__title">{{ t('myPlaces.brandTitle') }}</h2>
      <p class="place-brand-links__hint">{{ t('myPlaces.brandHint') }}</p>
    </div>

    <div v-if="rows.length" class="place-brand-links__rows">
      <div
        v-for="(row, index) in rows"
        :key="index"
        class="place-brand-links__row"
      >
        <label class="place-brand-links__field">
          <span class="place-brand-links__label">{{ t('myPlaces.brandFieldTitle') }}</span>
          <input
            class="place-brand-links__input"
            type="text"
            :value="row.title"
            :placeholder="t('myPlaces.brandFieldTitlePlaceholder')"
            @input="updateField(index, 'title', $event.target.value)"
          />
        </label>
        <label class="place-brand-links__field">
          <span class="place-brand-links__label">{{ t('myPlaces.brandFieldUrl') }}</span>
          <input
            class="place-brand-links__input"
            type="url"
            :value="row.url"
            placeholder="https://example.com"
            @input="updateField(index, 'url', $event.target.value)"
          />
        </label>
        <label class="place-brand-links__field">
          <span class="place-brand-links__label">{{ t('myPlaces.brandFieldIcon') }}</span>
          <select
            class="place-brand-links__select"
            :value="row.icon || 'website'"
            @change="updateField(index, 'icon', $event.target.value)"
          >
            <option
              v-for="option in PLACE_BRAND_ICON_OPTIONS"
              :key="option.value"
              :value="option.value"
            >
              {{ t(option.labelKey) }}
            </option>
          </select>
        </label>
        <button
          type="button"
          class="place-brand-links__remove"
          @click="removeRow(index)"
        >
          {{ t('myPlaces.brandRemove') }}
        </button>
      </div>
    </div>
    <p v-else class="place-brand-links__empty">{{ t('myPlaces.brandEmpty') }}</p>

    <div class="place-brand-links__actions">
      <Button
        type="button"
        variant="secondary"
        :disabled="!canAdd"
        @click="addRow"
      >
        {{ t('myPlaces.brandAdd') }}
      </Button>
      <Button
        type="button"
        :loading="saveLoading"
        @click="onSave"
      >
        {{ t('myPlaces.savePlace') }}
      </Button>
    </div>
    <p v-if="saveError" class="place-brand-links__error" role="alert">{{ saveError }}</p>
  </section>
</template>

<style scoped lang="scss">
.place-brand-links {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.place-brand-links__title {
  margin: 0;
  font-size: 1.1rem;
}

.place-brand-links__hint {
  margin: 0.2rem 0 0;
  color: var(--text-muted, #64748b);
}

.place-brand-links__rows {
  display: grid;
  gap: 0.75rem;
}

.place-brand-links__row {
  border: 1px solid var(--border);
  border-radius: 0.7rem;
  padding: 0.75rem;
  display: grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap: 0.6rem;
}

@media (min-width: 920px) {
  .place-brand-links__row {
    grid-template-columns: 1fr 1fr auto auto;
    align-items: end;
  }
}

.place-brand-links__field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.place-brand-links__label {
  font-size: 0.8rem;
  color: var(--text-muted, #64748b);
}

.place-brand-links__input,
.place-brand-links__select {
  min-height: 2.2rem;
  border: 1px solid var(--border);
  background: var(--bg);
  color: var(--text);
  border-radius: 0.5rem;
  padding: 0.45rem 0.6rem;
  font: inherit;
}

.place-brand-links__remove {
  min-height: 2.2rem;
  border: 1px solid var(--border);
  background: var(--bg);
  color: var(--text);
  border-radius: 0.5rem;
  padding: 0.45rem 0.7rem;
  cursor: pointer;
  font: inherit;
}

.place-brand-links__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.place-brand-links__empty {
  margin: 0;
  color: var(--text-muted, #64748b);
}

.place-brand-links__error {
  margin: 0;
  color: #b91c1c;
}
</style>
