<script setup>
import { computed } from 'vue'
import { postVideoEmbedSrc } from '../utils/postVideoEmbedSrc'
import { t } from '../i18n/i18n'

const props = defineProps({
  /** Original link saved on the post (YouTube or Google Drive). */
  videoUrl: {
    type: String,
    default: '',
  },
})

const embedSrc = computed(() => postVideoEmbedSrc(props.videoUrl || ''))
const showUnsupported = computed(() => Boolean((props.videoUrl || '').trim()) && !embedSrc.value)
</script>

<template>
  <div v-if="embedSrc" class="post-video-embed">
    <div class="post-video-embed__frame">
      <iframe
        class="post-video-embed__iframe"
        :src="embedSrc"
        title="Video"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        allowfullscreen
        referrerpolicy="strict-origin-when-cross-origin"
      />
    </div>
  </div>
  <p v-else-if="showUnsupported" class="post-video-embed__fallback">
    {{ t('posts.videoUnsupportedHint') }}
    <a
      class="post-video-embed__link"
      :href="videoUrl"
      target="_blank"
      rel="noopener noreferrer"
    >{{ t('posts.videoOpenLink') }}</a>
  </p>
</template>

<style scoped lang="scss">
.post-video-embed {
  width: 100%;
}

.post-video-embed__frame {
  position: relative;
  width: 100%;
  padding-bottom: 56.25%;
  height: 0;
  overflow: hidden;
  border-radius: 0.65rem;
  background: #0f172a;
  border: 1px solid var(--border, #e5e7eb);
}

.post-video-embed__iframe {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  border: 0;
}

.post-video-embed__fallback {
  margin: 0;
  font-size: 0.9rem;
  color: var(--text-muted, #64748b);
}

.post-video-embed__link {
  color: var(--link-color, #2563eb);
}
</style>
