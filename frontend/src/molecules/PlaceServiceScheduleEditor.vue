<script setup>
import Button from '../atoms/Button.vue'
import { PLACE_SCHEDULE_DAY_KEYS, normalizeServiceSchedule } from '../utils/placeSchedule.js'
import { t } from '../i18n/i18n'

const props = defineProps({
  modelValue: {
    type: Object,
    required: true,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

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

function schedule() {
  return normalizeServiceSchedule(props.modelValue)
}

function emitSchedule(next) {
  emit('update:modelValue', normalizeServiceSchedule(next))
}

function slotsFor(day) {
  return schedule()[day] || []
}

function patchDay(day, slots) {
  const base = { ...schedule(), [day]: slots }
  emitSchedule(base)
}

function addSlot(day) {
  const cur = [...slotsFor(day)]
  cur.push({ open: '09:00', close: '17:00' })
  patchDay(day, cur)
}

function removeSlot(day, index) {
  const cur = slotsFor(day).filter((_, i) => i !== index)
  patchDay(day, cur)
}

function setSlot(day, index, patch) {
  const cur = slotsFor(day).map((s, i) => (i === index ? { ...s, ...patch } : { ...s }))
  patchDay(day, cur)
}
</script>

<template>
  <div class="place-schedule-editor">
    <h3 class="place-schedule-editor__heading">{{ t('myPlaces.scheduleHeading') }}</h3>
    <p class="place-schedule-editor__intro">{{ t('myPlaces.scheduleIntro') }}</p>
    <div
      v-for="day in PLACE_SCHEDULE_DAY_KEYS"
      :key="day"
      class="place-schedule-editor__day"
    >
      <div class="place-schedule-editor__dayLabel">{{ dayLabel(day) }}</div>
      <div class="place-schedule-editor__dayBody">
        <p v-if="!slotsFor(day).length" class="place-schedule-editor__closed">
          {{ t('myPlaces.scheduleClosed') }}
        </p>
        <ul v-else class="place-schedule-editor__slots" role="list">
          <li v-for="(slot, index) in slotsFor(day)" :key="index" class="place-schedule-editor__slot">
            <label class="place-schedule-editor__timeLabel">
              <span class="place-schedule-editor__timeCaption">{{ t('myPlaces.scheduleOpen') }}</span>
              <input
                class="place-schedule-editor__timeInput"
                type="time"
                step="300"
                :value="slot.open"
                :disabled="disabled"
                @input="setSlot(day, index, { open: ($event.target).value })"
              />
            </label>
            <label class="place-schedule-editor__timeLabel">
              <span class="place-schedule-editor__timeCaption">{{ t('myPlaces.scheduleClose') }}</span>
              <input
                class="place-schedule-editor__timeInput"
                type="time"
                step="300"
                :value="slot.close"
                :disabled="disabled"
                @input="setSlot(day, index, { close: ($event.target).value })"
              />
            </label>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              :disabled="disabled"
              @click="removeSlot(day, index)"
            >
              {{ t('myPlaces.scheduleRemoveSlot') }}
            </Button>
          </li>
        </ul>
        <Button type="button" variant="secondary" size="sm" :disabled="disabled" @click="addSlot(day)">
          {{ t('myPlaces.scheduleAddSlot') }}
        </Button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.place-schedule-editor {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.place-schedule-editor__heading {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.place-schedule-editor__intro {
  margin: 0;
  font-size: 0.85rem;
  opacity: 0.85;
}

.place-schedule-editor__day {
  display: grid;
  grid-template-columns: minmax(5rem, 7rem) 1fr;
  gap: 0.5rem 0.75rem;
  align-items: start;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border);
}

.place-schedule-editor__day:last-child {
  border-bottom: none;
}

.place-schedule-editor__dayLabel {
  font-weight: 600;
  font-size: 0.9rem;
  padding-top: 0.25rem;
}

.place-schedule-editor__dayBody {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.place-schedule-editor__closed {
  margin: 0;
  font-size: 0.85rem;
  opacity: 0.75;
}

.place-schedule-editor__slots {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.place-schedule-editor__slot {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.5rem 0.75rem;
}

.place-schedule-editor__timeLabel {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  font-size: 0.8rem;
}

.place-schedule-editor__timeCaption {
  opacity: 0.85;
}

.place-schedule-editor__timeInput {
  padding: 0.25rem 0.35rem;
  border-radius: 0.35rem;
  border: 1px solid var(--border);
  font: inherit;
}
</style>
