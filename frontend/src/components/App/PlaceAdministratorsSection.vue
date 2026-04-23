<script setup>
import { computed, ref, watch } from 'vue'
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'
import {
  addPlaceAdministrator,
  fetchPlaceAdministrators,
  fetchPlaceAudienceMembers,
  removePlaceAdministrator,
  updatePlaceAdministrator,
} from '../../services/placesApi.js'

const props = defineProps({
  placeId: {
    type: [Number, String],
    required: true,
  },
  ownerUserId: {
    type: Number,
    required: true,
  },
})

const pickable = ref([])
const administrators = ref([])
const error = ref('')
const loading = ref(true)

const roleDraft = ref({})

const createUserId = ref('')
const createRole = ref('editor')

function initials(name) {
  if (typeof name !== 'string' || !name.trim()) {
    return '?'
  }
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase()
  }
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

const adminIdSet = computed(() => new Set(administrators.value.map((a) => Number(a.id))))

const addOptions = computed(() => {
  return pickable.value.filter((m) => {
    const id = Number(m.id)
    return id !== props.ownerUserId && !adminIdSet.value.has(id)
  })
})

async function loadAll() {
  error.value = ''
  loading.value = true
  const [memRes, admRes] = await Promise.all([
    fetchPlaceAudienceMembers(props.placeId),
    fetchPlaceAdministrators(props.placeId),
  ])
  loading.value = false
  if (!memRes.ok) {
    error.value = t('myPlaces.audienceMembersLoadError').replace('{status}', String(memRes.status))
    pickable.value = []
    administrators.value = []
    return
  }
  if (!admRes.ok) {
    error.value = t('myPlaces.administratorsLoadError').replace('{status}', String(admRes.status))
    pickable.value = []
    administrators.value = []
    return
  }
  pickable.value = Array.isArray(memRes.data?.data) ? memRes.data.data : []
  administrators.value = Array.isArray(admRes.data?.data) ? admRes.data.data : []
  const next = {}
  for (const a of administrators.value) {
    next[a.id] = a.role
  }
  roleDraft.value = next
}

watch(
  () => props.placeId,
  () => {
    createUserId.value = ''
    createRole.value = 'editor'
    loadAll()
  },
  { immediate: true },
)

async function saveRole(userId) {
  error.value = ''
  const role = roleDraft.value[userId]
  const { ok, status, data } = await updatePlaceAdministrator(props.placeId, userId, { role })
  if (!ok) {
    error.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('myPlaces.adminRoleSaveError').replace('{status}', String(status))
    return
  }
  await loadAll()
}

async function removeAdmin(row) {
  if (!window.confirm(t('myPlaces.removeAdministratorConfirm'))) return
  error.value = ''
  const { ok, status } = await removePlaceAdministrator(props.placeId, row.id)
  if (!ok) {
    error.value = t('myPlaces.adminRemoveError').replace('{status}', String(status))
    return
  }
  await loadAll()
}

async function submitAdd() {
  if (!createUserId.value) return
  error.value = ''
  const { ok, status, data } = await addPlaceAdministrator(props.placeId, {
    user_id: Number(createUserId.value),
    role: createRole.value,
  })
  if (!ok) {
    error.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('myPlaces.adminAddError').replace('{status}', String(status))
    return
  }
  createUserId.value = ''
  createRole.value = 'editor'
  await loadAll()
}
</script>

<template>
  <section class="place-administrators">
    <p class="place-administrators__intro">{{ t('myPlaces.administratorsIntro') }}</p>

    <p v-if="error" class="place-administrators__error" role="alert">{{ error }}</p>
    <p v-if="loading" class="place-administrators__muted">{{ t('myPlaces.administratorsLoading') }}</p>

    <template v-else>
      <Title tag="h3" class="place-administrators__heading">{{ t('myPlaces.administratorsListHeading') }}</Title>
      <ul v-if="administrators.length" class="place-administrators__list">
        <li
          v-for="a in administrators"
          :key="a.id"
          class="place-administrators__row"
        >
          <div class="place-administrators__person">
            <span class="place-administrators__avatar-wrap">
              <img
                v-if="a.avatar_url"
                :src="a.avatar_url"
                alt=""
                class="place-administrators__avatar-img"
              />
              <span v-else class="place-administrators__avatar-fallback">{{ initials(a.name) }}</span>
            </span>
            <span class="place-administrators__name">{{ a.name }}</span>
          </div>
          <select
            v-model="roleDraft[a.id]"
            class="place-administrators__select"
          >
            <option value="full_access">{{ t('myPlaces.adminRoleFullAccess') }}</option>
            <option value="editor">{{ t('myPlaces.adminRoleEditor') }}</option>
          </select>
          <button
            type="button"
            class="place-administrators__btn"
            @click="saveRole(a.id)"
          >
            {{ t('myPlaces.saveAdminRole') }}
          </button>
          <button
            type="button"
            class="place-administrators__btn place-administrators__btn--danger"
            @click="removeAdmin(a)"
          >
            {{ t('myPlaces.removeAdministrator') }}
          </button>
        </li>
      </ul>
      <p v-else class="place-administrators__muted">{{ t('myPlaces.administratorsEmpty') }}</p>

      <div class="place-administrators__add">
        <Title tag="h4" class="place-administrators__subheading">{{ t('myPlaces.addAdministratorHeading') }}</Title>
        <label class="place-administrators__label">{{ t('myPlaces.addAdministratorMember') }}</label>
        <select
          v-model="createUserId"
          class="place-administrators__select place-administrators__select--wide"
        >
          <option value="">{{ t('myPlaces.addAdministratorPick') }}</option>
          <option
            v-for="m in addOptions"
            :key="m.id"
            :value="String(m.id)"
          >
            {{ m.name }}
          </option>
        </select>
        <label class="place-administrators__label">{{ t('myPlaces.addAdministratorRole') }}</label>
        <select v-model="createRole" class="place-administrators__select">
          <option value="full_access">{{ t('myPlaces.adminRoleFullAccess') }}</option>
          <option value="editor">{{ t('myPlaces.adminRoleEditor') }}</option>
        </select>
        <button
          type="button"
          class="place-administrators__btn place-administrators__btn--primary"
          :disabled="!createUserId"
          @click="submitAdd"
        >
          {{ t('myPlaces.addAdministratorSubmit') }}
        </button>
      </div>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.place-administrators {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.place-administrators__intro {
  margin: 0;
  font-size: 0.9rem;
  opacity: 0.85;
  max-width: 42rem;
}

.place-administrators__muted {
  margin: 0;
  opacity: 0.8;
  font-size: 0.9rem;
}

.place-administrators__error {
  margin: 0;
  color: var(--danger, #b00020);
  font-size: 0.9rem;
}

.place-administrators__heading {
  font-size: 1.05rem;
  margin: 0.5rem 0 0;
}

.place-administrators__subheading {
  font-size: 0.95rem;
  margin: 0 0 0.35rem;
}

.place-administrators__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.place-administrators__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 0.75rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border);
}

.place-administrators__person {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
  min-width: 10rem;
}

.place-administrators__avatar-wrap {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--btn-bg, #e2e8f0);
}

.place-administrators__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.place-administrators__avatar-fallback {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--text-muted, #64748b);
}

.place-administrators__name {
  font-weight: 600;
}

.place-administrators__select {
  padding: 0.3rem 0.45rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  font: inherit;
}

.place-administrators__select--wide {
  max-width: 22rem;
  width: 100%;
}

.place-administrators__btn {
  cursor: pointer;
  padding: 0.3rem 0.55rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
  font: inherit;
}

.place-administrators__btn--primary {
  border-color: var(--accent, #3b5bdb);
  background: var(--accent, #3b5bdb);
  color: #fff;
}

.place-administrators__btn--danger {
  border-color: #c62828;
  color: #c62828;
}

.place-administrators__add {
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  max-width: 28rem;
}

.place-administrators__label {
  font-size: 0.85rem;
  margin-top: 0.35rem;
}
</style>
