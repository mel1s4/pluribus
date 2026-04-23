<script setup>
import { computed } from 'vue'
import { t } from '../i18n/i18n'
import { PLACE_SCHEDULE_DAY_KEYS } from '../utils/placeSchedule.js'

const props = defineProps({
  recurrenceMode: {
    type: String,
    required: true,
  },
  weekdays: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits(['update:recurrenceMode', 'update:weekdays'])

const dayLabelKey = {
  mon: 'myPlaces.scheduleDayMon',
  tue: 'myPlaces.scheduleDayTue',
  wed: 'myPlaces.scheduleDayWed',
  thu: 'myPlaces.scheduleDayThu',
  fri: 'myPlaces.scheduleDayFri',
  sat: 'myPlaces.scheduleDaySat',
  sun: 'myPlaces.scheduleDaySun',
}

const weekdayList = computed(() => PLACE_SCHEDULE_DAY_KEYS.map((key) => ({
  key,
  label: t(dayLabelKey[key]),
})))

const selectedSet = computed(() => new Set(
  Array.isArray(props.weekdays) ? props.weekdays.map(String) : [],
))

function setMode(mode) {
  emit('update:recurrenceMode', mode)
  if (mode === 'once') {
    emit('update:weekdays', [])
  }
}

function toggleDay(key) {
  const cur = Array.isArray(props.weekdays) ? [...props.weekdays.map(String)] : []
  const i = cur.indexOf(key)
  if (i === -1) {
    cur.push(key)
  } else {
    cur.splice(i, 1)
  }
  emit('update:weekdays', cur)
}
</script>

<template>
  <fieldset class="place-req-recurrence">
    <legend class="place-req-recurrence__legend">
      {{ t('myPlaces.requirementRecurrenceHeading') }}
    </legend>
    <p class="place-req-recurrence__hint">{{ t('myPlaces.requirementRecurrenceHint') }}</p>
    <div class="place-req-recurrence__modes">
      <label class="place-req-recurrence__radio">
        <input
          type="radio"
          name="recurrence-mode"
          value="once"
          :checked="recurrenceMode === 'once'"
          @change="setMode('once')"
        />
        {{ t('myPlaces.requirementRecurrenceOnce') }}
      </label>
      <label class="place-req-recurrence__radio">
        <input
          type="radio"
          name="recurrence-mode"
          value="weekly"
          :checked="recurrenceMode === 'weekly'"
          @change="setMode('weekly')"
        />
        {{ t('myPlaces.requirementRecurrenceWeekly') }}
      </label>
    </div>
    <div
      v-if="recurrenceMode === 'weekly'"
      class="place-req-recurrence__days"
      role="group"
      :aria-label="t('myPlaces.requirementWeekdaysGroup')"
    >
      <label
        v-for="d in weekdayList"
        :key="d.key"
        class="place-req-recurrence__day"
      >
        <input
          type="checkbox"
          :checked="selectedSet.has(d.key)"
          @change="toggleDay(d.key)"
        />
        {{ d.label }}
      </label>
    </div>
  </fieldset>
</template>

<style lang="scss" scoped>
.place-req-recurrence {
  border: none;
  margin: 0;
  padding: 0;
}

.place-req-recurrence__legend {
  font-size: 0.85rem;
  font-weight: 600;
  padding: 0;
}

.place-req-recurrence__hint {
  margin: 0.25rem 0 0.5rem;
  font-size: 0.8rem;
  opacity: 0.85;
}

.place-req-recurrence__modes {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
}

.place-req-recurrence__radio {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.9rem;
  cursor: pointer;
}

.place-req-recurrence__days {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.75rem;
  margin-top: 0.5rem;
}

.place-req-recurrence__day {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.85rem;
  cursor: pointer;
}
</style>
