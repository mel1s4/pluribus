<script setup>
import { ref, watch } from 'vue'
import { t } from '../i18n/i18n'
import { createRequirementResponse } from '../services/placesApi.js'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  placeId: {
    type: [Number, String],
    required: true,
  },
  requirementId: {
    type: [Number, String],
    required: true,
  },
})

const emit = defineEmits(['close', 'saved'])

const title = ref('')
const description = ref('')
const price = ref('')
const visibility = ref('creator_only')
const error = ref('')
const saving = ref(false)

watch(
  () => props.open,
  (v) => {
    if (v) {
      title.value = ''
      description.value = ''
      price.value = ''
      visibility.value = 'creator_only'
      error.value = ''
    }
  },
)

function onBackdrop() {
  emit('close')
}

async function submit() {
  if (!props.requirementId) return
  error.value = ''
  saving.value = true
  const { ok, status } = await createRequirementResponse(props.placeId, props.requirementId, {
    title: title.value.trim(),
    description: description.value.trim() || null,
    price: Number(price.value),
    visibility: visibility.value,
    tags: [],
  })
  saving.value = false
  if (!ok) {
    error.value = t('places.requirementResponseError').replace('{status}', String(status))
    return
  }
  emit('saved')
}
</script>

<template>
  <div
    v-if="open"
    class="req-resp-dlg"
    role="dialog"
    aria-modal="true"
    :aria-label="t('places.requirementResponseDialogTitle')"
  >
    <button type="button" class="req-resp-dlg__backdrop" tabindex="-1" @click="onBackdrop" />
    <div class="req-resp-dlg__panel">
      <h3 class="req-resp-dlg__title">{{ t('places.requirementResponseDialogTitle') }}</h3>
      <p v-if="error" class="req-resp-dlg__error">{{ error }}</p>
      <form class="req-resp-dlg__form" @submit.prevent="submit">
        <label class="req-resp-dlg__label">{{ t('places.requirementResponseTitle') }}</label>
        <input v-model="title" class="req-resp-dlg__input" type="text" required />
        <label class="req-resp-dlg__label">{{ t('places.requirementResponseDescription') }}</label>
        <textarea v-model="description" class="req-resp-dlg__textarea" rows="2" />
        <label class="req-resp-dlg__label">{{ t('places.requirementResponsePrice') }}</label>
        <input
          v-model="price"
          class="req-resp-dlg__input"
          type="number"
          min="0"
          step="0.01"
          required
        />
        <fieldset class="req-resp-dlg__vis">
          <legend class="req-resp-dlg__visLegend">{{ t('places.requirementResponseVisibility') }}</legend>
          <label class="req-resp-dlg__radio">
            <input v-model="visibility" type="radio" value="creator_only" />
            {{ t('places.requirementResponseVisibilityCreator') }}
          </label>
          <label class="req-resp-dlg__radio">
            <input v-model="visibility" type="radio" value="community" />
            {{ t('places.requirementResponseVisibilityCommunity') }}
          </label>
        </fieldset>
        <div class="req-resp-dlg__actions">
          <button type="submit" class="req-resp-dlg__btn req-resp-dlg__btn--primary" :disabled="saving">
            {{ t('places.requirementResponseSubmit') }}
          </button>
          <button type="button" class="req-resp-dlg__btn" :disabled="saving" @click="emit('close')">
            {{ t('myPlaces.cancel') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.req-resp-dlg {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.req-resp-dlg__backdrop {
  position: absolute;
  inset: 0;
  border: none;
  padding: 0;
  margin: 0;
  background: rgba(15, 23, 42, 0.45);
  cursor: pointer;
}

.req-resp-dlg__panel {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 24rem;
  max-height: 90vh;
  overflow: auto;
  padding: 1rem 1.1rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  background: var(--bg);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
}

.req-resp-dlg__title {
  margin: 0 0 0.75rem;
  font-size: 1.05rem;
}

.req-resp-dlg__error {
  color: var(--danger, #b00020);
  font-size: 0.85rem;
  margin: 0 0 0.5rem;
}

.req-resp-dlg__form {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.req-resp-dlg__label {
  font-size: 0.85rem;
  margin-top: 0.25rem;
}

.req-resp-dlg__input,
.req-resp-dlg__textarea {
  width: 100%;
  box-sizing: border-box;
  font: inherit;
}

.req-resp-dlg__vis {
  border: none;
  margin: 0.5rem 0 0;
  padding: 0;
}

.req-resp-dlg__visLegend {
  font-size: 0.85rem;
  font-weight: 600;
  padding: 0;
  margin-bottom: 0.35rem;
}

.req-resp-dlg__radio {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.9rem;
  margin-bottom: 0.25rem;
  cursor: pointer;
}

.req-resp-dlg__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.75rem;
}

.req-resp-dlg__btn {
  cursor: pointer;
  font: inherit;
  padding: 0.35rem 0.65rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
}

.req-resp-dlg__btn--primary {
  border-color: var(--accent, #3b5bdb);
  background: var(--accent, #3b5bdb);
  color: #fff;
}

.req-resp-dlg__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
