<script setup>
import { computed, ref, watch } from 'vue'
import Button from '../../atoms/Button.vue'
import Title from '../../atoms/Title.vue'
import { useWalletCommunityScope } from '../../composables/useWalletCommunityScope.js'
import { t } from '../../i18n/i18n'
import { fetchPlaceWallet, postPlaceWalletTransfer } from '../../services/placesApi.js'
import { formatOfferPrice } from '../../utils/formatPrice.js'

const props = defineProps({
  placeId: { type: [Number, String], required: true },
  canTransfer: { type: Boolean, default: false },
})

const { communityIdNum } = useWalletCommunityScope()

const loading = ref(true)
const loadError = ref('')
const balance = ref('0.00')
const currencyCode = ref(/** @type {string | null} */ (null))
const currencyName = ref(/** @type {string | null} */ (null))
const transactions = ref(/** @type {unknown[]} */ ([]))

const transferEmail = ref('')
const transferAmount = ref('')
const transferNote = ref('')
const transferBusy = ref(false)
const transferErr = ref('')
const transferOk = ref(false)

const balanceLabel = computed(() =>
  formatOfferPrice(balance.value, currencyCode.value),
)

async function load() {
  const id = props.placeId
  if (id == null || id === '') return
  loading.value = true
  loadError.value = ''
  const { ok, status, data } = await fetchPlaceWallet(id, { page: 1, perPage: 30 })
  loading.value = false
  if (!ok) {
    loadError.value = t('placeWallet.loadError').replace('{status}', String(status))
    return
  }
  const cur = data?.currency
  if (cur && typeof cur === 'object') {
    currencyCode.value = typeof cur.code === 'string' ? cur.code : null
    currencyName.value = typeof cur.name === 'string' ? cur.name : null
  }
  const w = data?.wallet
  if (w && typeof w === 'object') {
    balance.value = String(w.balance ?? '0.00')
  }
  transactions.value = Array.isArray(data?.data) ? data.data : []
}

watch(
  () => [props.placeId, communityIdNum.value],
  () => {
    if (communityIdNum.value > 0) load()
  },
  { immediate: true },
)

function txTypeLabel(type) {
  if (type === 'grant') return t('wallet.typeGrant')
  if (type === 'order_settlement') return t('wallet.typeOrderSettlement')
  return t('wallet.typeTransfer')
}

function directionLabel(direction) {
  if (direction === 'in') return t('wallet.directionIn')
  return t('wallet.directionOut')
}

async function onTransfer() {
  transferErr.value = ''
  transferOk.value = false
  const amt = Number(transferAmount.value)
  if (!Number.isFinite(amt) || amt <= 0) {
    transferErr.value = t('placeWallet.invalidAmount')
    return
  }
  transferBusy.value = true
  const { ok, data, status } = await postPlaceWalletTransfer(props.placeId, {
    recipient_email: transferEmail.value.trim(),
    amount: amt,
    note: transferNote.value.trim() || undefined,
  })
  transferBusy.value = false
  if (!ok) {
    let msg = `HTTP ${status}`
    if (data && typeof data === 'object' && 'message' in data && typeof data.message === 'string') {
      msg = data.message
    }
    transferErr.value = msg
    return
  }
  transferOk.value = true
  transferEmail.value = ''
  transferAmount.value = ''
  transferNote.value = ''
  await load()
}
</script>

<template>
  <div class="place-wallet-section">
    <Title tag="h2" class="place-wallet-section__title">{{ t('placeWallet.title') }}</Title>
    <p v-if="loading" class="place-wallet-section__muted">{{ t('wallet.loading') }}</p>
    <p v-else-if="loadError" class="place-wallet-section__err">{{ loadError }}</p>
    <template v-else>
      <p class="place-wallet-section__balance">
        {{ t('placeWallet.balance') }}: <strong>{{ balanceLabel }}</strong>
        <span v-if="currencyName" class="place-wallet-section__muted"> ({{ currencyName }})</span>
      </p>

      <section v-if="canTransfer" class="place-wallet-section__transfer">
        <Title tag="h3" class="place-wallet-section__sub">{{ t('placeWallet.sendToMember') }}</Title>
        <label class="place-wallet-section__field">
          <span>{{ t('wallet.recipientEmail') }}</span>
          <input v-model="transferEmail" type="email" class="place-wallet-section__input" autocomplete="email" />
        </label>
        <label class="place-wallet-section__field">
          <span>{{ t('wallet.amount') }}</span>
          <input v-model="transferAmount" type="number" step="0.01" min="0.01" class="place-wallet-section__input" />
        </label>
        <label class="place-wallet-section__field">
          <span>{{ t('wallet.noteOptional') }}</span>
          <input v-model="transferNote" type="text" class="place-wallet-section__input" maxlength="2000" />
        </label>
        <p v-if="transferErr" class="place-wallet-section__err">{{ transferErr }}</p>
        <p v-if="transferOk" class="place-wallet-section__ok">{{ t('placeWallet.transferSent') }}</p>
        <Button type="button" variant="primary" :disabled="transferBusy" @click="onTransfer">
          {{ transferBusy ? t('wallet.sending') : t('placeWallet.submitTransfer') }}
        </Button>
      </section>

      <Title tag="h3" class="place-wallet-section__sub">{{ t('wallet.transactionsHeading') }}</Title>
      <p v-if="!transactions.length" class="place-wallet-section__muted">{{ t('wallet.emptyTx') }}</p>
      <ul v-else class="place-wallet-section__tx">
        <li v-for="tx in transactions" :key="tx.id" class="place-wallet-section__txItem">
          <div class="place-wallet-section__txRow">
            <span>{{ directionLabel(tx.direction) }}</span>
            <span>{{ tx.amount }}</span>
          </div>
          <div class="place-wallet-section__txMeta">
            {{ txTypeLabel(tx.type) }} · {{ tx.counterparty_label }}
            <span v-if="tx.counterparty_masked_email"> · {{ tx.counterparty_masked_email }}</span>
          </div>
          <div v-if="tx.note" class="place-wallet-section__txNote">{{ tx.note }}</div>
          <div class="place-wallet-section__txDate">{{ tx.created_at }}</div>
        </li>
      </ul>
    </template>
  </div>
</template>

<style scoped lang="scss">
.place-wallet-section__title {
  margin: 0 0 1rem;
  font-size: 1.15rem;
}

.place-wallet-section__sub {
  margin: 1.25rem 0 0.65rem;
  font-size: 1rem;
}

.place-wallet-section__balance {
  margin: 0 0 0.5rem;
}

.place-wallet-section__muted {
  color: var(--text-muted, #64748b);
}

.place-wallet-section__err {
  color: #b91c1c;
  margin: 0.5rem 0;
}

.place-wallet-section__ok {
  color: #15803d;
  margin: 0.5rem 0;
}

.place-wallet-section__transfer {
  margin-bottom: 1.5rem;
  padding: 1rem;
  border-radius: 0.75rem;
  border: 1px solid var(--border, #e2e8f0);
}

.place-wallet-section__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  margin-bottom: 0.75rem;
  font-size: 0.9rem;
}

.place-wallet-section__input {
  padding: 0.45rem 0.55rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border, #e2e8f0);
}

.place-wallet-section__tx {
  list-style: none;
  margin: 0;
  padding: 0;
}

.place-wallet-section__txItem {
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--border, #e2e8f0);
}

.place-wallet-section__txRow {
  display: flex;
  justify-content: space-between;
  font-weight: 600;
}

.place-wallet-section__txMeta {
  font-size: 0.88rem;
  color: var(--text-muted, #64748b);
}

.place-wallet-section__txNote {
  font-size: 0.85rem;
  margin-top: 0.25rem;
}

.place-wallet-section__txDate {
  font-size: 0.8rem;
  color: var(--text-muted, #94a3b8);
  margin-top: 0.2rem;
}
</style>
