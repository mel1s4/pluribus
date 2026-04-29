import { computed, ref, watch } from 'vue'
import { sessionStatus } from './useSession.js'
import * as cartApi from '../services/cartApi.js'
import * as ordersApi from '../services/ordersApi.js'

const CART_COUNT_KEY = 'pluribus_cart_line_count_v1'

function readStoredLineCount() {
  if (typeof localStorage === 'undefined') return 0
  try {
    const v = parseInt(localStorage.getItem(CART_COUNT_KEY) || '0', 10)
    return Number.isFinite(v) && v >= 0 ? v : 0
  } catch {
    return 0
  }
}

function writeStoredLineCount(n) {
  if (typeof localStorage === 'undefined') return
  try {
    localStorage.setItem(CART_COUNT_KEY, String(n))
  } catch {
    /* ignore */
  }
}

const cartData = ref(null)
const cartLoading = ref(false)
const cartError = ref('')
const drawerOpen = ref(false)

/** @type {Promise<unknown>|null} */
let refreshInFlight = null

export async function refreshCart() {
  if (sessionStatus.value !== 'authenticated') {
    cartData.value = null
    return null
  }
  if (refreshInFlight) {
    return refreshInFlight
  }
  cartLoading.value = true
  cartError.value = ''
  refreshInFlight = (async () => {
    const { ok, status, data } = await cartApi.fetchCart()
    cartLoading.value = false
    refreshInFlight = null
    if (!ok) {
      const msg =
        data && typeof data === 'object' && 'message' in data && typeof data.message === 'string'
          ? data.message
          : `HTTP ${status}`
      cartError.value = msg
      return null
    }
    cartData.value = data
    const n =
      data && typeof data === 'object' && typeof data.line_count === 'number' ? data.line_count : 0
    writeStoredLineCount(n)
    return data
  })()
  return refreshInFlight
}

const lineCount = computed(() => {
  if (cartData.value && typeof cartData.value.line_count === 'number') {
    return cartData.value.line_count
  }
  return readStoredLineCount()
})

const cartGroups = computed(() =>
  cartData.value && Array.isArray(cartData.value.groups) ? cartData.value.groups : [],
)

const cartTotal = computed(() =>
  cartData.value && cartData.value.total != null ? String(cartData.value.total) : '0.00',
)

watch(
  sessionStatus,
  (s) => {
    if (s === 'authenticated') {
      void refreshCart()
    } else if (s === 'guest') {
      cartData.value = null
      drawerOpen.value = false
    }
  },
  { immediate: true },
)

/**
 * @returns {{
 *   cartData: import('vue').Ref<unknown>,
 *   cartLoading: import('vue').Ref<boolean>,
 *   cartError: import('vue').Ref<string>,
 *   cartGroups: import('vue').ComputedRef<unknown[]>,
 *   cartTotal: import('vue').ComputedRef<string>,
 *   lineCount: import('vue').ComputedRef<number>,
 *   drawerOpen: import('vue').Ref<boolean>,
 *   refreshCart: typeof refreshCart,
 *   openDrawer: () => void,
 *   closeDrawer: () => void,
 *   toggleDrawer: () => void,
 *   upsertItem: (placeOfferId: number|string, quantity: number) => Promise<void>,
 *   removeFromCart: (placeOfferId: number|string) => Promise<void>,
 *   clearCartRemote: () => Promise<void>,
 *   checkout: (notes?: string) => Promise<unknown>,
 * }}
 */
export function useCart() {
  function openDrawer() {
    drawerOpen.value = true
    void refreshCart()
  }

  function closeDrawer() {
    drawerOpen.value = false
  }

  function toggleDrawer() {
    if (drawerOpen.value) {
      closeDrawer()
    } else {
      openDrawer()
    }
  }

  /**
   * @param {number|string} placeOfferId
   * @param {number} quantity
   */
  async function upsertItem(placeOfferId, quantity) {
    const { ok, data, status } = await cartApi.upsertCartItem(placeOfferId, quantity)
    if (!ok) {
      const msg =
        data && typeof data === 'object' && 'message' in data && typeof data.message === 'string'
          ? data.message
          : `HTTP ${status}`
      throw new Error(msg)
    }
    await refreshCart()
  }

  /**
   * @param {number|string} placeOfferId
   */
  async function removeFromCart(placeOfferId) {
    const { ok, data, status } = await cartApi.removeCartItem(placeOfferId)
    if (!ok) {
      const msg =
        data && typeof data === 'object' && 'message' in data && typeof data.message === 'string'
          ? data.message
          : `HTTP ${status}`
      throw new Error(msg)
    }
    await refreshCart()
  }

  async function clearCartRemote() {
    const { ok, data, status } = await cartApi.clearCart()
    if (!ok) {
      const msg =
        data && typeof data === 'object' && 'message' in data && typeof data.message === 'string'
          ? data.message
          : `HTTP ${status}`
      throw new Error(msg)
    }
    await refreshCart()
  }

  /**
   * @param {string} [notes]
   */
  async function checkout(notes) {
    const body = notes && notes.trim() ? { notes: notes.trim() } : {}
    const { ok, data, status } = await ordersApi.createOrder(body)
    if (!ok) {
      let msg = `HTTP ${status}`
      if (data && typeof data === 'object') {
        if ('message' in data && typeof data.message === 'string') {
          msg = data.message
        } else if ('errors' in data && data.errors && typeof data.errors === 'object') {
          const errs = /** @type {Record<string, string[]>} */ (data.errors)
          const first = Object.values(errs)[0]
          if (Array.isArray(first) && first[0]) {
            msg = String(first[0])
          }
        }
      }
      throw new Error(msg)
    }
    await refreshCart()
    const payload = /** @type {{ order?: unknown }} */ (data)
    return payload?.order ?? null
  }

  return {
    cartData,
    cartLoading,
    cartError,
    cartGroups,
    cartTotal,
    lineCount,
    drawerOpen,
    refreshCart,
    openDrawer,
    closeDrawer,
    toggleDrawer,
    upsertItem,
    removeFromCart,
    clearCartRemote,
    checkout,
  }
}
