<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ChatInfoPanel from '../../components/App/ChatInfoPanel.vue'
import { t } from '../../i18n/i18n'
import { useSession } from '../../composables/useSession.js'
import { useChatRealtime } from '../../composables/useChatRealtime.js'
import { fetchChat, fetchChatMessages, sendChatMessage } from '../../services/chatApi.js'

const route = useRoute()
const { user } = useSession()
const chatId = computed(() => route.params.chatId)
const chat = ref(null)
const messages = ref([])
const text = ref('')
const loading = ref(true)
const showInfo = ref(false)
const listEl = ref(null)

function onIncomingMessage(message) {
  messages.value.push(message)
  nextTick(scrollBottom)
}

useChatRealtime(chatId, onIncomingMessage)

async function load() {
  loading.value = true
  const [chatRes, msgRes] = await Promise.all([
    fetchChat(chatId.value),
    fetchChatMessages(chatId.value),
  ])
  if (chatRes.ok && chatRes.data?.chat) chat.value = chatRes.data.chat
  if (msgRes.ok && Array.isArray(msgRes.data?.data)) messages.value = msgRes.data.data
  loading.value = false
  nextTick(scrollBottom)
}

function scrollBottom() {
  if (listEl.value) {
    listEl.value.scrollTop = listEl.value.scrollHeight
  }
}

async function submit() {
  const body = text.value.trim()
  if (!body) return
  const res = await sendChatMessage(chatId.value, body)
  if (res.ok) {
    text.value = ''
  }
}

onMounted(load)
</script>

<template>
  <section class="chat-thread">
    <header class="chat-thread__header">
      <div class="chat-thread__avatar" :style="{ backgroundColor: chat?.icon_bg_color || '#2563eb' }">
        {{ chat?.icon_emoji || '💬' }}
      </div>
      <div class="chat-thread__meta">
        <h1 class="chat-thread__title">{{ chat?.title || t('chats.thread.title') }}</h1>
      </div>
      <button class="btn btn--secondary btn--sm" @click="showInfo = !showInfo">{{ t('chats.info.title') }}</button>
    </header>

    <p v-if="loading">{{ t('chats.loading') }}</p>
    <div v-else ref="listEl" class="chat-thread__messages">
      <div
        v-for="message in messages"
        :key="message.id"
        class="chat-thread__message"
        :class="{ 'chat-thread__message--own': Number(message.user_id) === Number(user?.id) }"
      >
        <div class="chat-thread__bubble">
          <div class="chat-thread__author">{{ message.user?.name || t('chats.unknownUser') }}</div>
          <div>{{ message.body }}</div>
        </div>
      </div>
    </div>

    <form class="chat-thread__composer" @submit.prevent="submit">
      <input v-model="text" :placeholder="t('chats.thread.typeMessage')" class="chat-thread__input">
      <button type="submit" class="btn btn--primary btn--sm">{{ t('chats.thread.send') }}</button>
    </form>

    <ChatInfoPanel
      v-if="showInfo && chat && chat.is_owner"
      :chat="chat"
      @updated="load"
    />
  </section>
</template>

<style scoped lang="scss">
.chat-thread { display: flex; flex-direction: column; height: calc(100vh - 7rem); padding: 0.75rem; gap: 0.75rem; }
.chat-thread__header { display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }
.chat-thread__avatar { width: 2rem; height: 2rem; border-radius: 999px; display: inline-flex; justify-content: center; align-items: center; }
.chat-thread__messages { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 0.5rem; }
.chat-thread__message { display: flex; }
.chat-thread__message--own { justify-content: flex-end; }
.chat-thread__bubble { max-width: 70%; border-radius: 0.5rem; border: 1px solid var(--border); padding: 0.5rem 0.65rem; }
.chat-thread__author { font-size: 0.75rem; opacity: 0.7; }
.chat-thread__composer { display: flex; gap: 0.5rem; }
.chat-thread__input { flex: 1; }
</style>
