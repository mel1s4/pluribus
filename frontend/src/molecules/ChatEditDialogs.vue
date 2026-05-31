<script setup>
import { nextTick, reactive, ref } from 'vue'
import ChatColorPicker from './ChatColorPicker.vue'
import ChatIconPicker from './ChatIconPicker.vue'
import Button from '../atoms/Button.vue'
import { t } from '../i18n/i18n'
import { updateChat } from '../services/chatApi.js'

const emit = defineEmits(['saved'])

const renameDialogRef = ref(null)
const renameTargetId = ref(null)
const renameTitleDraft = ref('')
const renameSaving = ref(false)

const editDialogRef = ref(null)
const editTargetId = ref(null)
const editSaving = ref(false)
const editForm = reactive({
  title: '',
  icon_emoji: '💬',
  icon_bg_color: '#2563eb',
})

function onRenameDialogBackdrop(e) {
  if (e.target === renameDialogRef.value) renameDialogRef.value?.close()
}

function onEditDialogBackdrop(e) {
  if (e.target === editDialogRef.value) editDialogRef.value?.close()
}

async function openRenameDialog(chat) {
  renameTargetId.value = chat.id
  renameTitleDraft.value = chat.title || ''
  await nextTick()
  renameDialogRef.value?.showModal()
}

async function submitRename() {
  const id = renameTargetId.value
  if (id == null) return
  renameSaving.value = true
  const res = await updateChat(id, { title: renameTitleDraft.value.trim() })
  renameSaving.value = false
  if (!res.ok) return
  renameDialogRef.value?.close()
  renameTargetId.value = null
  emit('saved')
}

async function openEditDialog(chat) {
  editTargetId.value = chat.id
  editForm.title = chat.title || ''
  editForm.icon_emoji = chat.icon_emoji || '💬'
  editForm.icon_bg_color = /^#[0-9A-Fa-f]{6}$/.test(chat.icon_bg_color || '')
    ? chat.icon_bg_color
    : '#2563eb'
  await nextTick()
  editDialogRef.value?.showModal()
}

async function submitEdit() {
  const id = editTargetId.value
  if (id == null) return
  const payload = { title: editForm.title.trim() || null }
  if (editForm.icon_emoji?.trim()) payload.icon_emoji = editForm.icon_emoji.trim()
  if (editForm.icon_bg_color && /^#[0-9A-Fa-f]{6}$/.test(editForm.icon_bg_color)) {
    payload.icon_bg_color = editForm.icon_bg_color
  }
  editSaving.value = true
  const res = await updateChat(id, payload)
  editSaving.value = false
  if (!res.ok) return
  editDialogRef.value?.close()
  editTargetId.value = null
  emit('saved')
}

defineExpose({
  openRenameDialog,
  openEditDialog,
})
</script>

<template>
  <dialog
    ref="renameDialogRef"
    class="chat-edit-dialogs__dialog"
    aria-labelledby="chat-edit-rename-title"
    @click="onRenameDialogBackdrop"
  >
    <div class="chat-edit-dialogs__panel" @click.stop>
      <h2 id="chat-edit-rename-title" class="chat-edit-dialogs__title">
        {{ t('chats.modal.renameChatTitle') }}
      </h2>
      <div class="chat-edit-dialogs__field">
        <label for="chat-edit-rename-input">{{ t('chats.info.editTitle') }}</label>
        <input id="chat-edit-rename-input" v-model="renameTitleDraft" type="text">
      </div>
      <div class="chat-edit-dialogs__actions">
        <Button type="button" variant="secondary" size="sm" @click="renameDialogRef?.close()">
          {{ t('chats.modal.cancel') }}
        </Button>
        <Button type="button" size="sm" :disabled="renameSaving" @click="submitRename">
          {{ t('chats.info.save') }}
        </Button>
      </div>
    </div>
  </dialog>

  <dialog
    ref="editDialogRef"
    class="chat-edit-dialogs__dialog"
    aria-labelledby="chat-edit-appearance-title"
    @click="onEditDialogBackdrop"
  >
    <div class="chat-edit-dialogs__panel" @click.stop>
      <h2 id="chat-edit-appearance-title" class="chat-edit-dialogs__title">
        {{ t('chats.modal.editChatTitle') }}
      </h2>
      <div class="chat-edit-dialogs__field">
        <label for="chat-edit-title-input">{{ t('chats.info.editTitle') }}</label>
        <input id="chat-edit-title-input" v-model="editForm.title" type="text">
      </div>
      <div class="chat-edit-dialogs__field">
        <span class="chat-edit-dialogs__label">{{ t('chats.info.editIcon') }}</span>
        <ChatIconPicker v-model="editForm.icon_emoji" />
      </div>
      <div class="chat-edit-dialogs__field">
        <span class="chat-edit-dialogs__label">{{ t('chats.info.editColor') }}</span>
        <ChatColorPicker v-model="editForm.icon_bg_color" />
      </div>
      <div class="chat-edit-dialogs__actions">
        <Button type="button" variant="secondary" size="sm" @click="editDialogRef?.close()">
          {{ t('chats.modal.cancel') }}
        </Button>
        <Button type="button" size="sm" :disabled="editSaving" @click="submitEdit">
          {{ t('chats.info.save') }}
        </Button>
      </div>
    </div>
  </dialog>
</template>

<style scoped lang="scss">
.chat-edit-dialogs__dialog {
  border: none;
  padding: 0;
  background: transparent;
  max-width: calc(100vw - 2rem);
}

.chat-edit-dialogs__dialog::backdrop {
  background: rgba(0, 0, 0, 0.45);
}

.chat-edit-dialogs__panel {
  background: var(--bg, #fff);
  border-radius: 0.75rem;
  padding: 1.25rem;
  min-width: min(22rem, 100vw - 2rem);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.chat-edit-dialogs__title {
  margin: 0 0 1rem;
  font-size: 1.1rem;
}

.chat-edit-dialogs__field {
  margin-bottom: 1rem;

  label,
  .chat-edit-dialogs__label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 0.35rem;
    color: var(--text-muted, #6b7280);
  }

  input[type='text'] {
    width: 100%;
    box-sizing: border-box;
    padding: 0.5rem 0.65rem;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.5rem;
    font: inherit;
  }
}

.chat-edit-dialogs__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.5rem;
}
</style>
