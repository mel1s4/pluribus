<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import PlaceTagsField from '../../molecules/PlaceTagsField.vue'
import { useOfferCreateForm } from '../../composables/useOfferCreateForm'
import { t } from '../../i18n/i18n'
import { fetchAudiences } from '../../services/placesApi.js'

const route = useRoute()
const router = useRouter()
const tagsRef = ref(null)
const audiences = ref([])
const audiencesError = ref('')
const loadingAudiences = ref(false)

const placeId = computed(() => {
  const raw = route.params.placeId
  return typeof raw === 'string' ? raw : ''
})

const {
  currentStep,
  fieldErrors,
  form,
  formError,
  galleryFiles,
  hasAttemptedSubmit,
  isSubmitting,
  photoFile,
  saveState,
  stepErrors,
  clearFieldError,
  goToStep,
  submit,
} = useOfferCreateForm(placeId)

function goBack() {
  router.push({ name: 'placeEdit', params: { placeId: placeId.value, tab: 'offers' } })
}

function commitTags() {
  tagsRef.value?.commit?.()
}

function onPhotoChange(event) {
  photoFile.value = event.target?.files?.[0] ?? null
  clearFieldError('photo')
}

function onGalleryChange(event) {
  galleryFiles.value = Array.from(event.target?.files ?? [])
  clearFieldError('gallery')
}

async function onSubmit() {
  await submit(commitTags)
}

async function loadAudiences() {
  if (!placeId.value) return
  loadingAudiences.value = true
  audiencesError.value = ''
  const { ok, status, data } = await fetchAudiences(placeId.value)
  loadingAudiences.value = false
  if (!ok) {
    audiencesError.value = t('myPlaces.audiencesLoadError').replace('{status}', String(status))
    audiences.value = []
    return
  }
  audiences.value = Array.isArray(data?.data) ? data.data : []
}

watch(placeId, () => {
  loadAudiences()
}, { immediate: true })
</script>

<template>
  <section class="offer-create">
    <div class="offer-create__head">
      <button type="button" class="offer-create__back" @click="goBack">
        {{ t('myPlaces.backToPlaces') }}
      </button>
      <Title tag="h1">{{ t('myPlaces.addOfferPageTitle') }}</Title>
      <p class="offer-create__intro">{{ t('myPlaces.addOfferPageIntro') }}</p>
      <p v-if="saveState" class="offer-create__saved">{{ saveState }}</p>
    </div>

    <div class="offer-create__layout">
      <div class="offer-create__main">
        <nav class="offer-create__steps" aria-label="Offer steps">
          <button type="button" class="offer-create__step" :class="{ 'is-active': currentStep === 1, 'is-error': stepErrors[1] }" @click="goToStep(1, commitTags)">1. {{ t('myPlaces.offerStepBasics') }}</button>
          <button type="button" class="offer-create__step" :class="{ 'is-active': currentStep === 2, 'is-error': stepErrors[2] }" @click="goToStep(2, commitTags)">2. {{ t('myPlaces.offerStepMedia') }}</button>
          <button type="button" class="offer-create__step" :class="{ 'is-active': currentStep === 3, 'is-error': stepErrors[3] }" @click="goToStep(3, commitTags)">3. {{ t('myPlaces.offerStepPublish') }}</button>
        </nav>

        <form class="offer-create__panel" @submit.prevent="onSubmit">
          <template v-if="currentStep === 1">
            <label class="offer-create__label">{{ t('myPlaces.offerTitle') }}</label>
            <input v-model="form.title" class="offer-create__input" type="text" :placeholder="t('myPlaces.offerTitlePlaceholder')" required @input="clearFieldError('title')">
            <p v-if="fieldErrors.title?.length" class="offer-create__error">{{ fieldErrors.title[0] }}</p>

            <label class="offer-create__label">{{ t('myPlaces.offerPrice') }}</label>
            <input v-model="form.price" class="offer-create__input" type="number" inputmode="decimal" min="0" step="0.01" required @input="clearFieldError('price')">
            <p v-if="fieldErrors.price?.length" class="offer-create__error">{{ fieldErrors.price[0] }}</p>

            <label class="offer-create__label">{{ t('myPlaces.offerDescription') }}</label>
            <textarea
              v-model="form.description"
              class="offer-create__textarea"
              rows="4"
              :placeholder="t('myPlaces.offerDescriptionPlaceholder')"
              @input="clearFieldError('description')"
            />

            <PlaceTagsField
              ref="tagsRef"
              :model-value="form.tags"
              :label="t('myPlaces.offerTags')"
              :hint="t('myPlaces.offerTagsHint')"
              @update:model-value="form.tags = $event"
            />
          </template>

          <template v-else-if="currentStep === 2">
            <label class="offer-create__label">{{ t('myPlaces.offerPhoto') }}</label>
            <input class="offer-create__input" type="file" accept="image/*" @change="onPhotoChange">
            <p v-if="photoFile" class="offer-create__meta">{{ photoFile.name }}</p>
            <p v-if="fieldErrors.photo?.length" class="offer-create__error">{{ fieldErrors.photo[0] }}</p>

            <label class="offer-create__label">{{ t('myPlaces.offerGallery') }}</label>
            <input class="offer-create__input" type="file" accept="image/*" multiple @change="onGalleryChange">
            <p class="offer-create__meta">{{ t('myPlaces.offerGalleryHint') }}</p>
            <ul v-if="galleryFiles.length" class="offer-create__fileList">
              <li v-for="file in galleryFiles" :key="file.name + file.size">{{ file.name }}</li>
            </ul>
            <p v-if="fieldErrors.gallery?.length" class="offer-create__error">{{ fieldErrors.gallery[0] }}</p>
          </template>

          <template v-else>
            <label class="offer-create__label">{{ t('myPlaces.postVisibilityScope') }}</label>
            <select v-model="form.visibility_scope" class="offer-create__input" @change="clearFieldError('visibility_scope')">
              <option value="public">{{ t('myPlaces.postVisibilityPublic') }}</option>
              <option value="audience">{{ t('myPlaces.postVisibilityAudience') }}</option>
            </select>

            <template v-if="form.visibility_scope === 'audience'">
              <label class="offer-create__label">{{ t('myPlaces.postVisibilityAudiences') }}</label>
              <select v-model="form.audience_ids" class="offer-create__input" multiple @change="clearFieldError('audience_ids')">
                <option v-for="a in audiences" :key="a.id" :value="a.id">{{ a.name }}</option>
              </select>
              <p v-if="loadingAudiences" class="offer-create__meta">{{ t('myPlaces.audiencesLoading') }}</p>
              <p v-if="audiencesError" class="offer-create__error">{{ audiencesError }}</p>
              <p v-if="fieldErrors.audience_ids?.length" class="offer-create__error">{{ fieldErrors.audience_ids[0] }}</p>
            </template>
          </template>

          <p v-if="formError" class="offer-create__error">{{ formError }}</p>

          <footer class="offer-create__actions">
            <Button type="button" variant="ghost" :disabled="currentStep === 1 || isSubmitting" @click="goToStep(currentStep - 1, commitTags)">
              {{ t('myPlaces.previousStep') }}
            </Button>
            <Button v-if="currentStep < 3" type="button" variant="primary" :disabled="isSubmitting" @click="goToStep(currentStep + 1, commitTags)">
              {{ t('myPlaces.nextStep') }}
            </Button>
            <Button v-else type="submit" variant="primary" :disabled="isSubmitting">
              {{ isSubmitting ? t('myPlaces.offerSubmitting') : t('myPlaces.addOffer') }}
            </Button>
          </footer>
        </form>
      </div>

      <aside class="offer-create__preview">
        <h2 class="offer-create__previewTitle">{{ t('myPlaces.offerPreviewTitle') }}</h2>
        <p class="offer-create__previewName">{{ form.title || t('myPlaces.offerPreviewUntitled') }}</p>
        <p class="offer-create__previewText">{{ form.description || t('myPlaces.offerPreviewNoDescription') }}</p>
        <p class="offer-create__previewText">{{ t('myPlaces.offerPreviewPrice') }}: {{ form.price || '—' }}</p>
        <p class="offer-create__previewText">{{ t('myPlaces.postVisibilityScope') }}: {{ form.visibility_scope === 'audience' ? t('myPlaces.postVisibilityAudience') : t('myPlaces.postVisibilityPublic') }}</p>
        <p v-if="hasAttemptedSubmit && Object.keys(fieldErrors).length" class="offer-create__error">{{ t('myPlaces.offerFixErrorsHint') }}</p>
      </aside>
    </div>
  </section>
</template>

<style lang="scss" scoped>
.offer-create { max-width: 72rem; margin: 0 auto; padding: 1rem 1rem 5.5rem; }
.offer-create__head { display: grid; gap: 0.5rem; margin-bottom: 1rem; }
.offer-create__back { width: fit-content; cursor: pointer; padding: 0.4rem 0.75rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); font: inherit; }
.offer-create__intro, .offer-create__saved, .offer-create__meta { margin: 0; font-size: 0.9rem; opacity: 0.82; }
.offer-create__saved { color: var(--link); }
.offer-create__layout { display: grid; gap: 1rem; }
.offer-create__steps { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; }
.offer-create__step { border: 1px solid var(--border); background: var(--bg); color: var(--text); border-radius: 999px; padding: 0.4rem 0.75rem; cursor: pointer; }
.offer-create__step.is-active { border-color: var(--link); color: var(--link); }
.offer-create__step.is-error { border-color: var(--danger, #b00020); }
.offer-create__panel { border: 1px solid var(--border); border-radius: 12px; padding: 1rem; display: grid; gap: 0.4rem; background: var(--bg); }
.offer-create__label { font-size: 0.85rem; margin-top: 0.25rem; }
.offer-create__input, .offer-create__textarea { width: 100%; box-sizing: border-box; }
.offer-create__error { margin: 0; color: var(--danger, #b00020); font-size: 0.85rem; }
.offer-create__fileList { margin: 0; padding-left: 1rem; font-size: 0.85rem; }
.offer-create__actions { position: fixed; left: 0; right: 0; bottom: 0; display: flex; justify-content: flex-end; gap: 0.5rem; padding: 0.75rem 1rem; border-top: 1px solid var(--border); background: var(--bg); }
.offer-create__preview { border: 1px solid var(--border); border-radius: 12px; padding: 1rem; background: var(--btn-bg, rgba(0, 0, 0, 0.02)); display: grid; gap: 0.35rem; height: fit-content; }
.offer-create__previewTitle, .offer-create__previewName, .offer-create__previewText { margin: 0; }
.offer-create__previewTitle { font-size: 1rem; }
.offer-create__previewName { font-weight: 700; }
.offer-create__previewText { font-size: 0.9rem; opacity: 0.9; }

@media (min-width: 960px) {
  .offer-create { padding-bottom: 2rem; }
  .offer-create__layout { grid-template-columns: minmax(0, 1fr) 20rem; align-items: start; }
  .offer-create__actions { position: sticky; bottom: 0; padding: 0.75rem 0 0; border-top: 0; background: transparent; }
}
</style>
