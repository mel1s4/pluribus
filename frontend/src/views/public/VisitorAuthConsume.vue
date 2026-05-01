<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { consumeVisitorLoginLink, setSessionFromLoginUser } from '../../composables/useSession'

const route = useRoute()
const router = useRouter()
const state = ref('loading')
const message = ref('')

function defaultPostLoginPath(user) {
  const communityCount = Number(user?.community_count || 0)
  if (communityCount <= 0) return '/my-communities'
  const first = Array.isArray(user?.communities) ? user.communities[0] : null
  if (first?.slug) return `/${first.slug}/dashboard`
  return '/dashboard'
}

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
  setSessionFromLoginUser(data.user, data.personification)
  state.value = 'success'
  await router.replace(defaultPostLoginPath(data.user))
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
