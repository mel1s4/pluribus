<script setup>
import { computed } from 'vue'
import { PLACE_SCHEDULE_DAY_KEYS, normalizeServiceSchedule } from '../utils/placeSchedule.js'
import { t } from '../i18n/i18n'

const props = defineProps({
  schedule: {
    type: Object,
    default: null,
  },
})

const normalized = computed(() => normalizeServiceSchedule(props.schedule))

function dayLabel(day) {
  const map = {
    mon: 'myPlaces.scheduleDayMon',
    tue: 'myPlaces.scheduleDayTue',
    wed: 'myPlaces.scheduleDayWed',
    thu: 'myPlaces.scheduleDayThu',
    fri: 'myPlaces.scheduleDayFri',
    sat: 'myPlaces.scheduleDaySat',
    sun: 'myPlaces.scheduleDaySun',
  }
  return t(map[day] || day)
}

const hasAny = computed(() =>
  PLACE_SCHEDULE_DAY_KEYS.some((d) => (normalized.value[d] || []).length > 0),
)

function rangesText(day) {
  const slots = normalized.value[day] || []
  return slots.map((s) => `${s.open}–${s.close}`).join('; ')
}
</script>

<template>
  <div class="place-schedule-display">
    <p v-if="!hasAny" class="place-schedule-display__empty">{{ t('places.viewScheduleEmpty') }}</p>
    <table v-else class="place-schedule-display__table">
      <tbody>
        <tr v-for="day in PLACE_SCHEDULE_DAY_KEYS" :key="day">
          <th class="place-schedule-display__day">{{ dayLabel(day) }}</th>
          <td class="place-schedule-display__hours">
            <template v-if="!(normalized[day] || []).length">
              {{ t('myPlaces.scheduleClosed') }}
            </template>
            <template v-else>
              {{ rangesText(day) }}
            </template>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style lang="scss" scoped>
.place-schedule-display__empty {
  margin: 0;
  font-size: 0.9rem;
  opacity: 0.8;
}

.place-schedule-display__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.95rem;
}

.place-schedule-display__day {
  text-align: left;
  vertical-align: top;
  padding: 0.45rem 0.75rem 0.45rem 0;
  font-weight: 600;
  white-space: nowrap;
  border-bottom: 1px solid var(--border);
}

.place-schedule-display__hours {
  padding: 0.45rem 0;
  border-bottom: 1px solid var(--border);
}
</style>
