<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { consumeTableAccessToken, resolveTableAccessToken } from '../../services/placeTablesApi.js'
import { sessionStatus } from '../../composables/useSession.js'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  const token = typeof route.params.token === 'string' ? route.params.token : ''
  if (!token) {
    loading.value = false
    error.value = 'Invalid table link.'
    return
  }
  const preview = await resolveTableAccessToken(token)
  if (!preview.ok || !preview.data?.valid) {
    loading.value = false
    error.value = 'This table link is invalid or expired.'
    return
  }
  if (sessionStatus.value !== 'authenticated') {
    await router.replace({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  const consumed = await consumeTableAccessToken(token)
  if (!consumed.ok) {
    loading.value = false
    error.value = 'Could not activate table access.'
    return
  }
  const slug = preview.data?.place?.slug
  if (slug) {
    await router.replace({ name: 'placePublic', params: { slug } })
    return
  }
  await router.replace('/map')
})
</script>

<template>
  <section class="table-access-page">
    <p v-if="loading">Opening table...</p>
    <p v-else-if="error">{{ error }}</p>
  </section>
</template>

<style scoped lang="scss">
.table-access-page {
  min-height: 40vh;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
