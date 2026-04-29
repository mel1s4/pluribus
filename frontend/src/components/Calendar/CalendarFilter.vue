<script setup>
import { computed, ref } from 'vue'
import { t } from '../../i18n/i18n'

const props = defineProps({
  calendars: { type: Array, default: () => [] },
  /** @type {number[]} */
  visibleCalendarIds: { type: Array, default: () => [] },
  showPosts: { type: Boolean, default: true },
  showTasks: { type: Boolean, default: true },
  /** When true, panel is styled for bottom-sheet (rounded top, safe area) */
  variant: { type: String, default: 'panel' }, // 'panel' | 'sheet'
})

const emit = defineEmits([
  'update:visibleCalendarIds',
  'update:showPosts',
  'update:showTasks',
  'edit-calendar',
  'delete-calendar',
  'add-calendar',
])

const list = computed(() =>
  Array.isArray(props.calendars) ? props.calendars : [],
)

const allVisible = computed(
  () =>
    list.value.length > 0 &&
    list.value.every((c) => props.visibleCalendarIds.includes(c.id)),
)

const deleteConfirmId = ref(null)

function isVisible(id) {
  return props.visibleCalendarIds.includes(id)
}

function toggle(id) {
  const set = new Set(props.visibleCalendarIds)
  if (set.has(id)) set.delete(id)
  else set.add(id)
  emit('update:visibleCalendarIds', [...set])
}

function toggleShowAll() {
  if (allVisible.value) {
    emit('update:visibleCalendarIds', [])
  } else {
    emit(
      'update:visibleCalendarIds',
      list.value.map((c) => c.id),
    )
  }
}

function onEdit(cal) {
  deleteConfirmId.value = null
  emit('edit-calendar', cal)
}

function onDeleteClick(cal) {
  if (deleteConfirmId.value === cal.id) {
    emit('delete-calendar', cal)
    deleteConfirmId.value = null
    return
  }
  deleteConfirmId.value = cal.id
}

function cancelDelete() {
  deleteConfirmId.value = null
}

const isSheet = computed(() => props.variant === 'sheet')
</script>

<template>
  <aside class="cal-filter" :class="{ 'cal-filter--sheet': isSheet }">
    <div class="cal-filter__head">
      <h3 class="cal-filter__title">{{ t('calendar.calendars') }}</h3>
      <button
        type="button"
        class="cal-filter__link"
        @click="toggleShowAll"
      >
        {{ allVisible ? t('calendar.hideAllCalendars') : t('calendar.showAllCalendars') }}
      </button>
    </div>

    <button
      type="button"
      class="cal-filter__new btn btn--primary btn--sm"
      @click="emit('add-calendar')"
    >
      {{ t('calendar.newCalendar') }}
    </button>

    <ul class="cal-filter__list">
      <li v-for="cal in list" :key="cal.id" class="cal-filter__row">
        <label class="cal-filter__row-main">
          <input
            type="checkbox"
            class="cal-filter__cb"
            :checked="isVisible(cal.id)"
            @change="toggle(cal.id)"
          />
          <span
            class="cal-filter__chip"
            :style="{ '--cal-color': cal.color || '#22c55e' }"
            aria-hidden="true"
          />
          <span class="cal-filter__name">{{ cal.name }}</span>
        </label>
        <div class="cal-filter__actions">
          <template v-if="deleteConfirmId === cal.id">
            <span class="cal-filter__confirm-label">{{ t('calendar.confirmDeleteCalendarShort') }}</span>
            <button type="button" class="cal-filter__icon cal-filter__icon--danger" @click="onDeleteClick(cal)">
              {{ t('calendar.delete') }}
            </button>
            <button type="button" class="cal-filter__icon" @click="cancelDelete">
              {{ t('calendar.cancel') }}
            </button>
          </template>
          <template v-else>
            <button
              type="button"
              class="cal-filter__icon"
              :title="t('calendar.editCalendar')"
              :aria-label="t('calendar.editCalendar')"
              @click="onEdit(cal)"
            >
              <svg class="cal-filter__svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
            <button
              type="button"
              class="cal-filter__icon cal-filter__icon--danger"
              :title="t('calendar.deleteCalendar')"
              :aria-label="t('calendar.deleteCalendar')"
              @click="onDeleteClick(cal)"
            >
              <svg class="cal-filter__svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M3 6h18M8 6V4h8v2M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M10 11v6M14 11v6" stroke-linecap="round" />
              </svg>
            </button>
          </template>
        </div>
      </li>
    </ul>

    <div class="cal-filter__divider" />

    <h3 class="cal-filter__subtitle">{{ t('calendar.eventTypes') }}</h3>
    <div class="cal-filter__pills">
      <button
        type="button"
        class="cal-filter__pill"
        :class="{ 'cal-filter__pill--on': showPosts }"
        @click="emit('update:showPosts', !showPosts)"
      >
        {{ t('calendar.showEvents') }}
      </button>
      <button
        type="button"
        class="cal-filter__pill"
        :class="{ 'cal-filter__pill--on': showTasks }"
        @click="emit('update:showTasks', !showTasks)"
      >
        {{ t('calendar.showTasks') }}
      </button>
    </div>
  </aside>
</template>

<style scoped lang="scss">
.cal-filter {
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 0.85rem;
  padding: 0.85rem;
  display: grid;
  gap: 0.65rem;
  align-content: start;
  background: var(--surface, #fff);
}
.cal-filter--sheet {
  border: none;
  border-radius: 0;
  padding: 0.75rem 1rem 1rem;
  padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0));
  box-shadow: none;
}
.cal-filter__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
}
.cal-filter__title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}
.cal-filter__subtitle {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.65;
}
.cal-filter__link {
  border: none;
  background: none;
  padding: 0;
  font: inherit;
  font-size: 0.8rem;
  font-weight: 600;
  color: #2563eb;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 2px;
}
.cal-filter__new {
  width: 100%;
  justify-self: stretch;
  border-radius: 0.55rem;
  padding: 0.45rem 0.65rem;
  font-size: 0.85rem;
}
.btn--sm {
  font-size: 0.85rem;
}
.cal-filter__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.2rem;
}
.cal-filter__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.35rem;
  min-height: 2.25rem;
}
.cal-filter__row-main {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  min-width: 0;
  flex: 1;
  cursor: pointer;
  font-size: 0.9rem;
}
.cal-filter__cb {
  flex-shrink: 0;
}
.cal-filter__chip {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 50%;
  background: var(--cal-color);
  flex-shrink: 0;
  box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.12);
}
.cal-filter__name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cal-filter__actions {
  display: flex;
  align-items: center;
  gap: 0.15rem;
  flex-shrink: 0;
}
.cal-filter__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: 0.45rem;
  background: transparent;
  color: var(--text, #0f172a);
  cursor: pointer;
  opacity: 0.55;
  font-size: 0.75rem;
  font-weight: 600;
  &:hover {
    opacity: 1;
    background: var(--surface-2, #f1f5f9);
  }
}
.cal-filter__icon--danger {
  color: #b00020;
}
.cal-filter__svg {
  width: 1.05rem;
  height: 1.05rem;
}
.cal-filter__confirm-label {
  font-size: 0.72rem;
  max-width: 6rem;
  line-height: 1.2;
  opacity: 0.85;
}
.cal-filter__divider {
  height: 1px;
  background: var(--border, #e2e8f0);
  margin: 0.25rem 0;
}
.cal-filter__pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}
.cal-filter__pill {
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 999px;
  padding: 0.4rem 0.75rem;
  font: inherit;
  font-size: 0.8rem;
  cursor: pointer;
  background: var(--surface, #fff);
  color: inherit;
  opacity: 0.75;
  transition: background 0.15s ease, border-color 0.15s ease, opacity 0.15s ease;
}
.cal-filter__pill--on {
  opacity: 1;
  font-weight: 600;
  background: var(--surface-2, #f1f5f9);
  border-color: transparent;
  box-shadow: inset 0 0 0 2px var(--border, #e2e8f0);
}
</style>
