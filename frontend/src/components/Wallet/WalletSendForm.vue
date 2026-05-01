<script setup>
import { ref } from 'vue'
import Button from '../../atoms/Button.vue'
import { t } from '../../i18n/i18n'
import { postWalletTransfer } from '../../services/walletApi.js'

const props = defineProps({
  communityId: { type: Number, required: true },
})

const emit = defineEmits(['sent'])

const email = ref('')
const amount = ref('')
const note = ref('')
const busy = ref(false)
const message = ref('')
const error = ref('')

async function submit() {
  message.value = ''
  error.value = ''
  busy.value = true
  const res = await postWalletTransfer({
    community_id: props.communityId,
    recipient_email: email.value.trim(),
    amount: Number(amount.value),
    note: note.value.trim() || undefined,
  })
  busy.value = false
  if (!res.ok) {
    const msg =
      res.data && typeof res.data === 'object' && res.data.message
        ? String(res.data.message)
        : t('wallet.sendError', { status: res.status })
    error.value = msg
    return
  }
  message.value = t('wallet.sendSuccess')
  email.value = ''
  amount.value = ''
  note.value = ''
  emit('sent')
}
</script>

<template>
  <section class="wallet-send-form">
    <h2 class="wallet-send-form__title">{{ t('wallet.sendHeading') }}</h2>
    <form class="wallet-send-form__form" @submit.prevent="submit">
      <label class="wallet-send-form__label">
        {{ t('wallet.recipientEmail') }}
        <input v-model="email" type="email" required class="wallet-send-form__input" autocomplete="off" />
      </label>
      <label class="wallet-send-form__label">
        {{ t('wallet.amount') }}
        <input
          v-model="amount"
          type="number"
          step="0.01"
          min="0.01"
          required
          class="wallet-send-form__input"
        />
      </label>
      <label class="wallet-send-form__label">
        {{ t('wallet.noteOptional') }}
        <input v-model="note" type="text" maxlength="2000" class="wallet-send-form__input" />
      </label>
      <Button type="submit" :disabled="busy">
        {{ busy ? t('wallet.sending') : t('wallet.send') }}
      </Button>
    </form>
    <p v-if="message" class="wallet-send-form__ok">{{ message }}</p>
    <p v-if="error" class="wallet-send-form__err">{{ error }}</p>
  </section>
</template>

<style scoped lang="scss">
.wallet-send-form {
  margin-bottom: 0;
}

.wallet-send-form__title {
  font-size: 1.05rem;
  font-weight: 800;
  margin: 0 0 0.85rem;
  letter-spacing: -0.02em;
}

.wallet-send-form__form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  width: 100%;
  max-width: 100%;
}

@media (min-width: 640px) {
  .wallet-send-form__form {
    max-width: 26rem;
  }
}

.wallet-send-form__label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.875rem;
  font-weight: 600;
}

.wallet-send-form__input {
  padding: 0.6rem 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid var(--color-border, #d4d4d8);
  font: inherit;
}

.wallet-send-form__form :deep(.btn),
.wallet-send-form__form :deep(button[type='submit']) {
  width: 100%;
  margin-top: 0.25rem;
}

@media (min-width: 640px) {
  .wallet-send-form__form :deep(.btn),
  .wallet-send-form__form :deep(button[type='submit']) {
    width: auto;
    align-self: flex-start;
  }
}

.wallet-send-form__ok {
  color: var(--color-success-text, #166534);
  margin-top: 0.75rem;
}

.wallet-send-form__err {
  color: var(--color-danger-text, #b91c1c);
  margin-top: 0.75rem;
}
</style>
