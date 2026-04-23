<template>
  <section class="page page--contact">
    <Title tag="h1">{{ t('contact.title') }}</Title>
    <p>{{ t('contact.body') }}</p>
  </section>
</template>

<script setup>
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'
</script>

<style lang="scss" scoped>
.page--contact {
  padding: 2rem;
}
</style>
