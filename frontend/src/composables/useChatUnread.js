import { computed, ref } from 'vue'
import { useSession } from './useSession.js'
import { fetchChats, markChatRead } from '../services/chatApi.js'
import { getChatEcho } from './useChatRealtime.js'
import { useMessageNotifications } from './useMessageNotifications.js'

const unreadByChatId = ref({})
const activeChatId = ref(null)
const chatTitlesById = ref({})
const subscribedChatIds = new Set()
let initialized = false

const totalUnread = computed(() => Object.values(unreadByChatId.value).reduce((sum, count) => sum + Number(count || 0), 0))
const { user } = useSession()
const { requestPermission, notify, playIncomingSound } = useMessageNotifications()

function normalizeCount(value) {
  const n = Number(value)
  return Number.isFinite(n) && n > 0 ? Math.floor(n) : 0
}

function setChatUnread(chatId, count) {
  const key = String(chatId)
  unreadByChatId.value = {
    ...unreadByChatId.value,
    [key]: normalizeCount(count),
  }
}

function incrementChatUnread(chatId, delta = 1) {
  const key = String(chatId)
  const current = normalizeCount(unreadByChatId.value[key] ?? 0)
  setChatUnread(key, current + delta)
}

function clearChatUnread(chatId) {
  setChatUnread(chatId, 0)
}

function getChatUnread(chatId) {
  return normalizeCount(unreadByChatId.value[String(chatId)] ?? 0)
}

function hydrateFromChats(chats) {
  const nextUnread = {}
  const nextTitles = {}
  for (const chat of chats) {
    const key = String(chat.id)
    nextUnread[key] = normalizeCount(chat.unread_count ?? 0)
    nextTitles[key] = chat.title || 'New message'
  }
  unreadByChatId.value = nextUnread
  chatTitlesById.value = nextTitles
  ensureRealtimeSubscriptions(chats.map((chat) => String(chat.id)))
}

function handleIncomingEvent(chatId, event) {
  const message = event?.message
  if (!message) return
  if (Number(message.user_id) === Number(user.value?.id)) return

  const isActiveChat = String(activeChatId.value || '') === String(chatId)
  if (!isActiveChat) {
    incrementChatUnread(chatId, 1)
  }

  playIncomingSound()
  if (!isActiveChat) {
    notify({
      title: chatTitlesById.value[String(chatId)] || 'New message',
      body: message.body || 'You received a new message.',
      tag: `chat-${chatId}`,
    })
  }
}

function ensureRealtimeSubscriptions(chatIds) {
  const next = new Set(chatIds.map((id) => String(id)))
  const echo = getChatEcho()

  for (const id of subscribedChatIds) {
    if (!next.has(id)) {
      echo.leave(`chat.${id}`)
      subscribedChatIds.delete(id)
    }
  }

  for (const id of next) {
    if (subscribedChatIds.has(id)) continue
    echo.private(`chat.${id}`).listen('.message.sent', (event) => handleIncomingEvent(id, event))
    subscribedChatIds.add(id)
  }
}

async function refreshUnreadFromApi() {
  const res = await fetchChats()
  if (!res.ok) return
  const chats = Array.isArray(res.data?.data) ? res.data.data : Array.isArray(res.data) ? res.data : []
  hydrateFromChats(chats)
}

async function markChatAsRead(chatId) {
  clearChatUnread(chatId)
  const res = await markChatRead(chatId)
  return res
}

function setActiveChat(chatId) {
  activeChatId.value = chatId != null ? String(chatId) : null
  if (chatId != null) {
    clearChatUnread(chatId)
  }
}

async function initializeChatUnread() {
  if (initialized) return
  initialized = true
  await requestPermission()
  await refreshUnreadFromApi()
}

export function useChatUnread() {
  return {
    unreadByChatId,
    totalUnread,
    initializeChatUnread,
    refreshUnreadFromApi,
    hydrateFromChats,
    setChatUnread,
    clearChatUnread,
    getChatUnread,
    markChatAsRead,
    setActiveChat,
  }
}
