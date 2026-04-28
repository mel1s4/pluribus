<script setup>
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import { t } from '../../i18n/i18n'

const router = useRouter()
</script>

<template>
  <div class="users-members-toolbar">
    <Button type="button" variant="primary" @click="router.push({ name: 'userCreate' })">
      {{ t('users.createUserCta') }}
    </Button>
  </div>
</template>

<style lang="scss" scoped>
.users-members-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}
</style>
