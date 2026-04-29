<script setup>
import { computed, onMounted, reactive, ref, unref } from 'vue'
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
import { bulkMoveFolderItems, fetchChats } from '../../services/chatApi.js'
import { fetchTasks } from '../../services/contentApi.js'

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
      <div>
        <h1 class="folders-page__title">{{ t('folders.title') }}</h1>
        <p class="folders-page__intro">{{ t('folders.intro') }}</p>
      </div>
      <Button @click="createDialogRef.showModal()">{{ t('folders.createNew') }}</Button>
    </header>

    <div class="folders-page__stats" role="status">
      <span>{{ t('folders.statsFolders') }}: {{ totals.folders }} · {{ t('folders.statsChats') }}: {{ totals.chats }} · {{ t('folders.statsTasks') }}: {{ totals.tasks }}</span>
    </div>

    <FolderSearchPanel
      v-model:query="folderSearchQuery"
      v-model:filter-type="folderSearchFilterType"
      :loading="folderSearchLoading"
      :folders="folderSearchFolders"
      :chats="folderSearchChats"
      :tasks="folderSearchTasks"
      :recent-queries="folderSearchRecentQueries"
      @pick-recent="onPickRecent"
      @open-folder="onSearchOpenFolder"
      @open-chat="onSearchOpenChat"
      @open-task="onSearchOpenTask"
    />

    <div class="folders-page__toolbar">
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
        <p class="folders-page__dropHint">{{ t('folders.dragDrop.dropHereRoot') }}</p>

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
  </section>
</template>

<style scoped lang="scss">
.folders-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1rem 1.25rem 2rem;
}

.folders-page__header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.folders-page__title {
  margin: 0 0 0.35rem;
  font-size: 1.5rem;
}

.folders-page__intro {
  margin: 0;
  opacity: 0.85;
  max-width: 40rem;
}

.folders-page__stats {
  font-size: 0.9rem;
  opacity: 0.85;
  margin-bottom: 0.75rem;
}

.folders-page__toolbar {
  margin-bottom: 0.75rem;
}

.folders-page__layout {
  display: grid;
  grid-template-columns: minmax(200px, 260px) 1fr;
  gap: 1.25rem;
  align-items: start;

  @media (max-width: 900px) {
    grid-template-columns: 1fr;
  }
}

.folders-page__aside {
  border: 1px solid var(--border);
  border-radius: 0.65rem;
  padding: 0.75rem;
  background: var(--bg);
  position: sticky;
  top: 0.5rem;
}

.folders-page__asideTitle {
  margin: 0 0 0.5rem;
  font-size: 0.95rem;
}

.folders-page__main {
  border: 1px dashed transparent;
  border-radius: 0.65rem;
  padding: 0.75rem;
  min-height: 200px;

  &.is-drop-root {
    border-color: var(--accent, #2563eb);
    background: var(--selection-bg, rgba(37, 99, 235, 0.06));
  }
}

.folders-page__dropHint {
  font-size: 0.8rem;
  opacity: 0.7;
  margin: 0 0 0.75rem;
}

.folders-page__cards {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;

  &--grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  }
}

.folders-page__empty {
  opacity: 0.8;
  margin: 1rem 0 0;
}

.folders-page__hint {
  font-size: 0.9rem;
  opacity: 0.85;
}

.folders-page__dialog {
  border: none;
  padding: 0;
  max-width: 28rem;
  width: calc(100% - 2rem);
  background: transparent;

  &::backdrop {
    background: rgba(0, 0, 0, 0.45);
  }
}

.folders-page__dialogPanel {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 0.65rem;
  padding: 1.25rem;
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
</style>
