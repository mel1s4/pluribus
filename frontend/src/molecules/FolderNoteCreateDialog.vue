<script setup>
import { reactive, ref } from 'vue'
import Button from '../atoms/Button.vue'
import { t } from '../i18n/i18n'
import { createNote } from '../services/notesApi.js'

const props = defineProps({
  /** When true, note is always created in this folder (folder detail page). */
  lockFolder: { type: Boolean, default: true },
  /** Used when lockFolder is true. */
  folderId: { type: Number, default: null },
  /** Used when lockFolder is false: folder picker options (folders index page). */
  folders: { type: Array, default: () => [] },
})

const emit = defineEmits(['created'])

const dialogRef = ref(null)
const form = reactive({
  title: '',
  description: '',
  folder_id: null,
})

function showModal() {
  if (!props.lockFolder && Array.isArray(props.folders) && props.folders.length) {
    form.folder_id = Number(props.folders[0].id)
  }
  dialogRef.value?.showModal()
}

defineExpose({
  showModal,
  close: () => dialogRef.value?.close(),
})

function onBackdrop(e) {
  if (e.target === dialogRef.value) dialogRef.value?.close()
}

function resetForm() {
  form.title = ''
  form.description = ''
  form.folder_id = null
}

function onDialogClose() {
  resetForm()
}

async function submit() {
  const title = form.title.trim()
  if (!title) return
  const folder_id = props.lockFolder ? props.folderId : form.folder_id
  if (folder_id == null || !Number.isFinite(Number(folder_id))) return
  const res = await createNote({
    title,
    description: form.description.trim() || null,
    folder_id,
  })
  if (!res.ok) return
  dialogRef.value?.close()
  resetForm()
  const id = res.data?.note?.id
  emit('created', id)
}
</script>

<template>
  <dialog ref="dialogRef" class="folder-note-create-dialog" @click="onBackdrop" @close="onDialogClose">
    <form class="folder-note-create-dialog__panel" @submit.prevent="submit" @click.stop>
      <h2>{{ t('folders.newNoteTitle') }}</h2>
      <p v-if="!lockFolder && !folders.length" class="folder-note-create-dialog__hint">
        {{ t('folders.newNoteNeedFolder') }}
      </p>
      <label v-else-if="!lockFolder" class="folder-note-create-dialog__field">
        <span>{{ t('folders.parentLabel') }}</span>
        <select v-model.number="form.folder_id">
          <option v-for="x in folders" :key="x.id" :value="Number(x.id)">{{ x.name }}</option>
        </select>
      </label>
      <label class="folder-note-create-dialog__field">
        <span>{{ t('notes.titleLabel') }}</span>
        <input v-model="form.title" :placeholder="t('notes.titlePlaceholder')" required maxlength="255">
      </label>
      <label class="folder-note-create-dialog__field">
        <span>{{ t('notes.descriptionLabel') }}</span>
        <textarea v-model="form.description" :placeholder="t('notes.descriptionPlaceholder')" rows="3" maxlength="2000"></textarea>
      </label>
      <div class="folder-note-create-dialog__actions">
        <Button type="button" variant="secondary" @click="dialogRef.close()">{{ t('folders.cancel') }}</Button>
        <Button type="submit" :disabled="!lockFolder && !folders.length">{{ t('folders.create') }}</Button>
      </div>
    </form>
  </dialog>
</template>

<style scoped lang="scss">
.folder-note-create-dialog {
  border: none;
  padding: 0;
  max-width: 26rem;
  width: calc(100% - 2rem);
  background: transparent;
  border-radius: 0.75rem;

  &::backdrop {
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(2px);
  }
}

.folder-note-create-dialog__panel {
  background: var(--bg, #fff);
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.75rem;
  padding: 1.5rem;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);

  h2 {
    margin: 0 0 1.1rem;
    font-size: 1.1rem;
    font-weight: 700;
  }
}

.folder-note-create-dialog__field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin: 0.75rem 0;

  > span {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-muted, #374151);
  }
}

.folder-note-create-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1.25rem;
}

.folder-note-create-dialog__hint {
  margin: 0 0 0.75rem;
  font-size: 0.875rem;
  color: var(--text-muted, #6b7280);
  line-height: 1.4;
}
</style>
