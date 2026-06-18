<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import SurveyVoteForm from '../../components/organisms/SurveyVoteForm.vue'
import { useHasVotingId } from '../../composables/useHasVotingId'
import { t } from '../../i18n/i18n'
import {
  castSurveyVote,
  deleteSurvey,
  fetchSurvey,
  updateSurvey,
} from '../../services/surveysApi'

const route = useRoute()
const router = useRouter()
const hasVotingId = useHasVotingId()

const surveyId = computed(() => String(route.params.id || '').trim())
const survey = ref(null)
const loading = ref(false)
const error = ref('')
const voteBusy = ref(false)
const deleteDialogRef = ref(null)

const canVote = computed(() => Boolean(survey.value?.can_vote))
const canManage = computed(() => Boolean(survey.value?.can_manage))
const showVoteForm = computed(() => canVote.value && hasVotingId.value)

async function load() {
  if (!surveyId.value) return
  loading.value = true
  error.value = ''
  const res = await fetchSurvey(surveyId.value)
  loading.value = false
  if (!res.ok) {
    error.value = t('surveys.detailLoadError').replace('{status}', String(res.status))
    return
  }
  survey.value = res.data?.survey ?? null
}

async function submitVote(selections) {
  if (!survey.value) return
  voteBusy.value = true
  const res = await castSurveyVote(survey.value.id, { selections })
  voteBusy.value = false
  if (!res.ok) {
    error.value = t('surveys.voteError').replace('{status}', String(res.status))
    return
  }
  survey.value = res.data?.survey ?? survey.value
  error.value = ''
}

async function closeSurvey() {
  if (!survey.value) return
  const res = await updateSurvey(survey.value.id, { status: 'closed' })
  if (!res.ok) {
    error.value = t('surveys.closeError').replace('{status}', String(res.status))
    return
  }
  await load()
}

function goEdit() {
  router.push({ name: 'surveys-edit', params: { id: surveyId.value } })
}

function openDeleteDialog() {
  deleteDialogRef.value?.showModal()
}

function closeDeleteDialog() {
  deleteDialogRef.value?.close()
}

function onDeleteBackdrop(e) {
  if (e.target === deleteDialogRef.value) closeDeleteDialog()
}

async function confirmDelete() {
  if (!survey.value) return
  const res = await deleteSurvey(survey.value.id)
  closeDeleteDialog()
  if (!res.ok) {
    error.value = t('surveys.deleteError').replace('{status}', String(res.status))
    return
  }
  router.push({ name: 'surveys' })
}

function statusLabel(item) {
  return item?.is_open ? t('surveys.statusOpen') : t('surveys.statusClosed')
}

function resultMeta(option) {
  let text = `${option.vote_count} (${option.vote_percent}%)`
  if (survey.value?.require_ranked && option.average_rank != null) {
    text += ` · ${t('surveys.avgRank').replace('{rank}', String(option.average_rank))}`
  }
  return text
}

onMounted(load)
</script>

<template>
  <section class="survey-detail">
    <p v-if="loading" class="survey-detail__muted">{{ t('surveys.detailLoading') }}</p>
    <p v-else-if="error && !survey" class="survey-detail__error">{{ error }}</p>

    <template v-else-if="survey">
      <header class="survey-detail__header">
        <PageToolbarTitle route-key="surveys">
          <Title tag="h1">{{ survey.title }}</Title>
        </PageToolbarTitle>
        <span class="survey-detail__chip" :class="{ 'survey-detail__chip--open': survey.is_open }">
          {{ statusLabel(survey) }}
        </span>
      </header>

      <p v-if="survey.description" class="survey-detail__description">{{ survey.description }}</p>
      <p class="survey-detail__muted">
        {{ t('surveys.participantCount').replace('{count}', String(survey.participant_count ?? 0)) }}
        ·
        {{ t('surveys.voteCount').replace('{count}', String(survey.total_votes ?? 0)) }}
      </p>

      <p v-if="!hasVotingId" class="survey-detail__notice">{{ t('surveys.votingIdRequired') }}</p>
      <p v-else-if="!canVote && !survey.user_has_voted" class="survey-detail__muted">
        {{ t('surveys.notOpenForVote') }}
      </p>

      <ul class="survey-detail__results" aria-label="Results">
        <li
          v-for="option in survey.options"
          :key="option.id"
          class="survey-detail__result"
        >
          <div class="survey-detail__resultHead">
            <span>
              {{ option.label }}
              <span v-if="option.is_custom" class="survey-detail__customTag">{{ t('surveys.customOption') }}</span>
            </span>
            <span class="survey-detail__muted">{{ resultMeta(option) }}</span>
          </div>
          <div class="survey-detail__barTrack" aria-hidden="true">
            <div
              class="survey-detail__barFill"
              :style="{ width: `${Math.min(100, Number(option.vote_percent) || 0)}%` }"
            />
          </div>
        </li>
      </ul>

      <SurveyVoteForm
        v-if="showVoteForm"
        :survey="survey"
        :busy="voteBusy"
        @submit="submitVote"
      />

      <div v-if="canManage" class="survey-detail__manage">
        <Button
          v-if="survey.is_open"
          type="button"
          variant="ghost"
          @click="closeSurvey"
        >
          {{ t('surveys.close') }}
        </Button>
        <Button type="button" variant="ghost" @click="goEdit">{{ t('surveys.edit') }}</Button>
        <Button type="button" variant="ghost" @click="openDeleteDialog">{{ t('surveys.delete') }}</Button>
      </div>

      <p v-if="error" class="survey-detail__error">{{ error }}</p>
    </template>

    <dialog ref="deleteDialogRef" class="survey-detail__dialog" @click="onDeleteBackdrop">
      <div class="survey-detail__dialogPanel" @click.stop>
        <h2 class="survey-detail__dialogTitle">{{ t('surveys.deleteTitle') }}</h2>
        <p>{{ t('surveys.deleteConfirm') }}</p>
        <div class="survey-detail__dialogActions">
          <Button type="button" variant="ghost" @click="closeDeleteDialog">{{ t('surveys.cancel') }}</Button>
          <Button type="button" variant="primary" @click="confirmDelete">{{ t('surveys.delete') }}</Button>
        </div>
      </div>
    </dialog>
  </section>
</template>

<style scoped lang="scss">
.survey-detail {
  padding: 1rem;
  display: grid;
  gap: 0.75rem;
  max-width: 40rem;
}

.survey-detail__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.survey-detail__chip {
  font-size: 0.75rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  background: #e8ece9;
}

.survey-detail__chip--open {
  background: #d4f0dc;
  color: #1a5c2e;
}

.survey-detail__description {
  margin: 0;
}

.survey-detail__results {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.55rem;
}

.survey-detail__resultHead {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  font-size: 0.9rem;
}

.survey-detail__customTag {
  font-size: 0.7rem;
  color: #555;
  margin-left: 0.25rem;
}

.survey-detail__barTrack {
  height: 0.45rem;
  background: #e8ece9;
  border-radius: 999px;
  overflow: hidden;
}

.survey-detail__barFill {
  height: 100%;
  background: #4caf6a;
}

.survey-detail__manage {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.survey-detail__notice {
  margin: 0;
  padding: 0.65rem 0.75rem;
  border-radius: 0.5rem;
  background: #fff8e6;
  border: 1px solid #e6c84d;
}

.survey-detail__muted {
  margin: 0;
  color: #555;
}

.survey-detail__error {
  margin: 0;
  color: #a32020;
}

.survey-detail__dialog {
  border: 0;
  border-radius: 0.5rem;
  padding: 0;
  max-width: 24rem;
}

.survey-detail__dialogPanel {
  padding: 1rem;
  display: grid;
  gap: 0.65rem;
}

.survey-detail__dialogActions {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
}
</style>
