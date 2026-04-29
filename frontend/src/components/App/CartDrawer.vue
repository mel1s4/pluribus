<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Icon from '../../atoms/Icon.vue'
import { useCart } from '../../composables/useCart'
import { useCommunity } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'
import { formatOfferPrice } from '../../utils/formatPrice'

const router = useRouter()
const { communityCurrencyCode } = useCommunity()

const {
  drawerOpen,
  closeDrawer,
  cartGroups,
  cartTotal,
  lineCount,
  cartLoading,
  cartError,
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

function goFullCart() {
  closeDrawer()
  void router.push({ name: 'cart' })
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
    closeDrawer()
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
</script>

<template>
  <Teleport to="body">
    <Transition name="cart-drawer-fade">
      <div
        v-if="drawerOpen"
        class="cart-drawer__backdrop"
        role="presentation"
        @click.self="closeDrawer"
      />
    </Transition>
    <Transition name="cart-drawer-slide">
      <aside
        v-if="drawerOpen"
        class="cart-drawer"
        role="dialog"
        aria-modal="true"
        :aria-label="t('cart.drawerTitle')"
      >
        <div class="cart-drawer__head">
          <h2 class="cart-drawer__title">{{ t('cart.drawerTitle') }}</h2>
          <button type="button" class="cart-drawer__close" :aria-label="t('cart.close')" @click="closeDrawer">
            <Icon name="xmark" aria-hidden="true" />
          </button>
        </div>

        <p v-if="cartLoading" class="cart-drawer__state">{{ t('cart.loading') }}</p>
        <p v-else-if="cartError" class="cart-drawer__state cart-drawer__state--err">{{ cartError }}</p>

        <div v-else-if="lineCount <= 0" class="cart-drawer__empty">
          <Icon class="cart-drawer__emptyIcon" name="cart-shopping" aria-hidden="true" />
          <p>{{ t('cart.empty') }}</p>
          <Button type="button" variant="secondary" @click="goFullCart">{{ t('cart.browsePlaces') }}</Button>
        </div>

        <div v-else class="cart-drawer__body">
          <div class="cart-drawer__groups">
            <section
              v-for="(g, gi) in cartGroups"
              :key="gi"
              class="cart-drawer__group"
            >
              <h3 v-if="g && g.place && g.place.name" class="cart-drawer__place">
                {{ g.place.name }}
              </h3>
              <ul class="cart-drawer__lines" role="list">
                <li
                  v-for="(line, li) in (g && g.items) || []"
                  :key="li"
                  class="cart-drawer__line"
                >
                  <div v-if="offerPhoto(line.offer)" class="cart-drawer__thumbWrap">
                    <img class="cart-drawer__thumb" :src="offerPhoto(line.offer)" alt="" loading="lazy" />
                  </div>
                  <div class="cart-drawer__lineBody">
                    <p class="cart-drawer__lineTitle">{{ offerTitle(line.offer) }}</p>
                    <p class="cart-drawer__linePrice">{{ formatPrice(offerPrice(line.offer)) }}</p>
                    <div class="cart-drawer__qtyRow">
                      <button
                        type="button"
                        class="cart-drawer__qtyBtn"
                        :aria-label="t('cart.decreaseQty')"
                        @click="onDelta(getOfferId(line.offer), -1)"
                      >
                        <Icon name="minus" aria-hidden="true" />
                      </button>
                      <span class="cart-drawer__qty">{{ lineQty(line) }}</span>
                      <button
                        type="button"
                        class="cart-drawer__qtyBtn"
                        :aria-label="t('cart.increaseQty')"
                        @click="onDelta(getOfferId(line.offer), 1)"
                      >
                        <Icon name="plus" aria-hidden="true" />
                      </button>
                      <button
                        type="button"
                        class="cart-drawer__remove"
                        :aria-label="t('cart.remove')"
                        @click="onRemove(getOfferId(line.offer))"
                      >
                        <Icon name="trash" aria-hidden="true" />
                      </button>
                    </div>
                  </div>
                </li>
              </ul>
              <p v-if="g && g.subtotal" class="cart-drawer__subtotal">
                {{ t('cart.subtotalPlace') }}: <strong>{{ formatPrice(g.subtotal) }}</strong>
              </p>
            </section>
          </div>

          <label class="cart-drawer__notesLabel">
            <span>{{ t('cart.notesOptional') }}</span>
            <textarea v-model="checkoutNotes" class="cart-drawer__notes" rows="2" maxlength="2000" />
          </label>

          <p v-if="checkoutErr" class="cart-drawer__checkoutErr" role="alert">{{ checkoutErr }}</p>

          <div class="cart-drawer__footer">
            <p class="cart-drawer__total">
              {{ t('cart.total') }}: <strong>{{ formatPrice(cartTotal) }}</strong>
            </p>
            <div class="cart-drawer__actions">
              <Button type="button" variant="secondary" @click="onClear">{{ t('cart.clear') }}</Button>
              <Button type="button" variant="secondary" @click="goFullCart">{{ t('cart.viewFullCart') }}</Button>
              <Button
                type="button"
                variant="primary"
                :disabled="checkoutBusy"
                @click="onCheckout"
              >
                {{ checkoutBusy ? t('cart.placing') : t('cart.placeOrder') }}
              </Button>
            </div>
          </div>
        </div>
      </aside>
    </Transition>
  </Teleport>
</template>

<style lang="scss" scoped>
.cart-drawer__backdrop {
  position: fixed;
  inset: 0;
  z-index: 90;
  background: rgba(15, 23, 42, 0.45);
}

.cart-drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  z-index: 91;
  width: min(100vw - 2rem, 22rem);
  max-width: 100vw;
  background: var(--bg, #fff);
  box-shadow: -8px 0 32px rgba(15, 23, 42, 0.12);
  display: flex;
  flex-direction: column;
  border-radius: 1rem 0 0 1rem;
}

.cart-drawer__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 1rem 1rem 0.75rem;
  border-bottom: 1px solid var(--border);
}

.cart-drawer__title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
}

.cart-drawer__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border: none;
  border-radius: 0.75rem;
  background: rgba(15, 23, 42, 0.06);
  cursor: pointer;
  color: inherit;
}

.cart-drawer__close :deep(svg) {
  width: 1.1rem;
  height: 1.1rem;
}

.cart-drawer__state {
  margin: 1rem;
  font-size: 0.9rem;
  color: var(--text-muted, #64748b);
}

.cart-drawer__state--err {
  color: #b91c1c;
}

.cart-drawer__empty {
  padding: 2rem 1.25rem;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}

.cart-drawer__emptyIcon {
  width: 2.5rem;
  height: 2.5rem;
  opacity: 0.35;
}

.cart-drawer__body {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.cart-drawer__groups {
  flex: 1;
  overflow: auto;
  padding: 0.75rem 1rem;
}

.cart-drawer__group {
  margin-bottom: 1.25rem;
}

.cart-drawer__place {
  margin: 0 0 0.5rem;
  font-size: 0.85rem;
  font-weight: 700;
  color: #0d9488;
  letter-spacing: 0.02em;
}

.cart-drawer__lines {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.cart-drawer__line {
  display: flex;
  gap: 0.65rem;
  padding: 0.65rem;
  border-radius: 0.85rem;
  border: 1px solid var(--border);
  background: var(--card-bg, rgba(255, 255, 255, 0.6));
}

.cart-drawer__thumbWrap {
  flex: 0 0 3.25rem;
}

.cart-drawer__thumb {
  width: 3.25rem;
  height: 3.25rem;
  object-fit: cover;
  border-radius: 0.5rem;
}

.cart-drawer__lineBody {
  flex: 1;
  min-width: 0;
}

.cart-drawer__lineTitle {
  margin: 0 0 0.2rem;
  font-size: 0.9rem;
  font-weight: 600;
}

.cart-drawer__linePrice {
  margin: 0 0 0.35rem;
  font-size: 0.8rem;
  color: var(--text-muted, #64748b);
}

.cart-drawer__qtyRow {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.cart-drawer__qtyBtn {
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

.cart-drawer__qtyBtn :deep(svg) {
  width: 0.85rem;
  height: 0.85rem;
}

.cart-drawer__qty {
  min-width: 1.5rem;
  text-align: center;
  font-weight: 600;
  font-size: 0.9rem;
}

.cart-drawer__remove {
  margin-left: auto;
  width: 2rem;
  height: 2rem;
  border: none;
  background: transparent;
  cursor: pointer;
  color: #94a3b8;
  border-radius: 0.5rem;
}

.cart-drawer__remove:hover {
  color: #b91c1c;
  background: rgba(185, 28, 28, 0.08);
}

.cart-drawer__subtotal {
  margin: 0.35rem 0 0;
  font-size: 0.85rem;
  text-align: right;
}

.cart-drawer__notesLabel {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 0 1rem;
  font-size: 0.8rem;
  color: var(--text-muted, #64748b);
}

.cart-drawer__notes {
  font: inherit;
  padding: 0.5rem 0.65rem;
  border-radius: 0.65rem;
  border: 1px solid var(--border);
  resize: vertical;
  min-height: 2.5rem;
}

.cart-drawer__checkoutErr {
  margin: 0.5rem 1rem 0;
  font-size: 0.85rem;
  color: #b91c1c;
}

.cart-drawer__footer {
  padding: 0.75rem 1rem calc(0.75rem + env(safe-area-inset-bottom, 0px));
  border-top: 1px solid var(--border);
  background: linear-gradient(180deg, transparent, rgba(13, 148, 136, 0.06));
}

.cart-drawer__total {
  margin: 0 0 0.65rem;
  font-size: 1rem;
}

.cart-drawer__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.cart-drawer-fade-enter-active,
.cart-drawer-fade-leave-active {
  transition: opacity 0.2s ease;
}

.cart-drawer-fade-enter-from,
.cart-drawer-fade-leave-to {
  opacity: 0;
}

.cart-drawer-slide-enter-active,
.cart-drawer-slide-leave-active {
  transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

.cart-drawer-slide-enter-from,
.cart-drawer-slide-leave-to {
  transform: translateX(100%);
}
</style>
