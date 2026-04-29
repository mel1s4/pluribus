<script setup>
import { computed, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import Button from '../atoms/Button.vue'
import { useCart } from '../composables/useCart'
import { useCommunity } from '../composables/useCommunity'
import { sessionStatus } from '../composables/useSession'
import { t } from '../i18n/i18n'
import { formatOfferPrice } from '../utils/formatPrice'

const props = defineProps({
  offers: {
    type: Array,
    default: () => [],
  },
  /** When set and user is signed in, show add-to-cart controls */
  placeId: {
    type: [Number, String],
    default: null,
  },
})

const route = useRoute()
const { communityCurrencyCode } = useCommunity()
const { cartData, upsertItem } = useCart()

const busyOfferId = ref(null)

function formatPrice(amount) {
  return formatOfferPrice(amount, communityCurrencyCode.value)
}

const list = computed(() => (Array.isArray(props.offers) ? props.offers : []))

const showCart = computed(
  () => sessionStatus.value === 'authenticated' && props.placeId != null && props.placeId !== '',
)

const qtyByOfferId = computed(() => {
  /** @type {Map<number, number>} */
  const map = new Map()
  const data = cartData.value
  if (data && typeof data === 'object' && Array.isArray(data.items)) {
    for (const line of data.items) {
      if (!line || typeof line !== 'object') continue
      const o = line.offer
      const id = o && typeof o === 'object' && 'id' in o ? Number(o.id) : 0
      const q = typeof line.quantity === 'number' ? line.quantity : 0
      if (id) {
        map.set(id, q)
      }
    }
  }
  return map
})

/**
 * @param {unknown} offer
 */
function qtyFor(offer) {
  if (!offer || typeof offer !== 'object' || !('id' in offer)) return 0
  const id = Number(offer.id)
  return qtyByOfferId.value.get(id) ?? 0
}

const loginRedirect = computed(() => ({
  name: 'login',
  query: { redirect: route.fullPath },
}))

/**
 * @param {unknown} offer
 */
async function addOne(offer) {
  if (!showCart.value || !offer || typeof offer !== 'object' || !('id' in offer)) return
  const id = Number(offer.id)
  busyOfferId.value = id
  try {
    const next = qtyFor(offer) + 1
    await upsertItem(id, next)
  } finally {
    busyOfferId.value = 0
  }
}

/**
 * @param {unknown} offer
 */
async function removeOne(offer) {
  if (!showCart.value || !offer || typeof offer !== 'object' || !('id' in offer)) return
  const id = Number(offer.id)
  const q = qtyFor(offer)
  if (q <= 0) return
  busyOfferId.value = id
  try {
    await upsertItem(id, q - 1)
  } finally {
    busyOfferId.value = 0
  }
}
</script>

<template>
  <div class="place-offers-public">
    <p v-if="list.length === 0" class="place-offers-public__empty">
      {{ t('places.viewOffersEmpty') }}
    </p>
    <ul v-else class="place-offers-public__list" role="list">
      <li v-for="o in list" :key="o && o.id != null ? o.id : String(o)" class="place-offers-public__card">
        <div v-if="o.photo_url" class="place-offers-public__photoWrap">
          <img
            :src="o.photo_url"
            :alt="o.title || ''"
            class="place-offers-public__photo"
            loading="lazy"
          />
        </div>
        <div class="place-offers-public__body">
          <h3 class="place-offers-public__title">{{ o.title }}</h3>
          <p v-if="o.description" class="place-offers-public__desc">{{ o.description }}</p>
          <p class="place-offers-public__price">{{ formatPrice(o.price) }}</p>
          <ul
            v-if="Array.isArray(o.tags) && o.tags.length"
            class="place-offers-public__tags"
            aria-label="Tags"
          >
            <li v-for="(tag, i) in o.tags" :key="i" class="place-offers-public__tag">
              {{ tag }}
            </li>
          </ul>

          <div v-if="placeId" class="place-offers-public__cartRow">
            <template v-if="showCart">
              <div class="place-offers-public__qty">
                <button
                  type="button"
                  class="place-offers-public__qtyBtn"
                  :disabled="busyOfferId === o.id || qtyFor(o) <= 0"
                  :aria-label="t('cart.decreaseQty')"
                  @click="removeOne(o)"
                >
                  −
                </button>
                <span class="place-offers-public__qtyVal">{{ qtyFor(o) }}</span>
                <button
                  type="button"
                  class="place-offers-public__qtyBtn"
                  :disabled="busyOfferId === o.id"
                  :aria-label="t('cart.increaseQty')"
                  @click="addOne(o)"
                >
                  +
                </button>
              </div>
              <span v-if="qtyFor(o) > 0" class="place-offers-public__inCart">{{ t('cart.inCart') }}</span>
              <Button
                v-else
                type="button"
                variant="primary"
                size="sm"
                :disabled="busyOfferId === o.id"
                @click="addOne(o)"
              >
                {{ busyOfferId === o.id ? '…' : t('cart.add') }}
              </Button>
            </template>
            <RouterLink v-else class="place-offers-public__login" :to="loginRedirect">
              {{ t('cart.loginToOrder') }}
            </RouterLink>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

<style lang="scss" scoped>
.place-offers-public__empty {
  margin: 0;
  font-size: 0.9rem;
  opacity: 0.8;
}

.place-offers-public__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.place-offers-public__card {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  background: var(--card-bg, transparent);
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.place-offers-public__card:hover {
  box-shadow: 0 4px 18px rgba(13, 148, 136, 0.12);
}

.place-offers-public__photoWrap {
  flex: 0 0 8rem;
  max-width: 100%;
}

.place-offers-public__photo {
  width: 100%;
  height: auto;
  border-radius: 0.5rem;
  display: block;
}

.place-offers-public__body {
  flex: 1 1 12rem;
  min-width: 0;
}

.place-offers-public__title {
  margin: 0 0 0.35rem;
  font-size: 1.05rem;
}

.place-offers-public__desc {
  margin: 0 0 0.5rem;
  font-size: 0.9rem;
  opacity: 0.9;
  white-space: pre-wrap;
}

.place-offers-public__price {
  margin: 0 0 0.35rem;
  font-weight: 600;
}

.place-offers-public__tags {
  list-style: none;
  margin: 0.35rem 0 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.place-offers-public__tag {
  font-size: 0.75rem;
  padding: 0.15rem 0.45rem;
  border-radius: 0.35rem;
  background: rgba(37, 99, 235, 0.1);
  color: #1d4ed8;
}

.place-offers-public__cartRow {
  margin-top: 0.75rem;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.place-offers-public__qty {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border-radius: 999px;
  border: 1px solid var(--border);
  padding: 0.15rem 0.35rem;
  background: rgba(13, 148, 136, 0.06);
}

.place-offers-public__qtyBtn {
  width: 1.85rem;
  height: 1.85rem;
  border: none;
  border-radius: 50%;
  background: #fff;
  cursor: pointer;
  font-size: 1.1rem;
  font-weight: 700;
  line-height: 1;
  color: #0f766e;
}

.place-offers-public__qtyBtn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.place-offers-public__qtyVal {
  min-width: 1.25rem;
  text-align: center;
  font-weight: 700;
  font-size: 0.9rem;
}

.place-offers-public__inCart {
  font-size: 0.8rem;
  font-weight: 600;
  color: #0d9488;
}

.place-offers-public__login {
  font-size: 0.85rem;
  font-weight: 600;
  color: #0d9488;
}
</style>
