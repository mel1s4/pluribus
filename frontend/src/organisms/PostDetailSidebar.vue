<script setup>
import Card from '../atoms/Card.vue'
import { t } from '../i18n/i18n'

defineProps({
  postTypeLabel: { type: String, required: true },
  updatedDisplay: { type: String, required: true },
})
</script>

<template>
  <aside class="post-detail-sidebar" aria-label="Details">
    <Card class="post-detail-sidebar__card">
      <h2 class="post-detail-sidebar__title">{{ t('posts.detailSidebarTitle') }}</h2>
      <dl class="post-detail-sidebar__meta">
        <div class="post-detail-sidebar__row">
          <dt>{{ t('posts.typeLabel') }}</dt>
          <dd>{{ postTypeLabel }}</dd>
        </div>
        <div class="post-detail-sidebar__row">
          <dt>{{ t('posts.detailUpdatedLabel') }}</dt>
          <dd>{{ updatedDisplay }}</dd>
        </div>
      </dl>
      <p class="post-detail-sidebar__hint">{{ t('posts.detailSidebarHint') }}</p>
    </Card>
  </aside>
</template>

<style scoped lang="scss">
.post-detail-sidebar__card {
  margin: 0;
  position: sticky;
  top: 0.75rem;
}

.post-detail-sidebar__title {
  margin: 0 0 0.5rem;
  font-size: 0.95rem;
}

.post-detail-sidebar__meta {
  margin: 0;
  display: grid;
  gap: 0.65rem;
}

.post-detail-sidebar__row {
  display: grid;
  gap: 0.15rem;

  dt {
    margin: 0;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted, #64748b);
  }

  dd {
    margin: 0;
    font-size: 0.9rem;
  }
}

.post-detail-sidebar__hint {
  margin: 0.75rem 0 0;
  font-size: 0.8rem;
  color: var(--text-muted, #64748b);
  line-height: 1.4;
}
</style>
