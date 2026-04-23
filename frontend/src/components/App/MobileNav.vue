<template>
  <div class="mobile-nav" :class="{ 'is-open': open }">
    <div class="mobile-nav__backdrop" @click="$emit('close')" />
    <aside
      class="mobile-nav__drawer"
      role="dialog"
      aria-modal="true"
      :aria-hidden="!open"
      :aria-label="t('nav.sidebar')"
    >
      <Sidebar :open="open" @close="$emit('close')" />
    </aside>
  </div>
</template>

<script setup>
import { onUnmounted, watch } from 'vue'
import Sidebar from './Sidebar.vue'
import { t } from '../../i18n/i18n'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['close'])

watch(
  () => props.open,
  (isOpen) => {
    if (typeof document === 'undefined') return
    document.body.style.overflow = isOpen ? 'hidden' : ''
  },
  { immediate: true }
)

onUnmounted(() => {
  if (typeof document !== 'undefined') {
    document.body.style.overflow = ''
  }
})
</script>

<style lang="scss" scoped>
.mobile-nav {
  display: none;

  @media (max-width: 1023px) {
    display: block;
  }
}

.mobile-nav__backdrop {
  position: fixed;
  inset: 0;
  z-index: 40;
  background: rgba(15, 23, 42, 0.45);
  opacity: 0;
  pointer-events: none;
  transition: opacity 220ms ease;
}

.mobile-nav.is-open .mobile-nav__backdrop {
  opacity: 1;
  pointer-events: auto;
}

.mobile-nav__drawer {
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 45;
  width: min(88vw, 320px);
  max-width: 100%;
  transform: translateX(-100%);
  transition: transform 220ms ease;
  box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12);
  pointer-events: none;

  html[data-theme='dark'] & {
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.45);
  }
}

.mobile-nav.is-open .mobile-nav__drawer {
  transform: translateX(0);
  pointer-events: auto;
}
</style>
