<script setup>
import { RouterLink } from 'vue-router'
import Title from '../../atoms/Title.vue'
import { useActiveCommunity } from '../../composables/useActiveCommunity.js'
import { t } from '../../i18n/i18n'

defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const { withCommunityPath } = useActiveCommunity()

function movementHref(tx) {
  return withCommunityPath(`/wallet/movements/${tx.id}`)
}

function directionLabel(direction) {
  if (direction === 'in') return t('wallet.directionIn')
  return t('wallet.directionOut')
}

function typeLabel(type) {
  if (type === 'grant') return t('wallet.typeGrant')
  return t('wallet.typeTransfer')
}
</script>

<template>
  <section class="wallet-tx-list">
    <Title tag="h2" class="wallet-tx-list__heading">{{ t('wallet.transactionsHeading') }}</Title>
    <p v-if="loading" class="wallet-tx-list__muted">{{ t('wallet.loading') }}</p>
    <p v-else-if="!items.length" class="wallet-tx-list__muted">{{ t('wallet.emptyTx') }}</p>
    <ul v-else class="wallet-tx-list__items">
      <li v-for="tx in items" :key="tx.id" class="wallet-tx-list__item">
        <RouterLink class="wallet-tx-list__link" :to="movementHref(tx)">
          <div class="wallet-tx-list__row">
            <span class="wallet-tx-list__dir">{{ directionLabel(tx.direction) }}</span>
            <span class="wallet-tx-list__amount">{{ tx.amount }}</span>
          </div>
          <div class="wallet-tx-list__meta">
            {{ typeLabel(tx.type) }} · {{ tx.counterparty_label }}
            <span v-if="tx.counterparty_masked_email"> · {{ tx.counterparty_masked_email }}</span>
          </div>
          <div v-if="tx.note" class="wallet-tx-list__note">{{ tx.note }}</div>
          <div class="wallet-tx-list__date">{{ tx.created_at }}</div>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>

<style scoped lang="scss">
.wallet-tx-list__heading {
  margin: 1.25rem 0 0.85rem;
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.wallet-tx-list__muted {
  margin: 0;
  color: var(--color-muted, #71717a);
}

.wallet-tx-list__items {
  list-style: none;
  margin: 0;
  padding: 0;
}

.wallet-tx-list__item {
  border-bottom: 1px solid var(--color-border, #e4e4e7);
}

.wallet-tx-list__link {
  display: block;
  padding: 0.85rem 0.35rem;
  text-decoration: none;
  color: inherit;
  border-radius: 0.35rem;
  margin: 0 -0.35rem;
}

.wallet-tx-list__link:hover {
  background: var(--btn-bg-hover, rgba(0, 0, 0, 0.04));
}

.wallet-tx-list__link:focus-visible {
  outline: 2px solid var(--color-primary, #2563eb);
  outline-offset: 2px;
}

.wallet-tx-list__row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.wallet-tx-list__amount {
  font-weight: 600;
}

.wallet-tx-list__meta {
  font-size: 0.875rem;
  color: var(--color-muted, #71717a);
  margin-top: 0.25rem;
}

.wallet-tx-list__note {
  font-size: 0.8125rem;
  margin-top: 0.25rem;
}

.wallet-tx-list__date {
  font-size: 0.75rem;
  color: var(--color-muted, #71717a);
  margin-top: 0.25rem;
}
</style>
