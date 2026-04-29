<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '../../atoms/Icon.vue'
import ChatColorPicker from '../../molecules/ChatColorPicker.vue'
import ChatIconPicker from '../../molecules/ChatIconPicker.vue'
import { t } from '../../i18n/i18n'
import { deleteChat, fetchFolders, fetchChats, updateChat } from '../../services/chatApi.js'

const route = useRoute()
const router = useRouter()
const folderId = computed(() => Number(route.params.folderId))
const folders = ref([])
const chats = ref([])

const folder = computed(() => folders.value.find((item) => Number(item.id) === folderId.value) || null)
const folderChats = computed(() => chats.value.filter((item) => Number(item.folder_id) === folderId.value))

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

async function load() {
  const [foldersRes, chatsRes] = await Promise.all([fetchFolders(), fetchChats()])
  if (foldersRes.ok) folders.value = unwrapList(foldersRes.data)
  if (chatsRes.ok) chats.value = unwrapList(chatsRes.data)
}

/** @type {Record<string, HTMLDetailsElement | null>} */
const kebabRefs = {}

function setKebabRef(id, el) {
  if (el) kebabRefs[String(id)] = el
  else delete kebabRefs[String(id)]
}

function closeKebabFor(id) {
  const el = kebabRefs[String(id)]
  if (el) el.open = false
}

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
  closeKebabFor(chat.id)
  renameTargetId.value = chat.id
  renameTitleDraft.value = chat.title || ''
  await nextTick()
  renameDialogRef.value?.showModal()
}

async function submitRename() {
  const id = renameTargetId.value
  if (id == null) return
  renameSaving.value = true
  await updateChat(id, { title: renameTitleDraft.value.trim() })
  renameSaving.value = false
  renameDialogRef.value?.close()
  renameTargetId.value = null
  await load()
}

async function openEditDialog(chat) {
  closeKebabFor(chat.id)
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
  await load()
}

async function onDeleteChat(chat) {
  closeKebabFor(chat.id)
  if (!window.confirm(t('chats.deleteChatConfirm'))) return
  await deleteChat(chat.id)
  await load()
}

function openChat(chat) {
  router.push({ name: 'chatThread', params: { chatId: chat.id } })
}

onMounted(load)
</script>

<template>
  <section class="chat-folder-page">
    <header class="chat-folder-page__header">
      <button type="button" class="btn btn--secondary btn--sm" @click="$router.push({ name: 'chats' })">
        {{ t('chats.backToChats') }}
      </button>
      <h1>{{ folder?.name || t('chats.folderTitle') }}</h1>
    </header>

    <dialog
      ref="renameDialogRef"
      class="chat-folder-page__dialog"
      aria-labelledby="chat-folder-rename-title"
      @click="onRenameDialogBackdrop"
    >
      <div class="chat-folder-page__dialogPanel" @click.stop>
        <h2 id="chat-folder-rename-title" class="chat-folder-page__dialogTitle">
          {{ t('chats.modal.renameChatTitle') }}
        </h2>
        <div class="chat-folder-page__field">
          <label for="chat-folder-rename-input">{{ t('chats.info.editTitle') }}</label>
          <input id="chat-folder-rename-input" v-model="renameTitleDraft" type="text">
        </div>
        <div class="chat-folder-page__dialogActions">
          <button type="button" class="btn btn--secondary btn--sm" @click="renameDialogRef?.close()">
            {{ t('chats.modal.cancel') }}
          </button>
          <button type="button" class="btn btn--primary btn--sm" :disabled="renameSaving" @click="submitRename">
            {{ t('chats.info.save') }}
          </button>
        </div>
      </div>
    </dialog>

    <dialog
      ref="editDialogRef"
      class="chat-folder-page__dialog"
      aria-labelledby="chat-folder-edit-title"
      @click="onEditDialogBackdrop"
    >
      <div class="chat-folder-page__dialogPanel" @click.stop>
        <h2 id="chat-folder-edit-title" class="chat-folder-page__dialogTitle">
          {{ t('chats.modal.editChatTitle') }}
        </h2>
        <div class="chat-folder-page__field">
          <label for="chat-folder-edit-title-input">{{ t('chats.info.editTitle') }}</label>
          <input id="chat-folder-edit-title-input" v-model="editForm.title" type="text">
        </div>
        <div class="chat-folder-page__field">
          <span class="chat-folder-page__label">{{ t('chats.info.editIcon') }}</span>
          <ChatIconPicker v-model="editForm.icon_emoji" />
        </div>
        <div class="chat-folder-page__field">
          <span class="chat-folder-page__label">{{ t('chats.info.editColor') }}</span>
          <ChatColorPicker v-model="editForm.icon_bg_color" />
        </div>
        <div class="chat-folder-page__dialogActions">
          <button type="button" class="btn btn--secondary btn--sm" @click="editDialogRef?.close()">
            {{ t('chats.modal.cancel') }}
          </button>
          <button type="button" class="btn btn--primary btn--sm" :disabled="editSaving" @click="submitEdit">
            {{ t('chats.info.save') }}
          </button>
        </div>
      </div>
    </dialog>

    <ul class="chat-folder-page__list">
      <li v-for="chat in folderChats" :key="chat.id" class="chat-folder-page__item">
        <button type="button" class="chat-folder-page__chatButton" @click="openChat(chat)">
          <span class="chat-folder-page__icon" :style="{ backgroundColor: chat.icon_bg_color || '#2563eb' }">
            {{ chat.icon_emoji || '💬' }}
          </span>
          <span class="chat-folder-page__title">{{ chat.title || t('chats.defaultConversation') }}</span>
        </button>
        <details
          class="chat-folder-page__kebab"
          :ref="(el) => setKebabRef(chat.id, el)"
        >
          <summary
            class="chat-folder-page__kebabTrigger"
            :aria-label="t('chats.actionsMenu')"
          >
            <Icon class="chat-folder-page__kebabGlyph" name="ellipsis-vertical" aria-hidden="true" />
          </summary>
          <div
            class="chat-folder-page__kebabMenu"
            role="menu"
            @click.stop
          >
            <button
              type="button"
              class="chat-folder-page__kebabItem"
              role="menuitem"
              @click="openRenameDialog(chat)"
            >
              <Icon class="chat-folder-page__kebabGlyph" name="pen" aria-hidden="true" />
              {{ t('chats.rename') }}
            </button>
            <button
              type="button"
              class="chat-folder-page__kebabItem"
              role="menuitem"
              @click="openEditDialog(chat)"
            >
              <Icon class="chat-folder-page__kebabGlyph" name="gear" aria-hidden="true" />
              {{ t('chats.edit') }}
            </button>
            <button
              type="button"
              class="chat-folder-page__kebabItem chat-folder-page__kebabItem--danger"
              role="menuitem"
              @click="onDeleteChat(chat)"
            >
              <Icon class="chat-folder-page__kebabGlyph" name="trash" aria-hidden="true" />
              {{ t('chats.delete') }}
            </button>
          </div>
        </details>
      </li>
    </ul>
  </section>
</template>

<style scoped lang="scss">
.chat-folder-page { padding: 1rem; }
.chat-folder-page__header { display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.75rem; }
.chat-folder-page__list { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem; }
.chat-folder-page__item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.chat-folder-page__chatButton {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  border: 1px solid var(--border);
  flex: 1;
  min-width: 0;
  background: var(--bg);
  padding: 0.5rem;
  border-radius: 0.5rem;
  text-align: left;
  cursor: pointer;
  color: inherit;
  font: inherit;
}
.chat-folder-page__title { min-width: 0; }
.chat-folder-page__icon {
  width: 1.8rem;
  height: 1.8rem;
  border-radius: 999px;
  display: inline-flex;
  justify-content: center;
  align-items: center;
  flex-shrink: 0;
}

.chat-folder-page__kebab {
  position: relative;
  flex-shrink: 0;
  list-style: none;
}
.chat-folder-page__kebabTrigger {
  list-style: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  margin: 0;
  padding: 0;
  border: none;
  border-radius: 0.35rem;
  background: transparent;
  color: inherit;
  cursor: pointer;
}
.chat-folder-page__kebabTrigger::-webkit-details-marker {
  display: none;
}
.chat-folder-page__kebabTrigger:hover {
  background: var(--btn-bg);
}
.chat-folder-page__kebabGlyph {
  font-size: 0.95rem;
}
.chat-folder-page__kebabMenu {
  position: absolute;
  right: 0;
  top: 100%;
  z-index: 3;
  margin-top: 0.2rem;
  min-width: 11rem;
  padding: 0.35rem;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}
html[data-theme='dark'] .chat-folder-page__kebabMenu {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.45);
}
.chat-folder-page__kebabItem {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  width: 100%;
  margin: 0;
  padding: 0.45rem 0.55rem;
  text-align: left;
  border: none;
  border-radius: 0.35rem;
  background: transparent;
  color: inherit;
  font: inherit;
  font-size: 0.9rem;
  cursor: pointer;
}
.chat-folder-page__kebabItem:hover {
  background: var(--btn-bg-hover);
}
.chat-folder-page__kebabItem--danger:hover {
  background: rgba(220, 38, 38, 0.12);
  color: #dc2626;
}

.chat-folder-page__dialog {
  margin: auto;
  padding: 0;
  max-width: min(26rem, calc(100vw - 2rem));
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  background: var(--bg);
  color: var(--text);
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
}
.chat-folder-page__dialog::backdrop {
  background: rgba(15, 23, 42, 0.45);
}
html[data-theme='dark'] .chat-folder-page__dialog::backdrop {
  background: rgba(0, 0, 0, 0.55);
}
.chat-folder-page__dialogPanel {
  padding: 1.25rem 1.25rem 1rem;
}
.chat-folder-page__dialogTitle {
  margin: 0 0 1rem;
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.chat-folder-page__field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 0.85rem;
}
.chat-folder-page__field label,
.chat-folder-page__label {
  font-size: 0.875rem;
  font-weight: 600;
}
.chat-folder-page__field input[type='text'] {
  padding: 0.45rem 0.55rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--bg);
  color: inherit;
  font: inherit;
}
.chat-folder-page__dialogActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
  padding-top: 0.5rem;
  margin-top: 0.25rem;
  border-top: 1px solid var(--border);
}
</style>
