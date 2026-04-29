<template>
  <section class="page page--notifications">
    <PageToolbarTitle route-key="notifications">
      <Title tag="h1">{{ t('notifications.title') }}</Title>
    </PageToolbarTitle>
    <p class="page__muted">{{ t('notifications.placeholder') }}</p>
  </section>
</template>

<script setup>
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
</script>

<style lang="scss" scoped>
.page--notifications {
  padding: 2rem;
}

.page__muted {
  margin-top: 0.75rem;
  opacity: 0.8;
}
</style>
