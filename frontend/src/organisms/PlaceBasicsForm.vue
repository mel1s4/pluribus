<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import PlaceLocationPicker from './PlaceLocationPicker.vue'
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
      service_area_type: m.service_area_type,
      radius_meters: m.radius_meters,
      area_geojson: m.area_geojson,
    }
  },
  set(v) {
    patch({
      latitude: v.latitude,
      longitude: v.longitude,
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

    <div class="place-basics-form__logo">
      <span class="place-basics-form__label">{{ t('myPlaces.fieldPlaceLogo') }}</span>
      <div v-if="logoPreviewSrc" class="place-basics-form__logo-preview-wrap">
        <img
          :src="logoPreviewSrc"
          alt=""
          class="place-basics-form__logo-preview"
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
      <button
        v-if="logoPreviewSrc || modelValue.logoFile || (modelValue.logo_url && !modelValue.removeLogo)"
        type="button"
        class="place-basics-form__btn place-basics-form__btn--ghost"
        :disabled="saveLoading"
        @click="onClearLogo"
      >
        {{ t('myPlaces.removePlaceLogo') }}
      </button>
    </div>

    <PlaceLocationPicker
      v-if="mode === 'edit'"
      :key="pickerKey"
      v-model="locationModel"
    />

    <p v-if="saveError" class="place-basics-form__error">{{ saveError }}</p>

    <div class="place-basics-form__actions">
      <button
        type="submit"
        class="place-basics-form__btn place-basics-form__btn--primary"
        :disabled="saveLoading"
      >
        {{ mode === 'create' ? t('myPlaces.createSubmit') : t('myPlaces.savePlace') }}
      </button>
      <button
        v-if="mode === 'edit'"
        type="button"
        class="place-basics-form__btn place-basics-form__btn--danger"
        :disabled="saveLoading"
        @click="emit('delete')"
      >
        {{ t('myPlaces.deletePlace') }}
      </button>
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

.place-basics-form__btn {
  cursor: pointer;
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
}

.place-basics-form__btn--primary {
  border-color: var(--accent, #3b5bdb);
  background: var(--accent, #3b5bdb);
  color: #fff;
}

.place-basics-form__btn--danger {
  border-color: #c62828;
  color: #c62828;
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

.place-basics-form__logo-hint {
  margin: 0;
  font-size: 0.8rem;
  opacity: 0.75;
}

.place-basics-form__btn--ghost {
  align-self: flex-start;
}
</style>
