<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ChatInfoPanel from '../../components/App/ChatInfoPanel.vue'
import { t } from '../../i18n/i18n'
import { fetchChat } from '../../services/chatApi.js'

const route = useRoute()
const router = useRouter()
const chatId = computed(() => route.params.chatId)
const chat = ref(null)
const loading = ref(true)
const notFound = ref(false)

async function load() {
  loading.value = true
  notFound.value = false
  const res = await fetchChat(chatId.value)
  loading.value = false
  if (res.ok && res.data?.chat) {
    chat.value = res.data.chat
  } else {
    notFound.value = true
  }
}

function onUpdated() {
  void load()
}

function backToThread() {
  void router.push({ name: 'chatThread', params: { chatId: chatId.value } })
}

onMounted(load)
watch(chatId, () => {
  void load()
})
</script>

<template>
  <div class="chat-info-page">
    <header class="chat-info-page__header">
      <button type="button" class="btn btn--secondary btn--sm" @click="backToThread">
        {{ t('chats.backToThread') }}
      </button>
    </header>

    <p v-if="loading">{{ t('chats.info.loading') }}</p>
    <p v-else-if="notFound || !chat">{{ t('chats.info.notFound') }}</p>
    <p v-else-if="!chat.is_owner" class="chat-info-page__notice">{{ t('chats.info.ownerOnly') }}</p>
    <ChatInfoPanel v-else :key="String(chat.id)" :chat="chat" @updated="onUpdated" />
  </div>
</template>

<style scoped lang="scss">
.chat-info-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  max-width: 32rem;
  margin: 0 auto;
  padding: 0.75rem;
}

.chat-info-page__header {
  display: flex;
  align-items: center;
}

.chat-info-page__notice {
  margin: 0;
  padding: 0.75rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  opacity: 0.9;
}
</style>
