<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import Title from '../../atoms/Title.vue'
import WalletSendForm from '../../components/Wallet/WalletSendForm.vue'
import { useActiveCommunity } from '../../composables/useActiveCommunity.js'
import { useWalletCommunityScope } from '../../composables/useWalletCommunityScope.js'
import { t } from '../../i18n/i18n'
import { fetchWallet } from '../../services/walletApi.js'

const router = useRouter()
const { withCommunityPath } = useActiveCommunity()
const { memberships, showCommunityPicker, communityScope, communityIdNum } = useWalletCommunityScope()

const currencyName = ref('')
const currencyCode = ref('')

async function loadCurrency() {
  const cid = communityIdNum.value
  if (!cid) {
    currencyName.value = ''
    currencyCode.value = ''
    return
  }
  const res = await fetchWallet(cid, { page: 1, perPage: 1 })
  if (!res.ok) {
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
}

watch(
  communityIdNum,
  () => {
    void loadCurrency()
  },
  { immediate: true },
)

function onSent() {
  router.push(withCommunityPath('/wallet'))
}
</script>

<template>
  <section class="wallet-app wallet-app--send">
    <PageToolbarTitle route-key="my-wallet">
      <Title tag="h1">{{ t('wallet.sendPageTitle') }}</Title>
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

    <WalletSendForm
      v-if="communityIdNum > 0"
      :community-id="communityIdNum"
      :currency-name="currencyName"
      :currency-code="currencyCode"
      @sent="onSent"
    />
  </section>
</template>

<style scoped lang="scss">
.wallet-app--send {
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
</style>
