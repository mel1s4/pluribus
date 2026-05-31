<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import { t } from '../../i18n/i18n'
import { fetchMyCommunities } from '../../services/communityApi'

const router = useRouter()
const loading = ref(false)
const error = ref('')
const rows = ref([])

const showCommunityScopeTips = computed(() => rows.value.length > 1)

async function loadRows() {
  loading.value = true
  error.value = ''
  const { ok, status, data } = await fetchMyCommunities()
  loading.value = false
  if (!ok) {
    error.value = t('myCommunities.loadError').replace('{status}', String(status))
    rows.value = []
    return
  }
  rows.value = Array.isArray(data?.data) ? data.data : []
}

function goToCommunity(row) {
  router.push(`/community/${row.slug}/dashboard`)
}

onMounted(loadRows)
</script>

<template>
  <section class="my-communities">
    <PageToolbarTitle route-key="my-communities">
      <Title tag="h1">{{ t('myCommunities.title') }}</Title>
    </PageToolbarTitle>
    <p class="my-communities__intro">{{ t('myCommunities.intro') }}</p>
    <p v-if="showCommunityScopeTips" class="my-communities__tip">{{ t('myCommunities.scopeTip') }}</p>
    <p v-if="error" class="my-communities__error">{{ error }}</p>
    <p v-if="loading" class="my-communities__muted">{{ t('myCommunities.loading') }}</p>
    <p v-else-if="rows.length === 0" class="my-communities__muted">{{ t('myCommunities.empty') }}</p>
    <ul v-else class="my-communities__list">
      <li v-for="row in rows" :key="row.id" class="my-communities__item">
        <div>
          <strong>{{ row.name }}</strong>
          <p class="my-communities__meta">{{ t('myCommunities.role') }}: {{ row.role }}</p>
        </div>
        <div class="my-communities__actions">
          <RouterLink class="my-communities__hub" :to="`/community/${row.slug}`">
            {{ t('myCommunities.hubPage') }}
          </RouterLink>
          <Button type="button" size="sm" @click="goToCommunity(row)">
            {{ t('myCommunities.open') }}
          </Button>
        </div>
      </li>
    </ul>
  </section>
</template>

<style lang="scss" scoped>
.my-communities {
  max-width: 900px;
  margin: 0 auto;
  padding: 2rem;
}
.my-communities__intro,
.my-communities__muted,
.my-communities__tip,
.my-communities__meta { color: var(--muted, #6b7280); }
.my-communities__error { color: #b91c1c; }
.my-communities__list { list-style: none; padding: 0; display: grid; gap: 0.75rem; }
.my-communities__item {
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  padding: 0.75rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.my-communities__actions {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}
.my-communities__hub {
  font-size: 0.88rem;
  color: var(--link, #2563eb);
  text-decoration: none;
}
.my-communities__hub:hover {
  text-decoration: underline;
}
</style>
