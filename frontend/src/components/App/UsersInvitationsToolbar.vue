<script setup>
import { ref } from 'vue'
import Button from '../../atoms/Button.vue'
import UsersInvitationModals from './UsersInvitationModals.vue'
import { t } from '../../i18n/i18n'

const modalsRef = ref(null)

function openSendDialog() {
  modalsRef.value?.openSend()
}

function openLinkDialog() {
  modalsRef.value?.openLink()
}

function openQrDialog() {
  modalsRef.value?.openQr()
}
</script>

<template>
  <div class="users-invitations-toolbar">
    <Button type="button" variant="secondary" @click="openSendDialog">
      {{ t('users.inviteSendCta') }}
    </Button>
    <Button type="button" variant="secondary" @click="openLinkDialog">
      {{ t('users.inviteLinkCta') }}
    </Button>
    <Button type="button" variant="secondary" @click="openQrDialog">
      {{ t('users.inviteQrCta') }}
    </Button>
    <UsersInvitationModals ref="modalsRef" />
  </div>
</template>

<style lang="scss" scoped>
.users-invitations-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}
</style>
