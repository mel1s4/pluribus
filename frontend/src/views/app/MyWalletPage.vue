<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import Title from '../../atoms/Title.vue'
import WalletBalanceCard from '../../components/Wallet/WalletBalanceCard.vue'
import WalletTransactionList from '../../components/Wallet/WalletTransactionList.vue'
import WalletAuditLedgerPanel from '../../components/Wallet/WalletAuditLedgerPanel.vue'
import WalletStatsBar from '../../components/Wallet/WalletStatsBar.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { useActiveCommunity } from '../../composables/useActiveCommunity.js'
import { useSession, sessionUser } from '../../composables/useSession.js'
import { useWalletCommunityScope } from '../../composables/useWalletCommunityScope.js'
import { t } from '../../i18n/i18n'
import { ApiTimeoutError } from '../../services/api.js'
import { fetchWallet } from '../../services/walletApi.js'

const route = useRoute()
const { withCommunityPath } = useActiveCommunity()
const { status: sessionStatus } = useSession()
const { memberships, showCommunityPicker, communityScope, communityIdNum } = useWalletCommunityScope()

const currencyName = ref('')
const currencyCode = ref('')
const balance = ref('0.00')
const publicRef = ref('')
const transactions = ref([])
const loading = ref(false)
const loadError = ref('')

const canGrant = computed(() => hasCapability('wallet.grant'))
const canAuditLedger = computed(() => hasCapability('wallet.audit_ledger'))
const canTransfer = computed(() => hasCapability('wallet.transfer'))

const sendHref = computed(() => withCommunityPath('/wallet/send'))

function firstApiErrorMessage(data, fallback) {
  if (data && typeof data === 'object') {
    if (typeof data.message === 'string' && data.message.trim()) {
      return data.message.trim()
    }
    const errs = data.errors
    if (errs && typeof errs === 'object') {
      for (const k of Object.keys(errs)) {
        const arr = errs[k]
        if (Array.isArray(arr) && typeof arr[0] === 'string' && arr[0].trim()) {
          return arr[0].trim()
        }
      }
    }
  }
  return fallback
}

watch(
  () => ({
    name: route.name,
    slug: typeof route.params.communitySlug === 'string' ? route.params.communitySlug.trim() : '',
    userCommunities: sessionUser.value?.communities,
  }),
  ({ name, slug, userCommunities }) => {
    const scoped =
      name === 'walletScoped'
      || name === 'walletSendScoped'
      || name === 'walletMovementScoped'
    if (!scoped || !slug) {
      return
    }
    const list = Array.isArray(userCommunities) ? userCommunities : []
    const row = list.find((c) => c && String(c.slug || '').trim() === slug)
    if (row && row.id != null) {
      communityScope.value = String(row.id)
    }
  },
  { immediate: true },
)

async function loadWallet() {
  const cid = communityIdNum.value
  if (!cid) return
  loading.value = true
  loadError.value = ''
  try {
    const res = await fetchWallet(cid, { page: 1, perPage: 50 })
    loading.value = false
    if (!res.ok) {
      loadError.value = firstApiErrorMessage(res.data, t('wallet.loadError'))
      currencyName.value = ''
      currencyCode.value = ''
      return
    }
    const cur = res.data?.currency
    if (cur && typeof cur === 'object') {
      currencyName.value = typeof cur.name === 'string' ? cur.name : ''
      currencyCode.value = typeof cur.code === 'string' ? cur.code : ''
    } else {
      currencyName.value = ''
      currencyCode.value = ''
    }
    const w = res.data?.wallet
    if (w && typeof w === 'object') {
      balance.value = String(w.balance ?? '0.00')
      publicRef.value = String(w.public_ref ?? '')
    }
    const list = Array.isArray(res.data?.data) ? res.data.data : []
    transactions.value = list
  } catch (e) {
    loading.value = false
    loadError.value =
      e instanceof ApiTimeoutError ? t('wallet.loadErrorTimeout') : t('wallet.loadErrorNetwork')
    currencyName.value = ''
    currencyCode.value = ''
  }
}

watch(
  communityIdNum,
  (id) => {
    if (id > 0) loadWallet()
  },
  { immediate: true },
)
</script>

<template>
  <section class="wallet-app wallet-app--overview">
    <PageToolbarTitle route-key="my-wallet">
      <Title tag="h1">{{ t('wallet.title') }}</Title>
    </PageToolbarTitle>

    <div v-if="showCommunityPicker" class="wallet-app__scope">
      <label class="wallet-app__scope-label">
        {{ t('wallet.communityLabel') }}
        <select v-model="communityScope" class="wallet-app__scope-select">
          <option v-for="c in memberships" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
        </select>
      </label>
    </div>

    <p v-if="loadError" class="wallet-app__err">{{ loadError }}</p>

    <div v-if="communityIdNum > 0" class="wallet-app__stack">
      <WalletBalanceCard
        :balance="balance"
        :public-ref="publicRef"
        :currency-name="currencyName"
        :currency-code="currencyCode"
      />
      <WalletStatsBar v-if="canGrant" :community-id="communityIdNum" />
      <RouterLink v-if="canTransfer" class="wallet-app__cta" :to="sendHref">
        {{ t('wallet.openSendCredits') }}
      </RouterLink>
      <WalletTransactionList :items="transactions" :loading="loading" />
      <WalletAuditLedgerPanel v-if="canAuditLedger" :community-id="communityIdNum" />
    </div>

    <p v-else-if="sessionStatus === 'unknown'" class="wallet-app__muted">{{ t('wallet.loading') }}</p>

    <div v-else-if="memberships.length === 0" class="wallet-app__empty">
      <p class="wallet-app__empty-text">{{ t('wallet.noCommunitiesBody') }}</p>
      <RouterLink class="wallet-app__cta wallet-app__cta--secondary" :to="{ name: 'myCommunities' }">
        {{ t('wallet.noCommunitiesCta') }}
      </RouterLink>
    </div>

    <p v-else class="wallet-app__muted">{{ t('wallet.loading') }}</p>
  </section>
</template>

<style scoped lang="scss">
.wallet-app--overview {
  padding: 0 1.125rem 2rem;
  max-width: 40rem;
  margin: 0 auto;
}

.wallet-app__stack {
  display: flex;
  flex-direction: column;
  gap: 1.75rem;
}

.wallet-app__scope {
  margin-bottom: 0.25rem;
}

.wallet-app__scope-label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.875rem;
  font-weight: 600;
  max-width: 22rem;
}

.wallet-app__scope-select {
  padding: 0.55rem 0.65rem;
  border-radius: 0.5rem;
  border: 1px solid var(--color-border, #d4d4d8);
  font: inherit;
}

.wallet-app__err {
  margin: 0 0 1rem;
  color: var(--color-danger-text, #b91c1c);
  font-size: 0.9rem;
}

.wallet-app__muted {
  margin: 0;
  color: var(--muted, #6b7280);
  font-size: 0.95rem;
}

.wallet-app__empty {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-top: 0.25rem;
}

.wallet-app__empty-text {
  margin: 0;
  color: var(--muted, #6b7280);
  font-size: 0.95rem;
  line-height: 1.5;
}

.wallet-app__cta {
  display: block;
  width: 100%;
  text-align: center;
  text-decoration: none;
  padding: 0.65rem 1rem;
  border-radius: 0.5rem;
  font-weight: 700;
  font-size: 0.95rem;
  background: var(--color-primary, #2563eb);
  color: #fff;
  border: none;
  box-sizing: border-box;
}

.wallet-app__cta:hover {
  filter: brightness(1.05);
}

.wallet-app__cta--secondary {
  background: var(--color-surface-raised, #f4f4f5);
  color: var(--color-text, #18181b);
  border: 1px solid var(--color-border, #d4d4d8);
}

.wallet-app__cta--secondary:hover {
  filter: brightness(0.98);
}
</style>
