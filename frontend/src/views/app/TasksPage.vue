<script setup>
import { computed, onMounted, ref } from 'vue'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import { t } from '../../i18n/i18n'
import { createTask, fetchTasks, updateTask } from '../../services/contentApi'
import { fetchFolders } from '../../services/chatApi'

const tasks = ref([])
const folders = ref([])
const loading = ref(false)
const error = ref('')
const title = ref('')
const folderId = ref('')

const grouped = computed(() => {
  const bucket = new Map()
  for (const folder of folders.value) bucket.set(Number(folder.id), { folder, tasks: [] })
  bucket.set(0, { folder: { id: 0, name: t('tasks.unfiled') }, tasks: [] })
  for (const task of tasks.value) {
    const id = Number(task.folder_id || 0)
    const row = bucket.get(id) || bucket.get(0)
    row.tasks.push(task)
  }
  return Array.from(bucket.values())
    .filter((row) => row.tasks.length > 0 || Number(row.folder.id) === Number(folderId.value || 0))
})

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

async function load() {
  loading.value = true
  error.value = ''
  const [tasksRes, foldersRes] = await Promise.all([fetchTasks(), fetchFolders()])
  loading.value = false
  if (!tasksRes.ok) {
    error.value = `HTTP ${tasksRes.status}`
    return
  }
  tasks.value = unwrapList(tasksRes.data)
  folders.value = unwrapList(foldersRes.data)
}

async function onCreate() {
  if (!title.value.trim()) return
  const res = await createTask({
    title: title.value.trim(),
    visibility_scope: 'private',
    folder_id: folderId.value ? Number(folderId.value) : null,
  })
  if (res.ok) {
    title.value = ''
    await load()
  } else {
    error.value = `HTTP ${res.status}`
  }
}

async function toggle(task) {
  const completed_at = task.completed_at ? null : new Date().toISOString()
  const res = await updateTask(task.id, { completed_at })
  if (res.ok) await load()
}

onMounted(load)
</script>

<template>
  <section class="page page--tasks">
    <header class="page__header">
      <PageToolbarTitle route-key="tasks">
        <Title tag="h1">{{ t('tasks.title') }}</Title>
      </PageToolbarTitle>
      <p class="page__muted">{{ t('tasks.intro') }}</p>
    </header>

    <div class="tasks-create">
      <input v-model="title" :placeholder="t('tasks.titlePlaceholder')" />
      <select v-model="folderId">
        <option value="">{{ t('tasks.unfiled') }}</option>
        <option v-for="folder in folders" :key="folder.id" :value="String(folder.id)">
          {{ folder.name }}
        </option>
      </select>
      <Button variant="primary" @click="onCreate">{{ t('tasks.add') }}</Button>
    </div>

    <p v-if="loading" class="page__muted">{{ t('tasks.loading') }}</p>
    <p v-if="error" class="page__error">{{ t('tasks.error').replace('{status}', error.replace('HTTP ', '')) }}</p>

    <div v-for="row in grouped" :key="row.folder.id" class="tasks-group">
      <h3>{{ row.folder.name }}</h3>
      <ul>
        <li v-for="task in row.tasks" :key="task.id">
          <label>
            <input type="checkbox" :checked="Boolean(task.completed_at)" @change="toggle(task)" />
            <span :class="{ done: task.completed_at }">{{ task.title || t('tasks.untitled') }}</span>
          </label>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped lang="scss">
.page--tasks { padding: 1rem; display: grid; gap: 0.8rem; }
.tasks-create { display: grid; grid-template-columns: 1fr 220px auto; gap: 0.5rem; }
.tasks-create input, .tasks-create select { padding: 0.5rem; border: 1px solid var(--border); border-radius: 0.5rem; }
.tasks-group { border: 1px solid var(--border); border-radius: 0.6rem; padding: 0.6rem; }
.tasks-group ul { margin: 0; padding-left: 1rem; display: grid; gap: 0.25rem; }
.done { text-decoration: line-through; opacity: 0.7; }
.page__muted { margin: 0; opacity: 0.8; }
.page__error { color: #b00020; margin: 0; }
</style>

