<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import Icon from '../../atoms/Icon.vue'
import { t } from '../../i18n/i18n'
import { sessionUser } from '../../composables/useSession'
import { createPost, deletePost, fetchPosts, updatePost } from '../../services/contentApi'

const posts = ref([])
const loading = ref(false)
const error = ref('')
const title = ref('')
const description = ref('')
const type = ref('info')

const currentUserId = computed(() => Number(sessionUser.value?.id || 0))

/** @type {Record<string, HTMLDetailsElement | null>} */
const kebabRefs = {}

function setKebabRef(id, el) {
  if (el) kebabRefs[String(id)] = el
  else delete kebabRefs[String(id)]
}

function closeKebabFor(id) {
  const el = kebabRefs[String(id)]
  if (el) el.open = false
}

function isPostAuthor(post) {
  return currentUserId.value > 0 && Number(post.author_id) === currentUserId.value
}

const editDialogRef = ref(null)
const editTargetId = ref(null)
const editSaving = ref(false)
const editForm = reactive({
  type: 'info',
  title: '',
  description: '',
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

function onEditDialogBackdrop(e) {
  if (e.target === editDialogRef.value) editDialogRef.value?.close()
}

async function openEditPost(post) {
  closeKebabFor(post.id)
  editTargetId.value = post.id
  editForm.type = post.type || 'info'
  editForm.title = post.title || ''
  editForm.description = post.description || ''
  await nextTick()
  editDialogRef.value?.showModal()
}

async function submitEditPost() {
  const id = editTargetId.value
  if (id == null) return
  const trimmedTitle = editForm.title.trim()
  if (!trimmedTitle) return
  editSaving.value = true
  const res = await updatePost(id, {
    type: editForm.type,
    title: trimmedTitle,
    description: editForm.description.trim() || null,
  })
  editSaving.value = false
  if (!res.ok) {
    error.value = `HTTP ${res.status}`
    return
  }
  editDialogRef.value?.close()
  editTargetId.value = null
  error.value = ''
  await load()
}

async function onDeletePost(post) {
  closeKebabFor(post.id)
  if (!window.confirm(t('posts.deleteConfirm'))) return
  const res = await deletePost(post.id)
  if (!res.ok) {
    error.value = `HTTP ${res.status}`
    return
  }
  error.value = ''
  await load()
}

onMounted(load)
</script>

<template>
  <section class="page page--posts">
    <header class="page__header">
      <PageToolbarTitle route-key="posts">
        <Title tag="h1">{{ t('posts.title') }}</Title>
      </PageToolbarTitle>
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
      <Button variant="primary" @click="onCreate">{{ t('posts.add') }}</Button>
    </div>

    <p v-if="loading" class="page__muted">{{ t('posts.loading') }}</p>
    <p v-if="error" class="page__error">{{ t('posts.error').replace('{status}', error.replace('HTTP ', '')) }}</p>

    <dialog ref="editDialogRef" class="posts-page__dialog" @click="onEditDialogBackdrop">
      <div class="posts-page__dialogPanel" @click.stop>
        <h2 class="posts-page__dialogTitle">{{ t('posts.editPost') }}</h2>
        <div class="posts-page__dialogFields">
          <label class="posts-page__field">
            <span>{{ t('posts.typeLabel') }}</span>
            <select v-model="editForm.type">
              <option value="event">{{ t('posts.typeEvent') }}</option>
              <option value="announcement">{{ t('posts.typeAnnouncement') }}</option>
              <option value="info">{{ t('posts.typeInfo') }}</option>
            </select>
          </label>
          <label class="posts-page__field">
            <span>{{ t('posts.titleLabel') }}</span>
            <input v-model="editForm.title" :placeholder="t('posts.titlePlaceholder')" />
          </label>
          <label class="posts-page__field">
            <span>{{ t('posts.descriptionLabel') }}</span>
            <textarea v-model="editForm.description" rows="3" :placeholder="t('posts.descriptionPlaceholder')" />
          </label>
        </div>
        <div class="posts-page__dialogActions">
          <Button type="button" variant="ghost" :disabled="editSaving" @click="editDialogRef?.close()">
            {{ t('posts.cancel') }}
          </Button>
          <Button
            type="button"
            variant="primary"
            :disabled="editSaving || !editForm.title.trim()"
            @click="submitEditPost"
          >
            {{ t('posts.save') }}
          </Button>
        </div>
      </div>
    </dialog>

    <ul class="posts-list">
      <li v-for="post in posts" :key="post.id" class="posts-card">
        <div class="posts-card__header">
          <div class="posts-card__headline">
            <strong class="posts-card__title">{{ post.title }}</strong>
            <span class="posts-card__type">{{ post.type }}</span>
          </div>
          <details
            v-if="isPostAuthor(post)"
            class="posts-card__kebab"
            :ref="(el) => setKebabRef(post.id, el)"
          >
            <summary
              class="posts-card__kebabTrigger"
              :aria-label="t('posts.actionsMenu')"
            >
              <Icon class="posts-card__kebabGlyph" name="ellipsis-vertical" aria-hidden="true" />
            </summary>
            <div class="posts-card__kebabMenu" role="menu" @click.stop>
              <button
                type="button"
                class="posts-card__kebabItem"
                role="menuitem"
                @click="openEditPost(post)"
              >
                <Icon class="posts-card__kebabGlyph" name="pen" aria-hidden="true" />
                {{ t('posts.edit') }}
              </button>
              <button
                type="button"
                class="posts-card__kebabItem posts-card__kebabItem--danger"
                role="menuitem"
                @click="onDeletePost(post)"
              >
                <Icon class="posts-card__kebabGlyph" name="trash" aria-hidden="true" />
                {{ t('posts.delete') }}
              </button>
            </div>
          </details>
        </div>
        <p v-if="post.description" class="posts-card__description">{{ post.description }}</p>
      </li>
    </ul>
  </section>
</template>

<style scoped lang="scss">
.page--posts { padding: 1rem; display: grid; gap: 0.8rem; }
.posts-create { display: grid; grid-template-columns: 170px 1fr auto; gap: 0.5rem; align-items: start; }
.posts-create input, .posts-create select, .posts-create textarea { padding: 0.5rem; border: 1px solid #7abf8a; border-radius: 0.5rem; background: #f1fbf3; }
.posts-create textarea { grid-column: 1 / span 2; }
.posts-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem; }
.posts-card {
  border: 1px solid #7abf8a;
  background: #f6fff7;
  border-radius: 0.6rem;
  padding: 0.7rem;
  display: grid;
  gap: 0.3rem;
}
.posts-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
}
.posts-card__headline {
  display: grid;
  gap: 0.2rem;
  min-width: 0;
}
.posts-card__title { word-break: break-word; }
.posts-card__description { margin: 0; }
.posts-card__type { font-size: 0.8rem; color: #1f9d55; text-transform: uppercase; }

.posts-card__kebab {
  position: relative;
  flex-shrink: 0;
  list-style: none;
}
.posts-card__kebabTrigger {
  list-style: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  margin: 0;
  padding: 0;
  border: none;
  border-radius: 0.35rem;
  background: transparent;
  color: inherit;
  cursor: pointer;
}
.posts-card__kebabTrigger::-webkit-details-marker {
  display: none;
}
.posts-card__kebabTrigger:hover {
  background: var(--btn-bg);
}
.posts-card__kebabGlyph {
  font-size: 0.95rem;
}
.posts-card__kebabMenu {
  position: absolute;
  right: 0;
  top: 100%;
  z-index: 3;
  margin-top: 0.2rem;
  min-width: 11rem;
  padding: 0.35rem;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}
html[data-theme='dark'] .posts-card__kebabMenu {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.45);
}
.posts-card__kebabItem {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  width: 100%;
  margin: 0;
  padding: 0.45rem 0.55rem;
  text-align: left;
  border: none;
  border-radius: 0.35rem;
  background: transparent;
  color: inherit;
  font: inherit;
  font-size: 0.9rem;
  cursor: pointer;
}
.posts-card__kebabItem:hover {
  background: var(--btn-bg-hover);
}
.posts-card__kebabItem--danger:hover {
  background: rgba(220, 38, 38, 0.12);
  color: #dc2626;
}

.posts-page__dialog {
  margin: auto;
  padding: 0;
  max-width: min(26rem, calc(100vw - 2rem));
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  background: var(--bg);
  color: var(--text);
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
}
.posts-page__dialog::backdrop {
  background: rgba(15, 23, 42, 0.45);
}
html[data-theme='dark'] .posts-page__dialog::backdrop {
  background: rgba(0, 0, 0, 0.55);
}
.posts-page__dialogPanel {
  padding: 1rem 1.1rem;
  display: grid;
  gap: 1rem;
}
.posts-page__dialogTitle {
  margin: 0;
  font-size: 1.1rem;
}
.posts-page__dialogFields {
  display: grid;
  gap: 0.75rem;
}
.posts-page__field {
  display: grid;
  gap: 0.35rem;
  font-size: 0.9rem;
}
.posts-page__field span {
  font-weight: 600;
}
.posts-page__field input,
.posts-page__field select,
.posts-page__field textarea {
  padding: 0.5rem;
  border: 1px solid #7abf8a;
  border-radius: 0.5rem;
  background: #f1fbf3;
  color: inherit;
  font: inherit;
}
html[data-theme='dark'] .posts-page__field input,
html[data-theme='dark'] .posts-page__field select,
html[data-theme='dark'] .posts-page__field textarea {
  background: rgba(31, 157, 85, 0.12);
  border-color: rgba(122, 191, 138, 0.45);
}
.posts-page__dialogActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.page__muted { margin: 0; opacity: 0.8; }
.page__error { color: #b00020; margin: 0; }
</style>
