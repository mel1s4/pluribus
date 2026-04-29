<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import PostComposerAudienceTab from '../../organisms/PostComposerAudienceTab.vue'
import PostComposerContentTab from '../../organisms/PostComposerContentTab.vue'
import PostComposerMoreTab from '../../organisms/PostComposerMoreTab.vue'
import PostComposerScheduleTab from '../../organisms/PostComposerScheduleTab.vue'
import { usePostComposerForm } from '../../composables/usePostComposerForm'
import { t } from '../../i18n/i18n'
import {
  createPost,
  deletePost,
  fetchCalendars,
  fetchGroups,
  fetchPost,
  updatePost,
} from '../../services/contentApi'

const route = useRoute()
const router = useRouter()

const isEditRef = computed(() => route.name === 'posts-edit')
const {
  form,
  postId,
  dirty,
  markClean,
  resetForCreate,
  applyFromPost,
  buildPayload,
} = usePostComposerForm()

const activeTab = ref('content')
const calendars = ref([])
const groups = ref([])
const loadError = ref('')
const pageError = ref('')
const saving = ref(false)
const loadingPost = ref(false)
const discardDialogRef = ref(null)
const deleteDialogRef = ref(null)
const pendingRoute = ref(null)

function unwrapPost(data) {
  if (!data || typeof data !== 'object') return null
  if (data.post) return data.post
  return null
}

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

async function loadCalendarsAndGroups() {
  const [calRes, grpRes] = await Promise.all([fetchCalendars(), fetchGroups()])
  if (calRes.ok) calendars.value = unwrapList(calRes.data)
  else calendars.value = []
  if (grpRes.ok) groups.value = unwrapList(grpRes.data)
  else groups.value = []
}

async function loadPost() {
  const rawId = route.params.id
  const id = typeof rawId === 'string' ? rawId : String(rawId ?? '')
  if (!id) {
    loadError.value = t('posts.composerLoadError')
    return
  }
  loadingPost.value = true
  loadError.value = ''
  const res = await fetchPost(id)
  loadingPost.value = false
  if (!res.ok) {
    loadError.value = t('posts.composerLoadError')
    return
  }
  const post = unwrapPost(res.data)
  if (!post) {
    loadError.value = t('posts.composerLoadError')
    return
  }
  applyFromPost(post)
}

function initFromRoute() {
  loadError.value = ''
  pageError.value = ''
  activeTab.value = 'content'
  if (route.name === 'posts-edit') void loadPost()
  else resetForCreate()
}

function onBeforeUnload(e) {
  if (!dirty.value) return
  e.preventDefault()
  e.returnValue = ''
}

onBeforeRouteLeave((to) => {
  if (!dirty.value) return true
  pendingRoute.value = to.fullPath
  discardDialogRef.value?.showModal()
  return false
})

function closeDiscardDialog() {
  discardDialogRef.value?.close()
  pendingRoute.value = null
}

function confirmDiscardAndLeave() {
  const target = pendingRoute.value
  markClean()
  closeDiscardDialog()
  if (target) router.push(target)
}

function goBack() {
  router.push({ name: 'posts' })
}

function requestCancel() {
  if (!dirty.value) {
    goBack()
    return
  }
  pendingRoute.value = router.resolve({ name: 'posts' }).fullPath
  discardDialogRef.value?.showModal()
}

function onDiscardBackdrop(e) {
  if (e.target === discardDialogRef.value) closeDiscardDialog()
}

function onDeleteBackdrop(e) {
  if (e.target === deleteDialogRef.value) deleteDialogRef.value?.close()
}

async function onSave() {
  pageError.value = ''
  const built = buildPayload()
  if (!built.ok) {
    pageError.value = t(built.errorKey)
    return
  }
  saving.value = true
  try {
    if (isEditRef.value && postId.value != null) {
      const res = await updatePost(postId.value, built.payload)
      if (!res.ok) {
        pageError.value = t('posts.composerSaveError').replace('{status}', String(res.status))
        return
      }
    } else {
      const res = await createPost(built.payload)
      if (!res.ok) {
        pageError.value = t('posts.composerSaveError').replace('{status}', String(res.status))
        return
      }
    }
    markClean()
    await router.push({ name: 'posts' })
  } finally {
    saving.value = false
  }
}

async function confirmDelete() {
  if (postId.value == null) return
  saving.value = true
  pageError.value = ''
  const res = await deletePost(postId.value)
  saving.value = false
  if (!res.ok) {
    pageError.value = t('posts.composerSaveError').replace('{status}', String(res.status))
    deleteDialogRef.value?.close()
    return
  }
  markClean()
  deleteDialogRef.value?.close()
  await router.push({ name: 'posts' })
}

watch(
  () => [route.name, route.params.id],
  () => {
    initFromRoute()
  },
)

onMounted(() => {
  window.addEventListener('beforeunload', onBeforeUnload)
  void loadCalendarsAndGroups()
  initFromRoute()
})

onUnmounted(() => {
  window.removeEventListener('beforeunload', onBeforeUnload)
})
</script>

<template>
  <section class="post-composer-page">
    <header class="post-composer-page__header">
      <PageToolbarTitle route-key="posts">
        <Title tag="h1">
          {{ isEditRef ? t('posts.composerEditTitle') : t('posts.composerCreateTitle') }}
        </Title>
      </PageToolbarTitle>
    </header>

    <p v-if="loadError" class="post-composer-page__banner post-composer-page__banner--error">
      {{ loadError }}
    </p>
    <p v-else-if="loadingPost" class="post-composer-page__muted">{{ t('posts.composerLoading') }}</p>

    <template v-if="!loadError && !loadingPost">
      <div class="post-composer-page__card">
        <div class="post-composer-page__tabs" role="tablist" :aria-label="t('posts.composerTabsLabel')">
          <button
            type="button"
            role="tab"
            class="post-composer-page__tab"
            :class="{ 'is-active': activeTab === 'content' }"
            :aria-selected="activeTab === 'content'"
            @click="activeTab = 'content'"
          >
            {{ t('posts.composerTabContent') }}
          </button>
          <button
            type="button"
            role="tab"
            class="post-composer-page__tab"
            :class="{ 'is-active': activeTab === 'schedule' }"
            :aria-selected="activeTab === 'schedule'"
            @click="activeTab = 'schedule'"
          >
            {{ t('posts.composerTabSchedule') }}
          </button>
          <button
            type="button"
            role="tab"
            class="post-composer-page__tab"
            :class="{ 'is-active': activeTab === 'audience' }"
            :aria-selected="activeTab === 'audience'"
            @click="activeTab = 'audience'"
          >
            {{ t('posts.composerTabAudience') }}
          </button>
          <button
            type="button"
            role="tab"
            class="post-composer-page__tab"
            :class="{ 'is-active': activeTab === 'more' }"
            :aria-selected="activeTab === 'more'"
            @click="activeTab = 'more'"
          >
            {{ t('posts.composerTabMore') }}
          </button>
        </div>

        <div class="post-composer-page__panel">
          <PostComposerContentTab v-show="activeTab === 'content'" :form="form" />
          <PostComposerScheduleTab v-show="activeTab === 'schedule'" :form="form" />
          <PostComposerAudienceTab v-show="activeTab === 'audience'" :form="form" :groups="groups" />
          <PostComposerMoreTab v-show="activeTab === 'more'" :form="form" :calendars="calendars" />
        </div>
      </div>

      <p v-if="pageError" class="post-composer-page__banner post-composer-page__banner--error">{{ pageError }}</p>

      <footer class="post-composer-page__footer">
        <div class="post-composer-page__footerLeft">
          <Button v-if="isEditRef" type="button" variant="ghost" @click="deleteDialogRef?.showModal()">
            {{ t('posts.delete') }}
          </Button>
        </div>
        <div class="post-composer-page__footerRight">
          <Button type="button" variant="ghost" :disabled="saving" @click="requestCancel">
            {{ t('posts.cancel') }}
          </Button>
          <Button type="button" variant="primary" :disabled="saving || !form.title.trim()" @click="onSave">
            {{ saving ? t('posts.composerSaving') : t('posts.save') }}
          </Button>
        </div>
      </footer>
    </template>

    <dialog ref="discardDialogRef" class="post-composer-page__dialog" @click="onDiscardBackdrop">
      <div class="post-composer-page__dialogPanel" @click.stop>
        <h2 class="post-composer-page__dialogTitle">{{ t('posts.composerDiscardTitle') }}</h2>
        <p class="post-composer-page__dialogText">{{ t('posts.composerDiscardBody') }}</p>
        <div class="post-composer-page__dialogActions">
          <Button type="button" variant="ghost" @click="closeDiscardDialog">{{ t('posts.composerDiscardStay') }}</Button>
          <Button type="button" variant="primary" @click="confirmDiscardAndLeave">
            {{ t('posts.composerDiscardLeave') }}
          </Button>
        </div>
      </div>
    </dialog>

    <dialog ref="deleteDialogRef" class="post-composer-page__dialog" @click="onDeleteBackdrop">
      <div class="post-composer-page__dialogPanel" @click.stop>
        <h2 class="post-composer-page__dialogTitle">{{ t('posts.composerDeleteTitle') }}</h2>
        <p class="post-composer-page__dialogText">{{ t('posts.deleteConfirm') }}</p>
        <div class="post-composer-page__dialogActions">
          <Button type="button" variant="ghost" :disabled="saving" @click="deleteDialogRef?.close()">
            {{ t('posts.cancel') }}
          </Button>
          <Button type="button" variant="primary" :disabled="saving" @click="confirmDelete">
            {{ t('posts.delete') }}
          </Button>
        </div>
      </div>
    </dialog>
  </section>
</template>

<style scoped lang="scss">
.post-composer-page {
  max-width: 720px;
  margin: 0 auto;
  padding: 1rem 1rem 5rem;
  display: grid;
  gap: 1rem;
}

.post-composer-page__header {
  margin: 0;
}

.post-composer-page__muted {
  margin: 0;
  opacity: 0.75;
}

.post-composer-page__banner {
  margin: 0;
  padding: 0.65rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.9rem;
}

.post-composer-page__banner--error {
  background: rgba(220, 38, 38, 0.1);
  color: #b91c1c;
}

.post-composer-page__card {
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.75rem;
  background: var(--bg, #fff);
  overflow: hidden;
}

.post-composer-page__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.2rem;
  padding: 0.35rem;
  background: var(--surface-2, #f3f4f6);
  border-bottom: 1px solid var(--border, #e5e7eb);
}

.post-composer-page__tab {
  padding: 0.4rem 0.75rem;
  border: none;
  border-radius: 0.45rem;
  background: transparent;
  font: inherit;
  font-size: 0.82rem;
  font-weight: 500;
  cursor: pointer;
  color: var(--text-muted, #6b7280);

  &.is-active {
    background: var(--bg, #fff);
    color: var(--text, #111827);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  }
}

.post-composer-page__panel {
  padding: 1rem 1.1rem 1.25rem;
}

.post-composer-page__footer {
  position: sticky;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.75rem 0;
  margin: 0 -0.25rem;
  background: linear-gradient(to top, var(--bg, #fff) 70%, transparent);
}

.post-composer-page__footerRight {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-left: auto;
}

.post-composer-page__dialog {
  margin: auto;
  padding: 0;
  max-width: min(24rem, calc(100vw - 2rem));
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.75rem;
  background: var(--bg, #fff);
  color: inherit;
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
}

.post-composer-page__dialog::backdrop {
  background: rgba(15, 23, 42, 0.45);
}

.post-composer-page__dialogPanel {
  padding: 1rem 1.1rem;
  display: grid;
  gap: 0.75rem;
}

.post-composer-page__dialogTitle {
  margin: 0;
  font-size: 1.05rem;
}

.post-composer-page__dialogText {
  margin: 0;
  font-size: 0.9rem;
  opacity: 0.9;
}

.post-composer-page__dialogActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
}
</style>
