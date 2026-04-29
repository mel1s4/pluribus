<script setup>
import { t } from '../../i18n/i18n'
import TaskRow from './TaskRow.vue'

const props = defineProps({
  /** Section heading */
  label: { type: String, required: true },
  /** Folder id for this section: 0 = unfiled, else real folder id */
  folderId: { type: Number, required: true },
  /** @type {Record<string, unknown>[]} */
  tasks: { type: Array, default: () => [] },
  /** Task ids currently playing exit animation */
  exitingIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['toggle-complete', 'toggle-star', 'open', 'delete', 'add-task', 'add-folder'])

function isExiting(task) {
  return props.exitingIds.includes(Number(task.id))
}

function addTaskAria() {
  return `${t('tasks.groupAddTask')}: ${props.label}`
}

function addFolderAria() {
  return `${t('tasks.groupAddFolder')}: ${props.label}`
}
</script>

<template>
  <section class="task-list-group">
    <div class="task-list-group__head">
      <h3 class="task-list-group__title">{{ label }}</h3>
      <div class="task-list-group__actions" role="group" :aria-label="t('tasks.groupHeaderActions')">
        <button
          type="button"
          class="task-list-group__iconBtn"
          :title="t('tasks.groupAddTask')"
          :aria-label="addTaskAria()"
          @click="emit('add-task')"
        >
          <span class="task-list-group__iconGlyph" aria-hidden="true">+</span>
        </button>
        <button
          type="button"
          class="task-list-group__iconBtn"
          :title="t('tasks.groupAddFolder')"
          :aria-label="addFolderAria()"
          @click="emit('add-folder')"
        >
          <span class="task-list-group__iconGlyph" aria-hidden="true">📁</span>
        </button>
      </div>
    </div>
    <ul class="task-list-group__list">
      <li v-for="task in tasks" :key="task.id" class="task-list-group__item">
        <TaskRow
          :task="task"
          :removing="isExiting(task)"
          @toggle-complete="emit('toggle-complete', $event)"
          @toggle-star="emit('toggle-star', $event)"
          @open="emit('open', $event)"
          @delete="emit('delete', $event)"
        />
      </li>
    </ul>
  </section>
</template>

<style scoped lang="scss">
.task-list-group {
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.65rem;
  padding: 0.65rem 0.75rem 0.5rem;
  background: var(--surface, #fff);
}

.task-list-group__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.35rem;
}

.task-list-group__title {
  margin: 0;
  flex: 1;
  min-width: 0;
  font-size: 0.82rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--text-muted, #6b7280);
}

.task-list-group__actions {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.1rem;
}

.task-list-group__iconBtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2rem;
  min-height: 2rem;
  padding: 0;
  border: none;
  border-radius: 0.4rem;
  background: transparent;
  cursor: pointer;
  color: var(--text-muted, #6b7280);
  font: inherit;
}

.task-list-group__iconBtn:hover {
  background: rgba(0, 0, 0, 0.06);
  color: var(--text, #111827);
}

.task-list-group__iconGlyph {
  font-size: 1.05rem;
  line-height: 1;
  font-weight: 600;
}

.task-list-group__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
}

.task-list-group__item {
  border-bottom: 1px solid var(--border, rgba(0, 0, 0, 0.06));
  &:last-child {
    border-bottom: none;
  }
}
</style>
