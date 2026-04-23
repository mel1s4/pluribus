<script setup>
import { ref, watch } from 'vue'
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'
import PlaceAudienceMemberPicker from '../../molecules/PlaceAudienceMemberPicker.vue'
import {
  createAudience,
  deleteAudience,
  fetchAudiences,
  fetchPlaceAudienceMembers,
  updateAudience,
} from '../../services/placesApi.js'

const props = defineProps({
  placeId: {
    type: [Number, String],
    required: true,
  },
})

const pickable = ref([])
const audiences = ref([])
const loadError = ref('')
const pickableLoading = ref(false)
const audiencesLoading = ref(false)
const saveError = ref('')

const editing = ref(null)
const formName = ref('')
const formUserIds = ref([])

const createName = ref('')
const createUserIds = ref([])

async function loadPickable() {
  pickableLoading.value = true
  const { ok, status, data } = await fetchPlaceAudienceMembers(props.placeId)
  pickableLoading.value = false
  if (!ok) {
    loadError.value = t('myPlaces.audienceMembersLoadError').replace('{status}', String(status))
    pickable.value = []
    return
  }
  pickable.value = Array.isArray(data?.data) ? data.data : []
}

async function loadAudiences() {
  audiencesLoading.value = true
  saveError.value = ''
  const { ok, status, data } = await fetchAudiences(props.placeId)
  audiencesLoading.value = false
  if (!ok) {
    loadError.value = t('myPlaces.audiencesLoadError').replace('{status}', String(status))
    audiences.value = []
    return
  }
  audiences.value = Array.isArray(data?.data) ? data.data : []
}

async function loadAll() {
  loadError.value = ''
  await Promise.all([loadPickable(), loadAudiences()])
}

watch(
  () => props.placeId,
  () => {
    editing.value = null
    formName.value = ''
    formUserIds.value = []
    createName.value = ''
    createUserIds.value = []
    loadAll()
  },
  { immediate: true },
)

function startEdit(a) {
  editing.value = a
  formName.value = a.name || ''
  const ids = Array.isArray(a.members)
    ? a.members.map((m) => Number(m.id)).filter(Number.isFinite)
    : []
  formUserIds.value = ids
}

function cancelEdit() {
  editing.value = null
  formName.value = ''
  formUserIds.value = []
}

async function saveEdit() {
  if (!editing.value) return
  saveError.value = ''
  const { ok, status, data } = await updateAudience(props.placeId, editing.value.id, {
    name: formName.value.trim(),
    user_ids: formUserIds.value,
  })
  if (!ok) {
    saveError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('myPlaces.audienceSaveError').replace('{status}', String(status))
    return
  }
  cancelEdit()
  await loadAudiences()
}

async function removeAudience(a) {
  if (!window.confirm(t('myPlaces.deleteAudienceConfirm'))) return
  saveError.value = ''
  const { ok, status } = await deleteAudience(props.placeId, a.id)
  if (!ok) {
    saveError.value = t('myPlaces.audienceDeleteError').replace('{status}', String(status))
    return
  }
  await loadAudiences()
}

async function submitCreate() {
  saveError.value = ''
  const name = createName.value.trim()
  if (!name) return
  const { ok, status, data } = await createAudience(props.placeId, {
    name,
    user_ids: createUserIds.value,
  })
  if (!ok) {
    saveError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('myPlaces.audienceCreateError').replace('{status}', String(status))
    return
  }
  createName.value = ''
  createUserIds.value = []
  await loadAudiences()
}
</script>

<template>
  <section class="place-audiences">
    <p class="place-audiences__intro">{{ t('myPlaces.audiencesIntro') }}</p>

    <p v-if="loadError" class="place-audiences__error" role="alert">{{ loadError }}</p>
    <p v-if="saveError" class="place-audiences__error" role="alert">{{ saveError }}</p>

    <p v-if="pickableLoading || audiencesLoading" class="place-audiences__muted">
      {{ t('myPlaces.audiencesLoading') }}
    </p>

    <template v-else>
      <Title tag="h3" class="place-audiences__heading">{{ t('myPlaces.audiencesListHeading') }}</Title>
      <ul v-if="audiences.length" class="place-audiences__list">
        <li
          v-for="a in audiences"
          :key="a.id"
          class="place-audiences__row"
        >
          <span class="place-audiences__row-name">{{ a.name }}</span>
          <span class="place-audiences__row-count">{{ (a.members?.length ?? 0) }} {{ t('myPlaces.audienceMemberCount') }}</span>
          <button
            type="button"
            class="place-audiences__btn"
            @click="startEdit(a)"
          >
            {{ t('myPlaces.edit') }}
          </button>
          <button
            type="button"
            class="place-audiences__btn place-audiences__btn--danger"
            @click="removeAudience(a)"
          >
            {{ t('myPlaces.delete') }}
          </button>
        </li>
      </ul>
      <p v-else class="place-audiences__muted">{{ t('myPlaces.audiencesEmpty') }}</p>

      <div v-if="editing" class="place-audiences__panel">
        <Title tag="h4" class="place-audiences__subheading">{{ t('myPlaces.editAudienceTitle') }}</Title>
        <label class="place-audiences__label">{{ t('myPlaces.audienceName') }}</label>
        <input
          v-model="formName"
          class="place-audiences__input"
          type="text"
          maxlength="255"
          required
        />
        <p class="place-audiences__picker-label">{{ t('myPlaces.audiencePickMembers') }}</p>
        <PlaceAudienceMemberPicker
          :members="pickable"
          :model-value="formUserIds"
          @update:model-value="formUserIds = $event"
        />
        <div class="place-audiences__actions">
          <button type="button" class="place-audiences__btn place-audiences__btn--primary" @click="saveEdit">
            {{ t('myPlaces.saveAudience') }}
          </button>
          <button type="button" class="place-audiences__btn" @click="cancelEdit">
            {{ t('myPlaces.cancel') }}
          </button>
        </div>
      </div>

      <div class="place-audiences__panel place-audiences__panel--create">
        <Title tag="h4" class="place-audiences__subheading">{{ t('myPlaces.newAudienceTitle') }}</Title>
        <label class="place-audiences__label">{{ t('myPlaces.audienceName') }}</label>
        <input
          v-model="createName"
          class="place-audiences__input"
          type="text"
          maxlength="255"
          :placeholder="t('myPlaces.audienceNamePlaceholder')"
        />
        <p class="place-audiences__picker-label">{{ t('myPlaces.audiencePickMembers') }}</p>
        <PlaceAudienceMemberPicker
          :members="pickable"
          :model-value="createUserIds"
          @update:model-value="createUserIds = $event"
        />
        <button
          type="button"
          class="place-audiences__btn place-audiences__btn--primary"
          @click="submitCreate"
        >
          {{ t('myPlaces.addAudience') }}
        </button>
      </div>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.place-audiences {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.place-audiences__intro {
  margin: 0;
  font-size: 0.9rem;
  opacity: 0.85;
  max-width: 40rem;
}

.place-audiences__muted {
  margin: 0;
  opacity: 0.8;
  font-size: 0.9rem;
}

.place-audiences__error {
  margin: 0;
  color: var(--danger, #b00020);
  font-size: 0.9rem;
}

.place-audiences__heading {
  font-size: 1.05rem;
  margin: 0.5rem 0 0;
}

.place-audiences__subheading {
  font-size: 0.95rem;
  margin: 0 0 0.35rem;
}

.place-audiences__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.place-audiences__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 0.75rem;
  padding: 0.4rem 0;
  border-bottom: 1px solid var(--border);
}

.place-audiences__row-name {
  font-weight: 600;
  flex: 1;
  min-width: 8rem;
}

.place-audiences__row-count {
  font-size: 0.85rem;
  opacity: 0.8;
}

.place-audiences__panel {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  max-width: 36rem;
}

.place-audiences__panel--create {
  margin-top: 1.5rem;
}

.place-audiences__label {
  font-size: 0.85rem;
}

.place-audiences__picker-label {
  margin: 0.5rem 0 0;
  font-size: 0.85rem;
}

.place-audiences__input {
  max-width: 24rem;
  width: 100%;
  box-sizing: border-box;
  padding: 0.35rem 0.5rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  font: inherit;
}

.place-audiences__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.place-audiences__btn {
  cursor: pointer;
  padding: 0.35rem 0.65rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
  font: inherit;
}

.place-audiences__btn--primary {
  border-color: var(--accent, #3b5bdb);
  background: var(--accent, #3b5bdb);
  color: #fff;
}

.place-audiences__btn--danger {
  border-color: #c62828;
  color: #c62828;
}
</style>
