<template>
  <section class="page page--legal">
    <Title tag="h1">{{ t('legal.title') }}</Title>
    <p>{{ t('legal.body') }}</p>
  </section>
</template>

<script setup>
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'
</script>

<style lang="scss" scoped>
.page--legal {
  padding: 2rem;
}
</style>
