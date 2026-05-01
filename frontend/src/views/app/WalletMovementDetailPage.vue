<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import Title from '../../atoms/Title.vue'
import { useActiveCommunity } from '../../composables/useActiveCommunity.js'
import { useWalletCommunityScope } from '../../composables/useWalletCommunityScope.js'
import { t } from '../../i18n/i18n'
import { fetchWalletTransaction } from '../../services/walletApi.js'

const route = useRoute()
const { withCommunityPath } = useActiveCommunity()
const { memberships, showCommunityPicker, communityScope, communityIdNum } = useWalletCommunityScope()

const tx = ref(null)
const loading = ref(false)
const error = ref('')

const transactionId = computed(() => {
  const raw = route.params.transactionId
  const n = Number(raw)
  return Number.isFinite(n) && n > 0 ? n : 0
})

function directionLabel(direction) {
  if (direction === 'in') return t('wallet.directionIn')
  return t('wallet.directionOut')
}

function typeLabel(type) {
  if (type === 'grant') return t('wallet.typeGrant')
  return t('wallet.typeTransfer')
}

async function load() {
  const cid = communityIdNum.value
  const tid = transactionId.value
  if (!cid || !tid) return
  loading.value = true
  error.value = ''
  tx.value = null
  const res = await fetchWalletTransaction(cid, tid)
  loading.value = false
  if (!res.ok) {
    error.value =
      res.status === 404 ? t('wallet.movementNotFound') : t('wallet.movementLoadError', { status: res.status })
    return
  }
  const row = res.data?.transaction
  tx.value = row && typeof row === 'object' ? row : null
  if (!tx.value) {
    error.value = t('wallet.movementNotFound')
  }
}

watch(
  () => [communityIdNum.value, transactionId.value],
  () => {
    load()
  },
  { immediate: true },
)
</script>

<template>
  <section class="wallet-app wallet-app--movement">
    <PageToolbarTitle route-key="my-wallet">
      <Title tag="h1">{{ t('wallet.movementDetailTitle') }}</Title>
    </PageToolbarTitle>

    <p class="wallet-app__back">
      <RouterLink class="wallet-app__back-link" :to="withCommunityPath('/wallet')">
        {{ t('wallet.backToWallet') }}
      </RouterLink>
    </p>

    <div v-if="showCommunityPicker" class="wallet-app__scope">
      <label class="wallet-app__scope-label">
        {{ t('wallet.communityLabel') }}
        <select v-model="communityScope" class="wallet-app__scope-select">
          <option v-for="c in memberships" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
        </select>
      </label>
    </div>

    <p v-if="loading" class="wallet-app__muted">{{ t('wallet.loading') }}</p>
    <p v-else-if="error" class="wallet-app__err">{{ error }}</p>
    <dl v-else-if="tx" class="wallet-app__detail">
      <div class="wallet-app__detail-row">
        <dt>{{ t('wallet.fieldDirection') }}</dt>
        <dd>{{ directionLabel(tx.direction) }}</dd>
      </div>
      <div class="wallet-app__detail-row">
        <dt>{{ t('wallet.fieldAmount') }}</dt>
        <dd class="wallet-app__detail-amount">{{ tx.amount }}</dd>
      </div>
      <div class="wallet-app__detail-row">
        <dt>{{ t('wallet.fieldType') }}</dt>
        <dd>{{ typeLabel(tx.type) }}</dd>
      </div>
      <div class="wallet-app__detail-row">
        <dt>{{ t('wallet.fieldCounterparty') }}</dt>
        <dd>
          {{ tx.counterparty_label }}
          <span v-if="tx.counterparty_masked_email"> · {{ tx.counterparty_masked_email }}</span>
        </dd>
      </div>
      <div v-if="tx.note" class="wallet-app__detail-row">
        <dt>{{ t('wallet.fieldNote') }}</dt>
        <dd>{{ tx.note }}</dd>
      </div>
      <div class="wallet-app__detail-row">
        <dt>{{ t('wallet.fieldDate') }}</dt>
        <dd class="wallet-app__detail-date">{{ tx.created_at }}</dd>
      </div>
    </dl>
  </section>
</template>

<style scoped lang="scss">
.wallet-app--movement {
  padding: 0 1.125rem 1.5rem;
  max-width: 36rem;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.wallet-app__back {
  margin: 0;
}

.wallet-app__back-link {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--color-primary, #2563eb);
  text-decoration: none;
}

.wallet-app__back-link:hover {
  text-decoration: underline;
}

.wallet-app__scope-label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.875rem;
  font-weight: 600;
}

.wallet-app__scope-select {
  padding: 0.55rem 0.65rem;
  border-radius: 0.5rem;
  border: 1px solid var(--color-border, #d4d4d8);
  font: inherit;
}

.wallet-app__muted {
  margin: 0;
  color: var(--color-muted, #71717a);
  font-size: 0.9rem;
}

.wallet-app__err {
  margin: 0;
  color: var(--color-danger-text, #b91c1c);
  font-size: 0.9rem;
}

.wallet-app__detail {
  margin: 0;
  padding: 1rem 1.1rem;
  border: 1px solid var(--color-border, #e4e4e7);
  border-radius: 0.65rem;
  background: var(--color-surface-elevated, #fafafa);
}

.wallet-app__detail-row {
  display: grid;
  gap: 0.2rem;
  padding: 0.65rem 0;
  border-bottom: 1px solid var(--color-border, #e4e4e7);
}

.wallet-app__detail-row:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.wallet-app__detail-row:first-child {
  padding-top: 0;
}

.wallet-app__detail dt {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-muted, #71717a);
}

.wallet-app__detail dd {
  margin: 0;
  font-size: 1rem;
}

.wallet-app__detail-amount {
  font-weight: 700;
  font-size: 1.35rem;
}

.wallet-app__detail-date {
  font-size: 0.85rem;
  color: var(--color-muted, #71717a);
}
</style>
