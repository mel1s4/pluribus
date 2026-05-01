<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { t } from '../../i18n/i18n'
import { fetchCommunities } from '../../services/communityApi'

const route = useRoute()
const router = useRouter()

const rows = ref([])
const loading = ref(false)
const loadError = ref('')

const canManage = computed(() => hasCapability('communities.manage'))

const createLocation = computed(() => {
  const s = route.params.communitySlug
  if (typeof s === 'string' && s.trim() !== '') {
    return { name: 'communityCreateScoped', params: { communitySlug: s.trim() } }
  }
  return { name: 'communityCreate' }
})

function editLocation(row) {
  const id = String(row.id)
  const s = route.params.communitySlug
  if (typeof s === 'string' && s.trim() !== '') {
    return { name: 'communityEditScoped', params: { communitySlug: s.trim(), communityId: id } }
  }
  return { name: 'communityEdit', params: { communityId: id } }
}

function goCreate() {
  void router.push(createLocation.value)
}

function goEdit(row) {
  void router.push(editLocation(row))
}

async function loadRows() {
  loading.value = true
  loadError.value = ''
  const { ok, status, data } = await fetchCommunities()
  loading.value = false
  if (!ok) {
    loadError.value =
      (data && typeof data === 'object' && data.message && String(data.message))
      || t('communities.loadError').replace('{status}', String(status))
    rows.value = []
    return
  }
  rows.value = Array.isArray(data?.data) ? data.data : []
}

onMounted(() => {
  loadRows()
})
</script>

<template>
  <section class="communities-page">
    <PageToolbarTitle route-key="communities">
      <Title tag="h1">{{ t('communities.title') }}</Title>
    </PageToolbarTitle>
    <p class="communities-page__intro">{{ t('communities.intro') }}</p>

    <p v-if="loadError" class="communities-page__error" role="alert">{{ loadError }}</p>

    <div v-if="canManage" class="communities-page__toolbar">
      <Button type="button" @click="goCreate">{{ t('communities.createNewCta') }}</Button>
    </div>

    <div v-if="loading" class="communities-page__loading">
      {{ t('communities.loading') }}
    </div>

    <div v-else-if="rows.length === 0" class="communities-page__empty">
      {{ t('communities.empty') }}
    </div>

    <div v-else class="communities-page__table-wrap">
      <table class="communities-page__table">
        <thead>
          <tr>
            <th>{{ t('communities.colName') }}</th>
            <th>{{ t('communities.colSlug') }}</th>
            <th>{{ t('communities.colDescription') }}</th>
            <th v-if="canManage" class="communities-page__actions">{{ t('communities.colActions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td>{{ row.name }}</td>
            <td>{{ row.slug }}</td>
            <td>{{ row.description || '—' }}</td>
            <td v-if="canManage" class="communities-page__actions">
              <Button type="button" variant="secondary" size="sm" @click="goEdit(row)">
                {{ t('communities.edit') }}
              </Button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style lang="scss" scoped>
.communities-page {
  padding: 2rem;
  max-width: 1100px;
  margin: 0 auto;
}

.communities-page__intro,
.communities-page__loading,
.communities-page__empty {
  color: var(--muted, #6b7280);
}

.communities-page__toolbar {
  margin: 0.75rem 0 1rem;
}

.communities-page__error {
  color: #b91c1c;
}

.communities-page__table-wrap {
  overflow-x: auto;
  margin: 1rem 0 1.5rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
}

.communities-page__table {
  width: 100%;
  border-collapse: collapse;

  th,
  td {
    text-align: left;
    padding: 0.65rem 0.75rem;
    border-bottom: 1px solid var(--border);
    vertical-align: top;
  }

  th {
    font-weight: 600;
    background: var(--table-head, rgba(0, 0, 0, 0.03));
  }
}

.communities-page__actions {
  white-space: nowrap;
  text-align: right;
}
</style>
