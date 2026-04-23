import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

const MOBILE_MQ = '(max-width: 1023px)'

function isMobileViewport() {
  return typeof window !== 'undefined' && window.matchMedia(MOBILE_MQ).matches
}

/**
 * Hides fixed header + bottom nav on scroll-down, shows on scroll-up (mobile only).
 * When not mobile or header hidden, chrome is always considered visible.
 */
export function useMobileChromeScroll(showHeader) {
  const route = useRoute()
  const chromeVisible = ref(true)
  let lastY = 0

  function resetScrollState() {
    lastY = window.scrollY
    chromeVisible.value = true
  }

  function tick() {
    if (!showHeader.value || !isMobileViewport()) {
      chromeVisible.value = true
      return
    }

    const y = window.scrollY
    if (y < 24) {
      chromeVisible.value = true
    } else {
      const delta = y - lastY
      if (delta > 6) {
        chromeVisible.value = false
      } else if (delta < -6) {
        chromeVisible.value = true
      }
    }
    lastY = y
  }

  let raf = 0
  function onScroll() {
    cancelAnimationFrame(raf)
    raf = requestAnimationFrame(tick)
  }

  function onResize() {
    if (!isMobileViewport()) {
      chromeVisible.value = true
    }
    lastY = window.scrollY
  }

  onMounted(() => {
    lastY = window.scrollY
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onResize)
  })

  onUnmounted(() => {
    cancelAnimationFrame(raf)
    window.removeEventListener('scroll', onScroll)
    window.removeEventListener('resize', onResize)
  })

  watch(showHeader, (v) => {
    if (v) {
      resetScrollState()
    } else {
      chromeVisible.value = true
    }
  })

  watch(
    () => route.fullPath,
    () => {
      resetScrollState()
    }
  )

  return { chromeVisible, resetScrollState }
}
