<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import { useHasVotingId } from '../../composables/useHasVotingId'
import { hasCapability } from '../../composables/useCapabilities'
import { sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { fetchSurveys } from '../../services/surveysApi'

const router = useRouter()
const surveys = ref([])
const loading = ref(false)
const error = ref('')
const communityScope = ref('')
const hasVotingId = useHasVotingId()

const memberships = computed(() => Array.isArray(sessionUser.value?.communities) ? sessionUser.value.communities : [])
const showCommunityScope = computed(() => memberships.value.length > 1)
const canCreate = computed(() => hasCapability('surveys.manage') && hasVotingId.value)

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

async function load() {
  loading.value = true
  error.value = ''
  const res = await fetchSurveys({
    community_id: communityScope.value || undefined,
  })
  loading.value = false
  if (!res.ok) {
    error.value = `HTTP ${res.status}`
    return
  }
  surveys.value = unwrapList(res.data)
}

function statusLabel(survey) {
  if (survey.is_open) return t('surveys.statusOpen')
  return t('surveys.statusClosed')
}

function modalityChips(survey) {
  const chips = []
  if (survey.allow_multiple) chips.push(t('surveys.chipMultiple'))
  if (survey.require_ranked) chips.push(t('surveys.chipRanked'))
  if (survey.allow_add_options) chips.push(t('surveys.chipAddOptions'))
  return chips
}

function goCreate() {
  router.push({ name: 'surveys-new' })
}

function goDetail(survey) {
  router.push({ name: 'surveys-detail', params: { id: String(survey.id) } })
}

onMounted(load)
onMounted(() => {
  const active = Number(sessionUser.value?.active_community_id || 0)
  if (active > 0) communityScope.value = String(active)
})
watch(communityScope, () => {
  load()
})
</script>

<template>
  <section class="page page--surveys">
    <header class="page__header page__header--row">
      <div>
        <PageToolbarTitle route-key="surveys">
          <Title tag="h1">{{ t('surveys.title') }}</Title>
        </PageToolbarTitle>
        <p class="page__muted">{{ t('surveys.intro') }}</p>
      </div>
      <Button
        v-if="canCreate"
        variant="primary"
        class="page__createBtn"
        @click="goCreate"
      >
        {{ t('surveys.add') }}
      </Button>
    </header>

    <p v-if="!hasVotingId" class="surveys-page__notice">{{ t('surveys.votingIdRequired') }}</p>

    <p v-if="loading" class="page__muted">{{ t('surveys.loading') }}</p>
    <label v-if="showCommunityScope" class="page__scope">
      <span>{{ t('communityScope.label') }}</span>
      <select v-model="communityScope">
        <option v-for="c in memberships" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
      </select>
    </label>
    <p v-if="error" class="page__error">{{ t('surveys.error').replace('{status}', error.replace('HTTP ', '')) }}</p>

    <p v-if="!loading && !error && surveys.length === 0" class="page__muted">{{ t('surveys.empty') }}</p>

    <ul class="surveys-list">
      <li v-for="survey in surveys" :key="survey.id" class="surveys-card">
        <button type="button" class="surveys-card__link" @click="goDetail(survey)">
          <strong class="surveys-card__title">{{ survey.title }}</strong>
          <div class="surveys-card__meta">
            <span class="surveys-card__chip" :class="{ 'surveys-card__chip--open': survey.is_open }">
              {{ statusLabel(survey) }}
            </span>
            <span class="surveys-card__chip surveys-card__chip--muted">
              {{ t('surveys.voteCount').replace('{count}', String(survey.total_votes ?? 0)) }}
            </span>
            <span
              v-for="chip in modalityChips(survey)"
              :key="chip"
              class="surveys-card__chip surveys-card__chip--modality"
            >
              {{ chip }}
            </span>
          </div>
          <p v-if="survey.description" class="surveys-card__description">{{ survey.description }}</p>
        </button>
      </li>
    </ul>
  </section>
</template>

<style scoped lang="scss">
.page--surveys {
  padding: 1rem;
  display: grid;
  gap: 0.8rem;
}

.page__header--row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}

.page__createBtn {
  flex-shrink: 0;
}

.surveys-page__notice {
  margin: 0;
  padding: 0.65rem 0.75rem;
  border-radius: 0.5rem;
  background: #fff8e6;
  border: 1px solid #e6c84d;
  color: #5c4a00;
}

.surveys-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.5rem;
}

.surveys-card {
  border: 1px solid #7abf8a;
  background: #f6fff7;
  border-radius: 0.6rem;
}

.surveys-card__link {
  display: grid;
  gap: 0.35rem;
  width: 100%;
  padding: 0.7rem;
  border: 0;
  background: transparent;
  text-align: left;
  cursor: pointer;
  font: inherit;
  color: inherit;
}

.surveys-card__title {
  font-size: 1rem;
}

.surveys-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.surveys-card__chip {
  font-size: 0.75rem;
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
  background: #e8ece9;
  color: #333;
}

.surveys-card__chip--open {
  background: #d4f0dc;
  color: #1a5c2e;
}

.surveys-card__chip--muted {
  background: #eef2ef;
}

.surveys-card__chip--modality {
  background: #e8f0ff;
  color: #1a3d6b;
}

.surveys-card__description {
  margin: 0;
  color: #444;
  font-size: 0.9rem;
}
</style>
