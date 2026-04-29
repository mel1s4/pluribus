<script setup>
import { nextTick, ref, watch } from 'vue'
import Button from '../../atoms/Button.vue'
import { useCommunity } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'
import { formatOfferPrice } from '../../utils/formatPrice'
import Title from '../../atoms/Title.vue'
import PlaceTagsField from '../../molecules/PlaceTagsField.vue'
import {
  createOffer as apiCreateOffer,
  deleteOffer,
  fetchAudiences,
  fetchOffers,
  updateOffer,
} from '../../services/placesApi.js'

const props = defineProps({
  placeId: {
    type: [Number, String],
    required: true,
  },
})

const emit = defineEmits(['changed'])

const offers = ref([])
const audiences = ref([])
const error = ref('')
const editing = ref(null)
const form = ref({ title: '', description: '', price: '', tags: [], visibility_scope: 'public', audience_ids: [] })
const removeGallery = ref([])
const photoInput = ref(null)
const galleryInput = ref(null)
const createPhoto = ref(null)
const createGallery = ref(null)
const editTagsRef = ref(null)
const createTagsRef = ref(null)
const createDialogRef = ref(null)

const { communityCurrencyCode } = useCommunity()

function formatPrice(amount) {
  return formatOfferPrice(amount, communityCurrencyCode.value)
}

const createForm = ref({
  title: '',
  description: '',
  price: '',
  tags: [],
  visibility_scope: 'public',
  audience_ids: [],
})

async function load() {
  error.value = ''
  const { ok, status, data } = await fetchOffers(props.placeId)
  if (!ok) {
    error.value = t('myPlaces.offersLoadError').replace('{status}', String(status))
    offers.value = []
    return
  }
  offers.value = Array.isArray(data?.data) ? data.data : []
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

function startEdit(o) {
  editing.value = o
  form.value = {
    title: o.title,
    description: o.description || '',
    price: String(o.price),
    tags: Array.isArray(o.tags) ? [...o.tags] : [],
    visibility_scope: o.visibility_scope || 'public',
    audience_ids: Array.isArray(o.audience_ids) ? [...o.audience_ids] : [],
  }
  removeGallery.value = []
}

function cancelEdit() {
  editing.value = null
  removeGallery.value = []
}

async function saveEdit() {
  if (!editing.value) return
  editTagsRef.value?.commit?.()
  const fd = new FormData()
  fd.append('title', form.value.title)
  fd.append('description', form.value.description || '')
  fd.append('price', form.value.price)
  fd.append('tags', JSON.stringify(Array.isArray(form.value.tags) ? form.value.tags : []))
  fd.append('visibility_scope', form.value.visibility_scope || 'public')
  fd.append('audience_ids', JSON.stringify(Array.isArray(form.value.audience_ids) ? form.value.audience_ids : []))
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
  const { ok, status } = await updateOffer(props.placeId, editing.value.id, fd)
  if (!ok) {
    error.value = t('myPlaces.offerSaveError').replace('{status}', String(status))
    return
  }
  cancelEdit()
  await load()
  emit('changed')
}

async function removeOffer(o) {
  if (!window.confirm(t('myPlaces.deleteOfferConfirm'))) return
  const { ok, status } = await deleteOffer(props.placeId, o.id)
  if (!ok) {
    error.value = t('myPlaces.offerDeleteError').replace('{status}', String(status))
    return
  }
  await load()
  emit('changed')
}

function resetCreateOfferForm() {
  createForm.value = { title: '', description: '', price: '', tags: [], visibility_scope: 'public', audience_ids: [] }
  if (createPhoto.value) createPhoto.value.value = ''
  if (createGallery.value) createGallery.value.value = ''
}

function onCreateDialogBackdrop(e) {
  if (e.target === createDialogRef.value) createDialogRef.value?.close()
}

async function openCreateOfferDialog() {
  resetCreateOfferForm()
  await nextTick()
  createDialogRef.value?.showModal()
}

async function createOffer() {
  createTagsRef.value?.commit?.()
  const hasFiles =
    (createPhoto.value?.files?.length > 0)
    || (createGallery.value?.files?.length > 0)
  if (hasFiles) {
    const fd = new FormData()
    fd.append('title', createForm.value.title)
    fd.append('description', createForm.value.description || '')
    fd.append('price', createForm.value.price)
    fd.append('tags', JSON.stringify(Array.isArray(createForm.value.tags) ? createForm.value.tags : []))
    fd.append('visibility_scope', createForm.value.visibility_scope || 'public')
    fd.append('audience_ids', JSON.stringify(Array.isArray(createForm.value.audience_ids) ? createForm.value.audience_ids : []))
    if (createPhoto.value?.files?.[0]) {
      fd.append('photo', createPhoto.value.files[0])
    }
    if (createGallery.value?.files?.length) {
      for (let i = 0; i < createGallery.value.files.length; i += 1) {
        fd.append('gallery[]', createGallery.value.files[i])
      }
    }
    const { ok, status } = await apiCreateOffer(props.placeId, fd)
    if (!ok) {
      error.value = t('myPlaces.offerCreateError').replace('{status}', String(status))
      return
    }
  } else {
    const { ok, status } = await apiCreateOffer(props.placeId, {
      title: createForm.value.title,
      description: createForm.value.description || null,
      price: Number(createForm.value.price),
      tags: Array.isArray(createForm.value.tags) ? createForm.value.tags : [],
      visibility_scope: createForm.value.visibility_scope || 'public',
      audience_ids: Array.isArray(createForm.value.audience_ids) ? createForm.value.audience_ids : [],
    })
    if (!ok) {
      error.value = t('myPlaces.offerCreateError').replace('{status}', String(status))
      return
    }
  }
  resetCreateOfferForm()
  createDialogRef.value?.close()
  await load()
  emit('changed')
}
</script>

<template>
  <section class="place-offers">
    <div class="place-offers__head">
      <Title tag="h3" class="place-offers__title">{{ t('myPlaces.offersHeading') }}</Title>
      <Button
        v-if="!editing"
        type="button"
        variant="primary"
        size="sm"
        @click="openCreateOfferDialog"
      >
        {{ t('myPlaces.addOffer') }}
      </Button>
    </div>
    <p v-if="error" class="place-offers__error">{{ error }}</p>

    <ul v-if="offers.length" class="place-offers__list">
      <li
        v-for="o in offers"
        :key="o.id"
        class="place-offers__row"
      >
        <span class="place-offers__name">{{ o.title }}</span>
        <span class="place-offers__price">{{ formatPrice(o.price) }}</span>
        <span v-if="o.tags?.length" class="place-offers__tags">{{ o.tags.join(', ') }}</span>
        <Button
          type="button"
          variant="link"
          size="sm"
          @click="startEdit(o)"
        >
          {{ t('myPlaces.edit') }}
        </Button>
        <Button
          type="button"
          variant="danger"
          size="sm"
          @click="removeOffer(o)"
        >
          {{ t('myPlaces.delete') }}
        </Button>
      </li>
    </ul>

    <form
      v-if="editing"
      class="place-offers__form"
      @submit.prevent="saveEdit"
    >
      <label class="place-offers__label">{{ t('myPlaces.offerTitle') }}</label>
      <input
        v-model="form.title"
        class="place-offers__input"
        type="text"
        required
      />
      <label class="place-offers__label">{{ t('myPlaces.offerDescription') }}</label>
      <textarea
        v-model="form.description"
        class="place-offers__textarea"
        rows="2"
      />
      <label class="place-offers__label">{{ t('myPlaces.offerPrice') }}</label>
      <input
        v-model="form.price"
        class="place-offers__input"
        type="number"
        min="0"
        step="0.01"
        required
      />
      <PlaceTagsField
        ref="editTagsRef"
        :model-value="form.tags"
        :label="t('myPlaces.offerTags')"
        :hint="t('myPlaces.offerTagsHint')"
        @update:model-value="form.tags = $event"
      />
      <label class="place-offers__label">{{ t('myPlaces.postVisibilityScope') }}</label>
      <select v-model="form.visibility_scope" class="place-offers__input">
        <option value="public">{{ t('myPlaces.postVisibilityPublic') }}</option>
        <option value="audience">{{ t('myPlaces.postVisibilityAudience') }}</option>
      </select>
      <label v-if="form.visibility_scope === 'audience'" class="place-offers__label">{{ t('myPlaces.postVisibilityAudiences') }}</label>
      <select
        v-if="form.visibility_scope === 'audience'"
        v-model="form.audience_ids"
        class="place-offers__input"
        multiple
      >
        <option v-for="a in audiences" :key="a.id" :value="a.id">{{ a.name }}</option>
      </select>
      <label class="place-offers__label">{{ t('myPlaces.offerPhoto') }}</label>
      <input
        ref="photoInput"
        class="place-offers__file"
        type="file"
        accept="image/*"
      />
      <label class="place-offers__label">{{ t('myPlaces.offerGallery') }}</label>
      <input
        ref="galleryInput"
        class="place-offers__file"
        type="file"
        accept="image/*"
        multiple
      />
      <div v-if="editing.gallery_urls?.length" class="place-offers__gallery">
        <p class="place-offers__gallery-caption">{{ t('myPlaces.removeGalleryHint') }}</p>
        <div
          v-for="(url, idx) in editing.gallery_urls"
          :key="idx"
          class="place-offers__thumb-wrap"
        >
          <img
            :src="url"
            alt=""
            class="place-offers__thumb"
            loading="lazy"
          />
          <label class="place-offers__check">
            <input
              v-model="removeGallery"
              type="checkbox"
              :value="idx"
            />
            {{ t('myPlaces.remove') }}
          </label>
        </div>
      </div>
      <div class="place-offers__actions">
        <Button type="submit" variant="primary" size="sm">
          {{ t('myPlaces.saveOffer') }}
        </Button>
        <Button type="button" variant="ghost" size="sm" @click="cancelEdit">
          {{ t('myPlaces.cancel') }}
        </Button>
      </div>
    </form>

    <dialog
      v-if="!editing"
      ref="createDialogRef"
      class="place-offers__dialog"
      aria-labelledby="place-offers-create-title"
      @click="onCreateDialogBackdrop"
    >
      <div class="place-offers__dialogPanel" @click.stop>
        <h2 id="place-offers-create-title" class="place-offers__dialogTitle">
          {{ t('myPlaces.newOffer') }}
        </h2>
        <form class="place-offers__form place-offers__form--dialog" @submit.prevent="createOffer">
          <label class="place-offers__label">{{ t('myPlaces.offerTitle') }}</label>
          <input
            v-model="createForm.title"
            class="place-offers__input"
            type="text"
            required
          >
          <label class="place-offers__label">{{ t('myPlaces.offerDescription') }}</label>
          <textarea
            v-model="createForm.description"
            class="place-offers__textarea"
            rows="2"
          />
          <label class="place-offers__label">{{ t('myPlaces.offerPrice') }}</label>
          <input
            v-model="createForm.price"
            class="place-offers__input"
            type="number"
            min="0"
            step="0.01"
            required
          >
          <PlaceTagsField
            ref="createTagsRef"
            :model-value="createForm.tags"
            :label="t('myPlaces.offerTags')"
            :hint="t('myPlaces.offerTagsHint')"
            @update:model-value="createForm.tags = $event"
          />
          <label class="place-offers__label">{{ t('myPlaces.postVisibilityScope') }}</label>
          <select v-model="createForm.visibility_scope" class="place-offers__input">
            <option value="public">{{ t('myPlaces.postVisibilityPublic') }}</option>
            <option value="audience">{{ t('myPlaces.postVisibilityAudience') }}</option>
          </select>
          <label v-if="createForm.visibility_scope === 'audience'" class="place-offers__label">{{ t('myPlaces.postVisibilityAudiences') }}</label>
          <select
            v-if="createForm.visibility_scope === 'audience'"
            v-model="createForm.audience_ids"
            class="place-offers__input"
            multiple
          >
            <option v-for="a in audiences" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
          <label class="place-offers__label">{{ t('myPlaces.offerPhoto') }}</label>
          <input
            ref="createPhoto"
            class="place-offers__file"
            type="file"
            accept="image/*"
          >
          <label class="place-offers__label">{{ t('myPlaces.offerGallery') }}</label>
          <input
            ref="createGallery"
            class="place-offers__file"
            type="file"
            accept="image/*"
            multiple
          >
          <div class="place-offers__dialogActions">
            <Button type="button" variant="ghost" size="sm" @click="createDialogRef?.close()">
              {{ t('myPlaces.cancel') }}
            </Button>
            <Button type="submit" variant="primary" size="sm">
              {{ t('myPlaces.addOffer') }}
            </Button>
          </div>
        </form>
      </div>
    </dialog>
  </section>
</template>

<style lang="scss" scoped>
.place-offers {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border);
}

.place-offers__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.place-offers__title {
  font-size: 1.05rem;
}

.place-offers__subtitle {
  font-size: 0.95rem;
  margin: 0 0 0.35rem;
}

.place-offers__error {
  color: var(--danger, #b00020);
  margin: 0;
  font-size: 0.9rem;
}

.place-offers__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.place-offers__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 0.75rem;
  padding: 0.35rem 0;
  border-bottom: 1px solid var(--border);
}

.place-offers__name {
  flex: 1;
  min-width: 8rem;
}

.place-offers__price {
  font-variant-numeric: tabular-nums;
}

.place-offers__tags {
  font-size: 0.8rem;
  opacity: 0.85;
  max-width: 12rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.place-offers__form {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  max-width: 28rem;
}

.place-offers__form--dialog {
  max-width: none;
}

.place-offers__dialog {
  margin: auto;
  padding: 0;
  max-width: min(28rem, calc(100vw - 2rem));
  max-height: calc(100vh - 2rem);
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  background: var(--bg);
  color: var(--text);
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
}

.place-offers__dialog::backdrop {
  background: rgba(15, 23, 42, 0.45);
}

html[data-theme='dark'] .place-offers__dialog::backdrop {
  background: rgba(0, 0, 0, 0.55);
}

.place-offers__dialogPanel {
  padding: 1.25rem 1.25rem 1rem;
  max-height: calc(100vh - 3rem);
  overflow-y: auto;
}

.place-offers__dialogTitle {
  margin: 0 0 1rem;
  font-size: 1.1rem;
  font-weight: 700;
}

.place-offers__dialogActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-top: 0.5rem;
  padding-top: 0.75rem;
  border-top: 1px solid var(--border);
}

.place-offers__label {
  font-size: 0.85rem;
  margin-top: 0.35rem;
}

.place-offers__input,
.place-offers__textarea,
.place-offers__file {
  width: 100%;
  box-sizing: border-box;
}

.place-offers__actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.place-offers__gallery {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.place-offers__gallery-caption {
  margin: 0;
  font-size: 0.8rem;
  opacity: 0.85;
}

.place-offers__thumb-wrap {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.place-offers__thumb {
  width: 48px;
  height: 48px;
  object-fit: cover;
  border-radius: 4px;
}

.place-offers__check {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.85rem;
}
</style>
