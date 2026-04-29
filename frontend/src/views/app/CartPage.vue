<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Icon from '../../atoms/Icon.vue'
import Title from '../../atoms/Title.vue'
import { useCart } from '../../composables/useCart'
import { useCommunity } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'
import { formatOfferPrice } from '../../utils/formatPrice'

const router = useRouter()
const { communityCurrencyCode } = useCommunity()

const {
  cartGroups,
  cartTotal,
  lineCount,
  cartLoading,
  cartError,
  refreshCart,
  upsertItem,
  removeFromCart,
  clearCartRemote,
  checkout,
} = useCart()

const checkoutNotes = ref('')
const checkoutBusy = ref(false)
const checkoutErr = ref('')

function formatPrice(amount) {
  return formatOfferPrice(amount, communityCurrencyCode.value)
}

/**
 * @param {unknown} o
 */
function getOfferId(o) {
  if (o && typeof o === 'object' && 'id' in o) {
    return Number(o.id)
  }
  return 0
}

/**
 * @param {unknown} o
 */
function offerTitle(o) {
  if (o && typeof o === 'object' && 'title' in o && typeof o.title === 'string') {
    return o.title
  }
  return ''
}

/**
 * @param {unknown} o
 */
function offerPrice(o) {
  if (o && typeof o === 'object' && 'price' in o) {
    return o.price
  }
  return '0'
}

/**
 * @param {unknown} o
 */
function offerPhoto(o) {
  if (o && typeof o === 'object' && 'photo_url' in o && typeof o.photo_url === 'string') {
    return o.photo_url
  }
  return ''
}

/**
 * @param {unknown} line
 */
function lineQty(line) {
  if (line && typeof line === 'object' && 'quantity' in line && typeof line.quantity === 'number') {
    return line.quantity
  }
  return 0
}

async function onDelta(oid, delta) {
  checkoutErr.value = ''
  const id = Number(oid)
  const line = findLineByOfferId(id)
  const next = (line ? lineQty(line) : 0) + delta
  if (next <= 0) {
    await removeFromCart(id)
    return
  }
  await upsertItem(id, next)
}

function findLineByOfferId(offerId) {
  for (const g of cartGroups.value) {
    const items = g && typeof g === 'object' && Array.isArray(g.items) ? g.items : []
    for (const line of items) {
      const o = line && typeof line === 'object' && line.offer ? line.offer : null
      if (getOfferId(o) === offerId) {
        return line
      }
    }
  }
  return null
}

async function onRemove(offerId) {
  checkoutErr.value = ''
  await removeFromCart(offerId)
}

async function onClear() {
  checkoutErr.value = ''
  if (!window.confirm(t('cart.clearConfirm'))) return
  await clearCartRemote()
}

async function onCheckout() {
  checkoutErr.value = ''
  if (lineCount.value <= 0) return
  checkoutBusy.value = true
  try {
    const order = await checkout(checkoutNotes.value)
    checkoutNotes.value = ''
    const id = order && typeof order === 'object' && 'id' in order ? order.id : null
    if (id != null) {
      await router.push({ name: 'orderDetail', params: { orderId: String(id) } })
    }
  } catch (e) {
    checkoutErr.value = e instanceof Error ? e.message : String(e)
  } finally {
    checkoutBusy.value = false
  }
}

void refreshCart()
</script>

<template>
  <div class="cart-page">
    <Title tag="h1" class="cart-page__title">{{ t('cart.title') }}</Title>

    <p v-if="cartLoading" class="cart-page__muted">{{ t('cart.loading') }}</p>
    <p v-else-if="cartError" class="cart-page__err" role="alert">{{ cartError }}</p>

    <Card v-else-if="lineCount <= 0" class="cart-page__card cart-page__emptyCard">
      <Icon class="cart-page__emptyIcon" name="cart-shopping" aria-hidden="true" />
      <p>{{ t('cart.empty') }}</p>
      <Button type="button" variant="primary" @click="router.push({ name: 'map' })">
        {{ t('cart.browsePlaces') }}
      </Button>
    </Card>

    <template v-else>
      <div class="cart-page__groups">
        <Card
          v-for="(g, gi) in cartGroups"
          :key="gi"
          class="cart-page__card"
        >
          <h2 v-if="g && g.place && g.place.name" class="cart-page__place">
            {{ g.place.name }}
          </h2>
          <ul class="cart-page__lines" role="list">
            <li
              v-for="(line, li) in (g && g.items) || []"
              :key="li"
              class="cart-page__line"
            >
              <div v-if="offerPhoto(line.offer)" class="cart-page__thumbWrap">
                <img class="cart-page__thumb" :src="offerPhoto(line.offer)" alt="" loading="lazy" />
              </div>
              <div class="cart-page__lineBody">
                <p class="cart-page__lineTitle">{{ offerTitle(line.offer) }}</p>
                <p class="cart-page__linePrice">{{ formatPrice(offerPrice(line.offer)) }}</p>
                <div class="cart-page__qtyRow">
                  <button
                    type="button"
                    class="cart-page__qtyBtn"
                    :aria-label="t('cart.decreaseQty')"
                    @click="onDelta(getOfferId(line.offer), -1)"
                  >
                    <Icon name="minus" aria-hidden="true" />
                  </button>
                  <span class="cart-page__qty">{{ lineQty(line) }}</span>
                  <button
                    type="button"
                    class="cart-page__qtyBtn"
                    :aria-label="t('cart.increaseQty')"
                    @click="onDelta(getOfferId(line.offer), 1)"
                  >
                    <Icon name="plus" aria-hidden="true" />
                  </button>
                  <button
                    type="button"
                    class="cart-page__remove"
                    :aria-label="t('cart.remove')"
                    @click="onRemove(getOfferId(line.offer))"
                  >
                    <Icon name="trash" aria-hidden="true" />
                  </button>
                </div>
              </div>
            </li>
          </ul>
          <p v-if="g && g.subtotal" class="cart-page__subtotal">
            {{ t('cart.subtotalPlace') }}: <strong>{{ formatPrice(g.subtotal) }}</strong>
          </p>
        </Card>
      </div>

      <Card class="cart-page__card cart-page__checkoutCard">
        <label class="cart-page__notesLabel">
          <span>{{ t('cart.notesOptional') }}</span>
          <textarea v-model="checkoutNotes" class="cart-page__notes" rows="3" maxlength="2000" />
        </label>
        <p v-if="checkoutErr" class="cart-page__checkoutErr" role="alert">{{ checkoutErr }}</p>
        <p class="cart-page__total">
          {{ t('cart.total') }}: <strong>{{ formatPrice(cartTotal) }}</strong>
        </p>
        <div class="cart-page__actions">
          <Button type="button" variant="secondary" @click="onClear">{{ t('cart.clear') }}</Button>
          <Button type="button" variant="primary" :disabled="checkoutBusy" @click="onCheckout">
            {{ checkoutBusy ? t('cart.placing') : t('cart.placeOrder') }}
          </Button>
        </div>
      </Card>
    </template>
  </div>
</template>

<style lang="scss" scoped>
.cart-page {
  max-width: 40rem;
  margin: 0 auto;
  padding: 1rem 1rem 2.5rem;
}

.cart-page__title {
  margin: 0 0 1rem;
}

.cart-page__muted {
  color: var(--text-muted, #64748b);
}

.cart-page__err {
  color: #b91c1c;
}

.cart-page__card {
  margin-bottom: 1rem;
}

.cart-page__emptyCard {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  text-align: center;
  padding: 2rem 1rem;
}

.cart-page__emptyIcon {
  width: 2.5rem;
  height: 2.5rem;
  opacity: 0.35;
}

.cart-page__place {
  margin: 0 0 0.75rem;
  font-size: 1rem;
  font-weight: 700;
  color: #0d9488;
}

.cart-page__lines {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.cart-page__line {
  display: flex;
  gap: 0.75rem;
  padding: 0.65rem;
  border-radius: 0.85rem;
  border: 1px solid var(--border);
}

.cart-page__thumbWrap {
  flex: 0 0 3.5rem;
}

.cart-page__thumb {
  width: 3.5rem;
  height: 3.5rem;
  object-fit: cover;
  border-radius: 0.5rem;
}

.cart-page__lineBody {
  flex: 1;
  min-width: 0;
}

.cart-page__lineTitle {
  margin: 0 0 0.2rem;
  font-weight: 600;
}

.cart-page__linePrice {
  margin: 0 0 0.35rem;
  font-size: 0.9rem;
  color: var(--text-muted, #64748b);
}

.cart-page__qtyRow {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.cart-page__qtyBtn {
  width: 2rem;
  height: 2rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  background: var(--bg);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.cart-page__qtyBtn :deep(svg) {
  width: 0.85rem;
  height: 0.85rem;
}

.cart-page__qty {
  min-width: 1.5rem;
  text-align: center;
  font-weight: 600;
}

.cart-page__remove {
  margin-left: auto;
  width: 2rem;
  height: 2rem;
  border: none;
  background: transparent;
  cursor: pointer;
  color: #94a3b8;
  border-radius: 0.5rem;
}

.cart-page__remove:hover {
  color: #b91c1c;
  background: rgba(185, 28, 28, 0.08);
}

.cart-page__subtotal {
  margin: 0.75rem 0 0;
  text-align: right;
  font-size: 0.9rem;
}

.cart-page__checkoutCard {
  position: sticky;
  bottom: 1rem;
  box-shadow: 0 8px 28px rgba(15, 23, 42, 0.08);
}

.cart-page__notesLabel {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.85rem;
  color: var(--text-muted, #64748b);
  margin-bottom: 0.75rem;
}

.cart-page__notes {
  font: inherit;
  padding: 0.5rem 0.65rem;
  border-radius: 0.65rem;
  border: 1px solid var(--border);
}

.cart-page__checkoutErr {
  color: #b91c1c;
  font-size: 0.9rem;
  margin: 0 0 0.5rem;
}

.cart-page__total {
  margin: 0 0 0.75rem;
  font-size: 1.05rem;
}

.cart-page__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
</style>
