<script setup>
import { t } from '../i18n/i18n'

defineProps({
  form: { type: Object, required: true },
})
</script>

<template>
  <div class="post-composer-schedule">
    <p v-if="form.type === 'info'" class="post-composer-schedule__hint">
      {{ t('posts.composerScheduleHintInfo') }}
    </p>

    <div class="post-composer-schedule__row">
      <label class="post-composer-schedule__field">
        <span class="post-composer-schedule__label">{{ t('calendar.start') }}</span>
        <input v-model="form.startLocal" type="datetime-local" class="post-composer-schedule__input" />
      </label>
      <label class="post-composer-schedule__field">
        <span class="post-composer-schedule__label">{{ t('calendar.end') }}</span>
        <input v-model="form.endLocal" type="datetime-local" class="post-composer-schedule__input" />
      </label>
    </div>

    <label class="post-composer-schedule__allday">
      <input v-model="form.all_day" type="checkbox" />
      {{ t('calendar.allDay') }}
    </label>

    <label class="post-composer-schedule__field post-composer-schedule__field--full">
      <span class="post-composer-schedule__label">{{ t('calendar.recurrence') }}</span>
      <select v-model="form.recurrence_rule" class="post-composer-schedule__input">
        <option value="">{{ t('calendar.recurrenceNone') }}</option>
        <option value="FREQ=DAILY">{{ t('calendar.recurrenceDaily') }}</option>
        <option value="FREQ=WEEKLY">{{ t('calendar.recurrenceWeekly') }}</option>
        <option value="FREQ=MONTHLY">{{ t('calendar.recurrenceMonthly') }}</option>
      </select>
    </label>
  </div>
</template>

<style scoped lang="scss">
.post-composer-schedule {
  display: grid;
  gap: 1rem;
}

.post-composer-schedule__hint {
  margin: 0;
  font-size: 0.875rem;
  opacity: 0.85;
}

.post-composer-schedule__row {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: 1fr 1fr;

  @media (max-width: 640px) {
    grid-template-columns: 1fr;
  }
}

.post-composer-schedule__field {
  display: grid;
  gap: 0.35rem;
  margin: 0;
}

.post-composer-schedule__field--full {
  grid-column: 1 / -1;
}

.post-composer-schedule__label {
  font-size: 0.875rem;
  font-weight: 600;
}

.post-composer-schedule__input {
  padding: 0.5rem 0.55rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  background: var(--bg, #fff);
  color: inherit;
  font: inherit;
  width: 100%;
  box-sizing: border-box;
}

.post-composer-schedule__allday {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  cursor: pointer;
}
</style>
