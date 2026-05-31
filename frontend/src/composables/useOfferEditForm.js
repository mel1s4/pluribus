import { computed, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { communityLocalCurrencyCode } from './useCommunity'
import { t } from '../i18n/i18n'
import { fetchOffer, updateOffer } from '../services/placesApi.js'

const MAX_IMAGE_BYTES = 5 * 1024 * 1024
const MAX_GALLERY_FILES = 20

function emptyOfferForm() {
  return {
    title: '',
    description: '',
    price: '',
    local_price: '',
    tags: [],
    category: '',
    visibility_scope: 'public',
    audience_ids: [],
  }
}

function draftKey(placeId, offerId) {
  return `place-offer-edit-draft:${String(placeId)}:${String(offerId)}`
}

function normalizeAudienceIds(ids) {
  return Array.isArray(ids)
    ? ids.map((id) => Number(id)).filter((id) => Number.isFinite(id))
    : []
}

function stepForField(field) {
  if (
    field === 'title'
    || field === 'price'
    || field === 'local_price'
    || field === 'description'
    || field === 'tags'
    || field === 'category'
  ) {
    return 1
  }
  if (field === 'photo' || field === 'gallery') {
    return 2
  }
  if (field === 'visibility_scope' || field === 'audience_ids') {
    return 3
  }
  return 3
}

function cloneForm(form) {
  return {
    ...form,
    tags: Array.isArray(form.tags) ? [...form.tags] : [],
    category: typeof form.category === 'string' ? form.category : '',
    audience_ids: normalizeAudienceIds(form.audience_ids),
  }
}

function parseErrors(data) {
  if (!data || typeof data !== 'object') return {}
  if (!data.errors || typeof data.errors !== 'object') return {}
  return Object.entries(data.errors).reduce((acc, [field, messages]) => {
    acc[field] = Array.isArray(messages) ? messages.map(String) : [String(messages)]
    return acc
  }, {})
}

function validateOptionalPrice(value, fieldErrors, fieldKey) {
  if (value === '') return true
  const numeric = Number(value)
  if (!Number.isFinite(numeric) || numeric < 0) {
    fieldErrors[fieldKey] = [t('myPlaces.offerPriceInvalid')]
    return false
  }
  return true
}

export function useOfferEditForm(placeIdRef, offerIdRef) {
  const router = useRouter()
  const currentStep = ref(1)
  const form = ref(emptyOfferForm())
  const photoFile = ref(null)
  const galleryFiles = ref([])
  const isSubmitting = ref(false)
  const saveState = ref('')
  const formError = ref('')
  const fieldErrors = ref({})
  const hasAttemptedSubmit = ref(false)
  const initialized = ref(false)
  const isLoaded = ref(false)
  const initialSnapshot = ref(JSON.stringify(emptyOfferForm()))

  const localCurrencyConfigured = computed(
    () => typeof communityLocalCurrencyCode.value === 'string' && communityLocalCurrencyCode.value.trim() !== '',
  )

  const isDirty = computed(() => {
    return (
      JSON.stringify(cloneForm(form.value)) !== initialSnapshot.value
      || photoFile.value instanceof File
      || galleryFiles.value.length > 0
    )
  })

  const stepErrors = computed(() => {
    const errors = { 1: false, 2: false, 3: false }
    Object.keys(fieldErrors.value).forEach((field) => {
      errors[stepForField(field)] = true
    })
    return errors
  })

  function clearFieldError(field) {
    if (!fieldErrors.value[field]) return
    const next = { ...fieldErrors.value }
    delete next[field]
    fieldErrors.value = next
  }

  function markClean() {
    initialSnapshot.value = JSON.stringify(cloneForm(form.value))
  }

  function clearDraft() {
    if (typeof window === 'undefined') return
    window.localStorage.removeItem(draftKey(placeIdRef.value, offerIdRef.value))
  }

  function readDraft() {
    if (typeof window === 'undefined') return null
    try {
      const raw = window.localStorage.getItem(draftKey(placeIdRef.value, offerIdRef.value))
      if (!raw) return null
      const parsed = JSON.parse(raw)
      if (!parsed || typeof parsed !== 'object') return null
      return {
        ...emptyOfferForm(),
        ...parsed,
        tags: Array.isArray(parsed.tags) ? parsed.tags : [],
        category: typeof parsed.category === 'string' ? parsed.category : '',
        audience_ids: normalizeAudienceIds(parsed.audience_ids),
      }
    } catch {
      return null
    }
  }

  function writeDraft() {
    if (typeof window === 'undefined') return
    if (!initialized.value || !isLoaded.value) return
    try {
      const payload = cloneForm(form.value)
      window.localStorage.setItem(draftKey(placeIdRef.value, offerIdRef.value), JSON.stringify(payload))
      saveState.value = t('myPlaces.offerDraftSaved')
    } catch {
      saveState.value = t('myPlaces.offerDraftSaveFailed')
    }
  }

  function restoreDraftIfAvailable() {
    const draft = readDraft()
    if (!draft) return
    const accepted = window.confirm(t('myPlaces.offerDraftRestorePrompt'))
    if (!accepted) {
      clearDraft()
      return
    }
    form.value = draft
    saveState.value = t('myPlaces.offerDraftRestored')
  }

  function resetForm() {
    currentStep.value = 1
    form.value = emptyOfferForm()
    photoFile.value = null
    galleryFiles.value = []
    fieldErrors.value = {}
    formError.value = ''
    hasAttemptedSubmit.value = false
    saveState.value = ''
    clearDraft()
    markClean()
  }

  function validateBasics() {
    const next = { ...fieldErrors.value }
    delete next.title
    delete next.price
    delete next.local_price
    const trimmedTitle = form.value.title.trim()
    if (!trimmedTitle) {
      next.title = [t('myPlaces.offerTitleRequired')]
    }
    const priceOk = validateOptionalPrice(form.value.price, next, 'price')
    const localOk = validateOptionalPrice(form.value.local_price, next, 'local_price')
    if (localOk && form.value.local_price !== '' && !localCurrencyConfigured.value) {
      next.local_price = [t('myPlaces.offerLocalCurrencyNotConfigured')]
    }

    fieldErrors.value = next
    return !next.title && priceOk && localOk && !next.local_price
  }

  function validateMedia() {
    const next = { ...fieldErrors.value }
    delete next.photo
    delete next.gallery
    if (photoFile.value && !photoFile.value.type.startsWith('image/')) {
      next.photo = [t('myPlaces.offerImageTypeError')]
    } else if (photoFile.value && photoFile.value.size > MAX_IMAGE_BYTES) {
      next.photo = [t('myPlaces.offerImageSizeError')]
    }
    if (galleryFiles.value.length > MAX_GALLERY_FILES) {
      next.gallery = [t('myPlaces.offerGalleryCountError')]
    } else if (galleryFiles.value.some((file) => !file.type.startsWith('image/'))) {
      next.gallery = [t('myPlaces.offerImageTypeError')]
    } else if (galleryFiles.value.some((file) => file.size > MAX_IMAGE_BYTES)) {
      next.gallery = [t('myPlaces.offerImageSizeError')]
    }
    fieldErrors.value = next
    return !next.photo && !next.gallery
  }

  function validatePublish() {
    const next = { ...fieldErrors.value }
    delete next.audience_ids
    const isAudience = form.value.visibility_scope === 'audience'
    if (isAudience && normalizeAudienceIds(form.value.audience_ids).length === 0) {
      next.audience_ids = [t('myPlaces.offerAudienceRequired')]
    }
    fieldErrors.value = next
    return !next.audience_ids
  }

  function firstErrorStep() {
    const fields = Object.keys(fieldErrors.value)
    if (!fields.length) return null
    return fields.map(stepForField).sort((a, b) => a - b)[0]
  }

  function jumpToErrorStep() {
    const step = firstErrorStep()
    if (step) currentStep.value = step
  }

  function toFormData() {
    const fd = new FormData()
    fd.append('title', form.value.title.trim())
    fd.append('description', form.value.description?.trim() || '')
    fd.append('price', form.value.price === '' ? '' : String(form.value.price))
    fd.append('local_price', form.value.local_price === '' ? '' : String(form.value.local_price))
    fd.append('tags', JSON.stringify(Array.isArray(form.value.tags) ? form.value.tags : []))
    fd.append('category', typeof form.value.category === 'string' ? form.value.category.trim() : '')
    fd.append('visibility_scope', form.value.visibility_scope || 'public')
    fd.append('audience_ids', JSON.stringify(normalizeAudienceIds(form.value.audience_ids)))
    if (photoFile.value) {
      fd.append('photo', photoFile.value)
    }
    galleryFiles.value.forEach((file) => fd.append('gallery[]', file))
    return fd
  }

  function toJsonBody() {
    const cat = typeof form.value.category === 'string' ? form.value.category.trim() : ''
    return {
      title: form.value.title.trim(),
      description: form.value.description?.trim() || null,
      price: form.value.price !== '' ? Number(form.value.price) : null,
      local_price: form.value.local_price !== '' ? Number(form.value.local_price) : null,
      tags: Array.isArray(form.value.tags) ? form.value.tags : [],
      category: cat === '' ? null : cat,
      visibility_scope: form.value.visibility_scope || 'public',
      audience_ids: normalizeAudienceIds(form.value.audience_ids),
    }
  }

  async function goToStep(step, commitTags) {
    if (step < currentStep.value) {
      currentStep.value = step
      return true
    }
    commitTags?.()
    if (step >= 2 && !validateBasics()) {
      currentStep.value = 1
      return false
    }
    if (step >= 3 && !validateMedia()) {
      currentStep.value = 2
      return false
    }
    currentStep.value = step
    return true
  }

  async function submit(commitTags) {
    hasAttemptedSubmit.value = true
    formError.value = ''
    commitTags?.()
    const isValid = validateBasics() && validateMedia() && validatePublish()
    if (!isValid) {
      jumpToErrorStep()
      return false
    }
    isSubmitting.value = true
    const payload = photoFile.value || galleryFiles.value.length > 0
      ? toFormData()
      : toJsonBody()
    const { ok, status, data } = await updateOffer(placeIdRef.value, offerIdRef.value, payload)
    isSubmitting.value = false
    if (!ok) {
      const apiErrors = parseErrors(data)
      fieldErrors.value = Object.keys(apiErrors).length ? apiErrors : fieldErrors.value
      if (Object.keys(apiErrors).length) {
        jumpToErrorStep()
      }
      formError.value = (data && typeof data.message === 'string' && data.message)
        ? data.message
        : t('myPlaces.offerCreateError').replace('{status}', String(status))
      return false
    }
    resetForm()
    await router.push({ name: 'placeEdit', params: { placeId: String(placeIdRef.value), tab: 'offers' } })
    return true
  }

  watch(
    () => form.value.visibility_scope,
    (scope) => {
      if (scope !== 'audience' && form.value.audience_ids.length) {
        form.value.audience_ids = []
      }
    },
  )

  watch(
    () => cloneForm(form.value),
    () => {
      if (!initialized.value) return
      writeDraft()
    },
    { deep: true },
  )

  async function loadOffer() {
    if (!placeIdRef.value || !offerIdRef.value) return
    isLoaded.value = false
    const { ok, data } = await fetchOffer(placeIdRef.value, offerIdRef.value)
    if (ok && data?.offer) {
      const o = data.offer
      form.value = {
        title: o.title,
        description: o.description || '',
        price: o.price !== null && o.price !== undefined ? String(o.price) : '',
        local_price: o.local_price !== null && o.local_price !== undefined ? String(o.local_price) : '',
        tags: Array.isArray(o.tags) ? [...o.tags] : [],
        category: typeof o.category === 'string' ? o.category : '',
        visibility_scope: o.visibility_scope || 'public',
        audience_ids: Array.isArray(o.audience_ids) ? [...o.audience_ids] : [],
      }
      isLoaded.value = true
      markClean()
      restoreDraftIfAvailable()
    }
  }

  watch(
    () => [placeIdRef.value, offerIdRef.value],
    () => {
      form.value = emptyOfferForm()
      fieldErrors.value = {}
      formError.value = ''
      hasAttemptedSubmit.value = false
      currentStep.value = 1
      isLoaded.value = false
      loadOffer()
      initialized.value = true
    },
    { immediate: true },
  )

  onBeforeRouteLeave(() => {
    if (isSubmitting.value || !isDirty.value) return true
    return window.confirm(t('myPlaces.offerUnsavedLeaveConfirm'))
  })

  return {
    currentStep,
    fieldErrors,
    form,
    formError,
    galleryFiles,
    hasAttemptedSubmit,
    isDirty,
    isSubmitting,
    isLoaded,
    localCurrencyConfigured,
    photoFile,
    saveState,
    stepErrors,
    clearFieldError,
    goToStep,
    markClean,
    submit,
  }
}
