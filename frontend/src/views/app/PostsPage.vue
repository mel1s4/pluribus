<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import Icon from '../../atoms/Icon.vue'
import { t } from '../../i18n/i18n'
import { sessionUser } from '../../composables/useSession'
import { deletePost, fetchPosts } from '../../services/contentApi'

const router = useRouter()
const posts = ref([])
const loading = ref(false)
const error = ref('')

const deleteDialogRef = ref(null)
const deleteTarget = ref(null)

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

function postTypeLabel(type) {
  if (type === 'event') return t('posts.typeEvent')
  if (type === 'announcement') return t('posts.typeAnnouncement')
  if (type === 'info') return t('posts.typeInfo')
  return String(type || '')
}

function visibilityLabel(scope) {
  if (scope === 'community') return t('calendar.community')
  if (scope === 'group') return t('calendar.group')
  return t('calendar.private')
}

function scheduleChip(post) {
  if (!post.start_at) return ''
  try {
    const s = new Date(post.start_at)
    if (Number.isNaN(s.getTime())) return ''
    const opts = { dateStyle: 'short', timeStyle: 'short' }
    return s.toLocaleString(undefined, opts)
  } catch {
    return ''
  }
}

function cardBody(post) {
  if (post.description) return post.description
  const md = typeof post.content_markdown === 'string' ? post.content_markdown.trim() : ''
  if (!md) return ''
  const line = md.split(/\r?\n/).find((l) => l.trim()) || md
  return line.length > 200 ? `${line.slice(0, 200)}…` : line
}

function goCreate() {
  router.push({ name: 'posts-new' })
}

function goEdit(post) {
  closeKebabFor(post.id)
  router.push({ name: 'posts-edit', params: { id: String(post.id) } })
}

function openDeleteDialog(post) {
  closeKebabFor(post.id)
  deleteTarget.value = post
  deleteDialogRef.value?.showModal()
}

function closeDeleteDialog() {
  deleteDialogRef.value?.close()
  deleteTarget.value = null
}

function onDeleteBackdrop(e) {
  if (e.target === deleteDialogRef.value) closeDeleteDialog()
}

async function confirmDelete() {
  const post = deleteTarget.value
  if (!post) return
  const res = await deletePost(post.id)
  if (!res.ok) {
    error.value = `HTTP ${res.status}`
    closeDeleteDialog()
    return
  }
  error.value = ''
  closeDeleteDialog()
  await load()
}

onMounted(load)
</script>

<template>
  <section class="page page--posts">
    <header class="page__header page__header--row">
      <div>
        <PageToolbarTitle route-key="posts">
          <Title tag="h1">{{ t('posts.title') }}</Title>
        </PageToolbarTitle>
        <p class="page__muted">{{ t('posts.intro') }}</p>
      </div>
      <Button variant="primary" class="page__createBtn" @click="goCreate">{{ t('posts.add') }}</Button>
    </header>

    <p v-if="loading" class="page__muted">{{ t('posts.loading') }}</p>
    <p v-if="error" class="page__error">{{ t('posts.error').replace('{status}', error.replace('HTTP ', '')) }}</p>

    <ul class="posts-list">
      <li v-for="post in posts" :key="post.id" class="posts-card">
        <div class="posts-card__header">
          <div class="posts-card__headline">
            <strong class="posts-card__title">{{ post.title }}</strong>
            <div class="posts-card__chips">
              <span class="posts-card__chip">{{ postTypeLabel(post.type) }}</span>
              <span class="posts-card__chip posts-card__chip--muted">{{ visibilityLabel(post.visibility_scope) }}</span>
              <span v-if="scheduleChip(post)" class="posts-card__chip posts-card__chip--muted">{{ scheduleChip(post) }}</span>
            </div>
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
                @click="goEdit(post)"
              >
                <Icon class="posts-card__kebabGlyph" name="pen" aria-hidden="true" />
                {{ t('posts.edit') }}
              </button>
              <button
                type="button"
                class="posts-card__kebabItem posts-card__kebabItem--danger"
                role="menuitem"
                @click="openDeleteDialog(post)"
              >
                <Icon class="posts-card__kebabGlyph" name="trash" aria-hidden="true" />
                {{ t('posts.delete') }}
              </button>
            </div>
          </details>
        </div>
        <p v-if="cardBody(post)" class="posts-card__description">{{ cardBody(post) }}</p>
      </li>
    </ul>

    <dialog ref="deleteDialogRef" class="posts-page__dialog" @click="onDeleteBackdrop">
      <div class="posts-page__dialogPanel" @click.stop>
        <h2 class="posts-page__dialogTitle">{{ t('posts.composerDeleteTitle') }}</h2>
        <p class="posts-page__dialogText">{{ t('posts.deleteConfirm') }}</p>
        <div class="posts-page__dialogActions">
          <Button type="button" variant="ghost" @click="closeDeleteDialog">{{ t('posts.cancel') }}</Button>
          <Button type="button" variant="primary" @click="confirmDelete">{{ t('posts.delete') }}</Button>
        </div>
      </div>
    </dialog>
  </section>
</template>

<style scoped lang="scss">
.page--posts {
  padding: 1rem;
  display: grid;
  gap: 0.8rem;
}

.page__header--row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}

.page__createBtn {
  flex-shrink: 0;
}

.posts-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.5rem;
}

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
  gap: 0.35rem;
  min-width: 0;
}

.posts-card__title {
  word-break: break-word;
}

.posts-card__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.posts-card__chip {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  padding: 0.2rem 0.45rem;
  border-radius: 0.35rem;
  background: rgba(31, 157, 85, 0.15);
  color: #166534;
}

.posts-card__chip--muted {
  text-transform: none;
  font-weight: 500;
  background: var(--surface-2, #eef2f7);
  color: var(--text-muted, #64748b);
}

html[data-theme='dark'] .posts-card__chip--muted {
  background: rgba(255, 255, 255, 0.08);
  color: var(--text-muted, #94a3b8);
}

.posts-card__description {
  margin: 0;
}

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
  gap: 0.75rem;
}

.posts-page__dialogTitle {
  margin: 0;
  font-size: 1.1rem;
}

.posts-page__dialogText {
  margin: 0;
  font-size: 0.9rem;
  opacity: 0.9;
}

.posts-page__dialogActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.page__muted {
  margin: 0;
  opacity: 0.8;
}

.page__error {
  color: #b00020;
  margin: 0;
}
</style>
