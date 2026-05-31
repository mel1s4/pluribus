<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import { t } from '../../i18n/i18n'
import { fetchMyProjects } from '../../services/projectsApi.js'

const loading = ref(true)
const loadError = ref('')
const rows = ref([])

async function load() {
  loading.value = true
  loadError.value = ''
  const { ok, status, data } = await fetchMyProjects({ per_page: 100 })
  loading.value = false
  if (!ok) {
    loadError.value = t('communityProjects.myLoadError').replace('{status}', String(status))
    rows.value = []
    return
  }
  rows.value = Array.isArray(data?.data) ? data.data : []
}

onMounted(load)

const sortedRows = computed(() =>
  [...rows.value].sort((a, b) => {
    const ta = new Date(b.updated_at || b.created_at || 0).getTime()
    const tb = new Date(a.updated_at || a.created_at || 0).getTime()
    return ta - tb
  }),
)
</script>

<template>
  <section class="my-projects-page">
    <PageToolbarTitle route-key="my-projects">
      <Title tag="h1">{{ t('communityProjects.myProjectsTitle') }}</Title>
    </PageToolbarTitle>
    <p class="my-projects-page__intro">{{ t('communityProjects.myIntro') }}</p>

    <p v-if="loadError" class="my-projects-page__error" role="alert">{{ loadError }}</p>
    <p v-else-if="loading" class="my-projects-page__muted">{{ t('communityProjects.loading') }}</p>
    <ul v-else class="my-projects-page__list" role="list">
      <li v-for="row in sortedRows" :key="row.id" class="my-projects-page__item">
        <div class="my-projects-page__badges">
          <span v-if="row.is_proposer" class="my-projects-page__badge">{{ t('communityProjects.badgeProposed') }}</span>
          <span v-if="row.has_argued" class="my-projects-page__badge my-projects-page__badge--muted">{{
            t('communityProjects.badgeParticipated')
          }}</span>
        </div>
        <RouterLink
          v-if="row.community && row.community.slug"
          class="my-projects-page__link"
          :to="{ name: 'communityProjectDetail', params: { slug: row.community.slug, projectId: String(row.id) } }"
        >
          <span class="my-projects-page__community">{{ row.community.name }}</span>
          <span class="my-projects-page__title">{{ row.title }}</span>
          <span v-if="parseFloat(String(row.budget_sum || '0')) > 0" class="my-projects-page__budget">{{
            t('communityProjects.listBudgetTotal').replace('{amount}', String(row.budget_sum))
          }}</span>
        </RouterLink>
      </li>
    </ul>
    <p v-if="!loading && !loadError && !sortedRows.length" class="my-projects-page__muted">{{ t('communityProjects.myEmpty') }}</p>
  </section>
</template>

<style lang="scss" scoped>
.my-projects-page {
  max-width: 44rem;
  margin: 0 auto;
  padding: 1.5rem 1rem 2rem;
}
.my-projects-page__intro {
  margin: 0 0 1rem;
  color: var(--muted, #6b7280);
  line-height: 1.45;
}
.my-projects-page__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}
.my-projects-page__item {
  margin: 0;
  padding: 0.65rem 0.75rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
}
.my-projects-page__badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-bottom: 0.35rem;
}
.my-projects-page__badge {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 0.12rem 0.4rem;
  border-radius: 0.25rem;
  background: color-mix(in srgb, #1d4ed8 12%, var(--bg));
  color: #1e3a8a;
}
.my-projects-page__badge--muted {
  background: color-mix(in srgb, var(--border) 50%, var(--bg));
  color: inherit;
}
.my-projects-page__link {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  text-decoration: none;
  color: inherit;
  &:hover .my-projects-page__title {
    text-decoration: underline;
  }
}
.my-projects-page__community {
  font-size: 0.82rem;
  opacity: 0.75;
}
.my-projects-page__title {
  font-weight: 600;
}
.my-projects-page__budget {
  font-size: 0.82rem;
  opacity: 0.85;
}
.my-projects-page__muted {
  color: var(--muted, #6b7280);
}
.my-projects-page__error {
  color: #b91c1c;
}
</style>
