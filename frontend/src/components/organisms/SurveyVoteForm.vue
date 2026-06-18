<script setup>
import { computed, ref, watch } from 'vue'
import Button from '../../atoms/Button.vue'
import { t } from '../../i18n/i18n'

const props = defineProps({
  survey: {
    type: Object,
    required: true,
  },
  busy: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['submit'])

/** @type {import('vue').Ref<Array<{ key: string, optionId: number|null, customLabel: string, rank: number|null }>>} */
const ballot = ref([])
const customInput = ref('')

function optionLabel(optionId) {
  const opt = props.survey.options?.find((o) => Number(o.id) === Number(optionId))
  return opt?.label ?? ''
}

function hydrateFromSurvey(survey) {
  const rows = Array.isArray(survey.user_selections) ? survey.user_selections : []
  if (rows.length > 0) {
    ballot.value = rows.map((row, i) => ({
      key: `sel-${row.option_id}-${i}`,
      optionId: Number(row.option_id),
      customLabel: '',
      rank: row.rank ?? null,
    }))
    return
  }
  if (!survey.allow_multiple && survey.user_option_id) {
    ballot.value = [{
      key: `sel-${survey.user_option_id}`,
      optionId: Number(survey.user_option_id),
      customLabel: '',
      rank: null,
    }]
  } else {
    ballot.value = []
  }
}

watch(
  () => props.survey,
  (s) => {
    if (s) hydrateFromSurvey(s)
  },
  { immediate: true, deep: true },
)

const isSingle = computed(() => !props.survey.allow_multiple)
const isRanked = computed(() => props.survey.allow_multiple && props.survey.require_ranked)
const isMultiple = computed(() => props.survey.allow_multiple && !props.survey.require_ranked)

const allOptions = computed(() =>
  Array.isArray(props.survey.options) ? props.survey.options : [],
)

function isSelected(optionId) {
  return ballot.value.some((b) => b.optionId === Number(optionId))
}

function toggleCheckbox(optionId) {
  if (isSelected(optionId)) {
    ballot.value = ballot.value.filter((b) => b.optionId !== Number(optionId))
  } else {
    ballot.value.push({
      key: `opt-${optionId}-${Date.now()}`,
      optionId: Number(optionId),
      customLabel: '',
      rank: null,
    })
  }
}

function selectSingle(optionId) {
  ballot.value = [{
    key: `opt-${optionId}`,
    optionId: Number(optionId),
    customLabel: '',
    rank: null,
  }]
}

function addCustomToBallot() {
  const label = customInput.value.trim()
  if (!label) return
  const existing = props.survey.options?.find(
    (o) => String(o.label || '').trim().toLowerCase() === label.toLowerCase(),
  )
  if (existing) {
    if (isSingle.value) {
      selectSingle(existing.id)
    } else if (!isSelected(existing.id)) {
      toggleCheckbox(existing.id)
    }
    customInput.value = ''
    return
  }
  if (isSingle.value) {
    ballot.value = [{
      key: `custom-${Date.now()}`,
      optionId: null,
      customLabel: label,
      rank: null,
    }]
  } else {
    ballot.value.push({
      key: `custom-${Date.now()}`,
      optionId: null,
      customLabel: label,
      rank: isRanked.value ? ballot.value.length + 1 : null,
    })
    if (isRanked.value) syncRanks()
  }
  customInput.value = ''
}

function syncRanks() {
  ballot.value.forEach((row, index) => {
    row.rank = index + 1
  })
}

function moveUp(index) {
  if (index <= 0) return
  const copy = [...ballot.value]
  const tmp = copy[index - 1]
  copy[index - 1] = copy[index]
  copy[index] = tmp
  ballot.value = copy
  syncRanks()
}

function moveDown(index) {
  if (index >= ballot.value.length - 1) return
  const copy = [...ballot.value]
  const tmp = copy[index + 1]
  copy[index + 1] = copy[index]
  copy[index] = tmp
  ballot.value = copy
  syncRanks()
}

function addRankedOption(optionId) {
  if (isSelected(optionId)) return
  ballot.value.push({
    key: `opt-${optionId}-${Date.now()}`,
    optionId: Number(optionId),
    customLabel: '',
    rank: ballot.value.length + 1,
  })
}

function removeRanked(index) {
  ballot.value.splice(index, 1)
  syncRanks()
}

function rowLabel(row) {
  if (row.optionId != null) return optionLabel(row.optionId)
  return row.customLabel
}

const canSubmit = computed(() => {
  if (ballot.value.length === 0) return false
  if (isSingle.value) return ballot.value.length === 1
  return true
})

function buildSelections() {
  if (isRanked.value) {
    syncRanks()
  }
  return ballot.value.map((row) => {
    if (row.optionId != null) {
      const item = { option_id: row.optionId }
      if (isRanked.value && row.rank != null) item.rank = row.rank
      return item
    }
    const item = { custom_label: row.customLabel }
    if (isRanked.value && row.rank != null) item.rank = row.rank
    return item
  })
}

function submit() {
  if (!canSubmit.value) return
  emit('submit', buildSelections())
}
</script>

<template>
  <form class="survey-vote-form" @submit.prevent="submit">
    <p class="survey-vote-form__title">{{ t('surveys.castVote') }}</p>

    <template v-if="isSingle">
      <label
        v-for="opt in survey.options"
        :key="opt.id"
        class="survey-vote-form__choice"
      >
        <input
          type="radio"
          name="survey-single"
          :checked="isSelected(opt.id)"
          @change="selectSingle(opt.id)"
        />
        <span>{{ opt.label }}</span>
      </label>
      <label
        v-for="row in ballot.filter((b) => b.optionId == null)"
        :key="row.key"
        class="survey-vote-form__choice"
      >
        <input type="radio" name="survey-single" checked />
        <span>{{ row.customLabel }}</span>
      </label>
    </template>

    <template v-else-if="isMultiple">
      <label
        v-for="opt in survey.options"
        :key="opt.id"
        class="survey-vote-form__choice"
      >
        <input
          type="checkbox"
          :checked="isSelected(opt.id)"
          @change="toggleCheckbox(opt.id)"
        />
        <span>{{ opt.label }}</span>
      </label>
    </template>

    <template v-else-if="isRanked">
      <p class="survey-vote-form__hint">{{ t('surveys.rankedHint') }}</p>
      <div class="survey-vote-form__rankedPick">
        <label
          v-for="opt in allOptions"
          :key="`pick-${opt.id}`"
          class="survey-vote-form__choice"
        >
          <input
            type="checkbox"
            :checked="isSelected(opt.id)"
            @change="isSelected(opt.id) ? removeRanked(ballot.findIndex((b) => b.optionId === Number(opt.id))) : addRankedOption(opt.id)"
          />
          <span>{{ opt.label }}</span>
        </label>
      </div>
      <ol class="survey-vote-form__rankedList">
        <li
          v-for="(row, index) in ballot"
          :key="row.key"
          class="survey-vote-form__rankedItem"
        >
          <span class="survey-vote-form__rankNum">{{ index + 1 }}.</span>
          <span class="survey-vote-form__rankLabel">{{ rowLabel(row) }}</span>
          <div class="survey-vote-form__rankActions">
            <Button type="button" variant="ghost" @click="moveUp(index)">↑</Button>
            <Button type="button" variant="ghost" @click="moveDown(index)">↓</Button>
            <Button type="button" variant="ghost" @click="removeRanked(index)">×</Button>
          </div>
        </li>
      </ol>
    </template>

    <div v-if="survey.allow_add_options" class="survey-vote-form__addCustom">
      <input
        v-model="customInput"
        type="text"
        maxlength="255"
        :placeholder="t('surveys.addYourOptionPlaceholder')"
      />
      <Button type="button" variant="ghost" @click="addCustomToBallot">
        {{ t('surveys.addYourOption') }}
      </Button>
    </div>

    <Button type="submit" variant="primary" :disabled="!canSubmit || busy">
      {{ busy ? t('surveys.voting') : t('surveys.submitVote') }}
    </Button>
  </form>
</template>

<style scoped lang="scss">
.survey-vote-form {
  display: grid;
  gap: 0.45rem;
  padding: 0.65rem;
  border: 1px solid #9ab8a0;
  border-radius: 0.5rem;
}

.survey-vote-form__title {
  margin: 0;
  font-weight: 600;
}

.survey-vote-form__hint {
  margin: 0;
  font-size: 0.85rem;
  color: #555;
}

.survey-vote-form__choice {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.survey-vote-form__addCustom {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.survey-vote-form__addCustom input {
  flex: 1;
  min-width: 10rem;
  padding: 0.4rem 0.5rem;
  border: 1px solid #9ab8a0;
  border-radius: 0.4rem;
}

.survey-vote-form__rankedList {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.35rem;
}

.survey-vote-form__rankedItem {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.survey-vote-form__rankNum {
  font-weight: 600;
  min-width: 1.5rem;
}

.survey-vote-form__rankLabel {
  flex: 1;
}

.survey-vote-form__rankActions {
  display: flex;
  gap: 0.15rem;
}
</style>
