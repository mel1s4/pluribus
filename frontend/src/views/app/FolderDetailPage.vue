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
import { bulkMoveFolderItems, deleteChat, fetchChats, fetchFolderStats, updateFolder } from '../../services/chatApi.js'
import { deleteTask, fetchTasks } from '../../services/contentApi.js'

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

const renameDialogRef = ref(null)
const renameName = ref('')

const iconDialogRef = ref(null)
const iconForm = reactive({
  icon_emoji: '📁',
  icon_bg_color: '#6366f1',
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

function onKeydown(e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a') {
    const tag = (e.target && e.target.tagName) || ''
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return
    e.preventDefault()
    onBulkSelectAll()
  }
  if (e.key === 'Escape') bulk.clear()
}

onMounted(() => {
  load()
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <section v-if="!pageReady" class="folder-detail-page">
    <p>{{ t('folders.loading') }}</p>
  </section>

  <section v-else-if="folder" class="folder-detail-page" @dragend="drag.onDragEnd">
    <header class="folder-detail-page__header">
      <Button variant="secondary" size="sm" @click="router.push({ name: 'folders' })">
        {{ t('folders.backToBrowser') }}
      </Button>
      <div class="folder-detail-page__heading">
        <span
          class="folder-detail-page__emoji"
          :style="folder.icon_bg_color ? { background: folder.icon_bg_color } : undefined"
        >{{ folder.icon_emoji || '📁' }}</span>
        <div>
          <h1 class="folder-detail-page__title">{{ folder.name || t('folders.unnamed') }}</h1>
          <p v-if="stats" class="folder-detail-page__stats">
            {{ t('folders.stats.chatsShort') }}: {{ stats.chats_count }} · {{ t('folders.stats.tasksShort') }}: {{ stats.tasks_count }}
          </p>
        </div>
      </div>
      <div class="folder-detail-page__actions">
        <Button size="sm" variant="secondary" @click="onRenameFolder">{{ t('folders.rename') }}</Button>
        <Button size="sm" variant="secondary" @click="onEditIcon">{{ t('folders.editIcon') }}</Button>
        <Button size="sm" variant="danger" @click="onDeleteFolder">{{ t('folders.delete') }}</Button>
      </div>
    </header>

    <FolderBreadcrumb :ancestors="ancestors" @navigate="navigateBreadcrumb" />

    <div class="folder-detail-page__toolbar">
      <div class="folder-detail-page__viewToggle" role="group" :aria-label="t('folders.viewMode.label')">
        <button
          type="button"
          class="folder-detail-page__viewBtn"
          :class="{ 'is-active': viewMode === 'list' }"
          :aria-pressed="viewMode === 'list'"
          @click="viewMode = 'list'"
        >
          <Icon name="list" aria-hidden="true" />
          {{ t('folders.viewMode.list') }}
        </button>
        <button
          type="button"
          class="folder-detail-page__viewBtn"
          :class="{ 'is-active': viewMode === 'grid' }"
          :aria-pressed="viewMode === 'grid'"
          @click="viewMode = 'grid'"
        >
          <Icon name="note-sticky" aria-hidden="true" />
          {{ t('folders.viewMode.grid') }}
        </button>
      </div>
      <div class="folder-detail-page__filters">
        <select v-model="filterKind" class="folder-detail-page__select">
          <option value="all">{{ t('folders.filterAll') }}</option>
          <option value="chat">{{ t('folders.filterChats') }}</option>
          <option value="task">{{ t('folders.filterTasks') }}</option>
        </select>
        <select v-model="taskFilter" class="folder-detail-page__select">
          <option value="all">{{ t('folders.taskFilterAll') }}</option>
          <option value="open">{{ t('folders.taskFilterOpen') }}</option>
          <option value="done">{{ t('folders.taskFilterDone') }}</option>
        </select>
        <input
          v-model="textFilter"
          type="search"
          class="folder-detail-page__search"
          :placeholder="t('folders.filterByText')"
        >
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
  </section>

  <section v-else class="folder-detail-page folder-detail-page--missing">
    <p>{{ t('folders.notFound') }}</p>
    <Button @click="router.push({ name: 'folders' })">{{ t('folders.backToBrowser') }}</Button>
  </section>
</template>

<style scoped lang="scss">
.folder-detail-page {
  max-width: 960px;
  margin: 0 auto;
  padding: 1rem 1.25rem 2rem;
}

.folder-detail-page__header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.folder-detail-page__heading {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  flex: 1;
  min-width: 0;
}

.folder-detail-page__emoji {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 0.55rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  background: var(--surface-2, #e5e7eb);
  flex-shrink: 0;
}

.folder-detail-page__title {
  margin: 0;
  font-size: 1.35rem;
  word-break: break-word;
}

.folder-detail-page__stats {
  margin: 0.25rem 0 0;
  font-size: 0.85rem;
  opacity: 0.8;
}

.folder-detail-page__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.folder-detail-page__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  margin: 0.75rem 0;
}

.folder-detail-page__viewToggle {
  display: inline-flex;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  overflow: hidden;
}

.folder-detail-page__viewBtn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.4rem 0.65rem;
  border: none;
  background: transparent;
  font: inherit;
  cursor: pointer;
  color: inherit;

  &.is-active {
    background: var(--surface-2, rgba(0, 0, 0, 0.06));
    font-weight: 600;
  }

  &:not(:last-child) {
    border-right: 1px solid var(--border);
  }
}

.folder-detail-page__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-left: auto;
}

.folder-detail-page__select,
.folder-detail-page__search {
  min-width: 8rem;
}

.folder-detail-page__drop {
  border: 1px dashed transparent;
  border-radius: 0.5rem;
  padding: 0.5rem;

  &.is-drop-over {
    border-color: var(--accent, #2563eb);
    background: var(--selection-bg, rgba(37, 99, 235, 0.06));
  }
}

.folder-detail-page__empty {
  margin: 1rem 0 0;
  opacity: 0.8;
}

.folder-detail-page--missing {
  text-align: center;
  padding: 2rem;
}

.folder-detail-page__dialog {
  border: none;
  padding: 0;
  max-width: 26rem;
  width: calc(100% - 2rem);
  background: transparent;

  &::backdrop {
    background: rgba(0, 0, 0, 0.45);
  }
}

.folder-detail-page__dialogPanel {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 0.65rem;
  padding: 1.25rem;
}

.folder-detail-page__field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin: 0.75rem 0;
}

.folder-detail-page__dialogActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
