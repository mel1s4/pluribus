<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { t } from '../../i18n/i18n'
import ChatColorPicker from '../../molecules/ChatColorPicker.vue'
import ChatIconPicker from '../../molecules/ChatIconPicker.vue'
import { useChatMemberPicker } from '../../composables/useChatMemberPicker.js'
import {
  addChatMembers,
  createChatBackup,
  fetchChatBackups,
  removeChatMember,
  updateChat,
} from '../../services/chatApi.js'

const props = defineProps({
  chat: { type: Object, required: true },
})

const emit = defineEmits(['updated'])

const loading = ref(false)
const membersLoading = ref(false)
const membersSaving = ref(false)
const backups = ref([])
const members = ref([])
const form = reactive({
  title: '',
  icon_emoji: '',
  icon_bg_color: '#2563eb',
})

const {
  memberSearchQuery,
  memberSearchResults,
  memberSearchLoading,
  selectedMembers,
  isSelectedMember,
  toggleMember,
  removeSelectedMember,
  reset,
} = useChatMemberPicker({ unknownUserLabel: t('chats.unknownUser') })

const selectedMemberIds = computed(() =>
  selectedMembers.value.map((member) => Number(member.id)).filter((id) => Number.isFinite(id)),
)
const canAddMembers = computed(() => selectedMemberIds.value.length > 0 && !membersSaving.value)

function isExistingMember(memberId) {
  return members.value.some((member) => Number(member.id) === Number(memberId))
}

function syncForm() {
  form.title = props.chat?.title || ''
  form.icon_emoji = props.chat?.icon_emoji || '💬'
  form.icon_bg_color = props.chat?.icon_bg_color || '#2563eb'
  members.value = Array.isArray(props.chat?.members) ? [...props.chat.members] : []
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

async function addMembers() {
  if (!canAddMembers.value) return
  membersSaving.value = true
  const res = await addChatMembers(props.chat.id, selectedMemberIds.value)
  membersSaving.value = false
  if (res.ok && res.data?.chat) {
    members.value = Array.isArray(res.data.chat.members) ? res.data.chat.members : members.value
    reset()
    emit('updated')
  }
}

async function removeMember(memberId) {
  membersLoading.value = true
  const res = await removeChatMember(props.chat.id, memberId)
  membersLoading.value = false
  if (res.ok && res.data?.chat) {
    members.value = Array.isArray(res.data.chat.members) ? res.data.chat.members : []
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
    reset()
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

    <h3 class="chat-info-panel__subtitle">{{ t('chats.info.members') }}</h3>
    <div class="chat-info-panel__field">
      <label>{{ t('chats.modal.membersLabel') }}</label>
      <input
        v-model="memberSearchQuery"
        type="search"
        :placeholder="t('chats.modal.membersPlaceholder')"
      >
      <div v-if="memberSearchLoading" class="chat-info-panel__hint">{{ t('chats.modal.membersSearching') }}</div>
      <div v-else-if="memberSearchQuery.trim().length >= 2 && !memberSearchResults.length" class="chat-info-panel__hint">
        {{ t('chats.modal.membersEmpty') }}
      </div>
      <ul v-if="memberSearchResults.length" class="chat-info-panel__member-search-results">
        <li v-for="member in memberSearchResults" :key="member.id" class="chat-info-panel__member-result">
          <span>{{ member.name }}</span>
          <button
            type="button"
            class="btn btn--secondary btn--sm"
            :disabled="isSelectedMember(member.id) || isExistingMember(member.id)"
            @click="toggleMember(member)"
          >
            {{ isExistingMember(member.id) ? t('chats.info.memberAlreadyInChat') : (isSelectedMember(member.id) ? t('chats.info.selected') : t('chats.info.add')) }}
          </button>
        </li>
      </ul>
      <div v-if="selectedMembers.length" class="chat-info-panel__selected-members">
        <span v-for="member in selectedMembers" :key="member.id" class="chat-info-panel__member-chip">
          {{ member.name }}
          <button type="button" class="chat-info-panel__chip-remove" @click="removeSelectedMember(member.id)">
            ×
          </button>
        </span>
      </div>
      <button type="button" class="btn btn--secondary btn--sm" :disabled="!canAddMembers" @click="addMembers">
        {{ t('chats.info.addMembers') }}
      </button>
    </div>

    <ul class="chat-info-panel__list">
      <li v-for="member in members" :key="member.id" class="chat-info-panel__list-item">
        <span>{{ member.name || t('chats.unknownUser') }}</span>
        <button
          v-if="Number(member.id) !== Number(chat.owner_id)"
          type="button"
          class="btn btn--secondary btn--sm"
          :disabled="membersLoading"
          @click="removeMember(member.id)"
        >
          {{ t('chats.delete') }}
        </button>
      </li>
    </ul>

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

.chat-info-panel__list-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.chat-info-panel__member-search-results {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.chat-info-panel__member-result {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.chat-info-panel__selected-members {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.chat-info-panel__member-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 0.2rem 0.5rem;
}

.chat-info-panel__chip-remove {
  border: 0;
  background: transparent;
  cursor: pointer;
}

.chat-info-panel__hint {
  font-size: 0.85rem;
  opacity: 0.7;
}
</style>
