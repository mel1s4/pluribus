<script setup>
import { computed, ref } from 'vue'
import Button from '../../atoms/Button.vue'
import { t } from '../../i18n/i18n'
import { postWalletGrant } from '../../services/walletApi.js'

const props = defineProps({
  communityId: { type: Number, required: true },
  /** When set, grant only to this member (hides email / ID field). */
  recipientUserId: { type: Number, default: null },
  recipientName: { type: String, default: '' },
  /**
   * When true, HTTP/API grant failures are emitted as `grantError` instead of inline text
   * (e.g. parent shows a modal).
   */
  surfaceErrorsToParent: { type: Boolean, default: false },
})

const emit = defineEmits(['granted', 'grantError'])

const hasFixedRecipient = computed(
  () => props.recipientUserId != null && Number.isFinite(props.recipientUserId) && props.recipientUserId > 0,
)

const headingText = computed(() =>
  hasFixedRecipient.value && props.recipientName.trim() !== ''
    ? t('wallet.grantToMemberHeading').replace('{name}', props.recipientName.trim())
    : t('wallet.grantHeading'),
)

const target = ref('')
const amount = ref('')
const note = ref('')
const busy = ref(false)
const message = ref('')
const error = ref('')

async function submit() {
  message.value = ''
  error.value = ''
  let payload
  if (hasFixedRecipient.value) {
    payload = {
      community_id: props.communityId,
      user_id: props.recipientUserId,
      amount: Number(amount.value),
      note: note.value.trim() || undefined,
    }
  } else {
    const raw = target.value.trim()
    if (!raw) {
      error.value = t('wallet.grantHint')
      return
    }
    const asId = /^\d+$/.test(raw) ? Number(raw) : null
    payload =
      asId != null && asId > 0
        ? {
            community_id: props.communityId,
            user_id: asId,
            amount: Number(amount.value),
            note: note.value.trim() || undefined,
          }
        : {
            community_id: props.communityId,
            email: raw,
            amount: Number(amount.value),
            note: note.value.trim() || undefined,
          }
  }
  busy.value = true
  const res = await postWalletGrant(payload)
  busy.value = false
  if (!res.ok) {
    const msg =
      res.data && typeof res.data === 'object' && res.data.message
        ? String(res.data.message)
        : t('wallet.grantError').replace('{status}', String(res.status))
    if (props.surfaceErrorsToParent) {
      emit('grantError', { message: msg })
    } else {
      error.value = msg
    }
    return
  }
  message.value = t('wallet.grantSuccess')
  target.value = ''
  amount.value = ''
  note.value = ''
  emit('granted')
}
</script>

<template>
  <section class="wallet-grant-panel">
    <h2 class="wallet-grant-panel__title">{{ headingText }}</h2>
    <p v-if="!hasFixedRecipient" class="wallet-grant-panel__hint">{{ t('wallet.grantHint') }}</p>
    <p v-else class="wallet-grant-panel__hint">{{ t('wallet.grantToMemberHint') }}</p>
    <form class="wallet-grant-panel__form" @submit.prevent="submit">
      <label v-if="!hasFixedRecipient" class="wallet-grant-panel__label">
        {{ t('wallet.grantEmailOrId') }}
        <input v-model="target" type="text" required class="wallet-grant-panel__input" autocomplete="off" />
      </label>
      <label class="wallet-grant-panel__label">
        {{ t('wallet.amount') }}
        <input
          v-model="amount"
          type="number"
          step="0.01"
          min="0.01"
          required
          class="wallet-grant-panel__input"
        />
      </label>
      <label class="wallet-grant-panel__label">
        {{ t('wallet.noteOptional') }}
        <input v-model="note" type="text" maxlength="2000" class="wallet-grant-panel__input" />
      </label>
      <Button type="submit" :disabled="busy">
        {{ busy ? t('wallet.granting') : t('wallet.grant') }}
      </Button>
    </form>
    <p v-if="message" class="wallet-grant-panel__ok" role="status">{{ message }}</p>
    <p v-if="error" class="wallet-grant-panel__err" role="alert" aria-live="assertive">{{ error }}</p>
  </section>
</template>

<style scoped lang="scss">
.wallet-grant-panel {
  margin-bottom: 0;
  padding: 1.1rem 1.15rem;
  border-radius: 0.65rem;
  border: 1px dashed var(--color-border, #d4d4d8);
  background: var(--color-surface-elevated, #fafafa);
}

.wallet-grant-panel__title {
  font-size: 1.05rem;
  font-weight: 800;
  margin: 0 0 0.45rem;
  letter-spacing: -0.02em;
}

.wallet-grant-panel__hint {
  font-size: 0.85rem;
  color: var(--color-muted, #71717a);
  margin: 0 0 1rem;
  line-height: 1.45;
}

.wallet-grant-panel__form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  width: 100%;
  max-width: 100%;
}

@media (min-width: 640px) {
  .wallet-grant-panel__form {
    max-width: 26rem;
  }
}

.wallet-grant-panel__label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.875rem;
  font-weight: 600;
}

.wallet-grant-panel__input {
  padding: 0.6rem 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid var(--color-border, #d4d4d8);
  font: inherit;
}

.wallet-grant-panel__form :deep(.btn) {
  width: 100%;
  margin-top: 0.25rem;
}

@media (min-width: 640px) {
  .wallet-grant-panel__form :deep(.btn) {
    width: auto;
    align-self: flex-start;
  }
}

.wallet-grant-panel__ok {
  color: var(--color-success-text, #166534);
  margin-top: 0.75rem;
}

.wallet-grant-panel__err {
  color: var(--color-danger-text, #b91c1c);
  margin-top: 0.75rem;
}
</style>
