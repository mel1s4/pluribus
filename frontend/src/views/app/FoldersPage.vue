<script setup>
import { computed, onMounted, reactive, ref, unref, watch, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import FolderCard from '../../molecules/FolderCard.vue'
import FolderIconPicker from '../../molecules/FolderIconPicker.vue'
import ViewModeSwitcher from '../../molecules/ViewModeSwitcher.vue'
import FolderTree from '../../organisms/FolderTree.vue'
import FolderSearchPanel from '../../organisms/FolderSearchPanel.vue'
import { useFolders } from '../../composables/useFolders.js'
import { useDragDrop } from '../../composables/useDragDrop.js'
import { useFolderSearch } from '../../composables/useFolderSearch.js'
import { t } from '../../i18n/i18n'
import { bulkMoveFolderItems, createChat, fetchChats } from '../../services/chatApi.js'
import { createTask, fetchTasks } from '../../services/contentApi.js'
import { searchUsers } from '../../services/usersApi.js'

const router = useRouter()
const {
  folders,
  folderTree,
  load: loadFolders,
  createFolder,
  updateFolder,
  deleteFolder,
} = useFolders()

const chats = ref([])
const tasks = ref([])

const viewMode = ref(/** @type {'list'|'grid'|'tree'} */ ('grid'))
const expandedIds = reactive(/** @type {Record<number, boolean>} */ ({}))

const drag = useDragDrop()

/** Top-level refs so the template unwraps them for FolderSearchPanel props/v-model. */
const {
  query: folderSearchQuery,
  filterType: folderSearchFilterType,
  loading: folderSearchLoading,
  folders: folderSearchFolders,
  chats: folderSearchChats,
  tasks: folderSearchTasks,
  recentQueries: folderSearchRecentQueries,
  pushRecent: pushFolderSearchRecent,
} = useFolderSearch()

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

async function loadAll() {
  await loadFolders()
  const [chRes, tkRes] = await Promise.all([fetchChats(), fetchTasks()])
  if (chRes.ok) chats.value = unwrapList(chRes.data)
  if (tkRes.ok) tasks.value = unwrapList(tkRes.data)
}

const rootFolders = computed(() =>
  [...folders.value]
    .filter((f) => f.parent_id == null)
    .sort(
      (a, b) =>
        (Number(a.sort_order) || 0) - (Number(b.sort_order) || 0) ||
        Number(a.id) - Number(b.id),
    ),
)

const countsByFolder = computed(() => {
  const m = new Map()
  for (const c of chats.value) {
    const fid = c.folder_id != null ? Number(c.folder_id) : null
    if (fid == null) continue
    const cur = m.get(fid) || { chats: 0, tasks: 0 }
    cur.chats += 1
    m.set(fid, cur)
  }
  for (const tk of tasks.value) {
    const fid = tk.folder_id != null ? Number(tk.folder_id) : null
    if (fid == null) continue
    const cur = m.get(fid) || { chats: 0, tasks: 0 }
    cur.tasks += 1
    m.set(fid, cur)
  }
  return m
})

const totals = computed(() => ({
  folders: folders.value.length,
  chats: chats.value.length,
  tasks: tasks.value.length,
}))

const createDialogRef = ref(null)
const newFolder = reactive({
  name: '',
  icon_emoji: '📁',
  icon_bg_color: '#6366f1',
  parent_id: null,
})

const renameDialogRef = ref(null)
const renameFolderId = ref(null)
const renameName = ref('')

const iconDialogRef = ref(null)
const iconEditId = ref(null)
const iconForm = reactive({
  icon_emoji: '📁',
  icon_bg_color: '#6366f1',
})

const chatDialogRef = ref(null)
const chatForm = reactive({
  title: '',
  folder_id: null,
})
const memberSearchQuery = ref('')
const memberSearchResults = ref([])
const memberSearchLoading = ref(false)
const selectedMembers = ref([])
let memberSearchTimer = null

const taskDialogRef = ref(null)
const taskForm = reactive({
  title: '',
  description: '',
  folder_id: null,
})

function openFolder(f) {
  router.push({ name: 'folderDetail', params: { folderId: f.id } })
}

function toggleTreeFolder(id) {
  const n = Number(id)
  expandedIds[n] = !expandedIds[n]
}

function onTreeSelect(id) {
  router.push({ name: 'folderDetail', params: { folderId: id } })
}

function onCreateBackdrop(e) {
  if (e.target === createDialogRef.value) createDialogRef.value?.close()
}

async function submitCreate() {
  const name = newFolder.name.trim()
  if (!name) return
  const res = await createFolder({
    name,
    icon_emoji: newFolder.icon_emoji || null,
    icon_bg_color: newFolder.icon_bg_color || null,
    parent_id: newFolder.parent_id,
  })
  if (!res.ok) return
  createDialogRef.value?.close()
  newFolder.name = ''
  newFolder.parent_id = null
  await loadAll()
}

function onRenameBackdrop(e) {
  if (e.target === renameDialogRef.value) renameDialogRef.value?.close()
}

function onIconBackdrop(e) {
  if (e.target === iconDialogRef.value) iconDialogRef.value?.close()
}

async function submitRename() {
  const id = renameFolderId.value
  if (id == null) return
  const res = await updateFolder(id, { name: renameName.value.trim() })
  if (!res.ok) return
  renameDialogRef.value?.close()
  renameFolderId.value = null
  await loadAll()
}

async function submitIcon() {
  const id = iconEditId.value
  if (id == null) return
  const res = await updateFolder(id, {
    icon_emoji: iconForm.icon_emoji?.trim() || null,
    icon_bg_color: iconForm.icon_bg_color || null,
  })
  if (!res.ok) return
  iconDialogRef.value?.close()
  iconEditId.value = null
  await loadAll()
}

async function onFolderMenu({ action, folder }) {
  if (action === 'rename') {
    renameFolderId.value = folder.id
    renameName.value = folder.name || ''
    renameDialogRef.value?.showModal()
  } else if (action === 'editIcon') {
    iconEditId.value = folder.id
    iconForm.icon_emoji = folder.icon_emoji || '📁'
    iconForm.icon_bg_color = /^#[0-9A-Fa-f]{6}$/.test(folder.icon_bg_color || '')
      ? folder.icon_bg_color
      : '#6366f1'
    iconDialogRef.value?.showModal()
  } else if (action === 'delete') {
    if (!window.confirm(t('folders.deleteFolderConfirm'))) return
    await deleteFolder(folder.id)
    await loadAll()
  }
}

function startFolderDrag(folder, e) {
  drag.onDragStart(e, { type: 'folder', id: folder.id })
}

async function onDropOnFolder(targetFolder, e) {
  const payload = drag.readPayload(e)
  drag.dragOverTargetId.value = null
  if (!payload) return
  if (payload.type === 'folder' && Number(payload.id) === Number(targetFolder.id)) return
  if (payload.type === 'folder') {
    const res = await updateFolder(payload.id, { parent_id: targetFolder.id })
    if (res.ok) await loadAll()
    return
  }
  const res = await bulkMoveFolderItems({
    target_folder_id: targetFolder.id,
    items: [{ type: payload.type, id: payload.id }],
  })
  if (res.ok) await loadAll()
}

async function onDropRoot(e) {
  const payload = drag.readPayload(e)
  drag.dragOverTargetId.value = null
  if (!payload) return
  if (payload.type === 'folder') {
    const res = await updateFolder(payload.id, { parent_id: null })
    if (res.ok) await loadAll()
    return
  }
  const res = await bulkMoveFolderItems({
    target_folder_id: null,
    items: [{ type: payload.type, id: payload.id }],
  })
  if (res.ok) await loadAll()
}

function onSearchOpenFolder(id) {
  pushFolderSearchRecent(unref(folderSearchQuery))
  router.push({ name: 'folderDetail', params: { folderId: id } })
}

function onSearchOpenChat(id) {
  pushFolderSearchRecent(unref(folderSearchQuery))
  router.push({ name: 'chatThread', params: { chatId: id } })
}

function onSearchOpenTask() {
  pushFolderSearchRecent(unref(folderSearchQuery))
  router.push({ name: 'tasks' })
}

function onPickRecent(q) {
  folderSearchQuery.value = q
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

function onChatDialogBackdrop(e) {
  if (e.target === chatDialogRef.value) {
    chatDialogRef.value?.close()
    memberSearchQuery.value = ''
    memberSearchResults.value = []
    selectedMembers.value = []
  }
}

function onTaskDialogBackdrop(e) {
  if (e.target === taskDialogRef.value) taskDialogRef.value?.close()
}

async function submitCreateChat() {
  const payload = {
    type: 'group',
    title: chatForm.title.trim() || null,
    folder_id: chatForm.folder_id,
    member_ids: selectedMembers.value.map((member) => Number(member.id)).filter((id) => Number.isFinite(id)),
  }
  const res = await createChat(payload)
  if (!res.ok) return
  chatDialogRef.value?.close()
  chatForm.title = ''
  chatForm.folder_id = null
  memberSearchQuery.value = ''
  memberSearchResults.value = []
  selectedMembers.value = []
  await loadAll()
  const id = res.data?.chat?.id
  if (id != null) router.push({ name: 'chatThread', params: { chatId: id } })
}

async function submitCreateTask() {
  const title = taskForm.title.trim()
  if (!title) return
  const payload = {
    title,
    description: taskForm.description.trim() || null,
    folder_id: taskForm.folder_id,
  }
  const res = await createTask(payload)
  if (!res.ok) return
  taskDialogRef.value?.close()
  taskForm.title = ''
  taskForm.description = ''
  taskForm.folder_id = null
  await loadAll()
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
      name: item.name || t('folders.chatUntitled'),
      email: item.email || '',
    }))
  }, 300)
})

onBeforeUnmount(() => {
  clearMemberSearchTimer()
})

onMounted(async () => {
  await loadAll()
  for (const f of folders.value) {
    expandedIds[Number(f.id)] = true
  }
})
</script>

<template>
  <section class="folders-page" @dragend="drag.onDragEnd">
    <header class="folders-page__header">
      <div class="folders-page__headerText">
        <h1 class="folders-page__title">{{ t('folders.title') }}</h1>
        <p class="folders-page__subline" role="status">
          <span class="folders-page__counts">
            {{ totals.folders }} {{ t('folders.stats.foldersShort') }} · {{ totals.chats }} {{ t('folders.stats.chatsShort') }} · {{ totals.tasks }} {{ t('folders.stats.tasksShort') }}
          </span>
          <span class="folders-page__sublineSep" aria-hidden="true">·</span>
          <span class="folders-page__tagline">{{ t('folders.introShort') }}</span>
        </p>
      </div>
      <div class="folders-page__headerActions">
        <Button size="sm" @click="createDialogRef.showModal()">+ {{ t('folders.createNew') }}</Button>
        <Button size="sm" variant="secondary" @click="chatDialogRef.showModal()">+ {{ t('folders.newChat') }}</Button>
        <Button size="sm" variant="secondary" @click="taskDialogRef.showModal()">+ {{ t('folders.newTask') }}</Button>
      </div>
    </header>

    <div class="folders-page__controls">
      <FolderSearchPanel
        v-model:query="folderSearchQuery"
        v-model:filter-type="folderSearchFilterType"
        :loading="folderSearchLoading"
        :folders="folderSearchFolders"
        :chats="folderSearchChats"
        :tasks="folderSearchTasks"
        :recent-queries="folderSearchRecentQueries"
        class="folders-page__searchPanel"
        @pick-recent="onPickRecent"
        @open-folder="onSearchOpenFolder"
        @open-chat="onSearchOpenChat"
        @open-task="onSearchOpenTask"
      />
      <ViewModeSwitcher v-model="viewMode" />
    </div>

    <div class="folders-page__layout">
      <aside v-if="viewMode === 'tree' || viewMode === 'list' || viewMode === 'grid'" class="folders-page__aside">
        <h2 class="folders-page__asideTitle">{{ t('folders.treePanelTitle') }}</h2>
        <FolderTree
          :nodes="folderTree"
          :expanded-ids="expandedIds"
          :active-folder-id="null"
          @toggle="toggleTreeFolder"
          @select="onTreeSelect"
        />
      </aside>

      <div
        class="folders-page__main"
        :class="{ 'is-drop-root': drag.dragOverTargetId === 'root' }"
        @dragover="drag.onDragOverFolder($event, 'root')"
        @dragleave="drag.onDragLeaveFolder($event, 'root')"
        @drop="onDropRoot"
      >
        <div
          v-if="viewMode === 'tree'"
          class="folders-page__hint"
        >
          {{ t('folders.useTreeAside') }}
        </div>

        <div
          v-else
          class="folders-page__cards"
          :class="viewMode === 'grid' ? 'folders-page__cards--grid' : 'folders-page__cards--list'"
        >
          <FolderCard
            v-for="f in rootFolders"
            :key="f.id"
            :folder="f"
            :draggable="true"
            :show-drag-handle="true"
            :layout="viewMode === 'list' ? 'list' : 'grid'"
            :is-drop-over="drag.dragOverTargetId === String(f.id)"
            :chat-count="countsByFolder.get(Number(f.id))?.chats ?? 0"
            :task-count="countsByFolder.get(Number(f.id))?.tasks ?? 0"
            @open="openFolder"
            @menu="onFolderMenu"
            @dragstart="startFolderDrag(f, $event)"
            @dragend="drag.onDragEnd"
            @dragover="drag.onDragOverFolder($event, f.id)"
            @dragleave="drag.onDragLeaveFolder($event, f.id)"
            @drop="onDropOnFolder(f, $event)"
          />
        </div>

        <p v-if="!rootFolders.length" class="folders-page__empty">{{ t('folders.emptyRoot') }}</p>
        <p class="folders-page__dropHint">{{ t('folders.dragDrop.dropHereRootShort') }}</p>
      </div>
    </div>

    <dialog ref="createDialogRef" class="folders-page__dialog" @click="onCreateBackdrop">
      <form class="folders-page__dialogPanel" @submit.prevent="submitCreate" @click.stop>
        <h2>{{ t('folders.createNew') }}</h2>
        <label class="folders-page__field">
          <span>{{ t('folders.nameLabel') }}</span>
          <input v-model="newFolder.name" required maxlength="255">
        </label>
        <label class="folders-page__field">
          <span>{{ t('folders.parentLabel') }}</span>
          <select v-model.number="newFolder.parent_id">
            <option :value="null">{{ t('folders.parentNone') }}</option>
            <option v-for="x in folders" :key="x.id" :value="Number(x.id)">{{ x.name }}</option>
          </select>
        </label>
        <FolderIconPicker v-model:emoji="newFolder.icon_emoji" v-model:bg-color="newFolder.icon_bg_color" />
        <div class="folders-page__dialogActions">
          <Button type="button" variant="secondary" @click="createDialogRef.close()">{{ t('folders.cancel') }}</Button>
          <Button type="submit">{{ t('folders.save') }}</Button>
        </div>
      </form>
    </dialog>

    <dialog ref="renameDialogRef" class="folders-page__dialog" @click="onRenameBackdrop">
      <div class="folders-page__dialogPanel" @click.stop>
        <h2>{{ t('folders.rename') }}</h2>
        <label class="folders-page__field">
          <span>{{ t('folders.nameLabel') }}</span>
          <input v-model="renameName" maxlength="255">
        </label>
        <div class="folders-page__dialogActions">
          <Button variant="secondary" type="button" @click="renameDialogRef.close()">{{ t('folders.cancel') }}</Button>
          <Button type="button" @click="submitRename">{{ t('folders.save') }}</Button>
        </div>
      </div>
    </dialog>

    <dialog ref="iconDialogRef" class="folders-page__dialog" @click="onIconBackdrop">
      <div class="folders-page__dialogPanel" @click.stop>
        <h2>{{ t('folders.editIcon') }}</h2>
        <FolderIconPicker v-model:emoji="iconForm.icon_emoji" v-model:bg-color="iconForm.icon_bg_color" />
        <div class="folders-page__dialogActions">
          <Button variant="secondary" type="button" @click="iconDialogRef.close()">{{ t('folders.cancel') }}</Button>
          <Button type="button" @click="submitIcon">{{ t('folders.save') }}</Button>
        </div>
      </div>
    </dialog>

    <dialog ref="chatDialogRef" class="folders-page__dialog" @click="onChatDialogBackdrop">
      <form class="folders-page__dialogPanel" @submit.prevent="submitCreateChat" @click.stop>
        <h2>{{ t('folders.newChatTitle') }}</h2>
        <label class="folders-page__field">
          <span>{{ t('folders.chatTitle') }}</span>
          <input v-model="chatForm.title" :placeholder="t('folders.chatTitlePlaceholder')" maxlength="255">
        </label>
        <label class="folders-page__field">
          <span>{{ t('folders.parentLabel') }}</span>
          <select v-model.number="chatForm.folder_id">
            <option :value="null">{{ t('folders.unfiledRoot') }}</option>
            <option v-for="x in folders" :key="x.id" :value="Number(x.id)">{{ x.name }}</option>
          </select>
        </label>
        <div class="folders-page__field">
          <label for="folders-chat-members">{{ t('chats.modal.membersLabel') }}</label>
          <input
            id="folders-chat-members"
            v-model="memberSearchQuery"
            type="text"
            :placeholder="t('chats.modal.membersPlaceholder')"
          >
          <p v-if="memberSearchLoading" class="folders-page__memberHint">
            {{ t('chats.modal.membersSearching') }}
          </p>
          <ul v-else-if="memberSearchQuery.trim().length >= 2 && memberSearchResults.length > 0" class="folders-page__memberResults">
            <li v-for="member in memberSearchResults" :key="member.id" class="folders-page__memberResult">
              <button
                type="button"
                class="folders-page__memberResultBtn"
                :class="{ 'is-selected': isSelectedMember(member.id) }"
                @click="toggleMember(member)"
              >
                <span class="folders-page__memberResultName">{{ member.name }}</span>
                <span class="folders-page__memberResultMeta">{{ member.email }}</span>
              </button>
            </li>
          </ul>
          <p v-else-if="memberSearchQuery.trim().length >= 2" class="folders-page__memberHint">
            {{ t('chats.modal.membersEmpty') }}
          </p>
          <div v-if="selectedMembers.length > 0" class="folders-page__selectedMembers">
            <span
              v-for="member in selectedMembers"
              :key="member.id"
              class="folders-page__selectedMemberPill"
            >
              {{ member.name }}
              <button
                type="button"
                class="folders-page__selectedMemberRemove"
                :aria-label="`Remove ${member.name}`"
                @click="removeSelectedMember(member.id)"
              >
                ×
              </button>
            </span>
          </div>
        </div>
        <div class="folders-page__dialogActions">
          <Button type="button" variant="secondary" @click="chatDialogRef.close()">{{ t('folders.cancel') }}</Button>
          <Button type="submit">{{ t('folders.create') }}</Button>
        </div>
      </form>
    </dialog>

    <dialog ref="taskDialogRef" class="folders-page__dialog" @click="onTaskDialogBackdrop">
      <form class="folders-page__dialogPanel" @submit.prevent="submitCreateTask" @click.stop>
        <h2>{{ t('folders.newTaskTitle') }}</h2>
        <label class="folders-page__field">
          <span>{{ t('folders.taskTitle') }}</span>
          <input v-model="taskForm.title" :placeholder="t('folders.taskTitlePlaceholder')" required maxlength="255">
        </label>
        <label class="folders-page__field">
          <span>{{ t('folders.taskDescription') }}</span>
          <textarea v-model="taskForm.description" :placeholder="t('folders.taskDescriptionPlaceholder')" rows="4" maxlength="1000"></textarea>
        </label>
        <label class="folders-page__field">
          <span>{{ t('folders.parentLabel') }}</span>
          <select v-model.number="taskForm.folder_id">
            <option :value="null">{{ t('folders.unfiledRoot') }}</option>
            <option v-for="x in folders" :key="x.id" :value="Number(x.id)">{{ x.name }}</option>
          </select>
        </label>
        <div class="folders-page__dialogActions">
          <Button type="button" variant="secondary" @click="taskDialogRef.close()">{{ t('folders.cancel') }}</Button>
          <Button type="submit">{{ t('folders.create') }}</Button>
        </div>
      </form>
    </dialog>
  </section>
</template>

<style scoped lang="scss">
.folders-page {
  max-width: 960px;
  margin: 0 auto;
  padding: 1.25rem 1.5rem 3rem;
}

.folders-page__header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.folders-page__headerText {
  min-width: 0;
}

.folders-page__title {
  margin: 0 0 0.4rem;
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.2;
}

.folders-page__subline {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--text-muted, #6b7280);
  max-width: 36rem;
}

.folders-page__counts {
  font-weight: 500;
  color: var(--text, #374151);
}

.folders-page__sublineSep {
  margin: 0 0.35rem;
  opacity: 0.45;
}

.folders-page__tagline {
  color: var(--text-muted, #6b7280);
}

.folders-page__headerActions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

/* Search + view mode — one band under the hero */
.folders-page__controls {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.85rem 1rem;
  margin-bottom: 1.35rem;
  padding-bottom: 1.1rem;
  border-bottom: 1px solid var(--border, #e5e7eb);
}

.folders-page__searchPanel {
  flex: 1;
  min-width: min(100%, 16rem);
}

.folders-page__layout {
  display: grid;
  grid-template-columns: minmax(180px, 220px) 1fr;
  gap: 1.5rem;
  align-items: start;

  @media (max-width: 900px) {
    grid-template-columns: 1fr;
  }
}

.folders-page__aside {
  border-radius: 0.65rem;
  padding: 0.65rem 0.5rem;
  background: var(--surface-2, #f3f4f6);
  position: sticky;
  top: 0.5rem;
}

.folders-page__asideTitle {
  margin: 0 0 0.45rem 0.35rem;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted, #6b7280);
}

.folders-page__main {
  display: flex;
  flex-direction: column;
  border-radius: 0.65rem;
  padding: 0.25rem 0;
  min-height: 180px;
  border: 2px dashed transparent;
  transition: border-color 120ms ease, background 120ms ease;

  &.is-drop-root {
    border-color: var(--accent, #2563eb);
    background: var(--selection-bg, rgba(37, 99, 235, 0.04));
  }
}

.folders-page__dropHint {
  order: 10;
  font-size: 0.75rem;
  color: var(--text-muted, #9ca3af);
  margin: 1.25rem 0 0;
  padding-top: 0.75rem;
  border-top: 1px solid var(--border, #f3f4f6);
}

.folders-page__cards {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;

  &--grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 0.85rem;
  }
}

.folders-page__empty {
  color: var(--text-muted, #6b7280);
  font-size: 0.95rem;
  margin: 2rem 0 0;
  text-align: center;
}

.folders-page__hint {
  font-size: 0.9rem;
  color: var(--text-muted, #6b7280);
  padding: 0.25rem 0;
}

.folders-page__dialog {
  border: none;
  padding: 0;
  max-width: 28rem;
  width: calc(100% - 2rem);
  background: transparent;
  border-radius: 0.75rem;

  &::backdrop {
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(2px);
  }
}

.folders-page__dialogPanel {
  background: var(--bg, #fff);
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.75rem;
  padding: 1.5rem;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);

  h2 {
    margin: 0 0 1rem;
    font-size: 1.1rem;
    font-weight: 700;
  }
}

.folders-page__field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 0.85rem;
}

.folders-page__dialogActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
}

.folders-page__memberHint {
  margin: 0.5rem 0 0;
  font-size: 0.85rem;
  opacity: 0.8;
}

.folders-page__memberResults {
  list-style: none;
  padding: 0;
  margin: 0.5rem 0 0;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  max-height: 200px;
  overflow-y: auto;
}

.folders-page__memberResult {
  border-bottom: 1px solid var(--border);

  &:last-child {
    border-bottom: none;
  }
}

.folders-page__memberResultBtn {
  width: 100%;
  padding: 0.65rem 0.75rem;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;

  &:hover {
    background: var(--surface-2, rgba(0, 0, 0, 0.04));
  }

  &.is-selected {
    background: var(--accent, #2563eb);
    color: white;
  }
}

.folders-page__memberResultName {
  font-weight: 500;
  font-size: 0.9rem;
}

.folders-page__memberResultMeta {
  font-size: 0.8rem;
  opacity: 0.75;
}

.folders-page__selectedMembers {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.5rem;
}

.folders-page__selectedMemberPill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.25rem 0.6rem;
  background: rgba(37, 99, 235, 0.1);
  color: var(--accent, #2563eb);
  border-radius: 2rem;
  font-size: 0.825rem;
  font-weight: 500;
}

.folders-page__selectedMemberRemove {
  border: none;
  background: transparent;
  color: inherit;
  cursor: pointer;
  padding: 0;
  font-size: 1.25rem;
  line-height: 1;
  opacity: 0.8;

  &:hover {
    opacity: 1;
  }
}
</style>
