<script setup>
import { ref } from 'vue'
import { t } from '../i18n/i18n'

defineProps({
  form: { type: Object, required: true },
})

const bodyMode = ref('write')
</script>

<template>
  <div class="post-composer-content">
    <div class="post-composer-content__types" role="group" :aria-label="t('posts.composerTypeLabel')">
      <button
        type="button"
        class="post-composer-content__pill"
        :class="{ 'is-active': form.type === 'event' }"
        @click="form.type = 'event'"
      >
        {{ t('posts.typeEvent') }}
      </button>
      <button
        type="button"
        class="post-composer-content__pill"
        :class="{ 'is-active': form.type === 'announcement' }"
        @click="form.type = 'announcement'"
      >
        {{ t('posts.typeAnnouncement') }}
      </button>
      <button
        type="button"
        class="post-composer-content__pill"
        :class="{ 'is-active': form.type === 'info' }"
        @click="form.type = 'info'"
      >
        {{ t('posts.typeInfo') }}
      </button>
    </div>

    <label class="post-composer-content__block">
      <span class="post-composer-content__label">{{ t('posts.titleLabel') }}</span>
      <input
        v-model="form.title"
        class="post-composer-content__input post-composer-content__input--title"
        type="text"
        maxlength="255"
        :placeholder="t('posts.titlePlaceholder')"
      />
    </label>

    <div class="post-composer-content__block">
      <div class="post-composer-content__subtabs" role="tablist" :aria-label="t('posts.composerBodyMode')">
        <button
          type="button"
          role="tab"
          class="post-composer-content__subtab"
          :class="{ 'is-active': bodyMode === 'write' }"
          :aria-selected="bodyMode === 'write'"
          @click="bodyMode = 'write'"
        >
          {{ t('posts.composerWrite') }}
        </button>
        <button
          type="button"
          role="tab"
          class="post-composer-content__subtab"
          :class="{ 'is-active': bodyMode === 'preview' }"
          :aria-selected="bodyMode === 'preview'"
          @click="bodyMode = 'preview'"
        >
          {{ t('posts.composerPreview') }}
        </button>
      </div>
      <textarea
        v-show="bodyMode === 'write'"
        v-model="form.content_markdown"
        class="post-composer-content__textarea"
        rows="10"
        :placeholder="t('posts.composerBodyPlaceholder')"
      />
      <pre v-show="bodyMode === 'preview'" class="post-composer-content__preview" tabindex="0">{{ form.content_markdown || t('posts.composerPreviewEmpty') }}</pre>
    </div>

    <div class="post-composer-content__summaryRow">
      <button
        type="button"
        class="post-composer-content__linkish"
        @click="form.showSummary = !form.showSummary"
      >
        {{ form.showSummary ? t('posts.composerHideSummary') : t('posts.composerAddSummary') }}
      </button>
    </div>
    <label v-if="form.showSummary" class="post-composer-content__block">
      <span class="post-composer-content__label">{{ t('posts.composerSummaryLabel') }}</span>
      <textarea
        v-model="form.description"
        class="post-composer-content__textarea"
        rows="2"
        :placeholder="t('posts.descriptionPlaceholder')"
      />
    </label>

    <label class="post-composer-content__block">
      <span class="post-composer-content__label">{{ t('posts.composerTagsLabel') }}</span>
      <input
        v-model="form.tagsInput"
        class="post-composer-content__input"
        type="text"
        :placeholder="t('posts.composerTagsPlaceholder')"
      />
    </label>
  </div>
</template>

<style scoped lang="scss">
.post-composer-content {
  display: grid;
  gap: 1rem;
}

.post-composer-content__types {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.post-composer-content__pill {
  padding: 0.4rem 0.85rem;
  border-radius: 999px;
  border: 1px solid var(--border, #e5e7eb);
  background: var(--surface-2, #f3f4f6);
  font: inherit;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  color: var(--text-muted, #6b7280);

  &.is-active {
    background: var(--btn-primary-bg, #2563eb);
    color: var(--btn-primary-fg, #fff);
    border-color: transparent;
  }
}

.post-composer-content__block {
  display: grid;
  gap: 0.35rem;
  margin: 0;
}

.post-composer-content__label {
  font-size: 0.875rem;
  font-weight: 600;
}

.post-composer-content__input,
.post-composer-content__textarea {
  padding: 0.55rem 0.65rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  background: var(--bg, #fff);
  color: inherit;
  font: inherit;
  width: 100%;
  box-sizing: border-box;
}

.post-composer-content__input--title {
  font-size: 1.1rem;
  font-weight: 600;
}

.post-composer-content__subtabs {
  display: inline-flex;
  gap: 0.2rem;
  background: var(--surface-2, #f3f4f6);
  border-radius: 0.5rem;
  padding: 0.2rem;
  margin-bottom: 0.35rem;
}

.post-composer-content__subtab {
  padding: 0.3rem 0.75rem;
  border: none;
  background: transparent;
  border-radius: 0.35rem;
  font: inherit;
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
  color: var(--text-muted, #6b7280);

  &.is-active {
    background: var(--bg, #fff);
    color: var(--text, #111827);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  }
}

.post-composer-content__preview {
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

.post-composer-content__summaryRow {
  display: flex;
  align-items: center;
}

.post-composer-content__linkish {
  border: none;
  background: none;
  padding: 0;
  font: inherit;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--link-color, #2563eb);
  cursor: pointer;
  text-decoration: underline;
}
</style>
