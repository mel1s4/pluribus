<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import Title from '../../atoms/Title.vue'
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

const router = useRouter()
const chats = ref([])
const folders = ref([])
const loading = ref(true)

const chatDialogRef = ref(null)
const folderDialogRef = ref(null)
const chatSaving = ref(false)
const folderSaving = ref(false)

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
    member_ids: [],
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

async function rename(chat) {
  const next = window.prompt(t('chats.rename'), chat.title || '')
  if (next === null) return
  await updateChat(chat.id, { title: next.trim() })
  await load()
}

async function remove(chat) {
  await deleteChat(chat.id)
  await load()
}

function openChat(chat) {
  router.push({ name: 'chatThread', params: { chatId: chat.id } })
}

function openFolder(folderId) {
  router.push({ name: 'chatFolder', params: { folderId } })
}

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
          <button class="btn btn--secondary btn--sm" @click="rename(chat)">{{ t('chats.rename') }}</button>
          <button class="btn btn--secondary btn--sm" @click="remove(chat)">{{ t('chats.delete') }}</button>
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
.chats-page__chatOpen { border: none; background: transparent; display: flex; align-items: center; gap: 0.5rem; flex: 1; text-align: left; cursor: pointer; }
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
</style>
