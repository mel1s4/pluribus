<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import BottomSheet from '../../components/Tasks/BottomSheet.vue'
import TaskDetailSidebar from '../../components/Tasks/TaskDetailSidebar.vue'
import TaskListGroup from '../../components/Tasks/TaskListGroup.vue'
import TaskQuickAdd from '../../components/Tasks/TaskQuickAdd.vue'
import TaskToolbar from '../../components/Tasks/TaskToolbar.vue'
import { t } from '../../i18n/i18n'
import {
  createTask,
  deleteTask,
  fetchCalendars,
  fetchGroups,
  fetchTasks,
  updateTask,
} from '../../services/contentApi'
import { createFolder, fetchFolders } from '../../services/chatApi'

const tasks = ref([])
const folders = ref([])
const calendars = ref([])
const groups = ref([])
const loading = ref(false)
const error = ref('')

const searchQuery = ref('')
/** @type {import('vue').Ref<'all'|'open'|'done'>} */
const statusFilter = ref('all')
/** '' = all folders, '0' = unfiled only, else folder id */
const folderScope = ref('')

const detailOpen = ref(false)
const detailTask = ref(null)

const isMobile = ref(false)
const filtersOpen = ref(false)
const folderCreatingInline = ref(false)
const newFolderName = ref('')
const newFolderSaving = ref(false)
const newFolderError = ref('')
const inlineFolderInputRef = ref(null)
const taskQuickAddRef = ref(null)
/** When set, quick-add folder defaults to this instead of the toolbar folder filter */
const quickAddFolderOverride = ref(undefined)
/** undefined = follow folder filter; number = subfolder of that folder; 'top' = no parent */
const inlineFolderExplicitParent = ref(undefined)
/** @type {import('vue').Ref<number[]>} */
const exitingTaskIds = ref([])

function checkMobile() {
  isMobile.value = globalThis.matchMedia?.('(max-width: 767px)')?.matches ?? false
}

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

function sortTasks(list) {
  return [...list].sort((a, b) => {
    const ac = Boolean(a.completed_at)
    const bc = Boolean(b.completed_at)
    if (ac !== bc) return ac ? 1 : -1
    const as = a.start_at ? new Date(a.start_at).getTime() : Number.POSITIVE_INFINITY
    const bs = b.start_at ? new Date(b.start_at).getTime() : Number.POSITIVE_INFINITY
    if (as !== bs) return as - bs
    const ap = a.position ?? 0
    const bp = b.position ?? 0
    if (ap !== bp) return ap - bp
    return Number(b.id) - Number(a.id)
  })
}

function taskMatchesSearch(task, q) {
  const s = q.trim().toLowerCase()
  if (!s) return true
  if ((task.title || '').toLowerCase().includes(s)) return true
  if ((task.description || '').toLowerCase().includes(s)) return true
  const tags = Array.isArray(task.tags) ? task.tags : []
  if (tags.some((tag) => String(tag).toLowerCase().includes(s))) return true
  return false
}

async function load() {
  loading.value = true
  error.value = ''
  const params = {}
  if (statusFilter.value === 'open') params.only_open = true
  const scope = folderScope.value
  if (scope && scope !== '0') {
    params.folder_id = Number(scope)
  }

  const [tasksRes, foldersRes, calsRes, groupsRes] = await Promise.all([
    fetchTasks(params),
    fetchFolders(),
    fetchCalendars(),
    fetchGroups(),
  ])
  loading.value = false

  if (!tasksRes.ok) {
    error.value = `HTTP ${tasksRes.status}`
    return
  }
  folders.value = unwrapList(foldersRes.data)
  calendars.value = unwrapList(calsRes.data)
  groups.value = unwrapList(groupsRes.data)

  let list = unwrapList(tasksRes.data)
  if (scope === '0') {
    list = list.filter((tk) => !tk.folder_id)
  }
  if (statusFilter.value === 'done') {
    list = list.filter((tk) => tk.completed_at)
  }
  tasks.value = list

  if (detailTask.value) {
    const u = list.find((x) => Number(x.id) === Number(detailTask.value.id))
    if (!u) {
      detailTask.value = null
      detailOpen.value = false
    } else if (!detailOpen.value) {
      detailTask.value = u
    }
  }
}

watch([statusFilter, folderScope], () => {
  load()
})

watch(isMobile, (m) => {
  if (!m) filtersOpen.value = false
})

const filteredSortedTasks = computed(() => {
  const q = searchQuery.value
  const matched = tasks.value.filter((tk) => taskMatchesSearch(tk, q))
  return sortTasks(matched)
})

const groupedSections = computed(() => {
  const scope = folderScope.value
  const list = filteredSortedTasks.value

  if (scope && scope !== '') {
    if (list.length === 0) return []
    let folder = { id: scope === '0' ? 0 : Number(scope), name: t('tasks.unfiled') }
    if (scope !== '0') {
      const f = folders.value.find((x) => Number(x.id) === Number(scope))
      if (f) folder = f
      else folder = { id: Number(scope), name: t('tasks.unknownFolder') }
    }
    const folderIdNum = scope === '0' ? 0 : Number(scope)
    return [
      {
        key: `scope:${scope}`,
        label: folder.name || t('tasks.untitled'),
        tasks: list,
        folderId: folderIdNum,
      },
    ]
  }

  const bucket = new Map()
  for (const f of folders.value) {
    bucket.set(Number(f.id), { label: f.name || t('tasks.untitled'), tasks: [] })
  }
  bucket.set(0, { label: t('tasks.unfiled'), tasks: [] })

  for (const tk of list) {
    const id = Number(tk.folder_id || 0)
    const row = bucket.get(id) || bucket.get(0)
    row.tasks.push(tk)
  }

  return Array.from(bucket.entries())
    .filter(([, row]) => row.tasks.length > 0)
    .map(([id, row]) => ({
      key: `f:${id}`,
      label: row.label,
      tasks: row.tasks,
      folderId: Number(id),
    }))
})

const quickAddDefaultFolder = computed(() => {
  const s = folderScope.value
  if (s && s !== '0') return s
  return ''
})

const quickAddDefaultFolderMerged = computed(() =>
  quickAddFolderOverride.value !== undefined ? quickAddFolderOverride.value : quickAddDefaultFolder.value,
)

const inlineNewFolderParentHint = computed(() => {
  const e = inlineFolderExplicitParent.value
  if (e === 'top') return t('tasks.inlineFolderTop')
  if (typeof e === 'number') {
    const f = folders.value.find((x) => Number(x.id) === e)
    const name = f?.name || t('tasks.unknownFolder')
    return t('tasks.inlineFolderUnder').replace('{name}', name)
  }
  const s = folderScope.value
  if (s && s !== '' && s !== '0') {
    const f = folders.value.find((x) => Number(x.id) === Number(s))
    const name = f?.name || t('tasks.unknownFolder')
    return t('tasks.inlineFolderUnder').replace('{name}', name)
  }
  return t('tasks.inlineFolderTop')
})

function parentIdForNewFolder() {
  const e = inlineFolderExplicitParent.value
  if (e === 'top') return undefined
  if (typeof e === 'number') return e
  const s = folderScope.value
  if (s && s !== '' && s !== '0') return Number(s)
  return undefined
}

/** @param {undefined|number|'top'} [explicitParent] undefined = use folder filter; number > 0 = subfolder; 'top' or 0 context = top-level */
function startInlineFolderCreate(explicitParent) {
  if (explicitParent === 'top' || explicitParent === 0) {
    inlineFolderExplicitParent.value = 'top'
  } else if (typeof explicitParent === 'number' && explicitParent > 0) {
    inlineFolderExplicitParent.value = explicitParent
  } else {
    inlineFolderExplicitParent.value = undefined
  }
  folderCreatingInline.value = true
  newFolderName.value = ''
  newFolderError.value = ''
  nextTick(() => inlineFolderInputRef.value?.focus())
}

function cancelInlineFolderCreate() {
  inlineFolderExplicitParent.value = undefined
  folderCreatingInline.value = false
  newFolderName.value = ''
  newFolderError.value = ''
}

async function submitInlineFolderCreate() {
  const n = newFolderName.value.trim()
  if (!n) {
    cancelInlineFolderCreate()
    return
  }
  newFolderSaving.value = true
  newFolderError.value = ''
  const payload = { name: n }
  const pid = parentIdForNewFolder()
  if (pid != null) payload.parent_id = pid
  const res = await createFolder(payload)
  newFolderSaving.value = false
  if (!res.ok) {
    newFolderError.value = t('tasks.createFolderError').replace('{status}', String(res.status))
    return
  }
  const folder = res.data?.folder
  cancelInlineFolderCreate()
  await load()
  if (folder?.id != null) folderScope.value = String(folder.id)
}

function openDetail(task) {
  detailTask.value = task
  detailOpen.value = true
}

function onDetailOpen(v) {
  detailOpen.value = v
  if (!v) detailTask.value = null
}

function onGroupHeaderAddTask(folderId) {
  const fid = folderId === 0 ? '' : String(folderId)
  quickAddFolderOverride.value = fid
  taskQuickAddRef.value?.presetAndFocus(fid)
}

function onGroupHeaderAddFolder(folderId) {
  if (folderId > 0) startInlineFolderCreate(folderId)
  else startInlineFolderCreate('top')
}

async function onQuickSubmit(payload) {
  const res = await createTask(payload)
  if (!res.ok) {
    error.value = `HTTP ${res.status}`
    return
  }
  quickAddFolderOverride.value = undefined
  await load()
}

async function toggleComplete(task) {
  const completed_at = task.completed_at ? null : new Date().toISOString()
  const res = await updateTask(task.id, { completed_at })
  if (res.ok) await load()
}

async function toggleStar(task) {
  const res = await updateTask(task.id, { highlighted: !task.highlighted })
  if (res.ok) await load()
}

async function onInlineDelete(task) {
  if (!globalThis.confirm?.(t('tasks.deleteConfirmInline'))) return
  const id = Number(task.id)
  if (!exitingTaskIds.value.includes(id)) {
    exitingTaskIds.value = [...exitingTaskIds.value, id]
  }
  await nextTick()
  await new Promise((r) => setTimeout(r, 260))
  const res = await deleteTask(id)
  exitingTaskIds.value = exitingTaskIds.value.filter((x) => x !== id)
  if (res.ok) await load()
}

onMounted(() => {
  checkMobile()
  globalThis.addEventListener('resize', checkMobile)
  load()
})

onUnmounted(() => {
  globalThis.removeEventListener('resize', checkMobile)
  cancelInlineFolderCreate()
  quickAddFolderOverride.value = undefined
})
</script>

<template>
  <section class="tasks-page">
    <header class="tasks-page__header">
      <div class="tasks-page__titleRow">
        <PageToolbarTitle route-key="tasks" class="tasks-page__toolbarTitle">
          <Title v-if="!isMobile" tag="h1" class="tasks-page__heading">{{ t('tasks.title') }}</Title>
          <h1 v-else class="tasks-page__sr-only">{{ t('tasks.title') }}</h1>
        </PageToolbarTitle>
        <button
          v-if="isMobile"
          type="button"
          class="tasks-page__filtersBtn"
          @click="filtersOpen = true"
        >
          {{ t('tasks.filters') }}
        </button>
        <button
          type="button"
          class="tasks-page__newFolderBtn"
          :class="{ 'tasks-page__newFolderBtn--active': folderCreatingInline }"
          @click="folderCreatingInline ? cancelInlineFolderCreate() : startInlineFolderCreate()"
        >
          {{ folderCreatingInline ? t('folders.cancel') : t('folders.createNew') }}
        </button>
      </div>
      <p class="tasks-page__intro" :class="{ 'tasks-page__intro--desktop': !isMobile }">{{ t('tasks.intro') }}</p>
    </header>

    <div v-if="folderCreatingInline" class="tasks-page__inlineFolder">
      <span class="tasks-page__inlineFolderIcon" aria-hidden="true">📁</span>
      <div class="tasks-page__inlineFolderMain">
        <input
          ref="inlineFolderInputRef"
          v-model="newFolderName"
          type="text"
          class="tasks-page__inlineFolderInput"
          maxlength="255"
          :placeholder="t('tasks.folderNamePlaceholder')"
          :disabled="newFolderSaving"
          :aria-label="t('tasks.folderNamePlaceholder')"
          @keydown.enter.prevent="submitInlineFolderCreate"
          @keydown.escape.prevent="cancelInlineFolderCreate"
        />
        <p class="tasks-page__inlineFolderHint">{{ inlineNewFolderParentHint }}</p>
        <p v-if="newFolderError" class="tasks-page__inlineFolderErr">{{ newFolderError }}</p>
      </div>
      <button
        type="button"
        class="tasks-page__inlineFolderSubmit"
        :disabled="newFolderSaving || !newFolderName.trim()"
        @click="submitInlineFolderCreate"
      >
        {{ t('folders.save') }}
      </button>
    </div>

    <div v-if="!isMobile" class="tasks-page__toolbarDesktop">
      <TaskToolbar
        v-model:search-query="searchQuery"
        v-model:status-filter="statusFilter"
        v-model:folder-scope="folderScope"
        :folders="folders"
      />
    </div>

    <BottomSheet v-if="isMobile" v-model:open="filtersOpen" :title="t('tasks.filters')">
      <TaskToolbar
        hide-search
        v-model:search-query="searchQuery"
        v-model:status-filter="statusFilter"
        v-model:folder-scope="folderScope"
        :folders="folders"
      />
    </BottomSheet>

    <TaskQuickAdd
      ref="taskQuickAddRef"
      :folders="folders"
      :default-folder-id="quickAddDefaultFolderMerged"
      :is-mobile="isMobile"
      @submit="onQuickSubmit"
    />

    <div v-if="isMobile" class="tasks-page__searchMobile">
      <label class="tasks-page__searchLabel">
        <span class="tasks-page__sr">{{ t('tasks.searchLabel') }}</span>
        <input
          v-model="searchQuery"
          type="search"
          class="tasks-page__searchInput"
          :placeholder="t('tasks.searchPlaceholder')"
        />
      </label>
    </div>

    <p v-if="loading" class="tasks-page__muted">{{ t('tasks.loading') }}</p>
    <p v-if="error" class="tasks-page__err">{{ t('tasks.error').replace('{status}', error.replace('HTTP ', '')) }}</p>

    <div v-if="!loading" class="tasks-page__board">
      <p v-if="groupedSections.length === 0" class="tasks-page__muted">{{ t('tasks.empty') }}</p>
      <div v-else class="tasks-page__groups">
        <TaskListGroup
          v-for="sec in groupedSections"
          :key="sec.key"
          :label="sec.label"
          :folder-id="sec.folderId"
          :tasks="sec.tasks"
          :exiting-ids="exitingTaskIds"
          @toggle-complete="toggleComplete"
          @toggle-star="toggleStar"
          @open="openDetail"
          @delete="onInlineDelete"
          @add-task="onGroupHeaderAddTask(sec.folderId)"
          @add-folder="onGroupHeaderAddFolder(sec.folderId)"
        />
      </div>
    </div>

    <TaskDetailSidebar
      :open="detailOpen"
      :task="detailTask"
      :calendars="calendars"
      :groups="groups"
      :folders="folders"
      @update:open="onDetailOpen"
      @saved="load"
      @deleted="load"
    />

  </section>
</template>

<style scoped lang="scss">
.tasks-page {
  max-width: 56rem;
  margin: 0 auto;
  padding: 0.75rem 1rem 2.5rem;
  display: grid;
  gap: 0.35rem;
}

@media (min-width: 768px) {
  .tasks-page {
    padding: 1rem 1.25rem 2.5rem;
  }
}

.tasks-page__header {
  display: grid;
  gap: 0.35rem;
}

.tasks-page__titleRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.tasks-page__toolbarTitle {
  flex: 1;
  min-width: 0;
}

.tasks-page__filtersBtn {
  flex-shrink: 0;
  border: 1px solid var(--border, #e5e7eb);
  background: var(--surface, #fff);
  font: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  padding: 0.45rem 0.75rem;
  border-radius: 0.5rem;
  cursor: pointer;
  color: var(--text, #111827);
}

.tasks-page__newFolderBtn {
  flex-shrink: 0;
  border: 1px solid var(--border, #e5e7eb);
  background: var(--surface-2, rgba(0, 0, 0, 0.04));
  font: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  padding: 0.45rem 0.75rem;
  border-radius: 0.5rem;
  cursor: pointer;
  color: var(--text, #111827);
}

.tasks-page__newFolderBtn--active {
  border-color: var(--accent, #2563eb);
  color: var(--accent, #2563eb);
}

.tasks-page__inlineFolder {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  margin: 0.35rem 0 0.15rem;
  padding: 0.5rem 0.65rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  background: var(--surface, #fff);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.tasks-page__inlineFolderIcon {
  flex-shrink: 0;
  font-size: 1.1rem;
  line-height: 1.5;
  opacity: 0.85;
}

.tasks-page__inlineFolderMain {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 0.2rem;
}

.tasks-page__inlineFolderInput {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.35rem;
  padding: 0.35rem 0.5rem;
  font: inherit;
  font-size: 0.95rem;
  background: var(--surface, #fff);
  color: var(--text, #111827);
}

.tasks-page__inlineFolderInput:focus {
  outline: 2px solid var(--accent, #2563eb);
  outline-offset: 1px;
}

.tasks-page__inlineFolderHint {
  margin: 0;
  font-size: 0.75rem;
  opacity: 0.75;
  line-height: 1.3;
}

.tasks-page__inlineFolderErr {
  margin: 0;
  font-size: 0.8rem;
  color: #b00020;
}

.tasks-page__inlineFolderSubmit {
  flex-shrink: 0;
  align-self: center;
  border: 1px solid var(--border, #e5e7eb);
  background: var(--surface-2, rgba(0, 0, 0, 0.04));
  font: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  padding: 0.4rem 0.65rem;
  border-radius: 0.4rem;
  cursor: pointer;
  color: var(--text, #111827);
}

.tasks-page__inlineFolderSubmit:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.tasks-page__heading :deep(.title) {
  margin-bottom: 0.35rem;
}

.tasks-page__sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.tasks-page__intro {
  margin: 0;
  opacity: 0.8;
  font-size: 0.85rem;
  line-height: 1.35;
}

.tasks-page__intro--desktop {
  font-size: 0.9rem;
}

@media (max-width: 767px) {
  .tasks-page__intro:not(.tasks-page__intro--desktop) {
    display: none;
  }
}

.tasks-page__toolbarDesktop {
  border-bottom: 1px solid var(--border, #e5e7eb);
  margin-bottom: 0.25rem;
}

.tasks-page__searchMobile {
  margin: 0.25rem 0 0.5rem;
}

.tasks-page__searchLabel {
  display: block;
}

.tasks-page__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.tasks-page__searchInput {
  width: 100%;
  box-sizing: border-box;
  padding: 0.4rem 0.65rem;
  min-height: 2.5rem;
  max-height: 2.5rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.55rem;
  font: inherit;
  font-size: 1rem;
  line-height: 1.25;
  background: var(--surface, #fff);
}

.tasks-page__muted {
  margin: 0.35rem 0 0;
  opacity: 0.8;
  font-size: 0.9rem;
}

.tasks-page__err {
  color: #b00020;
  margin: 0.35rem 0 0;
  font-size: 0.9rem;
}

.tasks-page__board {
  margin-top: 0.5rem;
}

.tasks-page__groups {
  display: grid;
  gap: 0.85rem;
}
</style>
