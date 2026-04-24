<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { t } from '../../i18n/i18n'
import { fetchChatFolders, fetchChats } from '../../services/chatApi.js'

const route = useRoute()
const router = useRouter()
const folderId = computed(() => Number(route.params.folderId))
const folders = ref([])
const chats = ref([])

const folder = computed(() => folders.value.find((item) => Number(item.id) === folderId.value) || null)
const folderChats = computed(() => chats.value.filter((item) => Number(item.folder_id) === folderId.value))

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

async function load() {
  const [foldersRes, chatsRes] = await Promise.all([fetchChatFolders(), fetchChats()])
  if (foldersRes.ok) folders.value = unwrapList(foldersRes.data)
  if (chatsRes.ok) chats.value = unwrapList(chatsRes.data)
}

function openChat(chat) {
  router.push({ name: 'chatThread', params: { chatId: chat.id } })
}

onMounted(load)
</script>

<template>
  <section class="chat-folder-page">
    <header class="chat-folder-page__header">
      <button class="btn btn--secondary btn--sm" @click="$router.push({ name: 'chats' })">{{ t('chats.backToChats') }}</button>
      <h1>{{ folder?.name || t('chats.folderTitle') }}</h1>
    </header>

    <ul class="chat-folder-page__list">
      <li v-for="chat in folderChats" :key="chat.id" class="chat-folder-page__item">
        <button class="chat-folder-page__chatButton" @click="openChat(chat)">
          <span class="chat-folder-page__icon" :style="{ backgroundColor: chat.icon_bg_color || '#2563eb' }">
            {{ chat.icon_emoji || '💬' }}
          </span>
          <span>{{ chat.title || t('chats.defaultConversation') }}</span>
        </button>
      </li>
    </ul>
  </section>
</template>

<style scoped lang="scss">
.chat-folder-page { padding: 1rem; }
.chat-folder-page__header { display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.75rem; }
.chat-folder-page__list { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem; }
.chat-folder-page__chatButton { display: flex; gap: 0.5rem; align-items: center; border: 1px solid var(--border); width: 100%; background: var(--bg); padding: 0.5rem; border-radius: 0.5rem; }
.chat-folder-page__icon { width: 1.8rem; height: 1.8rem; border-radius: 999px; display: inline-flex; justify-content: center; align-items: center; }
</style>
