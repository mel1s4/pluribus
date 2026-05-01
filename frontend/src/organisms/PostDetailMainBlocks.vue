<script setup>
import Card from '../atoms/Card.vue'
import PostVideoEmbed from '../molecules/PostVideoEmbed.vue'
import { t } from '../i18n/i18n'

defineProps({
  post: { type: Object, required: true },
})
</script>

<template>
  <div class="post-detail-main-blocks">
    <PostVideoEmbed v-if="post.video_url" :video-url="post.video_url" />

    <Card v-if="post.description" class="post-detail-main-blocks__card">
      <h2 class="post-detail-main-blocks__h">{{ t('posts.detailSummaryHeading') }}</h2>
      <p class="post-detail-main-blocks__summary">{{ post.description }}</p>
    </Card>

    <Card v-if="post.content_markdown" class="post-detail-main-blocks__card">
      <h2 class="post-detail-main-blocks__h">{{ t('posts.detailBodyHeading') }}</h2>
      <pre class="post-detail-main-blocks__body">{{ post.content_markdown }}</pre>
    </Card>

    <Card v-if="Array.isArray(post.tags) && post.tags.length" class="post-detail-main-blocks__card">
      <h2 class="post-detail-main-blocks__h">{{ t('posts.detailTagsHeading') }}</h2>
      <ul class="post-detail-main-blocks__tags">
        <li v-for="(tag, i) in post.tags" :key="i" class="post-detail-main-blocks__tag">{{ tag }}</li>
      </ul>
    </Card>
  </div>
</template>

<style scoped lang="scss">
.post-detail-main-blocks {
  display: grid;
  gap: 1rem;
  min-width: 0;
}

.post-detail-main-blocks__card {
  margin: 0;
}

.post-detail-main-blocks__h {
  margin: 0 0 0.5rem;
  font-size: 0.95rem;
}

.post-detail-main-blocks__summary {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
}

.post-detail-main-blocks__body {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
  font-family: ui-monospace, monospace;
  font-size: 0.85rem;
  line-height: 1.45;
}

.post-detail-main-blocks__tags {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.post-detail-main-blocks__tag {
  font-size: 0.8rem;
  padding: 0.2rem 0.5rem;
  border-radius: 0.35rem;
  background: var(--surface-2, #f1f5f9);
}
</style>
