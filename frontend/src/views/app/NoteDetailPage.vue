<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { MdEditor, MdPreview } from 'md-editor-v3'
import 'md-editor-v3/lib/style.css'
import Button from '../../atoms/Button.vue'
import { t } from '../../i18n/i18n'
import { fetchContacts } from '../../services/contactsApi.js'
import {
  acquireNoteLock,
  addNoteCollaborator,
  deleteNote,
  fetchNote,
  fetchNoteRevision,
  fetchNoteRevisions,
  getNoteEditorTabSessionId,
  releaseNoteLock,
  removeNoteCollaborator,
  renewNoteLock,
  revertNote,
  updateNote,
  updateNoteCollaborator,
} from '../../services/notesApi.js'

const route = useRoute()
const router = useRouter()

const noteId = computed(() => Number(route.params.noteId))
const note = ref(null)
const loading = ref(true)
const saveError = ref('')
const lockError = ref('')
const lockHeld = ref(false)
const editorSessionId = getNoteEditorTabSessionId()
const editorId = computed(() => `note-md-${noteId.value}`)

const title = ref('')
const description = ref('')
const contentMarkdown = ref('')
const tagsInput = ref('')

const revisionsOpen = ref(false)
const revisions = ref([])
const revNextCursor = ref(null)
const revLoading = ref(false)
const detailRevision = ref(null)
const detailOpen = ref(false)

const contacts = ref([])
const collabUserId = ref('')
const collabPermission = ref('view')

/** @type {ReturnType<typeof setInterval>|null} */
let renewTimer = null

function tagsFromInput() {
  return tagsInput.value
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean)
    .slice(0, 50)
}

function loadTagsFromNote(n) {
  tagsInput.value = Array.isArray(n.tags) ? n.tags.join(', ') : ''
}

function unwrapContacts(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload.contacts)) return payload.contacts
  if (Array.isArray(payload.data)) return payload.data
  return []
}

async function loadNote() {
  loading.value = true
  saveError.value = ''
  const res = await fetchNote(noteId.value)
  loading.value = false
  if (!res.ok || !res.data?.note) {
    note.value = null
    return
  }
  note.value = res.data.note
  title.value = note.value.title || ''
  description.value = note.value.description || ''
  contentMarkdown.value = note.value.content_markdown || ''
  loadTagsFromNote(note.value)
}

function clearRenewTimer() {
  if (renewTimer) {
    clearInterval(renewTimer)
    renewTimer = null
  }
}

async function tryAcquireLock() {
  lockError.value = ''
  if (!note.value?.can_edit) return
  const res = await acquireNoteLock(noteId.value, editorSessionId)
  if (res.status === 409) {
    lockHeld.value = false
    lockError.value = (res.data && res.data.message) || t('notes.lockConflict')
    return
  }
  if (!res.ok) {
    lockHeld.value = false
    lockError.value = t('notes.lockAcquireFailed')
    return
  }
  lockHeld.value = true
  clearRenewTimer()
  renewTimer = setInterval(() => {
    renewNoteLock(noteId.value, editorSessionId)
  }, 30000)
}

async function releaseLockFor(notePk) {
  if (!lockHeld.value) return
  await releaseNoteLock(notePk, editorSessionId)
  lockHeld.value = false
  clearRenewTimer()
}

async function saveNote() {
  saveError.value = ''
  if (!note.value?.can_edit || !lockHeld.value) return
  const res = await updateNote(noteId.value, {
    title: title.value.trim(),
    description: description.value.trim() || null,
    content_markdown: contentMarkdown.value || null,
    tags: tagsFromInput().length ? tagsFromInput() : null,
    editor_session_id: editorSessionId,
  })
  if (!res.ok) {
    saveError.value = `${t('notes.saveFailedPrefix')} ${res.status}).`
    return
  }
  if (res.data?.note) {
    note.value = res.data.note
    loadTagsFromNote(note.value)
  }
}

function unwrapResourceList(payload) {
  if (!payload) return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

async function loadRevisions(cursor = null) {
  revLoading.value = true
  const res = await fetchNoteRevisions(noteId.value, cursor)
  revLoading.value = false
  if (!res.ok) return
  const d = res.data && typeof res.data === 'object' ? res.data : {}
  const list = unwrapResourceList(d.revisions)
  if (cursor) revisions.value = revisions.value.concat(list)
  else revisions.value = list
  revNextCursor.value = d.next_cursor ?? null
}

async function openRevisionDetail(id) {
  const res = await fetchNoteRevision(noteId.value, id)
  if (!res.ok || !res.data?.revision) return
  detailRevision.value = res.data.revision
  detailOpen.value = true
}

async function onRevert(revId) {
  if (!window.confirm(t('notes.revertConfirm'))) return
  const res = await revertNote(noteId.value, revId, editorSessionId)
  if (!res.ok) return
  if (res.data?.note) {
    note.value = res.data.note
    title.value = note.value.title || ''
    description.value = note.value.description || ''
    contentMarkdown.value = note.value.content_markdown || ''
    loadTagsFromNote(note.value)
  }
  detailOpen.value = false
  revisions.value = []
  revNextCursor.value = null
  await loadRevisions(null)
}

async function onDeleteNote() {
  if (!note.value?.can_delete) return
  if (!window.confirm(t('notes.deleteConfirm'))) return
  const res = await deleteNote(noteId.value)
  if (!res.ok) return
  router.push({ name: 'folders' })
}

async function onAddCollaborator() {
  const uid = Number(collabUserId.value)
  if (!Number.isFinite(uid)) return
  const res = await addNoteCollaborator(noteId.value, { user_id: uid, permission: collabPermission.value })
  if (!res.ok) return
  await loadNote()
}

async function onRemoveCollaborator(userId) {
  const res = await removeNoteCollaborator(noteId.value, userId)
  if (!res.ok) return
  await loadNote()
}

async function onChangeCollabPermission(userId, permission) {
  const res = await updateNoteCollaborator(noteId.value, userId, { permission })
  if (!res.ok) return
  await loadNote()
}

onMounted(async () => {
  await loadNote()
  if (note.value?.can_manage_collaborators) {
    const cRes = await fetchContacts()
    if (cRes.ok) contacts.value = unwrapContacts(cRes.data)
  }
  if (note.value?.can_edit) await tryAcquireLock()
})

watch(noteId, async (newId, oldId) => {
  clearRenewTimer()
  if (lockHeld.value && oldId && Number.isFinite(Number(oldId))) {
    await releaseNoteLock(Number(oldId), editorSessionId)
    lockHeld.value = false
  }
  await loadNote()
  if (note.value?.can_manage_collaborators) {
    const cRes = await fetchContacts()
    if (cRes.ok) contacts.value = unwrapContacts(cRes.data)
  }
  if (note.value?.can_edit) await tryAcquireLock()
  revisionsOpen.value = false
  revisions.value = []
  revNextCursor.value = null
})

onBeforeUnmount(async () => {
  clearRenewTimer()
  if (lockHeld.value) await releaseNoteLock(noteId.value, editorSessionId)
})

watch(revisionsOpen, async (open) => {
  if (open && !revisions.value.length) await loadRevisions(null)
})
</script>

<template>
  <section v-if="loading" class="note-detail-page">
    <p class="note-detail-page__loading">{{ t('notes.loading') }}</p>
  </section>

  <section v-else-if="!note" class="note-detail-page note-detail-page--missing">
    <p>{{ t('notes.notFound') }}</p>
    <Button @click="router.push({ name: 'folders' })">{{ t('folders.backToBrowser') }}</Button>
  </section>

  <section v-else class="note-detail-page">
    <header class="note-detail-page__header">
      <button type="button" class="note-detail-page__back" @click="router.push({ name: 'folders' })">
        {{ t('notes.back') }}
      </button>
      <div class="note-detail-page__headerRow">
        <h1 class="note-detail-page__title">{{ t('notes.detailTitle') }}</h1>
        <div class="note-detail-page__headerActions">
          <Button v-if="note.can_delete" size="sm" variant="danger" @click="onDeleteNote">{{ t('notes.delete') }}</Button>
          <Button size="sm" variant="secondary" @click="revisionsOpen = !revisionsOpen">
            {{ t('notes.history') }}
          </Button>
        </div>
      </div>
      <p v-if="note.last_edited_by" class="note-detail-page__lastEdit">
        {{ t('notes.lastEditedPrefix') }} {{ note.last_edited_by.name || '' }}
        <span v-if="note.updated_at"> · {{ note.updated_at }}</span>
      </p>
      <p v-if="lockError" class="note-detail-page__lockErr">{{ lockError }}</p>
      <p v-if="saveError" class="note-detail-page__saveErr">{{ saveError }}</p>
    </header>

    <div class="note-detail-page__layout">
      <div class="note-detail-page__main">
        <label class="note-detail-page__field">
          <span>{{ t('notes.titleLabel') }}</span>
          <input v-model="title" class="note-detail-page__input" maxlength="255" :readonly="!note.can_edit || !lockHeld">
        </label>
        <label class="note-detail-page__field">
          <span>{{ t('notes.descriptionLabel') }}</span>
          <textarea v-model="description" class="note-detail-page__textarea" rows="2" :readonly="!note.can_edit || !lockHeld"></textarea>
        </label>
        <label class="note-detail-page__field">
          <span>{{ t('notes.tagsLabel') }}</span>
          <input v-model="tagsInput" class="note-detail-page__input" :readonly="!note.can_edit || !lockHeld" :placeholder="t('notes.tagsPlaceholder')">
        </label>

        <div class="note-detail-page__editorBlock">
          <span class="note-detail-page__fieldLabel">{{ t('notes.contentLabel') }}</span>
          <MdEditor
            v-if="note.can_edit && lockHeld"
            :id="editorId"
            v-model="contentMarkdown"
            language="en-US"
          />
          <MdPreview
            v-else
            :id="`${editorId}-pv`"
            :model-value="contentMarkdown"
            language="en-US"
          />
        </div>

        <div v-if="note.can_edit" class="note-detail-page__saveRow">
          <Button :disabled="!lockHeld" @click="saveNote">{{ t('notes.save') }}</Button>
          <span v-if="!lockHeld" class="note-detail-page__hint">{{ t('notes.readOnlyHint') }}</span>
        </div>
      </div>

      <aside v-if="revisionsOpen" class="note-detail-page__aside">
        <h2 class="note-detail-page__asideTitle">{{ t('notes.history') }}</h2>
        <p v-if="revLoading" class="note-detail-page__muted">{{ t('notes.loading') }}</p>
        <ul v-else class="note-detail-page__revList">
          <li v-for="r in revisions" :key="r.id" class="note-detail-page__revLi">
            <div class="note-detail-page__revMeta">
              <span>{{ r.created_at }}</span>
              <span v-if="r.edited_by"> · {{ r.edited_by.name }}</span>
            </div>
            <div class="note-detail-page__revTitle">{{ r.title }}</div>
            <div class="note-detail-page__revActions">
              <Button size="sm" variant="secondary" @click="openRevisionDetail(r.id)">{{ t('notes.viewRevision') }}</Button>
              <Button v-if="note.can_edit && lockHeld" size="sm" @click="onRevert(r.id)">{{ t('notes.revert') }}</Button>
            </div>
          </li>
        </ul>
        <Button v-if="revNextCursor" size="sm" variant="secondary" @click="loadRevisions(revNextCursor)">{{ t('notes.loadMore') }}</Button>
      </aside>
    </div>

    <section v-if="note.can_manage_collaborators" class="note-detail-page__collab">
      <h2 class="note-detail-page__collabTitle">{{ t('notes.collaborators') }}</h2>
      <div class="note-detail-page__collabAdd">
        <select v-model="collabUserId" class="note-detail-page__select">
          <option value="">{{ t('notes.pickContact') }}</option>
          <option v-for="c in contacts" :key="c.id" :value="String(c.id)">{{ c.name || c.email || c.id }}</option>
        </select>
        <select v-model="collabPermission" class="note-detail-page__select">
          <option value="view">{{ t('notes.permissionView') }}</option>
          <option value="edit">{{ t('notes.permissionEdit') }}</option>
        </select>
        <Button size="sm" @click="onAddCollaborator">{{ t('notes.addCollaborator') }}</Button>
      </div>
      <ul v-if="note.collaborators?.length" class="note-detail-page__collabList">
        <li v-for="row in note.collaborators" :key="row.user.id" class="note-detail-page__collabLi">
          <span>{{ row.user.name }}</span>
          <select
            class="note-detail-page__select"
            :value="row.permission"
            @change="onChangeCollabPermission(row.user.id, $event.target.value)"
          >
            <option value="view">{{ t('notes.permissionView') }}</option>
            <option value="edit">{{ t('notes.permissionEdit') }}</option>
          </select>
          <Button size="sm" variant="secondary" @click="onRemoveCollaborator(row.user.id)">{{ t('notes.remove') }}</Button>
        </li>
      </ul>
    </section>

    <div v-if="detailOpen" class="note-detail-page__overlay" role="presentation" @click="detailOpen = false">
      <div class="note-detail-page__dialogPanel note-detail-page__dialogPanel--modal" role="dialog" @click.stop>
        <h2>{{ t('notes.revisionDetail') }}</h2>
        <template v-if="detailRevision">
          <p class="note-detail-page__muted">{{ detailRevision.created_at }}</p>
          <h3>{{ detailRevision.title }}</h3>
          <MdPreview :id="`${editorId}-dlg`" :model-value="detailRevision.content_markdown || ''" language="en-US" />
        </template>
        <Button variant="secondary" @click="detailOpen = false">{{ t('folders.cancel') }}</Button>
      </div>
    </div>
  </section>
</template>

<style scoped lang="scss">
.note-detail-page {
  max-width: 960px;
  margin: 0 auto;
  padding: 1.25rem 1.5rem 3rem;
}

.note-detail-page--missing {
  text-align: center;
}

.note-detail-page__loading {
  padding: 2rem;
  text-align: center;
  opacity: 0.7;
}

.note-detail-page__back {
  border: none;
  background: transparent;
  color: var(--link, #2563eb);
  cursor: pointer;
  font: inherit;
  margin-bottom: 0.75rem;
}

.note-detail-page__headerRow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.note-detail-page__title {
  margin: 0;
  font-size: 1.35rem;
}

.note-detail-page__headerActions {
  display: flex;
  gap: 0.5rem;
}

.note-detail-page__lastEdit {
  margin: 0.35rem 0 0;
  font-size: 0.875rem;
  opacity: 0.85;
}

.note-detail-page__lockErr,
.note-detail-page__saveErr {
  color: #b91c1c;
  font-size: 0.875rem;
}

.note-detail-page__layout {
  display: flex;
  gap: 1.25rem;
  align-items: flex-start;
  margin-top: 1rem;
}

.note-detail-page__main {
  flex: 1;
  min-width: 0;
}

.note-detail-page__aside {
  width: 280px;
  flex-shrink: 0;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  padding: 0.75rem;
  max-height: 70vh;
  overflow: auto;
}

.note-detail-page__asideTitle {
  margin: 0 0 0.5rem;
  font-size: 1rem;
}

.note-detail-page__revList {
  list-style: none;
  margin: 0;
  padding: 0;
}

.note-detail-page__revLi {
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border);
}

.note-detail-page__revMeta {
  font-size: 0.75rem;
  opacity: 0.75;
}

.note-detail-page__revTitle {
  font-weight: 600;
  font-size: 0.875rem;
  margin: 0.25rem 0;
}

.note-detail-page__revActions {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.note-detail-page__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  margin-bottom: 0.75rem;
  font-size: 0.875rem;
}

.note-detail-page__fieldLabel {
  font-size: 0.875rem;
  font-weight: 600;
  margin-bottom: 0.35rem;
  display: block;
}

.note-detail-page__input,
.note-detail-page__textarea {
  font: inherit;
  padding: 0.45rem 0.55rem;
  border: 1px solid var(--border);
  border-radius: 0.35rem;
  width: 100%;
  box-sizing: border-box;
}

.note-detail-page__editorBlock {
  margin-bottom: 1rem;
}

.note-detail-page__saveRow {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.note-detail-page__hint {
  font-size: 0.85rem;
  opacity: 0.75;
}

.note-detail-page__collab {
  margin-top: 2rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border);
}

.note-detail-page__collabTitle {
  margin: 0 0 0.65rem;
  font-size: 1.05rem;
}

.note-detail-page__collabAdd {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 0.75rem;
}

.note-detail-page__select {
  font: inherit;
  padding: 0.35rem 0.5rem;
  border-radius: 0.35rem;
  border: 1px solid var(--border);
}

.note-detail-page__collabList {
  list-style: none;
  margin: 0;
  padding: 0;
}

.note-detail-page__collabLi {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0;
}

.note-detail-page__muted {
  opacity: 0.7;
  font-size: 0.85rem;
}

.note-detail-page__overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
  padding: 1rem;
}

.note-detail-page__dialogPanel {
  padding: 1rem;
  background: var(--bg);
  border-radius: 0.5rem;
}

.note-detail-page__dialogPanel--modal {
  max-width: 720px;
  max-height: 85vh;
  overflow: auto;
  width: 100%;
}
</style>
