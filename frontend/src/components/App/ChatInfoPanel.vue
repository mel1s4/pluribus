<script setup>
import { reactive, ref, watch } from 'vue'
import { t } from '../../i18n/i18n'
import ChatColorPicker from '../../molecules/ChatColorPicker.vue'
import ChatIconPicker from '../../molecules/ChatIconPicker.vue'
import { createChatBackup, fetchChatBackups, updateChat } from '../../services/chatApi.js'

const props = defineProps({
  chat: { type: Object, required: true },
})

const emit = defineEmits(['updated'])

const loading = ref(false)
const backups = ref([])
const form = reactive({
  title: '',
  icon_emoji: '',
  icon_bg_color: '#2563eb',
})

function syncForm() {
  form.title = props.chat?.title || ''
  form.icon_emoji = props.chat?.icon_emoji || '💬'
  form.icon_bg_color = props.chat?.icon_bg_color || '#2563eb'
}

async function loadBackups() {
  const res = await fetchChatBackups(props.chat.id)
  if (res.ok && res.data?.backups) {
    backups.value = res.data.backups
  }
}

async function save() {
  loading.value = true
  const res = await updateChat(props.chat.id, { ...form })
  loading.value = false
  if (res.ok) {
    emit('updated')
  }
}

async function createBackup() {
  const res = await createChatBackup(props.chat.id)
  if (res.ok && res.data?.backup?.download_url) {
    window.open(res.data.backup.download_url, '_blank')
    await loadBackups()
  }
}

syncForm()
void loadBackups()

watch(
  () => props.chat.id,
  () => {
    syncForm()
    void loadBackups()
  },
)
</script>

<template>
  <aside class="chat-info-panel">
    <h2 class="chat-info-panel__title">{{ t('chats.info.title') }}</h2>
    <div class="chat-info-panel__field">
      <label>{{ t('chats.info.editTitle') }}</label>
      <input v-model="form.title" type="text">
    </div>
    <div class="chat-info-panel__field">
      <label>{{ t('chats.info.editIcon') }}</label>
      <ChatIconPicker v-model="form.icon_emoji" />
    </div>
    <div class="chat-info-panel__field">
      <label>{{ t('chats.info.editColor') }}</label>
      <ChatColorPicker v-model="form.icon_bg_color" />
    </div>
    <button class="btn btn--primary btn--sm" :disabled="loading" @click="save">
      {{ t('chats.info.save') }}
    </button>

    <h3 class="chat-info-panel__subtitle">{{ t('chats.info.backupConversation') }}</h3>
    <button class="btn btn--secondary btn--sm" @click="createBackup">
      {{ t('chats.info.downloadBackup') }}
    </button>
    <ul class="chat-info-panel__list">
      <li v-for="backup in backups" :key="backup.id">
        {{ backup.file_name }}
      </li>
    </ul>
  </aside>
</template>

<style scoped lang="scss">
.chat-info-panel {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.75rem;
  border: 1px solid var(--border);
  border-radius: 0.6rem;
}

.chat-info-panel__field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.chat-info-panel__list {
  margin: 0;
  padding-left: 1rem;
}
</style>
