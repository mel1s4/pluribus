import { onMounted, onUnmounted, ref } from 'vue'

const DESKTOP_MQ = '(min-width: 1024px)'

/**
 * Tracks whether the viewport matches desktop breakpoint (sidebar in flow, not drawer).
 */
export function useDesktopViewport() {
  const isDesktop = ref(
    typeof window !== 'undefined' && window.matchMedia(DESKTOP_MQ).matches
  )
  let mql

  function update() {
    if (typeof window === 'undefined') return
    isDesktop.value = window.matchMedia(DESKTOP_MQ).matches
  }

  onMounted(() => {
    update()
    mql = window.matchMedia(DESKTOP_MQ)
    mql.addEventListener('change', update)
  })

  onUnmounted(() => {
    mql?.removeEventListener('change', update)
  })

  return { isDesktop }
}
