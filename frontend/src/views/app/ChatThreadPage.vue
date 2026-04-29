<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { t } from '../../i18n/i18n'
import { useSession } from '../../composables/useSession.js'
import { useChatRealtime } from '../../composables/useChatRealtime.js'
import { fetchChat, fetchChatMessages, sendChatMessage } from '../../services/chatApi.js'
import { useChatUnread } from '../../composables/useChatUnread.js'

const MESSAGE_MAX = 10000
const CHAR_COUNTER_THRESHOLD = 8000
const NEAR_BOTTOM_PX = 100

const route = useRoute()
const router = useRouter()
const { user } = useSession()
const { markChatAsRead, setActiveChat } = useChatUnread()
const chatId = computed(() => route.params.chatId)
const chat = ref(null)
const messages = ref([])
const text = ref('')
const loading = ref(true)
const listEl = ref(null)

const timeFormatter = new Intl.DateTimeFormat(undefined, {
  hour: 'numeric',
  minute: '2-digit',
})

function formatMessageTime(msg) {
  const raw = msg.created_at
  if (!raw) {
    return timeFormatter.format(new Date())
  }
  return timeFormatter.format(new Date(raw))
}

function isOwnMessage(msg) {
  return user.value != null && Number(msg.user_id) === Number(user.value.id)
}

function replaceOptimistic(tempId, realMessage, status) {
  const idx = messages.value.findIndex((m) => m._tempId === tempId)
  if (idx === -1) {
    return
  }
  if (realMessage) {
    messages.value[idx] = { ...realMessage, _status: status }
  } else {
    messages.value[idx] = { ...messages.value[idx], _status: status }
  }
  nextTick(() => maybeScrollBottom(true))
}

function onIncomingMessage(message) {
  if (messages.value.some((m) => m.id === message.id)) {
    return
  }

  const uid = user.value?.id
  if (uid && Number(message.user_id) === Number(uid) && message.body) {
    const idx = messages.value.findIndex(
      (m) => m._status === 'pending' && m._tempId && m.body === message.body,
    )
    if (idx !== -1) {
      messages.value[idx] = { ...message, _status: 'sent' }
      nextTick(() => maybeScrollBottom(true))
      return
    }
  }

  const isOwn = Number(message.user_id) === Number(uid)
  messages.value.push({ ...message, _status: 'sent' })
  nextTick(() => maybeScrollBottom(isOwn))
}

const { connected } = useChatRealtime(chatId, onIncomingMessage)

const messagesWithMeta = computed(() =>
  messages.value.map((msg, i) => {
    const prev = i > 0 ? messages.value[i - 1] : null
    const isGrouped =
      Boolean(prev)
      && Number(prev.user_id) === Number(msg.user_id)
      && prev._status !== 'error'
      && msg._status !== 'error'
    return { ...msg, isGrouped }
  }),
)

const charCount = computed(() => text.value.length)
const showCharCounter = computed(() => charCount.value > CHAR_COUNTER_THRESHOLD)
const atCharLimit = computed(() => charCount.value > MESSAGE_MAX)
const canSend = computed(() => {
  const trimmed = text.value.trim()
  return trimmed.length > 0 && text.value.length <= MESSAGE_MAX
})

const charCountLabel = computed(() =>
  t('chats.thread.charCount')
    .replace('{used}', String(charCount.value))
    .replace('{max}', String(MESSAGE_MAX)),
)

const textareaRows = computed(() => {
  const lines = (text.value.match(/\n/g) || []).length + 1
  return Math.min(5, Math.max(1, lines))
})

async function load() {
  loading.value = true
  const [chatRes, msgRes] = await Promise.all([
    fetchChat(chatId.value),
    fetchChatMessages(chatId.value),
  ])
  if (chatRes.ok && chatRes.data?.chat) {
    chat.value = chatRes.data.chat
  }
  if (msgRes.ok && Array.isArray(msgRes.data?.data)) {
    messages.value = msgRes.data.data.map((m) => ({ ...m, _status: 'sent' }))
  }
  loading.value = false
  setActiveChat(chatId.value)
  await markChatAsRead(chatId.value)
  nextTick(() => maybeScrollBottom(true))
}

function maybeScrollBottom(isOwn) {
  if (!listEl.value) {
    return
  }
  const el = listEl.value
  const { scrollTop, scrollHeight, clientHeight } = el
  const nearBottom = scrollHeight - scrollTop - clientHeight < NEAR_BOTTOM_PX
  if (isOwn || nearBottom) {
    nextTick(() => {
      if (listEl.value) {
        listEl.value.scrollTop = listEl.value.scrollHeight
      }
    })
  }
}

function onComposerKeydown(e) {
  if (e.key !== 'Enter' || e.shiftKey) {
    return
  }
  e.preventDefault()
  if (canSend.value) {
    void submit()
  }
}

async function submit() {
  const body = text.value.trim()
  if (!body || text.value.length > MESSAGE_MAX) {
    return
  }

  const tempId = `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`
  const optimistic = {
    id: tempId,
    _tempId: tempId,
    user_id: user.value?.id,
    user: user.value
      ? { id: user.value.id, name: user.value.name, avatar_path: user.value.avatar_path }
      : null,
    body,
    created_at: new Date().toISOString(),
    _status: 'pending',
  }
  messages.value.push(optimistic)
  text.value = ''
  nextTick(() => maybeScrollBottom(true))

  const res = await sendChatMessage(chatId.value, body)
  if (res.ok && res.data?.message) {
    replaceOptimistic(tempId, res.data.message, 'sent')
  } else {
    replaceOptimistic(tempId, null, 'error')
  }
}

async function retryMessage(msg) {
  if (!msg._tempId || msg._status !== 'error') {
    return
  }
  const { body } = msg
  const { _tempId: tempId } = msg
  const idx = messages.value.findIndex((m) => m._tempId === tempId)
  if (idx === -1) {
    return
  }
  messages.value[idx] = { ...messages.value[idx], _status: 'pending' }
  nextTick(() => maybeScrollBottom(true))

  const res = await sendChatMessage(chatId.value, body)
  if (res.ok && res.data?.message) {
    replaceOptimistic(tempId, res.data.message, 'sent')
  } else {
    replaceOptimistic(tempId, null, 'error')
  }
}

function goBackToChats() {
  void router.push({ name: 'chats' })
}

function goToInfo() {
  if (!chat.value?.is_owner) {
    return
  }
  void router.push({ name: 'chatInfo', params: { chatId: String(chatId.value) } })
}

onMounted(load)
watch(chatId, () => {
  void load()
})
onBeforeUnmount(() => {
  setActiveChat(null)
})
</script>

<template>
  <section class="chat-thread">
    <header class="chat-thread__header">
      <button
        type="button"
        class="btn btn--secondary btn--sm chat-thread__back"
        :aria-label="t('chats.backToChats')"
        @click="goBackToChats"
      >
        ← {{ t('chats.backToChats') }}
      </button>
      <div class="chat-thread__avatar" :style="{ backgroundColor: chat?.icon_bg_color || '#2563eb' }">
        {{ chat?.icon_emoji || '💬' }}
      </div>
      <div class="chat-thread__meta">
        <h1 class="chat-thread__title">{{ chat?.title || t('chats.thread.title') }}</h1>
      </div>
      <span
        class="chat-thread__connection"
        :class="{
          'chat-thread__connection--live': connected,
          'chat-thread__connection--off': !connected,
        }"
        role="status"
      >
        <span class="chat-thread__connection-dot" aria-hidden="true" />
        {{ connected ? t('chats.thread.connectionLive') : t('chats.thread.connectionConnecting') }}
      </span>
      <button
        v-if="chat?.is_owner"
        type="button"
        class="btn btn--secondary btn--sm"
        @click="goToInfo"
      >
        {{ t('chats.info.title') }}
      </button>
    </header>

    <p v-if="loading">{{ t('chats.thread.loadingThread') }}</p>
    <div v-else ref="listEl" class="chat-thread__messages">
      <div
        v-for="message in messagesWithMeta"
        :key="message.id"
        class="chat-thread__message"
        :class="{
          'chat-thread__message--own': isOwnMessage(message),
          'chat-thread__message--grouped': message.isGrouped,
        }"
      >
        <div
          class="chat-thread__bubble"
          :class="{
            'chat-thread__bubble--pending': isOwnMessage(message) && message._status === 'pending',
            'chat-thread__bubble--error': isOwnMessage(message) && message._status === 'error',
          }"
        >
          <div v-if="!message.isGrouped" class="chat-thread__author">
            {{ message.user?.name || t('chats.unknownUser') }}
          </div>
          <div class="chat-thread__body-text">{{ message.body }}</div>
          <div
            class="chat-thread__meta-row"
            :class="{ 'chat-thread__meta-row--own': isOwnMessage(message) }"
          >
            <time class="chat-thread__time" :datetime="message.created_at || undefined">
              {{ formatMessageTime(message) }}
            </time>
            <span v-if="isOwnMessage(message)" class="chat-thread__status" aria-label="message status">
              <span v-if="message._status === 'pending'" class="chat-thread__spinner" aria-hidden="true" />
              <template v-else-if="message._status === 'error'">
                <span class="chat-thread__err-icon" aria-hidden="true">✗</span>
              </template>
              <svg
                v-else
                class="chat-thread__check"
                viewBox="0 0 20 20"
                width="12"
                height="12"
                aria-hidden="true"
              >
                <path
                  fill="currentColor"
                  d="M16.707 5.293a1 1 0 0 1 0 1.414l-8 8a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 1.414-1.414L8 12.586l7.293-7.293a1 1 0 0 1 1.414 0z"
                />
              </svg>
            </span>
          </div>
          <div v-if="isOwnMessage(message) && message._status === 'error'" class="chat-thread__retry">
            <button type="button" class="btn btn--secondary btn--sm" @click="retryMessage(message)">
              {{ t('chats.thread.retry') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <form class="chat-thread__composer" @submit.prevent="submit">
      <div class="chat-thread__composer-field">
        <textarea
          v-model="text"
          :rows="textareaRows"
          :placeholder="t('chats.thread.typeMessage')"
          class="chat-thread__input"
          :maxlength="MESSAGE_MAX"
          @keydown="onComposerKeydown"
        />
        <p
          v-show="showCharCounter"
          class="chat-thread__char-hint"
          :class="{ 'chat-thread__char-hint--warn': atCharLimit }"
        >
          {{ charCountLabel }}
        </p>
      </div>
      <button type="submit" class="btn btn--primary btn--sm" :disabled="!canSend || atCharLimit">
        {{ t('chats.thread.send') }}
      </button>
    </form>
  </section>
</template>

<style scoped lang="scss">
.chat-thread { display: flex; flex-direction: column; height: calc(100vh - 7rem); padding: 0.75rem; gap: 0.75rem; }
.chat-thread__header { display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; flex-wrap: wrap; }
.chat-thread__back { flex-shrink: 0; }
.chat-thread__avatar { width: 2rem; height: 2rem; border-radius: 999px; display: inline-flex; justify-content: center; align-items: center; }
.chat-thread__meta { flex: 1; min-width: 0; }
.chat-thread__title { font-size: 1.1rem; margin: 0; }
.chat-thread__connection { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 999px; border: 1px solid var(--border); }
.chat-thread__connection-dot { width: 0.4rem; height: 0.4rem; border-radius: 50%; background: #94a3b8; }
.chat-thread__connection--live .chat-thread__connection-dot { background: #22c55e; }
.chat-thread__connection--off .chat-thread__connection-dot { background: #94a3b8; }
.chat-thread__messages { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 0.5rem; }
.chat-thread__message { display: flex; }
.chat-thread__message--own { justify-content: flex-end; }
.chat-thread__message--grouped { margin-top: -0.25rem; }
.chat-thread__message--grouped .chat-thread__bubble { margin-top: 0.15rem; }
.chat-thread__bubble {
  max-width: 70%;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  padding: 0.5rem 0.65rem;
  position: relative;
  transition: opacity 0.15s ease;
}
.chat-thread__bubble--pending { opacity: 0.6; }
.chat-thread__bubble--error { border-color: #f87171; }
.chat-thread__author { font-size: 0.75rem; opacity: 0.7; margin-bottom: 0.2rem; }
.chat-thread__body-text { white-space: pre-wrap; word-break: break-word; }
.chat-thread__meta-row {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin-top: 0.35rem;
  font-size: 0.7rem;
  opacity: 0.65;
  justify-content: flex-start;
}
.chat-thread__meta-row--own { justify-content: flex-end; }
.chat-thread__time { font-variant-numeric: tabular-nums; }
.chat-thread__status { display: inline-flex; align-items: center; min-height: 0.75rem; }
.chat-thread__check { color: #38bdf8; flex-shrink: 0; }
.chat-thread__err-icon { color: #ef4444; font-size: 0.7rem; }
.chat-thread__spinner {
  width: 0.75rem;
  height: 0.75rem;
  border: 1.5px solid color-mix(in srgb, currentColor 30%, transparent);
  border-top-color: currentColor;
  border-radius: 50%;
  animation: chat-spin 0.7s linear infinite;
}
.chat-thread__retry { margin-top: 0.35rem; }
.chat-thread__composer { display: flex; gap: 0.5rem; align-items: flex-end; }
.chat-thread__composer-field { flex: 1; display: flex; flex-direction: column; gap: 0.25rem; }
.chat-thread__input {
  width: 100%;
  min-height: 2.5rem;
  border: 1px solid var(--border);
  border-radius: 0.35rem;
  padding: 0.45rem 0.6rem;
  font: inherit;
  line-height: 1.4;
  resize: vertical;
  max-height: 8rem;
}
.chat-thread__char-hint { font-size: 0.7rem; margin: 0; opacity: 0.75; }
.chat-thread__char-hint--warn { color: #ef4444; opacity: 1; }

@keyframes chat-spin {
  to { transform: rotate(360deg); }
}
</style>
