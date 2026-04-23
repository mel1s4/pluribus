<script setup>
import { ref, watch } from 'vue'
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'
import PlaceTagsField from '../../molecules/PlaceTagsField.vue'
import PlaceRequirementRecurrenceFields from '../../molecules/PlaceRequirementRecurrenceFields.vue'
import PlaceRequirementExampleOfferField from '../../molecules/PlaceRequirementExampleOfferField.vue'
import {
  createRequirement as apiCreateRequirement,
  deleteRequirement,
  deleteRequirementResponse,
  fetchAudiences,
  fetchRequirements,
  updateRequirement,
} from '../../services/placesApi.js'

const props = defineProps({
  placeId: {
    type: [Number, String],
    required: true,
  },
})

const emit = defineEmits(['changed'])

const rows = ref([])
const audiences = ref([])
const error = ref('')
const editing = ref(null)
const form = ref({
  title: '',
  description: '',
  quantity: '',
  unit: '',
  recurrence_mode: 'once',
  recurrence_weekdays: [],
  tags: [],
  example_place_offer_id: null,
  visibility_scope: 'public',
  audience_ids: [],
})
const removeGallery = ref([])
const photoInput = ref(null)
const galleryInput = ref(null)
const createPhoto = ref(null)
const createGallery = ref(null)
const editTagsRef = ref(null)
const createTagsRef = ref(null)

const createForm = ref({
  title: '',
  description: '',
  quantity: '',
  unit: '',
  recurrence_mode: 'once',
  recurrence_weekdays: [],
  tags: [],
  example_place_offer_id: null,
  visibility_scope: 'public',
  audience_ids: [],
})

function recurrenceSummary(r) {
  if (r.recurrence_mode === 'weekly' && Array.isArray(r.recurrence_weekdays) && r.recurrence_weekdays.length) {
    return t('myPlaces.requirementListWeekly').replace('{days}', r.recurrence_weekdays.join(', '))
  }
  return t('myPlaces.requirementListOnce')
}

async function load() {
  error.value = ''
  const { ok, status, data } = await fetchRequirements(props.placeId)
  if (!ok) {
    error.value = t('myPlaces.requirementsLoadError').replace('{status}', String(status))
    rows.value = []
    return
  }
  rows.value = Array.isArray(data?.data) ? data.data : []
  const audiencesResponse = await fetchAudiences(props.placeId)
  audiences.value = audiencesResponse.ok && Array.isArray(audiencesResponse.data?.data) ? audiencesResponse.data.data : []
}

watch(
  () => props.placeId,
  () => {
    editing.value = null
    removeGallery.value = []
    load()
  },
  { immediate: true },
)

function startEdit(r) {
  editing.value = r
  form.value = {
    title: r.title,
    description: r.description || '',
    quantity: String(r.quantity),
    unit: r.unit || '',
    recurrence_mode: r.recurrence_mode === 'weekly' ? 'weekly' : 'once',
    recurrence_weekdays: Array.isArray(r.recurrence_weekdays) ? [...r.recurrence_weekdays] : [],
    tags: Array.isArray(r.tags) ? [...r.tags] : [],
    example_place_offer_id: r.example_offer?.id ?? null,
    visibility_scope: r.visibility_scope || 'public',
    audience_ids: Array.isArray(r.audience_ids) ? [...r.audience_ids] : [],
  }
  removeGallery.value = []
}

function cancelEdit() {
  editing.value = null
  removeGallery.value = []
}

function requirementPayloadBase(f) {
  const mode = f.recurrence_mode === 'weekly' ? 'weekly' : 'once'
  const out = {
    title: f.title,
    description: f.description || null,
    quantity: Number(f.quantity),
    unit: f.unit.trim(),
    recurrence_mode: mode,
    tags: Array.isArray(f.tags) ? f.tags : [],
  }
  if (mode === 'weekly') {
    out.recurrence_weekdays = Array.isArray(f.recurrence_weekdays) ? f.recurrence_weekdays : []
  }
  if (f.example_place_offer_id != null && f.example_place_offer_id !== '') {
    out.example_place_offer_id = Number(f.example_place_offer_id)
  }
  out.visibility_scope = f.visibility_scope || 'public'
  out.audience_ids = Array.isArray(f.audience_ids) ? f.audience_ids : []
  return out
}

async function saveEdit() {
  if (!editing.value) return
  editTagsRef.value?.commit?.()
  const fd = new FormData()
  const base = requirementPayloadBase(form.value)
  fd.append('title', base.title)
  fd.append('description', base.description ?? '')
  fd.append('quantity', String(base.quantity))
  fd.append('unit', base.unit)
  fd.append('recurrence_mode', base.recurrence_mode)
  if (base.recurrence_mode === 'weekly') {
    fd.append('recurrence_weekdays', JSON.stringify(base.recurrence_weekdays ?? []))
  }
  fd.append('tags', JSON.stringify(base.tags ?? []))
  fd.append('visibility_scope', base.visibility_scope || 'public')
  fd.append('audience_ids', JSON.stringify(base.audience_ids ?? []))
  if (photoInput.value?.files?.[0]) {
    fd.append('photo', photoInput.value.files[0])
  }
  if (galleryInput.value?.files?.length) {
    for (let i = 0; i < galleryInput.value.files.length; i += 1) {
      fd.append('gallery[]', galleryInput.value.files[i])
    }
  }
  removeGallery.value.sort((a, b) => a - b).forEach((idx) => {
    fd.append('remove_gallery_indices[]', String(idx))
  })
  fd.append(
    'example_place_offer_id',
    form.value.example_place_offer_id != null && form.value.example_place_offer_id !== ''
      ? String(form.value.example_place_offer_id)
      : '',
  )
  const { ok, status } = await updateRequirement(props.placeId, editing.value.id, fd)
  if (!ok) {
    error.value = t('myPlaces.requirementSaveError').replace('{status}', String(status))
    return
  }
  cancelEdit()
  await load()
  emit('changed')
}

async function removeRow(r) {
  if (!window.confirm(t('myPlaces.deleteRequirementConfirm'))) return
  const { ok, status } = await deleteRequirement(props.placeId, r.id)
  if (!ok) {
    error.value = t('myPlaces.requirementDeleteError').replace('{status}', String(status))
    return
  }
  await load()
  emit('changed')
}

async function removeResponse(req, resp) {
  if (!window.confirm(t('myPlaces.deleteRequirementResponseConfirm'))) return
  const { ok, status } = await deleteRequirementResponse(props.placeId, req.id, resp.id)
  if (!ok) {
    error.value = t('myPlaces.requirementResponseDeleteError').replace('{status}', String(status))
    return
  }
  await load()
  emit('changed')
}

async function createRow() {
  createTagsRef.value?.commit?.()
  const hasFiles =
    (createPhoto.value?.files?.length > 0)
    || (createGallery.value?.files?.length > 0)
  const base = requirementPayloadBase(createForm.value)
  if (hasFiles) {
    const fd = new FormData()
    fd.append('title', base.title)
    fd.append('description', base.description ?? '')
    fd.append('quantity', String(base.quantity))
    fd.append('unit', base.unit)
    fd.append('recurrence_mode', base.recurrence_mode)
    if (base.recurrence_mode === 'weekly') {
      fd.append('recurrence_weekdays', JSON.stringify(base.recurrence_weekdays ?? []))
    }
    fd.append('tags', JSON.stringify(base.tags ?? []))
    fd.append('visibility_scope', base.visibility_scope || 'public')
    fd.append('audience_ids', JSON.stringify(base.audience_ids ?? []))
    if (createPhoto.value?.files?.[0]) {
      fd.append('photo', createPhoto.value.files[0])
    }
    if (createGallery.value?.files?.length) {
      for (let i = 0; i < createGallery.value.files.length; i += 1) {
        fd.append('gallery[]', createGallery.value.files[i])
      }
    }
    if (createForm.value.example_place_offer_id != null && createForm.value.example_place_offer_id !== '') {
      fd.append('example_place_offer_id', String(createForm.value.example_place_offer_id))
    }
    const { ok, status } = await apiCreateRequirement(props.placeId, fd)
    if (!ok) {
      error.value = t('myPlaces.requirementCreateError').replace('{status}', String(status))
      return
    }
  } else {
    const { ok, status } = await apiCreateRequirement(props.placeId, base)
    if (!ok) {
      error.value = t('myPlaces.requirementCreateError').replace('{status}', String(status))
      return
    }
  }
  createForm.value = {
    title: '',
    description: '',
    quantity: '',
    unit: '',
    recurrence_mode: 'once',
    recurrence_weekdays: [],
    tags: [],
    example_place_offer_id: null,
    visibility_scope: 'public',
    audience_ids: [],
  }
  if (createPhoto.value) createPhoto.value.value = ''
  if (createGallery.value) createGallery.value.value = ''
  await load()
  emit('changed')
}
</script>

<template>
  <section class="place-reqs">
    <Title tag="h3" class="place-reqs__title">{{ t('myPlaces.requirementsHeading') }}</Title>
    <p v-if="error" class="place-reqs__error">{{ error }}</p>

    <ul v-if="rows.length" class="place-reqs__list">
      <li
        v-for="r in rows"
        :key="r.id"
        class="place-reqs__item"
      >
        <div class="place-reqs__row">
          <span class="place-reqs__name">{{ r.title }}</span>
          <span class="place-reqs__qty">{{ r.quantity }} {{ r.unit }}</span>
          <span class="place-reqs__recur">{{ recurrenceSummary(r) }}</span>
          <span v-if="r.tags?.length" class="place-reqs__tags">{{ r.tags.join(', ') }}</span>
          <button
            type="button"
            class="place-reqs__btn"
            @click="startEdit(r)"
          >
            {{ t('myPlaces.edit') }}
          </button>
          <button
            type="button"
            class="place-reqs__btn place-reqs__btn--danger"
            @click="removeRow(r)"
          >
            {{ t('myPlaces.delete') }}
          </button>
        </div>
        <div v-if="r.offers_made?.length" class="place-reqs__made">
          <h4 class="place-reqs__madeTitle">{{ t('myPlaces.requirementOffersMade') }}</h4>
          <ul class="place-reqs__madeList">
            <li v-for="resp in r.offers_made" :key="resp.id" class="place-reqs__madeRow">
              <span class="place-reqs__madeName">{{ resp.title }}</span>
              <span class="place-reqs__madePrice">{{ resp.price }}</span>
              <span class="place-reqs__madeVis">{{ resp.visibility === 'community' ? t('myPlaces.requirementVisibilityCommunity') : t('myPlaces.requirementVisibilityCreator') }}</span>
              <button
                type="button"
                class="place-reqs__btn place-reqs__btn--danger"
                @click="removeResponse(r, resp)"
              >
                {{ t('myPlaces.delete') }}
              </button>
            </li>
          </ul>
        </div>
      </li>
    </ul>

    <form
      v-if="editing"
      class="place-reqs__form"
      @submit.prevent="saveEdit"
    >
      <label class="place-reqs__label">{{ t('myPlaces.requirementTitle') }}</label>
      <input
        v-model="form.title"
        class="place-reqs__input"
        type="text"
        required
      />
      <label class="place-reqs__label">{{ t('myPlaces.requirementDescription') }}</label>
      <textarea
        v-model="form.description"
        class="place-reqs__textarea"
        rows="2"
      />
      <label class="place-reqs__label">{{ t('myPlaces.requirementQuantity') }}</label>
      <input
        v-model="form.quantity"
        class="place-reqs__input"
        type="number"
        min="0"
        step="any"
        required
      />
      <label class="place-reqs__label">{{ t('myPlaces.requirementUnit') }}</label>
      <input
        v-model="form.unit"
        class="place-reqs__input"
        type="text"
        required
      />
      <PlaceRequirementRecurrenceFields
        :recurrence-mode="form.recurrence_mode"
        :weekdays="form.recurrence_weekdays"
        @update:recurrence-mode="form.recurrence_mode = $event"
        @update:weekdays="form.recurrence_weekdays = $event"
      />
      <PlaceRequirementExampleOfferField
        :model-value="form.example_place_offer_id"
        @update:model-value="form.example_place_offer_id = $event"
      />
      <PlaceTagsField
        ref="editTagsRef"
        :model-value="form.tags"
        :label="t('myPlaces.requirementTags')"
        :hint="t('myPlaces.requirementTagsHint')"
        @update:model-value="form.tags = $event"
      />
      <label class="place-reqs__label">{{ t('myPlaces.postVisibilityScope') }}</label>
      <select v-model="form.visibility_scope" class="place-reqs__input">
        <option value="public">{{ t('myPlaces.postVisibilityPublic') }}</option>
        <option value="audience">{{ t('myPlaces.postVisibilityAudience') }}</option>
      </select>
      <label v-if="form.visibility_scope === 'audience'" class="place-reqs__label">{{ t('myPlaces.postVisibilityAudiences') }}</label>
      <select
        v-if="form.visibility_scope === 'audience'"
        v-model="form.audience_ids"
        class="place-reqs__input"
        multiple
      >
        <option v-for="a in audiences" :key="a.id" :value="a.id">{{ a.name }}</option>
      </select>
      <label class="place-reqs__label">{{ t('myPlaces.requirementPhoto') }}</label>
      <input
        ref="photoInput"
        class="place-reqs__file"
        type="file"
        accept="image/*"
      />
      <label class="place-reqs__label">{{ t('myPlaces.requirementGallery') }}</label>
      <input
        ref="galleryInput"
        class="place-reqs__file"
        type="file"
        accept="image/*"
        multiple
      />
      <div v-if="editing.gallery_urls?.length" class="place-reqs__gallery">
        <p class="place-reqs__gallery-caption">{{ t('myPlaces.removeGalleryHint') }}</p>
        <div
          v-for="(url, idx) in editing.gallery_urls"
          :key="idx"
          class="place-reqs__thumb-wrap"
        >
          <img
            :src="url"
            alt=""
            class="place-reqs__thumb"
          />
          <label class="place-reqs__check">
            <input
              v-model="removeGallery"
              type="checkbox"
              :value="idx"
            />
            {{ t('myPlaces.remove') }}
          </label>
        </div>
      </div>
      <div class="place-reqs__actions">
        <button type="submit" class="place-reqs__btn place-reqs__btn--primary">
          {{ t('myPlaces.saveRequirement') }}
        </button>
        <button type="button" class="place-reqs__btn" @click="cancelEdit">
          {{ t('myPlaces.cancel') }}
        </button>
      </div>
    </form>

    <form v-else class="place-reqs__form" @submit.prevent="createRow">
      <Title tag="h4" class="place-reqs__subtitle">{{ t('myPlaces.newRequirement') }}</Title>
      <label class="place-reqs__label">{{ t('myPlaces.requirementTitle') }}</label>
      <input
        v-model="createForm.title"
        class="place-reqs__input"
        type="text"
        required
      />
      <label class="place-reqs__label">{{ t('myPlaces.requirementDescription') }}</label>
      <textarea
        v-model="createForm.description"
        class="place-reqs__textarea"
        rows="2"
      />
      <label class="place-reqs__label">{{ t('myPlaces.requirementQuantity') }}</label>
      <input
        v-model="createForm.quantity"
        class="place-reqs__input"
        type="number"
        min="0"
        step="any"
        required
      />
      <label class="place-reqs__label">{{ t('myPlaces.requirementUnit') }}</label>
      <input
        v-model="createForm.unit"
        class="place-reqs__input"
        type="text"
        required
      />
      <PlaceRequirementRecurrenceFields
        :recurrence-mode="createForm.recurrence_mode"
        :weekdays="createForm.recurrence_weekdays"
        @update:recurrence-mode="createForm.recurrence_mode = $event"
        @update:weekdays="createForm.recurrence_weekdays = $event"
      />
      <PlaceRequirementExampleOfferField
        :model-value="createForm.example_place_offer_id"
        @update:model-value="createForm.example_place_offer_id = $event"
      />
      <PlaceTagsField
        ref="createTagsRef"
        :model-value="createForm.tags"
        :label="t('myPlaces.requirementTags')"
        :hint="t('myPlaces.requirementTagsHint')"
        @update:model-value="createForm.tags = $event"
      />
      <label class="place-reqs__label">{{ t('myPlaces.postVisibilityScope') }}</label>
      <select v-model="createForm.visibility_scope" class="place-reqs__input">
        <option value="public">{{ t('myPlaces.postVisibilityPublic') }}</option>
        <option value="audience">{{ t('myPlaces.postVisibilityAudience') }}</option>
      </select>
      <label v-if="createForm.visibility_scope === 'audience'" class="place-reqs__label">{{ t('myPlaces.postVisibilityAudiences') }}</label>
      <select
        v-if="createForm.visibility_scope === 'audience'"
        v-model="createForm.audience_ids"
        class="place-reqs__input"
        multiple
      >
        <option v-for="a in audiences" :key="a.id" :value="a.id">{{ a.name }}</option>
      </select>
      <label class="place-reqs__label">{{ t('myPlaces.requirementPhoto') }}</label>
      <input
        ref="createPhoto"
        class="place-reqs__file"
        type="file"
        accept="image/*"
      />
      <label class="place-reqs__label">{{ t('myPlaces.requirementGallery') }}</label>
      <input
        ref="createGallery"
        class="place-reqs__file"
        type="file"
        accept="image/*"
        multiple
      />
      <button type="submit" class="place-reqs__btn place-reqs__btn--primary">
        {{ t('myPlaces.addRequirement') }}
      </button>
    </form>
  </section>
</template>

<style lang="scss" scoped>
.place-reqs {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border);
}

.place-reqs__title {
  font-size: 1.05rem;
}

.place-reqs__subtitle {
  font-size: 0.95rem;
  margin: 0 0 0.35rem;
}

.place-reqs__error {
  color: var(--danger, #b00020);
  margin: 0;
  font-size: 0.9rem;
}

.place-reqs__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.place-reqs__item {
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--border);
}

.place-reqs__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 0.75rem;
  padding: 0.35rem 0;
}

.place-reqs__made {
  margin-top: 0.35rem;
  padding: 0.5rem 0.65rem;
  border-radius: 6px;
  border: 1px dashed var(--border);
  background: var(--btn-bg, rgba(0, 0, 0, 0.02));
}

.place-reqs__madeTitle {
  margin: 0 0 0.35rem;
  font-size: 0.85rem;
  font-weight: 600;
}

.place-reqs__madeList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.place-reqs__madeRow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem 0.5rem;
  font-size: 0.85rem;
}

.place-reqs__madeName {
  flex: 1;
  min-width: 6rem;
}

.place-reqs__madePrice {
  font-variant-numeric: tabular-nums;
}

.place-reqs__madeVis {
  font-size: 0.75rem;
  opacity: 0.85;
}

.place-reqs__name {
  flex: 1;
  min-width: 8rem;
}

.place-reqs__qty {
  font-variant-numeric: tabular-nums;
}

.place-reqs__recur {
  font-size: 0.8rem;
  opacity: 0.85;
  max-width: 14rem;
}

.place-reqs__tags {
  font-size: 0.8rem;
  opacity: 0.85;
  max-width: 12rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.place-reqs__form {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  max-width: 28rem;
}

.place-reqs__label {
  font-size: 0.85rem;
  margin-top: 0.35rem;
}

.place-reqs__input,
.place-reqs__textarea,
.place-reqs__file {
  width: 100%;
  box-sizing: border-box;
}

.place-reqs__actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.place-reqs__btn {
  cursor: pointer;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
}

.place-reqs__btn--primary {
  border-color: var(--accent, #3b5bdb);
  background: var(--accent, #3b5bdb);
  color: #fff;
}

.place-reqs__btn--danger {
  border-color: #c62828;
  color: #c62828;
}

.place-reqs__gallery {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.place-reqs__gallery-caption {
  margin: 0;
  font-size: 0.8rem;
  opacity: 0.85;
}

.place-reqs__thumb-wrap {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.place-reqs__thumb {
  width: 48px;
  height: 48px;
  object-fit: cover;
  border-radius: 4px;
}

.place-reqs__check {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.85rem;
}
</style>
