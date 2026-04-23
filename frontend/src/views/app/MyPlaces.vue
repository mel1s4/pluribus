<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Title from '../../atoms/Title.vue'
import { t } from '../../i18n/i18n'
import { fetchPlaces } from '../../services/placesApi.js'
import { placeApiErrorMessage } from '../../utils/placeForm.js'

const router = useRouter()
const places = ref([])
const listLoading = ref(false)
const listError = ref('')

function tagsPreview(p) {
  const tags = Array.isArray(p.tags) ? p.tags : []
  if (!tags.length) return ''
  return tags.slice(0, 4).join(', ') + (tags.length > 4 ? '…' : '')
}

function placeRoleLabel(p) {
  const r = p.viewer_place_role
  if (r === 'full_access') return t('myPlaces.listRoleFullAccess')
  if (r === 'editor') return t('myPlaces.listRoleEditor')
  return ''
}

async function loadList() {
  listError.value = ''
  listLoading.value = true
  const { ok, status, data } = await fetchPlaces()
  listLoading.value = false
  if (!ok) {
    listError.value = placeApiErrorMessage(data, status, t('myPlaces.loadError'))
    places.value = []
    return
  }
  places.value = Array.isArray(data?.data) ? data.data : []
}

function goCreate() {
  router.push({ name: 'placeCreate' })
}

function goEdit(id) {
  router.push({ name: 'placeEdit', params: { placeId: String(id) } })
}

onMounted(() => {
  loadList()
})
</script>

<template>
  <section class="page page--my-places">
    <Title tag="h1">{{ t('myPlaces.title') }}</Title>
    <p class="page__muted">{{ t('myPlaces.intro') }}</p>

    <p v-if="listError" class="my-places__error">{{ listError }}</p>

    <div class="my-places__toolbar">
      <button
        type="button"
        class="my-places__btn my-places__btn--primary"
        :disabled="listLoading"
        @click="goCreate"
      >
        {{ t('myPlaces.addPlace') }}
      </button>
    </div>

    <p v-if="listLoading" class="page__muted">{{ t('myPlaces.loading') }}</p>

    <ul v-else class="my-places__list">
      <li v-if="places.length === 0" class="page__muted">{{ t('myPlaces.empty') }}</li>
      <li
        v-for="p in places"
        :key="p.id"
        class="my-places__list-item"
      >
        <button
          type="button"
          class="my-places__link"
          @click="goEdit(p.id)"
        >
          <span class="my-places__link-name">{{ p.name }}</span>
          <span v-if="placeRoleLabel(p)" class="my-places__link-role">{{ placeRoleLabel(p) }}</span>
          <span v-if="tagsPreview(p)" class="my-places__link-meta">{{ tagsPreview(p) }}</span>
        </button>
      </li>
    </ul>
  </section>
</template>

<style lang="scss" scoped>
.page--my-places {
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-height: 0;
}

.page__muted {
  opacity: 0.8;
  margin: 0;
}

.my-places__error {
  color: var(--danger, #b00020);
  margin: 0;
  font-size: 0.9rem;
}

.my-places__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.my-places__btn {
  cursor: pointer;
  padding: 0.45rem 0.85rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
  font: inherit;
}

.my-places__btn--primary {
  border-color: var(--accent, #3b5bdb);
  background: var(--accent, #3b5bdb);
  color: #fff;
}

.my-places__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.my-places__list-item {
  margin: 0;
}

.my-places__link {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.15rem;
  width: 100%;
  text-align: left;
  padding: 0.5rem 0.65rem;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--bg);
  cursor: pointer;
  font: inherit;
  color: inherit;
}

.my-places__link:hover {
  border-color: var(--accent, #3b5bdb);
}

.my-places__link-name {
  font-weight: 600;
}

.my-places__link-role {
  font-size: 0.8rem;
  opacity: 0.85;
  font-weight: 500;
}

.my-places__link-meta {
  font-size: 0.8rem;
  opacity: 0.75;
}
</style>
