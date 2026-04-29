<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import PlaceBasicsForm from '../../organisms/PlaceBasicsForm.vue'
import { t } from '../../i18n/i18n'
import { createPlace } from '../../services/placesApi.js'
import {
  emptyPlaceDraft,
  placeApiErrorMessage,
  placeCreateToFormData,
} from '../../utils/placeForm.js'
import { normalizeServiceSchedule } from '../../utils/placeSchedule.js'

const router = useRouter()
const draft = ref(emptyPlaceDraft())
const saveError = ref('')
const saveLoading = ref(false)
const pickerKey = ref(0)

async function onSubmit() {
  saveError.value = ''
  saveLoading.value = true
  const d = draft.value
  const useMultipart = d.logoFile instanceof File
  const payload = useMultipart
    ? placeCreateToFormData(d)
    : {
        name: d.name.trim(),
        slug: d.slug.trim(),
        description: d.description?.trim() || null,
        tags: Array.isArray(d.tags) ? d.tags : [],
        service_schedule: normalizeServiceSchedule(d.service_schedule),
        is_public: Boolean(d.is_public),
      }
  const { ok, status, data } = await createPlace(payload)
  saveLoading.value = false
  if (!ok) {
    saveError.value = placeApiErrorMessage(data, status, t('myPlaces.createError'))
    return
  }
  const id = data?.place?.id
  if (id != null) {
    await router.replace({ name: 'placeEdit', params: { placeId: String(id) } })
  }
}

function goBack() {
  router.push({ name: 'myPlaces' })
}

function setDraft(next) {
  draft.value = next
}
</script>

<template>
  <section class="place-create-page">
    <Title tag="h1">{{ t('myPlaces.createPageTitle') }}</Title>
    <p class="place-create-page__muted">{{ t('myPlaces.createPageIntro') }}</p>

    <div class="place-create-page__toolbar">
      <button type="button" class="place-create-page__btn" @click="goBack">
        {{ t('myPlaces.backToPlaces') }}
      </button>
    </div>

    <Card class="place-create-page__panel">
      <PlaceBasicsForm
        mode="create"
        :model-value="draft"
        :save-error="saveError"
        :save-loading="saveLoading"
        :picker-key="pickerKey"
        @update:model-value="setDraft"
        @submit="onSubmit"
      />
    </Card>
  </section>
</template>

<style lang="scss" scoped>
.place-create-page {
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 52rem;
  margin: 0 auto;
}

.place-create-page__muted {
  opacity: 0.8;
  margin: 0;
}

.place-create-page__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.place-create-page__btn {
  cursor: pointer;
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
}

.place-create-page__panel {
  margin-top: 0.25rem;
}
</style>
