<script setup>
import Title from '../../atoms/Title.vue'
import { t } from '../../i18n/i18n'

defineProps({
  balance: { type: String, required: true },
  publicRef: { type: String, required: true },
})
</script>

<template>
  <section class="wallet-balance-card">
    <Title tag="h2" class="wallet-balance-card__title">{{ t('wallet.balanceLabel') }}</Title>
    <p class="wallet-balance-card__amount">{{ balance }}</p>
    <p class="wallet-balance-card__ref">
      <span class="wallet-balance-card__ref-label">{{ t('wallet.publicRef') }}</span>
      <code class="wallet-balance-card__ref-code">{{ publicRef }}</code>
    </p>
  </section>
</template>

<style scoped lang="scss">
.wallet-balance-card {
  padding: 1.2rem 1.15rem;
  border-radius: 0.65rem;
  border: 1px solid var(--color-border, #e4e4e7);
  background: var(--bg, #fff);
  margin-bottom: 0;
}

.wallet-balance-card__title {
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin: 0 0 0.45rem;
  color: var(--color-muted, #71717a);
}

.wallet-balance-card__amount {
  font-size: 2rem;
  font-weight: 600;
  margin: 0 0 0.75rem;
}

.wallet-balance-card__ref {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-muted, #71717a);
}

.wallet-balance-card__ref-label {
  display: block;
  margin-bottom: 0.25rem;
}

.wallet-balance-card__ref-code {
  font-size: 0.75rem;
  word-break: break-all;
}
</style>
