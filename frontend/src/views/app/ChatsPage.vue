<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Title from '../../atoms/Title.vue'
import Icon from '../../atoms/Icon.vue'
import ChatColorPicker from '../../molecules/ChatColorPicker.vue'
import ChatIconPicker from '../../molecules/ChatIconPicker.vue'
import { t } from '../../i18n/i18n'
import {
  createChat,
  createChatFolder,
  deleteChat,
  fetchChatFolders,
  fetchChats,
  updateChat,
} from '../../services/chatApi.js'
import { searchUsers } from '../../services/usersApi.js'

const router = useRouter()
const chats = ref([])
const folders = ref([])
const loading = ref(true)

const chatDialogRef = ref(null)
const folderDialogRef = ref(null)
const chatSaving = ref(false)
const folderSaving = ref(false)
const memberSearchQuery = ref('')
const memberSearchResults = ref([])
const memberSearchLoading = ref(false)
const selectedMembers = ref([])
let memberSearchTimer = null

const chatForm = reactive({
  title: '',
  icon_emoji: '💬',
  icon_bg_color: '#2563eb',
})

const folderForm = reactive({
  name: '',
  icon_emoji: '',
  icon_bg_color: '#64748b',
})

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

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

const sections = computed(() => {
  const byFolder = new Map()
  for (const folder of folders.value) byFolder.set(folder.id, [])
  for (const chat of chats.value) {
    const key = chat.folder_id ?? 'unfiled'
    const out = byFolder.get(key) || []
    out.push(chat)
    byFolder.set(key, out)
  }
  return [
    { id: 'unfiled', name: t('chats.unfiled'), folder: null, chats: byFolder.get('unfiled') || [] },
    ...folders.value.map((folder) => ({
      id: folder.id,
      name: folder.name,
      folder,
      chats: byFolder.get(folder.id) || [],
    })),
  ]
})

async function load() {
  loading.value = true
  const [chatRes, folderRes] = await Promise.all([fetchChats(), fetchChatFolders()])
  if (chatRes.ok) chats.value = unwrapList(chatRes.data)
  if (folderRes.ok) folders.value = unwrapList(folderRes.data)
  loading.value = false
}

function resetChatForm() {
  chatForm.title = ''
  chatForm.icon_emoji = '💬'
  chatForm.icon_bg_color = '#2563eb'
  memberSearchQuery.value = ''
  memberSearchResults.value = []
  selectedMembers.value = []
}

function resetFolderForm() {
  folderForm.name = ''
  folderForm.icon_emoji = ''
  folderForm.icon_bg_color = '#64748b'
}

async function openNewChatDialog() {
  resetChatForm()
  await nextTick()
  chatDialogRef.value?.showModal()
}

async function openNewFolderDialog() {
  resetFolderForm()
  await nextTick()
  folderDialogRef.value?.showModal()
}

function onChatDialogBackdrop(e) {
  if (e.target === chatDialogRef.value) chatDialogRef.value?.close()
}

function onFolderDialogBackdrop(e) {
  if (e.target === folderDialogRef.value) folderDialogRef.value?.close()
}

async function submitNewChat() {
  chatSaving.value = true
  const payload = {
    type: 'group',
    title: chatForm.title.trim() || null,
    member_ids: selectedMembers.value.map((member) => Number(member.id)).filter((id) => Number.isFinite(id)),
  }
  if (chatForm.icon_emoji?.trim()) payload.icon_emoji = chatForm.icon_emoji.trim()
  if (chatForm.icon_bg_color && /^#[0-9A-Fa-f]{6}$/.test(chatForm.icon_bg_color)) {
    payload.icon_bg_color = chatForm.icon_bg_color
  }
  const res = await createChat(payload)
  chatSaving.value = false
  if (!res.ok) return
  chatDialogRef.value?.close()
  await load()
  const id = res.data?.chat?.id
  if (id != null) router.push({ name: 'chatThread', params: { chatId: id } })
}

async function submitNewFolder() {
  folderSaving.value = true
  const name = folderForm.name.trim() || `${t('chats.folderDefault')} ${folders.value.length + 1}`
  const payload = { name }
  if (folderForm.icon_emoji?.trim()) payload.icon_emoji = folderForm.icon_emoji.trim()
  if (folderForm.icon_bg_color && /^#[0-9A-Fa-f]{6}$/.test(folderForm.icon_bg_color)) {
    payload.icon_bg_color = folderForm.icon_bg_color
  }
  const res = await createChatFolder(payload)
  folderSaving.value = false
  if (!res.ok) return
  folderDialogRef.value?.close()
  await load()
}

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

function openFolder(folderId) {
  router.push({ name: 'chatFolder', params: { folderId } })
}

function isSelectedMember(memberId) {
  return selectedMembers.value.some((member) => Number(member.id) === Number(memberId))
}

function toggleMember(member) {
  const id = Number(member.id)
  if (!Number.isFinite(id)) return
  if (isSelectedMember(id)) {
    selectedMembers.value = selectedMembers.value.filter((item) => Number(item.id) !== id)
    return
  }
  selectedMembers.value = [...selectedMembers.value, member]
}

function removeSelectedMember(memberId) {
  selectedMembers.value = selectedMembers.value.filter((item) => Number(item.id) !== Number(memberId))
}

function clearMemberSearchTimer() {
  if (memberSearchTimer) {
    clearTimeout(memberSearchTimer)
    memberSearchTimer = null
  }
}

watch(memberSearchQuery, (next) => {
  clearMemberSearchTimer()
  const q = String(next || '').trim()
  if (q.length < 2) {
    memberSearchResults.value = []
    memberSearchLoading.value = false
    return
  }
  memberSearchTimer = setTimeout(async () => {
    memberSearchLoading.value = true
    const res = await searchUsers(q, 10)
    memberSearchLoading.value = false
    if (!res.ok) {
      memberSearchResults.value = []
      return
    }
    const items = Array.isArray(res.data?.data) ? res.data.data : []
    memberSearchResults.value = items.map((item) => ({
      id: item.id,
      name: item.name || t('chats.unknownUser'),
      email: item.email || '',
    }))
  }, 300)
})

onBeforeUnmount(() => {
  clearMemberSearchTimer()
})

onMounted(load)
</script>

<template>
  <section class="chats-page">
    <header class="chats-page__toolbar">
      <Title tag="h1">{{ t('chats.title') }}</Title>
      <div class="chats-page__actions">
        <button type="button" class="btn btn--primary btn--sm" @click="openNewChatDialog">
          {{ t('chats.addConversation') }}
        </button>
        <button type="button" class="btn btn--secondary btn--sm" @click="openNewFolderDialog">
          {{ t('chats.addFolder') }}
        </button>
      </div>
    </header>

    <dialog
      ref="chatDialogRef"
      class="chats-page__dialog"
      aria-labelledby="chats-new-chat-title"
      @click="onChatDialogBackdrop"
    >
      <div class="chats-page__dialogPanel" @click.stop>
        <h2 id="chats-new-chat-title" class="chats-page__dialogTitle">
          {{ t('chats.modal.newChatTitle') }}
        </h2>
        <div class="chats-page__field">
          <label for="chats-new-chat-title-input">{{ t('chats.info.editTitle') }}</label>
          <input id="chats-new-chat-title-input" v-model="chatForm.title" type="text">
        </div>
        <div class="chats-page__field">
          <span class="chats-page__label">{{ t('chats.info.editIcon') }}</span>
          <ChatIconPicker v-model="chatForm.icon_emoji" />
        </div>
        <div class="chats-page__field">
          <span class="chats-page__label">{{ t('chats.info.editColor') }}</span>
          <ChatColorPicker v-model="chatForm.icon_bg_color" />
        </div>
        <div class="chats-page__field">
          <label for="chats-new-chat-members">{{ t('chats.modal.membersLabel') }}</label>
          <input
            id="chats-new-chat-members"
            v-model="memberSearchQuery"
            type="text"
            :placeholder="t('chats.modal.membersPlaceholder')"
          >
          <p v-if="memberSearchLoading" class="chats-page__memberHint">
            {{ t('chats.modal.membersSearching') }}
          </p>
          <ul v-else-if="memberSearchQuery.trim().length >= 2 && memberSearchResults.length > 0" class="chats-page__memberResults">
            <li v-for="member in memberSearchResults" :key="member.id" class="chats-page__memberResult">
              <button
                type="button"
                class="chats-page__memberResultBtn"
                :class="{ 'is-selected': isSelectedMember(member.id) }"
                @click="toggleMember(member)"
              >
                <span class="chats-page__memberResultName">{{ member.name }}</span>
                <span class="chats-page__memberResultMeta">{{ member.email }}</span>
              </button>
            </li>
          </ul>
          <p v-else-if="memberSearchQuery.trim().length >= 2" class="chats-page__memberHint">
            {{ t('chats.modal.membersEmpty') }}
          </p>
          <div v-if="selectedMembers.length > 0" class="chats-page__selectedMembers">
            <span
              v-for="member in selectedMembers"
              :key="member.id"
              class="chats-page__selectedMemberPill"
            >
              {{ member.name }}
              <button
                type="button"
                class="chats-page__selectedMemberRemove"
                :aria-label="`Remove ${member.name}`"
                @click="removeSelectedMember(member.id)"
              >
                ×
              </button>
            </span>
          </div>
        </div>
        <div class="chats-page__dialogActions">
          <button type="button" class="btn btn--secondary btn--sm" @click="chatDialogRef?.close()">
            {{ t('chats.modal.cancel') }}
          </button>
          <button type="button" class="btn btn--primary btn--sm" :disabled="chatSaving" @click="submitNewChat">
            {{ t('chats.modal.submitChat') }}
          </button>
        </div>
      </div>
    </dialog>

    <dialog
      ref="folderDialogRef"
      class="chats-page__dialog"
      aria-labelledby="chats-new-folder-title"
      @click="onFolderDialogBackdrop"
    >
      <div class="chats-page__dialogPanel" @click.stop>
        <h2 id="chats-new-folder-title" class="chats-page__dialogTitle">
          {{ t('chats.modal.newFolderTitle') }}
        </h2>
        <div class="chats-page__field">
          <label for="chats-new-folder-name">{{ t('chats.modal.folderName') }}</label>
          <input id="chats-new-folder-name" v-model="folderForm.name" type="text" :placeholder="t('chats.folderDefault')">
        </div>
        <div class="chats-page__field">
          <span class="chats-page__label">{{ t('chats.info.editIcon') }}</span>
          <ChatIconPicker v-model="folderForm.icon_emoji" />
        </div>
        <div class="chats-page__field">
          <span class="chats-page__label">{{ t('chats.info.editColor') }}</span>
          <ChatColorPicker v-model="folderForm.icon_bg_color" />
        </div>
        <div class="chats-page__dialogActions">
          <button type="button" class="btn btn--secondary btn--sm" @click="folderDialogRef?.close()">
            {{ t('chats.modal.cancel') }}
          </button>
          <button type="button" class="btn btn--primary btn--sm" :disabled="folderSaving" @click="submitNewFolder">
            {{ t('chats.modal.submitFolder') }}
          </button>
        </div>
      </div>
    </dialog>

    <dialog
      ref="renameDialogRef"
      class="chats-page__dialog"
      aria-labelledby="chats-rename-title"
      @click="onRenameDialogBackdrop"
    >
      <div class="chats-page__dialogPanel" @click.stop>
        <h2 id="chats-rename-title" class="chats-page__dialogTitle">
          {{ t('chats.modal.renameChatTitle') }}
        </h2>
        <div class="chats-page__field">
          <label for="chats-rename-title-input">{{ t('chats.info.editTitle') }}</label>
          <input id="chats-rename-title-input" v-model="renameTitleDraft" type="text">
        </div>
        <div class="chats-page__dialogActions">
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
      class="chats-page__dialog"
      aria-labelledby="chats-edit-title"
      @click="onEditDialogBackdrop"
    >
      <div class="chats-page__dialogPanel" @click.stop>
        <h2 id="chats-edit-title" class="chats-page__dialogTitle">
          {{ t('chats.modal.editChatTitle') }}
        </h2>
        <div class="chats-page__field">
          <label for="chats-edit-title-input">{{ t('chats.info.editTitle') }}</label>
          <input id="chats-edit-title-input" v-model="editForm.title" type="text">
        </div>
        <div class="chats-page__field">
          <span class="chats-page__label">{{ t('chats.info.editIcon') }}</span>
          <ChatIconPicker v-model="editForm.icon_emoji" />
        </div>
        <div class="chats-page__field">
          <span class="chats-page__label">{{ t('chats.info.editColor') }}</span>
          <ChatColorPicker v-model="editForm.icon_bg_color" />
        </div>
        <div class="chats-page__dialogActions">
          <button type="button" class="btn btn--secondary btn--sm" @click="editDialogRef?.close()">
            {{ t('chats.modal.cancel') }}
          </button>
          <button type="button" class="btn btn--primary btn--sm" :disabled="editSaving" @click="submitEdit">
            {{ t('chats.info.save') }}
          </button>
        </div>
      </div>
    </dialog>

    <p v-if="loading">{{ t('chats.loading') }}</p>
    <div v-for="section in sections" :key="section.id" class="chats-page__section">
      <div class="chats-page__sectionHeader">
        <button
          v-if="section.id !== 'unfiled'"
          type="button"
          class="chats-page__folderButton"
          @click="openFolder(section.id)"
        >
          <span
            v-if="section.folder?.icon_emoji"
            class="chats-page__folderIcon"
            :style="{ backgroundColor: section.folder.icon_bg_color || '#64748b' }"
          >{{ section.folder.icon_emoji }}</span>
          {{ section.name }}
        </button>
        <span v-else>{{ section.name }}</span>
      </div>
      <ul class="chats-page__list">
        <li v-for="chat in section.chats" :key="chat.id" class="chats-page__item">
          <button type="button" class="chats-page__chatOpen" @click="openChat(chat)">
            <span class="chats-page__icon" :style="{ backgroundColor: chat.icon_bg_color || '#2563eb' }">
              {{ chat.icon_emoji || '💬' }}
            </span>
            <span>{{ chat.title || t('chats.defaultConversation') }}</span>
          </button>
          <details
            class="chats-page__kebab"
            :ref="(el) => setKebabRef(chat.id, el)"
          >
            <summary
              class="chats-page__kebabTrigger"
              :aria-label="t('chats.actionsMenu')"
            >
              <Icon class="chats-page__kebabGlyph" name="ellipsis-vertical" aria-hidden="true" />
            </summary>
            <div
              class="chats-page__kebabMenu"
              role="menu"
              @click.stop
            >
              <button
                type="button"
                class="chats-page__kebabItem"
                role="menuitem"
                @click="openRenameDialog(chat)"
              >
                <Icon class="chats-page__kebabGlyph" name="pen" aria-hidden="true" />
                {{ t('chats.rename') }}
              </button>
              <button
                type="button"
                class="chats-page__kebabItem"
                role="menuitem"
                @click="openEditDialog(chat)"
              >
                <Icon class="chats-page__kebabGlyph" name="gear" aria-hidden="true" />
                {{ t('chats.edit') }}
              </button>
              <button
                type="button"
                class="chats-page__kebabItem chats-page__kebabItem--danger"
                role="menuitem"
                @click="onDeleteChat(chat)"
              >
                <Icon class="chats-page__kebabGlyph" name="trash" aria-hidden="true" />
                {{ t('chats.delete') }}
              </button>
            </div>
          </details>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped lang="scss">
.chats-page { padding: 1rem; }
.chats-page__toolbar { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; align-items: center; }
.chats-page__actions { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.chats-page__section { border: 1px solid var(--border); border-radius: 0.5rem; margin-top: 0.75rem; }
.chats-page__sectionHeader { padding: 0.5rem 0.75rem; font-weight: 700; border-bottom: 1px solid var(--border); }
.chats-page__list { list-style: none; margin: 0; padding: 0.5rem; display: grid; gap: 0.5rem; }
.chats-page__item { display: flex; align-items: center; gap: 0.5rem; }
.chats-page__chatOpen { border: none; background: transparent; display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 0; text-align: left; cursor: pointer; }

.chats-page__kebab {
  position: relative;
  flex-shrink: 0;
  list-style: none;
}
.chats-page__kebabTrigger {
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
.chats-page__kebabTrigger::-webkit-details-marker {
  display: none;
}
.chats-page__kebabTrigger:hover {
  background: var(--btn-bg);
}
.chats-page__kebabGlyph {
  font-size: 0.95rem;
}
.chats-page__kebabMenu {
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
html[data-theme='dark'] .chats-page__kebabMenu {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.45);
}
.chats-page__kebabItem {
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
.chats-page__kebabItem:hover {
  background: var(--btn-bg-hover);
}
.chats-page__kebabItem--danger:hover {
  background: rgba(220, 38, 38, 0.12);
  color: #dc2626;
}
.chats-page__icon { width: 1.8rem; height: 1.8rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; }
.chats-page__folderButton {
  border: none;
  background: transparent;
  font: inherit;
  padding: 0;
  cursor: pointer;
  text-decoration: underline;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}
.chats-page__folderIcon {
  width: 1.65rem;
  height: 1.65rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  text-decoration: none;
  font-size: 0.95rem;
}

.chats-page__dialog {
  margin: auto;
  padding: 0;
  max-width: min(26rem, calc(100vw - 2rem));
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  background: var(--bg);
  color: var(--text);
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
}
.chats-page__dialog::backdrop {
  background: rgba(15, 23, 42, 0.45);
}
html[data-theme='dark'] .chats-page__dialog::backdrop {
  background: rgba(0, 0, 0, 0.55);
}
.chats-page__dialogPanel {
  padding: 1.25rem 1.25rem 1rem;
}
.chats-page__dialogTitle {
  margin: 0 0 1rem;
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.chats-page__field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 0.85rem;
}
.chats-page__field label,
.chats-page__label {
  font-size: 0.875rem;
  font-weight: 600;
}
.chats-page__field input[type='text'] {
  padding: 0.45rem 0.55rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--bg);
  color: inherit;
  font: inherit;
}
.chats-page__dialogActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
  padding-top: 0.5rem;
  margin-top: 0.25rem;
  border-top: 1px solid var(--border);
}

.chats-page__memberResults {
  list-style: none;
  margin: 0;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  max-height: 10rem;
  overflow-y: auto;
}

.chats-page__memberResult + .chats-page__memberResult {
  border-top: 1px solid var(--border);
}

.chats-page__memberResultBtn {
  width: 100%;
  border: none;
  background: transparent;
  padding: 0.4rem 0.5rem;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  cursor: pointer;
  color: inherit;
}

.chats-page__memberResultBtn.is-selected {
  background: var(--surface, rgba(59, 91, 219, 0.12));
}

.chats-page__memberResultName {
  font-weight: 600;
}

.chats-page__memberResultMeta {
  font-size: 0.8rem;
  opacity: 0.8;
}

.chats-page__memberHint {
  margin: 0;
  font-size: 0.82rem;
  opacity: 0.8;
}

.chats-page__selectedMembers {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.chats-page__selectedMemberPill {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 0.2rem 0.45rem;
  font-size: 0.82rem;
}

.chats-page__selectedMemberRemove {
  border: none;
  background: transparent;
  cursor: pointer;
  padding: 0;
  line-height: 1;
  font-size: 1rem;
}
</style>
