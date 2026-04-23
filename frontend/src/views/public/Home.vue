<template>
  <section class="page page--home">
    <Title tag="h1">{{ t('home.title') }}</Title>
    <p>{{ welcomeLine }}</p>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useCommunity } from '../../composables/useCommunity'
import { language, t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'

const { displayName } = useCommunity()

const welcomeLine = computed(() => {
  void language.value
  return t('home.welcome').replace('{name}', displayName.value)
})
</script>

<style lang="scss" scoped>
.page--home {
  padding: 2rem;
}
</style>
