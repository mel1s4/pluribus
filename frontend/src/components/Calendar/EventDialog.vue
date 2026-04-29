<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { t } from '../../i18n/i18n'
import {
  createPost,
  createTask,
  deletePost,
  deleteTask,
  updatePost,
  updateTask,
} from '../../services/contentApi'
import { fromDatetimeLocal, toDatetimeLocal } from '../../utils/datetimeLocal.js'

const props = defineProps({
  open: { type: Boolean, default: false },
  calendars: { type: Array, default: () => [] },
  /** Create: slot from dateClick. { date: Date, allDay: boolean } */
  initialSlot: { type: Object, default: null },
  /**
   * Edit: snapshot from FullCalendar
   * @type {{ title: string, start: Date|null, end: Date|null, allDay: boolean, extendedProps: Record<string, unknown> }|null}
   */
  editing: { type: Object, default: null },
})

const emit = defineEmits(['update:open', 'saved'])

const entityKind = ref('post')
const postType = ref('event')
const title = ref('')
const calendarId = ref('')
const startLocal = ref('')
const endLocal = ref('')
const allDay = ref(false)
const recurrencePreset = ref('')
const contentMarkdown = ref('')
const visibilityScope = ref('private')
const editScope = ref('series')
const saving = ref(false)
const error = ref('')
const showNotes = ref(false)
const deleteStep = ref(0)

const isEdit = computed(() => Boolean(props.editing))

const isMobile = ref(false)
let mql = null

function readMobile() {
  if (typeof window === 'undefined') return false
  return window.matchMedia('(max-width: 767px)').matches
}

onMounted(() => {
  isMobile.value = readMobile()
  mql = window.matchMedia?.('(max-width: 767px)')
  if (mql?.addEventListener) {
    mql.addEventListener('change', onMq)
  } else if (mql?.addListener) {
    mql.addListener(onMq)
  }
})

function onMq() {
  isMobile.value = readMobile()
}

onUnmounted(() => {
  if (mql?.removeEventListener) mql.removeEventListener('change', onMq)
  else if (mql?.removeListener) mql.removeListener(onMq)
})

function resetFromCreateSlot() {
  const slot = props.initialSlot
  error.value = ''
  deleteStep.value = 0
  showNotes.value = false
  entityKind.value = 'post'
  postType.value = 'event'
  title.value = ''
  calendarId.value = ''
  contentMarkdown.value = ''
  visibilityScope.value = 'private'
  recurrencePreset.value = ''
  editScope.value = 'series'
  if (slot?.date) {
    const d = slot.date instanceof Date ? slot.date : new Date(slot.date)
    allDay.value = Boolean(slot.allDay)
    if (allDay.value) {
      const day = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 12, 0, 0)
      startLocal.value = toDatetimeLocal(day)
      const endDay = new Date(day)
      endDay.setHours(13, 0, 0, 0)
      endLocal.value = toDatetimeLocal(endDay)
    } else {
      startLocal.value = toDatetimeLocal(d)
      const end = new Date(d.getTime() + 60 * 60 * 1000)
      endLocal.value = toDatetimeLocal(end)
    }
  } else {
    const now = new Date()
    startLocal.value = toDatetimeLocal(now)
    endLocal.value = toDatetimeLocal(new Date(now.getTime() + 60 * 60 * 1000))
    allDay.value = false
  }
}

function resetFromEdit() {
  const e = props.editing
  error.value = ''
  deleteStep.value = 0
  showNotes.value = Boolean((e?.extendedProps?.raw || {}).content_markdown)
  if (!e?.extendedProps) return
  const raw = e.extendedProps.raw || {}
  entityKind.value = e.extendedProps.entityType === 'task' ? 'task' : 'post'
  postType.value = raw.type || 'event'
  title.value = e.title || ''
  calendarId.value = raw.calendar_id != null ? String(raw.calendar_id) : ''
  allDay.value = Boolean(e.allDay)
  startLocal.value = toDatetimeLocal(e.start)
  endLocal.value = toDatetimeLocal(e.end)
  recurrencePreset.value = raw.recurrence_rule || ''
  contentMarkdown.value = raw.content_markdown || ''
  visibilityScope.value = raw.visibility_scope || 'private'
  editScope.value = 'series'
}

watch(
  () => [props.open, props.editing, props.initialSlot],
  () => {
    if (!props.open) return
    if (props.editing) resetFromEdit()
    else resetFromCreateSlot()
  },
  { flush: 'post' },
)

function close() {
  deleteStep.value = 0
  emit('update:open', false)
}

function setCalendarId(id) {
  calendarId.value = id === '' ? '' : String(id)
}

async function onSave() {
  error.value = ''
  if (!title.value.trim()) {
    error.value = t('calendar.validationTitle')
    return
  }
  saving.value = true
  try {
    const calId = calendarId.value ? Number(calendarId.value) : null
    const startIso = fromDatetimeLocal(startLocal.value)
    const endIso = fromDatetimeLocal(endLocal.value)

    if (isEdit.value) {
      const ex = props.editing.extendedProps
      const id = ex.entityId
      const kind = ex.entityType === 'task' ? 'task' : 'post'
      const raw = ex.raw || {}
      const payload = {
        title: title.value.trim(),
        calendar_id: calId,
        start_at: startIso,
        end_at: endIso,
        all_day: allDay.value,
        content_markdown: contentMarkdown.value || null,
        visibility_scope: visibilityScope.value,
      }
      if (kind === 'post') {
        payload.type = postType.value
      }
      if (ex.isRecurrenceInstance && editScope.value === 'single') {
        payload.recurrence_rule = raw.recurrence_rule ?? null
        payload.recurrence_id = ex.recurrenceInstanceId || startIso
      } else {
        payload.recurrence_rule = recurrencePreset.value || null
        payload.recurrence_id = raw.recurrence_id ?? null
      }

      const res =
        kind === 'task'
          ? await updateTask(id, payload)
          : await updatePost(id, payload)
      if (!res.ok) {
        error.value = `HTTP ${res.status}`
        saving.value = false
        return
      }
    } else {
      const base = {
        title: title.value.trim(),
        calendar_id: calId,
        start_at: startIso,
        end_at: endIso,
        all_day: allDay.value,
        content_markdown: contentMarkdown.value || null,
        visibility_scope: visibilityScope.value,
        recurrence_rule: recurrencePreset.value || null,
      }
      if (entityKind.value === 'task') {
        const res = await createTask(base)
        if (!res.ok) {
          error.value = `HTTP ${res.status}`
          saving.value = false
          return
        }
      } else {
        const res = await createPost({
          ...base,
          type: postType.value,
        })
        if (!res.ok) {
          error.value = `HTTP ${res.status}`
          saving.value = false
          return
        }
      }
    }
    emit('saved')
    close()
  } finally {
    saving.value = false
  }
}

function onDeleteClick() {
  if (deleteStep.value === 0) {
    deleteStep.value = 1
    return
  }
  void confirmDelete()
}

function cancelDelete() {
  deleteStep.value = 0
}

async function confirmDelete() {
  if (!isEdit.value) return
  const ex = props.editing.extendedProps
  const id = ex.entityId
  const kind = ex.entityType === 'task' ? 'task' : 'post'
  saving.value = true
  error.value = ''
  try {
    const res = kind === 'task' ? await deleteTask(id) : await deletePost(id)
    if (!res.ok) {
      error.value = `HTTP ${res.status}`
      return
    }
    emit('saved')
    close()
  } finally {
    saving.value = false
    deleteStep.value = 0
  }
}

const showRecurrenceEditScope = computed(() => {
  if (!isEdit.value || !props.editing?.extendedProps) return false
  const ex = props.editing.extendedProps
  return Boolean(ex.raw?.recurrence_rule && ex.isRecurrenceInstance)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="ev-sheet">
      <div
        v-if="open"
        class="ev-wrap"
        role="presentation"
        @click.self="close"
      >
        <div
          class="ev-dialog"
          :class="{ 'ev-dialog--mobile': isMobile }"
          role="dialog"
          aria-modal="true"
          @click.stop
        >
          <header class="ev-dialog__head">
            <button
              type="button"
              class="ev-close"
              :aria-label="t('calendar.close')"
              @click="close"
            >
              ×
            </button>
            <h2 class="ev-dialog__title">
              {{ isEdit ? t('calendar.editEvent') : t('calendar.createEvent') }}
            </h2>
            <button
              v-if="isMobile"
              type="button"
              class="btn btn--primary ev-head-save"
              :disabled="saving"
              @click="onSave"
            >
              {{ saving ? t('calendar.saving') : t('calendar.save') }}
            </button>
          </header>

          <div class="ev-dialog__body">
            <p v-if="error" class="ev-dialog__err">{{ error }}</p>

            <template v-if="!isEdit">
              <div class="ev-section">
                <span class="ev-section__icon" aria-hidden="true">◇</span>
                <div class="ev-section__content">
                  <label class="ev-label">{{ t('calendar.entityKind') }}</label>
                  <select v-model="entityKind" class="ev-input">
                    <option value="post">{{ t('calendar.entityPost') }}</option>
                    <option value="task">{{ t('calendar.entityTask') }}</option>
                  </select>
                  <label v-if="entityKind === 'post'" class="ev-label ev-label--mt">{{ t('calendar.postType') }}</label>
                  <select v-if="entityKind === 'post'" v-model="postType" class="ev-input">
                    <option value="event">{{ t('calendar.postTypeEvent') }}</option>
                    <option value="announcement">{{ t('calendar.postTypeAnnouncement') }}</option>
                    <option value="info">{{ t('calendar.postTypeInfo') }}</option>
                  </select>
                </div>
              </div>
            </template>
            <div v-else-if="entityKind === 'post'" class="ev-section">
              <span class="ev-section__icon" aria-hidden="true">◇</span>
              <div class="ev-section__content">
                <label class="ev-label">{{ t('calendar.postType') }}</label>
                <select v-model="postType" class="ev-input">
                  <option value="event">{{ t('calendar.postTypeEvent') }}</option>
                  <option value="announcement">{{ t('calendar.postTypeAnnouncement') }}</option>
                  <option value="info">{{ t('calendar.postTypeInfo') }}</option>
                </select>
              </div>
            </div>

            <div class="ev-section ev-section--title">
              <span class="ev-section__icon" aria-hidden="true">📌</span>
              <div class="ev-section__content">
                <label class="ev-label" for="ev-title-input">{{ t('calendar.eventTitle') }}</label>
                <input
                  id="ev-title-input"
                  v-model="title"
                  type="text"
                  class="ev-input ev-input--title"
                  maxlength="255"
                  :placeholder="t('calendar.titlePlaceholder')"
                />
              </div>
            </div>

            <div class="ev-section">
              <span class="ev-section__icon" aria-hidden="true">🗂</span>
              <div class="ev-section__content">
                <span class="ev-label">{{ t('calendar.eventCalendar') }}</span>
                <div class="ev-chips" role="group" :aria-label="t('calendar.eventCalendar')">
                  <button
                    type="button"
                    class="ev-chip"
                    :class="{ 'ev-chip--on': calendarId === '' }"
                    @click="setCalendarId('')"
                  >
                    <span class="ev-chip__dot ev-chip__dot--none" />
                    {{ t('calendar.noCalendar') }}
                  </button>
                  <button
                    v-for="c in calendars"
                    :key="c.id"
                    type="button"
                    class="ev-chip"
                    :class="{ 'ev-chip--on': calendarId === String(c.id) }"
                    @click="setCalendarId(String(c.id))"
                  >
                    <span class="ev-chip__dot" :style="{ background: c.color || '#64748b' }" />
                    {{ c.name }}
                  </button>
                </div>
              </div>
            </div>

            <div class="ev-section">
              <span class="ev-section__icon" aria-hidden="true">📅</span>
              <div class="ev-section__content">
                <div class="ev-datetime-row">
                  <label class="ev-label">{{ t('calendar.start') }}</label>
                  <input v-model="startLocal" type="datetime-local" class="ev-input" />
                </div>
                <div class="ev-datetime-row ev-datetime-row--mt">
                  <label class="ev-label">{{ t('calendar.end') }}</label>
                  <input v-model="endLocal" type="datetime-local" class="ev-input" />
                </div>
                <label class="ev-allday">
                  <input v-model="allDay" type="checkbox" />
                  {{ t('calendar.allDay') }}
                </label>
              </div>
            </div>

            <div class="ev-section">
              <span class="ev-section__icon" aria-hidden="true">🔁</span>
              <div class="ev-section__content">
                <label class="ev-label">{{ t('calendar.recurrence') }}</label>
                <select v-model="recurrencePreset" class="ev-input">
                  <option value="">{{ t('calendar.recurrenceNone') }}</option>
                  <option value="FREQ=DAILY">{{ t('calendar.recurrenceDaily') }}</option>
                  <option value="FREQ=WEEKLY">{{ t('calendar.recurrenceWeekly') }}</option>
                  <option value="FREQ=MONTHLY">{{ t('calendar.recurrenceMonthly') }}</option>
                </select>
              </div>
            </div>

            <fieldset v-if="showRecurrenceEditScope" class="ev-fieldset">
              <legend>{{ t('calendar.recurrenceEditLegend') }}</legend>
              <label class="ev-radio">
                <input v-model="editScope" type="radio" value="series" />
                {{ t('calendar.editSeries') }}
              </label>
              <label class="ev-radio">
                <input v-model="editScope" type="radio" value="single" />
                {{ t('calendar.editSingle') }}
              </label>
            </fieldset>

            <div class="ev-section">
              <span class="ev-section__icon" aria-hidden="true">👁</span>
              <div class="ev-section__content">
                <span class="ev-label">{{ t('calendar.visibility') }}</span>
                <div class="ev-vis" role="group">
                  <button
                    type="button"
                    class="ev-vis__btn"
                    :class="{ 'ev-vis__btn--on': visibilityScope === 'private' }"
                    @click="visibilityScope = 'private'"
                  >
                    {{ t('calendar.private') }}
                  </button>
                  <button
                    type="button"
                    class="ev-vis__btn"
                    :class="{ 'ev-vis__btn--on': visibilityScope === 'community' }"
                    @click="visibilityScope = 'community'"
                  >
                    {{ t('calendar.community') }}
                  </button>
                  <button
                    type="button"
                    class="ev-vis__btn"
                    :class="{ 'ev-vis__btn--on': visibilityScope === 'group' }"
                    @click="visibilityScope = 'group'"
                  >
                    {{ t('calendar.group') }}
                  </button>
                </div>
              </div>
            </div>

            <div class="ev-notes">
              <button type="button" class="ev-notes__toggle" @click="showNotes = !showNotes">
                <span aria-hidden="true">📝</span>
                {{ showNotes ? t('calendar.hideNotes') : t('calendar.showNotes') }}
              </button>
              <div v-show="showNotes" class="ev-notes__panel">
                <label class="ev-label">{{ t('calendar.contentMarkdown') }}</label>
                <textarea v-model="contentMarkdown" class="ev-input ev-textarea" rows="4" />
              </div>
            </div>
          </div>

          <footer class="ev-dialog__foot">
            <template v-if="!isMobile">
              <button type="button" class="btn btn--ghost" @click="close">
                {{ t('calendar.cancel') }}
              </button>
              <div v-if="isEdit" class="ev-delete-zone">
                <template v-if="deleteStep === 0">
                  <button
                    type="button"
                    class="btn btn--ghost ev-danger"
                    :disabled="saving"
                    @click="onDeleteClick"
                  >
                    {{ t('calendar.delete') }}
                  </button>
                </template>
                <template v-else>
                  <span class="ev-delete-hint">{{ t('calendar.confirmDeleteEventInline') }}</span>
                  <button type="button" class="btn btn--ghost ev-danger" :disabled="saving" @click="confirmDelete">
                    {{ t('calendar.delete') }}
                  </button>
                  <button type="button" class="btn btn--ghost" :disabled="saving" @click="cancelDelete">
                    {{ t('calendar.cancel') }}
                  </button>
                </template>
              </div>
              <button type="button" class="btn btn--primary" :disabled="saving" @click="onSave">
                {{ saving ? t('calendar.saving') : t('calendar.save') }}
              </button>
            </template>
            <template v-else>
              <button type="button" class="btn btn--ghost ev-foot-cancel" @click="close">
                {{ t('calendar.cancel') }}
              </button>
              <div v-if="isEdit" class="ev-delete-zone ev-delete-zone--grow">
                <template v-if="deleteStep === 0">
                  <button
                    type="button"
                    class="btn btn--ghost ev-danger"
                    :disabled="saving"
                    @click="onDeleteClick"
                  >
                    {{ t('calendar.delete') }}
                  </button>
                </template>
                <template v-else>
                  <span class="ev-delete-hint">{{ t('calendar.confirmDeleteEventInline') }}</span>
                  <button type="button" class="btn btn--ghost ev-danger" :disabled="saving" @click="confirmDelete">
                    {{ t('calendar.delete') }}
                  </button>
                  <button type="button" class="btn btn--ghost" :disabled="saving" @click="cancelDelete">
                    {{ t('calendar.cancel') }}
                  </button>
                </template>
              </div>
            </template>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped lang="scss">
$bp-md: 768px;

.ev-sheet-enter-active,
.ev-sheet-leave-active {
  transition: opacity 0.2s ease;
}
.ev-sheet-enter-from,
.ev-sheet-leave-to {
  opacity: 0;
}
.ev-sheet-enter-active .ev-dialog,
.ev-sheet-leave-active .ev-dialog {
  transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.2s ease;
}
.ev-sheet-enter-from .ev-dialog--mobile,
.ev-sheet-leave-to .ev-dialog--mobile {
  transform: translateY(100%);
  opacity: 0.94;
}
.ev-sheet-enter-from .ev-dialog:not(.ev-dialog--mobile),
.ev-sheet-leave-to .ev-dialog:not(.ev-dialog--mobile) {
  transform: scale(0.96) translateY(0.35rem);
  opacity: 0;
}

.ev-wrap {
  position: fixed;
  inset: 0;
  z-index: 2000;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 0;
  overflow: hidden;
  @media (min-width: $bp-md) {
    align-items: center;
    padding: 1.25rem;
    overflow: auto;
  }
}

.ev-dialog {
  background: var(--surface, #fff);
  color: var(--text, #0f172a);
  border-radius: 1rem 1rem 0 0;
  width: 100%;
  max-height: 100dvh;
  box-shadow: 0 -12px 48px rgba(0, 0, 0, 0.14);
  display: flex;
  flex-direction: column;
  @media (min-width: $bp-md) {
    width: min(36rem, 100%);
    max-height: calc(100vh - 2.5rem);
    border-radius: 1rem;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18);
  }
}
.ev-dialog--mobile {
  max-height: 100dvh;
  min-height: min(100dvh, 36rem);
}

.ev-dialog__head {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 0.5rem;
  padding: 0.65rem 0.75rem;
  padding-top: calc(0.65rem + env(safe-area-inset-top, 0));
  border-bottom: 1px solid var(--border, #e2e8f0);
  flex-shrink: 0;
  @media (min-width: $bp-md) {
    padding-top: 0.75rem;
  }
}
.ev-close {
  border: none;
  background: transparent;
  font-size: 1.65rem;
  line-height: 1;
  cursor: pointer;
  color: inherit;
  opacity: 0.55;
  padding: 0.2rem;
  border-radius: 0.45rem;
  &:hover {
    opacity: 1;
    background: var(--surface-2, #f1f5f9);
  }
}
.ev-dialog__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  text-align: center;
}
.ev-head-save {
  font-size: 0.85rem;
  padding: 0.35rem 0.75rem;
  border-radius: 999px;
}

.ev-dialog__body {
  padding: 1rem;
  overflow: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.ev-dialog__err {
  color: #b00020;
  margin: 0;
  font-size: 0.9rem;
}

.ev-section {
  display: grid;
  grid-template-columns: 1.75rem 1fr;
  gap: 0.65rem;
  align-items: start;
}
.ev-section--title {
  align-items: stretch;
}
.ev-section__icon {
  font-size: 1rem;
  line-height: 1.5;
  opacity: 0.85;
  text-align: center;
}
.ev-section__content {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.ev-label {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  opacity: 0.65;
}
.ev-label--mt {
  margin-top: 0.35rem;
}
.ev-input {
  padding: 0.55rem 0.65rem;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 0.55rem;
  font: inherit;
  font-size: 0.9rem;
  width: 100%;
  box-sizing: border-box;
}
.ev-input--title {
  font-size: 1.15rem;
  font-weight: 600;
  letter-spacing: -0.02em;
  border-width: 0 0 2px 0;
  border-radius: 0;
  padding-left: 0;
  padding-right: 0;
  border-color: var(--border, #e2e8f0);
  background: transparent;
}
.ev-datetime-row {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.ev-datetime-row--mt {
  margin-top: 0.35rem;
}
.ev-allday {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin-top: 0.35rem;
  font-size: 0.9rem;
  cursor: pointer;
}

.ev-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}
.ev-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.65rem;
  border-radius: 999px;
  border: 1px solid var(--border, #e2e8f0);
  background: var(--surface, #fff);
  font: inherit;
  font-size: 0.78rem;
  cursor: pointer;
  max-width: 100%;
  &--on {
    border-color: transparent;
    background: var(--surface-2, #f1f5f9);
    font-weight: 600;
    box-shadow: inset 0 0 0 2px var(--border, #e2e8f0);
  }
}
.ev-chip__dot {
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 50%;
  flex-shrink: 0;
}
.ev-chip__dot--none {
  border: 2px dashed var(--border, #cbd5e1);
  background: transparent;
}

.ev-vis {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.ev-vis__btn {
  flex: 1;
  min-width: 0;
  padding: 0.45rem 0.35rem;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 0.5rem;
  background: var(--surface, #fff);
  font: inherit;
  font-size: 0.78rem;
  cursor: pointer;
  &--on {
    font-weight: 600;
    background: var(--surface-2, #f1f5f9);
    border-color: transparent;
    box-shadow: inset 0 0 0 2px var(--border, #e2e8f0);
  }
}

.ev-fieldset {
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 0.55rem;
  padding: 0.65rem 0.85rem;
  margin: 0;
  legend {
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0 0.25rem;
  }
}
.ev-radio {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin-top: 0.35rem;
  font-size: 0.88rem;
  cursor: pointer;
}

.ev-notes__toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border: none;
  background: var(--surface-2, #f1f5f9);
  padding: 0.45rem 0.75rem;
  border-radius: 0.55rem;
  font: inherit;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  color: inherit;
}
.ev-notes__panel {
  margin-top: 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.ev-textarea {
  resize: vertical;
  min-height: 5rem;
}

.ev-dialog__foot {
  padding: 0.65rem 0.85rem;
  padding-bottom: calc(0.65rem + env(safe-area-inset-bottom, 0));
  border-top: 1px solid var(--border, #e2e8f0);
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  justify-content: flex-end;
  flex-shrink: 0;
  @media (min-width: $bp-md) {
    padding-bottom: 0.75rem;
  }
}
.ev-foot-cancel {
  margin-right: auto;
}
.ev-delete-zone {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
  &--grow {
    flex: 1;
    justify-content: flex-end;
  }
}
.ev-delete-hint {
  font-size: 0.78rem;
  opacity: 0.85;
  max-width: 12rem;
  line-height: 1.25;
}
.ev-danger {
  color: #b00020;
}

@media (min-width: $bp-md) {
  .ev-head-save {
    display: none;
  }
}
</style>
