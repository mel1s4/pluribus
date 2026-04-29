<script setup>
import { computed, ref, watch } from 'vue'
import { t } from '../../i18n/i18n'
import { createCalendar, updateCalendar } from '../../services/contentApi'

/** Preset palette (Notion-style) */
const CALENDAR_COLOR_SWATCHES = [
  '#22c55e',
  '#3b82f6',
  '#a855f7',
  '#f97316',
  '#ec4899',
  '#14b8a6',
  '#eab308',
  '#64748b',
]

const props = defineProps({
  open: { type: Boolean, default: false },
  /** When set, dialog is in edit mode */
  calendar: { type: Object, default: null },
})

const emit = defineEmits(['update:open', 'saved'])

const name = ref('')
const visibility = ref('private')
const color = ref(CALENDAR_COLOR_SWATCHES[0])
const saving = ref(false)
const error = ref('')

const isEdit = computed(() => Boolean(props.calendar?.id))

watch(
  () => [props.open, props.calendar],
  () => {
    if (!props.open) return
    error.value = ''
    if (props.calendar?.id) {
      name.value = props.calendar.name || ''
      color.value = props.calendar.color || CALENDAR_COLOR_SWATCHES[0]
      visibility.value = props.calendar.visibility_scope || 'private'
    } else {
      name.value = ''
      color.value = CALENDAR_COLOR_SWATCHES[0]
      visibility.value = 'private'
    }
  },
  { flush: 'post' },
)

function close() {
  emit('update:open', false)
}

async function onSave() {
  if (!name.value.trim()) {
    error.value = t('calendar.validationCalendarName')
    return
  }
  saving.value = true
  error.value = ''
  try {
    if (isEdit.value) {
      const res = await updateCalendar(props.calendar.id, {
        name: name.value.trim(),
        color: color.value,
      })
      if (!res.ok) {
        error.value = `HTTP ${res.status}`
        return
      }
    } else {
      const res = await createCalendar({
        name: name.value.trim(),
        visibility_scope: visibility.value,
        color: color.value,
      })
      if (!res.ok) {
        error.value = `HTTP ${res.status}`
        return
      }
      const data = res.data || {}
      const created = data.calendar
      emit('saved', { created })
      close()
      return
    }
    emit('saved', {})
    close()
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="cc-sheet">
      <div
        v-if="open"
        class="cc-wrap"
        role="presentation"
        @click.self="close"
      >
        <div
          class="cc-dialog"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="'cc-title'"
          @click.stop
        >
            <header class="cc-head">
              <h2 id="cc-title" class="cc-title">
                {{ isEdit ? t('calendar.editCalendarTitle') : t('calendar.newCalendarTitle') }}
              </h2>
              <button type="button" class="cc-close" :aria-label="t('calendar.close')" @click="close">
                ×
              </button>
            </header>

            <div class="cc-body">
              <p v-if="error" class="cc-err">{{ error }}</p>

              <label class="cc-field">
                <span class="cc-label">{{ t('calendar.eventTitle') }}</span>
                <input v-model="name" type="text" class="cc-input" maxlength="120" />
              </label>

              <div v-if="!isEdit" class="cc-field">
                <span class="cc-label">{{ t('calendar.visibility') }}</span>
                <div class="cc-seg">
                  <button
                    type="button"
                    class="cc-seg__btn"
                    :class="{ 'cc-seg__btn--on': visibility === 'private' }"
                    @click="visibility = 'private'"
                  >
                    {{ t('calendar.private') }}
                  </button>
                  <button
                    type="button"
                    class="cc-seg__btn"
                    :class="{ 'cc-seg__btn--on': visibility === 'community' }"
                    @click="visibility = 'community'"
                  >
                    {{ t('calendar.community') }}
                  </button>
                  <button
                    type="button"
                    class="cc-seg__btn"
                    :class="{ 'cc-seg__btn--on': visibility === 'group' }"
                    @click="visibility = 'group'"
                  >
                    {{ t('calendar.group') }}
                  </button>
                </div>
              </div>

              <div class="cc-field">
                <span class="cc-label">{{ t('calendar.color') }}</span>
                <div class="cc-swatches" role="listbox" :aria-label="t('calendar.color')">
                  <button
                    v-for="c in CALENDAR_COLOR_SWATCHES"
                    :key="c"
                    type="button"
                    class="cc-swatch"
                    :class="{ 'cc-swatch--on': color === c }"
                    :style="{ '--sw': c }"
                    :aria-pressed="color === c"
                    @click="color = c"
                  />
                </div>
              </div>
            </div>

            <footer class="cc-foot">
              <button type="button" class="btn btn--ghost" @click="close">
                {{ t('calendar.cancel') }}
              </button>
              <button type="button" class="btn btn--primary" :disabled="saving" @click="onSave">
                {{ saving ? t('calendar.saving') : t('calendar.save') }}
              </button>
            </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped lang="scss">
$bp-md: 768px;

.cc-sheet-enter-active,
.cc-sheet-leave-active {
  transition: opacity 0.22s ease;
}
.cc-sheet-enter-from,
.cc-sheet-leave-to {
  opacity: 0;
}
.cc-sheet-enter-active .cc-dialog,
.cc-sheet-leave-active .cc-dialog {
  transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.22s ease;
}
@media (max-width: 767px) {
  .cc-sheet-enter-from .cc-dialog,
  .cc-sheet-leave-to .cc-dialog {
    transform: translateY(100%);
    opacity: 0.95;
  }
}
@media (min-width: $bp-md) {
  .cc-sheet-enter-from .cc-dialog,
  .cc-sheet-leave-to .cc-dialog {
    transform: scale(0.96) translateY(0.5rem);
    opacity: 0;
  }
}

.cc-wrap {
  position: fixed;
  inset: 0;
  z-index: 1600;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 0;
  overflow: auto;
  @media (min-width: $bp-md) {
    align-items: center;
    padding: 1.5rem;
  }
}

.cc-dialog {
  background: var(--surface, #fff);
  color: var(--text, #0f172a);
  width: 100%;
  max-height: 100dvh;
  border-radius: 1rem 1rem 0 0;
  box-shadow: 0 -8px 40px rgba(0, 0, 0, 0.12);
  display: flex;
  flex-direction: column;
  @media (min-width: $bp-md) {
    width: min(26rem, 100%);
    max-height: calc(100vh - 3rem);
    border-radius: 1rem;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18);
  }
}

.cc-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1rem 0.75rem;
  border-bottom: 1px solid var(--border, #e2e8f0);
  flex-shrink: 0;
}
.cc-title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}
.cc-close {
  border: none;
  background: transparent;
  font-size: 1.75rem;
  line-height: 1;
  cursor: pointer;
  color: var(--text, #0f172a);
  opacity: 0.55;
  padding: 0.25rem;
  border-radius: 0.5rem;
  &:hover {
    opacity: 1;
    background: var(--surface-2, #f1f5f9);
  }
}

.cc-body {
  padding: 1rem;
  overflow: auto;
  display: grid;
  gap: 1rem;
  flex: 1;
}
.cc-err {
  color: #b00020;
  margin: 0;
  font-size: 0.9rem;
}
.cc-field {
  display: grid;
  gap: 0.4rem;
}
.cc-label {
  font-size: 0.8rem;
  font-weight: 600;
  opacity: 0.75;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.cc-input {
  padding: 0.65rem 0.85rem;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 0.65rem;
  font: inherit;
  font-size: 1rem;
}

.cc-seg {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.cc-seg__btn {
  flex: 1;
  min-width: 0;
  padding: 0.5rem 0.4rem;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 0.5rem;
  background: var(--surface, #fff);
  font: inherit;
  font-size: 0.8rem;
  cursor: pointer;
  color: inherit;
  opacity: 0.85;
  &--on {
    border-color: transparent;
    background: var(--surface-2, #f1f5f9);
    font-weight: 600;
    opacity: 1;
    box-shadow: inset 0 0 0 2px var(--border, #e2e8f0);
  }
}

.cc-swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.cc-swatch {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  border: 2px solid transparent;
  background: var(--sw);
  cursor: pointer;
  padding: 0;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
  &:hover {
    transform: scale(1.06);
  }
  &--on {
    box-shadow: 0 0 0 2px var(--surface, #fff), 0 0 0 4px var(--sw);
    transform: scale(1.05);
  }
}

.cc-foot {
  padding: 0.85rem 1rem calc(0.85rem + env(safe-area-inset-bottom, 0));
  border-top: 1px solid var(--border, #e2e8f0);
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-shrink: 0;
  @media (min-width: $bp-md) {
    padding-bottom: 0.85rem;
  }
}
</style>
