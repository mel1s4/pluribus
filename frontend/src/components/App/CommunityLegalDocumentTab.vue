<script setup>
import { computed, ref, watch } from 'vue'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import { useActiveCommunity } from '../../composables/useActiveCommunity'
import { sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { fetchCommunity, patchCommunityLegalDocuments } from '../../services/communityApi.js'

const props = defineProps({
  kind: {
    type: String,
    required: true,
    validator: (v) => v === 'terms' || v === 'privacy',
  },
})

const { activeCommunitySlug } = useActiveCommunity()

function communityRequestOptions() {
  const s = activeCommunitySlug.value
  return s && s.trim() !== '' ? { headers: { 'X-Community-Slug': s.trim() } } : {}
}

const apiField = computed(() => (props.kind === 'terms' ? 'terms_markdown' : 'privacy_policy_markdown'))

const canEditLegal = computed(() => {
  const u = sessionUser.value
  if (!u) return false
  if (u.is_root) return true
  return u.user_type === 'admin'
})

const draft = ref('')
const bodyMode = ref('write')
const loadError = ref('')
const loading = ref(true)
const saveError = ref('')
const saveLoading = ref(false)

function apiErrorMessage(data, status, fallback) {
  if (data && typeof data === 'object') {
    if (typeof data.message === 'string' && data.message.length) {
      return data.message
    }
    if (data.errors && typeof data.errors === 'object') {
      return Object.values(data.errors)
        .flat()
        .map(String)
        .join(' ')
    }
  }
  return fallback.replace('{status}', String(status))
}

async function load() {
  loadError.value = ''
  loading.value = true
  const { ok, status, data } = await fetchCommunity(communityRequestOptions())
  loading.value = false
  if (!ok) {
    loadError.value = apiErrorMessage(data, status, t('communitySettings.legalLoadError'))
    return
  }
  const c = data?.community
  const raw = c && typeof c === 'object' ? c[apiField.value] : null
  draft.value = typeof raw === 'string' ? raw : ''
}

load()

watch(activeCommunitySlug, () => {
  void load()
})

const introKey = computed(() =>
  props.kind === 'terms' ? 'communitySettings.legalIntroTerms' : 'communitySettings.legalIntroPrivacy',
)
const placeholderKey = computed(() =>
  props.kind === 'terms'
    ? 'communitySettings.legalPlaceholderTerms'
    : 'communitySettings.legalPlaceholderPrivacy',
)

async function onSave() {
  if (!canEditLegal.value) return
  saveError.value = ''
  saveLoading.value = true
  const trimmed = draft.value.trim()
  const payload = { [apiField.value]: trimmed === '' ? null : draft.value }
  const { ok, status, data } = await patchCommunityLegalDocuments(payload, communityRequestOptions())
  saveLoading.value = false
  if (!ok) {
    saveError.value = apiErrorMessage(data, status, t('communitySettings.legalSaveError'))
    return
  }
  const c = data?.community
  const raw = c && typeof c === 'object' ? c[apiField.value] : null
  draft.value = typeof raw === 'string' ? raw : ''
}
</script>

<template>
  <div class="community-legal-document-tab">
    <p class="community-legal-document-tab__intro">{{ t(introKey) }}</p>
    <p v-if="loadError" class="community-legal-document-tab__error" role="alert">
      {{ loadError }}
    </p>
    <p v-else-if="loading" class="community-legal-document-tab__muted">{{ t('communitySettings.loading') }}</p>

    <Card v-else class="community-legal-document-tab__panel">
      <p v-if="!canEditLegal" class="community-legal-document-tab__hint">
        {{ t('communitySettings.legalReadOnlyHint') }}
      </p>
      <p class="community-legal-document-tab__markdown-hint">{{ t('communitySettings.legalMarkdownHint') }}</p>

      <template v-if="canEditLegal">
        <div class="community-legal-document-tab__subtabs" role="tablist" :aria-label="t('posts.composerBodyMode')">
          <button
            type="button"
            role="tab"
            class="community-legal-document-tab__subtab"
            :class="{ 'community-legal-document-tab__subtab--active': bodyMode === 'write' }"
            :aria-selected="bodyMode === 'write'"
            @click="bodyMode = 'write'"
          >
            {{ t('communitySettings.legalWrite') }}
          </button>
          <button
            type="button"
            role="tab"
            class="community-legal-document-tab__subtab"
            :class="{ 'community-legal-document-tab__subtab--active': bodyMode === 'preview' }"
            :aria-selected="bodyMode === 'preview'"
            @click="bodyMode = 'preview'"
          >
            {{ t('communitySettings.legalPreview') }}
          </button>
        </div>
        <textarea
          v-show="bodyMode === 'write'"
          :id="`community-legal-${kind}`"
          v-model="draft"
          class="community-legal-document-tab__textarea"
          rows="14"
          :placeholder="t(placeholderKey)"
        />
        <pre
          v-show="bodyMode === 'preview'"
          class="community-legal-document-tab__preview"
          tabindex="0"
        >{{ draft || t('posts.composerPreviewEmpty') }}</pre>
        <p v-if="saveError" class="community-legal-document-tab__error" role="alert">
          {{ saveError }}
        </p>
        <div class="community-legal-document-tab__actions">
          <Button type="button" :disabled="saveLoading" @click="onSave">
            {{ t('communitySettings.legalSave') }}
          </Button>
        </div>
      </template>
      <template v-else>
        <pre v-if="draft.trim() !== ''" class="community-legal-document-tab__preview" tabindex="0">{{ draft }}</pre>
        <p v-else class="community-legal-document-tab__muted">{{ t('communitySettings.legalEmptyReadOnly') }}</p>
      </template>
    </Card>
  </div>
</template>

<style lang="scss" scoped>
.community-legal-document-tab {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.community-legal-document-tab__intro {
  margin: 0;
  opacity: 0.88;
  max-width: 42rem;
  line-height: 1.5;
  font-size: 0.95rem;
}

.community-legal-document-tab__muted {
  margin: 0;
  opacity: 0.85;
}

.community-legal-document-tab__hint {
  margin: 0 0 0.5rem;
  font-size: 0.9rem;
  opacity: 0.9;
}

.community-legal-document-tab__markdown-hint {
  margin: 0 0 0.75rem;
  font-size: 0.82rem;
  opacity: 0.85;
  line-height: 1.4;
}

.community-legal-document-tab__error {
  margin: 0;
  color: var(--danger, #b91c1c);
  font-size: 0.9rem;
}

.community-legal-document-tab__panel {
  padding: 1.1rem 1.15rem;
}

.community-legal-document-tab__subtabs {
  display: inline-flex;
  gap: 0.2rem;
  background: var(--surface-2, #f3f4f6);
  border-radius: 0.5rem;
  padding: 0.2rem;
  margin-bottom: 0.5rem;
}

.community-legal-document-tab__subtab {
  padding: 0.3rem 0.75rem;
  border: none;
  background: transparent;
  border-radius: 0.35rem;
  font: inherit;
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
  color: var(--text-muted, #6b7280);
}

.community-legal-document-tab__subtab--active {
  background: var(--bg, #fff);
  color: var(--text, #111827);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.community-legal-document-tab__textarea {
  padding: 0.55rem 0.65rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  background: var(--bg, #fff);
  color: inherit;
  font: inherit;
  width: 100%;
  box-sizing: border-box;
  resize: vertical;
  min-height: 10rem;
}

.community-legal-document-tab__preview {
  margin: 0;
  min-height: 12rem;
  padding: 0.65rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  background: var(--surface-2, #f9fafb);
  font-family: ui-monospace, monospace;
  font-size: 0.85rem;
  white-space: pre-wrap;
  word-break: break-word;
  overflow: auto;
}

.community-legal-document-tab__actions {
  margin-top: 0.85rem;
}
</style>
