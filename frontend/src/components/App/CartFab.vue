<script setup>
import Icon from '../../atoms/Icon.vue'
import { useCart } from '../../composables/useCart'
import { t } from '../../i18n/i18n'

const { lineCount, toggleDrawer } = useCart()
</script>

<template>
  <button
    type="button"
    class="cart-fab"
    :aria-label="t('header.openCart')"
    @click="toggleDrawer"
  >
    <Icon name="cart-shopping" aria-hidden="true" />
    <span v-if="lineCount > 0" class="cart-fab__badge">{{ lineCount > 99 ? '99+' : lineCount }}</span>
  </button>
</template>

<style lang="scss" scoped>
.cart-fab {
  position: fixed;
  right: 1rem;
  bottom: calc(4.25rem + env(safe-area-inset-bottom, 0px));
  z-index: 34;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: 50%;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
  color: #fff;
  box-shadow: 0 6px 20px rgba(13, 148, 136, 0.45);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.cart-fab:hover {
  transform: scale(1.04);
  box-shadow: 0 8px 24px rgba(13, 148, 136, 0.55);
}

.cart-fab:active {
  transform: scale(0.96);
}

.cart-fab :deep(svg) {
  width: 1.35rem;
  height: 1.35rem;
}

.cart-fab__badge {
  position: absolute;
  top: -0.15rem;
  right: -0.15rem;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.3rem;
  border-radius: 999px;
  background: #ef4444;
  color: #fff;
  font-size: 0.65rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

@media (min-width: 1024px) {
  .cart-fab {
    display: none;
  }
}
</style>
