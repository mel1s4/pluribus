<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '../atoms/Icon.vue'
import FolderBulkActions from './FolderBulkActions.vue'
import FolderMoveDialog from '../molecules/FolderMoveDialog.vue'
import ItemGridView from './ItemGridView.vue'
import ItemListView from './ItemListView.vue'
import TaskDetailSidebar from '../components/Tasks/TaskDetailSidebar.vue'
import ChatEditDialogs from '../molecules/ChatEditDialogs.vue'
import { useBulkSelection } from '../composables/useBulkSelection.js'
import { useChatUnread } from '../composables/useChatUnread.js'
import { useDragDrop } from '../composables/useDragDrop.js'
import { buildMergedRows, filterExplorerRows, folderFocusQueryToFilterKind } from '../composables/useFolderExplorerContent.js'
import { t } from '../i18n/i18n'
import { bulkMoveFolderItems, deleteChat } from '../services/chatApi.js'
import { deleteTask, updateTask } from '../services/contentApi.js'
import { deleteNote } from '../services/notesApi.js'

const props = defineProps({
  chats: { type: Array, default: () => [] },
  tasks: { type: Array, default: () => [] },
  notes: { type: Array, default: () => [] },
  folders: { type: Array, default: () => [] },
  /** 'list' | 'grid' */
  viewMode: { type: String, default: 'grid' },
  calendars: { type: Array, default: () => [] },
  groups: { type: Array, default: () => [] },
  /** e.g. `tasks` from route query to preset task tab */
  focusKind: { type: String, default: '' },
})

const emit = defineEmits(['refresh'])

const router = useRouter()
const { getChatUnread } = useChatUnread()

const filterKind = ref(/** @type {'all'|'chat'|'task'|'note'} */ ('all'))
const taskFilter = ref(/** @type {'all'|'open'|'done'} */ ('all'))
const textFilter = ref('')
const moveDialogOpen = ref(false)
const chatEditDialogsRef = ref(null)
const detailOpen = ref(false)
const detailTask = ref(null)

const drag = useDragDrop()

const mergedRows = computed(() => buildMergedRows(props.chats, props.tasks, props.notes, null))

const filteredRows = computed(() =>
  filterExplorerRows(mergedRows.value, {
    filterKind: filterKind.value,
    taskFilter: taskFilter.value,
    textFilter: textFilter.value,
  }),
)

const orderedRowKeys = computed(() => filteredRows.value.map((r) => r.key))

const bulk = useBulkSelection({
  getOrderedIds: () => orderedRowKeys.value,
})

function openChat(c) {
  router.push({ name: 'chatThread', params: { chatId: c.id } })
}

function openTask(tk) {
  detailTask.value = tk
  detailOpen.value = true
}

function openNote(n) {
  router.push({ name: 'noteDetail', params: { noteId: n.id } })
}

function onDetailOpen(v) {
  detailOpen.value = v
  if (!v) detailTask.value = null
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

function startNoteDrag(item, e) {
  drag.onDragStart(e, { type: 'note', id: item.id })
}

async function onDropUnfiled(e) {
  const payload = drag.readPayload(e)
  drag.dragOverTargetId.value = null
  if (!payload) return
  if (payload.type === 'folder') return
  const res = await bulkMoveFolderItems({
    target_folder_id: null,
    items: [{ type: payload.type, id: payload.id }],
  })
  if (res.ok) emit('refresh')
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
    moveDialogOpen.value = false
    emit('refresh')
  }
}

async function onBulkDelete() {
  if (!window.confirm(t('folders.bulkDeleteConfirm'))) return
  for (const it of bulk.items.value) {
    if (it.type === 'chat') await deleteChat(it.id)
    else if (it.type === 'task') await deleteTask(it.id)
    else await deleteNote(it.id)
  }
  bulk.clear()
  emit('refresh')
}

function onBulkSelectAll() {
  bulk.selectAllFromKeys(orderedRowKeys.value)
}

async function onExplorerMenuAction({ action, kind, item }) {
  if (action === 'open') {
    if (kind === 'chat') openChat(item)
    else if (kind === 'task') openTask(item)
    else openNote(item)
    return
  }
  if (action === 'rename' && kind === 'chat') {
    chatEditDialogsRef.value?.openRenameDialog(item)
    return
  }
  if (action === 'edit' && kind === 'chat') {
    chatEditDialogsRef.value?.openEditDialog(item)
    return
  }
  if (action === 'move') {
    bulk.clear()
    bulk.select(kind, item.id)
    moveDialogOpen.value = true
    return
  }
  if (action === 'delete') {
    if (kind === 'chat') {
      if (!window.confirm(t('chats.deleteChatConfirm'))) return
      await deleteChat(item.id)
    } else if (kind === 'task') {
      if (!window.confirm(t('tasks.deleteConfirmInline'))) return
      await deleteTask(item.id)
    } else {
      if (!window.confirm(t('notes.deleteConfirm'))) return
      await deleteNote(item.id)
    }
    emit('refresh')
    return
  }
  if (action === 'toggleComplete' && kind === 'task') {
    const completed_at = item.completed_at ? null : new Date().toISOString()
    const res = await updateTask(item.id, { completed_at })
    if (res.ok) emit('refresh')
    return
  }
  if (action === 'toggleStar' && kind === 'task') {
    const res = await updateTask(item.id, { highlighted: !item.highlighted })
    if (res.ok) emit('refresh')
  }
}

function syncDetailTask() {
  if (!detailTask.value || !detailOpen.value) return
  const u = props.tasks.find((x) => Number(x.id) === Number(detailTask.value.id))
  if (u) detailTask.value = u
  else {
    detailTask.value = null
    detailOpen.value = false
  }
}

watch(
  () => props.tasks,
  () => syncDetailTask(),
  { deep: true },
)

watch(
  () => props.focusKind,
  (k) => {
    const kind = folderFocusQueryToFilterKind(k)
    if (kind) {
      filterKind.value = kind
    } else if (!k) {
      filterKind.value = 'all'
    }
  },
  { immediate: true },
)
</script>

<template>
  <section
    v-if="mergedRows.length > 0"
    class="folder-unfiled"
    @dragend="drag.onDragEnd"
  >
    <header class="folder-unfiled__header">
      <h2 class="folder-unfiled__title">{{ t('folders.explorer.unfiledTitle') }}</h2>
      <p class="folder-unfiled__hint">{{ t('folders.explorer.unfiledHint') }}</p>
    </header>

    <div class="folder-unfiled__toolbar">
      <div class="folder-unfiled__tabs" role="tablist">
        <button
          type="button"
          role="tab"
          class="folder-unfiled__tab"
          :class="{ 'is-active': filterKind === 'all' }"
          @click="filterKind = 'all'"
        >
          {{ t('folders.filterAll') }}
        </button>
        <button
          type="button"
          role="tab"
          class="folder-unfiled__tab"
          :class="{ 'is-active': filterKind === 'chat' }"
          @click="filterKind = 'chat'"
        >
          {{ t('folders.filterChats') }}
        </button>
        <button
          type="button"
          role="tab"
          class="folder-unfiled__tab"
          :class="{ 'is-active': filterKind === 'task' }"
          @click="filterKind = 'task'"
        >
          {{ t('folders.filterTasks') }}
        </button>
        <button
          type="button"
          role="tab"
          class="folder-unfiled__tab"
          :class="{ 'is-active': filterKind === 'note' }"
          @click="filterKind = 'note'"
        >
          {{ t('folders.filterNotes') }}
        </button>
      </div>
      <div class="folder-unfiled__toolbarRight">
        <div v-if="filterKind === 'task' || filterKind === 'all'" class="folder-unfiled__subTabs">
          <button type="button" class="folder-unfiled__subTab" :class="{ 'is-active': taskFilter === 'all' }" @click="taskFilter = 'all'">{{ t('folders.taskFilterAll') }}</button>
          <button type="button" class="folder-unfiled__subTab" :class="{ 'is-active': taskFilter === 'open' }" @click="taskFilter = 'open'">{{ t('folders.taskFilterOpen') }}</button>
          <button type="button" class="folder-unfiled__subTab" :class="{ 'is-active': taskFilter === 'done' }" @click="taskFilter = 'done'">{{ t('folders.taskFilterDone') }}</button>
        </div>
        <input
          v-model="textFilter"
          type="search"
          class="folder-unfiled__search"
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
      class="folder-unfiled__drop"
      :class="{ 'is-drop-over': drag.dragOverTargetId === 'unfiled' }"
      @dragover="drag.onDragOverFolder($event, 'unfiled')"
      @dragleave="drag.onDragLeaveFolder($event, 'unfiled')"
      @drop="onDropUnfiled"
    >
      <ItemListView
        v-if="viewMode === 'list'"
        :items="filteredRows"
        show-checkboxes
        :is-selected="bulk.isSelected"
        :get-chat-unread="getChatUnread"
        @open-chat="openChat"
        @open-task="openTask"
        @open-note="openNote"
        @toggle-select="onToggleSelect"
        @drag-start-chat="startChatDrag"
        @drag-end-chat="drag.onDragEnd"
        @drag-start-task="startTaskDrag"
        @drag-end-task="drag.onDragEnd"
        @drag-start-note="startNoteDrag"
        @drag-end-note="drag.onDragEnd"
        @menu-action="onExplorerMenuAction"
      />
      <ItemGridView
        v-else
        :items="filteredRows"
        show-checkboxes
        :is-selected="bulk.isSelected"
        :get-chat-unread="getChatUnread"
        @open-chat="openChat"
        @open-task="openTask"
        @open-note="openNote"
        @toggle-select="onToggleSelect"
        @drag-start-chat="startChatDrag"
        @drag-end-chat="drag.onDragEnd"
        @drag-start-task="startTaskDrag"
        @drag-end-task="drag.onDragEnd"
        @drag-start-note="startNoteDrag"
        @drag-end-note="drag.onDragEnd"
        @menu-action="onExplorerMenuAction"
      />
      <p v-if="!filteredRows.length" class="folder-unfiled__empty">{{ t('folders.explorer.unfiledFilteredEmpty') }}</p>
    </div>

    <FolderMoveDialog
      v-model:open="moveDialogOpen"
      :folders="folders"
      :exclude-folder-id="null"
      @confirm="onMoveConfirm"
    />

    <ChatEditDialogs ref="chatEditDialogsRef" @saved="emit('refresh')" />

    <TaskDetailSidebar
      :open="detailOpen"
      :task="detailTask"
      :calendars="calendars"
      :groups="groups"
      :folders="folders"
      @update:open="onDetailOpen"
      @saved="emit('refresh')"
      @deleted="emit('refresh')"
    />
  </section>
</template>

<style scoped lang="scss">
.folder-unfiled {
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--border, #e5e7eb);
}

.folder-unfiled__header {
  margin-bottom: 1rem;
}

.folder-unfiled__title {
  margin: 0 0 0.35rem;
  font-size: 1.1rem;
  font-weight: 700;
}

.folder-unfiled__hint {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--text-muted, #6b7280);
}

.folder-unfiled__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
  align-items: flex-start;
  margin-bottom: 0.75rem;
}

.folder-unfiled__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.folder-unfiled__tab {
  border: 1px solid var(--border, #e5e7eb);
  background: var(--surface, #fff);
  padding: 0.35rem 0.65rem;
  border-radius: 0.45rem;
  font: inherit;
  font-size: 0.8rem;
  cursor: pointer;
}

.folder-unfiled__tab.is-active {
  border-color: var(--accent, #2563eb);
  background: rgba(37, 99, 235, 0.08);
  font-weight: 600;
}

.folder-unfiled__toolbarRight {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  flex: 1;
  min-width: 12rem;
}

.folder-unfiled__subTabs {
  display: flex;
  gap: 0.25rem;
}

.folder-unfiled__subTab {
  border: none;
  background: transparent;
  font: inherit;
  font-size: 0.78rem;
  padding: 0.25rem 0.45rem;
  border-radius: 0.35rem;
  cursor: pointer;
  opacity: 0.75;
}

.folder-unfiled__subTab.is-active {
  opacity: 1;
  font-weight: 600;
  background: var(--surface-2, rgba(0, 0, 0, 0.05));
}

.folder-unfiled__search {
  flex: 1;
  min-width: 10rem;
  padding: 0.4rem 0.6rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.45rem;
  font: inherit;
}

.folder-unfiled__drop {
  min-height: 2rem;
  padding: 0.35rem 0;
  border-radius: 0.5rem;
  transition: background 120ms ease, outline 120ms ease;
}

.folder-unfiled__drop.is-drop-over {
  background: rgba(37, 99, 235, 0.06);
  outline: 2px dashed var(--accent, #2563eb);
  outline-offset: 2px;
}

.folder-unfiled__empty {
  margin: 0.75rem 0 0;
  font-size: 0.875rem;
  color: var(--text-muted, #6b7280);
}
</style>
