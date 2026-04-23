<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Title from '../../atoms/Title.vue'
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
const title = ref('')

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
    { id: 'unfiled', name: t('chats.unfiled'), chats: byFolder.get('unfiled') || [] },
    ...folders.value.map((folder) => ({ id: folder.id, name: folder.name, chats: byFolder.get(folder.id) || [] })),
  ]
})

async function load() {
  loading.value = true
  const [chatRes, folderRes] = await Promise.all([fetchChats(), fetchChatFolders()])
  if (chatRes.ok && Array.isArray(chatRes.data)) chats.value = chatRes.data
  if (folderRes.ok && Array.isArray(folderRes.data)) folders.value = folderRes.data
  loading.value = false
}

async function addChat() {
  const payload = {
    type: 'group',
    title: title.value.trim() || t('chats.defaultConversation'),
    member_ids: [],
  }
  const res = await createChat(payload)
  if (res.ok) {
    title.value = ''
    await load()
  }
}

async function addFolder() {
  await createChatFolder({ name: `${t('chats.folderDefault')} ${folders.value.length + 1}` })
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
        <input v-model="title" :placeholder="t('chats.defaultConversation')" class="chats-page__input">
        <button class="btn btn--primary btn--sm" @click="addChat">{{ t('chats.addConversation') }}</button>
        <button class="btn btn--secondary btn--sm" @click="addFolder">{{ t('chats.addFolder') }}</button>
      </div>
    </header>
    <p v-if="loading">{{ t('chats.loading') }}</p>
    <div v-for="section in sections" :key="section.id" class="chats-page__section">
      <div class="chats-page__sectionHeader">
        <button
          v-if="section.id !== 'unfiled'"
          type="button"
          class="chats-page__folderButton"
          @click="openFolder(section.id)"
        >
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
.chats-page__toolbar { display: flex; justify-content: space-between; gap: 1rem; }
.chats-page__actions { display: flex; gap: 0.5rem; align-items: center; }
.chats-page__section { border: 1px solid var(--border); border-radius: 0.5rem; margin-top: 0.75rem; }
.chats-page__sectionHeader { padding: 0.5rem 0.75rem; font-weight: 700; border-bottom: 1px solid var(--border); }
.chats-page__list { list-style: none; margin: 0; padding: 0.5rem; display: grid; gap: 0.5rem; }
.chats-page__item { display: flex; align-items: center; gap: 0.5rem; }
.chats-page__chatOpen { border: none; background: transparent; display: flex; align-items: center; gap: 0.5rem; flex: 1; text-align: left; cursor: pointer; }
.chats-page__icon { width: 1.8rem; height: 1.8rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; }
.chats-page__folderButton { border: none; background: transparent; font: inherit; padding: 0; cursor: pointer; text-decoration: underline; }
</style>
