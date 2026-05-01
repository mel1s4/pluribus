<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import { t } from '../../i18n/i18n'
import { createChat } from '../../services/chatApi.js'
import { addContact, fetchContacts, searchContactByEmail } from '../../services/contactsApi.js'

const router = useRouter()
const contacts = ref([])
const loading = ref(true)
const selectedContactIds = ref([])
const searchEmail = ref('')
const searchResult = ref(null)
const searchLoading = ref(false)
const searchError = ref('')
const addLoading = ref(false)
const startLoading = ref(false)
const actionError = ref('')

const hasSelection = computed(() => selectedContactIds.value.length > 0)

function unwrapContacts(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload.contacts)) return payload.contacts
  if (Array.isArray(payload.data)) return payload.data
  return []
}

async function loadContacts() {
  loading.value = true
  const res = await fetchContacts()
  contacts.value = res.ok ? unwrapContacts(res.data) : []
  selectedContactIds.value = selectedContactIds.value
    .filter((id) => contacts.value.some((contact) => Number(contact.id) === Number(id)))
  loading.value = false
}

function toggleSelected(contactId, checked) {
  const id = Number(contactId)
  if (!Number.isFinite(id)) return
  if (checked) {
    if (!selectedContactIds.value.includes(id)) selectedContactIds.value.push(id)
    return
  }
  selectedContactIds.value = selectedContactIds.value.filter((candidate) => candidate !== id)
}

async function runSearch() {
  searchError.value = ''
  actionError.value = ''
  searchResult.value = null
  const email = searchEmail.value.trim()
  if (!email) return
  searchLoading.value = true
  const res = await searchContactByEmail(email)
  searchLoading.value = false
  if (!res.ok) {
    searchError.value = t('contacts.searchError', { status: res.status })
    return
  }
  searchResult.value = res.data?.user ?? null
}

async function addSearchResultToContacts() {
  if (!searchResult.value?.id) return
  addLoading.value = true
  actionError.value = ''
  const res = await addContact(searchResult.value.id)
  addLoading.value = false
  if (!res.ok) {
    actionError.value = t('contacts.addError', { status: res.status })
    return
  }
  await loadContacts()
}

async function startConversation() {
  if (!hasSelection.value) return
  startLoading.value = true
  actionError.value = ''
  const isDirect = selectedContactIds.value.length === 1
  const res = await createChat({
    type: isDirect ? 'direct' : 'group',
    member_ids: selectedContactIds.value,
  })
  startLoading.value = false
  if (!res.ok) {
    actionError.value = t('contacts.startConversationError', { status: res.status })
    return
  }
  const chatId = res.data?.chat?.id
  if (chatId != null) {
    selectedContactIds.value = []
    router.push({ name: 'chatThread', params: { chatId } })
  }
}

function contactAlreadySaved(userId) {
  return contacts.value.some((contact) => Number(contact.id) === Number(userId))
}

onMounted(loadContacts)
</script>

<template>
  <section class="contacts-page">
    <header class="contacts-page__toolbar">
      <PageToolbarTitle class="contacts-page__titleRow" route-key="my-contacts">
        <Title tag="h1">{{ t('contacts.title') }}</Title>
      </PageToolbarTitle>
    </header>

    <div class="contacts-page__panel">
      <label for="contacts-search-email" class="contacts-page__label">{{ t('contacts.searchLabel') }}</label>
      <div class="contacts-page__searchRow">
        <input
          id="contacts-search-email"
          v-model="searchEmail"
          class="contacts-page__input"
          type="email"
          :placeholder="t('contacts.searchPlaceholder')"
          @keyup.enter="runSearch"
        >
        <button type="button" class="btn btn--secondary btn--sm" :disabled="searchLoading" @click="runSearch">
          {{ t('contacts.searchCta') }}
        </button>
      </div>
      <p v-if="searchError" class="contacts-page__error">{{ searchError }}</p>
      <p v-else-if="searchLoading" class="contacts-page__hint">{{ t('contacts.searchLoading') }}</p>
      <div v-else-if="searchResult" class="contacts-page__result">
        <div class="contacts-page__resultMeta">
          <strong>{{ searchResult.name }}</strong>
          <span>{{ searchResult.email }}</span>
        </div>
        <button
          type="button"
          class="btn btn--primary btn--sm"
          :disabled="addLoading || contactAlreadySaved(searchResult.id)"
          @click="addSearchResultToContacts"
        >
          {{ contactAlreadySaved(searchResult.id) ? t('contacts.alreadyAdded') : t('contacts.addCta') }}
        </button>
      </div>
      <p v-else-if="searchEmail.trim()" class="contacts-page__hint">{{ t('contacts.searchNotFound') }}</p>
    </div>

    <div class="contacts-page__panel">
      <div class="contacts-page__contactsHeader">
        <h2>{{ t('contacts.savedTitle') }}</h2>
        <button
          type="button"
          class="btn btn--primary btn--sm"
          :disabled="startLoading || !hasSelection"
          @click="startConversation"
        >
          {{ t('contacts.startConversation') }}
        </button>
      </div>
      <p v-if="actionError" class="contacts-page__error">{{ actionError }}</p>
      <p v-if="loading" class="contacts-page__hint">{{ t('contacts.loading') }}</p>
      <p v-else-if="contacts.length === 0" class="contacts-page__hint">{{ t('contacts.empty') }}</p>
      <ul v-else class="contacts-page__list">
        <li v-for="contact in contacts" :key="contact.id" class="contacts-page__item">
          <label class="contacts-page__row">
            <input
              type="checkbox"
              :checked="selectedContactIds.includes(Number(contact.id))"
              @change="toggleSelected(contact.id, $event.target.checked)"
            >
            <span class="contacts-page__itemText">
              <strong>{{ contact.name }}</strong>
              <span>{{ contact.email }}</span>
            </span>
          </label>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped lang="scss">
.contacts-page { padding: 1rem; display: grid; gap: 1rem; }
.contacts-page__toolbar { display: flex; justify-content: space-between; gap: 0.75rem; align-items: center; }
.contacts-page__titleRow { flex: 1; min-width: 0; }
.contacts-page__panel { border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.85rem; display: grid; gap: 0.5rem; }
.contacts-page__label { font-size: 0.9rem; font-weight: 600; }
.contacts-page__searchRow { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.contacts-page__input { flex: 1; min-width: 15rem; border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.45rem 0.55rem; font: inherit; }
.contacts-page__result { border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.5rem 0.6rem; display: flex; justify-content: space-between; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.contacts-page__resultMeta { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
.contacts-page__contactsHeader { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.contacts-page__contactsHeader h2 { margin: 0; font-size: 1rem; }
.contacts-page__hint { margin: 0; opacity: 0.8; font-size: 0.85rem; }
.contacts-page__error { margin: 0; color: #dc2626; font-size: 0.85rem; }
.contacts-page__list { list-style: none; margin: 0; padding: 0; border: 1px solid var(--border); border-radius: 0.4rem; }
.contacts-page__item + .contacts-page__item { border-top: 1px solid var(--border); }
.contacts-page__row { display: flex; gap: 0.5rem; padding: 0.5rem 0.6rem; align-items: flex-start; cursor: pointer; }
.contacts-page__itemText { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
</style>
