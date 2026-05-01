<script setup>
import { ref } from 'vue'
import Button from '../../atoms/Button.vue'
import { t } from '../../i18n/i18n'
import { fetchWalletAuditLedger } from '../../services/walletApi.js'

const props = defineProps({
  communityId: { type: Number, required: true },
})

const rows = ref([])
const loading = ref(false)
const error = ref('')
const loaded = ref(false)

function actorLabel(kind) {
  if (kind === 'community_grant') return t('wallet.auditActorGrant')
  return t('wallet.auditActorTransfer')
}

async function load() {
  error.value = ''
  loading.value = true
  const res = await fetchWalletAuditLedger(props.communityId, { page: 1, perPage: 100 })
  loading.value = false
  if (!res.ok) {
    error.value = t('wallet.auditError')
    return
  }
  const data = res.data && typeof res.data === 'object' && Array.isArray(res.data.data) ? res.data.data : []
  rows.value = data
  loaded.value = true
}
</script>

<template>
  <section class="wallet-audit-panel">
    <h2 class="wallet-audit-panel__title">{{ t('wallet.auditHeading') }}</h2>
    <p class="wallet-audit-panel__intro">{{ t('wallet.auditIntro') }}</p>
    <Button type="button" class="wallet-audit-panel__btn" :disabled="loading" @click="load">
      {{ loading ? t('wallet.auditLoading') : t('wallet.auditLoad') }}
    </Button>
    <p v-if="error" class="wallet-audit-panel__err">{{ error }}</p>
    <p v-else-if="loaded && !rows.length" class="wallet-audit-panel__muted">{{ t('wallet.auditEmpty') }}</p>
    <table v-else-if="rows.length" class="wallet-audit-panel__table">
      <thead>
        <tr>
          <th>{{ t('wallet.auditColId') }}</th>
          <th>{{ t('wallet.auditColType') }}</th>
          <th>{{ t('wallet.amount') }}</th>
          <th>{{ t('wallet.auditColFrom') }}</th>
          <th>{{ t('wallet.auditColTo') }}</th>
          <th>{{ t('wallet.auditColActor') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in rows" :key="r.id">
          <td>{{ r.id }}</td>
          <td>{{ r.type === 'grant' ? t('wallet.typeGrant') : t('wallet.typeTransfer') }}</td>
          <td>{{ r.amount }}</td>
          <td class="wallet-audit-panel__mono">{{ r.from_public_ref || '—' }}</td>
          <td class="wallet-audit-panel__mono">{{ r.to_public_ref }}</td>
          <td>{{ actorLabel(r.actor_kind) }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<style scoped lang="scss">
.wallet-audit-panel {
  margin-top: 0;
  padding-top: 1.5rem;
  border-top: 1px solid var(--color-border, #e4e4e7);
}

.wallet-audit-panel__title {
  font-size: 1.05rem;
  font-weight: 800;
  margin: 0 0 0.5rem;
  letter-spacing: -0.02em;
}

.wallet-audit-panel__intro {
  font-size: 0.85rem;
  line-height: 1.45;
  color: var(--color-muted, #71717a);
  margin: 0 0 1rem;
}

.wallet-audit-panel__btn {
  margin-bottom: 0.75rem;
}

.wallet-audit-panel__err {
  color: var(--color-danger-text, #b91c1c);
}

.wallet-audit-panel__muted {
  color: var(--color-muted, #71717a);
}

.wallet-audit-panel__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.wallet-audit-panel__table th,
.wallet-audit-panel__table td {
  text-align: left;
  padding: 0.35rem 0.5rem;
  border-bottom: 1px solid var(--color-border, #e4e4e7);
}

.wallet-audit-panel__mono {
  font-family: ui-monospace, monospace;
  word-break: break-all;
  max-width: 10rem;
}
</style>
