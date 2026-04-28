<script setup>
import { computed, onMounted, ref } from 'vue'
import Title from '../../atoms/Title.vue'
import { t } from '../../i18n/i18n'
import { createGroup, fetchGroupMembers, fetchGroups, removeGroupMember } from '../../services/contentApi'
import { fetchUsers } from '../../services/usersApi'
import { sessionUser } from '../../composables/useSession'

const groups = ref([])
const membersByGroup = ref({})
const users = ref([])
const loading = ref(false)
const error = ref('')
const name = ref('')

const currentUserId = computed(() => Number(sessionUser.value?.id || 0))

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

async function load() {
  loading.value = true
  error.value = ''
  const [groupsRes, usersRes] = await Promise.all([fetchGroups(), fetchUsers({ per_page: 100 })])
  loading.value = false
  if (!groupsRes.ok) {
    error.value = `HTTP ${groupsRes.status}`
    return
  }
  groups.value = unwrapList(groupsRes.data)
  users.value = unwrapList(usersRes.data)
  await Promise.all(groups.value.map(async (group) => {
    const membersRes = await fetchGroupMembers(group.id)
    if (membersRes.ok) {
      membersByGroup.value = {
        ...membersByGroup.value,
        [String(group.id)]: unwrapList(membersRes.data),
      }
    }
  }))
}

async function onCreate() {
  if (!name.value.trim()) return
  const res = await createGroup({ name: name.value.trim() })
  if (res.ok) {
    name.value = ''
    await load()
  } else {
    error.value = `HTTP ${res.status}`
  }
}

async function leave(group) {
  const members = membersByGroup.value[String(group.id)] || []
  const me = members.find((m) => Number(m.id) === currentUserId.value)
  if (!me) return
  const res = await removeGroupMember(group.id, me.id)
  if (res.ok) await load()
}

function memberCount(groupId) {
  const members = membersByGroup.value[String(groupId)] || []
  return members.length
}

onMounted(load)
</script>

<template>
  <section class="page page--groups">
    <header class="page__header">
      <Title tag="h1">{{ t('groups.title') }}</Title>
      <p class="page__muted">{{ t('groups.intro') }}</p>
    </header>

    <div class="groups-create">
      <input v-model="name" :placeholder="t('groups.namePlaceholder')" />
      <button class="btn btn--primary" @click="onCreate">{{ t('groups.create') }}</button>
    </div>

    <p v-if="loading" class="page__muted">{{ t('groups.loading') }}</p>
    <p v-if="error" class="page__error">{{ t('groups.error').replace('{status}', error.replace('HTTP ', '')) }}</p>

    <ul class="groups-list">
      <li v-for="group in groups" :key="group.id" class="groups-list__item">
        <div>
          <strong>{{ group.name }}</strong>
          <p class="page__muted">
            {{ t('groups.membersCount').replace('{count}', String(memberCount(group.id))) }}
          </p>
        </div>
        <button class="btn btn--ghost" @click="leave(group)">
          {{ t('groups.leave') }}
        </button>
      </li>
    </ul>
  </section>
</template>

<style scoped lang="scss">
.page--groups { padding: 1rem; display: grid; gap: 0.8rem; }
.groups-create { display: flex; gap: 0.5rem; }
.groups-create input { flex: 1 1 auto; padding: 0.5rem; border: 1px solid var(--border); border-radius: 0.5rem; }
.groups-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem; }
.groups-list__item { display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--border); border-radius: 0.6rem; padding: 0.7rem; }
.page__muted { margin: 0; opacity: 0.8; }
.page__error { color: #b00020; margin: 0; }
</style>

