<script setup>
import { onMounted, ref } from 'vue'
import Title from '../../atoms/Title.vue'
import { t } from '../../i18n/i18n'
import { createPost, fetchPosts } from '../../services/contentApi'

const posts = ref([])
const loading = ref(false)
const error = ref('')
const title = ref('')
const description = ref('')
const type = ref('info')

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

async function load() {
  loading.value = true
  error.value = ''
  const res = await fetchPosts()
  loading.value = false
  if (!res.ok) {
    error.value = `HTTP ${res.status}`
    return
  }
  posts.value = unwrapList(res.data)
}

async function onCreate() {
  if (!title.value.trim()) return
  const res = await createPost({
    type: type.value,
    title: title.value.trim(),
    description: description.value.trim() || null,
    visibility_scope: 'private',
  })
  if (res.ok) {
    title.value = ''
    description.value = ''
    await load()
  } else {
    error.value = `HTTP ${res.status}`
  }
}

onMounted(load)
</script>

<template>
  <section class="page page--posts">
    <header class="page__header">
      <Title tag="h1">{{ t('posts.title') }}</Title>
      <p class="page__muted">{{ t('posts.intro') }}</p>
    </header>

    <div class="posts-create">
      <select v-model="type">
        <option value="event">{{ t('posts.typeEvent') }}</option>
        <option value="announcement">{{ t('posts.typeAnnouncement') }}</option>
        <option value="info">{{ t('posts.typeInfo') }}</option>
      </select>
      <input v-model="title" :placeholder="t('posts.titlePlaceholder')" />
      <textarea v-model="description" rows="2" :placeholder="t('posts.descriptionPlaceholder')" />
      <button class="btn btn--primary" @click="onCreate">{{ t('posts.add') }}</button>
    </div>

    <p v-if="loading" class="page__muted">{{ t('posts.loading') }}</p>
    <p v-if="error" class="page__error">{{ t('posts.error').replace('{status}', error.replace('HTTP ', '')) }}</p>

    <ul class="posts-list">
      <li v-for="post in posts" :key="post.id" class="posts-card">
        <strong>{{ post.title }}</strong>
        <span class="posts-type">{{ post.type }}</span>
        <p v-if="post.description">{{ post.description }}</p>
      </li>
    </ul>
  </section>
</template>

<style scoped lang="scss">
.page--posts { padding: 1rem; display: grid; gap: 0.8rem; }
.posts-create { display: grid; grid-template-columns: 170px 1fr auto; gap: 0.5rem; align-items: start; }
.posts-create input, .posts-create select, .posts-create textarea { padding: 0.5rem; border: 1px solid #7abf8a; border-radius: 0.5rem; background: #f1fbf3; }
.posts-create textarea { grid-column: 1 / span 2; }
.posts-create .btn { align-self: stretch; background: #1f9d55; border-color: #1f9d55; color: white; }
.posts-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem; }
.posts-card { border: 1px solid #7abf8a; background: #f6fff7; border-radius: 0.6rem; padding: 0.7rem; display: grid; gap: 0.3rem; }
.posts-card p { margin: 0; }
.posts-type { font-size: 0.8rem; color: #1f9d55; text-transform: uppercase; }
.page__muted { margin: 0; opacity: 0.8; }
.page__error { color: #b00020; margin: 0; }
</style>

