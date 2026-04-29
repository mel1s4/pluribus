<script setup>
import { computed } from 'vue'
import { t } from '../../i18n/i18n'
import { formatTaskRowMeta } from './taskRowMeta.js'

const props = defineProps({
  task: { type: Object, required: true },
  maxTagPills: { type: Number, default: 2 },
  /** Row is animating out before removal */
  removing: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle-complete', 'toggle-star', 'open', 'delete'])

const meta = computed(() => formatTaskRowMeta(props.task))

const tags = computed(() => (Array.isArray(props.task.tags) ? props.task.tags : []).filter(Boolean))

const visibleTags = computed(() => tags.value.slice(0, props.maxTagPills))

const moreTagCount = computed(() => Math.max(0, tags.value.length - props.maxTagPills))

function onDeleteClick() {
  emit('delete', props.task)
}
</script>

<template>
  <div
    class="task-row"
    :class="{ 'task-row--exiting': removing }"
    role="button"
    tabindex="0"
    @click="emit('open', task)"
    @keydown.enter.prevent="emit('open', task)"
  >
    <label class="task-row__check" @click.stop>
      <input
        type="checkbox"
        :checked="Boolean(task.completed_at)"
        @change="emit('toggle-complete', task)"
      />
    </label>
    <div class="task-row__body">
      <span class="task-row__title" :class="{ done: task.completed_at }">
        {{ task.title || t('tasks.untitled') }}
      </span>
      <div v-if="meta || visibleTags.length || moreTagCount" class="task-row__meta">
        <span v-if="meta" class="task-row__date">{{ meta }}</span>
        <span v-for="tag in visibleTags" :key="tag" class="task-row__pill">{{ tag }}</span>
        <span v-if="moreTagCount" class="task-row__more">+{{ moreTagCount }}</span>
      </div>
    </div>
    <button
      type="button"
      class="task-row__star"
      :class="{ 'is-on': task.highlighted }"
      :aria-pressed="Boolean(task.highlighted)"
      :aria-label="t('tasks.toggleHighlight')"
      @click.stop="emit('toggle-star', task)"
    >
      ★
    </button>
    <button
      type="button"
      class="task-row__del"
      :aria-label="t('tasks.deleteTask')"
      @click.stop="onDeleteClick"
    >
      <span class="task-row__delIcon" aria-hidden="true">🗑</span>
    </button>
  </div>
</template>

<style scoped lang="scss">
.task-row {
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  padding: 0.55rem 0.35rem;
  border-radius: 0.45rem;
  cursor: pointer;
  transition:
    background 120ms ease,
    opacity 0.22s ease,
    transform 0.22s ease,
    max-height 0.28s ease,
    margin 0.28s ease,
    padding 0.28s ease;
  max-height: 12rem;

  &:hover,
  &:focus-within {
    background: var(--surface-2, rgba(0, 0, 0, 0.04));
  }
}

.task-row--exiting {
  opacity: 0;
  transform: translateX(0.35rem);
  max-height: 0;
  margin-top: 0;
  margin-bottom: 0;
  padding-top: 0;
  padding-bottom: 0;
  overflow: hidden;
  pointer-events: none;
}

.task-row__check {
  padding-top: 0.1rem;
  flex-shrink: 0;
}

.task-row__body {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 0.2rem;
}

.task-row__title {
  font-size: 0.95rem;
  line-height: 1.35;
  &.done {
    text-decoration: line-through;
    opacity: 0.65;
  }
}

.task-row__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.78rem;
  color: var(--text-muted, #6b7280);
}

.task-row__pill {
  background: var(--surface-2, rgba(0, 0, 0, 0.06));
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  max-width: 8rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-row__star {
  flex-shrink: 0;
  border: none;
  background: transparent;
  font-size: 1.1rem;
  line-height: 1;
  cursor: pointer;
  padding: 0.2rem 0.35rem;
  color: var(--text-muted, #9ca3af);
  opacity: 0.55;
  &.is-on {
    color: #ca8a04;
    opacity: 1;
  }
  &:hover {
    opacity: 1;
  }
}

.task-row__del {
  flex-shrink: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  padding: 0.2rem 0.35rem;
  line-height: 1;
  opacity: 0;
  transition: opacity 0.15s ease;
  border-radius: 0.35rem;
  &:hover {
    background: rgba(176, 0, 32, 0.08);
  }
}

.task-row__delIcon {
  font-size: 0.95rem;
  filter: grayscale(0.2);
}

.task-row:hover .task-row__del,
.task-row:focus-within .task-row__del {
  opacity: 0.85;
}

@media (max-width: 767px) {
  .task-row__del {
    opacity: 0.65;
  }
}
</style>
