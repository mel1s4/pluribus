<script setup>
import Button from '../atoms/Button.vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  modelValue: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits(['update:modelValue'])

function positions() {
  return Array.isArray(props.modelValue) ? props.modelValue : []
}

function patch(next) {
  emit('update:modelValue', next)
}

function addPosition() {
  patch([...positions(), { title: '', tasks: [{ body: '' }] }])
}

function removePosition(index) {
  patch(positions().filter((_, i) => i !== index))
}

function updatePositionTitle(index, value) {
  patch(positions().map((p, i) => (i === index ? { ...p, title: value } : p)))
}

function addTask(posIndex) {
  patch(
    positions().map((p, i) =>
      i === posIndex ? { ...p, tasks: [...(Array.isArray(p.tasks) ? p.tasks : []), { body: '' }] } : p,
    ),
  )
}

function removeTask(posIndex, taskIndex) {
  patch(
    positions().map((p, i) =>
      i === posIndex
        ? { ...p, tasks: (Array.isArray(p.tasks) ? p.tasks : []).filter((_, ti) => ti !== taskIndex) }
        : p,
    ),
  )
}

function updateTask(posIndex, taskIndex, value) {
  patch(
    positions().map((p, i) =>
      i === posIndex
        ? {
            ...p,
            tasks: (Array.isArray(p.tasks) ? p.tasks : []).map((t, ti) =>
              ti === taskIndex ? { ...t, body: value } : t,
            ),
          }
        : p,
    ),
  )
}
</script>

<template>
  <div class="project-job-fields">
    <div class="project-job-fields__head">
      <span class="project-job-fields__label">{{ t('communityProjects.jobHeading') }}</span>
      <Button type="button" @click="addPosition">{{ t('communityProjects.jobAddPosition') }}</Button>
    </div>
    <p class="project-job-fields__hint">{{ t('communityProjects.jobHint') }}</p>
    <div v-for="(pos, posIndex) in positions()" :key="posIndex" class="project-job-fields__position">
      <div class="project-job-fields__position-head">
        <label class="project-job-fields__field">
          <span>{{ t('communityProjects.jobTitle') }}</span>
          <input
            :value="pos.title"
            type="text"
            class="project-job-fields__input"
            maxlength="255"
            @input="updatePositionTitle(posIndex, $event.target.value)"
          />
        </label>
        <Button type="button" class="project-job-fields__remove-pos" @click="removePosition(posIndex)">
          {{ t('communityProjects.jobRemovePosition') }}
        </Button>
      </div>
      <ul class="project-job-fields__tasks" role="list">
        <li v-for="(task, taskIndex) in pos.tasks || []" :key="taskIndex" class="project-job-fields__task">
          <label class="project-job-fields__field project-job-fields__field--task">
            <span>{{ t('communityProjects.jobTask') }}</span>
            <input
              :value="task.body"
              type="text"
              class="project-job-fields__input"
              @input="updateTask(posIndex, taskIndex, $event.target.value)"
            />
          </label>
          <Button type="button" @click="removeTask(posIndex, taskIndex)">{{ t('communityProjects.jobRemoveTask') }}</Button>
        </li>
      </ul>
      <Button type="button" class="project-job-fields__add-task" @click="addTask(posIndex)">
        {{ t('communityProjects.jobAddTask') }}
      </Button>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.project-job-fields {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}
.project-job-fields__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}
.project-job-fields__label {
  font-weight: 600;
}
.project-job-fields__hint {
  margin: 0;
  font-size: 0.85rem;
  opacity: 0.8;
}
.project-job-fields__position {
  padding: 0.65rem 0.75rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}
.project-job-fields__position-head {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: flex-end;
}
.project-job-fields__field {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.82rem;
  flex: 1;
  min-width: 12rem;
}
.project-job-fields__field--task {
  flex: 1;
}
.project-job-fields__input {
  font: inherit;
  padding: 0.35rem 0.45rem;
  border-radius: 0.35rem;
  border: 1px solid var(--border);
}
.project-job-fields__tasks {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.project-job-fields__task {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: flex-end;
}
.project-job-fields__add-task,
.project-job-fields__remove-pos {
  font-size: 0.85rem;
  align-self: flex-start;
}
</style>
