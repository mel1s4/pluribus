<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import Button from '../../atoms/Button.vue'
import { t } from '../../i18n/i18n'
import { deleteTask, updateTask } from '../../services/contentApi'
import { fromDatetimeLocal, toDatetimeLocal } from '../../utils/datetimeLocal.js'

const props = defineProps({
  open: { type: Boolean, default: false },
  task: { type: Object, default: null },
  calendars: { type: Array, default: () => [] },
  groups: { type: Array, default: () => [] },
  folders: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:open', 'saved', 'deleted'])

const saving = ref(false)
const error = ref('')
const isMobileLayout = ref(false)

const title = ref('')
const description = ref('')
const folderId = ref('')
const calendarId = ref('')
const startLocal = ref('')
const endLocal = ref('')
const allDay = ref(false)
const tagsStr = ref('')
const visibilityScope = ref('private')
const sharedGroupId = ref('')
const completed = ref(false)

function mq() {
  isMobileLayout.value = globalThis.matchMedia?.('(max-width: 767px)')?.matches ?? false
}

function fillFromTask(tk) {
  if (!tk) return
  title.value = tk.title || ''
  description.value = tk.description || ''
  folderId.value = tk.folder_id != null ? String(tk.folder_id) : ''
  calendarId.value = tk.calendar_id != null ? String(tk.calendar_id) : ''
  allDay.value = Boolean(tk.all_day)
  startLocal.value = tk.start_at ? toDatetimeLocal(tk.start_at) : ''
  endLocal.value = tk.end_at ? toDatetimeLocal(tk.end_at) : ''
  tagsStr.value = (Array.isArray(tk.tags) ? tk.tags : []).join(', ')
  visibilityScope.value = tk.visibility_scope || 'private'
  sharedGroupId.value = tk.shared_group_id != null ? String(tk.shared_group_id) : ''
  completed.value = Boolean(tk.completed_at)
  error.value = ''
}

function close() {
  emit('update:open', false)
}

function onBackdrop() {
  close()
}

watch(
  () => [props.open, props.task?.id],
  () => {
    if (props.open && props.task) fillFromTask(props.task)
  },
  { flush: 'post' },
)

watch(
  () => props.open,
  (v) => {
    if (typeof document === 'undefined') return
    document.body.style.overflow = v && props.task ? 'hidden' : ''
  },
)

function onKeydown(e) {
  if (!props.open || !props.task) return
  if (e.key === 'Escape') {
    e.preventDefault()
    close()
  }
}

onMounted(() => {
  mq()
  globalThis.addEventListener('resize', mq)
  globalThis.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  globalThis.removeEventListener('resize', mq)
  globalThis.removeEventListener('keydown', onKeydown)
  if (typeof document !== 'undefined') document.body.style.overflow = ''
})

function parseTags(s) {
  return s
    .split(',')
    .map((x) => x.trim())
    .filter(Boolean)
    .slice(0, 50)
}

async function onSave() {
  if (!props.task) return
  error.value = ''
  if (!title.value.trim()) {
    error.value = t('tasks.validationTitle')
    return
  }
  saving.value = true
  try {
    const completedAt = completed.value
      ? props.task.completed_at || new Date().toISOString()
      : null
    const payload = {
      title: title.value.trim(),
      description: description.value.trim() || null,
      folder_id: folderId.value === '' ? null : Number(folderId.value),
      calendar_id: calendarId.value === '' ? null : Number(calendarId.value),
      start_at: fromDatetimeLocal(startLocal.value),
      end_at: fromDatetimeLocal(endLocal.value),
      all_day: allDay.value,
      tags: parseTags(tagsStr.value),
      visibility_scope: visibilityScope.value,
      shared_group_id:
        visibilityScope.value === 'group' && sharedGroupId.value
          ? Number(sharedGroupId.value)
          : null,
      completed_at: completedAt,
    }
    const res = await updateTask(props.task.id, payload)
    if (!res.ok) {
      error.value = `HTTP ${res.status}`
      return
    }
    emit('saved')
    close()
  } finally {
    saving.value = false
  }
}

async function onDelete() {
  if (!props.task) return
  if (!globalThis.confirm?.(t('tasks.confirmDelete'))) return
  saving.value = true
  error.value = ''
  try {
    const res = await deleteTask(props.task.id)
    if (!res.ok) {
      error.value = `HTTP ${res.status}`
      return
    }
    emit('deleted')
    close()
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="tds">
      <div
        v-if="open && task"
        class="tds-wrap"
        :class="{ 'tds-wrap--mobile': isMobileLayout }"
      >
        <div class="tds-backdrop" @click="onBackdrop" />
        <aside class="tds-sidebar" @click.stop>
            <form class="tds-panel" @submit.prevent="onSave">
              <header class="tds-head">
                <h2 class="tds-h">{{ t('tasks.detailTitle') }}</h2>
                <button type="button" class="tds-x" :aria-label="t('tasks.closeSheet')" @click="close">
                  ×
                </button>
              </header>

              <div class="tds-body">
                <p v-if="error" class="tds-err">{{ error }}</p>

                <p v-if="task.recurrence_rule" class="tds-hint">{{ t('tasks.recurringReadOnly') }}</p>

                <label v-if="task.assignee" class="tds-field tds-readonly">
                  <span>{{ t('tasks.assignee') }}</span>
                  <span class="tds-assignee">
                    <img
                      v-if="task.assignee.avatar_url"
                      :src="task.assignee.avatar_url"
                      alt=""
                      class="tds-avatar"
                    />
                    {{ task.assignee.name }}
                  </span>
                </label>

                <label class="tds-field tds-row">
                  <input v-model="completed" type="checkbox" />
                  <span>{{ t('tasks.markComplete') }}</span>
                </label>

                <label class="tds-field">
                  <span>{{ t('tasks.fieldTitle') }}</span>
                  <input v-model="title" type="text" maxlength="255" required />
                </label>

                <label class="tds-field">
                  <span>{{ t('tasks.description') }}</span>
                  <textarea v-model="description" rows="4" maxlength="10000" />
                </label>

                <label class="tds-field">
                  <span>{{ t('tasks.fieldFolder') }}</span>
                  <select v-model="folderId">
                    <option value="">{{ t('tasks.unfiled') }}</option>
                    <option v-for="f in folders" :key="f.id" :value="String(f.id)">{{ f.name }}</option>
                  </select>
                </label>

                <label class="tds-field">
                  <span>{{ t('tasks.fieldCalendar') }}</span>
                  <select v-model="calendarId">
                    <option value="">{{ t('tasks.noCalendar') }}</option>
                    <option v-for="c in calendars" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                  </select>
                </label>

                <p v-if="task.calendar_id" class="tds-linkRow">
                  <RouterLink class="tds-link" :to="{ name: 'calendar' }">
                    {{ t('tasks.openInCalendar') }}
                  </RouterLink>
                </p>

                <label class="tds-field tds-row">
                  <input v-model="allDay" type="checkbox" />
                  <span>{{ t('tasks.allDay') }}</span>
                </label>

                <label class="tds-field">
                  <span>{{ t('tasks.start') }}</span>
                  <input v-model="startLocal" type="datetime-local" />
                </label>
                <label class="tds-field">
                  <span>{{ t('tasks.end') }}</span>
                  <input v-model="endLocal" type="datetime-local" />
                </label>

                <label class="tds-field">
                  <span>{{ t('tasks.tagsHint') }}</span>
                  <input v-model="tagsStr" type="text" :placeholder="t('tasks.tagsPlaceholder')" />
                </label>

                <label class="tds-field">
                  <span>{{ t('tasks.visibility') }}</span>
                  <select v-model="visibilityScope">
                    <option value="private">{{ t('tasks.visPrivate') }}</option>
                    <option value="community">{{ t('tasks.visCommunity') }}</option>
                    <option value="group">{{ t('tasks.visGroup') }}</option>
                  </select>
                </label>

                <label v-if="visibilityScope === 'group'" class="tds-field">
                  <span>{{ t('tasks.sharedGroup') }}</span>
                  <select v-model="sharedGroupId">
                    <option value="">{{ t('tasks.pickGroup') }}</option>
                    <option v-for="g in groups" :key="g.id" :value="String(g.id)">{{ g.name }}</option>
                  </select>
                </label>
              </div>

              <footer class="tds-foot">
                <Button type="button" variant="secondary" @click="close">{{ t('tasks.cancel') }}</Button>
                <Button type="button" variant="danger" :disabled="saving" @click="onDelete">
                  {{ t('tasks.delete') }}
                </Button>
                <Button type="submit" variant="primary" :loading="saving" :disabled="saving">
                  {{ t('tasks.save') }}
                </Button>
              </footer>
            </form>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped lang="scss">
.tds-wrap {
  position: fixed;
  inset: 0;
  z-index: 2000;
  pointer-events: auto;
}

.tds-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.22);
  opacity: 1;
}

.tds-wrap--mobile .tds-backdrop {
  background: rgba(15, 23, 42, 0.48);
}

.tds-sidebar {
  position: absolute;
  top: 0;
  right: 0;
  width: min(24rem, 100vw);
  height: 100%;
  max-height: 100dvh;
  background: var(--surface, #fff);
  color: var(--text, #0f172a);
  box-shadow: -6px 0 28px rgba(0, 0, 0, 0.12);
  display: flex;
  flex-direction: column;
}

.tds-wrap--mobile .tds-sidebar {
  left: 0;
  right: 0;
  width: 100%;
  max-height: 100dvh;
  height: 100%;
  box-shadow: none;
}

.tds-panel {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  overflow: hidden;
}

.tds-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border, #e5e7eb);
  flex-shrink: 0;
}

.tds-h {
  margin: 0;
  font-size: 1.05rem;
}

.tds-x {
  border: none;
  background: transparent;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
  color: var(--text-muted, #6b7280);
  padding: 0.2rem 0.45rem;
}

.tds-body {
  padding: 0.85rem 1rem;
  overflow: auto;
  display: grid;
  gap: 0.65rem;
  flex: 1;
  min-height: 0;
  -webkit-overflow-scrolling: touch;
}

.tds-foot {
  padding: 0.75rem 1rem;
  border-top: 1px solid var(--border, #e5e7eb);
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  justify-content: flex-end;
  flex-shrink: 0;
  padding-bottom: max(0.75rem, env(safe-area-inset-bottom, 0));
}

.tds-field {
  display: grid;
  gap: 0.25rem;
  font-size: 0.875rem;
  span {
    color: var(--text-muted, #6b7280);
  }
  input[type='text'],
  select,
  textarea {
    font: inherit;
    padding: 0.45rem 0.5rem;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.4rem;
  }
}

.tds-row {
  grid-template-columns: auto 1fr;
  align-items: center;
}

.tds-readonly span:last-child {
  padding: 0.35rem 0;
}

.tds-assignee {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.tds-avatar {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 999px;
  object-fit: cover;
}

.tds-hint {
  margin: 0;
  font-size: 0.82rem;
  color: var(--text-muted, #6b7280);
  padding: 0.35rem 0.5rem;
  background: var(--surface-2, rgba(0, 0, 0, 0.04));
  border-radius: 0.35rem;
}

.tds-err {
  margin: 0;
  color: #b00020;
  font-size: 0.875rem;
}

.tds-linkRow {
  margin: 0;
}

.tds-link {
  font-size: 0.875rem;
}

.tds-enter-active,
.tds-leave-active {
  .tds-backdrop {
    transition: opacity 0.22s ease;
  }
  .tds-sidebar {
    transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
  }
}

.tds-enter-from,
.tds-leave-to {
  .tds-backdrop {
    opacity: 0;
  }
  .tds-sidebar {
    transform: translateX(100%);
  }
}

.tds-wrap--mobile.tds-enter-from,
.tds-wrap--mobile.tds-leave-to {
  .tds-sidebar {
    transform: translateY(100%);
  }
}
</style>
