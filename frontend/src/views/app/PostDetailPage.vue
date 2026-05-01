<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Icon from '../../atoms/Icon.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import PostDetailMainBlocks from '../../organisms/PostDetailMainBlocks.vue'
import PostDetailSidebar from '../../organisms/PostDetailSidebar.vue'
import { t } from '../../i18n/i18n'
import { sessionUser } from '../../composables/useSession'
import { deletePost, fetchPost } from '../../services/contentApi'

const route = useRoute()
const router = useRouter()

const post = ref(null)
const loading = ref(true)
const error = ref('')
const deleteDialogRef = ref(null)

const postId = computed(() => {
  const raw = route.params.id
  return typeof raw === 'string' ? raw : String(raw ?? '')
})

const currentUserId = computed(() => Number(sessionUser.value?.id || 0))

const isAuthor = computed(
  () => post.value && currentUserId.value > 0 && Number(post.value.author_id) === currentUserId.value,
)

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

function formatScheduleRange() {
  const p = post.value
  if (!p || !p.start_at) return ''
  try {
    const s = new Date(p.start_at)
    if (Number.isNaN(s.getTime())) return ''
    const opts = { dateStyle: 'medium', timeStyle: 'short' }
    if (!p.end_at) return s.toLocaleString(undefined, opts)
    const e = new Date(p.end_at)
    if (Number.isNaN(e.getTime())) return s.toLocaleString(undefined, opts)
    return `${s.toLocaleString(undefined, opts)} — ${e.toLocaleString(undefined, opts)}`
  } catch {
    return ''
  }
}

function formatUpdated() {
  const p = post.value
  if (!p?.updated_at) return ''
  try {
    const d = new Date(p.updated_at)
    if (Number.isNaN(d.getTime())) return ''
    return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
  } catch {
    return ''
  }
}

async function load() {
  const id = postId.value
  if (!id) return
  loading.value = true
  error.value = ''
  post.value = null
  const res = await fetchPost(id)
  loading.value = false
  if (!res.ok) {
    error.value = t('posts.detailLoadError').replace('{status}', String(res.status))
    return
  }
  const payload = res.data && typeof res.data === 'object' && res.data.post ? res.data.post : null
  post.value = payload
}

function goBack() {
  router.push({ name: 'posts' })
}

function goEdit() {
  router.push({ name: 'posts-edit', params: { id: postId.value } })
}

async function confirmDelete() {
  const id = postId.value
  if (!id) return
  const res = await deletePost(id)
  deleteDialogRef.value?.close()
  if (!res.ok) {
    error.value = t('posts.detailLoadError').replace('{status}', String(res.status))
    return
  }
  await router.push({ name: 'posts' })
}

function onDeleteBackdrop(e) {
  if (e.target === deleteDialogRef.value) deleteDialogRef.value?.close()
}

watch(postId, () => {
  void load()
})

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="post-detail-page">
    <header class="post-detail-page__toolbar">
      <Button type="button" variant="ghost" class="post-detail-page__back" @click="goBack">
        <Icon name="arrow-left" aria-hidden="true" />
        {{ t('posts.detailBack') }}
      </Button>
      <div v-if="isAuthor" class="post-detail-page__authorActions">
        <Button type="button" variant="ghost" @click="goEdit">{{ t('posts.edit') }}</Button>
        <Button type="button" variant="ghost" @click="deleteDialogRef?.showModal()">
          {{ t('posts.delete') }}
        </Button>
      </div>
    </header>

    <p v-if="loading" class="post-detail-page__muted">{{ t('posts.detailLoading') }}</p>
    <p v-else-if="error" class="post-detail-page__error">{{ error }}</p>

    <template v-else-if="post">
      <PageToolbarTitle route-key="posts">
        <Title tag="h1" class="post-detail-page__title">{{ post.title }}</Title>
      </PageToolbarTitle>

      <div class="post-detail-page__chips" aria-label="Post meta">
        <span class="post-detail-page__chip">{{ postTypeLabel(post.type) }}</span>
        <span class="post-detail-page__chip post-detail-page__chip--muted">
          {{ visibilityLabel(post.visibility_scope) }}
        </span>
        <span
          v-if="formatScheduleRange()"
          class="post-detail-page__chip post-detail-page__chip--muted"
        >{{ formatScheduleRange() }}</span>
      </div>

      <div class="post-detail-page__grid">
        <PostDetailMainBlocks :post="post" />
        <PostDetailSidebar
          :post-type-label="postTypeLabel(post.type)"
          :updated-display="formatUpdated() || '—'"
        />
      </div>
    </template>

    <dialog ref="deleteDialogRef" class="post-detail-page__dialog" @click="onDeleteBackdrop">
      <div class="post-detail-page__dialogPanel" @click.stop>
        <h2 class="post-detail-page__dialogTitle">{{ t('posts.composerDeleteTitle') }}</h2>
        <p class="post-detail-page__dialogText">{{ t('posts.deleteConfirm') }}</p>
        <div class="post-detail-page__dialogActions">
          <Button type="button" variant="ghost" @click="deleteDialogRef?.close()">{{ t('posts.cancel') }}</Button>
          <Button type="button" variant="primary" @click="confirmDelete">{{ t('posts.delete') }}</Button>
        </div>
      </div>
    </dialog>
  </section>
</template>

<style scoped lang="scss">
.post-detail-page {
  max-width: 960px;
  margin: 0 auto;
  padding: 1rem 1rem 3rem;
  display: grid;
  gap: 1rem;
}

.post-detail-page__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.post-detail-page__back {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.post-detail-page__authorActions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.post-detail-page__title {
  margin: 0;
  word-break: break-word;
}

.post-detail-page__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.post-detail-page__chip {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  padding: 0.2rem 0.45rem;
  border-radius: 0.35rem;
  background: rgba(31, 157, 85, 0.15);
  color: #166534;
}

.post-detail-page__chip--muted {
  text-transform: none;
  font-weight: 500;
  background: var(--surface-2, #eef2f7);
  color: var(--text-muted, #64748b);
}

html[data-theme='dark'] .post-detail-page__chip--muted {
  background: rgba(255, 255, 255, 0.08);
  color: var(--text-muted, #94a3b8);
}

.post-detail-page__grid {
  display: grid;
  gap: 1rem;
  align-items: start;

  @media (min-width: 840px) {
    grid-template-columns: 1fr min(280px, 32%);
  }
}

.post-detail-page__muted {
  margin: 0;
  opacity: 0.8;
}

.post-detail-page__error {
  margin: 0;
  color: #b00020;
}

.post-detail-page__dialog {
  margin: auto;
  padding: 0;
  max-width: min(26rem, calc(100vw - 2rem));
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  background: var(--bg);
  color: var(--text);
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
}

.post-detail-page__dialog::backdrop {
  background: rgba(15, 23, 42, 0.45);
}

.post-detail-page__dialogPanel {
  padding: 1rem 1.1rem;
  display: grid;
  gap: 0.75rem;
}

.post-detail-page__dialogTitle {
  margin: 0;
  font-size: 1.1rem;
}

.post-detail-page__dialogText {
  margin: 0;
  font-size: 0.9rem;
  opacity: 0.9;
}

.post-detail-page__dialogActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
}
</style>
