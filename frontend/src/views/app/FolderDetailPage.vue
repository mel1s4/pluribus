<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import FolderBreadcrumb from '../../molecules/FolderBreadcrumb.vue'
import FolderIconPicker from '../../molecules/FolderIconPicker.vue'
import FolderMoveDialog from '../../molecules/FolderMoveDialog.vue'
import Icon from '../../atoms/Icon.vue'
import FolderBulkActions from '../../organisms/FolderBulkActions.vue'
import ItemGridView from '../../organisms/ItemGridView.vue'
import ItemListView from '../../organisms/ItemListView.vue'
import { useBulkSelection } from '../../composables/useBulkSelection.js'
import { useDragDrop } from '../../composables/useDragDrop.js'
import { getFolderAncestors, useFolders } from '../../composables/useFolders.js'
import { t } from '../../i18n/i18n'
import { bulkMoveFolderItems, createChat, deleteChat, fetchChats, fetchFolderStats, updateFolder } from '../../services/chatApi.js'
import { createTask, deleteTask, fetchTasks } from '../../services/contentApi.js'
import { searchUsers } from '../../services/usersApi.js'

const route = useRoute()
const router = useRouter()
const folderId = computed(() => Number(route.params.folderId))

const { folders, load: loadFolders, folderById, deleteFolder } = useFolders()

const chats = ref([])
const tasks = ref([])
const stats = ref(null)
const viewMode = ref(/** @type {'list'|'grid'} */ ('list'))
const filterKind = ref(/** @type {'all'|'chat'|'task'} */ ('all'))
const taskFilter = ref(/** @type {'all'|'open'|'done'} */ ('all'))
const textFilter = ref('')

const moveDialogOpen = ref(false)
const pageReady = ref(false)
const moreMenuOpen = ref(false)

const renameDialogRef = ref(null)
const renameName = ref('')

const iconDialogRef = ref(null)
const iconForm = reactive({
  icon_emoji: '📁',
  icon_bg_color: '#6366f1',
})

const chatDialogRef = ref(null)
const chatForm = reactive({
  title: '',
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
})

const drag = useDragDrop()

const orderedRowKeys = computed(() => filteredRows.value.map((r) => r.key))

const bulk = useBulkSelection({
  getOrderedIds: () => orderedRowKeys.value,
})

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

const folder = computed(() => folderById(folderId.value))

const ancestors = computed(() => {
  if (!folder.value) return []
  const chain = getFolderAncestors(folders.value, folderId.value)
  return chain
})

const folderChats = computed(() =>
  chats.value.filter((c) => Number(c.folder_id) === folderId.value),
)

const folderTasks = computed(() =>
  tasks.value.filter((tk) => Number(tk.folder_id) === folderId.value),
)

const mergedRows = computed(() => {
  const rows = []
  for (const c of folderChats.value) {
    rows.push({
      key: `chat:${c.id}`,
      kind: 'chat',
      item: c,
      sort: new Date(c.updated_at || c.created_at || 0).getTime(),
    })
  }
  for (const tk of folderTasks.value) {
    rows.push({
      key: `task:${tk.id}`,
      kind: 'task',
      item: tk,
      sort: new Date(tk.updated_at || tk.created_at || 0).getTime(),
    })
  }
  rows.sort((a, b) => b.sort - a.sort)
  return rows
})

const filteredRows = computed(() => {
  let rows = mergedRows.value
  if (filterKind.value === 'chat') rows = rows.filter((r) => r.kind === 'chat')
  if (filterKind.value === 'task') rows = rows.filter((r) => r.kind === 'task')
  if (filterKind.value === 'task' || filterKind.value === 'all') {
    if (taskFilter.value === 'open') {
      rows = rows.filter((r) => r.kind !== 'task' || !r.item.completed_at)
    }
    if (taskFilter.value === 'done') {
      rows = rows.filter((r) => r.kind !== 'task' || r.item.completed_at)
    }
  }
  const q = textFilter.value.trim().toLowerCase()
  if (q) {
    rows = rows.filter((r) => {
      const title = (r.item.title || '').toLowerCase()
      const desc = (r.item.description || '').toLowerCase()
      return title.includes(q) || desc.includes(q)
    })
  }
  return rows
})

async function load() {
  pageReady.value = false
  await loadFolders()
  const [chRes, tkRes, stRes] = await Promise.all([
    fetchChats(),
    fetchTasks(),
    fetchFolderStats(folderId.value),
  ])
  if (chRes.ok) chats.value = unwrapList(chRes.data)
  if (tkRes.ok) tasks.value = unwrapList(tkRes.data)
  if (stRes.ok && stRes.data && typeof stRes.data === 'object' && stRes.data.stats) {
    stats.value = stRes.data.stats
  } else {
    stats.value = null
  }
  pageReady.value = true
}

watch(folderId, () => {
  bulk.clear()
  load()
})

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

function navigateBreadcrumb(id) {
  if (id == null) router.push({ name: 'folders' })
  else router.push({ name: 'folderDetail', params: { folderId: id } })
}

function openChat(c) {
  router.push({ name: 'chatThread', params: { chatId: c.id } })
}

function openTask() {
  router.push({ name: 'tasks' })
}

function onToggleSelect(type, id, checked) {
  if (checked) bulk.select(type, id)
  else bulk.deselect(type, id)
}

function startChatDrag(item, e) {
  drag.onDragStart(e, { type: 'chat', id: item.id })
}

function startTaskDrag(item, e) {
  drag.onDragStart(e, { type: 'task', id: item.id })
}

async function onDropCurrentFolder(e) {
  const payload = drag.readPayload(e)
  drag.dragOverTargetId.value = null
  if (!payload) return
  if (payload.type === 'folder') return
  const res = await bulkMoveFolderItems({
    target_folder_id: folderId.value,
    items: [{ type: payload.type, id: payload.id }],
  })
  if (res.ok) await load()
}

function onBulkMove() {
  moveDialogOpen.value = true
}

async function onMoveConfirm(targetId) {
  const items = bulk.items.value.map((it) => ({ type: it.type, id: it.id })).filter((it) => it.type && it.id != null)
  if (!items.length) return
  const res = await bulkMoveFolderItems({
    target_folder_id: targetId,
    items,
  })
  if (res.ok) {
    bulk.clear()
    await load()
  }
}

async function onBulkDelete() {
  if (!window.confirm(t('folders.bulkDeleteConfirm'))) return
  for (const it of bulk.items.value) {
    if (it.type === 'chat') await deleteChat(it.id)
    else await deleteTask(it.id)
  }
  bulk.clear()
  await load()
}

function onBulkSelectAll() {
  bulk.selectAllFromKeys(orderedRowKeys.value)
}

function onRenameFolder() {
  if (!folder.value) return
  renameName.value = folder.value.name || ''
  renameDialogRef.value?.showModal()
}

async function submitRename() {
  const res = await updateFolder(folderId.value, { name: renameName.value.trim() })
  if (!res.ok) return
  renameDialogRef.value?.close()
  await load()
}

function onEditIcon() {
  if (!folder.value) return
  iconForm.icon_emoji = folder.value.icon_emoji || '📁'
  iconForm.icon_bg_color = /^#[0-9A-Fa-f]{6}$/.test(folder.value.icon_bg_color || '')
    ? folder.value.icon_bg_color
    : '#6366f1'
  iconDialogRef.value?.showModal()
}

async function submitIcon() {
  const res = await updateFolder(folderId.value, {
    icon_emoji: iconForm.icon_emoji?.trim() || null,
    icon_bg_color: iconForm.icon_bg_color || null,
  })
  if (!res.ok) return
  iconDialogRef.value?.close()
  await load()
}

async function onDeleteFolder() {
  if (!window.confirm(t('folders.deleteFolderConfirm'))) return
  await deleteFolder(folderId.value)
  router.push({ name: 'folders' })
}

function onRenameDialogBackdrop(e) {
  if (e.target === renameDialogRef.value) renameDialogRef.value?.close()
}

function onIconDialogBackdrop(e) {
  if (e.target === iconDialogRef.value) iconDialogRef.value?.close()
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

async function submitCreateChat() {
  const payload = {
    type: 'group',
    title: chatForm.title.trim() || null,
    folder_id: folderId.value,
    member_ids: selectedMembers.value.map((member) => Number(member.id)).filter((id) => Number.isFinite(id)),
  }
  const res = await createChat(payload)
  if (!res.ok) return
  chatDialogRef.value?.close()
  chatForm.title = ''
  memberSearchQuery.value = ''
  memberSearchResults.value = []
  selectedMembers.value = []
  await load()
  const id = res.data?.chat?.id
  if (id != null) router.push({ name: 'chatThread', params: { chatId: id } })
}

async function submitCreateTask() {
  const title = taskForm.title.trim()
  if (!title) return
  const payload = {
    title,
    description: taskForm.description.trim() || null,
    folder_id: folderId.value,
  }
  const res = await createTask(payload)
  if (!res.ok) return
  taskDialogRef.value?.close()
  taskForm.title = ''
  taskForm.description = ''
  await load()
}

function onKeydown(e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a') {
    const tag = (e.target && e.target.tagName) || ''
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return
    e.preventDefault()
    onBulkSelectAll()
  }
  if (e.key === 'Escape') {
    bulk.clear()
    moreMenuOpen.value = false
  }
}

function onDocumentClick() {
  moreMenuOpen.value = false
}

onMounted(() => {
  load()
  window.addEventListener('keydown', onKeydown)
  document.addEventListener('click', onDocumentClick)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
  document.removeEventListener('click', onDocumentClick)
  clearMemberSearchTimer()
})
</script>

<template>
  <section v-if="!pageReady" class="folder-detail-page">
    <p class="folder-detail-page__loading">{{ t('folders.loading') }}</p>
  </section>

  <section v-else-if="folder" class="folder-detail-page" @dragend="drag.onDragEnd">
    <!-- Breadcrumb nav -->
    <nav class="folder-detail-page__nav">
      <button type="button" class="folder-detail-page__back" @click="router.push({ name: 'folders' })">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M8.5 3L5 7l3.5 4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
        {{ t('folders.backToBrowser') }}
      </button>
      <FolderBreadcrumb :ancestors="ancestors" @navigate="navigateBreadcrumb" />
    </nav>

    <!-- Hero header -->
    <header class="folder-detail-page__header">
      <div class="folder-detail-page__heading">
        <button
          type="button"
          class="folder-detail-page__emoji"
          :style="folder.icon_bg_color ? { background: folder.icon_bg_color } : undefined"
          :title="t('folders.editIcon')"
          @click="onEditIcon"
        >{{ folder.icon_emoji || '📁' }}</button>
        <div class="folder-detail-page__meta">
          <h1 class="folder-detail-page__title">{{ folder.name || t('folders.unnamed') }}</h1>
          <p v-if="stats" class="folder-detail-page__stats">
            {{ stats.chats_count }} {{ t('folders.stats.chatsShort') }} · {{ stats.tasks_count }} {{ t('folders.stats.tasksShort') }}
          </p>
        </div>
      </div>

      <div class="folder-detail-page__actions">
        <Button size="sm" @click="chatDialogRef.showModal()">+ {{ t('folders.newChat') }}</Button>
        <Button size="sm" variant="secondary" @click="taskDialogRef.showModal()">+ {{ t('folders.newTask') }}</Button>
        <div class="folder-detail-page__more" @click.stop>
          <button
            type="button"
            class="folder-detail-page__moreBtn"
            :aria-expanded="moreMenuOpen"
            :aria-label="t('folders.moreActions')"
            @click="moreMenuOpen = !moreMenuOpen"
          >
            <svg width="18" height="18" viewBox="0 0 18 18" fill="currentColor" aria-hidden="true"><circle cx="9" cy="4" r="1.5"/><circle cx="9" cy="9" r="1.5"/><circle cx="9" cy="14" r="1.5"/></svg>
          </button>
          <div v-if="moreMenuOpen" class="folder-detail-page__dropdown">
            <button type="button" class="folder-detail-page__dropdownItem" @click="onRenameFolder(); moreMenuOpen = false">
              {{ t('folders.rename') }}
            </button>
            <button type="button" class="folder-detail-page__dropdownItem" @click="onEditIcon(); moreMenuOpen = false">
              {{ t('folders.editIcon') }}
            </button>
            <div class="folder-detail-page__dropdownDivider" />
            <button type="button" class="folder-detail-page__dropdownItem folder-detail-page__dropdownItem--danger" @click="onDeleteFolder(); moreMenuOpen = false">
              {{ t('folders.delete') }}
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Toolbar: type tabs + task sub-filter + search + view toggle -->
    <div class="folder-detail-page__toolbar">
      <div class="folder-detail-page__tabs" role="tablist" :aria-label="t('folders.filterAll')">
        <button
          type="button"
          role="tab"
          class="folder-detail-page__tab"
          :class="{ 'is-active': filterKind === 'all' }"
          :aria-selected="filterKind === 'all'"
          @click="filterKind = 'all'"
        >{{ t('folders.filterAll') }}</button>
        <button
          type="button"
          role="tab"
          class="folder-detail-page__tab"
          :class="{ 'is-active': filterKind === 'chat' }"
          :aria-selected="filterKind === 'chat'"
          @click="filterKind = 'chat'"
        >{{ t('folders.filterChats') }}</button>
        <button
          type="button"
          role="tab"
          class="folder-detail-page__tab"
          :class="{ 'is-active': filterKind === 'task' }"
          :aria-selected="filterKind === 'task'"
          @click="filterKind = 'task'"
        >{{ t('folders.filterTasks') }}</button>
      </div>

      <div class="folder-detail-page__toolbarRight">
        <div v-if="filterKind === 'task' || filterKind === 'all'" class="folder-detail-page__subTabs">
          <button type="button" class="folder-detail-page__subTab" :class="{ 'is-active': taskFilter === 'all' }" @click="taskFilter = 'all'">{{ t('folders.taskFilterAll') }}</button>
          <button type="button" class="folder-detail-page__subTab" :class="{ 'is-active': taskFilter === 'open' }" @click="taskFilter = 'open'">{{ t('folders.taskFilterOpen') }}</button>
          <button type="button" class="folder-detail-page__subTab" :class="{ 'is-active': taskFilter === 'done' }" @click="taskFilter = 'done'">{{ t('folders.taskFilterDone') }}</button>
        </div>
        <input
          v-model="textFilter"
          type="search"
          class="folder-detail-page__search"
          :placeholder="t('folders.filterByText')"
        >
        <div class="folder-detail-page__viewToggle" role="group" :aria-label="t('folders.viewMode.label')">
          <button
            type="button"
            class="folder-detail-page__viewBtn"
            :class="{ 'is-active': viewMode === 'list' }"
            :aria-pressed="viewMode === 'list'"
            :title="t('folders.viewMode.list')"
            @click="viewMode = 'list'"
          >
            <Icon name="list" aria-hidden="true" />
          </button>
          <button
            type="button"
            class="folder-detail-page__viewBtn"
            :class="{ 'is-active': viewMode === 'grid' }"
            :aria-pressed="viewMode === 'grid'"
            :title="t('folders.viewMode.grid')"
            @click="viewMode = 'grid'"
          >
            <Icon name="note-sticky" aria-hidden="true" />
          </button>
        </div>
      </div>
    </div>

    <FolderBulkActions
      :count="bulk.count"
      @move="onBulkMove"
      @delete="onBulkDelete"
      @clear="bulk.clear()"
      @select-all="onBulkSelectAll"
    />

    <div
      class="folder-detail-page__drop"
      :class="{ 'is-drop-over': drag.dragOverTargetId === String(folderId) }"
      @dragover="drag.onDragOverFolder($event, folderId)"
      @dragleave="drag.onDragLeaveFolder($event, folderId)"
      @drop="onDropCurrentFolder"
    >
      <ItemListView
        v-if="viewMode === 'list'"
        :items="filteredRows"
        show-checkboxes
        :is-selected="bulk.isSelected"
        @open-chat="openChat"
        @open-task="openTask"
        @toggle-select="onToggleSelect"
        @drag-start-chat="startChatDrag"
        @drag-end-chat="drag.onDragEnd"
        @drag-start-task="startTaskDrag"
        @drag-end-task="drag.onDragEnd"
      />
      <ItemGridView
        v-else
        :items="filteredRows"
        show-checkboxes
        :is-selected="bulk.isSelected"
        @open-chat="openChat"
        @open-task="openTask"
        @toggle-select="onToggleSelect"
        @drag-start-chat="startChatDrag"
        @drag-end-chat="drag.onDragEnd"
        @drag-start-task="startTaskDrag"
        @drag-end-task="drag.onDragEnd"
      />

      <p v-if="!filteredRows.length" class="folder-detail-page__empty">{{ t('folders.detailEmpty') }}</p>
    </div>

    <FolderMoveDialog
      v-model:open="moveDialogOpen"
      :folders="folders"
      :exclude-folder-id="folderId"
      @confirm="onMoveConfirm"
    />

    <dialog ref="renameDialogRef" class="folder-detail-page__dialog" @click="onRenameDialogBackdrop">
      <form class="folder-detail-page__dialogPanel" @submit.prevent="submitRename" @click.stop>
        <h2>{{ t('folders.rename') }}</h2>
        <label class="folder-detail-page__field">
          <span>{{ t('folders.nameLabel') }}</span>
          <input v-model="renameName" maxlength="255" required>
        </label>
        <div class="folder-detail-page__dialogActions">
          <Button type="button" variant="secondary" @click="renameDialogRef.close()">{{ t('folders.cancel') }}</Button>
          <Button type="submit">{{ t('folders.save') }}</Button>
        </div>
      </form>
    </dialog>

    <dialog ref="iconDialogRef" class="folder-detail-page__dialog" @click="onIconDialogBackdrop">
      <div class="folder-detail-page__dialogPanel" @click.stop>
        <h2>{{ t('folders.editIcon') }}</h2>
        <FolderIconPicker v-model:emoji="iconForm.icon_emoji" v-model:bg-color="iconForm.icon_bg_color" />
        <div class="folder-detail-page__dialogActions">
          <Button variant="secondary" @click="iconDialogRef.close()">{{ t('folders.cancel') }}</Button>
          <Button @click="submitIcon">{{ t('folders.save') }}</Button>
        </div>
      </div>
    </dialog>

    <dialog ref="chatDialogRef" class="folder-detail-page__dialog" @click="onChatDialogBackdrop">
      <form class="folder-detail-page__dialogPanel" @submit.prevent="submitCreateChat" @click.stop>
        <h2>{{ t('folders.newChatTitle') }}</h2>
        <label class="folder-detail-page__field">
          <span>{{ t('folders.chatTitle') }}</span>
          <input v-model="chatForm.title" :placeholder="t('folders.chatTitlePlaceholder')" maxlength="255">
        </label>
        <div class="folder-detail-page__field">
          <label for="folder-detail-chat-members">{{ t('chats.modal.membersLabel') }}</label>
          <input
            id="folder-detail-chat-members"
            v-model="memberSearchQuery"
            type="text"
            :placeholder="t('chats.modal.membersPlaceholder')"
          >
          <p v-if="memberSearchLoading" class="folder-detail-page__memberHint">
            {{ t('chats.modal.membersSearching') }}
          </p>
          <ul v-else-if="memberSearchQuery.trim().length >= 2 && memberSearchResults.length > 0" class="folder-detail-page__memberResults">
            <li v-for="member in memberSearchResults" :key="member.id" class="folder-detail-page__memberResult">
              <button
                type="button"
                class="folder-detail-page__memberResultBtn"
                :class="{ 'is-selected': isSelectedMember(member.id) }"
                @click="toggleMember(member)"
              >
                <span class="folder-detail-page__memberResultName">{{ member.name }}</span>
                <span class="folder-detail-page__memberResultMeta">{{ member.email }}</span>
              </button>
            </li>
          </ul>
          <p v-else-if="memberSearchQuery.trim().length >= 2" class="folder-detail-page__memberHint">
            {{ t('chats.modal.membersEmpty') }}
          </p>
          <div v-if="selectedMembers.length > 0" class="folder-detail-page__selectedMembers">
            <span
              v-for="member in selectedMembers"
              :key="member.id"
              class="folder-detail-page__selectedMemberPill"
            >
              {{ member.name }}
              <button
                type="button"
                class="folder-detail-page__selectedMemberRemove"
                :aria-label="`Remove ${member.name}`"
                @click="removeSelectedMember(member.id)"
              >
                ×
              </button>
            </span>
          </div>
        </div>
        <div class="folder-detail-page__dialogActions">
          <Button type="button" variant="secondary" @click="chatDialogRef.close()">{{ t('folders.cancel') }}</Button>
          <Button type="submit">{{ t('folders.create') }}</Button>
        </div>
      </form>
    </dialog>

    <dialog ref="taskDialogRef" class="folder-detail-page__dialog" @click="onTaskDialogBackdrop">
      <form class="folder-detail-page__dialogPanel" @submit.prevent="submitCreateTask" @click.stop>
        <h2>{{ t('folders.newTaskTitle') }}</h2>
        <label class="folder-detail-page__field">
          <span>{{ t('folders.taskTitle') }}</span>
          <input v-model="taskForm.title" :placeholder="t('folders.taskTitlePlaceholder')" required maxlength="255">
        </label>
        <label class="folder-detail-page__field">
          <span>{{ t('folders.taskDescription') }}</span>
          <textarea v-model="taskForm.description" :placeholder="t('folders.taskDescriptionPlaceholder')" rows="4" maxlength="1000"></textarea>
        </label>
        <div class="folder-detail-page__dialogActions">
          <Button type="button" variant="secondary" @click="taskDialogRef.close()">{{ t('folders.cancel') }}</Button>
          <Button type="submit">{{ t('folders.create') }}</Button>
        </div>
      </form>
    </dialog>
  </section>

  <section v-else class="folder-detail-page folder-detail-page--missing">
    <p>{{ t('folders.notFound') }}</p>
    <Button @click="router.push({ name: 'folders' })">{{ t('folders.backToBrowser') }}</Button>
  </section>
</template>

<style scoped lang="scss">
/* ─── Page shell ──────────────────────────────────────────── */
.folder-detail-page {
  max-width: 900px;
  margin: 0 auto;
  padding: 1.25rem 1.5rem 3rem;
}

.folder-detail-page__loading {
  padding: 3rem;
  text-align: center;
  opacity: 0.6;
}

/* ─── Breadcrumb nav ──────────────────────────────────────── */
.folder-detail-page__nav {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.folder-detail-page__back {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  border: none;
  background: transparent;
  cursor: pointer;
  font: inherit;
  font-size: 0.875rem;
  color: var(--text-muted, #6b7280);
  padding: 0.25rem 0.5rem;
  border-radius: 0.4rem;
  transition: color 120ms ease, background 120ms ease;

  &:hover {
    color: var(--text, #111827);
    background: var(--surface-2, rgba(0, 0, 0, 0.05));
  }
}

/* ─── Hero header ─────────────────────────────────────────── */
.folder-detail-page__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.75rem;
  flex-wrap: wrap;
}

.folder-detail-page__heading {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  flex: 1;
  min-width: 0;
}

.folder-detail-page__emoji {
  width: 3.25rem;
  height: 3.25rem;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
  background: var(--surface-2, #e5e7eb);
  flex-shrink: 0;
  border: none;
  cursor: pointer;
  transition: transform 120ms ease, box-shadow 120ms ease;

  &:hover {
    transform: scale(1.06);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.12);
  }
}

.folder-detail-page__meta {
  min-width: 0;
}

.folder-detail-page__title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  word-break: break-word;
  line-height: 1.2;
}

.folder-detail-page__stats {
  margin: 0.3rem 0 0;
  font-size: 0.8rem;
  color: var(--text-muted, #6b7280);
  letter-spacing: 0.01em;
}

/* ─── Header action buttons + kebab ──────────────────────── */
.folder-detail-page__actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.folder-detail-page__more {
  position: relative;
}

.folder-detail-page__moreBtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border, #e5e7eb);
  background: transparent;
  cursor: pointer;
  color: var(--text-muted, #6b7280);
  transition: background 120ms ease, color 120ms ease;

  &:hover,
  &[aria-expanded="true"] {
    background: var(--surface-2, rgba(0, 0, 0, 0.05));
    color: var(--text, #111827);
  }
}

.folder-detail-page__dropdown {
  position: absolute;
  top: calc(100% + 0.35rem);
  right: 0;
  min-width: 11rem;
  background: var(--bg, #fff);
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.65rem;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  padding: 0.35rem;
  z-index: 200;
}

.folder-detail-page__dropdownItem {
  display: block;
  width: 100%;
  padding: 0.55rem 0.85rem;
  border: none;
  background: transparent;
  text-align: left;
  font: inherit;
  font-size: 0.9rem;
  cursor: pointer;
  border-radius: 0.45rem;
  color: var(--text, #111827);
  transition: background 100ms ease;

  &:hover {
    background: var(--surface-2, rgba(0, 0, 0, 0.05));
  }

  &--danger {
    color: #dc2626;

    &:hover {
      background: rgba(220, 38, 38, 0.07);
    }
  }
}

.folder-detail-page__dropdownDivider {
  height: 1px;
  background: var(--border, #e5e7eb);
  margin: 0.3rem 0.5rem;
}

/* ─── Toolbar ─────────────────────────────────────────────── */
.folder-detail-page__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border, #e5e7eb);
}

/* Type tabs (All / Chats / Tasks) */
.folder-detail-page__tabs {
  display: inline-flex;
  gap: 0.2rem;
  background: var(--surface-2, #f3f4f6);
  border-radius: 0.6rem;
  padding: 0.25rem;
}

.folder-detail-page__tab {
  padding: 0.35rem 0.9rem;
  border: none;
  background: transparent;
  border-radius: 0.45rem;
  font: inherit;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  color: var(--text-muted, #6b7280);
  transition: background 120ms ease, color 120ms ease;

  &.is-active {
    background: var(--bg, #fff);
    color: var(--text, #111827);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
  }

  &:not(.is-active):hover {
    color: var(--text, #111827);
  }
}

/* Right side of toolbar */
.folder-detail-page__toolbarRight {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

/* Task sub-filter (All / Open / Done) */
.folder-detail-page__subTabs {
  display: inline-flex;
  gap: 0.15rem;
}

.folder-detail-page__subTab {
  padding: 0.3rem 0.65rem;
  border: 1px solid transparent;
  background: transparent;
  border-radius: 2rem;
  font: inherit;
  font-size: 0.8rem;
  cursor: pointer;
  color: var(--text-muted, #6b7280);
  transition: background 100ms ease, color 100ms ease, border-color 100ms ease;

  &.is-active {
    background: var(--bg, #fff);
    border-color: var(--border, #e5e7eb);
    color: var(--text, #111827);
    font-weight: 600;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.07);
  }

  &:not(.is-active):hover {
    color: var(--text, #111827);
  }
}

.folder-detail-page__search {
  height: 2rem;
  padding: 0 0.75rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  font: inherit;
  font-size: 0.875rem;
  background: var(--bg, #fff);
  color: var(--text, #111827);
  width: 11rem;
  min-width: 8rem;
  max-width: 100%;
  transition: border-color 120ms ease, box-shadow 120ms ease;

  &:focus {
    outline: none;
    border-color: var(--accent, #2563eb);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }

  &::placeholder {
    color: var(--text-muted, #9ca3af);
  }
}

/* Icon-only view toggle */
.folder-detail-page__viewToggle {
  display: inline-flex;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  overflow: hidden;
}

.folder-detail-page__viewBtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  background: transparent;
  cursor: pointer;
  color: var(--text-muted, #6b7280);
  transition: background 100ms ease, color 100ms ease;

  &.is-active {
    background: var(--surface-2, rgba(0, 0, 0, 0.06));
    color: var(--text, #111827);
  }

  &:not(:last-child) {
    border-right: 1px solid var(--border, #e5e7eb);
  }

  &:hover:not(.is-active) {
    background: var(--surface-2, rgba(0, 0, 0, 0.03));
  }
}

/* ─── Drop zone ───────────────────────────────────────────── */
.folder-detail-page__drop {
  border: 2px dashed transparent;
  border-radius: 0.65rem;
  padding: 0.25rem;
  transition: border-color 120ms ease, background 120ms ease;

  &.is-drop-over {
    border-color: var(--accent, #2563eb);
    background: var(--selection-bg, rgba(37, 99, 235, 0.04));
  }
}

.folder-detail-page__empty {
  margin: 3rem 0;
  text-align: center;
  color: var(--text-muted, #6b7280);
  font-size: 0.95rem;
}

/* ─── Missing folder ──────────────────────────────────────── */
.folder-detail-page--missing {
  text-align: center;
  padding: 4rem 2rem;
}

/* ─── Dialogs ─────────────────────────────────────────────── */
.folder-detail-page__dialog {
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

.folder-detail-page__dialogPanel {
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

.folder-detail-page__field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin: 0.75rem 0;

  > span,
  > label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-muted, #374151);
  }
}

.folder-detail-page__dialogActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1.25rem;
}

/* ─── Member search (in chat dialog) ─────────────────────── */
.folder-detail-page__memberHint {
  margin: 0.5rem 0 0;
  font-size: 0.85rem;
  color: var(--text-muted, #6b7280);
}

.folder-detail-page__memberResults {
  list-style: none;
  padding: 0;
  margin: 0.5rem 0 0;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  max-height: 200px;
  overflow-y: auto;
}

.folder-detail-page__memberResult {
  border-bottom: 1px solid var(--border, #e5e7eb);

  &:last-child {
    border-bottom: none;
  }
}

.folder-detail-page__memberResultBtn {
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

.folder-detail-page__memberResultName {
  font-weight: 500;
  font-size: 0.9rem;
}

.folder-detail-page__memberResultMeta {
  font-size: 0.8rem;
  opacity: 0.75;
}

.folder-detail-page__selectedMembers {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.5rem;
}

.folder-detail-page__selectedMemberPill {
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

.folder-detail-page__selectedMemberRemove {
  border: none;
  background: transparent;
  color: inherit;
  cursor: pointer;
  padding: 0;
  font-size: 1.1rem;
  line-height: 1;
  opacity: 0.7;

  &:hover {
    opacity: 1;
  }
}

/* ─── Mobile responsive styles ────────────────────────────── */
@media (max-width: 767px) {
  .folder-detail-page {
    padding: 1rem 1rem 3rem;
  }

  .folder-detail-page__header {
    flex-direction: column;
    align-items: stretch;
    gap: 1.25rem;
  }

  .folder-detail-page__actions {
    flex-wrap: wrap;
    gap: 0.75rem;
  }

  .folder-detail-page__toolbar {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .folder-detail-page__toolbarRight {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }

  .folder-detail-page__search {
    width: 100%;
    min-width: unset;
  }

  .folder-detail-page__tabs {
    justify-content: center;
  }

  .folder-detail-page__viewToggle {
    align-self: center;
  }
}

@media (max-width: 480px) {
  .folder-detail-page {
    padding: 0.75rem 0.75rem 3rem;
  }

  .folder-detail-page__heading {
    gap: 0.75rem;
  }

  .folder-detail-page__emoji {
    width: 2.75rem;
    height: 2.75rem;
    font-size: 1.4rem;
  }

  .folder-detail-page__title {
    font-size: 1.25rem;
  }

  .folder-detail-page__actions {
    flex-direction: column;
  }

  .folder-detail-page__tabs {
    flex-direction: column;
    width: 100%;
  }

  .folder-detail-page__tab {
    text-align: center;
    padding: 0.5rem;
  }

  .folder-detail-page__subTabs {
    justify-content: center;
    flex-wrap: wrap;
  }
}
</style>
