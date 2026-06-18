<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import { useHasVotingId } from '../../composables/useHasVotingId'
import { sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { createSurvey, fetchSurvey, updateSurvey } from '../../services/surveysApi'

const route = useRoute()
const router = useRouter()
const hasVotingId = useHasVotingId()

const surveyId = computed(() => {
  const raw = route.params.id
  return typeof raw === 'string' && raw.trim() !== '' ? raw.trim() : ''
})
const isEdit = computed(() => surveyId.value !== '' && route.name === 'surveys-edit')

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const hasVotes = ref(false)

const title = ref('')
const description = ref('')
const closesAtInput = ref('')
const options = ref(['', ''])
const allowMultiple = ref(false)
const requireRanked = ref(false)
const allowAddOptions = ref(false)

function toDatetimeLocalValue(iso) {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return ''
    const pad = (n) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
  } catch {
    return ''
  }
}

function closesAtPayload() {
  const raw = closesAtInput.value.trim()
  if (!raw) return null
  const d = new Date(raw)
  if (Number.isNaN(d.getTime())) return null
  return d.toISOString()
}

function trimmedOptions() {
  return options.value.map((o) => String(o || '').trim()).filter((o) => o !== '')
}

const canSubmit = computed(() => {
  if (!hasVotingId.value) return false
  if (title.value.trim() === '') return false
  const opts = trimmedOptions()
  return opts.length >= 2 && opts.length <= 10
})

function addOption() {
  if (options.value.length >= 10) return
  options.value.push('')
}

function removeOption(index) {
  if (options.value.length <= 2) return
  options.value.splice(index, 1)
}

async function loadSurvey() {
  if (!isEdit.value) return
  loading.value = true
  error.value = ''
  const res = await fetchSurvey(surveyId.value)
  loading.value = false
  if (!res.ok) {
    error.value = t('surveys.composerLoadError')
    return
  }
  const survey = res.data?.survey
  if (!survey) {
    error.value = t('surveys.composerLoadError')
    return
  }
  title.value = survey.title || ''
  description.value = survey.description || ''
  closesAtInput.value = toDatetimeLocalValue(survey.closes_at)
  hasVotes.value = Boolean(survey.has_votes)
  allowMultiple.value = Boolean(survey.allow_multiple)
  requireRanked.value = Boolean(survey.require_ranked)
  allowAddOptions.value = Boolean(survey.allow_add_options)
  options.value = Array.isArray(survey.options)
    ? survey.options.filter((o) => !o.is_custom).map((o) => o.label)
    : ['', '']
  if (options.value.length < 2) {
    options.value = ['', '']
  }
}

async function submit() {
  if (!canSubmit.value) return
  saving.value = true
  error.value = ''
  const payload = {
    title: title.value.trim(),
    description: description.value.trim() || null,
    closes_at: closesAtPayload(),
    community_id: Number(sessionUser.value?.active_community_id || 0) || undefined,
  }
  const opts = trimmedOptions()
  if (!hasVotes.value) {
    payload.options = opts
    payload.allow_multiple = allowMultiple.value
    payload.require_ranked = requireRanked.value
    payload.allow_add_options = allowAddOptions.value
  }

  const res = isEdit.value
    ? await updateSurvey(surveyId.value, payload)
    : await createSurvey(payload)

  saving.value = false
  if (!res.ok) {
    error.value = t('surveys.composerSaveError').replace('{status}', String(res.status))
    return
  }
  const id = res.data?.survey?.id ?? surveyId.value
  router.push({ name: 'surveys-detail', params: { id: String(id) } })
}

function cancel() {
  if (isEdit.value) {
    router.push({ name: 'surveys-detail', params: { id: surveyId.value } })
    return
  }
  router.push({ name: 'surveys' })
}

watch(allowMultiple, (on) => {
  if (!on) requireRanked.value = false
})

onMounted(loadSurvey)
</script>

<template>
  <section class="survey-composer">
    <PageToolbarTitle route-key="surveys">
      <Title tag="h1">
        {{ isEdit ? t('surveys.composerEditTitle') : t('surveys.composerCreateTitle') }}
      </Title>
    </PageToolbarTitle>

    <p v-if="loading" class="survey-composer__muted">{{ t('surveys.composerLoading') }}</p>
    <p v-else-if="!hasVotingId" class="survey-composer__notice">{{ t('surveys.votingIdRequired') }}</p>

    <form v-else class="survey-composer__form" @submit.prevent="submit">
      <label class="survey-composer__field">
        <span>{{ t('surveys.titleLabel') }}</span>
        <input v-model="title" type="text" required maxlength="255" />
      </label>

      <label class="survey-composer__field">
        <span>{{ t('surveys.descriptionLabel') }}</span>
        <textarea v-model="description" rows="3" />
      </label>

      <label class="survey-composer__field">
        <span>{{ t('surveys.closesAtLabel') }}</span>
        <input v-model="closesAtInput" type="datetime-local" />
      </label>

      <fieldset class="survey-composer__modalities" :disabled="hasVotes">
        <legend>{{ t('surveys.modalitiesLabel') }}</legend>
        <p v-if="hasVotes" class="survey-composer__muted">{{ t('surveys.modalitiesLocked') }}</p>
        <label class="survey-composer__check">
          <input v-model="allowMultiple" type="checkbox" :disabled="hasVotes" />
          <span>{{ t('surveys.allowMultiple') }}</span>
        </label>
        <p class="survey-composer__hint">{{ t('surveys.allowMultipleHint') }}</p>
        <label class="survey-composer__check">
          <input
            v-model="requireRanked"
            type="checkbox"
            :disabled="hasVotes || !allowMultiple"
          />
          <span>{{ t('surveys.requireRanked') }}</span>
        </label>
        <p class="survey-composer__hint">{{ t('surveys.requireRankedHint') }}</p>
        <label class="survey-composer__check">
          <input v-model="allowAddOptions" type="checkbox" :disabled="hasVotes" />
          <span>{{ t('surveys.allowAddOptions') }}</span>
        </label>
        <p class="survey-composer__hint">{{ t('surveys.allowAddOptionsHint') }}</p>
      </fieldset>

      <fieldset class="survey-composer__options">
        <legend>{{ t('surveys.optionsLabel') }}</legend>
        <p v-if="hasVotes" class="survey-composer__muted">{{ t('surveys.optionsLocked') }}</p>
        <div
          v-for="(_, index) in options"
          :key="index"
          class="survey-composer__optionRow"
        >
          <input
            v-model="options[index]"
            type="text"
            maxlength="255"
            :disabled="hasVotes"
            :placeholder="t('surveys.optionPlaceholder').replace('{n}', String(index + 1))"
          />
          <Button
            v-if="!hasVotes && options.length > 2"
            type="button"
            variant="ghost"
            @click="removeOption(index)"
          >
            {{ t('surveys.removeOption') }}
          </Button>
        </div>
        <Button
          v-if="!hasVotes && options.length < 10"
          type="button"
          variant="ghost"
          @click="addOption"
        >
          {{ t('surveys.addOption') }}
        </Button>
      </fieldset>

      <p v-if="error" class="survey-composer__error">{{ error }}</p>

      <div class="survey-composer__actions">
        <Button type="button" variant="ghost" @click="cancel">{{ t('surveys.cancel') }}</Button>
        <Button type="submit" variant="primary" :disabled="!canSubmit || saving">
          {{ saving ? t('surveys.composerSaving') : t('surveys.save') }}
        </Button>
      </div>
    </form>
  </section>
</template>

<style scoped lang="scss">
.survey-composer {
  padding: 1rem;
  display: grid;
  gap: 0.75rem;
  max-width: 36rem;
}

.survey-composer__form {
  display: grid;
  gap: 0.75rem;
}

.survey-composer__field {
  display: grid;
  gap: 0.25rem;
}

.survey-composer__field input,
.survey-composer__field textarea {
  width: 100%;
  padding: 0.45rem 0.55rem;
  border: 1px solid #9ab8a0;
  border-radius: 0.4rem;
}

.survey-composer__modalities,
.survey-composer__options {
  border: 1px solid #9ab8a0;
  border-radius: 0.5rem;
  padding: 0.65rem;
  display: grid;
  gap: 0.5rem;
}

.survey-composer__check {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.survey-composer__hint {
  margin: 0;
  font-size: 0.85rem;
  color: #555;
}

.survey-composer__optionRow {
  display: flex;
  gap: 0.35rem;
  align-items: center;
}

.survey-composer__optionRow input {
  flex: 1;
  padding: 0.45rem 0.55rem;
  border: 1px solid #9ab8a0;
  border-radius: 0.4rem;
}

.survey-composer__actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.survey-composer__muted {
  margin: 0;
  color: #555;
}

.survey-composer__notice {
  margin: 0;
  padding: 0.65rem 0.75rem;
  border-radius: 0.5rem;
  background: #fff8e6;
  border: 1px solid #e6c84d;
}

.survey-composer__error {
  margin: 0;
  color: #a32020;
}
</style>
