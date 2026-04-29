<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { consumeVisitorLoginLink, setSessionFromLoginUser } from '../../composables/useSession'

const route = useRoute()
const router = useRouter()
const state = ref('loading')
const message = ref('')

onMounted(async () => {
  const token = typeof route.params.token === 'string' ? route.params.token : ''
  if (!token) {
    state.value = 'error'
    message.value = 'Invalid login link.'
    return
  }
  const { ok, data } = await consumeVisitorLoginLink(token)
  if (!ok || !data?.user) {
    state.value = 'error'
    message.value = 'This login link is invalid or expired.'
    return
  }
  setSessionFromLoginUser(data.user)
  state.value = 'success'
  await router.replace('/dashboard')
})
</script>

<template>
  <section class="visitor-auth-consume">
    <p v-if="state === 'loading'">Signing you in...</p>
    <p v-else-if="state === 'error'">{{ message }}</p>
  </section>
</template>

<style scoped lang="scss">
.visitor-auth-consume {
  min-height: 40vh;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
