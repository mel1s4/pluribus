<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import Icon from '../../atoms/Icon.vue'
import ChatColorPicker from '../../molecules/ChatColorPicker.vue'
import ChatIconPicker from '../../molecules/ChatIconPicker.vue'
import { t } from '../../i18n/i18n'
import {
  createChat,
  createFolder,
  deleteChat,
  fetchFolders,
  fetchChats,
  updateChat,
} from '../../services/chatApi.js'
import { useChatUnread } from '../../composables/useChatUnread.js'
import { useChatMemberPicker } from '../../composables/useChatMemberPicker.js'

const router = useRouter()
const { hydrateFromChats, getChatUnread } = useChatUnread()
const chats = ref([])
const folders = ref([])
const loading = ref(true)
const searchQuery = ref('')

const chatDialogRef = ref(null)
const folderDialogRef = ref(null)
const chatSaving = ref(false)
const folderSaving = ref(false)

const {
  memberSearchQuery,
  memberSearchResults,
  memberSearchLoading,
  selectedMembers,
  groups,
  groupsLoading,
  groupMembersLoadingId,
  reset: resetMemberPicker,
  loadGroups,
  isSelectedMember,
  toggleMember,
  removeSelectedMember,
  onGroupCheckboxChange,
  isGroupChecked,
} = useChatMemberPicker({ unknownUserLabel: t('chats.unknownUser') })

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

function chatSortTime(chat) {
  return new Date(chat?.last_message_at || chat?.updated_at || chat?.created_at || 0).getTime()
}

const sortedChats = computed(() => [...chats.value].sort((a, b) => chatSortTime(b) - chatSortTime(a)))

const filteredChats = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return sortedChats.value
  return sortedChats.value.filter((chat) => {
    const title = String(chat.title || t('chats.defaultConversation')).toLowerCase()
    const members = chatMembersSummary(chat).toLowerCase()
    const folder = folderNameFor(chat).toLowerCase()
    return title.includes(q) || members.includes(q) || folder.includes(q)
  })
})

async function load() {
  loading.value = true
  try {
    const [chatOutcome, folderOutcome] = await Promise.allSettled([fetchChats(), fetchFolders()])

    if (chatOutcome.status === 'fulfilled' && chatOutcome.value.ok) {
      chats.value = unwrapList(chatOutcome.value.data)
      hydrateFromChats(chats.value)
    }

    if (folderOutcome.status === 'fulfilled' && folderOutcome.value.ok) {
      folders.value = unwrapList(folderOutcome.value.data)
    }
  } catch (error) {
    // Keep page interactive even if one of the initial requests fails.
    console.warn('ChatsPage load failed:', error)
  } finally {
    loading.value = false
  }
}

function resetChatForm() {
  chatForm.title = ''
  chatForm.icon_emoji = '💬'
  chatForm.icon_bg_color = '#2563eb'
  resetMemberPicker()
}

function resetFolderForm() {
  folderForm.name = ''
  folderForm.icon_emoji = ''
  folderForm.icon_bg_color = '#64748b'
}

async function openNewChatDialog() {
  resetChatForm()
  await loadGroups()
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
  if (selectedMembers.value.length === 0) return
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
  const res = await createFolder(payload)
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

function folderNameFor(chat) {
  if (chat?.folder_id == null) return t('chats.unfiled')
  const folder = folders.value.find((f) => Number(f.id) === Number(chat.folder_id))
  return folder?.name || t('chats.folderDefault')
}

function openChatFolder(chat) {
  closeKebabFor(chat.id)
  if (chat.folder_id == null) {
    router.push({ name: 'folders', query: { focus: 'chats' } })
    return
  }
  router.push({ name: 'folderDetail', params: { folderId: chat.folder_id } })
}

function formatChatTime(chat) {
  const source = chat?.last_message_at || chat?.updated_at || chat?.created_at || null
  if (!source) return ''
  const parsed = new Date(source)
  if (Number.isNaN(parsed.getTime())) return ''
  const now = new Date()
  const sameDay =
    parsed.getFullYear() === now.getFullYear() &&
    parsed.getMonth() === now.getMonth() &&
    parsed.getDate() === now.getDate()
  if (sameDay) {
    return parsed.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
  }
  return parsed.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

function chatMembersSummary(chat) {
  const members = Array.isArray(chat?.members) ? chat.members : []
  const names = members
    .map((member) => String(member?.name || '').trim())
    .filter((name) => name.length > 0)
  if (names.length === 0) return ''
  if (names.length <= 3) return names.join(', ')
  return `${names.slice(0, 3).join(', ')} +${names.length - 3}`
}

onMounted(load)
</script>

<template>
  <section class="chats-page">
    <header class="chats-page__header">
      <PageToolbarTitle class="chats-page__titleRow" route-key="chats">
        <Title tag="h1">{{ t('chats.title') }}</Title>
      </PageToolbarTitle>
      <div class="chats-page__headerActions">
        <button
          type="button"
          class="chats-page__headerBtn"
          :aria-label="t('chats.addConversation')"
          @click="openNewChatDialog"
        >
          <Icon name="comments" aria-hidden="true" />
        </button>
        <button
          type="button"
          class="chats-page__headerBtn"
          :aria-label="t('chats.addFolder')"
          @click="openNewFolderDialog"
        >
          <Icon name="folder" aria-hidden="true" />
        </button>
      </div>
    </header>

    <label class="chats-page__search">
      <Icon class="chats-page__searchIcon" name="magnifying-glass" aria-hidden="true" />
      <input
        v-model="searchQuery"
        type="search"
        class="chats-page__searchInput"
        :placeholder="t('chats.searchPlaceholder')"
        autocomplete="off"
      >
    </label>

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
        <div class="chats-page__field chats-page__field--groups">
          <span id="chats-new-chat-groups-label" class="chats-page__label">{{ t('chats.modal.groupsLabel') }}</span>
          <p class="chats-page__groupHint">{{ t('chats.modal.groupsHint') }}</p>
          <p v-if="groupsLoading" class="chats-page__memberHint">
            {{ t('chats.modal.groupsLoading') }}
          </p>
          <p v-else-if="groups.length === 0" class="chats-page__memberHint">
            {{ t('chats.modal.groupsEmpty') }}
          </p>
          <ul
            v-else
            class="chats-page__groupList"
            role="group"
            aria-labelledby="chats-new-chat-groups-label"
          >
            <li v-for="g in groups" :key="g.id" class="chats-page__groupListItem">
              <label class="chats-page__groupRow">
                <input
                  type="checkbox"
                  class="chats-page__groupCheckbox"
                  :checked="isGroupChecked(g.id)"
                  :disabled="groupMembersLoadingId != null && groupMembersLoadingId !== g.id"
                  @change="onGroupCheckboxChange(g, $event.target.checked)"
                >
                <span class="chats-page__groupRowText">
                  <span class="chats-page__groupName">{{ g.name }}</span>
                  <span v-if="g.members_count != null" class="chats-page__groupMeta">({{ g.members_count }})</span>
                </span>
              </label>
            </li>
          </ul>
          <p v-if="groupMembersLoadingId != null" class="chats-page__memberHint">
            {{ t('chats.modal.groupMembersLoading') }}
          </p>
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
          <p v-else class="chats-page__memberHint">{{ t('chats.modal.membersRequired') }}</p>
        </div>
        <div class="chats-page__dialogActions">
          <button type="button" class="btn btn--secondary btn--sm" @click="chatDialogRef?.close()">
            {{ t('chats.modal.cancel') }}
          </button>
          <button
            type="button"
            class="btn btn--primary btn--sm"
            :disabled="chatSaving || selectedMembers.length === 0"
            @click="submitNewChat"
          >
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

    <p v-if="loading" class="chats-page__status">{{ t('chats.loading') }}</p>
    <p v-else-if="chats.length === 0" class="chats-page__status">{{ t('chats.emptyAll') }}</p>
    <p v-else-if="filteredChats.length === 0" class="chats-page__status">{{ t('chats.noSearchResults') }}</p>
    <ul v-else class="chats-page__list" :aria-label="t('chats.title')">
      <li v-for="chat in filteredChats" :key="chat.id" class="chats-page__row">
        <button type="button" class="chats-page__chatOpen" @click="openChat(chat)">
          <span
            class="chats-page__avatar"
            :style="{ backgroundColor: chat.icon_bg_color || '#2563eb' }"
          >
            {{ chat.icon_emoji || '💬' }}
          </span>
          <span class="chats-page__chatBody">
            <span class="chats-page__chatTop">
              <span class="chats-page__chatTitle">{{ chat.title || t('chats.defaultConversation') }}</span>
              <span v-if="formatChatTime(chat)" class="chats-page__chatTime">{{ formatChatTime(chat) }}</span>
            </span>
            <span class="chats-page__chatBottom">
              <span class="chats-page__chatPreview">
                {{ chatMembersSummary(chat) || folderNameFor(chat) }}
              </span>
              <span v-if="getChatUnread(chat.id) > 0" class="chats-page__unreadBadge">
                {{ getChatUnread(chat.id) > 99 ? '99+' : getChatUnread(chat.id) }}
              </span>
            </span>
          </span>
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
              @click="openChatFolder(chat)"
            >
              <Icon class="chats-page__kebabGlyph" name="folder-open" aria-hidden="true" />
              {{ t('chats.openChatFolder') }}
            </button>
            <button
              type="button"
              class="chats-page__kebabItem"
              role="menuitem"
              @click="openEditDialog(chat)"
            >
              <Icon class="chats-page__kebabGlyph" name="pen" aria-hidden="true" />
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
  </section>
</template>

<style scoped lang="scss">
.chats-page {
  display: flex;
  flex-direction: column;
  min-height: 100%;
  max-width: 40rem;
  margin: 0 auto;
  padding: 0 0 1rem;
  background: var(--bg);
}

.chats-page__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.75rem 1rem 0.35rem;
}

.chats-page__titleRow {
  flex: 1;
  min-width: 0;
}

.chats-page__headerActions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  flex-shrink: 0;
}

.chats-page__headerBtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  margin: 0;
  padding: 0;
  border: none;
  border-radius: 999px;
  background: transparent;
  color: var(--text);
  cursor: pointer;
}

.chats-page__headerBtn:hover {
  background: var(--btn-bg-hover, rgba(0, 0, 0, 0.06));
}

.chats-page__search {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  margin: 0.35rem 1rem 0.5rem;
  padding: 0.45rem 0.75rem;
  border-radius: 0.5rem;
  background: var(--btn-bg, rgba(15, 23, 42, 0.06));
}

.chats-page__searchIcon {
  opacity: 0.55;
  font-size: 0.95rem;
  flex-shrink: 0;
}

.chats-page__searchInput {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: inherit;
  font: inherit;
  font-size: 0.92rem;
  outline: none;
}

.chats-page__searchInput::placeholder {
  opacity: 0.65;
}

.chats-page__status {
  margin: 0.75rem 1rem;
  opacity: 0.75;
}

.chats-page__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.chats-page__row {
  display: flex;
  align-items: stretch;
  border-bottom: 1px solid var(--border);
}

.chats-page__row:hover {
  background: var(--btn-bg-hover, rgba(0, 0, 0, 0.03));
}

.chats-page__chatOpen {
  border: none;
  background: transparent;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex: 1;
  min-width: 0;
  padding: 0.65rem 0.35rem 0.65rem 1rem;
  text-align: left;
  cursor: pointer;
  color: inherit;
}

.chats-page__avatar {
  width: 3rem;
  height: 3rem;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  font-size: 1.25rem;
}

.chats-page__chatBody {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.chats-page__chatTop,
.chats-page__chatBottom {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}

.chats-page__chatTitle {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 600;
  font-size: 0.98rem;
}

.chats-page__chatTime {
  flex-shrink: 0;
  font-size: 0.72rem;
  opacity: 0.62;
}

.chats-page__chatPreview {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.84rem;
  opacity: 0.72;
}

.chats-page__kebab {
  position: relative;
  flex-shrink: 0;
  list-style: none;
  align-self: center;
  margin-right: 0.35rem;
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
.chats-page__unreadBadge {
  flex-shrink: 0;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.35rem;
  border-radius: 999px;
  background: #25d366;
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.72rem;
  font-weight: 700;
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

.chats-page__groupHint {
  margin: 0;
  font-size: 0.8rem;
  opacity: 0.8;
  line-height: 1.35;
}

.chats-page__groupList {
  list-style: none;
  margin: 0.35rem 0 0;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  max-height: 11rem;
  overflow-y: auto;
}

.chats-page__groupListItem {
  border-bottom: 1px solid var(--border);

  &:last-child {
    border-bottom: none;
  }
}

.chats-page__groupRow {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.5rem 0.65rem;
  cursor: pointer;
  font: inherit;
}

.chats-page__groupCheckbox {
  margin-top: 0.15rem;
  flex-shrink: 0;
}

.chats-page__groupRowText {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  min-width: 0;
}

.chats-page__groupName {
  font-size: 0.9rem;
  font-weight: 600;
}

.chats-page__groupMeta {
  font-size: 0.8rem;
  opacity: 0.75;
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
