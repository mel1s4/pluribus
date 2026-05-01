<script setup>
import { ref, watch } from 'vue'
import { t } from '../../i18n/i18n'
import { fetchWalletCommunityStats } from '../../services/walletApi.js'

const props = defineProps({
  communityId: { type: Number, required: true },
  refreshKey: { type: Number, default: 0 },
})

const total = ref('')
const error = ref('')

async function load() {
  error.value = ''
  const res = await fetchWalletCommunityStats(props.communityId)
  if (!res.ok) {
    error.value = t('wallet.statsLoadError')
    total.value = ''
    return
  }
  const v =
    res.data && typeof res.data === 'object' && res.data.credits_granted_total != null
      ? String(res.data.credits_granted_total)
      : ''
  total.value = v
}

watch(
  () => [props.communityId, props.refreshKey],
  () => {
    load()
  },
  { immediate: true },
)
</script>

<template>
  <section class="wallet-stats-bar">
    <h2 class="wallet-stats-bar__title">{{ t('wallet.statsHeading') }}</h2>
    <p v-if="error" class="wallet-stats-bar__err">{{ error }}</p>
    <p v-else class="wallet-stats-bar__line">
      <span class="wallet-stats-bar__label">{{ t('wallet.statsTotal') }}:</span>
      <strong class="wallet-stats-bar__value">{{ total || '—' }}</strong>
    </p>
  </section>
</template>

<style scoped lang="scss">
.wallet-stats-bar {
  margin-bottom: 0;
  padding: 1rem 1.1rem;
  border-radius: 0.65rem;
  border: 1px solid var(--color-border, #e4e4e7);
  background: var(--color-surface-muted, #fafafa);
}

.wallet-stats-bar__title {
  font-size: 0.95rem;
  font-weight: 800;
  margin: 0 0 0.45rem;
  letter-spacing: -0.02em;
}

.wallet-stats-bar__line {
  margin: 0;
  font-size: 0.9375rem;
}

.wallet-stats-bar__err {
  margin: 0;
  color: var(--color-danger-text, #b91c1c);
  font-size: 0.875rem;
}
</style>
