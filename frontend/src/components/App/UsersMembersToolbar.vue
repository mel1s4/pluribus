<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import UsersInvitationModals from './UsersInvitationModals.vue'
import { t } from '../../i18n/i18n'

const router = useRouter()
const modalsRef = ref(null)

function openSendDialog() {
  modalsRef.value?.openSend()
}

function openLinkDialog() {
  modalsRef.value?.openLink()
}
</script>

<template>
  <div class="users-members-toolbar">
    <Button type="button" variant="primary" @click="router.push({ name: 'userCreate' })">
      {{ t('users.createUserCta') }}
    </Button>
    <Button type="button" variant="secondary" @click="openSendDialog">
      {{ t('users.inviteSendCta') }}
    </Button>
    <Button type="button" variant="secondary" @click="openLinkDialog">
      {{ t('users.inviteLinkCta') }}
    </Button>
    <UsersInvitationModals ref="modalsRef" />
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
