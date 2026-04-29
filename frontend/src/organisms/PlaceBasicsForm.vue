<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import Button from '../atoms/Button.vue'
import PlaceLocationPicker from './PlaceLocationPicker.vue'
import PlaceServiceScheduleEditor from '../molecules/PlaceServiceScheduleEditor.vue'
import PlaceTagsField from '../molecules/PlaceTagsField.vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: (v) => v === 'create' || v === 'edit',
  },
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
  pickerKey: {
    type: Number,
    default: 0,
  },
})

const emit = defineEmits(['update:modelValue', 'submit', 'delete'])

const tagsFieldRef = ref(null)

function patch(partial) {
  emit('update:modelValue', { ...props.modelValue, ...partial })
}

function commitTags() {
  tagsFieldRef.value?.commit?.()
}

function onSubmit() {
  commitTags()
  emit('submit')
}

const locationModel = computed({
  get() {
    const m = props.modelValue
    return {
      latitude: m.latitude,
      longitude: m.longitude,
      location_type: m.location_type || 'none',
      service_area_type: m.service_area_type,
      radius_meters: m.radius_meters,
      area_geojson: m.area_geojson,
    }
  },
  set(v) {
    patch({
      latitude: v.latitude,
      longitude: v.longitude,
      location_type: v.location_type || 'none',
      service_area_type: v.service_area_type,
      radius_meters: v.radius_meters ?? null,
      area_geojson: v.area_geojson ?? null,
    })
  },
})

const logoBlobUrl = ref('')

function revokeLogoBlob() {
  if (logoBlobUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(logoBlobUrl.value)
  }
  logoBlobUrl.value = ''
}

watch(
  () => props.modelValue.logoFile,
  (file) => {
    revokeLogoBlob()
    if (file instanceof File) {
      logoBlobUrl.value = URL.createObjectURL(file)
    }
  },
  { immediate: true },
)

onUnmounted(revokeLogoBlob)

const logoPreviewSrc = computed(() => {
  if (logoBlobUrl.value) {
    return logoBlobUrl.value
  }
  if (!props.modelValue.removeLogo && props.modelValue.logo_url) {
    return props.modelValue.logo_url
  }
  return ''
})

function onLogoFile(ev) {
  const file = ev.target.files?.[0]
  patch({ logoFile: file ?? null, removeLogo: false })
}

function onClearLogo() {
  patch({ logoFile: null, removeLogo: true })
}

function setSchedule(next) {
  patch({ service_schedule: next })
}

function onLogoBackgroundColorInput(ev) {
  const value = (ev.target).value.trim()
  patch({ logo_background_color: value || null })
}
</script>

<template>
  <form class="place-basics-form" @submit.prevent="onSubmit">
    <label class="place-basics-form__label">{{ t('myPlaces.fieldName') }}</label>
    <input
      :value="modelValue.name"
      class="place-basics-form__input"
      type="text"
      required
      maxlength="255"
      @input="patch({ name: ($event.target).value })"
    />

    <label class="place-basics-form__label" for="place-slug-input">{{ t('myPlaces.fieldSlug') }}</label>
    <input
      id="place-slug-input"
      :value="modelValue.slug ?? ''"
      class="place-basics-form__input"
      type="text"
      required
      maxlength="64"
      pattern="[a-z0-9]+(-[a-z0-9]+)*"
      autocomplete="off"
      spellcheck="false"
      :placeholder="t('myPlaces.fieldSlugPlaceholder')"
      :aria-describedby="'place-slug-hint'"
      @input="patch({ slug: ($event.target).value })"
    />
    <p id="place-slug-hint" class="place-basics-form__hint">{{ t('myPlaces.slugHint') }}</p>

    <label class="place-basics-form__checkLabel">
      <input
        type="checkbox"
        class="place-basics-form__check"
        :checked="Boolean(modelValue.is_public)"
        :disabled="saveLoading"
        @change="patch({ is_public: ($event.target).checked })"
      />
      <span>{{ t('myPlaces.fieldIsPublic') }}</span>
    </label>
    <p class="place-basics-form__hint">{{ t('myPlaces.isPublicHint') }}</p>

    <label class="place-basics-form__label">{{ t('myPlaces.fieldDescription') }}</label>
    <textarea
      :value="modelValue.description ?? ''"
      class="place-basics-form__textarea"
      rows="3"
      maxlength="10000"
      @input="patch({ description: ($event.target).value })"
    />

    <PlaceTagsField
      ref="tagsFieldRef"
      :model-value="modelValue.tags || []"
      :disabled="saveLoading"
      :label="t('myPlaces.fieldTags')"
      :hint="t('myPlaces.tagsHint')"
      @update:model-value="patch({ tags: $event })"
    />

    <PlaceServiceScheduleEditor
      :model-value="modelValue.service_schedule || {}"
      :disabled="saveLoading"
      @update:model-value="setSchedule"
    />

    <div class="place-basics-form__logo">
      <span class="place-basics-form__label">{{ t('myPlaces.fieldPlaceLogo') }}</span>
      <div v-if="logoPreviewSrc" class="place-basics-form__logo-preview-wrap">
        <img
          :src="logoPreviewSrc"
          alt=""
          class="place-basics-form__logo-preview"
          loading="lazy"
        />
      </div>
      <input
        class="place-basics-form__file"
        type="file"
        accept="image/*"
        :disabled="saveLoading"
        @change="onLogoFile"
      />
      <p class="place-basics-form__logo-hint">{{ t('myPlaces.placeLogoHint') }}</p>
      <label class="place-basics-form__label" for="place-logo-background-color">
        {{ t('myPlaces.fieldPlaceLogoBackgroundColor') }}
      </label>
      <input
        id="place-logo-background-color"
        class="place-basics-form__input place-basics-form__color-input"
        type="text"
        :value="modelValue.logo_background_color ?? ''"
        :placeholder="t('myPlaces.placeLogoBackgroundColorPlaceholder')"
        :disabled="saveLoading"
        @input="onLogoBackgroundColorInput"
      />
      <p class="place-basics-form__logo-hint">{{ t('myPlaces.placeLogoBackgroundColorHint') }}</p>
      <Button
        v-if="logoPreviewSrc || modelValue.logoFile || (modelValue.logo_url && !modelValue.removeLogo)"
        type="button"
        variant="ghost"
        size="sm"
        :disabled="saveLoading"
        @click="onClearLogo"
      >
        {{ t('myPlaces.removePlaceLogo') }}
      </Button>
    </div>

    <PlaceLocationPicker
      v-if="mode === 'edit'"
      :key="pickerKey"
      v-model="locationModel"
    />

    <p v-if="saveError" class="place-basics-form__error">{{ saveError }}</p>

    <div class="place-basics-form__actions">
      <Button
        type="submit"
        variant="primary"
        :disabled="saveLoading"
      >
        {{ mode === 'create' ? t('myPlaces.createSubmit') : t('myPlaces.savePlace') }}
      </Button>
      <Button
        v-if="mode === 'edit'"
        type="button"
        variant="danger"
        :disabled="saveLoading"
        @click="emit('delete')"
      >
        {{ t('myPlaces.deletePlace') }}
      </Button>
    </div>
  </form>
</template>

<style lang="scss" scoped>
.place-basics-form {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.place-basics-form__error {
  color: var(--danger, #b00020);
  margin: 0;
  font-size: 0.9rem;
}

.place-basics-form__label {
  font-size: 0.85rem;
}

.place-basics-form__hint {
  margin: -0.25rem 0 0;
  font-size: 0.8rem;
  color: var(--muted, #64748b);
}

.place-basics-form__checkLabel {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  font-size: 0.9rem;
  margin-top: 0.25rem;
  max-width: 28rem;
}

.place-basics-form__check {
  margin-top: 0.2rem;
  flex-shrink: 0;
}

.place-basics-form__input,
.place-basics-form__textarea {
  max-width: 28rem;
  width: 100%;
  box-sizing: border-box;
}

.place-basics-form__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.place-basics-form__logo {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-top: 0.25rem;
}

.place-basics-form__logo-preview-wrap {
  max-width: 8rem;
}

.place-basics-form__logo-preview {
  width: 100%;
  height: auto;
  border-radius: 8px;
  border: 1px solid var(--border);
  display: block;
}

.place-basics-form__file {
  max-width: 24rem;
  font-size: 0.85rem;
}

.place-basics-form__color-input {
  max-width: 12rem;
}

.place-basics-form__logo-hint {
  margin: 0;
  font-size: 0.8rem;
  opacity: 0.75;
}
</style>
