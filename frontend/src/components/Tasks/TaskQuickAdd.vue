<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import Button from '../../atoms/Button.vue'
import { t } from '../../i18n/i18n'
import { fromDatetimeLocal } from '../../utils/datetimeLocal.js'

const props = defineProps({
  folders: { type: Array, default: () => [] },
  /** Default folder id as string, '' unfiled */
  defaultFolderId: { type: String, default: '' },
  /** Tighter layout; collapse extras when entering mobile */
  isMobile: { type: Boolean, default: false },
})

const emit = defineEmits(['submit'])

const titleInputRef = ref(null)
const title = ref('')
const folderId = ref('')
const expanded = ref(false)
const description = ref('')
const startLocal = ref('')

watch(
  () => props.defaultFolderId,
  (v) => {
    folderId.value = v != null ? String(v) : ''
  },
  { immediate: true },
)

watch(
  () => props.isMobile,
  (m) => {
    if (m) expanded.value = false
  },
)

const canSubmit = computed(() => Boolean(title.value.trim()))

function reset() {
  title.value = ''
  description.value = ''
  startLocal.value = ''
  expanded.value = false
  folderId.value = props.defaultFolderId != null ? String(props.defaultFolderId) : ''
}

function onSubmit() {
  if (!canSubmit.value) return
  const payload = {
    title: title.value.trim(),
    folder_id: folderId.value === '' || folderId.value === '0' ? null : Number(folderId.value),
    description: description.value.trim() || null,
    start_at: fromDatetimeLocal(startLocal.value),
    visibility_scope: 'private',
  }
  emit('submit', payload)
  reset()
}

/** @param {string} folderIdStr '' = unfiled, else numeric id string */
function presetAndFocus(folderIdStr) {
  folderId.value = folderIdStr != null && folderIdStr !== '' ? String(folderIdStr) : ''
  nextTick(() => {
    titleInputRef.value?.focus?.()
    titleInputRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'nearest' })
  })
}

defineExpose({ reset, presetAndFocus })
</script>

<template>
  <div class="task-quick-add" :class="{ 'task-quick-add--mobile': isMobile }">
    <div class="task-quick-add__row">
      <input
        ref="titleInputRef"
        v-model="title"
        class="task-quick-add__title"
        type="text"
        maxlength="255"
        :placeholder="t('tasks.titlePlaceholder')"
        @keydown.enter.prevent="onSubmit"
      />
      <select v-model="folderId" class="task-quick-add__folder">
        <option value="">{{ t('tasks.unfiled') }}</option>
        <option v-for="folder in folders" :key="folder.id" :value="String(folder.id)">
          {{ folder.name }}
        </option>
      </select>
      <div class="task-quick-add__actions">
        <Button variant="primary" size="sm" :disabled="!canSubmit" @click="onSubmit">{{ t('tasks.add') }}</Button>
        <button
          type="button"
          class="task-quick-add__more"
          :aria-expanded="expanded"
          :aria-label="expanded ? t('tasks.quickAddLess') : t('tasks.quickAddMore')"
          @click="expanded = !expanded"
        >
          <span v-if="isMobile" class="task-quick-add__moreGlyph" aria-hidden="true">+</span>
          <span v-else>{{ expanded ? t('tasks.quickAddLess') : t('tasks.quickAddMore') }}</span>
        </button>
      </div>
    </div>
    <div v-if="expanded" class="task-quick-add__extra">
      <label class="task-quick-add__field">
        <span>{{ t('tasks.description') }}</span>
        <textarea
          v-model="description"
          :rows="isMobile ? 2 : 3"
          maxlength="10000"
          :placeholder="t('tasks.descriptionPlaceholder')"
        />
      </label>
      <label class="task-quick-add__field">
        <span>{{ t('tasks.dueOrStart') }}</span>
        <input v-model="startLocal" type="datetime-local" />
      </label>
    </div>
  </div>
</template>

<style scoped lang="scss">
.task-quick-add {
  display: grid;
  gap: 0.5rem;
  padding: 0.65rem 0;
  border-radius: 0.75rem;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:focus-within {
    background: linear-gradient(135deg, 
      rgba(59, 130, 246, 0.02) 0%, 
      rgba(147, 197, 253, 0.03) 50%, 
      rgba(59, 130, 246, 0.02) 100%);
    box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.08),
                0 2px 8px rgba(59, 130, 246, 0.06);
    transform: translateY(-1px);
  }
}

.task-quick-add__row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
}

.task-quick-add__actions {
  display: contents;
}

.task-quick-add__title {
  flex: 1 1 10rem;
  min-width: 0;
  padding: 0.5rem 0.65rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  font: inherit;
  background: var(--bg, #fff);
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:focus {
    outline: none;
    border-color: #3b82f6;
    background: #fefefe;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1),
                0 1px 3px rgba(0, 0, 0, 0.1),
                0 4px 12px rgba(59, 130, 246, 0.15);
    transform: translateY(-1px);
  }
  
  &::placeholder {
    color: var(--text-muted, #9ca3af);
    transition: color 0.2s ease;
  }
  
  &:focus::placeholder {
    color: rgba(59, 130, 246, 0.6);
  }
}

.task-quick-add__folder {
  flex: 0 1 12rem;
  padding: 0.5rem 0.5rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  font: inherit;
  background: var(--bg, #fff);
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }
}

.task-quick-add__more {
  border: 1px solid var(--border, #e5e7eb);
  background: var(--surface, #fff);
  font: inherit;
  font-size: 0.85rem;
  color: var(--text-muted, #6b7280);
  cursor: pointer;
  padding: 0.35rem 0.55rem;
  border-radius: 0.45rem;
  line-height: 1.2;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: rgba(59, 130, 246, 0.04);
  }
  
  &:focus {
    outline: none;
    border-color: #3b82f6;
    color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }
  
  &[aria-expanded="true"] {
    border-color: #3b82f6;
    color: #3b82f6;
    background: rgba(59, 130, 246, 0.08);
  }
}

.task-quick-add__moreGlyph {
  display: inline-block;
  font-weight: 700;
  font-size: 1.1rem;
  line-height: 1;
  min-width: 1.25rem;
  text-align: center;
}

.task-quick-add__extra {
  display: grid;
  gap: 0.5rem;
  padding: 0.5rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  background: var(--surface-2, rgba(0, 0, 0, 0.02));
}

.task-quick-add__field {
  display: grid;
  gap: 0.25rem;
  font-size: 0.875rem;
  span {
    color: var(--text-muted, #6b7280);
  }
  textarea {
    font: inherit;
    padding: 0.45rem 0.5rem;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.4rem;
    resize: vertical;
    background: var(--bg, #fff);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    
    &:focus {
      outline: none;
      border-color: #3b82f6;
      background: #fefefe;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1),
                  0 1px 3px rgba(0, 0, 0, 0.1);
      transform: translateY(-1px);
    }
    
    &::placeholder {
      color: var(--text-muted, #9ca3af);
      transition: color 0.2s ease;
    }
    
    &:focus::placeholder {
      color: rgba(59, 130, 246, 0.6);
    }
  }
  input[type='datetime-local'] {
    font: inherit;
    padding: 0.45rem 0.5rem;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.4rem;
    background: var(--bg, #fff);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    
    &:focus {
      outline: none;
      border-color: #3b82f6;
      background: #fefefe;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1),
                  0 1px 3px rgba(0, 0, 0, 0.1);
      transform: translateY(-1px);
    }
  }
}

@media (max-width: 767px) {
  .task-quick-add--mobile {
    padding: 0.35rem 0;
    gap: 0.35rem;
  }

  .task-quick-add--mobile .task-quick-add__row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.35rem 0.45rem;
    align-items: stretch;
  }

  .task-quick-add--mobile .task-quick-add__title {
    grid-column: 1 / -1;
    width: 100%;
    min-height: 2.5rem;
    max-height: 2.5rem;
    padding: 0.35rem 0.55rem;
    font-size: 1rem;
    line-height: 1.25;
    box-sizing: border-box;
    
    &:focus {
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12),
                  0 2px 8px rgba(59, 130, 246, 0.2),
                  0 4px 16px rgba(59, 130, 246, 0.1);
      transform: translateY(-2px);
    }
  }

  .task-quick-add--mobile .task-quick-add__folder {
    grid-column: 1;
    width: auto;
    min-width: 0;
    min-height: 2.5rem;
    max-height: 2.5rem;
    padding: 0.3rem 0.45rem;
    font-size: 0.95rem;
    line-height: 1.2;
    box-sizing: border-box;
  }

  .task-quick-add--mobile .task-quick-add__actions {
    display: flex;
    grid-column: 2;
    grid-row: 2;
    flex-direction: row;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: nowrap;
  }

  .task-quick-add--mobile .task-quick-add__actions :deep(.btn) {
    width: auto;
    flex-shrink: 0;
    padding-left: 0.65rem;
    padding-right: 0.65rem;
  }

  .task-quick-add--mobile .task-quick-add__more {
    flex-shrink: 0;
    min-height: 2.25rem;
    min-width: 2.25rem;
    padding: 0.25rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .task-quick-add--mobile .task-quick-add__extra {
    padding: 0.45rem;
    gap: 0.4rem;
  }
}
</style>
