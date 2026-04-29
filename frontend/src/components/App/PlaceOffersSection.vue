<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import { useCommunity } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'
import { formatOfferPrice } from '../../utils/formatPrice'
import Title from '../../atoms/Title.vue'
import PlaceTagsField from '../../molecules/PlaceTagsField.vue'
import { deleteOffer, fetchAudiences, fetchOffers, updateOffer } from '../../services/placesApi.js'

const props = defineProps({
  placeId: {
    type: [Number, String],
    required: true,
  },
})

const emit = defineEmits(['changed'])
const router = useRouter()

const offers = ref([])
const audiences = ref([])
const error = ref('')
const editing = ref(null)
const form = ref({ title: '', description: '', price: '', tags: [], visibility_scope: 'public', audience_ids: [] })
const removeGallery = ref([])
const photoInput = ref(null)
const galleryInput = ref(null)
const editTagsRef = ref(null)

const { communityCurrencyCode } = useCommunity()

function formatPrice(amount) {
  return formatOfferPrice(amount, communityCurrencyCode.value)
}

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

function goToCreateOfferPage() {
  router.push({ name: 'placeOfferCreate', params: { placeId: String(props.placeId) } })
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
        @click="goToCreateOfferPage"
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
