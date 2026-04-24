<template>
  <section class="page page--chats">
    <header class="page__toolbar">
      <Title tag="h1" class="page__toolbarTitle">{{ t('chats.title') }}</Title>
      <details ref="addMenuEl" class="chats-add">
        <summary class="chats-add__summary btn btn--primary btn--md">
          <span class="chats-add__summaryInner">
            <Icon class="chats-add__glyph" name="plus" aria-hidden="true" />
            {{ t('chats.add') }}
            <Icon class="chats-add__glyph chats-add__chev" name="chevron-down" aria-hidden="true" />
          </span>
        </summary>
        <div class="chats-add__menu" role="menu">
          <button
            type="button"
            class="chats-add__item"
            role="menuitem"
            @click="addConversation"
          >
            <Icon class="chats-add__glyph" name="comments" aria-hidden="true" />
            {{ t('chats.addConversation') }}
          </button>
          <button
            type="button"
            class="chats-add__item"
            role="menuitem"
            @click="addList"
          >
            <Icon class="chats-add__glyph" name="list" aria-hidden="true" />
            {{ t('chats.addList') }}
          </button>
          <button
            type="button"
            class="chats-add__item"
            role="menuitem"
            @click="addNote"
          >
            <Icon class="chats-add__glyph" name="note-sticky" aria-hidden="true" />
            {{ t('chats.addNote') }}
          </button>
          <button
            type="button"
            class="chats-add__item"
            role="menuitem"
            @click="addFolder"
          >
            <Icon class="chats-add__glyph" name="folder" aria-hidden="true" />
            {{ t('chats.addFolder') }}
          </button>
        </div>
      </details>
    </header>

    <p class="page__muted">{{ t('chats.intro') }}</p>

    <div v-if="totalCount === 0" class="page__muted chats-empty">{{ t('chats.emptyAll') }}</div>

    <div v-else class="chats-browser" :aria-label="t('chats.title')">
      <section
        v-for="section in sections"
        :key="section.key"
        class="chats-folder"
      >
        <div class="chats-folder__header">
          <Icon class="chats-folder__folderIcon" name="folder" aria-hidden="true" />
          <template v-if="section.key === 'unfiled'">
            <span class="chats-folder__name">{{ section.label }}</span>
          </template>
          <template v-else>
            <input
              v-if="editingFolderId === section.key"
              v-model="editFolderNameDraft"
              type="text"
              class="chats-folder__nameInput"
              :data-folder-rename-input="section.key"
              :aria-label="t('chats.renameFolder')"
              @blur="commitFolderRename(section.key)"
              @keydown.enter.prevent="commitFolderRename(section.key)"
              @keydown.escape.prevent="cancelFolderRename"
            />
            <span
              v-else
              class="chats-folder__name chats-folder__name--editable"
              role="button"
              tabindex="0"
              @click="startFolderRename(section.key)"
              @keydown.enter.prevent="startFolderRename(section.key)"
              @keydown.space.prevent="startFolderRename(section.key)"
            >{{ section.label }}</span>
          </template>
          <span class="chats-folder__badge">{{ section.items.length }}</span>
        </div>
        <div class="chats-folder__pane">
          <p v-if="section.items.length === 0" class="page__muted chats-folder__empty">
            {{ t('chats.emptyFolder') }}
          </p>
          <ul v-else class="chats-folder__files" role="list">
            <li
              v-for="item in section.items"
              :key="item.id"
              class="chats-file"
            >
              <div class="chats-file__row">
                <Icon
                  class="chats-file__icon"
                  :name="fileIcon(item.kind)"
                  aria-hidden="true"
                />
                <div class="chats-file__main">
                  <input
                    v-if="editingId === item.id"
                    :data-rename-input="item.id"
                    v-model="editTitleDraft"
                    type="text"
                    class="chats-file__renameInput"
                    :aria-label="t('chats.rename')"
                    @blur="commitRename(item)"
                    @keydown.enter.prevent="commitRename(item)"
                    @keydown.escape.prevent="cancelRename"
                  />
                  <span v-else class="chats-file__title">{{ item.title }}</span>
                  <span
                    v-if="item.kind === 'conversation'"
                    class="chats-file__meta"
                  >
                    {{
                      item.peerKind === 'agent'
                        ? t('chats.peer.agent')
                        : t('chats.peer.contact')
                    }}
                  </span>
                  <span v-else class="chats-file__meta">{{ kindLabel(item.kind) }}</span>
                </div>
                <details
                  class="chats-kebab"
                  :ref="(el) => setKebabRef(item.id, el)"
                >
                  <summary
                    class="chats-kebab__trigger"
                    :aria-label="t('chats.actionsMenu')"
                  >
                    <Icon class="chats-kebab__glyph" name="ellipsis-vertical" aria-hidden="true" />
                  </summary>
                  <div
                    class="chats-kebab__menu"
                    role="menu"
                    @click.stop
                  >
                    <button
                      type="button"
                      class="chats-kebab__item"
                      role="menuitem"
                      @click="onRenameClick(item)"
                    >
                      <Icon class="chats-kebab__glyph" name="pen" aria-hidden="true" />
                      {{ t('chats.rename') }}
                    </button>
                    <button
                      type="button"
                      class="chats-kebab__item"
                      role="menuitem"
                      @click="openMoveModal(item)"
                    >
                      <Icon class="chats-kebab__glyph" name="arrow-right-arrow-left" aria-hidden="true" />
                      {{ t('chats.moveToFolder') }}
                    </button>
                    <button
                      type="button"
                      class="chats-kebab__item chats-kebab__item--danger"
                      role="menuitem"
                      @click="onDeleteClick(item.id)"
                    >
                      <Icon class="chats-kebab__glyph" name="trash" aria-hidden="true" />
                      {{ t('chats.delete') }}
                    </button>
                  </div>
                </details>
              </div>
            </li>
          </ul>
        </div>
      </section>
    </div>

    <dialog
      ref="moveDialogRef"
      class="chats-move-dialog"
      aria-labelledby="chats-move-dialog-title"
      @click="onMoveDialogBackdrop"
      @close="resetMoveModal"
    >
      <div class="chats-move-dialog__panel" @click.stop>
        <h2 id="chats-move-dialog-title" class="chats-move-dialog__title">
          {{ t('chats.moveModalTitle') }}
        </h2>
        <p class="chats-move-dialog__hint">{{ t('chats.moveDestinationHint') }}</p>

        <nav class="chats-move-dialog__crumb" :aria-label="t('chats.moveHome')">
          <button
            v-if="moveViewFolderId !== null"
            type="button"
            class="chats-move-dialog__back btn btn--secondary btn--sm"
            @click="goBackMove"
          >
            <Icon class="chats-move-dialog__glyph" name="arrow-left" aria-hidden="true" />
            {{ t('chats.moveBack') }}
          </button>
          <span class="chats-move-dialog__path">{{ moveBreadcrumb }}</span>
        </nav>

        <div class="chats-move-dialog__list" role="navigation">
          <template v-if="moveViewFolderId === null">
            <button
              type="button"
              class="chats-move-dialog__row"
              :class="{ 'is-selected': pendingFolderId === null }"
              @click="selectMoveUnfiled"
            >
              <Icon class="chats-move-dialog__rowIcon" name="folder-open" aria-hidden="true" />
              <span class="chats-move-dialog__rowLabel">{{ t('chats.moveSelectUnfiled') }}</span>
            </button>
            <button
              v-for="f in rootFoldersForMove"
              :key="f.id"
              type="button"
              class="chats-move-dialog__row chats-move-dialog__row--folder"
              :class="{ 'is-selected': pendingFolderId === f.id }"
              @click="navigateIntoFolder(f.id)"
            >
              <Icon class="chats-move-dialog__rowIcon" name="folder" aria-hidden="true" />
              <span class="chats-move-dialog__rowLabel">{{ f.name }}</span>
              <span class="chats-move-dialog__open">{{ t('chats.moveOpenFolder') }}</span>
              <Icon class="chats-move-dialog__chev" name="chevron-right" aria-hidden="true" />
            </button>
          </template>
          <template v-else>
            <p v-if="childFoldersForMove.length === 0" class="chats-move-dialog__empty">
              {{ t('chats.moveInsideEmpty') }}
            </p>
            <button
              v-for="f in childFoldersForMove"
              :key="f.id"
              type="button"
              class="chats-move-dialog__row chats-move-dialog__row--folder"
              :class="{ 'is-selected': pendingFolderId === f.id }"
              @click="navigateIntoFolder(f.id)"
            >
              <Icon class="chats-move-dialog__rowIcon" name="folder" aria-hidden="true" />
              <span class="chats-move-dialog__rowLabel">{{ f.name }}</span>
              <span class="chats-move-dialog__open">{{ t('chats.moveOpenFolder') }}</span>
              <Icon class="chats-move-dialog__chev" name="chevron-right" aria-hidden="true" />
            </button>
          </template>
        </div>

        <div class="chats-move-dialog__actions">
          <button type="button" class="btn btn--secondary btn--md" @click="cancelMoveModal">
            {{ t('chats.moveCancel') }}
          </button>
          <button type="button" class="btn btn--primary btn--md" @click="confirmMoveModal">
            {{ t('chats.moveSave') }}
          </button>
        </div>
      </div>
    </dialog>
  </section>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue'
import Icon from '../../atoms/Icon.vue'
import Title from '../../atoms/Title.vue'
import { t } from '../../i18n/i18n'

function uid() {
  return typeof crypto !== 'undefined' && crypto.randomUUID
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(16).slice(2)}`
}

const addMenuEl = ref(null)

const folders = ref([])
const chats = ref([])

let convN = 0
let listN = 0
let noteN = 0
let folderN = 0
let peerFlip = false

const editingId = ref(null)
const editTitleDraft = ref('')

const editingFolderId = ref(null)
const editFolderNameDraft = ref('')
const editFolderNameOriginal = ref('')

/** @type {Record<string, HTMLDetailsElement | null>} */
const kebabRefs = {}

function setKebabRef(id, el) {
  if (el) kebabRefs[id] = el
  else delete kebabRefs[id]
}

function closeKebabFor(id) {
  const el = kebabRefs[id]
  if (el) el.open = false
}

const totalCount = computed(() => chats.value.length)

const sections = computed(() => {
  const out = []
  const unfiled = chats.value.filter((c) => c.folderId == null)
  out.push({
    key: 'unfiled',
    label: t('chats.unfiled'),
    items: unfiled,
  })
  for (const f of folders.value) {
    const items = chats.value.filter((c) => c.folderId === f.id)
    out.push({
      key: f.id,
      label: f.name,
      items,
    })
  }
  return out
})

const moveDialogRef = ref(null)
const moveChatId = ref(null)
const moveViewFolderId = ref(null)
const pendingFolderId = ref(null)

const rootFoldersForMove = computed(() =>
  folders.value.filter((f) => f.parentId == null || f.parentId === undefined),
)

const childFoldersForMove = computed(() => {
  const parent = moveViewFolderId.value
  if (parent == null) return []
  return folders.value.filter((f) => f.parentId === parent)
})

const moveBreadcrumb = computed(() => {
  if (!moveViewFolderId.value) return t('chats.moveHome')
  const parts = []
  let id = moveViewFolderId.value
  const seen = new Set()
  while (id && !seen.has(id)) {
    seen.add(id)
    const f = folders.value.find((x) => x.id === id)
    if (!f) break
    parts.unshift(f.name)
    id = f.parentId
  }
  return parts.length ? `${t('chats.moveHome')} / ${parts.join(' / ')}` : t('chats.moveHome')
})

function openMoveModal(item) {
  closeKebabFor(item.id)
  moveChatId.value = item.id
  moveViewFolderId.value = null
  pendingFolderId.value = item.folderId ?? null
  nextTick(() => moveDialogRef.value?.showModal())
}

function navigateIntoFolder(id) {
  moveViewFolderId.value = id
  pendingFolderId.value = id
}

function selectMoveUnfiled() {
  pendingFolderId.value = null
}

function goBackMove() {
  const id = moveViewFolderId.value
  if (id == null) return
  const cur = folders.value.find((f) => f.id === id)
  if (!cur) {
    moveViewFolderId.value = null
    return
  }
  if (cur.parentId) {
    moveViewFolderId.value = cur.parentId
    pendingFolderId.value = cur.parentId
  } else {
    moveViewFolderId.value = null
    pendingFolderId.value = cur.id
  }
}

function confirmMoveModal() {
  const chat = chats.value.find((c) => c.id === moveChatId.value)
  if (chat) chat.folderId = pendingFolderId.value
  moveDialogRef.value?.close()
}

function cancelMoveModal() {
  moveDialogRef.value?.close()
}

function resetMoveModal() {
  moveChatId.value = null
  moveViewFolderId.value = null
  pendingFolderId.value = null
}

function onMoveDialogBackdrop(e) {
  if (e.target === moveDialogRef.value) {
    cancelMoveModal()
  }
}

function closeAddMenu() {
  const el = addMenuEl.value
  if (el) el.open = false
}

function addConversation(e) {
  e?.preventDefault()
  convN += 1
  peerFlip = !peerFlip
  chats.value.push({
    id: uid(),
    kind: 'conversation',
    title: `${t('chats.defaultConversation')} ${convN}`,
    folderId: null,
    peerKind: peerFlip ? 'agent' : 'contact',
  })
  closeAddMenu()
}

function addList(e) {
  e?.preventDefault()
  listN += 1
  chats.value.push({
    id: uid(),
    kind: 'list',
    title: `${t('chats.defaultList')} ${listN}`,
    folderId: null,
  })
  closeAddMenu()
}

function addNote(e) {
  e?.preventDefault()
  noteN += 1
  chats.value.push({
    id: uid(),
    kind: 'note',
    title: `${t('chats.defaultNote')} ${noteN}`,
    folderId: null,
  })
  closeAddMenu()
}

function addFolder(e) {
  e?.preventDefault()
  folderN += 1
  const finalName = `${t('chats.folderDefault')} ${folderN}`
  folders.value.push({ id: uid(), name: finalName, parentId: null })
  closeAddMenu()
}

function removeChat(id) {
  chats.value = chats.value.filter((c) => c.id !== id)
  if (editingId.value === id) {
    editingId.value = null
  }
}

function startFolderRename(folderId) {
  const f = folders.value.find((x) => x.id === folderId)
  if (!f) return
  editingFolderId.value = folderId
  editFolderNameDraft.value = f.name
  editFolderNameOriginal.value = f.name
  nextTick(() => {
    const input = document.querySelector(`[data-folder-rename-input="${folderId}"]`)
    input?.focus()
    input?.select()
  })
}

function commitFolderRename(folderId) {
  if (editingFolderId.value !== folderId) return
  const f = folders.value.find((x) => x.id === folderId)
  if (!f) {
    editingFolderId.value = null
    return
  }
  const next = editFolderNameDraft.value.trim()
  f.name = next || editFolderNameOriginal.value
  editingFolderId.value = null
}

function cancelFolderRename() {
  editingFolderId.value = null
}

async function onRenameClick(item) {
  closeKebabFor(item.id)
  editingId.value = item.id
  editTitleDraft.value = item.title
  await nextTick()
  const input = document.querySelector(`[data-rename-input="${item.id}"]`)
  input?.focus()
  input?.select()
}

function commitRename(item) {
  if (editingId.value !== item.id) return
  const next = editTitleDraft.value.trim()
  if (next) item.title = next
  editingId.value = null
}

function cancelRename() {
  editingId.value = null
}

function onDeleteClick(id) {
  closeKebabFor(id)
  removeChat(id)
}

function fileIcon(kind) {
  if (kind === 'conversation') return 'file-lines'
  if (kind === 'list') return 'list'
  return 'note-sticky'
}

function kindLabel(kind) {
  if (kind === 'list') return t('chats.kind.list')
  if (kind === 'note') return t('chats.kind.note')
  return t('chats.kind.conversation')
}

function seed() {
  convN = 1
  noteN = 1
  peerFlip = false
  chats.value = [
    {
      id: uid(),
      kind: 'conversation',
      title: `${t('chats.defaultConversation')} ${convN}`,
      folderId: null,
      peerKind: 'contact',
    },
    {
      id: uid(),
      kind: 'note',
      title: `${t('chats.defaultNote')} ${noteN}`,
      folderId: null,
    },
  ]
}

seed()
</script>

<style lang="scss" scoped>
.page--chats {
  padding: 2rem;
}

.page__toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 0.5rem;
}

.page__toolbarTitle :deep(.title) {
  margin-bottom: 0;
}

.page__muted {
  margin-top: 0.5rem;
  margin-bottom: 0;
  opacity: 0.8;
}

.chats-add {
  position: relative;
  list-style: none;
}

.chats-add summary {
  list-style: none;
  cursor: pointer;
}

.chats-add summary::-webkit-details-marker {
  display: none;
}

.chats-add__summary {
  margin: 0;
}

.chats-add__summaryInner {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.chats-add__glyph {
  font-size: 1em;
  flex-shrink: 0;
}

.chats-add__chev {
  font-size: 0.7em;
  opacity: 0.85;
}

.chats-add__menu {
  position: absolute;
  right: 0;
  top: calc(100% + 0.35rem);
  z-index: 2;
  min-width: 12rem;
  padding: 0.35rem;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

html[data-theme='dark'] .chats-add__menu {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.45);
}

.chats-add__item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  margin: 0;
  padding: 0.5rem 0.65rem;
  text-align: left;
  border: none;
  border-radius: 0.35rem;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.chats-add__item:hover {
  background: var(--btn-bg-hover);
}

.chats-empty {
  margin-top: 1rem;
}

/* OS-like file browser */
.chats-browser {
  display: flex;
  flex-direction: column;
  gap: 0;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--bg);
}

.chats-folder {
  border-bottom: 1px solid var(--border);
}

.chats-folder:last-child {
  border-bottom: none;
}

.chats-folder__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: var(--btn-bg);
  border-bottom: 1px solid var(--border);
  font-size: 0.9rem;
  font-weight: 700;
}

.chats-folder__folderIcon {
  opacity: 0.9;
  color: #ca8a04;
}

html[data-theme='dark'] .chats-folder__folderIcon {
  color: #facc15;
}

.chats-folder__name {
  flex: 1;
  min-width: 0;
  word-break: break-word;
}

.chats-folder__name--editable {
  cursor: pointer;
  border-radius: 0.25rem;
  outline-offset: 2px;
}

.chats-folder__name--editable:hover {
  text-decoration: underline;
  text-underline-offset: 0.12em;
}

.chats-folder__name--editable:focus-visible {
  outline: 2px solid var(--link);
}

.chats-folder__nameInput {
  flex: 1;
  min-width: 0;
  font: inherit;
  font-weight: 700;
  font-size: 0.9rem;
  letter-spacing: inherit;
}

.chats-folder__badge {
  font-size: 0.75rem;
  font-weight: 600;
  opacity: 0.75;
  padding: 0.1rem 0.45rem;
  border-radius: 0.25rem;
  background: var(--bg);
  border: 1px solid var(--border);
}

.chats-folder__pane {
  min-height: 2.5rem;
}

.chats-folder__empty {
  margin: 0;
  padding: 0.65rem 0.75rem 0.65rem 2.25rem;
  font-size: 0.875rem;
}

.chats-folder__files {
  list-style: none;
  margin: 0;
  padding: 0.35rem 0;
  border-left: 2px solid var(--border);
  margin-left: 1rem;
}

.chats-file {
  margin: 0;
}

.chats-file__row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 2.5rem;
  padding: 0.35rem 0.65rem 0.35rem 0.5rem;
  margin-left: 0.35rem;
  border-radius: 0.35rem;
}

.chats-file__row:hover {
  background: var(--btn-bg-hover);
}

.chats-file__icon {
  flex-shrink: 0;
  width: 1.1rem;
  height: 1.1rem;
  text-align: center;
  opacity: 0.88;
  font-size: 0.95rem;
}

.chats-file__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}

.chats-file__title {
  font-weight: 500;
  font-size: 0.9375rem;
  word-break: break-word;
}

.chats-file__renameInput {
  width: 100%;
  max-width: 20rem;
  padding: 0.35rem 0.5rem;
  font-size: 0.9375rem;
}

.chats-file__meta {
  font-size: 0.8rem;
  opacity: 0.72;
}

.chats-kebab {
  position: relative;
  flex-shrink: 0;
  list-style: none;
}

.chats-kebab__trigger {
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

.chats-kebab__trigger::-webkit-details-marker {
  display: none;
}

.chats-kebab__trigger:hover {
  background: var(--btn-bg);
}

.chats-kebab__glyph {
  font-size: 0.95rem;
}

.chats-kebab__menu {
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

html[data-theme='dark'] .chats-kebab__menu {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.45);
}

.chats-kebab__item {
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

.chats-kebab__item:hover {
  background: var(--btn-bg-hover);
}

.chats-kebab__item--danger:hover {
  background: rgba(220, 38, 38, 0.12);
  color: #dc2626;
}

.chats-move-dialog {
  margin: auto;
  padding: 0;
  max-width: min(26rem, calc(100vw - 2rem));
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  background: var(--bg);
  color: var(--text);
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
}

.chats-move-dialog::backdrop {
  background: rgba(15, 23, 42, 0.45);
}

html[data-theme='dark'] .chats-move-dialog::backdrop {
  background: rgba(0, 0, 0, 0.55);
}

.chats-move-dialog__panel {
  padding: 1.25rem 1.25rem 1rem;
}

.chats-move-dialog__title {
  margin: 0 0 0.35rem;
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.chats-move-dialog__hint {
  margin: 0 0 1rem;
  font-size: 0.875rem;
  opacity: 0.8;
}

.chats-move-dialog__crumb {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.chats-move-dialog__back {
  margin: 0;
}

.chats-move-dialog__glyph {
  font-size: 1rem;
  margin-inline-end: 0.35rem;
}

.chats-move-dialog__rowIcon {
  font-size: 1.05rem;
  flex-shrink: 0;
}

.chats-move-dialog__path {
  font-size: 0.8rem;
  font-weight: 600;
  opacity: 0.85;
  word-break: break-word;
}

.chats-move-dialog__list {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 1.25rem;
  max-height: min(50vh, 20rem);
  overflow-y: auto;
}

.chats-move-dialog__row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  margin: 0;
  padding: 0.55rem 0.65rem;
  text-align: left;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--bg);
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.chats-move-dialog__row:hover {
  background: var(--btn-bg-hover);
}

.chats-move-dialog__row.is-selected {
  border-color: #2563eb;
  color: #ffffff;
  background: #2563eb;
}

.chats-move-dialog__row.is-selected .chats-move-dialog__open,
.chats-move-dialog__row.is-selected .chats-move-dialog__chev {
  opacity: 0.95;
}

.chats-move-dialog__row--folder .chats-move-dialog__rowLabel {
  flex: 1;
  min-width: 0;
  font-weight: 600;
}

.chats-move-dialog__open {
  font-size: 0.75rem;
  font-weight: 600;
  opacity: 0.75;
}

.chats-move-dialog__chev {
  font-size: 0.7rem;
  opacity: 0.65;
}

.chats-move-dialog__empty {
  margin: 0;
  padding: 0.65rem 0.5rem;
  font-size: 0.875rem;
  opacity: 0.85;
}

.chats-move-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
  padding-top: 0.25rem;
  border-top: 1px solid var(--border);
}

.visually-hidden {
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

/* Mirror Button atom tokens for summary (scoped duplicate of .btn--primary) */
.btn {
  font-family: inherit;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn--md {
  padding: 0.6rem 1rem;
  font-size: 1rem;
}

.btn--sm {
  padding: 0.45rem 0.75rem;
  font-size: 0.875rem;
}

.btn--primary {
  color: #fff;
  background: #2563eb;
  border-color: #2563eb;
}

.btn--primary:hover:not(:disabled) {
  background: #1d4ed8;
  border-color: #1d4ed8;
}

.btn--secondary {
  background: var(--btn-bg);
  color: var(--btn-text);
  border: 1px solid var(--border);
}

.btn--secondary:hover:not(:disabled) {
  background: var(--btn-bg-hover);
}
</style>
